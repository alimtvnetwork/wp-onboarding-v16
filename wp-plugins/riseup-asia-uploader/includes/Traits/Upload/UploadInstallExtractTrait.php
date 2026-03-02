<?php
/**
 * UploadInstallExtractTrait — Deactivation, extraction, and ZIP processing.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Response;
use ZipArchive;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SelfUpdateStatusType;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Update\SelfUpdateBackupHelper;
use RiseupAsia\Update\SelfUpdateHealthCheck;
use RiseupAsia\Update\SelfUpdateValidator;

trait UploadInstallExtractTrait
{
    /**
     * Deactivate plugin and remove old directory if this is an update.
     */
    private function deactivateIfUpdating(
        $slug,
        $isUpdate,
        $targetDir,
    ) {
        $this->fileLogger->info($isUpdate ? 'Updating existing plugin' : 'Installing new plugin', array('slug' => $slug));

        $isFreshInstall = ($isUpdate === false);

        if ($isFreshInstall) {
            return false;
        }

        $pluginFile = $this->findPluginFile($slug);
        $isPreviouslyActive = false;

        if ($pluginFile) {
            $isPreviouslyActive = is_plugin_active($pluginFile);

            if ($isPreviouslyActive) {
                deactivate_plugins($pluginFile);
            }
        }

        $this->deleteDirectory($targetDir);

        return $isPreviouslyActive;
    }

    /**
     * Process the extraction, activation, and version detection phases.
     */
    private function processUploadExtraction(array $input, array $zipResult) {
        $context = $this->prepareExtractionContext($input, $zipResult);

        // Phase 1: Create backup before self-update
        $backupDir = null;

        if ($context[ResponseKeyType::IsSelfUpdate->value]) {
            $backupHelper = new SelfUpdateBackupHelper($this->fileLogger);
            $backupDir = $backupHelper->createBackup();

            if ($backupDir === false) {
                return $this->errorResponse(
                    SelfUpdateStatusType::BackupCreationFailed->label(),
                    HttpStatusType::ServerError->value,
                );
            }
        }

        $isPreviouslyActive = $this->deactivateIfUpdating(
            $context[ResponseKeyType::Slug->value],
            $context[ResponseKeyType::IsUpdate->value],
            $context['targetDir'],
        );

        $stepResult = $this->executeExtractionSteps($context, $isPreviouslyActive, $input, $backupDir);

        if ($stepResult instanceof WP_REST_Response) {
            return $stepResult;
        }

        // Cleanup backup on success
        if ($backupDir !== null) {
            $backupHelper = new SelfUpdateBackupHelper($this->fileLogger);
            $backupHelper->cleanup($backupDir);
        }

        return $stepResult;
    }

    /** Prepare extraction context from input and ZIP result. */
    private function prepareExtractionContext(array $input, array $zipResult): array {
        $slug      = $zipResult[ResponseKeyType::Slug->value];
        $targetDir = WP_PLUGIN_DIR . '/' . $slug;
        $isUpdate  = is_dir($targetDir);

        $this->removeDuplicatePlugins($slug, WP_PLUGIN_DIR);

        $isSelfUpdate = ($slug === PluginConfigType::Slug->value && $isUpdate);

        if ($isSelfUpdate) {
            $this->preLogSelfUpdate($slug, $input['uploadSource'], $input['clientPluginVersion'], strlen($input['zipContent']));
        }

        return array(
            ResponseKeyType::TempFile->value => $zipResult[ResponseKeyType::TempFile->value],
            ResponseKeyType::Slug->value => $slug,
            'targetDir' => $targetDir,
            ResponseKeyType::IsUpdate->value => $isUpdate,
            ResponseKeyType::IsSelfUpdate->value => $isSelfUpdate,
        );
    }

    /** Execute extraction, validation, activation, and version detection. */
    private function executeExtractionSteps(
        array $ctx,
        bool $isPreviouslyActive,
        array $input,
        ?string $backupDir = null,
    ) {
        $extractResult = $this->extractToPluginsDir($ctx[ResponseKeyType::TempFile->value], $ctx[ResponseKeyType::Slug->value], $ctx['targetDir']);

        if ($extractResult instanceof WP_REST_Response) {
            return $this->rollbackIfNeeded($backupDir, $extractResult, SelfUpdateStatusType::ExtractionFailed);
        }

        // Phase 2: Validate new files before activation (self-update only)
        if ($ctx[ResponseKeyType::IsSelfUpdate->value]) {
            $validator = new SelfUpdateValidator($this->fileLogger);
            $isValid = $validator->validate($ctx['targetDir']);

            if ($isValid === false) {
                $diagnostics = $validator->getDiagnostics();

                $this->fileLogger->error('Self-update validation failed — triggering rollback', array(
                    'slug'        => $ctx[ResponseKeyType::Slug->value],
                    'diagnostics' => $diagnostics,
                ));

                $rollbackResponse = $this->performRollbackAndBuildResponse(
                    $backupDir,
                    $ctx[ResponseKeyType::Slug->value],
                    SelfUpdateStatusType::ValidationFailed,
                    $diagnostics,
                );

                return $rollbackResponse;
            }
        }

        $pluginFile = $this->resetOpcacheAndFindPlugin($ctx[ResponseKeyType::Slug->value]);

        if ($pluginFile instanceof WP_REST_Response) {
            return $this->rollbackIfNeeded($backupDir, $pluginFile, SelfUpdateStatusType::PluginFileNotFound);
        }

        $activation = $this->activateIfNeeded($pluginFile, $ctx[ResponseKeyType::Slug->value], $input['activate'], $isPreviouslyActive, $ctx[ResponseKeyType::IsUpdate->value]);

        if ($activation instanceof WP_REST_Response) {
            return $this->rollbackIfNeeded($backupDir, $activation, SelfUpdateStatusType::ActivationException);
        }

        // Phase 4: Post-activation health check (self-update only)
        if ($ctx[ResponseKeyType::IsSelfUpdate->value] && $activation['activated'] === true) {
            $healthCheck = new SelfUpdateHealthCheck($this->fileLogger);
            $isHealthy = $healthCheck->check();

            if ($isHealthy === false) {
                $diagnostics = $healthCheck->getDiagnostics();

                $this->fileLogger->error('Post-activation health check failed — triggering rollback', array(
                    'slug'        => $ctx[ResponseKeyType::Slug->value],
                    'diagnostics' => $diagnostics,
                ));

                // Deactivate the broken new version before rollback
                deactivate_plugins($pluginFile);

                $rollbackResponse = $this->performRollbackAndBuildResponse(
                    $backupDir,
                    $ctx[ResponseKeyType::Slug->value],
                    SelfUpdateStatusType::HealthCheckFailed,
                    $diagnostics,
                );

                return $rollbackResponse;
            }
        }

        $versionInfo = $this->detectInstalledVersion($pluginFile, $ctx[ResponseKeyType::Slug->value], $ctx[ResponseKeyType::IsSelfUpdate->value], $input['clientPluginVersion']);

        return array(
            ResponseKeyType::Slug->value => $ctx[ResponseKeyType::Slug->value],
            ResponseKeyType::IsUpdate->value => $ctx[ResponseKeyType::IsUpdate->value],
            ResponseKeyType::Activated->value => $activation['activated'],
            ResponseKeyType::PluginVersion->value => $versionInfo[ResponseKeyType::Version->value],
            ResponseKeyType::IsSelfUpdate->value => $ctx[ResponseKeyType::IsSelfUpdate->value],
        );
    }

    /**
     * Attempt rollback from backup if available, then return the original error response.
     */
    private function rollbackIfNeeded(?string $backupDir, WP_REST_Response $errorResponse, SelfUpdateStatusType $reason): WP_REST_Response
    {
        if ($backupDir === null) {
            return $errorResponse;
        }

        $this->fileLogger->warn('Triggering self-update rollback', array(
            'reason'     => $reason->value,
            'reasonLabel' => $reason->label(),
        ));

        $backupHelper = new SelfUpdateBackupHelper($this->fileLogger);
        $rollbackSuccess = $backupHelper->rollback($backupDir);

        if ($rollbackSuccess) {
            $this->fileLogger->info('Self-update rollback succeeded — previous version restored');

            // Try to re-activate the restored version
            $pluginFile = $this->findPluginFile(PluginConfigType::Slug->value);

            if ($pluginFile) {
                activate_plugin($pluginFile);
            }
        } else {
            $this->fileLogger->error('Self-update rollback failed — plugin may be in broken state');
        }

        return $errorResponse;
    }

    /**
     * Perform rollback and build a detailed error response with diagnostics.
     */
    private function performRollbackAndBuildResponse(
        ?string $backupDir,
        string $slug,
        SelfUpdateStatusType $reason,
        array $diagnostics,
    ): WP_REST_Response {
        $rolledBack = false;
        $restoredVersion = '';
        $outcome = SelfUpdateStatusType::RollbackFailed;

        if ($backupDir !== null) {
            $backupHelper = new SelfUpdateBackupHelper($this->fileLogger);
            $restoredVersion = $backupHelper->getBackupVersion($backupDir);
            $rolledBack = $backupHelper->rollback($backupDir);

            if ($rolledBack) {
                $outcome = SelfUpdateStatusType::RolledBack;

                $this->fileLogger->info('Self-update rollback succeeded', array(
                    'restoredVersion' => $restoredVersion,
                ));

                // Re-activate the restored version
                $pluginFile = $this->findPluginFile($slug);

                if ($pluginFile) {
                    activate_plugin($pluginFile);
                }
            }
        }

        $requestedAt = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        return EnvelopeBuilder::error($reason->label(), HttpStatusType::ServerError->value)
            ->setRequestedAt($requestedAt)
            ->setSingleResult(array(
                'selfUpdateStatus' => $outcome->value,
                'rollbackReason'   => $reason->value,
                'rollback' => array(
                    'attempted'       => ($backupDir !== null),
                    'success'         => $rolledBack,
                    'restoredVersion' => $restoredVersion,
                ),
                'validation' => $diagnostics,
            ))
            ->toResponse();
    }

    /**
     * Extract ZIP to a temp directory, then move to the correct plugin location.
     */
    private function extractToPluginsDir(
        $tempFile,
        $slug,
        $targetDir,
    ) {
        $tempExtractDir = $this->getTempDir() . '/extract_' . uniqid();
        wp_mkdir_p($tempExtractDir);

        $extractError = $this->openAndExtractZip($tempFile, $tempExtractDir);

        if ($extractError) {
            return $extractError;
        }

        $extractedFolders = glob($tempExtractDir . '/*', GLOB_ONLYDIR);

        if (empty($extractedFolders)) {
            $this->deleteDirectory($tempExtractDir);
            $this->logger->logUploadFailed($slug, 'No folder found in extracted ZIP');

            return $this->errorResponse('No folder found in extracted ZIP', HttpStatusType::ServerError->value);
        }

        $this->moveExtractedPlugin($extractedFolders[0], $targetDir);
        $this->deleteDirectory($tempExtractDir);

        return true;
    }

    /** Open ZIP, extract to temp dir, and clean up the ZIP file. */
    private function openAndExtractZip(string $tempFile, string $tempExtractDir) {
        $zip = new ZipArchive();

        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);
            $this->deleteDirectory($tempExtractDir);

            return $this->errorResponse('Failed to open ZIP for extraction', HttpStatusType::ServerError->value);
        }

        $zip->extractTo($tempExtractDir);
        $zip->close();
        @unlink($tempFile);

        return null;
    }

    /** Move extracted plugin folder to target, with copy fallback. */
    private function moveExtractedPlugin(string $extractedFolder, string $targetDir) {
        if (rename($extractedFolder, $targetDir)) {
            $this->fileLogger->info('Plugin installed to correct location');

            return;
        }

        $this->copyDirectory($extractedFolder, $targetDir);
        $this->deleteDirectory($extractedFolder);
    }
}
