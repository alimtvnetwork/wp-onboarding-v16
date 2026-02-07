<?php
/**
 * Riseup Asia Uploader - Snapshot Orchestrator
 *
 * End-to-end full backup flow: settings → dependency graph → worker pool
 * → plugin snapshots → a-root.db finalization → ZIP export.
 *
 * @package RiseupAsiaUploader
 * @since   1.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Orchestrator class.
 *
 * Coordinates the complete backup pipeline:
 * 1. Read settings from snapshot_settings
 * 2. Create snapshot directory
 * 3. Build dependency graph via RiseupDependencyAnalyzer
 * 4. Export tables via RiseupSnapshotWorker
 * 5. Snapshot installed plugins as ZIPs (optional)
 * 6. Finalize a-root.db with stats
 * 7. Package everything into a single ZIP (optional)
 * 8. Register snapshot in the snapshots table
 */
class RiseupSnapshotOrchestrator {

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupSnapshotManager */
    private $manager;

    /** @var RiseupSnapshotWorker */
    private $worker;

    /** @var RiseupRootDb */
    private $rootDb;

    /** @var RiseupDependencyAnalyzer */
    private $analyzer;

    /** @var wpdb */
    private $wpdb;

    /** @var RiseupSnapshotOrchestrator|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger|null         $logger   Logger.
     * @param RiseupDatabase|null           $db       Plugin database.
     * @param RiseupSnapshotManager|null    $manager  Snapshot manager.
     * @return RiseupSnapshotOrchestrator
     */
    public static function getInstance($logger = null, $db = null, $manager = null) {
        if (self::$instance === null && $logger && $db && $manager) {
            self::$instance = new self($logger, $db, $manager);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger      $logger  Logger.
     * @param RiseupDatabase        $db      Plugin database.
     * @param RiseupSnapshotManager $manager Snapshot manager.
     */
    private function __construct($logger, $db, $manager) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->manager = $manager;

        $this->analyzer = RiseupDependencyAnalyzer::getInstance($logger);
        $this->rootDb = RiseupRootDb::getInstance($logger, $this->analyzer);
        $this->worker = RiseupSnapshotWorker::getInstance($logger, $db, $this->rootDb, $this->analyzer);
    }

    /**
     * Execute a full end-to-end backup.
     *
     * @param array $options Options: title, scope, include_plugins, plugin_selection, compression.
     * @return array Result with success, path, zip_path, stats.
     */
    public function executeFullBackup($options = array()) {
        $start_time = microtime(true);

        // 1. Read settings (merge with overrides)
        $settings = $this->manager->getSettings();
        $title = $options['title'] ?? ('Full Backup ' . date('Y-m-d H:i'));
        $scope = $options['scope'] ?? $settings['scope'] ?? RISEUP_SNAPSHOT_SCOPE_WORDPRESS;
        $include_plugins = $options['include_plugins'] ?? $settings['include_plugins'] ?? true;
        $plugin_selection = $options['plugin_selection'] ?? $settings['plugin_selection'] ?? 'all';
        $compression = $options['compression'] ?? $settings['compression'] ?? true;

        $this->log('INFO', 'Starting full backup orchestration', array(
            'title'            => $title,
            'scope'            => $scope,
            'include_plugins'  => $include_plugins,
            'plugin_selection' => $plugin_selection,
            'compression'      => $compression,
        ));

        try {
            // 2. Execute per-table export via worker
            $worker_result = $this->worker->execute(array(
                'title'    => $title,
                'scope'    => $scope,
                'type'     => 'full',
                'settings' => $settings,
            ));

            if (!$worker_result['success']) {
                return array(
                    'success' => false,
                    'error'   => 'Table export failed: ' . ($worker_result['error'] ?? 'Unknown error'),
                    'phase'   => 'table_export',
                );
            }

            $snapshot_dir = $worker_result['path'];
            $this->log('INFO', 'Table export complete', array(
                'tables'     => $worker_result['tables'],
                'total_rows' => $worker_result['total_rows'],
                'directory'  => $worker_result['directory'],
            ));

            // 3. Plugin snapshots (optional)
            $plugin_stats = array('count' => 0, 'total_size' => 0);
            if ($include_plugins) {
                $plugin_stats = $this->snapshotPlugins($snapshot_dir, $plugin_selection);
                $this->log('INFO', 'Plugin snapshots complete', array(
                    'count'      => $plugin_stats['count'],
                    'total_size' => $this->formatBytes($plugin_stats['total_size']),
                ));
            }

            // 4. Register in snapshots table for tracking
            $snapshot_id = $this->registerSnapshot(
                $title,
                $scope,
                $worker_result,
                $plugin_stats,
                $snapshot_dir
            );

            // 5. ZIP export (optional)
            $zip_path = null;
            $zip_size = 0;
            if ($compression) {
                $zip_result = $this->createZipExport($snapshot_dir, $title);
                if ($zip_result['success']) {
                    $zip_path = $zip_result['path'];
                    $zip_size = $zip_result['size'];
                    $this->log('INFO', 'ZIP export complete', array(
                        'path' => basename($zip_path),
                        'size' => $this->formatBytes($zip_size),
                    ));
                } else {
                    $this->log('WARN', 'ZIP export failed (non-fatal)', array(
                        'error' => $zip_result['error'],
                    ));
                }
            }

            $duration = microtime(true) - $start_time;

            $this->log('INFO', 'Full backup orchestration complete', array(
                'snapshot_id'     => $snapshot_id,
                'tables'          => $worker_result['tables'],
                'total_rows'      => $worker_result['total_rows'],
                'plugins'         => $plugin_stats['count'],
                'zip'             => $zip_path ? basename($zip_path) : 'disabled',
                'duration'        => round($duration, 2) . 's',
                'worker_errors'   => count($worker_result['errors'] ?? array()),
            ));

            return array(
                'success'      => true,
                'snapshot_id'  => $snapshot_id,
                'directory'    => $worker_result['directory'],
                'path'         => $snapshot_dir,
                'tables'       => $worker_result['tables'],
                'total_rows'   => $worker_result['total_rows'],
                'plugins'      => $plugin_stats['count'],
                'zip_path'     => $zip_path,
                'zip_size'     => $zip_size,
                'duration'     => $duration,
                'errors'       => $worker_result['errors'] ?? array(),
            );

        } catch (Exception $e) {
            $this->log('ERROR', 'Full backup orchestration failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
                'phase'   => 'orchestration',
            );
        }
    }

    /**
     * Snapshot installed WordPress plugins as ZIP files.
     *
     * @param string $snapshot_dir Snapshot directory.
     * @param string $selection    'all' or 'selective' (only active).
     * @return array Stats: count, total_size, plugins[].
     */
    private function snapshotPlugins($snapshot_dir, $selection = 'all') {
        $plugins_dir = $snapshot_dir . '/plugins';
        if (!RiseupPathUtils::ensureDir($plugins_dir, true)) {
            $this->log('ERROR', 'Failed to create plugins directory');
            return array('count' => 0, 'total_size' => 0, 'plugins' => array());
        }

        // Get installed plugins
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());

        // Filter based on selection
        $plugins_to_snapshot = array();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }

            // Skip self
            if ($slug === RISEUP_SLUG) {
                continue;
            }

            if ($selection === 'all' || in_array($plugin_file, $active_plugins)) {
                $plugins_to_snapshot[$plugin_file] = array(
                    'slug'    => $slug,
                    'name'    => $plugin_data['Name'] ?? $slug,
                    'version' => $plugin_data['Version'] ?? '0.0.0',
                );
            }
        }

        $this->log('INFO', 'Snapshotting plugins', array(
            'total'     => count($all_plugins),
            'selected'  => count($plugins_to_snapshot),
            'selection' => $selection,
        ));

        // Open a-root.db to register plugin snapshots
        $root_path = $snapshot_dir . '/a-root.db';
        $rootPdo = null;
        if (file_exists($root_path)) {
            try {
                $rootPdo = new PDO('sqlite:' . $root_path);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Exception $e) {
                $this->log('WARN', 'Could not open a-root.db for plugin registration', array('error' => $e->getMessage()));
            }
        }

        $count = 0;
        $total_size = 0;
        $plugin_list = array();

        foreach ($plugins_to_snapshot as $plugin_file => $info) {
            $slug = $info['slug'];
            $plugin_path = WP_PLUGIN_DIR . '/' . $slug;

            if (!is_dir($plugin_path)) {
                // Single-file plugin — skip ZIP, not a directory
                $this->log('INFO', 'Skipping single-file plugin: ' . $slug);
                continue;
            }

            $zip_filename = $slug . '.zip';
            $zip_path = $plugins_dir . '/' . $zip_filename;

            $zip_result = $this->createPluginZip($plugin_path, $zip_path, $slug);

            if ($zip_result['success']) {
                $file_size = filesize($zip_path);
                $checksum = md5_file($zip_path);
                $total_size += $file_size;
                $count++;

                $plugin_list[] = array(
                    'slug'    => $slug,
                    'name'    => $info['name'],
                    'version' => $info['version'],
                    'zip'     => $zip_filename,
                    'size'    => $file_size,
                );

                // Register in a-root.db
                if ($rootPdo) {
                    $this->rootDb->registerPluginSnapshot($rootPdo, array(
                        'plugin_slug'    => $slug,
                        'plugin_name'    => $info['name'],
                        'plugin_version' => $info['version'],
                        'zip_file'       => 'plugins/' . $zip_filename,
                        'file_size_bytes' => $file_size,
                        'checksum_md5'   => $checksum,
                    ));
                }

                $this->log('INFO', sprintf('Plugin archived: %s (%s)',
                    $info['name'], $this->formatBytes($file_size)
                ));
            } else {
                $this->log('WARN', 'Failed to archive plugin: ' . $slug, array(
                    'error' => $zip_result['error'],
                ));
            }
        }

        if ($rootPdo) {
            $rootPdo = null; // Close
        }

        return array(
            'count'      => $count,
            'total_size' => $total_size,
            'plugins'    => $plugin_list,
        );
    }

    /**
     * Create a ZIP archive from a plugin directory.
     *
     * @param string $source_dir Plugin source directory.
     * @param string $zip_path   Destination ZIP path.
     * @param string $slug       Plugin slug (root folder name in ZIP).
     * @return array Result: success, error.
     */
    private function createPluginZip($source_dir, $zip_path, $slug) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return array('success' => false, 'error' => 'Failed to create ZIP');
            }

            $source_dir = rtrim($source_dir, '/\\');
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $relative = $slug . '/' . substr($item->getPathname(), strlen($source_dir) + 1);
                $relative = str_replace('\\', '/', $relative);

                if ($item->isDir()) {
                    $zip->addEmptyDir($relative);
                } else {
                    $zip->addFile($item->getPathname(), $relative);
                }
            }

            // Explicit close (not defer!) per zip-creation-rules
            $zip->close();

            // Verify non-zero size
            $size = filesize($zip_path);
            if ($size === 0) {
                @unlink($zip_path);
                return array('success' => false, 'error' => 'ZIP file is empty');
            }

            return array('success' => true);

        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Create a ZIP export of the entire snapshot directory.
     *
     * @param string $snapshot_dir Snapshot directory path.
     * @param string $title        Snapshot title for ZIP filename.
     * @return array Result: success, path, size, error.
     */
    private function createZipExport($snapshot_dir, $title) {
        try {
            $zip_filename = sanitize_title($title) . '_' . date('Y-m-d_His') . '.zip';
            $base_dir = dirname($snapshot_dir);
            $zip_path = $base_dir . '/' . $zip_filename;
            $dir_basename = basename($snapshot_dir);

            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return array('success' => false, 'error' => 'Failed to create ZIP');
            }

            // Recursively add all files from snapshot directory
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($snapshot_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $file_count = 0;
            foreach ($iterator as $item) {
                $relative = substr($item->getPathname(), strlen($snapshot_dir) + 1);
                $relative = str_replace('\\', '/', $relative);

                if ($item->isDir()) {
                    $zip->addEmptyDir($relative);
                } else {
                    $zip->addFile($item->getPathname(), $relative);
                    $file_count++;
                }
            }

            // Explicit close per zip-creation-rules
            $zip->close();

            $size = filesize($zip_path);
            if ($size === 0) {
                @unlink($zip_path);
                return array('success' => false, 'error' => 'ZIP export is empty');
            }

            $this->log('INFO', 'ZIP export created', array(
                'filename'   => $zip_filename,
                'files'      => $file_count,
                'size'       => $this->formatBytes($size),
            ));

            return array(
                'success'  => true,
                'path'     => $zip_path,
                'filename' => $zip_filename,
                'size'     => $size,
                'files'    => $file_count,
            );

        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Register the completed snapshot in the snapshots tracking table.
     *
     * @param string $title         Snapshot title.
     * @param string $scope         Table scope.
     * @param array  $worker_result Worker execution result.
     * @param array  $plugin_stats  Plugin snapshot stats.
     * @param string $snapshot_dir  Snapshot directory path.
     * @return int|false Snapshot ID or false.
     */
    private function registerSnapshot($title, $scope, $worker_result, $plugin_stats, $snapshot_dir) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return false;
        }

        try {
            // Get next sequence
            $seq_result = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . RISEUP_TABLE_SNAPSHOTS);
            $row = $seq_result->fetch(PDO::FETCH_ASSOC);
            $sequence = ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;

            $now = gmdate('c');
            $filename = basename($snapshot_dir);

            // Build tables list from worker
            $tables_json = json_encode(array(
                'exported'       => $worker_result['tables'] ?? 0,
                'total_rows'     => $worker_result['total_rows'] ?? 0,
                'errors'         => $worker_result['errors'] ?? array(),
                'plugins'        => $plugin_stats['count'] ?? 0,
                'plugin_details' => $plugin_stats['plugins'] ?? array(),
            ));

            $stmt = $pdo->prepare("INSERT INTO " . RISEUP_TABLE_SNAPSHOTS . "
                (sequence, filename, filepath, provider, scope, tables_json, total_rows,
                 file_size, trigger_source, status, created_at, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Calculate total directory size
            $dir_size = $this->getDirectorySize($snapshot_dir);

            $stmt->execute(array(
                $sequence,
                $filename,
                $snapshot_dir,
                RISEUP_SNAPSHOT_PROVIDER_NATIVE,
                $scope,
                $tables_json,
                $worker_result['total_rows'] ?? 0,
                $dir_size,
                RISEUP_SNAPSHOT_TRIGGER_API,
                RISEUP_SNAPSHOT_STATUS_COMPLETE,
                $now,
                $now,
            ));

            return (int)$pdo->lastInsertId();

        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to register snapshot', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Get total size of a directory recursively.
     *
     * @param string $dir Directory path.
     * @return int Total size in bytes.
     */
    private function getDirectorySize($dir) {
        $size = 0;
        if (!is_dir($dir)) return 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Format bytes to human-readable string.
     *
     * @param int $bytes Byte count.
     * @return string Formatted string.
     */
    private function formatBytes($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }

    /**
     * Log a message with orchestrator context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [ORCHESTRATOR]';
        $full = $prefix . ' ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }

        if ($this->logger) {
            switch ($level) {
                case 'WARN':
                    $this->logger->warn($full);
                    break;
                case 'ERROR':
                    $this->logger->error($full);
                    break;
                default:
                    $this->logger->info($full);
            }
        }
    }
}
