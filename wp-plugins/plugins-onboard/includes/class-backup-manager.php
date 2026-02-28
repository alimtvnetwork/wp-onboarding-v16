<?php
/**
 * Backup Manager class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardBackupManager
 *
 * Handles backup operations and exports.
 */
class OnboardBackupManager {

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
     * Download all plugins as ZIP.
     *
     * @return string|WP_Error Path to ZIP file or error.
     */
    public function download_all_plugins() {
        return $this->create_plugins_export('all');
    }

    /**
     * Download active plugins as ZIP.
     *
     * @return string|WP_Error Path to ZIP file or error.
     */
    public function download_active_plugins() {
        return $this->create_plugins_export('active');
    }

    /**
     * Create plugins export ZIP.
     *
     * @param string $type Export type (all, active).
     * @return string|WP_Error Path to ZIP file or error.
     */
    private function create_plugins_export($type) {
        // Get plugins list.
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());

        $plugins_to_export = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }

            if ($type === 'all' || in_array($plugin_file, $active_plugins, true)) {
                $plugins_to_export[$slug] = array(
                    'file' => $plugin_file,
                    'data' => $plugin_data,
                    'is_active' => in_array($plugin_file, $active_plugins, true),
                );
            }
        }

        // Create temp directory.
        $timestamp = date('Ymd-His');
        $export_dir = OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS) . 'export-' . $timestamp;
        wp_mkdir_p($export_dir);

        // Create ZIP.
        $zip_filename = ($type === 'all' ? 'all-plugins' : 'active-plugins') . '-' . $timestamp . '.zip';
        $zip_path = OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS) . $zip_filename;

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
            return new WP_Error('zip_creation_failed', 'Failed to create ZIP archive');
        }

        // Add metadata.
        $metadata = array(
            'export_date' => $timestamp,
            'export_type' => $type . '_plugins',
            'wordpress_version' => get_bloginfo('version'),
            'onboard_version' => ONBOARD_PLUGIN_VERSION,
            'plugin_count' => count($plugins_to_export),
            'plugins' => array(),
        );

        // Add plugin directories.
        foreach ($plugins_to_export as $slug => $plugin_info) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            
            if (is_dir($plugin_dir)) {
                $this->add_directory_to_zip($zip, $plugin_dir, 'plugins/' . $slug);
            } elseif (file_exists(WP_PLUGIN_DIR . '/' . $plugin_info['file'])) {
                // Single file plugin.
                $zip->addFile(WP_PLUGIN_DIR . '/' . $plugin_info['file'], 'plugins/' . $plugin_info['file']);
            }

            $metadata['plugins'][$slug] = array(
                'name' => $plugin_info['data']['Name'],
                'version' => $plugin_info['data']['Version'],
                'is_active' => $plugin_info['is_active'],
            );
        }

        $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        $zip->close();

        // Log export.
        $this->audit_logger->log(
            'plugins_exported',
            null,
            null,
            null,
            'success',
            array(
                'type' => $type,
                'plugin_count' => count($plugins_to_export),
                'file_path' => $zip_path,
            )
        );

        return $zip_path;
    }

    /**
     * Download all snapshots as ZIP.
     *
     * @return string|WP_Error Path to ZIP file or error.
     */
    public function download_snapshots() {
        $snapshots = $this->snapshot->get_all_snapshots(1000);

        if (empty($snapshots)) {
            return new WP_Error('no_snapshots', 'No snapshots available to export');
        }

        // Create ZIP.
        $timestamp = date('Ymd-His');
        $zip_filename = 'snapshots-export-' . $timestamp . '.zip';
        $zip_path = OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS) . $zip_filename;

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
            return new WP_Error('zip_creation_failed', 'Failed to create ZIP archive');
        }

        // Add metadata.
        $metadata = array(
            'export_date' => $timestamp,
            'export_type' => 'snapshots',
            'wordpress_version' => get_bloginfo('version'),
            'onboard_version' => ONBOARD_PLUGIN_VERSION,
            'snapshot_count' => count($snapshots),
            'snapshots' => array(),
        );

        $total_size = 0;

        foreach ($snapshots as $snapshot) {
            if (file_exists($snapshot['file_path'])) {
                $relative_path = str_replace(OnboardPaths::get(OnboardPaths::DIR_PLUGIN_SNAPSHOTS), '', $snapshot['file_path']);
                $zip->addFile($snapshot['file_path'], 'snapshots/' . $relative_path);
                $total_size += $snapshot['file_size'];

                $metadata['snapshots'][] = array(
                    'snapshot_id' => $snapshot['snapshot_id'],
                    'plugin_slug' => $snapshot['plugin_slug'],
                    'version' => $snapshot['version'],
                    'backup_date' => $snapshot['backup_date'],
                    'file_size' => $snapshot['file_size'],
                    'checksum' => $snapshot['checksum'],
                );
            }
        }

        $metadata['total_size'] = $total_size;

        $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        $zip->close();

        // Log export.
        $this->audit_logger->log(
            'snapshots_exported',
            null,
            null,
            null,
            'success',
            array(
                'snapshot_count' => count($snapshots),
                'total_size' => $total_size,
                'file_path' => $zip_path,
            )
        );

        return $zip_path;
    }

    /**
     * Download databases as ZIP.
     *
     * @return string|WP_Error Path to ZIP file or error.
     */
    public function download_databases() {
        $timestamp = date('Ymd-His');
        $zip_filename = 'databases-' . $timestamp . '.zip';
        $zip_path = OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS) . $zip_filename;

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
            return new WP_Error('zip_creation_failed', 'Failed to create ZIP archive');
        }

        // Add databases.
        if (file_exists(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE))) {
            $zip->addFile(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE), 'plugin-manager.sqlite');
        }

        if (file_exists(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE))) {
            $zip->addFile(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE), 'audit.sqlite');
        }

        // Add metadata.
        $metadata = array(
            'export_date' => $timestamp,
            'export_type' => 'databases',
            'wordpress_version' => get_bloginfo('version'),
            'onboard_version' => ONBOARD_PLUGIN_VERSION,
            'files' => array(
                'plugin-manager.sqlite' => file_exists(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE)) ? filesize(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE)) : 0,
                'audit.sqlite' => file_exists(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE)) ? filesize(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE)) : 0,
            ),
        );

        $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        $zip->close();

        // Log export.
        $this->audit_logger->log(
            'databases_exported',
            null,
            null,
            null,
            'success',
            array('file_path' => $zip_path)
        );

        return $zip_path;
    }

    /**
     * Import databases from ZIP.
     *
     * @param string $zip_path Path to ZIP file.
     * @return array|WP_Error
     */
    public function import_databases($zip_path) {
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('zip_open_failed', 'Failed to open ZIP file');
        }

        // Validate structure.
        $has_metadata = $zip->locateName('metadata.json') !== false;
        $has_plugin_db = $zip->locateName('plugin-manager.sqlite') !== false;
        $has_audit_db = $zip->locateName('audit.sqlite') !== false;

        $isMissingMetadata = !$has_metadata;

        if ($isMissingMetadata) {
            $zip->close();
            return new WP_Error('invalid_export', 'Invalid export file - missing metadata');
        }

        // Backup existing databases.
        $timestamp = date('Ymd-His');
        $backup_dir = OnboardPaths::get(OnboardPaths::DIR_PLUGIN_DATA) . 'backups/' . $timestamp;
        wp_mkdir_p($backup_dir);

        if (file_exists(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE))) {
            copy(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE), $backup_dir . '/plugin-manager.sqlite');
        }
        if (file_exists(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE))) {
            copy(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE), $backup_dir . '/audit.sqlite');
        }

        // Extract and replace databases.
        $restored = array();

        if ($has_plugin_db) {
            $content = $zip->getFromName('plugin-manager.sqlite');
            file_put_contents(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE), $content);
            $restored[] = 'plugin-manager.sqlite';
        }

        if ($has_audit_db) {
            $content = $zip->getFromName('audit.sqlite');
            file_put_contents(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE), $content);
            $restored[] = 'audit.sqlite';
        }

        $zip->close();

        // Log import.
        $this->audit_logger->log(
            'databases_imported',
            null,
            null,
            null,
            'success',
            array(
                'restored_files' => $restored,
                'backup_location' => $backup_dir,
            )
        );

        return array(
            'success' => true,
            'restored' => $restored,
            'backup_location' => $backup_dir,
        );
    }

    /**
     * Import snapshots from ZIP.
     *
     * @param string $zip_path Path to ZIP file.
     * @return array|WP_Error
     */
    public function import_snapshots($zip_path) {
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('zip_open_failed', 'Failed to open ZIP file');
        }

        // Read metadata.
        $metadata_content = $zip->getFromName('metadata.json');
        $isMetadataMissing = !$metadata_content;

        if ($isMetadataMissing) {
            $zip->close();
            return new WP_Error('invalid_export', 'Invalid export file - missing metadata');
        }

        $metadata = json_decode($metadata_content, true);
        $isWrongExportType = ($metadata['export_type'] !== 'snapshots');

        if ($isWrongExportType) {
            $zip->close();
            return new WP_Error('invalid_export_type', 'This is not a snapshots export file');
        }

        // Extract snapshots.
        $imported = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, 'snapshots/') === 0 && substr($filename, -4) === '.zip') {
                $relative_path = substr($filename, strlen('snapshots/'));
                $dest_path = OnboardPaths::get(OnboardPaths::DIR_PLUGIN_SNAPSHOTS) . $relative_path;
                $dest_dir = dirname($dest_path);

                wp_mkdir_p($dest_dir);
                file_put_contents($dest_path, $zip->getFromIndex($i));
                $imported++;
            }
        }

        // Import snapshot records from metadata.
        foreach ($metadata['snapshots'] as $snapshot_data) {
            $relative_path = $snapshot_data['plugin_slug'] . '/' . $snapshot_data['version'] . '/' . $snapshot_data['backup_date'];
            $file_path = OnboardPaths::get(OnboardPaths::DIR_PLUGIN_SNAPSHOTS) . $relative_path . '/' . $snapshot_data['plugin_slug'] . '-v' . $snapshot_data['version'] . '-' . $snapshot_data['backup_date'] . '.zip';

            if (file_exists($file_path)) {
                // Check if already exists in database.
                $existing = $this->snapshot->get_snapshot_by_version(
                    $snapshot_data['plugin_slug'],
                    $snapshot_data['version'],
                    $snapshot_data['backup_date']
                );

                $isNewSnapshot = !$existing;

                if ($isNewSnapshot) {
                        'INSERT INTO snapshots (snapshot_id, plugin_slug, version, backup_date, file_path, file_size, checksum, trigger_action, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        array(
                            $snapshot_data['snapshot_id'],
                            $snapshot_data['plugin_slug'],
                            $snapshot_data['version'],
                            $snapshot_data['backup_date'],
                            $file_path,
                            $snapshot_data['file_size'],
                            $snapshot_data['checksum'],
                            'imported',
                            date('Y-m-d H:i:s'),
                            'success',
                        )
                    );
                }
            }
        }

        $zip->close();

        // Log import.
        $this->audit_logger->log(
            'snapshots_imported',
            null,
            null,
            null,
            'success',
            array('imported_count' => $imported)
        );

        return array(
            'success' => true,
            'imported_count' => $imported,
        );
    }

    /**
     * Get database info.
     *
     * @return array
     */
    public function get_database_info() {
        $plugin_db_size = file_exists(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE)) ? filesize(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE)) : 0;
        $audit_db_size = file_exists(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE)) ? filesize(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE)) : 0;

        $plugin_db_modified = file_exists(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE)) ? filemtime(OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE)) : null;
        $audit_db_modified = file_exists(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE)) ? filemtime(OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE)) : null;

        return array(
            'plugin_manager_db' => array(
                'path' => OnboardPaths::get(OnboardPaths::FILE_MAIN_DATABASE),
                'size' => $plugin_db_size,
                'modified' => $plugin_db_modified ? date('Y-m-d H:i:s', $plugin_db_modified) : null,
            ),
            'audit_db' => array(
                'path' => OnboardPaths::get(OnboardPaths::FILE_AUDIT_DATABASE),
                'size' => $audit_db_size,
                'modified' => $audit_db_modified ? date('Y-m-d H:i:s', $audit_db_modified) : null,
            ),
            'total_size' => $plugin_db_size + $audit_db_size,
        );
    }

    /**
     * Get temp directory info.
     *
     * @return array
     */
    public function get_temp_info() {
        $total_size = 0;
        $files = array();

        if (is_dir(OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS))) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS), RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total_size += $file->getSize();
                    $files[] = array(
                        'path' => $file->getPathname(),
                        'size' => $file->getSize(),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    );
                }
            }
        }

        return array(
            'path' => OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS),
            'total_size' => $total_size,
            'file_count' => count($files),
            'files' => $files,
            'size_warning' => $total_size > ONBOARD_TEMP_SIZE_WARNING,
        );
    }

    /**
     * Add directory to ZIP.
     *
     * @param ZipArchive $zip       ZipArchive instance.
     * @param string     $dir       Directory path.
     * @param string     $base_name Base name in ZIP.
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
}
