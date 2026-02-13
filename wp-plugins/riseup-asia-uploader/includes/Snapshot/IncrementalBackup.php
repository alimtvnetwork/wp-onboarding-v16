<?php
/**
 * Riseup Asia Uploader - Incremental Backup
 *
 * Tracks last_max_id per table from the master (full) snapshot and exports
 * only new/changed rows into sequenced incremental folders.
 *
 * @package RiseupAsiaUploader
 * @since   1.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/SqliteSchemaConverter.php';

/**
 * Incremental Backup class.
 *
 * Produces delta snapshots relative to a master (full) backup.
 * Each incremental gets its own sequenced folder inside the master's
 * `incremental/` subdirectory and is registered in `a-root.db`.
 *
 * Limitation: Row deletions in MySQL between incrementals are NOT captured.
 * Periodic full backups should reset the baseline.
 */
class RiseupIncrementalBackup {

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
     *
     * @param RiseupFileLogger|null $logger Logger.
     * @param RiseupDatabase|null   $db     Plugin database.
     * @param RiseupRootDb|null     $rootDb Root DB manager.
     * @return RiseupIncrementalBackup
     */
    public static function getInstance($logger = null, $db = null, $rootDb = null) {
        if (self::$instance === null && $logger && $db && $rootDb) {
            self::$instance = new self($logger, $db, $rootDb);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger.
     * @param RiseupDatabase   $db     Plugin database.
     * @param RiseupRootDb     $rootDb Root DB manager.
     */
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
     * @param string $master_dir  Path to the master (full) snapshot directory.
     * @param array  $options     Options: title.
     * @return array Result with success, path, tables_changed, total_new_rows, etc.
     */
    public function execute($master_dir, $options = array()) {
        $start_time = microtime(true);
        $title = $options['title'] ?? ('Incremental ' . date('Y-m-d H:i'));

        $root_path = $master_dir . '/a-root.db';
        if (RiseupBooleanHelpers::is_file_missing($root_path)) {
            return array(
                'success' => false,
                'error'   => 'Master snapshot a-root.db not found at: ' . $root_path,
            );
        }

        $this->log('INFO', 'Starting incremental backup', array(
            'master_dir' => basename($master_dir),
            'title'      => $title,
        ));

        try {
            $prepared = $this->prepareIncrementalDir($root_path);
            if (!$prepared['success']) {
                return $prepared;
            }

            $rootPdo = $prepared['rootPdo'];
            $master_tables = $prepared['master_tables'];
            $sequence = $prepared['sequence'];
            $incremental_dir = $prepared['incremental_dir'];
            $folder_name = $prepared['folder_name'];

            $export = $this->exportChangedTables($master_tables, $incremental_dir, $rootPdo, $sequence);

            $this->rootDb->registerIncremental($rootPdo, array(
                'sequence_num'   => $sequence,
                'folder_name'    => $folder_name,
                'tables_changed' => $export['tables_changed'],
                'total_new_rows' => $export['total_new_rows'],
                'relative_path'  => 'incremental/' . $folder_name . '/',
            ));

            $rootPdo = null;

            return $this->finalizeIncremental(
                $title, $master_dir, $folder_name, $sequence,
                $export, $incremental_dir, $start_time
            );

        } catch (Exception $e) {
            $this->log('ERROR', 'Incremental backup failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            return array('success' => false, 'error' => $e->getMessage(), 'phase' => 'incremental');
        }
    }

    /**
     * Prepare the incremental directory and load master inventory.
     *
     * @param string $rootPath Path to a-root.db.
     * @return array Prepared context or error result.
     */
    private function prepareIncrementalDir(string $rootPath): array {
        $rootPdo = new PDO('sqlite:' . $rootPath);
        $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $master_tables = $this->getMasterTableInventory($rootPdo);
        if (empty($master_tables)) {
            $rootPdo = null;
            return array('success' => false, 'error' => 'No tables found in master snapshot');
        }

        $sequence = $this->getNextSequence($rootPdo);
        $folder_name = sprintf('%02d_%s', $sequence, date('Y-m-d'));
        $master_dir = dirname($rootPath);
        $incremental_dir = $master_dir . '/incremental/' . $folder_name;

        if (!RiseupPathUtils::ensure_dir($incremental_dir, true)) {
            $rootPdo = null;
            return array('success' => false, 'error' => 'Failed to create incremental directory: ' . $folder_name);
        }

        $this->log('INFO', 'Incremental directory created', array(
            'sequence' => $sequence, 'folder_name' => $folder_name,
        ));

        return array(
            'success'          => true,
            'rootPdo'          => $rootPdo,
            'master_tables'    => $master_tables,
            'sequence'         => $sequence,
            'folder_name'      => $folder_name,
            'incremental_dir'  => $incremental_dir,
        );
    }

    /**
     * Export changed tables (delta rows) to incremental SQLite files.
     *
     * @param array $masterTables Master table inventory.
     * @param string $incDir      Incremental directory.
     * @param PDO   $rootPdo      Root DB connection.
     * @param int   $sequence     Current sequence number.
     * @return array Export results with tables_changed, total_new_rows, errors, exported_tables.
     */
    private function exportChangedTables(array $masterTables, string $incDir, PDO $rootPdo, int $sequence): array {
        $tables_changed = 0;
        $total_new_rows = 0;
        $errors = array();
        $exported_tables = array();

        foreach ($masterTables as $table_name => $info) {
            $result = $this->exportTableDelta($table_name, $info, $incDir, $rootPdo, $sequence);

            if ($result === null) {
                continue; // skipped or no changes
            }

            if ($result['success']) {
                $tables_changed++;
                $total_new_rows += $result['rows'];
                $exported_tables[] = $result['entry'];
            } else {
                $errors[] = $table_name . ': ' . $result['error'];
            }
        }

        return array(
            'tables_changed' => $tables_changed,
            'total_new_rows' => $total_new_rows,
            'errors'         => $errors,
            'exported_tables' => $exported_tables,
        );
    }

    /**
     * Export delta for a single table if it has new rows.
     *
     * @param string $tableName Table name.
     * @param array  $info      Master table info.
     * @param string $incDir    Incremental directory.
     * @param PDO    $rootPdo   Root DB connection.
     * @param int    $sequence  Sequence number.
     * @return array|null Export result or null if skipped.
     */
    private function exportTableDelta(string $tableName, array $info, string $incDir, PDO $rootPdo, int $sequence): ?array {
        $last_max_id = $this->getLastMaxId($tableName, $info, $rootPdo, $sequence);

        if ($last_max_id === null) {
            $this->log('INFO', 'Skipping table (no auto-increment PK): ' . $tableName);
            return null;
        }

        $new_count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM `{$tableName}` WHERE `{$info['pk_column']}` > %d",
                $last_max_id
            )
        );

        if ($new_count === 0) {
            return null;
        }

        $result = $this->exportDeltaRows($incDir, $tableName, $info['pk_column'], $last_max_id, $new_count);

        if ($result['success']) {
            $this->log('INFO', sprintf('Incremental export: %s (+%d rows, %s)',
                $tableName, $result['rows'], $this->formatBytes($result['file_size'])
            ));
            $result['entry'] = array(
                'table'    => $tableName,
                'new_rows' => $result['rows'],
                'size'     => $result['file_size'],
            );
        } else {
            $this->log('ERROR', 'Incremental export failed: ' . $tableName, array('error' => $result['error']));
        }

        return $result;
    }

    /**
     * Finalize the incremental backup: register, invalidate, and return result.
     *
     * @param string $title         Backup title.
     * @param string $masterDir     Master directory.
     * @param string $folderName    Folder name.
     * @param int    $sequence      Sequence number.
     * @param array  $export        Export results.
     * @param string $incrementalDir Incremental directory.
     * @param float  $startTime     Start time.
     * @return array Final result.
     */
    private function finalizeIncremental(string $title, string $masterDir, string $folderName, int $sequence, array $export, string $incrementalDir, float $startTime): array {
        $duration = microtime(true) - $startTime;

        $snapshot_id = $this->registerIncrementalSnapshot(
            $title, $masterDir, $folderName, $sequence,
            $export['tables_changed'], $export['total_new_rows'], $incrementalDir
        );

        $this->log('INFO', 'Incremental backup complete', array(
            'snapshot_id'    => $snapshot_id,
            'sequence'       => $sequence,
            'tables_changed' => $export['tables_changed'],
            'total_new_rows' => $export['total_new_rows'],
            'errors'         => count($export['errors']),
            'duration'       => round($duration, 2) . 's',
        ));

        $this->invalidateParentZipExport($masterDir);

        return array(
            'success'        => true,
            'snapshot_id'    => $snapshot_id,
            'sequence'       => $sequence,
            'folder_name'    => $folderName,
            'path'           => $incrementalDir,
            'tables_changed' => $export['tables_changed'],
            'total_new_rows' => $export['total_new_rows'],
            'tables'         => $export['exported_tables'],
            'errors'         => $export['errors'],
            'duration'       => $duration,
        );
    }

    /**
     * Invalidate any cached ZIP export for the parent full snapshot.
     *
     * @param string $master_dir Path to the master (full) snapshot directory.
     * @return void
     */
    private function invalidateParentZipExport($master_dir) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return;
            }

            $stmt = $pdo->prepare(
                'SELECT id FROM ' . TABLE_SNAPSHOTS .
                ' WHERE filepath = ? AND status = ? LIMIT 1'
            );
            $stmt->execute(array($master_dir, SNAPSHOT_STATUS_COMPLETE));
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                $this->log('DEBUG', 'No parent snapshot found for ZIP invalidation', array(
                    'master_dir' => basename($master_dir),
                ));
                return;
            }

            require_once dirname(__FILE__) . '/SnapshotExporter.php';
            $exporter = RiseupSnapshotExporter::getInstance($this->logger, $this->db);
            if ($exporter) {
                $invalidated = $exporter->invalidateZip((int) $parent['id']);
                $this->log('INFO', 'Parent ZIP export invalidated after incremental backup', array(
                    'parent_id'   => $parent['id'],
                    'invalidated' => $invalidated,
                ));
            }
        } catch (Exception $e) {
            $this->log('WARN', 'Failed to invalidate parent ZIP export', array(
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Get master table inventory from a-root.db.
     *
     * @param PDO $rootPdo a-root.db PDO connection.
     * @return array Map of table_name => { row_count, pk_column }.
     */
    private function getMasterTableInventory($rootPdo) {
        $rows = $rootPdo->query("SELECT table_name, row_count FROM snapshot_tables ORDER BY table_name")
            ->fetchAll(PDO::FETCH_ASSOC);

        $inventory = array();
        foreach ($rows as $row) {
            $pk = $this->detectPrimaryKey($row['table_name']);
            $inventory[$row['table_name']] = array(
                'row_count' => (int) $row['row_count'],
                'pk_column' => $pk,
            );
        }

        return $inventory;
    }

    /**
     * Detect the auto-increment primary key column of a MySQL table.
     *
     * @param string $table Table name.
     * @return string|null PK column name or null if no auto-increment PK.
     */
    private function detectPrimaryKey($table) {
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI' && strpos($col['Extra'], 'auto_increment') !== false) {
                return $col['Field'];
            }
        }
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI' && in_array(strtolower($col['Type']), array('bigint', 'int', 'mediumint', 'smallint', 'tinyint'))
                || (strpos(strtolower($col['Type']), 'int') !== false && $col['Key'] === 'PRI')) {
                return $col['Field'];
            }
        }
        return null;
    }

    /**
     * Determine the last_max_id for a table.
     *
     * @param string $table_name Table name.
     * @param array  $info       Master table info with row_count and pk_column.
     * @param PDO    $rootPdo    a-root.db PDO connection.
     * @param int    $sequence   Current sequence number.
     * @return int|null Last max ID or null if no PK.
     */
    private function getLastMaxId($table_name, $info, $rootPdo, $sequence) {
        $pk = $info['pk_column'];
        if ($pk === null) {
            return null;
        }

        if ($sequence === 1) {
            return $this->getMaxIdFromMasterSqlite($rootPdo, $table_name, $pk, $info);
        }

        return $this->getMaxIdFromPreviousIncremental($rootPdo, $table_name, $pk, $sequence, $info);
    }

    /**
     * Get max ID from the master snapshot's SQLite file.
     *
     * @param PDO    $rootPdo   Root DB connection.
     * @param string $tableName Table name.
     * @param string $pk        Primary key column.
     * @param array  $info      Master table info (fallback row_count).
     * @return int Max ID.
     */
    private function getMaxIdFromMasterSqlite(PDO $rootPdo, string $tableName, string $pk, array $info): int {
        $sqlite_file = $this->findMasterSqliteFile($rootPdo, $tableName);
        if (!$sqlite_file) {
            return (int) $info['row_count'];
        }

        try {
            $tablePdo = new PDO('sqlite:' . $sqlite_file);
            $tablePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $max_id = $tablePdo->query("SELECT MAX(`{$pk}`) FROM `{$tableName}`")->fetchColumn();
            $tablePdo = null;
            return ($max_id !== false && $max_id !== null) ? (int) $max_id : 0;
        } catch (Exception $e) {
            $this->log('WARN', 'Could not read master SQLite for max ID', array(
                'table' => $tableName, 'error' => $e->getMessage(),
            ));
            return (int) $info['row_count'];
        }
    }

    /**
     * Get max ID from the previous incremental's SQLite file.
     *
     * @param PDO    $rootPdo   Root DB connection.
     * @param string $tableName Table name.
     * @param string $pk        Primary key column.
     * @param int    $sequence  Current sequence number.
     * @param array  $info      Master table info (for fallback).
     * @return int Max ID.
     */
    private function getMaxIdFromPreviousIncremental(PDO $rootPdo, string $tableName, string $pk, int $sequence, array $info): int {
        $prev_seq = $sequence - 1;
        $prev_folder = $rootPdo->query(
            "SELECT folder_name FROM incremental_backups WHERE sequence_num = {$prev_seq}"
        )->fetchColumn();

        if ($prev_folder) {
            $root_dir = $this->getRootDirFromPdo($rootPdo);
            $prev_sqlite = $root_dir . '/incremental/' . $prev_folder . '/' . $tableName . '.sqlite';
            $maxId = $this->readMaxIdFromSqlite($prev_sqlite, $tableName, $pk);
            if ($maxId !== null) {
                return $maxId;
            }
        }

        // Fallback: use master max
        return $this->getMaxIdFromMasterSqlite($rootPdo, $tableName, $pk, $info);
    }

    /**
     * Read MAX(pk) from a SQLite file.
     *
     * @param string $sqlitePath Path to SQLite file.
     * @param string $tableName  Table name.
     * @param string $pk         Primary key column.
     * @return int|null Max ID or null on failure.
     */
    private function readMaxIdFromSqlite(string $sqlitePath, string $tableName, string $pk): ?int {
        if (RiseupBooleanHelpers::is_file_missing($sqlitePath)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $sqlitePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $max_id = $pdo->query("SELECT MAX(`{$pk}`) FROM `{$tableName}`")->fetchColumn();
            $pdo = null;
            return ($max_id !== false && $max_id !== null) ? (int) $max_id : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Find the master SQLite file path for a table.
     *
     * @param PDO    $rootPdo    a-root.db connection.
     * @param string $table_name Table name.
     * @return string|null Full path or null.
     */
    private function findMasterSqliteFile($rootPdo, $table_name) {
        $stmt = $rootPdo->prepare("SELECT sqlite_file FROM snapshot_tables WHERE table_name = ?");
        $stmt->execute(array($table_name));
        $filename = $stmt->fetchColumn();

        if (!$filename) {
            return null;
        }

        $root_dir = $this->getRootDirFromPdo($rootPdo);
        $full_path = $root_dir . '/' . $filename;

        return file_exists($full_path) ? $full_path : null;
    }

    /**
     * Get the snapshot root directory from the a-root.db PDO path.
     *
     * @param PDO $rootPdo PDO connection.
     * @return string Directory containing a-root.db.
     */
    private function getRootDirFromPdo($rootPdo) {
        $result = $rootPdo->query("PRAGMA database_list")->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['file'])) {
            return dirname($result['file']);
        }
        return '';
    }

    /**
     * Get the next incremental sequence number.
     *
     * @param PDO $rootPdo a-root.db connection.
     * @return int Next sequence number (1-based).
     */
    private function getNextSequence($rootPdo) {
        $max = $rootPdo->query("SELECT MAX(sequence_num) FROM incremental_backups")->fetchColumn();
        return ($max !== false && $max !== null) ? (int) $max + 1 : 1;
    }

    /**
     * Export delta rows (id > last_max_id) for a table to an incremental SQLite file.
     *
     * @param string $incremental_dir Incremental directory path.
     * @param string $table           MySQL table name.
     * @param string $pk_column       Primary key column.
     * @param int    $last_max_id     Last max ID from previous backup.
     * @param int    $expected_count  Expected number of new rows.
     * @return array Result: success, rows, file_size, checksum, error.
     */
    private function exportDeltaRows($incremental_dir, $table, $pk_column, $last_max_id, $expected_count) {
        $filename = $table . '.sqlite';
        $filepath = $incremental_dir . '/' . $filename;

        try {
            $sqlite = $this->createIncrementalSqliteTable($filepath, $table);
            $exported = $this->batchExportDelta($sqlite, $table, $pk_column, $last_max_id);

            $sqlite = null;

            return array(
                'success'   => true,
                'rows'      => $exported,
                'file_size' => filesize($filepath),
                'checksum'  => md5_file($filepath),
            );

        } catch (Exception $e) {
            return array(
                'success' => false, 'error' => $e->getMessage(),
                'rows' => 0, 'file_size' => 0, 'checksum' => '',
            );
        }
    }

    /**
     * Create a SQLite file and initialize the table schema.
     *
     * @param string $filepath SQLite file path.
     * @param string $table    Table name.
     * @return PDO SQLite connection.
     */
    private function createIncrementalSqliteTable(string $filepath, string $table): PDO {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('PRAGMA journal_mode = WAL');
        $sqlite->exec('PRAGMA synchronous = OFF');

        $create_result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        if (!$create_result) {
            throw new Exception('Failed to get CREATE TABLE for ' . $table);
        }

        $sqlite->exec(RiseupSqliteSchemaConverter::convert($create_result[1], $table));

        return $sqlite;
    }

    /**
     * Batch export delta rows from MySQL to SQLite.
     *
     * @param PDO    $sqlite     SQLite connection.
     * @param string $table      Table name.
     * @param string $pkColumn   Primary key column.
     * @param int    $lastMaxId  Last max ID threshold.
     * @return int Number of rows exported.
     */
    private function batchExportDelta(PDO $sqlite, string $table, string $pkColumn, int $lastMaxId): int {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
        $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

        $stmt = $sqlite->prepare("INSERT OR REPLACE INTO `{$table}` ({$column_list}) VALUES ({$placeholders})");

        $offset = 0;
        $exported = 0;
        $sqlite->beginTransaction();

        while (true) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE `{$pkColumn}` > %d ORDER BY `{$pkColumn}` ASC LIMIT %d OFFSET %d",
                    $lastMaxId, $this->batchSize, $offset
                ),
                ARRAY_N
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $stmt->execute($row);
                $exported++;
            }

            $offset += $this->batchSize;
        }

        $sqlite->commit();
        return $exported;
    }

    /**
     * Register the incremental snapshot in the tracking table.
     *
     * @param string $title           Snapshot title.
     * @param string $master_dir      Master snapshot directory.
     * @param string $folder_name     Incremental folder name.
     * @param int    $sequence        Sequence number.
     * @param int    $tables_changed  Tables with changes.
     * @param int    $total_new_rows  Total new rows.
     * @param string $incremental_dir Incremental directory path.
     * @return int|false Snapshot ID or false.
     */
    private function registerIncrementalSnapshot($title, $master_dir, $folder_name, $sequence, $tables_changed, $total_new_rows, $incremental_dir) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return false;
        }

        try {
            $snap_sequence = $this->getNextTrackingSequence($pdo);
            $tables_json = $this->buildIncrementalMetaJson($master_dir, $folder_name, $sequence, $tables_changed, $total_new_rows);
            $dir_size = $this->calculateDirectorySize($incremental_dir);

            return $this->insertIncrementalRecord($pdo, $snap_sequence, $folder_name, $incremental_dir, $tables_json, $total_new_rows, $dir_size);
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to register incremental snapshot', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Get the next tracking sequence from the snapshots table.
     *
     * @param PDO $pdo Database connection.
     * @return int Next sequence.
     */
    private function getNextTrackingSequence(PDO $pdo): int {
        $row = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . TABLE_SNAPSHOTS)
            ->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;
    }

    /**
     * Build the incremental metadata JSON.
     *
     * @param string $masterDir     Master directory.
     * @param string $folderName    Folder name.
     * @param int    $sequence      Sequence.
     * @param int    $tablesChanged Tables changed.
     * @param int    $totalNewRows  Total new rows.
     * @return string JSON metadata.
     */
    private function buildIncrementalMetaJson(string $masterDir, string $folderName, int $sequence, int $tablesChanged, int $totalNewRows): string {
        return json_encode(array(
            'type'           => 'incremental',
            'master'         => basename($masterDir),
            'sequence'       => $sequence,
            'folder'         => $folderName,
            'tables_changed' => $tablesChanged,
            'total_new_rows' => $totalNewRows,
        ));
    }

    /**
     * Calculate total size of a directory.
     *
     * @param string $dir Directory path.
     * @return int Size in bytes.
     */
    private function calculateDirectorySize(string $dir): int {
        $size = 0;
        if (RiseupBooleanHelpers::is_dir_missing($dir)) {
            return 0;
        }

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
     * Insert an incremental snapshot record.
     *
     * @param PDO    $pdo        Database connection.
     * @param int    $sequence   Sequence number.
     * @param string $filename   Folder name.
     * @param string $filepath   Full path.
     * @param string $tablesJson JSON metadata.
     * @param int    $totalRows  Total new rows.
     * @param int    $dirSize    Directory size.
     * @return int Snapshot ID.
     */
    private function insertIncrementalRecord(PDO $pdo, int $sequence, string $filename, string $filepath, string $tablesJson, int $totalRows, int $dirSize): int {
        $now = gmdate('c');
        $stmt = $pdo->prepare("INSERT INTO " . TABLE_SNAPSHOTS . "
            (sequence, filename, filepath, provider, scope, tables_json, total_rows,
             file_size, trigger_source, status, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute(array(
            $sequence, $filename, $filepath,
            SNAPSHOT_PROVIDER_NATIVE, 'incremental', $tablesJson,
            $totalRows, $dirSize,
            SNAPSHOT_TRIGGER_API, SNAPSHOT_STATUS_COMPLETE, $now, $now,
        ));

        return (int)$pdo->lastInsertId();
    }

    /**
     * Find the latest full (master) snapshot directory.
     *
     * @return string|null Path to the latest full snapshot directory or null.
     */
    public function findLatestMasterSnapshot() {
        $masterFromDb = $this->findMasterFromDb();
        if ($masterFromDb) {
            return $masterFromDb;
        }

        return $this->findMasterFromFilesystem();
    }

    /**
     * Find the latest master snapshot from the database.
     *
     * @return string|null Master directory path or null.
     */
    private function findMasterFromDb(): ?string {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->query("SELECT filepath FROM " . TABLE_SNAPSHOTS . "
                WHERE scope != 'incremental' AND status = '" . SNAPSHOT_STATUS_COMPLETE . "'
                ORDER BY created_at DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['filepath']) && is_dir($row['filepath']) && file_exists($row['filepath'] . '/a-root.db')) {
                return $row['filepath'];
            }

            return null;
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to find master snapshot from DB', array('error' => $e->getMessage()));
            return null;
        }
    }

    /**
     * Find the latest master snapshot from the filesystem (fallback).
     *
     * @return string|null Master directory path or null.
     */
    private function findMasterFromFilesystem(): ?string {
        $base_dir = $this->getSnapshotsBaseDir();
        if (RiseupBooleanHelpers::is_dir_missing($base_dir)) {
            return null;
        }

        $dirs = glob($base_dir . '/*_full_*', GLOB_ONLYDIR);
        if (empty($dirs)) {
            return null;
        }

        rsort($dirs);
        foreach ($dirs as $dir) {
            if (file_exists($dir . '/a-root.db')) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Get the base snapshots directory.
     *
     * @return string Base snapshots directory path.
     */
    private function getSnapshotsBaseDir() {
        return RiseupPathUtils::get_snapshots_dir();
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
     * Log a message with incremental context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [INCREMENTAL]';
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
