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

// Load trait files
require_once __DIR__ . '/Traits/OrchestratorPluginTrait.php';
require_once __DIR__ . '/Traits/OrchestratorZipTrait.php';
require_once __DIR__ . '/Traits/OrchestratorRegistrationTrait.php';

/**
 * Snapshot Orchestrator class.
 */
class RiseupSnapshotOrchestrator {

    use OrchestratorPluginTrait;
    use OrchestratorZipTrait;
    use OrchestratorRegistrationTrait;

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

    public static function getInstance($logger = null, $db = null, $manager = null) {
        if (self::$instance === null && $logger && $db && $manager) {
            self::$instance = new self($logger, $db, $manager);
        }
        return self::$instance;
    }

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
     */
    public function executeFullBackup($options = array()) {
        $resolved = $this->resolveBackupOptions($options);
        $this->log('INFO', 'Starting full backup orchestration', $resolved);

        return ($options['async'] ?? true)
            ? $this->executeAsyncBackup($resolved)
            : $this->executeSyncBackup($resolved);
    }

    private function resolveBackupOptions(array $options): array {
        $settings = $this->manager->getSettings();
        $this->worker->setPoolSize($settings['worker_pool_size'] ?? SNAPSHOT_WORKER_POOL_DEFAULT);

        return array(
            'title' => $options['title'] ?? ('Full Backup ' . date('Y-m-d H:i')),
            'scope' => $options['scope'] ?? $settings['scope'] ?? SNAPSHOT_SCOPE_WORDPRESS,
            'include_plugins' => $options['include_plugins'] ?? $settings['include_plugins'] ?? true,
            'plugin_selection' => $options['plugin_selection'] ?? $settings['plugin_selection'] ?? 'all',
            'compression' => $options['compression'] ?? $settings['compression'] ?? true,
            'settings' => $settings,
        );
    }

    private function executeAsyncBackup($resolved) {
        try {
            $worker_result = $this->runWorkerExport($resolved, true);
            if (!$worker_result['success']) {
                return $this->buildPhaseError('table_export', $worker_result);
            }

            $this->log('INFO', 'Async backup job created', array(
                'job_id' => $worker_result['job_id'] ?? null, 'total_tables' => $worker_result['total_tables'] ?? null,
                'pool_size' => $worker_result['pool_size'] ?? null, 'directory' => $worker_result['directory'] ?? null,
            ));

            $snapshot_id = $this->registerSnapshot($resolved['title'], $resolved['scope'], $worker_result, array('count' => 0, 'total_size' => 0), $worker_result['path']);

            return array(
                'success' => true, 'async' => true, 'job_id' => $worker_result['job_id'] ?? null,
                'snapshot_id' => $snapshot_id, 'directory' => $worker_result['directory'] ?? null,
                'path' => $worker_result['path'], 'total_tables' => $worker_result['total_tables'] ?? null,
                'pool_size' => $worker_result['pool_size'] ?? null, 'status' => $worker_result['status'] ?? null,
            );
        } catch (Exception $e) {
            return $this->buildExceptionResult($e, 'async_orchestration');
        }
    }

    private function executeSyncBackup($resolved) {
        $start_time = microtime(true);
        try {
            $worker_result = $this->runWorkerExport($resolved, false);
            if (!$worker_result['success']) {
                return $this->buildPhaseError('table_export', $worker_result);
            }

            $snapshot_dir = $worker_result['path'];
            $plugin_stats = $resolved['include_plugins'] ? $this->snapshotPlugins($snapshot_dir, $resolved['plugin_selection']) : array('count' => 0, 'total_size' => 0);
            $snapshot_id = $this->registerSnapshot($resolved['title'], $resolved['scope'], $worker_result, $plugin_stats, $snapshot_dir);
            $zip_result = $resolved['compression'] ? $this->executeZipPhase($snapshot_dir, $resolved) : array('path' => null, 'size' => 0);

            $duration = microtime(true) - $start_time;
            return array(
                'success' => true, 'snapshot_id' => $snapshot_id, 'directory' => $worker_result['directory'],
                'path' => $worker_result['path'], 'tables' => $worker_result['tables'],
                'total_rows' => $worker_result['total_rows'], 'plugins' => $plugin_stats['count'],
                'zip_path' => $zip_result['path'], 'zip_size' => $zip_result['size'],
                'duration' => $duration, 'errors' => $worker_result['errors'] ?? array(),
            );
        } catch (Exception $e) {
            return $this->buildExceptionResult($e, 'sync_orchestration');
        }
    }

    private function runWorkerExport(array $resolved, bool $async): array {
        $config = array('title' => $resolved['title'], 'scope' => $resolved['scope'], 'type' => 'full', 'settings' => $resolved['settings']);
        return $async ? $this->worker->execute($config) : $this->worker->executeSynchronous($config);
    }

    private function executeZipPhase(string $snapshotDir, array $resolved): array {
        $zip_result = $this->createZipExport($snapshotDir, $resolved['title']);
        if ($zip_result['success']) {
            return array('path' => $zip_result['path'], 'size' => $zip_result['size']);
        }
        $this->log('WARN', 'ZIP export failed (non-fatal)', array('error' => $zip_result['error']));
        return array('path' => null, 'size' => 0);
    }

    /**
     * Execute an incremental backup against the latest full snapshot.
     */
    public function executeIncrementalBackup($options = array()) {
        $this->log('INFO', 'Starting incremental backup orchestration', $options);
        try {
            $incremental = RiseupIncrementalBackup::getInstance($this->logger, $this->db, $this->rootDb);
            $master_dir = $this->resolveMasterDir($options, $incremental);

            if (!$master_dir) {
                return array('success' => false, 'error' => 'No full snapshot found. A full backup is required before creating an incremental.', 'phase' => 'incremental_lookup');
            }

            $result = $incremental->execute($master_dir, $options);
            $this->log('INFO', 'Incremental backup orchestration ' . ($result['success'] ? 'complete' : 'failed'), array(
                'master' => basename($master_dir), 'tables_changed' => $result['tables_changed'] ?? 0, 'total_new_rows' => $result['total_new_rows'] ?? 0,
            ));
            return $result;
        } catch (Exception $e) {
            return $this->buildExceptionResult($e, 'incremental_orchestration');
        }
    }

    private function resolveMasterDir(array $options, $incremental): ?string {
        if (!empty($options['master_snapshot_id'])) {
            $pdo = $this->db->get_pdo();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT filepath FROM " . TABLE_SNAPSHOTS . " WHERE id = ?");
                $stmt->execute(array($options['master_snapshot_id']));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && is_dir($row['filepath'])) {
                    return $row['filepath'];
                }
            }
        }
        return $incremental->findLatestMasterSnapshot();
    }

    private function buildPhaseError(string $phase, array $result): array {
        return array('success' => false, 'error' => 'Table export failed: ' . ($result['error'] ?? 'Unknown error'), 'phase' => $phase);
    }

    private function buildExceptionResult(Exception $e, string $phase): array {
        $this->log('ERROR', ucfirst(str_replace('_', ' ', $phase)) . ' failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
        return array('success' => false, 'error' => $e->getMessage(), 'phase' => $phase);
    }

    /** Get directory size in bytes. */
    private function getDirectorySize(string $dir): int {
        $size = 0;
        if (RiseupBooleanHelpers::is_dir_missing($dir)) return 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) $size += $file->getSize();
        }
        return $size;
    }

    private function formatBytes($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }

    private function log($level, $message, $context = array()) {
        $full = '[SNAPSHOT] [ORCHESTRATOR] ' . $message;
        if (!empty($context)) $full .= ' ' . json_encode($context);
        if (!$this->logger) return;
        switch ($level) {
            case 'WARN':  $this->logger->warn($full); break;
            case 'ERROR': $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
