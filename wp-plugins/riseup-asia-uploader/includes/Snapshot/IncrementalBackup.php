<?php
/**
 * Riseup Asia Uploader - Incremental Backup
 *
 * Tracks last_max_id per table from the master (full) snapshot and exports
 * only new/changed rows into sequenced incremental folders.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

require_once dirname(__FILE__) . '/SqliteSchemaConverter.php';

// Load trait files
require_once __DIR__ . '/Traits/IncrementalDeltaTrait.php';
require_once __DIR__ . '/Traits/IncrementalExportTrait.php';
require_once __DIR__ . '/Traits/IncrementalRegistrationTrait.php';
require_once __DIR__ . '/Traits/IncrementalDiscoveryTrait.php';
require_once __DIR__ . '/Traits/IncrementalCoreTrait.php';

/**
 * Incremental Backup class.
 */
class RiseupIncrementalBackup {

    use IncrementalDeltaTrait;
    use IncrementalExportTrait;
    use IncrementalRegistrationTrait;
    use IncrementalDiscoveryTrait;
    use IncrementalCoreTrait;

    private RiseupFileLogger $logger;
    private RiseupDatabase $db;
    private RiseupRootDb $rootDb;
    private \wpdb $wpdb;
    private int $batchSize;
    private static ?RiseupIncrementalBackup $instance = null;

    /** Get singleton instance. */
    public static function getInstance(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null, ?RiseupRootDb $rootDb = null): ?self {
        if (self::$instance === null && $logger && $db && $rootDb) {
            self::$instance = new self($logger, $db, $rootDb);
        }
        return self::$instance;
    }

    /** Constructor. */
    private function __construct(RiseupFileLogger $logger, RiseupDatabase $db, RiseupRootDb $rootDb) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->rootDb = $rootDb;
        $this->batchSize = SNAPSHOT_BATCH_SIZE;
    }

    /** Execute an incremental backup against a master snapshot. */
    public function execute(string $masterDir, array $options = array()): array {
        $startTime = microtime(true);
        $title = $options['title'] ?? ('Incremental ' . date('Y-m-d H:i'));

        $rootPath = $masterDir . '/a-root.db';
        if (RiseupBooleanHelpers::isFileMissing($rootPath)) {
            return array('success' => false, 'error' => 'Master snapshot a-root.db not found at: ' . $rootPath);
        }

        $this->log(LogLevelType::Info->value, 'Starting incremental backup', array('master_dir' => basename($masterDir), 'title' => $title));

        return $this->executeIncrementalPipeline($rootPath, $title, $masterDir, $startTime);
    }

    /** Run the incremental backup pipeline (prepare, export, register, finalize). */
    private function executeIncrementalPipeline(string $rootPath, string $title, string $masterDir, float $startTime): array {
        try {
            $prepared = $this->prepareIncrementalDir($rootPath);
            if (!$prepared['success']) {
                return $prepared;
            }

            $export = $this->exportChangedTables($prepared['master_tables'], $prepared['incremental_dir'], $prepared['rootPdo'], $prepared['sequence']);

            $this->registerIncrementalInRoot($prepared, $export);
            $prepared['rootPdo'] = null;

            return $this->finalizeIncremental($title, $masterDir, $prepared['folder_name'], $prepared['sequence'], $export, $prepared['incremental_dir'], $startTime);
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Incremental backup failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            return array('success' => false, 'error' => $e->getMessage(), 'phase' => 'incremental');
        }
    }

    /** Register the incremental export in the root database. */
    private function registerIncrementalInRoot(array $prepared, array $export): void {
        $this->rootDb->registerIncremental($prepared['rootPdo'], array(
            'sequence_num' => $prepared['sequence'], 'folder_name' => $prepared['folder_name'],
            'tables_changed' => $export['tables_changed'], 'total_new_rows' => $export['total_new_rows'],
            'relative_path' => 'incremental/' . $prepared['folder_name'] . '/',
        ));
    }

    /** Get the base snapshots directory. */
    private function getSnapshotsBaseDir(): string {
        return RiseupPathUtils::getSnapshotsDir();
    }

    /** Format bytes. */
    private function formatBytes(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }

    /** Log a message. */
    private function log(string $level, string $message, array $context = array()): void {
        $full = '[SNAPSHOT] [INCREMENTAL] ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }
        if (!$this->logger) return;
        switch ($level) {
            case LogLevelType::Warn->value:  $this->logger->warn($full); break;
            case LogLevelType::Error->value: $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
