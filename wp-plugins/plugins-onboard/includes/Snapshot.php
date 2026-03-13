<?php
/**
 * Snapshot class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-filesystem-utils.php';
require_once __DIR__ . '/traits/trait-snapshot-restore.php';
require_once __DIR__ . '/traits/trait-snapshot-query.php';

/**
 * Class OnboardSnapshot
 *
 * Handles plugin snapshot creation and management.
 * Restore logic delegated to OnboardSnapshotRestoreTrait.
 * Query/stats/cleanup delegated to OnboardSnapshotQueryTrait.
 */
class OnboardSnapshot {
    use OnboardSnapshotRestoreTrait;
    use OnboardSnapshotQueryTrait;

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
        $version = $plugin_data['Version'] ?? '0.0.0';

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
}
