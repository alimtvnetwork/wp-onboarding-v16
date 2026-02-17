<?php
/**
 * Riseup Asia Uploader - Incremental Backup
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Snapshot
 * @since   1.14.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use wpdb;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Snapshot\Traits\IncrementalDeltaTrait;
use RiseupAsia\Snapshot\Traits\IncrementalExportTrait;
use RiseupAsia\Snapshot\Traits\IncrementalRegistrationTrait;
use RiseupAsia\Snapshot\Traits\IncrementalDiscoveryTrait;
use RiseupAsia\Snapshot\Traits\IncrementalCoreTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Database\RootDb;
use RiseupAsia\Logging\FileLogger;

class IncrementalBackup {

    use IncrementalDeltaTrait;
    use IncrementalExportTrait;
    use IncrementalRegistrationTrait;
    use IncrementalDiscoveryTrait;
    use IncrementalCoreTrait;

    private FileLogger $logger;
    private Database $db;
    private RootDb $rootDb;
    private wpdb $wpdb;
    private int $batchSize;
    private static ?IncrementalBackup $instance = null;

    public static function getInstance(
        ?FileLogger $logger = null,
        ?Database $db = null,
        ?RootDb $rootDb = null,
    ): ?self {
        $isReadyToInit = self::$instance === null && $logger && $db && $rootDb;
        if ($isReadyToInit) {
            self::$instance = new self($logger, $db, $rootDb);
        }

        return self::$instance;
    }

    private function __construct(
        FileLogger $logger,
        Database $db,
        RootDb $rootDb,
    ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->rootDb = $rootDb;
        $this->batchSize = SnapshotConfigType::BatchSize->value;
    }

    public function execute(string $masterDir, array $options = array()): array {
        $startTime = microtime(true);
        $title = $options['title'] ?? ('Incremental ' . date('Y-m-d H:i'));

        $rootPath = $masterDir . '/a-root.db';
        if (PathHelper::isFileMissing($rootPath)) {
            return array('success' => false, 'error' => 'Master snapshot a-root.db not found at: ' . $rootPath);
        }

        $this->log(LogLevelType::Info->value, 'Starting incremental backup', array('master_dir' => basename($masterDir), 'title' => $title));

        return $this->executeIncrementalPipeline($rootPath, $title, $masterDir, $startTime);
    }

    private function executeIncrementalPipeline(
        string $rootPath,
        string $title,
        string $masterDir,
        float $startTime,
    ): array {
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

    private function registerIncrementalInRoot(array $prepared, array $export): void {
        $this->rootDb->registerIncremental($prepared['rootPdo'], array(
            'sequence_num' => $prepared['sequence'], 'folder_name' => $prepared['folder_name'],
            'tables_changed' => $export['tables_changed'], 'total_new_rows' => $export['total_new_rows'],
            'relative_path' => 'incremental/' . $prepared['folder_name'] . '/',
        ));
    }

    private function getSnapshotsBaseDir(): string {
        return PathHelper::getSnapshotsDir();
    }

    private function formatBytes(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';

        return round($bytes / 1073741824, 1) . ' GB';
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
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
