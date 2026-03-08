<?php
/**
 * Snapshot Restore Trait — Restore from snapshot using shared filesystem utils.
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
 * Handles restoring a plugin from a snapshot ZIP.
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
            OnboardFilesystemUtils::delete_directory($plugin_dir);
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
        $plugin_file = OnboardFilesystemUtils::find_plugin_file($plugin_slug);

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
}
