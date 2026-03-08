<?php
/**
 * Snapshot Restore Trait — Restore from snapshot and filesystem helpers.
 *
 * @package PluginsOnboard
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait OnboardSnapshotRestoreTrait
 *
 * Handles restoring a plugin from a snapshot ZIP, plus shared
 * filesystem utilities (find_plugin_file, delete_directory).
 */
trait OnboardSnapshotRestoreTrait {

    /**
     * Restore a plugin from snapshot.
     *
     * @param string      $snapshot_id Snapshot ID.
     * @param string|null $app_id      Application ID.
     * @param string|null $ip_address  IP address.
     * @return array|WP_Error
     */
    public function restore($snapshot_id, $app_id = null, $ip_address = null) {
        $snapshot = $this->get_snapshot($snapshot_id);

        if (!$snapshot) {
            return new WP_Error(
                'snapshot_not_found',
                'Snapshot not found',
                array('status' => 404)
            );
        }

        // Verify file exists.
        if (!file_exists($snapshot['file_path'])) {
            return new WP_Error(
                'snapshot_file_missing',
                'Snapshot file not found on disk',
                array('status' => 404)
            );
        }

        // Verify checksum.
        $current_checksum = hash_file('sha256', $snapshot['file_path']);

        if ($current_checksum !== $snapshot['checksum']) {
            return new WP_Error(
                'checksum_mismatch',
                'Snapshot file checksum mismatch - file may be corrupted',
                array('status' => 500)
            );
        }

        $plugin_slug = $snapshot['plugin_slug'];
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        // Create backup of current version before restore.
        $current_backup = null;

        if (is_dir($plugin_dir)) {
            $current_backup = $this->create($plugin_slug, 'pre_restore', $app_id, $ip_address);
        }

        // Delete current plugin directory.
        if (is_dir($plugin_dir)) {
            $this->delete_directory($plugin_dir);
        }

        // Extract snapshot.
        $zip = new ZipArchive();

        if ($zip->open($snapshot['file_path']) !== true) {
            return new WP_Error(
                'zip_open_failed',
                'Failed to open snapshot ZIP',
                array('status' => 500)
            );
        }

        $isExtracted = $zip->extractTo(WP_PLUGIN_DIR);
        $zip->close();

        if ($isExtracted === false) {
            return new WP_Error(
                'zip_extract_failed',
                'Failed to extract snapshot ZIP contents',
                array('status' => 500)
            );
        }

        // Activate plugin.
        $plugin_file = $this->find_plugin_file($plugin_slug);

        if ($plugin_file) {
            activate_plugin($plugin_file);
        }

        $isBackupCreated = !is_wp_error($current_backup);

        // Log restore.
        $this->audit_logger->log(
            'plugin_restored',
            $plugin_slug,
            $app_id,
            $ip_address,
            'success',
            array(
                'restored_version' => $snapshot['version'],
                'restored_from' => $snapshot['backup_date'],
                'current_backup_created' => $isBackupCreated,
            )
        );

        return array(
            'success' => true,
            'plugin_slug' => $plugin_slug,
            'restored_version' => $snapshot['version'],
            'backup_of_current_created' => $isBackupCreated,
            'backup_location' => $isBackupCreated ? $current_backup['file_path'] : null,
        );
    }

    /**
     * Find plugin main file.
     *
     * @param string $plugin_slug Plugin slug.
     * @return string|null
     */
    private function find_plugin_file($plugin_slug) {
        $possible_files = array(
            $plugin_slug . '/' . $plugin_slug . '.php',
            $plugin_slug . '/plugin.php',
            $plugin_slug . '/index.php',
        );

        foreach ($possible_files as $file) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $file)) {
                return $file;
            }
        }

        // Search for PHP file with plugin headers.
        $files = glob(WP_PLUGIN_DIR . '/' . $plugin_slug . '/*.php');

        foreach ($files as $file) {
            $data = get_file_data($file, array('Name' => 'Plugin Name'));

            if (!empty($data['Name'])) {
                return $plugin_slug . '/' . basename($file);
            }
        }

        return null;
    }

    /**
     * Delete directory recursively.
     *
     * @param string $dir Directory path.
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
