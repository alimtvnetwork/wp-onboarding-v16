<?php
/**
 * Plugin Manager Upload Trait — Upload and install plugin from ZIP.
 *
 * @package PluginsOnboard
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait OnboardPluginManagerUploadTrait
 *
 * Handles plugin upload, extraction, activation, and audit logging.
 */
trait OnboardPluginManagerUploadTrait {

    /**
     * Upload and install a plugin.
     *
     * @param array       $file       Uploaded file data.
     * @param bool        $auto_backup Whether to create backup of existing plugin.
     * @param string|null $app_id     Application ID.
     * @param string|null $ip_address IP address.
     * @return array|WP_Error
     */
    public function upload($file, $auto_backup = true, $app_id = null, $ip_address = null) {
        // Validate upload.
        $validator = new OnboardUploadValidator();
        $validation = $validator->validate($file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Extract plugin info.
        $plugin_info = $validator->get_plugin_info($file['tmp_name']);
        if (is_wp_error($plugin_info)) {
            return $plugin_info;
        }

        $slug = $plugin_info['slug'];

        // Check if plugin already exists and create backup.
        $existing_plugin = $this->get_plugin($slug);
        $backup = null;

        $isBackupRequired = $existing_plugin && $auto_backup && $this->should_backup('upload');

        if ($isBackupRequired) {
            $backup = $this->snapshot->create($slug, 'pre_upload', $app_id, $ip_address);

            // Delete existing plugin.
            if ($existing_plugin['is_active']) {
                deactivate_plugins($existing_plugin['file']);
            }
            $this->delete_plugin_directory($slug);
        }

        // Extract ZIP to plugins directory.
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            return new WP_Error('zip_open_failed', 'Failed to open ZIP file', array('status' => 500));
        }

        $isExtracted = $zip->extractTo(WP_PLUGIN_DIR);
        $zip->close();

        if ($isExtracted === false) {
            return new WP_Error('zip_extract_failed', 'Failed to extract ZIP contents', array('status' => 500));
        }

        // Find and activate plugin.
        $plugin_file = $this->find_plugin_file($slug);
        $isPluginFileMissing = !$plugin_file;

        if ($isPluginFileMissing) {
            return new WP_Error('plugin_file_not_found', 'Could not find plugin file after extraction', array('status' => 500));
        }

        $result = activate_plugin($plugin_file);
        $isActivated = !is_wp_error($result);

        // Get updated plugin data.
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        wp_cache_delete('plugins', 'plugins');
        $all_plugins = get_plugins();
        $plugin_data = $all_plugins[$plugin_file] ?? array();

        $isBackupCreated = !is_wp_error($backup) && $backup !== null;

        // Log upload.
        $this->audit_logger->log(
            'plugin_uploaded',
            $slug,
            $app_id,
            $ip_address,
            'success',
            array(
                'plugin' => $slug,
                'version' => $plugin_data['Version'] ?? 'unknown',
                'file_size' => $file['size'],
                'backup_created' => $isBackupCreated,
                'is_active' => $isActivated,
            )
        );

        return array(
            'success' => true,
            'plugin_slug' => $slug,
            'plugin_name' => $plugin_data['Name'] ?? $slug,
            'version' => $plugin_data['Version'] ?? 'unknown',
            'is_active' => $isActivated,
            'backup_created' => $isBackupCreated,
            'backup_location' => $isBackupCreated ? $backup['file_path'] : null,
            'message' => 'Plugin uploaded' . ($isActivated ? ' and activated' : '') . ' successfully' . ($backup !== null ? ', backup of previous version created' : ''),
        );
    }
}
