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
     * @param array $options Options: title, scope, include_plugins, plugin_selection, compression, async.
     * @return array Result with success, path, zip_path, stats.
     */
    public function executeFullBackup($options = array()) {
        $resolved = $this->resolveBackupOptions($options);

        $this->log('INFO', 'Starting full backup orchestration', $resolved);

        $async = $options['async'] ?? true;

        if ($async) {
            return $this->executeAsyncBackup($resolved);
        }

        return $this->executeSyncBackup($resolved);
    }

    /**
     * Resolve and merge backup options with settings defaults.
     *
     * @param array $options User-provided options.
     * @return array Resolved options.
     */
    private function resolveBackupOptions(array $options): array {
        $settings = $this->manager->getSettings();
        $pool_size = $settings['worker_pool_size'] ?? SNAPSHOT_WORKER_POOL_DEFAULT;
        $this->worker->setPoolSize($pool_size);

        return array(
            'title'            => $options['title'] ?? ('Full Backup ' . date('Y-m-d H:i')),
            'scope'            => $options['scope'] ?? $settings['scope'] ?? SNAPSHOT_SCOPE_WORDPRESS,
            'include_plugins'  => $options['include_plugins'] ?? $settings['include_plugins'] ?? true,
            'plugin_selection' => $options['plugin_selection'] ?? $settings['plugin_selection'] ?? 'all',
            'compression'      => $options['compression'] ?? $settings['compression'] ?? true,
            'settings'         => $settings,
        );
    }

    /**
     * Execute an asynchronous (cron-based) backup.
     *
     * @param array $resolved Resolved options from executeFullBackup.
     * @return array Result with job_id, snapshot_id, and status.
     */
    private function executeAsyncBackup($resolved) {
        try {
            $worker_result = $this->runWorkerExport($resolved, true);

            if (!$worker_result['success']) {
                return $this->buildPhaseError('table_export', $worker_result);
            }

            $this->logAsyncJobCreated($worker_result);

            $snapshot_id = $this->registerSnapshot(
                $resolved['title'], $resolved['scope'], $worker_result,
                array('count' => 0, 'total_size' => 0), $worker_result['path']
            );

            return $this->buildAsyncResult($worker_result, $snapshot_id);
        } catch (Exception $e) {
            return $this->buildExceptionResult($e, 'async_orchestration');
        }
    }

    /**
     * Run the worker export phase.
     *
     * @param array $resolved Resolved options.
     * @param bool  $async    Whether to use async mode.
     * @return array Worker result.
     */
    private function runWorkerExport(array $resolved, bool $async): array {
        $config = array(
            'title'    => $resolved['title'],
            'scope'    => $resolved['scope'],
            'type'     => 'full',
            'settings' => $resolved['settings'],
        );

        return $async
            ? $this->worker->execute($config)
            : $this->worker->executeSynchronous($config);
    }

    /**
     * Log async job creation details.
     *
     * @param array $result Worker result.
     */
    private function logAsyncJobCreated(array $result): void {
        $this->log('INFO', 'Async backup job created', array(
            'job_id'       => $result['job_id'] ?? null,
            'total_tables' => $result['total_tables'] ?? null,
            'pool_size'    => $result['pool_size'] ?? null,
            'directory'    => $result['directory'] ?? null,
        ));
    }

    /**
     * Build the async backup result array.
     *
     * @param array $workerResult Worker result.
     * @param int   $snapshotId   Snapshot ID.
     * @return array Async result.
     */
    private function buildAsyncResult(array $workerResult, $snapshotId): array {
        return array(
            'success'      => true,
            'async'        => true,
            'job_id'       => $workerResult['job_id'] ?? null,
            'snapshot_id'  => $snapshotId,
            'directory'    => $workerResult['directory'] ?? null,
            'path'         => $workerResult['path'],
            'total_tables' => $workerResult['total_tables'] ?? null,
            'pool_size'    => $workerResult['pool_size'] ?? null,
            'status'       => $workerResult['status'] ?? null,
        );
    }

    /**
     * Execute a synchronous (blocking) backup.
     *
     * @param array $resolved Resolved options from executeFullBackup.
     * @return array Result with snapshot_id, tables, total_rows, zip info.
     */
    private function executeSyncBackup($resolved) {
        $start_time = microtime(true);

        try {
            $worker_result = $this->runWorkerExport($resolved, false);

            if (!$worker_result['success']) {
                return $this->buildPhaseError('table_export', $worker_result);
            }

            $snapshot_dir = $worker_result['path'];
            $this->logTableExportComplete($worker_result);

            $plugin_stats = $this->executePluginSnapshots($snapshot_dir, $resolved);
            $snapshot_id = $this->registerSnapshot(
                $resolved['title'], $resolved['scope'], $worker_result,
                $plugin_stats, $snapshot_dir
            );

            $zip_result = $this->executeZipExportPhase($snapshot_dir, $resolved);
            $duration = microtime(true) - $start_time;

            $this->logSyncComplete($snapshot_id, $worker_result, $plugin_stats, $zip_result, $duration);

            return $this->buildSyncResult($worker_result, $plugin_stats, $zip_result, $snapshot_id, $duration);
        } catch (Exception $e) {
            return $this->buildExceptionResult($e, 'sync_orchestration');
        }
    }

    /**
     * Execute the plugin snapshots phase.
     *
     * @param string $snapshotDir Snapshot directory.
     * @param array  $resolved    Resolved options.
     * @return array Plugin stats.
     */
    private function executePluginSnapshots(string $snapshotDir, array $resolved): array {
        $plugin_stats = array('count' => 0, 'total_size' => 0);

        if (!$resolved['include_plugins']) {
            return $plugin_stats;
        }

        $plugin_stats = $this->snapshotPlugins($snapshotDir, $resolved['plugin_selection']);
        $this->log('INFO', 'Plugin snapshots complete', array(
            'count'      => $plugin_stats['count'],
            'total_size' => $this->formatBytes($plugin_stats['total_size']),
        ));

        return $plugin_stats;
    }

    /**
     * Execute the optional ZIP export phase.
     *
     * @param string $snapshotDir Snapshot directory.
     * @param array  $resolved    Resolved options.
     * @return array ZIP result with path and size.
     */
    private function executeZipExportPhase(string $snapshotDir, array $resolved): array {
        if (!$resolved['compression']) {
            return array('path' => null, 'size' => 0);
        }

        $zip_result = $this->createZipExport($snapshotDir, $resolved['title']);

        if ($zip_result['success']) {
            $this->log('INFO', 'ZIP export complete', array(
                'path' => basename($zip_result['path']),
                'size' => $this->formatBytes($zip_result['size']),
            ));
            return array('path' => $zip_result['path'], 'size' => $zip_result['size']);
        }

        $this->log('WARN', 'ZIP export failed (non-fatal)', array('error' => $zip_result['error']));
        return array('path' => null, 'size' => 0);
    }

    /**
     * Build the sync backup result array.
     *
     * @param array  $workerResult Worker result.
     * @param array  $pluginStats  Plugin stats.
     * @param array  $zipResult    ZIP result.
     * @param int    $snapshotId   Snapshot ID.
     * @param float  $duration     Duration in seconds.
     * @return array Sync result.
     */
    private function buildSyncResult(array $workerResult, array $pluginStats, array $zipResult, $snapshotId, float $duration): array {
        return array(
            'success'      => true,
            'snapshot_id'  => $snapshotId,
            'directory'    => $workerResult['directory'],
            'path'         => $workerResult['path'],
            'tables'       => $workerResult['tables'],
            'total_rows'   => $workerResult['total_rows'],
            'plugins'      => $pluginStats['count'],
            'zip_path'     => $zipResult['path'],
            'zip_size'     => $zipResult['size'],
            'duration'     => $duration,
            'errors'       => $workerResult['errors'] ?? array(),
        );
    }

    /**
     * Log table export completion.
     *
     * @param array $result Worker result.
     */
    private function logTableExportComplete(array $result): void {
        $this->log('INFO', 'Table export complete', array(
            'tables'     => $result['tables'],
            'total_rows' => $result['total_rows'],
            'directory'  => $result['directory'],
        ));
    }

    /**
     * Log sync backup completion.
     *
     * @param int   $snapshotId   Snapshot ID.
     * @param array $workerResult Worker result.
     * @param array $pluginStats  Plugin stats.
     * @param array $zipResult    ZIP result.
     * @param float $duration     Duration.
     */
    private function logSyncComplete($snapshotId, array $workerResult, array $pluginStats, array $zipResult, float $duration): void {
        $this->log('INFO', 'Sync backup orchestration complete', array(
            'snapshot_id'   => $snapshotId,
            'tables'        => $workerResult['tables'],
            'total_rows'    => $workerResult['total_rows'],
            'plugins'       => $pluginStats['count'],
            'zip'           => $zipResult['path'] ? basename($zipResult['path']) : 'disabled',
            'duration'      => round($duration, 2) . 's',
            'worker_errors' => count($workerResult['errors'] ?? array()),
        ));
    }

    /**
     * Build a phase error result.
     *
     * @param string $phase  Phase name.
     * @param array  $result Worker result with error.
     * @return array Error result.
     */
    private function buildPhaseError(string $phase, array $result): array {
        return array(
            'success' => false,
            'error'   => 'Table export failed: ' . ($result['error'] ?? 'Unknown error'),
            'phase'   => $phase,
        );
    }

    /**
     * Build an exception error result.
     *
     * @param Exception $e     Exception.
     * @param string    $phase Phase name.
     * @return array Error result.
     */
    private function buildExceptionResult(Exception $e, string $phase): array {
        $this->log('ERROR', ucfirst(str_replace('_', ' ', $phase)) . ' failed', array(
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ));

        return array(
            'success' => false,
            'error'   => $e->getMessage(),
            'phase'   => $phase,
        );
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
        if (!RiseupPathUtils::ensure_dir($plugins_dir, true)) {
            $this->log('ERROR', 'Failed to create plugins directory');
            return array('count' => 0, 'total_size' => 0, 'plugins' => array());
        }

        $plugins_to_snapshot = $this->collectPluginsToSnapshot($selection);
        $rootPdo = $this->openRootDbForPlugins($snapshot_dir);

        $count = 0;
        $total_size = 0;
        $plugin_list = array();

        foreach ($plugins_to_snapshot as $plugin_file => $info) {
            $result = $this->archiveSinglePlugin($info, $plugins_dir, $rootPdo);
            if ($result === null) {
                continue;
            }
            if ($result['success']) {
                $total_size += $result['size'];
                $count++;
                $plugin_list[] = $result['entry'];
            }
        }

        $rootPdo = null;

        return array(
            'count'      => $count,
            'total_size' => $total_size,
            'plugins'    => $plugin_list,
        );
    }

    /**
     * Open a-root.db for plugin registration.
     *
     * @param string $snapshotDir Snapshot directory.
     * @return PDO|null PDO connection or null.
     */
    private function openRootDbForPlugins(string $snapshotDir): ?PDO {
        $root_path = $snapshotDir . '/a-root.db';
        if (RiseupBooleanHelpers::is_file_missing($root_path)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $root_path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (Exception $e) {
            $this->log('WARN', 'Could not open a-root.db for plugin registration', array('error' => $e->getMessage()));
            return null;
        }
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
            $this->log('WARN', 'Failed to archive plugin: ' . $slug, array('error' => $zip_result['error']));
            return array('success' => false);
        }

        $entry = $this->buildPluginEntry($info, $zip_filename, $zip_path);

        if ($rootPdo) {
            $this->registerPluginInRootDb($rootPdo, $info, $zip_filename, $zip_path);
        }

        $this->log('INFO', sprintf('Plugin archived: %s (%s)',
            $info['name'], $this->formatBytes($entry['size'])
        ));

        return array('success' => true, 'size' => $entry['size'], 'entry' => $entry);
    }

    /**
     * Build a plugin entry array.
     *
     * @param array  $info        Plugin info.
     * @param string $zipFilename ZIP filename.
     * @param string $zipPath     Full ZIP path.
     * @return array Plugin entry.
     */
    private function buildPluginEntry(array $info, string $zipFilename, string $zipPath): array {
        return array(
            'slug'    => $info['slug'],
            'name'    => $info['name'],
            'version' => $info['version'],
            'zip'     => $zipFilename,
            'size'    => filesize($zipPath),
        );
    }

    /**
     * Register a plugin snapshot in a-root.db.
     *
     * @param PDO    $rootPdo     Root DB connection.
     * @param array  $info        Plugin info.
     * @param string $zipFilename ZIP filename.
     * @param string $zipPath     Full ZIP path.
     */
    private function registerPluginInRootDb(PDO $rootPdo, array $info, string $zipFilename, string $zipPath): void {
        $this->rootDb->registerPluginSnapshot($rootPdo, array(
            'plugin_slug'     => $info['slug'],
            'plugin_name'     => $info['name'],
            'plugin_version'  => $info['version'],
            'zip_file'        => 'plugins/' . $zipFilename,
            'file_size_bytes' => filesize($zipPath),
            'checksum_md5'    => md5_file($zipPath),
        ));
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

            $source_dir = rtrim($source_dir, '/\\\\');
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

            $zip->close();

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
            $zip_path = dirname($snapshot_dir) . '/' . $zip_filename;

            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return array('success' => false, 'error' => 'Failed to create ZIP');
            }

            $file_count = $this->addDirectoryToZip($zip, $snapshot_dir);
            $zip->close();

            return $this->validateZipExport($zip_path, $zip_filename, $file_count);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Recursively add all files from a directory to a ZIP archive.
     *
     * @param ZipArchive $zip ZIP archive instance.
     * @param string     $dir Directory to add.
     * @return int Number of files added.
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dir): int {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $count = 0;
        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($dir) + 1));
            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($item->getPathname(), $relative);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Validate a ZIP export file and return result metadata.
     *
     * @param string $path     ZIP file path.
     * @param string $filename ZIP filename.
     * @param int    $files    Number of files in archive.
     * @return array Result array.
     */
    private function validateZipExport(string $path, string $filename, int $files): array {
        $size = filesize($path);
        if ($size === 0) {
            @unlink($path);
            return array('success' => false, 'error' => 'ZIP export is empty');
        }

        $this->log('INFO', 'ZIP export created', array(
            'filename' => $filename, 'files' => $files, 'size' => $this->formatBytes($size),
        ));

        return array('success' => true, 'path' => $path, 'filename' => $filename, 'size' => $size, 'files' => $files);
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
            $sequence = $this->getNextSnapshotSequence($pdo);
            $tables_json = $this->buildSnapshotTablesJson($worker_result, $plugin_stats);
            $dir_size = $this->getDirectorySize($snapshot_dir);

            return $this->insertSnapshotRecord($pdo, $sequence, $snapshot_dir, $scope, $tables_json, $worker_result, $dir_size);
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to register snapshot', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Get the next snapshot sequence number.
     *
     * @param PDO $pdo Database connection.
     * @return int Next sequence.
     */
    private function getNextSnapshotSequence(PDO $pdo): int {
        $row = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . TABLE_SNAPSHOTS)
            ->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;
    }

    /**
     * Build the tables JSON metadata for a snapshot.
     *
     * @param array $workerResult Worker result.
     * @param array $pluginStats  Plugin stats.
     * @return string JSON string.
     */
    private function buildSnapshotTablesJson(array $workerResult, array $pluginStats): string {
        return json_encode(array(
            'exported'       => $workerResult['tables'] ?? 0,
            'total_rows'     => $workerResult['total_rows'] ?? 0,
            'errors'         => $workerResult['errors'] ?? array(),
            'plugins'        => $pluginStats['count'] ?? 0,
            'plugin_details' => $pluginStats['plugins'] ?? array(),
        ));
    }

    /**
     * Insert a snapshot record into the database.
     *
     * @param PDO    $pdo          Database connection.
     * @param int    $sequence     Sequence number.
     * @param string $snapshotDir  Snapshot directory.
     * @param string $scope        Table scope.
     * @param string $tablesJson   Tables JSON metadata.
     * @param array  $workerResult Worker result.
     * @param int    $dirSize      Directory size in bytes.
     * @return int Snapshot ID.
     */
    private function insertSnapshotRecord(PDO $pdo, int $sequence, string $snapshotDir, string $scope, string $tablesJson, array $workerResult, int $dirSize): int {
        $now = gmdate('c');
        $stmt = $pdo->prepare("INSERT INTO " . TABLE_SNAPSHOTS . "
            (sequence, filename, filepath, provider, scope, tables_json, total_rows,
             file_size, trigger_source, status, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $sequence, basename($snapshotDir), $snapshotDir,
            SNAPSHOT_PROVIDER_NATIVE, $scope, $tablesJson,
            $workerResult['total_rows'] ?? 0, $dirSize,
            SNAPSHOT_TRIGGER_API, SNAPSHOT_STATUS_COMPLETE, $now, $now,
        ));

        return (int)$pdo->lastInsertId();
    }

    /**
     * Execute an incremental backup against the latest full snapshot.
     *
     * @param array $options Options: title, master_snapshot_id (optional).
     * @return array Result with success, path, tables_changed, total_new_rows, etc.
     */
    public function executeIncrementalBackup($options = array()) {
        $this->log('INFO', 'Starting incremental backup orchestration', $options);

        try {
            $incremental = RiseupIncrementalBackup::getInstance($this->logger, $this->db, $this->rootDb);
            $master_dir = $this->resolveMasterDir($options, $incremental);

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
            return $this->buildExceptionResult($e, 'incremental_orchestration');
        }
    }

    /**
     * Resolve the master snapshot directory for incremental backups.
     *
     * @param array  $options     Backup options (may include master_snapshot_id).
     * @param object $incremental Incremental backup instance.
     * @return string|null Master directory path or null.
     */
    private function resolveMasterDir(array $options, $incremental): ?string {
        if (!empty($options['master_snapshot_id'])) {
            $pdo = $this->db->get_pdo();
            if ($pdo) {
                $stmt = $pdo->
