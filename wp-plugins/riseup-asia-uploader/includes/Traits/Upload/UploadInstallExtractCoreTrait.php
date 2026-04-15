<?php
/**
 * UploadInstallExtractCoreTrait — Core extraction pipeline: context, steps, and orchestration.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   2.37.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Response;

use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SelfUpdateStatusType;
use RiseupAsia\Update\SelfUpdateBackupHelper;
use RiseupAsia\Update\SelfUpdateHealthCheck;
use RiseupAsia\Update\SelfUpdateValidator;

trait UploadInstallExtractCoreTrait
{
    /** Guard flag: true while an upload pipeline is running (prevents deactivation hook from deleting temp files). */
    private static bool $isUploadInProgress = false;

    /**
     * Process the extraction, activation, and version detection phases.
     */
    private function processUploadExtraction(array $input, array $zipResult) {
        $context = $this->prepareExtractionContext($input, $zipResult);

        // Capture previous version before any replacement
        $previousVersion = $context[ResponseKeyType::IsUpdate->value]
            ? $this->detectInstalledVersionBySlug($context[ResponseKeyType::Slug->value])
            : null;

        // Log upload activity
        $this->fileLogger->info('Plugin upload processing started', [
            'slug' => $context[ResponseKeyType::Slug->value],
            'isUpdate' => $context[ResponseKeyType::IsUpdate->value],
            'isSelfUpdate' => $context[ResponseKeyType::IsSelfUpdate->value],
            'activate' => $input['activate'],
            'previousVersion' => $previousVersion,
        ]);

        // Create backup before replacement for ALL updates (self and non-self)
        $backupDir = null;
        $isExistingUpdate = $context[ResponseKeyType::IsUpdate->value];

        if ($isExistingUpdate) {
            $backupHelper = new SelfUpdateBackupHelper($this->fileLogger);
            $backupDir = $backupHelper->createBackup();

            if ($context[ResponseKeyType::IsSelfUpdate->value] && $backupDir === false) {
                return $this->errorResponse(
                    SelfUpdateStatusType::BackupCreationFailed->label(),
                    HttpStatusType::InternalServerError->value,
                );
            }

            if ($backupDir !== false) {
                $this->fileLogger->info('Pre-upload backup created', [
                    'slug' => $context[ResponseKeyType::Slug->value],
                    'isSelfUpdate' => $context[ResponseKeyType::IsSelfUpdate->value],
                    'backupDir' => $backupDir,
                ]);
            }
        }

        // Guard: prevent deactivation hook from deleting temp files during self-update
        self::$isUploadInProgress = true;
        $isPreviouslyActive = $this->deactivateIfUpdating(
            $context[ResponseKeyType::Slug->value],
            $context[ResponseKeyType::IsUpdate->value],
            $context['targetDir'],
        );

        $stepResult = $this->executeExtractionSteps($context, $isPreviouslyActive, $input, $backupDir);
        self::$isUploadInProgress = false;

        if ($stepResult instanceof WP_REST_Response) {
            // Log external plugin failure for non-self updates
            $isExternalPluginFailure = !$context[ResponseKeyType::IsSelfUpdate->value];

            if ($isExternalPluginFailure) {
                $this->logExternalPluginFailure(
                    $context[ResponseKeyType::Slug->value],
                    'upload',
                    'Plugin upload, extraction, or activation failed',
                );
            }

            // Rollback for non-self updates (self-update rollback handled in executeExtractionSteps)
            $isNonSelfFailure = !$context[ResponseKeyType::IsSelfUpdate->value] && $backupDir !== null;

            if ($isNonSelfFailure) {
                $this->fileLogger->warn('Upload failed — rolling back to previous version', [
                    'slug' => $context[ResponseKeyType::Slug->value],
                    'previousVersion' => $previousVersion,
                ]);

                $rollbackHelper = new SelfUpdateBackupHelper($this->fileLogger);
                $isRolledBack = $rollbackHelper->rollback($backupDir);

                if ($isRolledBack && $isPreviouslyActive) {
                    $pluginFile = $this->findPluginFile($context[ResponseKeyType::Slug->value]);

                    if ($pluginFile) {
                        activate_plugin($pluginFile);
                        $this->fileLogger->info('Rolled-back plugin re-activated', ['slug' => $context[ResponseKeyType::Slug->value]]);
                    }
                }

                $restoredVersion = $isRolledBack
                    ? $this->detectInstalledVersionBySlug($context[ResponseKeyType::Slug->value])
                    : null;

                if ($isRolledBack) {
                    $this->fileLogger->info('Rollback complete — previous version restored', [
                        'slug' => $context[ResponseKeyType::Slug->value],
                        'restoredVersion' => $restoredVersion,
                    ]);
                } else {
                    $this->fileLogger->error('Rollback FAILED — plugin may be in a broken state', [
                        'slug' => $context[ResponseKeyType::Slug->value],
                    ]);
                }

                // Inject rollback metadata into the error response
                $data = $stepResult->get_data();
                $data[ResponseKeyType::RollbackSuccess->value] = $isRolledBack;
                $data[ResponseKeyType::RestoredVersion->value] = $restoredVersion;
                $stepResult->set_data($data);
            }

            return $stepResult;
        }

        // Cleanup backup on success
        if ($backupDir !== null) {
            $backupHelper = new SelfUpdateBackupHelper($this->fileLogger);
            $backupHelper->cleanup($backupDir);
        }

        $this->fileLogger->info('Plugin upload completed successfully', [
            'slug' => $context[ResponseKeyType::Slug->value],
            'version' => $stepResult[ResponseKeyType::PluginVersion->value] ?? '',
            'isUpdate' => $context[ResponseKeyType::IsUpdate->value],
            'isSelfUpdate' => $context[ResponseKeyType::IsSelfUpdate->value],
            'activated' => $stepResult[ResponseKeyType::Activated->value] ?? false,
        ]);

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

        return [
            ResponseKeyType::TempFile->value => $zipResult[ResponseKeyType::TempFile->value],
            ResponseKeyType::Slug->value => $slug,
            'targetDir' => $targetDir,
            ResponseKeyType::IsUpdate->value => $isUpdate,
            ResponseKeyType::IsSelfUpdate->value => $isSelfUpdate,
        ];
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

                $this->fileLogger->error('Self-update validation failed — triggering rollback', [
                    'slug'        => $ctx[ResponseKeyType::Slug->value],
                    'diagnostics' => $diagnostics,
                ]);

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

                $this->fileLogger->error('Post-activation health check failed — triggering rollback', [
                    'slug'        => $ctx[ResponseKeyType::Slug->value],
                    'diagnostics' => $diagnostics,
                ]);

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

        return [
            ResponseKeyType::Slug->value => $ctx[ResponseKeyType::Slug->value],
            ResponseKeyType::IsUpdate->value => $ctx[ResponseKeyType::IsUpdate->value],
            ResponseKeyType::Activated->value => $activation['activated'],
            ResponseKeyType::PluginVersion->value => $versionInfo[ResponseKeyType::Version->value],
            ResponseKeyType::IsSelfUpdate->value => $ctx[ResponseKeyType::IsSelfUpdate->value],
        ];
    }
}
