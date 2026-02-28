<?php
/**
 * Plugin Manager class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardPluginManager
 *
 * Handles plugin management operations (enable, disable, delete, upload).
 */
class OnboardPluginManager {

    /**
     * Database instance.
     *
     * @var OnboardDatabase
     */
    private $db;

    /**
     * Snapshot manager instance.
     *
     * @var OnboardSnapshot
     */
    private $snapshot;

    /**
     * Audit logger instance.
     *
     * @var OnboardAuditLogger
     */
    private $audit_logger;

    /**
     * Constructor.
     *
     * @param OnboardDatabase     $db           Database instance.
     * @param OnboardSnapshot     $snapshot     Snapshot manager instance.
     * @param OnboardAuditLogger $audit_logger Audit logger instance.
     */
    public function __construct(OnboardDatabase $db, OnboardSnapshot $snapshot, OnboardAuditLogger $audit_logger) {
        $this->db = $db;
        $this->snapshot = $snapshot;
        $this->audit_logger = $audit_logger;
    }

    /**
     * Get all plugins with status.
     *
     * @return array
     */
    public function get_all_plugins() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugins = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }

            $snapshot_count = $this->snapshot->get_snapshot_count($slug);
            $latest_snapshot = $this->snapshot->get_latest_snapshot($slug);

            $plugins[] = array(
                'slug' => $slug,
                'file' => $plugin_file,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author' => $plugin_data['Author'],
                'author_uri' => $plugin_data['AuthorURI'],
                'plugin_uri' => $plugin_data['PluginURI'],
                'is_active' => in_array($plugin_file, $active_plugins, true),
                'snapshot_count' => $snapshot_count,
                'last_backup' => $latest_snapshot ? $latest_snapshot['created_at'] : null,
            );
        }

        return $plugins;
    }

    /**
     * Get plugin by slug.
     *
     * @param string $slug Plugin slug.
     * @return array|null
     */
    public function get_plugin($slug) {
        $plugins = $this->get_all_plugins();

        foreach ($plugins as $plugin) {
            if ($plugin['slug'] === $slug) {
                return $plugin;
            }
        }
        return null;
    }

    /**
     * Enable a plugin.
     *
     * @param string      $slug       Plugin slug.
     * @param string|null $app_id     Application ID.
     * @param string|null $ip_address IP address.
     * @return array|WP_Error
     */
    public function enable($slug, $app_id = null, $ip_address = null) {
        $plugin = $this->get_plugin($slug);
        $isPluginMissing = !$plugin;

        if ($isPluginMissing) {
            return new WP_Error('plugin_not_found', 'Plugin not found: ' . $slug, array('status' => 404));
        }

        $isAlreadyActive = $plugin['is_active'];

        if ($isAlreadyActive) {
            return new WP_Error('plugin_already_active', 'Plugin is already active', array('status' => 400));
        }

        // Create pre-action snapshot if enabled.
        $backup = null;
        if ($this->should_backup('enable')) {
            $backup = $this->snapshot->create($slug, 'pre_enable', $app_id, $ip_address);
        }

        // Activate plugin.
        $result = activate_plugin($plugin['file']);
        if (is_wp_error($result)) {
            $this->audit_logger->log(
                'plugin_enable_failed',
                $slug,
                $app_id,
                $ip_address,
                'failed',
                array('plugin' => $slug),
                $result->get_error_message()
            );
            return $result;
        }

        $isBackupCreated = !is_wp_error($backup);

        // Log success.
        $this->audit_logger->log(
            'plugin_enabled',
            $slug,
            $app_id,
            $ip_address,
            'success',
            array(
                'plugin' => $slug,
                'version' => $plugin['version'],
                'backup_created' => $isBackupCreated,
            )
        );

        return array(
            'success' => true,
            'plugin_slug' => $slug,
            'status' => 'active',
            'message' => 'Plugin enabled successfully',
            'backup_created' => $isBackupCreated,
            'backup_location' => $isBackupCreated ? $backup['file_path'] : null,
        );
    }

    /**
     * Disable a plugin.
     *
     * @param string      $slug       Plugin slug.
     * @param string|null $app_id     Application ID.
     * @param string|null $ip_address IP address.
     * @return array|WP_Error
     */
    public function disable($slug, $app_id = null, $ip_address = null) {
        $plugin = $this->get_plugin($slug);
        $isPluginMissing = !$plugin;

        if ($isPluginMissing) {
            return new WP_Error('plugin_not_found', 'Plugin not found: ' . $slug, array('status' => 404));
        }

        $isAlreadyInactive = !$plugin['is_active'];

        if ($isAlreadyInactive) {
            return new WP_Error('plugin_already_inactive', 'Plugin is already inactive', array('status' => 400));
        }

        // Prevent disabling this plugin.
        if ($slug === 'plugins-onboard') {
            return new WP_Error('cannot_disable_self', 'Cannot disable Plugins Onboard plugin', array('status' => 400));
        }

        // Create pre-action snapshot if enabled.
        $backup = null;
        if ($this->should_backup('disable')) {
            $backup = $this->snapshot->create($slug, 'pre_disable', $app_id, $ip_address);
        }

        // Deactivate plugin.
        deactivate_plugins($plugin['file']);

        $isBackupCreated = !is_wp_error($backup);

        // Log success.
        $this->audit_logger->log(
            'plugin_disabled',
            $slug,
            $app_id,
            $ip_address,
            'success',
            array(
                'plugin' => $slug,
                'version' => $plugin['version'],
                'backup_created' => $isBackupCreated,
            )
        );

        return array(
            'success' => true,
            'plugin_slug' => $slug,
            'status' => 'inactive',
            'message' => 'Plugin disabled successfully',
            'backup_created' => $isBackupCreated,
            'backup_location' => $isBackupCreated ? $backup['file_path'] : null,
        );
    }

    /**
     * Delete a plugin.
     *
     * @param string      $slug       Plugin slug.
     * @param string|null $app_id     Application ID.
     * @param string|null $ip_address IP address.
     * @return array|WP_Error
     */
    public function delete($slug, $app_id = null, $ip_address = null) {
        $plugin = $this->get_plugin($slug);
        $isPluginMissing = !$plugin;

        if ($isPluginMissing) {
            return new WP_Error('plugin_not_found', 'Plugin not found: ' . $slug, array('status' => 404));
        }

        // Prevent deleting this plugin.
        if ($slug === 'plugins-onboard') {
            return new WP_Error('cannot_delete_self', 'Cannot delete Plugins Onboard plugin', array('status' => 400));
        }

        // Create pre-action snapshot if enabled.
        $backup = null;
        if ($this->should_backup('delete')) {
            $backup = $this->snapshot->create($slug, 'pre_delete', $app_id, $ip_address);
        }

        // Deactivate if active.
        if ($plugin['is_active']) {
            deactivate_plugins($plugin['file']);
        }

        // Delete plugin.
        if (!function_exists('delete_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $deleted = delete_plugins(array($plugin['file']));
        if (is_wp_error($deleted)) {
            $this->audit_logger->log(
                'plugin_delete_failed',
                $slug,
                $app_id,
                $ip_address,
                'failed',
                array('plugin' => $slug),
                $deleted->get_error_message()
            );
            return $deleted;
        }

        $isBackupCreated = !is_wp_error($backup);

        // Log success.
        $this->audit_logger->log(
            'plugin_deleted',
            $slug,
            $app_id,
            $ip_address,
            'success',
            array(
                'plugin' => $slug,
                'version' => $plugin['version'],
                'backup_created' => $isBackupCreated,
            )
        );

        return array(
            'success' => true,
            'plugin_slug' => $slug,
            'message' => 'Plugin deleted successfully',
            'backup_created' => $isBackupCreated,
            'backup_location' => $isBackupCreated ? $backup['file_path'] : null,
        );
    }

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

        $zip->extractTo(WP_PLUGIN_DIR);
        $zip->close();

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
        $plugin_data = isset($all_plugins[$plugin_file]) ? $all_plugins[$plugin_file] : array();

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
                'version' => isset($plugin_data['Version']) ? $plugin_data['Version'] : 'unknown',
                'file_size' => $file['size'],
                'backup_created' => $isBackupCreated,
                'is_active' => $isActivated,
            )
        );

        return array(
            'success' => true,
            'plugin_slug' => $slug,
            'plugin_name' => isset($plugin_data['Name']) ? $plugin_data['Name'] : $slug,
            'version' => isset($plugin_data['Version']) ? $plugin_data['Version'] : 'unknown',
            'is_active' => $isActivated,
            'backup_created' => $isBackupCreated,
            'backup_location' => $isBackupCreated ? $backup['file_path'] : null,
            'message' => 'Plugin uploaded' . ($isActivated ? ' and activated' : '') . ' successfully' . ($backup !== null ? ', backup of previous version created' : ''),
        );
    }

    /**
     * Check if backup should be created for action.
     *
     * @param string $action Action type.
     * @return bool
     */
    private function should_backup($action) {
        // Use constant with safe default.
        $auto_backup = defined('ONBOARD_AUTO_BACKUP_ENABLED') ? ONBOARD_AUTO_BACKUP_ENABLED : true;
        $isBackupDisabled = !$auto_backup;

        if ($isBackupDisabled) {
            return false;
        }

        $setting = $this->db->get_setting('backup_trigger_' . $action);
        if ($setting !== null) {
            return (bool) $setting;
        }

        // Fall back to constants with safe defaults.
        switch ($action) {
            case 'enable':
                return defined('ONBOARD_BACKUP_TRIGGER_ENABLE') ? ONBOARD_BACKUP_TRIGGER_ENABLE : true;
            case 'disable':
                return defined('ONBOARD_BACKUP_TRIGGER_DISABLE') ? ONBOARD_BACKUP_TRIGGER_DISABLE : true;
            case 'delete':
                return defined('ONBOARD_BACKUP_TRIGGER_DELETE') ? ONBOARD_BACKUP_TRIGGER_DELETE : true;
            case 'upload':
                return defined('ONBOARD_BACKUP_TRIGGER_UPLOAD') ? ONBOARD_BACKUP_TRIGGER_UPLOAD : true;
            default:
                return true;
        }
    }

    /**
     * Find plugin main file.
     *
     * @param string $slug Plugin slug.
     * @return string|null
     */
    private function find_plugin_file($slug) {
        $possible_files = array(
            $slug . '/' . $slug . '.php',
            $slug . '/plugin.php',
            $slug . '/index.php',
        );

        foreach ($possible_files as $file) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $file)) {
                return $file;
            }
        }

        // Search for PHP file with plugin headers.
        $files = glob(WP_PLUGIN_DIR . '/' . $slug . '/*.php');

        foreach ($files as $file) {
            $data = get_file_data($file, array('Name' => 'Plugin Name'));
            if (!empty($data['Name'])) {
                return $slug . '/' . basename($file);
            }
        }

        return null;
    }

    /**
     * Delete plugin directory.
     *
     * @param string $slug Plugin slug.
     */
    private function delete_plugin_directory($slug) {
        $dir = WP_PLUGIN_DIR . '/' . $slug;
        if (is_dir($dir)) {
            $this->recursive_delete($dir);
        }
    }

    /**
     * Recursively delete directory.
     *
     * @param string $dir Directory path.
     */
    private function recursive_delete($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursive_delete($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Get plugins uploaded by this tool.
     *
     * @return array
     */
    public function get_uploaded_plugins() {
        $logs = $this->audit_logger->get_logs(array('action' => 'plugin_uploaded'), 100);
        $uploaded = array();

        foreach ($logs as $log) {
            $plugin = $this->get_plugin($log['plugin_slug']);
            if ($plugin) {
                $uploaded[] = array_merge($plugin, array(
                    'upload_date' => $log['timestamp'],
                    'upload_app' => $log['app_name'],
                    'upload_ip' => $log['ip_address'],
                ));
            }
        }

        return $uploaded;
    }
}
