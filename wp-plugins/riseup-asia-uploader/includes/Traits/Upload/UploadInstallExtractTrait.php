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

trait UploadInstallExtractTrait
{
    /**
     * Deactivate plugin and remove old directory if this is an update.
     */
    private function deactivateIfUpdating(
        $slug,
        $is_update,
        $target_dir,
    ) {
        $this->fileLogger->info($is_update ? 'Updating existing plugin' : 'Installing new plugin', array('slug' => $slug));

        if (!$is_update) {
            return false;
        }

        $plugin_file = $this->findPluginFile($slug);
        $isPreviouslyActive = false;

        if ($plugin_file) {
            $isPreviouslyActive = is_plugin_active($plugin_file);
            if ($isPreviouslyActive) {
                deactivate_plugins($plugin_file);
            }
        }

        $this->deleteDirectory($target_dir);

        return $isPreviouslyActive;
    }

    /**
     * Process the extraction, activation, and version detection phases.
     */
    private function processUploadExtraction(array $input, array $zip_result) {
        $context = $this->prepareExtractionContext($input, $zip_result);

        $isPreviouslyActive = $this->deactivateIfUpdating($context['slug'], $context['is_update'], $context['target_dir']);

        $stepResult = $this->executeExtractionSteps($context, $isPreviouslyActive, $input);
        if ($stepResult instanceof WP_REST_Response) {
            return $stepResult;
        }

        return $stepResult;
    }

    /** Prepare extraction context from input and ZIP result. */
    private function prepareExtractionContext(array $input, array $zip_result): array {
        $slug       = $zip_result['slug'];
        $target_dir = WP_PLUGIN_DIR . '/' . $slug;
        $is_update  = is_dir($target_dir);

        $this->removeDuplicatePlugins($slug, WP_PLUGIN_DIR);

        $is_self_update = ($slug === PluginConfigType::Slug->value && $is_update);
        if ($is_self_update) {
            $this->preLogSelfUpdate($slug, $input['upload_source'], $input['client_plugin_version'], strlen($input['zip_content']));
        }

        return array(
            'temp_file' => $zip_result['temp_file'], 'slug' => $slug,
            'target_dir' => $target_dir, 'is_update' => $is_update,
            'is_self_update' => $is_self_update,
        );
    }

    /** Execute extraction, opcache reset, activation, and version detection. */
    private function executeExtractionSteps(
        array $ctx,
        bool $isPreviouslyActive,
        array $input,
    ) {
        $extract_result = $this->extractToPluginsDir($ctx['temp_file'], $ctx['slug'], $ctx['target_dir']);
        if ($extract_result instanceof WP_REST_Response) {
            return $extract_result;
        }

        $plugin_file = $this->resetOpcacheAndFindPlugin($ctx['slug']);
        if ($plugin_file instanceof WP_REST_Response) {
            return $plugin_file;
        }

        $activation = $this->activateIfNeeded($plugin_file, $ctx['slug'], $input['activate'], $isPreviouslyActive, $ctx['is_update']);
        if ($activation instanceof WP_REST_Response) {
            return $activation;
        }

        $version_info = $this->detectInstalledVersion($plugin_file, $ctx['slug'], $ctx['is_self_update'], $input['client_plugin_version']);

        return array(
            'slug' => $ctx['slug'], 'is_update' => $ctx['is_update'], 'activated' => $activation['activated'],
            'plugin_version' => $version_info['version'], 'is_self_update' => $ctx['is_self_update'],
        );
    }

    /**
     * Extract ZIP to a temp directory, then move to the correct plugin location.
     */
    private function extractToPluginsDir(
        $temp_file,
        $slug,
        $target_dir,
    ) {
        $temp_extract_dir = $this->getTempDir() . '/extract_' . uniqid();
        wp_mkdir_p($temp_extract_dir);

        $extractError = $this->openAndExtractZip($temp_file, $temp_extract_dir);
        if ($extractError) {
            return $extractError;
        }

        $extracted_folders = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
        if (empty($extracted_folders)) {
            $this->deleteDirectory($temp_extract_dir);
            $this->logger->logUploadFailed($slug, 'No folder found in extracted ZIP');

            return $this->errorResponse('No folder found in extracted ZIP', HttpStatusType::ServerError->value);
        }

        $this->moveExtractedPlugin($extracted_folders[0], $target_dir);
        $this->deleteDirectory($temp_extract_dir);

        return true;
    }

    /** Open ZIP, extract to temp dir, and clean up the ZIP file. */
    private function openAndExtractZip(string $temp_file, string $temp_extract_dir) {
        $zip = new ZipArchive();
        if ($zip->open($temp_file) !== true) {
            @unlink($temp_file);
            $this->deleteDirectory($temp_extract_dir);

            return $this->errorResponse('Failed to open ZIP for extraction', HttpStatusType::ServerError->value);
        }

        $zip->extractTo($temp_extract_dir);
        $zip->close();
        @unlink($temp_file);

        return null;
    }

    /** Move extracted plugin folder to target, with copy fallback. */
    private function moveExtractedPlugin(string $extracted_folder, string $target_dir) {
        if (rename($extracted_folder, $target_dir)) {
            $this->fileLogger->info('Plugin installed to correct location');

            return;
        }

        $this->copyDirectory($extracted_folder, $target_dir);
        $this->deleteDirectory($extracted_folder);
    }
}
