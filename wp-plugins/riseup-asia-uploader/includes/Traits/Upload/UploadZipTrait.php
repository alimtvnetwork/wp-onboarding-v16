<?php
/**
 * UploadZipTrait — ZIP validation, duplicate removal, and pre-logging.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;

trait UploadZipTrait
{
    /** Write ZIP content to temp file and validate its structure. */
    private function validateAndWriteZip($zip_content, $slug) {
        $temp_file = $this->writeZipToTemp($zip_content, $slug);
        if ($temp_file instanceof WP_REST_Response) {
            return $temp_file;
        }

        $detected_slug = $this->validateZipStructure($temp_file, $slug);
        if ($detected_slug instanceof WP_REST_Response) {
            return $detected_slug;
        }

        $final_slug = !empty($slug) ? $slug : $detected_slug;
        $this->fileLogger->info('Plugin slug determined', array('slug' => $final_slug));
        return array('temp_file' => $temp_file, 'slug' => $final_slug);
    }

    /** Write ZIP content to a temp file. */
    private function writeZipToTemp(string $zip_content, string $slug) {
        $temp_dir  = $this->getTempDir();
        $temp_file = $temp_dir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

        $this->fileLogger->debug('Writing temp file', array('path' => $temp_file));
        if (file_put_contents($temp_file, $zip_content) === false) {
            $this->fileLogger->error('Failed to write temp file');
            $this->logger->logUploadFailed($slug, 'Failed to write temp file');
            return $this->errorResponse(MSG_UPLOAD_FAILED, HTTP_SERVER_ERROR);
        }

        return $temp_file;
    }

    /** Validate ZIP archive and detect plugin slug. */
    private function validateZipStructure(string $temp_file, string $slug) {
        $this->fileLogger->debug('Validating ZIP archive');
        $zip = new ZipArchive();
        if ($zip->open($temp_file) !== true) {
            @unlink($temp_file);
            $this->fileLogger->error('Invalid ZIP archive');
            $this->logger->logUploadFailed($slug, 'Invalid ZIP archive');
            return $this->errorResponse('Invalid ZIP archive', HTTP_BAD_REQUEST);
        }

        $detected_slug = $this->detectPluginSlugFromZip($zip);
        $zip->close();

        if (!$detected_slug) {
            @unlink($temp_file);
            $this->fileLogger->error('Could not detect plugin in ZIP');
            $this->logger->logUploadFailed($slug, 'Could not detect plugin in ZIP');
            return $this->errorResponse('Could not detect plugin in ZIP', HTTP_BAD_REQUEST);
        }

        return $detected_slug;
    }

    /** Remove duplicate plugin folders that share the same slug or TextDomain. */
    private function removeDuplicatePlugins($slug, $plugins_dir) {
        if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $duplicates_removed = 0;

        foreach ($all_plugins as $pfile => $pdata) {
            $removed = $this->removeSingleDuplicate($pfile, $pdata, $slug, $plugins_dir);
            $duplicates_removed += $removed ? 1 : 0;
        }

        if ($duplicates_removed > 0) {
            wp_cache_delete('plugins', 'plugins');
        }

        return $duplicates_removed;
    }

    /** Check and remove a single duplicate plugin. Returns true if removed. */
    private function removeSingleDuplicate(string $pfile, array $pdata, string $slug, string $plugins_dir): bool {
        $pdir = dirname($pfile);
        if ($pdir === '.' || $pdir === $slug) {
            return false;
        }

        if (!$this->isDuplicatePlugin($pdata, $pfile, $slug)) {
            return false;
        }

        $dup_dir = $plugins_dir . '/' . $pdir;
        $this->fileLogger->warn('Duplicate plugin folder detected', array('duplicate_dir' => $pdir, 'target_slug' => $slug));

        if (is_plugin_active($pfile)) {
            deactivate_plugins($pfile);
        }

        if (is_dir($dup_dir)) {
            $this->deleteDirectory($dup_dir);
            return true;
        }

        return false;
    }

    /** Check if a plugin entry is a duplicate of the target slug. */
    private function isDuplicatePlugin(array $pdata, string $pfile, string $slug): bool {
        $hasMatchingTextDomain = (isset($pdata['TextDomain']) && $pdata['TextDomain'] === $slug);
        $hasMatchingSlugInPath = (isset($pdata['Name']) && strpos(strtolower($pfile), $slug) !== false);
        return $hasMatchingTextDomain || $hasMatchingSlugInPath;
    }

    /** Pre-log self-update activity before files are replaced. */
    private function preLogSelfUpdate($slug, $upload_source, $client_version, $file_size) {
        $old_version = PLUGIN_VERSION;
        $this->fileLogger->info('Self-update detected, pre-logging activity', array('old_version' => $old_version));

        $this->logger->logPluginAction(
            ActionType::Upload->value, $slug, STATUS_SUCCESS,
            array(
                'is_update' => true, 'is_self_update' => true,
                'old_version' => $old_version, 'new_version' => $client_version,
                'file_size' => $file_size, 'note' => 'Pre-logged before self-update to ensure audit trail',
            ),
            null,
            array('plugin_version' => $client_version ?: $old_version, 'upload_source' => $upload_source)
        );
    }
}
