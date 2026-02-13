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
     * Execute a full end-to-end backup (dispatcher).
     *
     * Delegates to executeAsyncBackup or executeSyncBackup based on the
     * 'async' option (defaults to true).
     *
     * @param array $options Options: title, scope, include_plugins, plugin_selection, compression, async.
     * @return array Result with success, path, zip_path, stats.
     */
    public function executeFullBackup($options = array()) {
        // Read settings (merge with overrides)
        $settings = $this->manager->getSettings();
        $title = $options['title'] ?? ('Full Backup ' . date('Y-m-d H:i'));
        $scope = $options['scope'] ?? $settings['scope'] ?? SNAPSHOT_SCOPE_WORDPRESS;
        $include_plugins = $options['include_plugins'] ?? $settings['include_plugins'] ?? true;
        $plugin_selection = $options['plugin_selection'] ?? $settings['plugin_selection'] ?? 'all';
        $compression = $options['compression'] ?? $settings['compression'] ?? true;

        // Apply worker pool size from settings
        $pool_size = $settings['worker_pool_size'] ?? SNAPSHOT_WORKER_POOL_DEFAULT;
        $this->worker->setPoolSize($pool_size);

        $resolved = array(
            'title'            => $title,
            'scope'            => $scope,
            'include_plugins'  => $include_plugins,
            'plugin_selection' => $plugin_selection,
            'compression'      => $compression,
            'settings'         => $settings,
        );

        $this->log('INFO', 'Starting full backup orchestration', $resolved);

        $async = $options['async'] ?? true;

        if ($async) {
            return $this->executeAsyncBackup($resolved);
        }

        return $this->executeSyncBackup($resolved);
    }

    /**
     * Execute an asynchronous (cron-based) backup.
     *
     * Creates a job and schedules the first cron batch. The caller polls
     * the progress endpoint until the job completes.
     *
     * @param array $resolved Resolved options from executeFullBackup.
     * @return array Result with job_id, snapshot_id, and status.
     */
    private function executeAsyncBackup($resolved) {
        try {
            $worker_result = $this->worker->execute(array(
                'title'    => $resolved['title'],
                'scope'    => $resolved['scope'],
                'type'     => 'full',
                'settings' => $resolved['settings'],
            ));

            if (!$worker_result['success']) {
                return array(
                    'success' => false,
                    'error'   => 'Table export failed: ' . ($worker_result['error'] ?? 'Unknown error'),
                    'phase'   => 'table_export',
                );
            }

            $snapshot_dir = $worker_result['path'];

            $this->log('INFO', 'Async backup job created', array(
                'job_id'       => $worker_result['job_id'] ?? null,
                'total_tables' => $worker_result['total_tables'] ?? null,
                'pool_size'    => $worker_result['pool_size'] ?? null,
                'directory'    => $worker_result['directory'] ?? null,
            ));

            // Register snapshot record in pending state
            $snapshot_id = $this->registerSnapshot(
                $resolved['title'], $resolved['scope'], $worker_result,
                array('count' => 0, 'total_size' => 0), $snapshot_dir
            );

            return array(
                'success'      => true,
                'async'        => true,
                'job_id'       => $worker_result['job_id'] ?? null,
                'snapshot_id'  => $snapshot_id,
                'directory'    => $worker_result['directory'] ?? null,
                'path'         => $snapshot_dir,
                'total_tables' => $worker_result['total_tables'] ?? null,
                'pool_size'    => $worker_result['pool_size'] ?? null,
                'status'       => $worker_result['status'] ?? null,
            );
        } catch (Exception $e) {
            $this->log('ERROR', 'Async backup failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));

            return array(
                'success' => false,
                'error'   => $e->getMessage(),
                'phase'   => 'async_orchestration',
            );
        }
    }

    /**
     * Execute a synchronous (blocking) backup.
     *
     * Blocks until all tables are exported, optionally snapshots plugins,
     * and creates a ZIP archive.
     *
     * @param array $resolved Resolved options from executeFullBackup.
     * @return array Result with snapshot_id, tables, total_rows, zip info.
     */
    private function executeSyncBackup($resolved) {
        $start_time = microtime(true);

        try {
            $worker_result = $this->worker->executeSynchronous(array(
                'title'    => $resolved['title'],
                'scope'    => $resolved['scope'],
                'type'     => 'full',
                'settings' => $resolved['settings'],
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

            // Plugin snapshots (optional)
            $plugin_stats = array('count' => 0, 'total_size' => 0);
            if ($resolved['include_plugins']) {
                $plugin_stats = $this->snapshotPlugins($snapshot_dir, $resolved['plugin_selection']);
                $this->log('INFO', 'Plugin snapshots complete', array(
                    'count'      => $plugin_stats['count'],
                    'total_size' => $this->formatBytes($plugin_stats['total_size']),
                ));
            }

            // Register in snapshots table for tracking
            $snapshot_id = $this->registerSnapshot(
                $resolved['title'], $resolved['scope'], $worker_result,
                $plugin_stats, $snapshot_dir
            );

            // ZIP export (optional)
            $zip_path = null;
            $zip_size = 0;
            if ($resolved['compression']) {
                $zip_result = $this->createZipExport($snapshot_dir, $resolved['title']);
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

            $this->log('INFO', 'Sync backup orchestration complete', array(
                'snapshot_id'   => $snapshot_id,
                'tables'        => $worker_result['tables'],
                'total_rows'    => $worker_result['total_rows'],
                'plugins'       => $plugin_stats['count'],
                'zip'           => $zip_path ? basename($zip_path) : 'disabled',
                'duration'      => round($duration, 2) . 's',
                'worker_errors' => count($worker_result['errors'] ?? array()),
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
            $this->log('ERROR', 'Sync backup failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));

            return array(
                'success' => false,
                'error'   => $e->getMessage(),
                'phase'   => 'sync_orchestration',
            );
        }
    }

    /**
     * Snapshot installed WordPress plugins as ZIP files.
     *
     * Thin orchestrator: collects eligible plugins, opens a-root.db for
     * registration, then archives each plugin individually.
     *
     * @param string $snapshot_dir Snapshot directory.
     * @param string $selection    'all' or 'selective' (only active).
     * @return array Stats: count, total_size, plugins[].
     */
    private function snapshotPlugins($snapshot_dir, $selection = 'all') {
        $plugins_dir = $snapshot_dir . '/plugins';
        if (!RiseupPathUtils::ensure_dir($plugins_dir, true)) {
            $this->log('ERROR', 'Failed to create plugins directory');

            return array('count' => 0, 'total_size' => 0, 'plugins' => array());
        }

        $plugins_to_snapshot = $this->collectPluginsToSnapshot($selection);

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
            $result = $this->archiveSinglePlugin($info, $plugins_dir, $rootPdo);

            if ($result === null) {
                continue; // skipped (single-file plugin)
            }

            if ($result['success']) {
                $total_size += $result['size'];
                $count++;
                $plugin_list[] = $result['entry'];
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
     * Collect the list of plugins eligible for snapshotting.
     *
     * @param string $selection 'all' or 'selective' (only active).
     * @return array Associative array keyed by plugin_file with slug, name, version.
     */
    private function collectPluginsToSnapshot($selection) {
        if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugins_to_snapshot = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }

            // Skip self
            if ($slug === PLUGIN_SLUG) {
                continue;
            }

            $isEligible = ($selection === 'all' || in_array($plugin_file, $active_plugins));
            if (!$isEligible) {
                continue;
            }

            $plugins_to_snapshot[$plugin_file] = array(
                'slug'    => $slug,
                'name'    => $plugin_data['Name'] ?? $slug,
                'version' => $plugin_data['Version'] ?? '0.0.0',
            );
        }

        $this->log('INFO', 'Snapshotting plugins', array(
            'total'     => count($all_plugins),
            'selected'  => count($plugins_to_snapshot),
            'selection' => $selection,
        ));

        return $plugins_to_snapshot;
    }

    /**
     * Archive a single plugin as a ZIP and register it in a-root.db.
     *
     * @param array    $info        Plugin info with slug, name, version.
     * @param string   $plugins_dir Destination directory for ZIP files.
     * @param PDO|null $rootPdo     Open a-root.db connection (nullable).
     * @return array|null Result with success, size, entry — or null if skipped.
     */
    private function archiveSinglePlugin($info, $plugins_dir, $rootPdo) {
        $slug = $info['slug'];
        $plugin_path = WP_PLUGIN_DIR . '/' . $slug;

        if (RiseupBooleanHelpers::is_dir_missing($plugin_path)) {
            $this->log('INFO', 'Skipping single-file plugin: ' . $slug);

            return null;
        }

        $zip_filename = $slug . '.zip';
        $zip_path = $plugins_dir . '/' . $zip_filename;

        $zip_result = $this->createPluginZip($plugin_path, $zip_path, $slug);

        if (!$zip_result['success']) {
            $this->log('WARN', 'Failed to archive plugin: ' . $slug, array(
                'error' => $zip_result['error'],
            ));

            return array('success' => false);
        }

        $file_size = filesize($zip_path);
        $checksum = md5_file($zip_path);

        $entry = array(
            'slug'    => $slug,
            'name'    => $info['name'],
            'version' => $info['version'],
            'zip'     => $zip_filename,
            'size'    => $file_size,
        );

        // Register in a-root.db
        if ($rootPdo) {
            $this->rootDb->registerPluginSnapshot($rootPdo, array(
                'plugin_slug'     => $slug,
                'plugin_name'     => $info['name'],
                'plugin_version'  => $info['version'],
                'zip_file'        => 'plugins/' . $zip_filename,
                'file_size_bytes' => $file_size,
                'checksum_md5'    => $checksum,
            ));
        }

        $this->log('INFO', sprintf('Plugin archived: %s (%s)',
            $info['name'], $this->formatBytes($file_size)
        ));

        return array(
            'success' => true,
            'size'    => $file_size,
            'entry'   => $entry,
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
            $seq_result = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . TABLE_SNAPSHOTS);
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

            $stmt = $pdo->prepare("INSERT INTO " . TABLE_SNAPSHOTS . "
                (sequence, filename, filepath, provider, scope, tables_json, total_rows,
                 file_size, trigger_source, status, created_at, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Calculate total directory size
            $dir_size = $this->getDirectorySize($snapshot_dir);

            $stmt->execute(array(
                $sequence,
                $filename,
                $snapshot_dir,
                SNAPSHOT_PROVIDER_NATIVE,
                $scope,
                $tables_json,
                $worker_result['total_rows'] ?? 0,
                $dir_size,
                SNAPSHOT_TRIGGER_API,
                SNAPSHOT_STATUS_COMPLETE,
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
     * Execute an incremental backup against the latest full snapshot.
     *
     * Delegates to RiseupIncrementalBackup after locating the master directory.
     *
     * @param array $options Options: title, master_snapshot_id (optional).
     * @return array Result with success, path, tables_changed, total_new_rows, etc.
     */
    public function executeIncrementalBackup($options = array()) {
        $this->log('INFO', 'Starting incremental backup orchestration', $options);

        try {
            // Locate the master (full) snapshot directory
            $incremental = RiseupIncrementalBackup::getInstance($this->logger, $this->db, $this->rootDb);

            // If a specific master ID was provided, resolve its filepath
            $master_dir = null;
            if (!empty($options['master_snapshot_id'])) {
                $pdo = $this->db->get_pdo();
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT filepath FROM " . TABLE_SNAPSHOTS . " WHERE id = ? AND status = ?");
                    $stmt->execute(array($options['master_snapshot_id'], SNAPSHOT_STATUS_COMPLETE));
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && is_dir($row['filepath']) && file_exists($row['filepath'] . '/a-root.db')) {
                        $master_dir = $row['filepath'];
                    }
                }
            }

            // Fallback: find the latest full snapshot
            if (!$master_dir) {
                $master_dir = $incremental->findLatestMasterSnapshot();
            }

            if (!$master_dir) {
                return array(
                    'success' => false,
                    'error'   => 'No full snapshot found. A full backup is required before creating an incremental.',
                    'phase'   => 'incremental_lookup',
                );
            }

            $result = $incremental->execute($master_dir, $options);

            $this->log('INFO', 'Incremental backup orchestration ' . ($result['success'] ? 'complete' : 'failed'), array(
                'master'         => basename($master_dir),
                'tables_changed' => $result['tables_changed'] ?? 0,
                'total_new_rows' => $result['total_new_rows'] ?? 0,
            ));

            return $result;

        } catch (Exception $e) {
            $this->log('ERROR', 'Incremental backup orchestration failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
                'phase'   => 'incremental_orchestration',
            );
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
        if (RiseupBooleanHelpers::is_dir_missing($dir)) return 0;

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
