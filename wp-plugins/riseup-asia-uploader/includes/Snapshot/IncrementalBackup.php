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

    /** @var RiseupFileLogger */
    private $logger;
    /** @var RiseupDatabase */
    private $db;
    /** @var RiseupRootDb */
    private $rootDb;
    /** @var wpdb */
    private $wpdb;
    /** @var int */
    private $batchSize;
    /** @var RiseupIncrementalBackup|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance($logger = null, $db = null, $rootDb = null) {
        if (self::$instance === null && $logger && $db && $rootDb) {
            self::$instance = new self($logger, $db, $rootDb);
        }
        return self::$instance;
    }

    /** Constructor. */
    private function __construct($logger, $db, $rootDb) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
        $this->db = $db;
        $this->rootDb = $rootDb;
        $this->batchSize = SNAPSHOT_BATCH_SIZE;
    }

    /**
     * Execute an incremental backup against a master snapshot.
     *
     * @param string $master_dir Path to the master (full) snapshot directory.
     * @param array  $options    Options: title.
     * @return array Result.
     */
    public function execute($master_dir, $options = array()) {
        $start_time = microtime(true);
        $title = $options['title'] ?? ('Incremental ' . date('Y-m-d H:i'));

        $root_path = $master_dir . '/a-root.db';
        if (RiseupBooleanHelpers::is_file_missing($root_path)) {
            return array('success' => false, 'error' => 'Master snapshot a-root.db not found at: ' . $root_path);
        }

        $this->log('INFO', 'Starting incremental backup', array('master_dir' => basename($master_dir), 'title' => $title));

        try {
            $prepared = $this->prepareIncrementalDir($root_path);
            if (!$prepared['success']) {
                return $prepared;
            }

            $export = $this->exportChangedTables($prepared['master_tables'], $prepared['incremental_dir'], $prepared['rootPdo'], $prepared['sequence']);

            $this->rootDb->registerIncremental($prepared['rootPdo'], array(
                'sequence_num' => $prepared['sequence'], 'folder_name' => $prepared['folder_name'],
                'tables_changed' => $export['tables_changed'], 'total_new_rows' => $export['total_new_rows'],
                'relative_path' => 'incremental/' . $prepared['folder_name'] . '/',
            ));

            $prepared['rootPdo'] = null;

            return $this->finalizeIncremental($title, $master_dir, $prepared['folder_name'], $prepared['sequence'], $export, $prepared['incremental_dir'], $start_time);
        } catch (Exception $e) {
            $this->log('ERROR', 'Incremental backup failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
            return array('success' => false, 'error' => $e->getMessage(), 'phase' => 'incremental');
        }
    }

    /** Get the base snapshots directory. */
    private function getSnapshotsBaseDir() {
        return RiseupPathUtils::get_snapshots_dir();
    }

    /** Format bytes. */
    private function formatBytes($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }

    /** Log a message. */
    private function log($level, $message, $context = array()) {
        $full = '[SNAPSHOT] [INCREMENTAL] ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }
        if (!$this->logger) return;
        switch ($level) {
            case 'WARN':  $this->logger->warn($full); break;
            case 'ERROR': $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
