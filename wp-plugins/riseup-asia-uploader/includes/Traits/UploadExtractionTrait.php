<?php
/**
 * UploadExtractionTrait — ZIP validation, extraction, activation, and version detection.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UploadExtractionTrait
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
            $isDuplicate = ($hasMatchingTextDomain || $hasMatchingSlugInPath);
            if (!$isDuplicate) {
                continue;
            }

            $dup_dir = $plugins_dir . '/' . $pdir;
            $this->file_logger->warn('Duplicate plugin folder detected', array(
                'duplicate_dir' => $pdir,
                'duplicate_ver' => isset($pdata['Version']) ? $pdata['Version'] : 'unknown',
                'target_slug'   => $slug,
            ));

            if (is_plugin_active($pfile)) {
                deactivate_plugins($pfile);
                $this->file_logger->info('Deactivated duplicate plugin', array('file' => $pfile));
            }

            if (is_dir($dup_dir)) {
                $this->delete_directory($dup_dir);
                $this->file_logger->info('Removed duplicate plugin folder', array('dir' => $dup_dir));
                $duplicates_removed++;
            }
        }

        if ($duplicates_removed > 0) {
            wp_cache_delete('plugins', 'plugins');
            $this->file_logger->info('Duplicate cleanup complete', array('removed' => $duplicates_removed));
        }

        return $duplicates_removed;
    }

    /**
     * Pre-log self-update activity before files are replaced.
     *
     * @param string $slug            Plugin slug.
     * @param string $upload_source   Upload source identifier.
     * @param string $client_version  Client-reported version.
     * @param int    $file_size       ZIP file size in bytes.
     */
    private function pre_log_self_update($slug, $upload_source, $client_version, $file_size) {
        $old_version = PLUGIN_VERSION;

        $this->file_logger->info('Self-update detected, pre-logging activity', array(
            'old_version'   => $old_version,
            'upload_source' => $upload_source,
        ));

        $this->logger->log_plugin_action(
            ACTION_UPLOAD, $slug, STATUS_SUCCESS,
            array(
                'is_update'      => true,
                'is_self_update' => true,
                'old_version'    => $old_version,
                'new_version'    => $client_version,
                'file_size'      => $file_size,
                'note'           => 'Pre-logged before self-update to ensure audit trail',
            ),
            null,
            array(
                'plugin_version' => $client_version ?: $old_version,
                'upload_source'  => $upload_source,
            )
        );
    }

    /**
     * Deactivate plugin and remove old directory if this is an update.
     *
     * @param string $slug       Plugin slug.
     * @param bool   $is_update  Whether this is an update.
     * @param string $target_dir Absolute path to plugin directory.
     * @return bool Whether the plugin was previously active.
     */
    private function deactivate_if_updating($slug, $is_update, $target_dir) {
        $this->file_logger->info($is_update ? 'Updating existing plugin' : 'Installing new plugin', array(
            'slug'       => $slug,
            'target_dir' => $target_dir,
        ));

        if (!$is_update) {
            return false;
        }

        $plugin_file = $this->find_plugin_file($slug);
        $was_active = false;

        if ($plugin_file) {
            $was_active = is_plugin_active($plugin_file);
            if ($was_active) {
                $this->file_logger->debug('Deactivating plugin before update', array('plugin_file' => $plugin_file));
                deactivate_plugins($plugin_file);
            }
        }

        $this->file_logger->debug('Removing old plugin version', array('target_dir' => $target_dir));
        $this->delete_directory($target_dir);

        return $was_active;
    }

    /**
     * Process the extraction, activation, and version detection phases.
     *
     * @param array $input      Parsed upload input.
     * @param array $zip_result Validated ZIP result.
     * @return array|WP_REST_Response Result array or error response.
     */
    private function processUploadExtraction(array $input, array $zip_result) {
        $temp_file = $zip_result['temp_file'];
        $slug      = $zip_result['slug'];
        $plugins_dir = WP_PLUGIN_DIR;
        $target_dir  = $plugins_dir . '/' . $slug;
        $is_update   = is_dir($target_dir);

        $this->remove_duplicate_plugins($slug, $plugins_dir);

        $is_self_update = ($slug === PLUGIN_SLUG && $is_update);
        if ($is_self_update) {
            $this->pre_log_self_update($slug, $input['upload_source'], $input['client_plugin_version'], strlen($input['zip_content']));
        }

        $was_active = $this->deactivate_if_updating($slug, $is_update, $target_dir);

        $extract_result = $this->extract_to_plugins_dir($temp_file, $slug, $target_dir);
        if ($extract_result instanceof WP_REST_Response) {
            return $extract_result;
        }

        $plugin_file = $this->reset_opcache_and_find_plugin($slug);
        if ($plugin_file instanceof WP_REST_Response) {
            return $plugin_file;
        }

        $activation = $this->activate_if_needed($plugin_file, $slug, $input['activate'], $was_active, $is_update);
        if ($activation instanceof WP_REST_Response) {
            return $activation;
        }

        $version_info = $this->detect_installed_version($plugin_file, $slug, $is_self_update, $input['client_plugin_version']);

        return array(
            'slug'           => $slug,
            'is_update'      => $is_update,
            'activated'      => $activation['activated'],
            'plugin_version' => $version_info['version'],
            'is_self_update' => $is_self_update,
        );
    }

    /**
     * Extract ZIP to a temp directory, then move to the correct plugin location.
     *
     * @param string $temp_file  Path to the temp ZIP file.
     * @param string $slug       Plugin slug.
     * @param string $target_dir Target plugin directory.
     * @return true|WP_REST_Response True on success, or error response.
     */
    private function extract_to_plugins_dir($temp_file, $slug, $target_dir) {
        $temp_extract_dir = $this->get_temp_dir() . '/extract_' . uniqid();
        wp_mkdir_p($temp_extract_dir);

        $zip = new ZipArchive();
        if ($zip->open($temp_file) !== true) {
            @unlink($temp_file);
            $this->delete_directory($temp_extract_dir);

            return $this->error_response('Failed to open ZIP for extraction', HTTP_SERVER_ERROR);
        }

        $zip->extractTo($temp_extract_dir);
        $zip->close();
        @unlink($temp_file);

        $extracted_folders = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
        if (empty($extracted_folders)) {
            $this->delete_directory($temp_extract_dir);
            $this->logger->log_upload_failed($slug, 'No folder found in extracted ZIP');

            return $this->error_response('No folder found in extracted ZIP', HTTP_SERVER_ERROR);
        }

        $extracted_folder = $extracted_folders[0];

        if (rename($extracted_folder, $target_dir)) {
            $this->file_logger->info('Plugin installed to correct location');
        } else {
            $this->copy_directory($extracted_folder, $target_dir);
            $this->delete_directory($extracted_folder);
        }

        $this->delete_directory($temp_extract_dir);

        return true;
    }

    /**
     * Reset OPcache and locate the plugin's main file.
     *
     * @param string $slug Plugin slug.
     * @return string|WP_REST_Response Plugin file path, or error response.
     */
    private function reset_opcache_and_find_plugin($slug) {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $plugin_file = $this->find_plugin_file($slug);

        if (!empty($plugin_file)) {
            $full_plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($full_plugin_path, true);
            }

            $constants_file = WP_PLUGIN_DIR . '/' . $slug . '/includes/constants.php';
            $hasConstantsFile = (file_exists($constants_file) && function_exists('opcache_invalidate'));
            if ($hasConstantsFile) {
                opcache_invalidate($constants_file, true);
            }

            wp_cache_delete('plugins', 'plugins');
        }

        if (!$plugin_file) {
            $this->logger->log_upload_failed($slug, 'Could not find plugin file after extraction');

            return $this->error_response('Could not find plugin file after extraction', HTTP_SERVER_ERROR);
        }

        return $plugin_file;
    }

    /**
     * Activate the plugin if requested or if it was previously active.
     *
     * @param string $plugin_file Plugin file relative path.
     * @param string $slug        Plugin slug.
     * @param bool   $activate    Whether activation was requested.
     * @param bool   $was_active  Whether the plugin was previously active.
     * @param bool   $is_update   Whether this is an update.
     * @return array|WP_REST_Response Array with 'activated' key, or partial-success response.
     */
    private function activate_if_needed($plugin_file, $slug, $activate, $was_active, $is_update) {
        $shouldActivate = ($activate || $was_active);
        if (!$shouldActivate) {
            return array('activated' => false);
        }

        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            $error_msg = $result->get_error_message();
            $this->logger->log_upload_failed($slug, MSG_ACTIVATION_FAILED . ': ' . $error_msg);

            return RiseupEnvelopeBuilder::success('Plugin uploaded but activation failed', HTTP_OK)
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_UPLOAD)
                ->setSingleResult(array(
                    'plugin_slug'      => $slug,
                    'is_update'        => $is_update,
                    'activated'        => false,
                    'activation_error' => $error_msg,
                ))
                ->toResponse();
        }

        return array('activated' => true);
    }

    /**
     * Detect the installed plugin version from disk.
     *
     * @param string $plugin_file     Plugin file relative path.
     * @param string $slug            Plugin slug.
     * @param bool   $is_self_update  Whether this is a self-update.
     * @param string $client_version  Client-reported version.
     * @return array Array with 'version' and 'source' keys.
     */
    private function detect_installed_version($plugin_file, $slug, $is_self_update, $client_version) {
        $installed_version = '';
        $full_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        clearstatcache(true, $full_path);
        if (file_exists($full_path)) {
            $file_contents = file_get_contents($full_path, false, null, 0, 8192);
            $hasVersionHeader = ($file_contents !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $file_contents, $matches));
            if ($hasVersionHeader) {
                $installed_version = $matches[1];
            }
        }

        if (empty($installed_version)) {
            $plugin_data = get_plugin_data($full_path, false, false);
            if (!empty($plugin_data['Version'])) {
                $installed_version = $plugin_data['Version'];
            }
        }

        if ($is_self_update) {
            $version = $client_version ?: ($installed_version ?: PLUGIN_VERSION);
            $source  = !empty($client_version) ? 'client (self-update)' : ($installed_version ? 'file_header' : 'constant');
        } else {
            $version = $installed_version ?: ($client_version ?: PLUGIN_VERSION);
            $source  = $installed_version ? 'file_header' : (!empty($client_version) ? 'client' : 'constant');
        }

        $this->file_logger->info('Plugin version determined', array(
            'version' => $version, 'source' => $source,
        ));

        return array('version' => $version, 'source' => $source);
    }
}
