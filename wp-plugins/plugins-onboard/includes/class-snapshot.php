<?php
/**
 * Snapshot class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardSnapshot
 *
 * Handles plugin snapshot creation and management.
 */
class OnboardSnapshot {

    /**
     * Database instance.
     *
     * @var OnboardDatabase
     */
    private $db;

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
     * @param OnboardAuditLogger $audit_logger Audit logger instance.
     */
    public function __construct(OnboardDatabase $db, OnboardAuditLogger $audit_logger) {
        $this->db = $db;
        $this->audit_logger = $audit_logger;
    }

    /**
     * Create a snapshot of a plugin.
     *
     * @param string      $plugin_slug    Plugin slug.
     * @param string      $trigger_action Trigger action (pre_enable, pre_disable, etc.).
     * @param string|null $app_id         Application ID.
     * @param string|null $ip_address     IP address.
     * @return array|WP_Error Snapshot data or error.
     */
    public function create($plugin_slug, $trigger_action, $app_id = null, $ip_address = null) {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        if (!is_dir($plugin_dir)) {
            return new WP_Error(
                'plugin_not_found',
                'Plugin directory not found: ' . $plugin_slug,
                array('status' => 404)
            );
        }

        // Get plugin version.
        $plugin_data = $this->get_plugin_data($plugin_slug);
        $version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';

        // Create snapshot directory structure.
        $snapshot_date = date('Ymd-His');
        $snapshot_dir = OnboardPaths::get(OnboardPaths::DIR_PLUGIN_SNAPSHOTS) . $plugin_slug . '/' . $version . '/' . $snapshot_date;

        if (!wp_mkdir_p($snapshot_dir)) {
            return new WP_Error(
                'directory_creation_failed',
                'Failed to create snapshot directory',
                array('status' => 500)
            );
        }

        // Create ZIP file.
        $zip_filename = $plugin_slug . '-v' . $version . '-' . $snapshot_date . '.zip';
        $zip_path = $snapshot_dir . '/' . $zip_filename;

        $result = $this->create_zip($plugin_dir, $zip_path, $plugin_slug);
        if (is_wp_error($result)) {
            return $result;
        }

        // Calculate checksum.
        $checksum = hash_file('sha256', $zip_path);
        $file_size = filesize($zip_path);

        // Store snapshot record.
        $snapshot_id = $this->db->generate_uuid();
        $now = date('Y-m-d H:i:s');

        $this->db->query(
            'INSERT INTO snapshots (snapshot_id, plugin_slug, version, backup_date, file_path, file_size, checksum, trigger_action, requestor_app_id, requestor_ip_address, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $snapshot_id,
                $plugin_slug,
                $version,
                $snapshot_date,
                $zip_path,
                $file_size,
                $checksum,
                $trigger_action,
                $app_id,
                $ip_address,
                $now,
                'success',
            )
        );

        // Update latest-snapshot.txt.
        $latest_file = OnboardPaths::get(OnboardPaths::DIR_PLUGIN_SNAPSHOTS) . $plugin_slug . '/latest-snapshot.txt';
        file_put_contents($latest_file, $version . '\n' . $snapshot_date);

        // Log the snapshot creation.
        $this->audit_logger->log(
            'snapshot_created',
            $plugin_slug,
            $app_id,
            $ip_address,
            'success',
            array(
                'version' => $version,
                'trigger' => $trigger_action,
                'file_size' => $file_size,
                'checksum' => $checksum,
            )
        );

        return array(
            'snapshot_id' => $snapshot_id,
            'plugin_slug' => $plugin_slug,
            'version' => $version,
            'backup_date' => $snapshot_date,
            'file_path' => $zip_path,
            'file_size' => $file_size,
            'checksum' => $checksum,
            'trigger_action' => $trigger_action,
        );
    }

    /**
     * Create ZIP archive of plugin directory.
     *
     * @param string $source_dir  Source directory.
     * @param string $zip_path    Destination ZIP path.
     * @param string $plugin_slug Plugin slug for base directory in ZIP.
     * @return true|WP_Error
     */
    private function create_zip($source_dir, $zip_path, $plugin_slug) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error(
                'zip_not_available',
                'ZipArchive class not available',
                array('status' => 500)
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return new WP_Error(
                'zip_creation_failed',
                'Failed to create ZIP archive',
                array('status' => 500)
            );
        }

        // Add files recursively.
        $this->add_directory_to_zip($zip, $source_dir, $plugin_slug);
        $zip->close();

        return true;
    }

    /**
     * Add directory contents to ZIP archive.
     *
     * @param ZipArchive $zip       ZipArchive instance.
     * @param string     $dir       Directory path.
     * @param string     $base_name Base name for files in ZIP.
     */
    private function add_directory_to_zip($zip, $dir, $base_name) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relative_path = substr($file->getRealPath(), strlen($dir) + 1);
            $archive_path = $base_name . '/' . $relative_path;

            if ($file->isDir()) {
                $zip->addEmptyDir($archive_path);
            } else {
                $zip->addFile($file->getRealPath(), $archive_path);
            }
        }
    }

    /**
     * Get plugin data.
     *
     * @param string $plugin_slug Plugin slug.
     * @return array
     */
    private function get_plugin_data($plugin_slug) {
        $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php';

        // Try common plugin file patterns.
        $possible_files = array(
            $plugin_file,
            WP_PLUGIN_DIR . '/' . $plugin_slug . '/plugin.php',
            WP_PLUGIN_DIR . '/' . $plugin_slug . '/index.php',
        );

        // Look for any PHP file with plugin headers.
        if (!file_exists($plugin_file)) {
            $files = glob(WP_PLUGIN_DIR . '/' . $plugin_slug . '/*.php');

            foreach ($files as $file) {
                $data = get_file_data($file, array(
                    'Name' => 'Plugin Name',
                    'Version' => 'Version',
                ));
                if (!empty($data['Name'])) {
                    return $data;
                }
            }
        }

        foreach ($possible_files as $file) {
            if (file_exists($file)) {
                return get_file_data($file, array(
                    'Name' => 'Plugin Name',
                    'Version' => 'Version',
                    'Description' => 'Description',
                    'Author' => 'Author',
                    'AuthorURI' => 'Author URI',
                    'PluginURI' => 'Plugin URI',
                ));
            }
        }

        return array('Version' => '0.0.0');
    }

    /**
     * Get snapshots for a plugin.
     *
     * @param string $plugin_slug Plugin slug.
     * @param int    $limit       Limit.
     * @param int    $offset      Offset.
     * @return array
     */
    public function get_snapshots($plugin_slug, $limit = 50, $offset = 0) {
        return $this->db->query(
            'SELECT * FROM snapshots WHERE plugin_slug = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($plugin_slug, $limit, $offset)
        )->fetchAll();
    }

    /**
     * Get all snapshots.
     *
     * @param int $limit  Limit.
     * @param int $offset Offset.
     * @return array
     */
    public function get_all_snapshots($limit = 100, $offset = 0) {
        return $this->db->query(
            'SELECT * FROM snapshots ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($limit, $offset)
        )->fetchAll();
    }

    /**
     * Get snapshot by ID.
     *
     * @param string $snapshot_id Snapshot ID.
     * @return array|null
     */
    public function get_snapshot($snapshot_id) {
        return $this->db->query(
            'SELECT * FROM snapshots WHERE snapshot_id = ?',
            array($snapshot_id)
        )->fetch();
    }

    /**
     * Get snapshot by version and date.
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $version     Version.
     * @param string $backup_date Backup date.
     * @return array|null
     */
    public function get_snapshot_by_version($plugin_slug, $version, $backup_date) {
        return $this->db->query(
            'SELECT * FROM snapshots WHERE plugin_slug = ? AND version = ? AND backup_date = ?',
            array($plugin_slug, $version, $backup_date)
        )->fetch();
    }

    /**
     * Get latest snapshot for a plugin.
     *
     * @param string $plugin_slug Plugin slug.
     * @return array|null
     */
    public function get_latest_snapshot($plugin_slug) {
        return $this->db->query(
            'SELECT * FROM snapshots WHERE plugin_slug = ? ORDER BY created_at DESC LIMIT 1',
            array($plugin_slug)
        )->fetch();
    }

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

        $zip->extractTo(WP_PLUGIN_DIR);
        $zip->close();

        // Activate plugin.
        $plugin_file = $this->find_plugin_file($plugin_slug);
        if ($plugin_file) {
            activate_plugin($plugin_file);
        }

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
                'current_backup_created' => !is_wp_error($current_backup),
            )
        );

        return array(
            'success' => true,
            'plugin_slug' => $plugin_slug,
            'restored_version' => $snapshot['version'],
            'backup_of_current_created' => !is_wp_error($current_backup),
            'backup_location' => !is_wp_error($current_backup) ? $current_backup['file_path'] : null,
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

    /**
     * Delete a snapshot.
     *
     * @param string $snapshot_id Snapshot ID.
     * @return bool
     */
    public function delete($snapshot_id) {
        $snapshot = $this->get_snapshot($snapshot_id);
        if (!$snapshot) {
            return false;
        }

        // Delete file.
        if (file_exists($snapshot['file_path'])) {
            unlink($snapshot['file_path']);
        }

        // Delete database record.
        $this->db->query('DELETE FROM snapshots WHERE snapshot_id = ?', array($snapshot_id));

        // Log deletion.
        $this->audit_logger->log(
            'snapshot_deleted',
            $snapshot['plugin_slug'],
            null,
            null,
            'success',
            array(
                'version' => $snapshot['version'],
                'backup_date' => $snapshot['backup_date'],
            )
        );

        return true;
    }

    /**
     * Get snapshot count for a plugin.
     *
     * @param string $plugin_slug Plugin slug.
     * @return int
     */
    public function get_snapshot_count($plugin_slug) {
        $result = $this->db->query(
            'SELECT COUNT(*) as count FROM snapshots WHERE plugin_slug = ?',
            array($plugin_slug)
        )->fetch();
        return (int) $result['count'];
    }

    /**
     * Get total snapshot count.
     *
     * @return int
     */
    public function get_total_count() {
        $result = $this->db->query('SELECT COUNT(*) as count FROM snapshots')->fetch();
        return (int) $result['count'];
    }

    /**
     * Get total snapshot size.
     *
     * @return int Size in bytes.
     */
    public function get_total_size() {
        $result = $this->db->query('SELECT SUM(file_size) as total FROM snapshots')->fetch();
        return (int) $result['total'];
    }

    /**
     * Get unique plugins with snapshots.
     *
     * @return array
     */
    public function get_plugins_with_snapshots() {
        return $this->db->query(
            'SELECT plugin_slug, COUNT(*) as snapshot_count, MAX(created_at) as last_backup FROM snapshots GROUP BY plugin_slug ORDER BY last_backup DESC'
        )->fetchAll();
    }

    /**
     * Clean up old snapshots based on retention policy.
     *
     * @param int|null $retention_count Number of snapshots to keep per plugin.
     * @return int Number of snapshots deleted.
     */
    public function cleanup($retention_count = null) {
        if ($retention_count === null) {
            // Use constant with safe default.
            $default_retention = defined('ONBOARD_SNAPSHOT_RETENTION_COUNT') ? ONBOARD_SNAPSHOT_RETENTION_COUNT : 5;
            $retention_count = $this->db->get_setting('snapshot_retention_count') ?: $default_retention;
        }

        $plugins = $this->get_plugins_with_snapshots();
        $deleted = 0;

        foreach ($plugins as $plugin) {
            $slug = $plugin['plugin_slug'];
            
            // Get snapshots to delete (older than retention count).
            $old_snapshots = $this->db->query(
                'SELECT * FROM snapshots WHERE plugin_slug = ? ORDER BY created_at DESC LIMIT -1 OFFSET ?',
                array($slug, $retention_count)
            )->fetchAll();

            foreach ($old_snapshots as $snapshot) {
                if ($this->delete($snapshot['snapshot_id'])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
