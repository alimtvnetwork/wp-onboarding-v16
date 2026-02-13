<?php
/**
 * UploadInstallTrait — extraction, installation, activation, and version detection.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UploadInstallTrait
{
    /**
     * Deactivate plugin and remove old directory if this is an update.
     *
     * @param string $slug       Plugin slug.
     * @param bool   $is_update  Whether this is an update.
     * @param string $target_dir Absolute path to plugin directory.
     * @return bool Whether the plugin was previously active.
     */
    private function deactivate_if_updating($slug, $is_update, $target_dir) {
        $this->file_logger->info($is_update ? 'Updating existing plugin' : 'Installing new plugin', array('slug' => $slug));

        if (!$is_update) {
            return false;
        }

        $plugin_file = $this->find_plugin_file($slug);
        $was_active = false;

        if ($plugin_file) {
            $was_active = is_plugin_active($plugin_file);
            if ($was_active) {
                deactivate_plugins($plugin_file);
            }
        }

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
            'slug' => $slug, 'is_update' => $is_update, 'activated' => $activation['activated'],
            'plugin_version' => $version_info['version'], 'is_self_update' => $is_self_update,
        );
    }

    /**
     * Extract ZIP to a temp directory, then move to the correct plugin location.
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

    /** Reset OPcache and locate the plugin's main file. */
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
            if (file_exists($constants_file) && function_exists('opcache_invalidate')) {
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

    /** Activate the plugin if requested or if it was previously active. */
    private function activate_if_needed($plugin_file, $slug, $activate, $was_active, $is_update) {
        if (!$activate && !$was_active) {
            return array('activated' => false);
        }

        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            $error_msg = $result->get_error_message();
            $this->logger->log_upload_failed($slug, MSG_ACTIVATION_FAILED . ': ' . $error_msg);
            return RiseupEnvelopeBuilder::success('Plugin uploaded but activation failed', HTTP_OK)
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_UPLOAD)
                ->setSingleResult(array(
                    'plugin_slug' => $slug, 'is_update' => $is_update,
                    'activated' => false, 'activation_error' => $error_msg,
                ))
                ->toResponse();
        }

        return array('activated' => true);
    }

    /** Detect the installed plugin version from disk. */
    private function detect_installed_version($plugin_file, $slug, $is_self_update, $client_version) {
        $installed_version = '';
        $full_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        clearstatcache(true, $full_path);
        if (file_exists($full_path)) {
            $file_contents = file_get_contents($full_path, false, null, 0, 8192);
            if ($file_contents !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $file_contents, $matches)) {
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
        } else {
            $version = $installed_version ?: ($client_version ?: PLUGIN_VERSION);
        }

        return array('version' => $version);
    }
}
