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

trait UploadZipTrait
{
    /**
     * Write ZIP content to temp file and validate its structure.
     *
     * @param string $zip_content Raw ZIP bytes.
     * @param string $slug        Optional slug hint.
     * @return array|WP_REST_Response Array with temp_file and slug, or error response.
     */
    private function validate_and_write_zip($zip_content, $slug) {
        $temp_dir  = $this->get_temp_dir();
        $temp_file = $temp_dir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

        $this->file_logger->debug('Writing temp file', array('path' => $temp_file));
        if (file_put_contents($temp_file, $zip_content) === false) {
            $this->file_logger->error('Failed to write temp file');
            $this->logger->log_upload_failed($slug, 'Failed to write temp file');
            return $this->error_response(MSG_UPLOAD_FAILED, HTTP_SERVER_ERROR);
        }

        $this->file_logger->debug('Validating ZIP archive');
        $zip = new ZipArchive();
        if ($zip->open($temp_file) !== true) {
            @unlink($temp_file);
            $this->file_logger->error('Invalid ZIP archive');
            $this->logger->log_upload_failed($slug, 'Invalid ZIP archive');
            return $this->error_response('Invalid ZIP archive', HTTP_BAD_REQUEST);
        }

        $detected_slug = $this->detect_plugin_slug_from_zip($zip);
        $zip->close();

        if (!$detected_slug) {
            @unlink($temp_file);
            $this->file_logger->error('Could not detect plugin in ZIP');
            $this->logger->log_upload_failed($slug, 'Could not detect plugin in ZIP');
            return $this->error_response('Could not detect plugin in ZIP', HTTP_BAD_REQUEST);
        }

        if (empty($slug)) {
            $slug = $detected_slug;
        }

        $this->file_logger->info('Plugin slug determined', array('slug' => $slug));
        return array('temp_file' => $temp_file, 'slug' => $slug);
    }

    /**
     * Remove duplicate plugin folders that share the same slug or TextDomain.
     *
     * @param string $slug        Target plugin slug.
     * @param string $plugins_dir Absolute path to wp-content/plugins.
     * @return int Number of duplicates removed.
     */
    private function remove_duplicate_plugins($slug, $plugins_dir) {
        if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $duplicates_removed = 0;

        foreach ($all_plugins as $pfile => $pdata) {
            $pdir = dirname($pfile);
            $isSkippable = ($pdir === '.' || $pdir === $slug);
            if ($isSkippable) {
                continue;
            }

            $hasMatchingTextDomain = (isset($pdata['TextDomain']) && $pdata['TextDomain'] === $slug);
            $hasMatchingSlugInPath = (isset($pdata['Name']) && strpos(strtolower($pfile), $slug) !== false);
            if (!$hasMatchingTextDomain && !$hasMatchingSlugInPath) {
                continue;
            }

            $dup_dir = $plugins_dir . '/' . $pdir;
            $this->file_logger->warn('Duplicate plugin folder detected', array(
                'duplicate_dir' => $pdir, 'target_slug' => $slug,
            ));

            if (is_plugin_active($pfile)) {
                deactivate_plugins($pfile);
            }

            if (is_dir($dup_dir)) {
                $this->delete_directory($dup_dir);
                $duplicates_removed++;
            }
        }

        if ($duplicates_removed > 0) {
            wp_cache_delete('plugins', 'plugins');
        }

        return $duplicates_removed;
    }

    /**
     * Pre-log self-update activity before files are replaced.
     */
    private function pre_log_self_update($slug, $upload_source, $client_version, $file_size) {
        $old_version = PLUGIN_VERSION;
        $this->file_logger->info('Self-update detected, pre-logging activity', array('old_version' => $old_version));

        $this->logger->log_plugin_action(
            ACTION_UPLOAD, $slug, STATUS_SUCCESS,
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
