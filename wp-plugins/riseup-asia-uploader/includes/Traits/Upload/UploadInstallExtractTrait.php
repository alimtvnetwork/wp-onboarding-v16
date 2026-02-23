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

        $isPreviouslyActive = $this->deactivateIfUpdating($context[ResponseKeyType::Slug->value], $context[ResponseKeyType::IsUpdate->value], $context['target_dir']);

        $stepResult = $this->executeExtractionSteps($context, $isPreviouslyActive, $input);
        if ($stepResult instanceof WP_REST_Response) {
            return $stepResult;
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
            $this->preLogSelfUpdate($slug, $input['upload_source'], $input['client_plugin_version'], strlen($input['zip_content']));
        }

        return array(
            ResponseKeyType::TempFile->value => $zipResult[ResponseKeyType::TempFile->value],
            ResponseKeyType::Slug->value => $slug,
            'target_dir' => $targetDir,
            ResponseKeyType::IsUpdate->value => $isUpdate,
            ResponseKeyType::IsSelfUpdate->value => $isSelfUpdate,
        );
    }

    /** Execute extraction, opcache reset, activation, and version detection. */
    private function executeExtractionSteps(
        array $ctx,
        bool $isPreviouslyActive,
        array $input,
    ) {
        $extractResult = $this->extractToPluginsDir($ctx[ResponseKeyType::TempFile->value], $ctx[ResponseKeyType::Slug->value], $ctx['target_dir']);
        if ($extractResult instanceof WP_REST_Response) {
            return $extractResult;
        }

        $pluginFile = $this->resetOpcacheAndFindPlugin($ctx[ResponseKeyType::Slug->value]);
        if ($pluginFile instanceof WP_REST_Response) {
            return $pluginFile;
        }

        $activation = $this->activateIfNeeded($pluginFile, $ctx[ResponseKeyType::Slug->value], $input['activate'], $isPreviouslyActive, $ctx[ResponseKeyType::IsUpdate->value]);
        if ($activation instanceof WP_REST_Response) {
            return $activation;
        }

        $versionInfo = $this->detectInstalledVersion($pluginFile, $ctx[ResponseKeyType::Slug->value], $ctx[ResponseKeyType::IsSelfUpdate->value], $input['client_plugin_version']);

        return array(
            ResponseKeyType::Slug->value => $ctx[ResponseKeyType::Slug->value],
            ResponseKeyType::IsUpdate->value => $ctx[ResponseKeyType::IsUpdate->value],
            ResponseKeyType::Activated->value => $activation[ResponseKeyType::Activated->value],
            ResponseKeyType::PluginVersion->value => $versionInfo['version'],
            ResponseKeyType::IsSelfUpdate->value => $ctx[ResponseKeyType::IsSelfUpdate->value],
        );
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
