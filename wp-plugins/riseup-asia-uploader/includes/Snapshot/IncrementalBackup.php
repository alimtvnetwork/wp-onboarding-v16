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
        $this->batchSize = RISEUP_SNAPSHOT_BATCH_SIZE;
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
            // 1. Open master a-root.db
            $rootPdo = new PDO('sqlite:' . $root_path);
            $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 2. Read master table inventory (last_max_id = row_count from master)
            $master_tables = $this->getMasterTableInventory($rootPdo);
            if (empty($master_tables)) {
                $rootPdo = null;
                return array(
                    'success' => false,
                    'error'   => 'No tables found in master snapshot',
                );
            }

            // 3. Determine next sequence number
            $sequence = $this->getNextSequence($rootPdo);
            $folder_name = sprintf('%02d_%s', $sequence, date('Y-m-d'));
            $incremental_base = $master_dir . '/incremental';
            $incremental_dir = $incremental_base . '/' . $folder_name;

            if (!RiseupPathUtils::ensure_dir($incremental_dir, true)) {
                $rootPdo = null;
                return array(
                    'success' => false,
                    'error'   => 'Failed to create incremental directory: ' . $folder_name,
                );
            }

            $this->log('INFO', 'Incremental directory created', array(
                'sequence'    => $sequence,
                'folder_name' => $folder_name,
            ));

            // 4. For each master table, check for new rows and export delta
            $tables_changed = 0;
            $total_new_rows = 0;
            $errors = array();
            $exported_tables = array();

            foreach ($master_tables as $table_name => $info) {
                $last_max_id = $this->getLastMaxId($table_name, $info, $rootPdo, $sequence);

                if ($last_max_id === null) {
                    // Table has no auto-increment primary key — skip incremental
                    $this->log('INFO', 'Skipping table (no auto-increment PK): ' . $table_name);
                    continue;
                }

                // Count new rows
                $new_count = (int) $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$table_name}` WHERE `{$info['pk_column']}` > %d",
                        $last_max_id
                    )
                );

                if ($new_count === 0) {
                    continue; // No changes for this table
                }

                // Export new rows to SQLite
                $result = $this->exportDeltaRows(
                    $incremental_dir,
                    $table_name,
                    $info['pk_column'],
                    $last_max_id,
                    $new_count
                );

                if ($result['success']) {
                    $tables_changed++;
                    $total_new_rows += $result['rows'];
                    $exported_tables[] = array(
                        'table'    => $table_name,
                        'new_rows' => $result['rows'],
                        'size'     => $result['file_size'],
                    );

                    $this->log('INFO', sprintf('Incremental export: %s (+%d rows, %s)',
                        $table_name, $result['rows'], $this->formatBytes($result['file_size'])
                    ));
                } else {
                    $errors[] = $table_name . ': ' . $result['error'];
                    $this->log('ERROR', 'Incremental export failed: ' . $table_name, array(
                        'error' => $result['error'],
                    ));
                }
            }

            // 5. Register incremental in a-root.db
            $this->rootDb->registerIncremental($rootPdo, array(
                'sequence_num'   => $sequence,
                'folder_name'    => $folder_name,
                'tables_changed' => $tables_changed,
                'total_new_rows' => $total_new_rows,
                'relative_path'  => 'incremental/' . $folder_name . '/',
            ));

            $rootPdo = null; // Close

            $duration = microtime(true) - $start_time;

            // 6. Register in snapshots tracking table
            $snapshot_id = $this->registerIncrementalSnapshot(
                $title,
                $master_dir,
                $folder_name,
                $sequence,
                $tables_changed,
                $total_new_rows,
                $incremental_dir
            );

            $this->log('INFO', 'Incremental backup complete', array(
                'snapshot_id'    => $snapshot_id,
                'sequence'       => $sequence,
                'tables_changed' => $tables_changed,
                'total_new_rows' => $total_new_rows,
                'errors'         => count($errors),
                'duration'       => round($duration, 2) . 's',
            ));

            // Feature D: Invalidate cached ZIP export for the parent full snapshot
            $this->invalidateParentZipExport($master_dir);

            return array(
                'success'        => true,
                'snapshot_id'    => $snapshot_id,
                'sequence'       => $sequence,
                'folder_name'    => $folder_name,
                'path'           => $incremental_dir,
                'tables_changed' => $tables_changed,
                'total_new_rows' => $total_new_rows,
                'tables'         => $exported_tables,
                'errors'         => $errors,
                'duration'       => $duration,
            );

        } catch (Exception $e) {
            $this->log('ERROR', 'Incremental backup failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            return array(
                'success' => false,
                'error'   => $e->getMessage(),
                'phase'   => 'incremental',
            );
        }
    }

    /**
     * Invalidate any cached ZIP export for the parent full snapshot.
     *
     * Looks up the parent snapshot ID from the master directory path and
     * calls the exporter to expire the cached ZIP.
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

            // Find the parent full snapshot by matching filepath to master_dir
            $stmt = $pdo->prepare(
                'SELECT id FROM ' . RISEUP_TABLE_SNAPSHOTS .
                ' WHERE filepath = ? AND status = ? LIMIT 1'
            );
            $stmt->execute(array($master_dir, RISEUP_SNAPSHOT_STATUS_COMPLETE));
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
        // Fallback: check for any PRI column (non-auto-increment)
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
     * For the first incremental (sequence 1), the last_max_id comes from the
     * master's row_count (i.e., the MAX(pk) at master time). For subsequent
     * incrementals, we look at the previous incremental's exported data.
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
            // First incremental — get MAX(pk) from the master snapshot's SQLite file
            // We use the actual MySQL max at master time approximated by querying the SQLite copy
            $master_dir = dirname($rootPdo->query("SELECT 1")->queryString ?: '');
            // Simpler approach: query the master SQLite file directly
            $sqlite_file = $this->findMasterSqliteFile($rootPdo, $table_name);
            if ($sqlite_file) {
                try {
                    $tablePdo = new PDO('sqlite:' . $sqlite_file);
                    $tablePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $max_id = $tablePdo->query("SELECT MAX(`{$pk}`) FROM `{$table_name}`")->fetchColumn();
                    $tablePdo = null;
                    return $max_id !== false && $max_id !== null ? (int) $max_id : 0;
                } catch (Exception $e) {
                    $this->log('WARN', 'Could not read master SQLite for max ID', array(
                        'table' => $table_name,
                        'error' => $e->getMessage(),
                    ));
                }
            }
            // Fallback: use row_count as rough estimate (less accurate for gaps)
            return (int) $info['row_count'];
        }

        // For sequence > 1: find the previous incremental's SQLite file for this table
        $prev_seq = $sequence - 1;
        $prev_folder = $rootPdo->query(
            "SELECT folder_name FROM incremental_backups WHERE sequence_num = {$prev_seq}"
        )->fetchColumn();

        if ($prev_folder) {
            $root_dir = $this->getRootDirFromPdo($rootPdo);
            $prev_sqlite = $root_dir . '/incremental/' . $prev_folder . '/' . $table_name . '.sqlite';
            if (file_exists($prev_sqlite)) {
                try {
                    $prevPdo = new PDO('sqlite:' . $prev_sqlite);
                    $prevPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $max_id = $prevPdo->query("SELECT MAX(`{$pk}`) FROM `{$table_name}`")->fetchColumn();
                    $prevPdo = null;
                    if ($max_id !== false && $max_id !== null) {
                        return (int) $max_id;
                    }
                } catch (Exception $e) {
                    // Fall through to master lookup
                }
            }
        }

        // Fallback: use master max
        return $this->getLastMaxId($table_name, $info, $rootPdo, 1);
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
     * @param PDO $rootPdo PDO connection (we extract the DSN path).
     * @return string Directory containing a-root.db.
     */
    private function getRootDirFromPdo($rootPdo) {
        // PDO doesn't expose DSN easily; use a workaround via PRAGMA
        // We stored the path when opening, but since we don't have it,
        // extract from the database_list pragma
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
            $sqlite = new PDO('sqlite:' . $filepath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqlite->exec('PRAGMA journal_mode = WAL');
            $sqlite->exec('PRAGMA synchronous = OFF');

            // Get MySQL table structure and convert for SQLite
            $create_result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            if (!$create_result) {
                throw new Exception('Failed to get CREATE TABLE for ' . $table);
            }

            $sqlite_create = $this->convertCreateStatement($create_result[1], $table);
            $sqlite->exec($sqlite_create);

            // Get columns
            $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
            $column_names = array_column($columns, 'Field');
            $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
            $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

            // Prepare INSERT OR REPLACE for incremental merge safety
            $insert_sql = "INSERT OR REPLACE INTO `{$table}` ({$column_list}) VALUES ({$placeholders})";
            $stmt = $sqlite->prepare($insert_sql);

            // Export in batches: only rows where pk > last_max_id
            $offset = 0;
            $exported = 0;
            $sqlite->beginTransaction();

            while (true) {
                $rows = $this->wpdb->get_results(
                    $this->wpdb->prepare(
                        "SELECT * FROM `{$table}` WHERE `{$pk_column}` > %d ORDER BY `{$pk_column}` ASC LIMIT %d OFFSET %d",
                        $last_max_id,
                        $this->batchSize,
                        $offset
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
            $sqlite = null; // Close

            $file_size = filesize($filepath);
            $checksum = md5_file($filepath);

            return array(
                'success'   => true,
                'rows'      => $exported,
                'file_size' => $file_size,
                'checksum'  => $checksum,
            );

        } catch (Exception $e) {
            return array(
                'success'   => false,
                'error'     => $e->getMessage(),
                'rows'      => 0,
                'file_size' => 0,
                'checksum'  => '',
            );
        }
    }

    /**
     * Convert MySQL CREATE TABLE to SQLite syntax.
     * (Reuses same logic as RiseupSnapshotWorker)
     *
     * @param string $mysql_create MySQL CREATE statement.
     * @param string $table        Table name.
     * @return string SQLite CREATE statement.
     */
    private function convertCreateStatement($mysql_create, $table) {
        $sql = $mysql_create;

        // Remove MySQL-specific clauses
        $sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+COLLATE\s*=?\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+AUTO_INCREMENT\s*=\s*\d+/i', '', $sql);
        $sql = preg_replace('/\s+ROW_FORMAT\s*=\s*\w+/i', '', $sql);

        // AUTO_INCREMENT → AUTOINCREMENT
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);

        // Convert data types
        $type_map = array(
            '/\bTINYINT\s*\(\d+\)/i'    => 'INTEGER',
            '/\bSMALLINT\s*\(\d+\)/i'   => 'INTEGER',
            '/\bMEDIUMINT\s*\(\d+\)/i'  => 'INTEGER',
            '/\bBIGINT\s*\(\d+\)/i'     => 'INTEGER',
            '/\bINT\s*\(\d+\)/i'        => 'INTEGER',
            '/\bDOUBLE\b/i'             => 'REAL',
            '/\bFLOAT\b/i'             => 'REAL',
            '/\bDECIMAL\s*\([^)]+\)/i'  => 'REAL',
            '/\bVARCHAR\s*\(\d+\)/i'    => 'TEXT',
            '/\bCHAR\s*\(\d+\)/i'       => 'TEXT',
            '/\bLONGTEXT\b/i'          => 'TEXT',
            '/\bMEDIUMTEXT\b/i'        => 'TEXT',
            '/\bTINYTEXT\b/i'          => 'TEXT',
            '/\bDATETIME\b/i'          => 'TEXT',
            '/\bTIMESTAMP\b/i'         => 'TEXT',
            '/\bDATE\b/i'              => 'TEXT',
            '/\bTIME\b/i'              => 'TEXT',
            '/\bLONGBLOB\b/i'          => 'BLOB',
            '/\bMEDIUMBLOB\b/i'        => 'BLOB',
            '/\bTINYBLOB\b/i'          => 'BLOB',
            '/\bENUM\s*\([^)]+\)/i'     => 'TEXT',
            '/\bSET\s*\([^)]+\)/i'      => 'TEXT',
            '/\bBIT\s*\(\d+\)/i'        => 'INTEGER',
            '/\bYEAR\s*\(\d+\)/i'       => 'INTEGER',
            '/\bBOOLEAN\b/i'           => 'INTEGER',
            '/\bBOOL\b/i'              => 'INTEGER',
        );

        foreach ($type_map as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        // Remove inline collation/charset/unsigned/zerofill
        $sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+UNSIGNED\b/i', '', $sql);
        $sql = preg_replace('/\s+ZEROFILL\b/i', '', $sql);

        // Remove KEY/INDEX definitions
        $sql = preg_replace('/,\s*KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);

        // Remove extra commas before closing paren
        $sql = preg_replace('/,\s*\)/', ')', $sql);

        return $sql;
    }

    /**
     * Register the incremental snapshot in the tracking table.
     *
     * @param string $title          Snapshot title.
     * @param string $master_dir     Master snapshot directory.
     * @param string $folder_name    Incremental folder name.
     * @param int    $sequence       Sequence number.
     * @param int    $tables_changed Tables with changes.
     * @param int    $total_new_rows Total new rows.
     * @param string $incremental_dir Incremental directory path.
     * @return int|false Snapshot ID or false.
     */
    private function registerIncrementalSnapshot($title, $master_dir, $folder_name, $sequence, $tables_changed, $total_new_rows, $incremental_dir) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return false;
        }

        try {
            $now = gmdate('c');
            $master_basename = basename($master_dir);

            // Get next snapshot sequence from tracking table
            $seq_result = $pdo->query("SELECT MAX(sequence) as max_seq FROM " . RISEUP_TABLE_SNAPSHOTS);
            $row = $seq_result->fetch(PDO::FETCH_ASSOC);
            $snap_sequence = ($row && $row['max_seq']) ? (int)$row['max_seq'] + 1 : 1;

            $tables_json = json_encode(array(
                'type'           => 'incremental',
                'master'         => $master_basename,
                'sequence'       => $sequence,
                'folder'         => $folder_name,
                'tables_changed' => $tables_changed,
                'total_new_rows' => $total_new_rows,
            ));

            $dir_size = 0;
            if (is_dir($incremental_dir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($incremental_dir, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $dir_size += $file->getSize();
                    }
                }
            }

            $stmt = $pdo->prepare("INSERT INTO " . RISEUP_TABLE_SNAPSHOTS . "
                (sequence, filename, filepath, provider, scope, tables_json, total_rows,
                 file_size, trigger_source, status, created_at, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute(array(
                $snap_sequence,
                $folder_name,
                $incremental_dir,
                RISEUP_SNAPSHOT_PROVIDER_NATIVE,
                'incremental',
                $tables_json,
                $total_new_rows,
                $dir_size,
                RISEUP_SNAPSHOT_TRIGGER_API,
                RISEUP_SNAPSHOT_STATUS_COMPLETE,
                $now,
                $now,
            ));

            return (int)$pdo->lastInsertId();

        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to register incremental snapshot', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Find the latest full (master) snapshot directory.
     *
     * @return string|null Path to the latest full snapshot directory or null.
     */
    public function findLatestMasterSnapshot() {
        $pdo = $this->db->get_pdo();
        if (!$pdo) {
            return null;
        }

        try {
            // Find the most recent full backup from tracking table
            $stmt = $pdo->query("SELECT filepath FROM " . RISEUP_TABLE_SNAPSHOTS . "
                WHERE scope != 'incremental' AND status = '" . RISEUP_SNAPSHOT_STATUS_COMPLETE . "'
                ORDER BY created_at DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['filepath']) && is_dir($row['filepath'])) {
                // Verify a-root.db exists
                if (file_exists($row['filepath'] . '/a-root.db')) {
                    return $row['filepath'];
                }
            }

            // Fallback: scan snapshots directory for most recent full backup
            $base_dir = $this->getSnapshotsBaseDir();
            if (!is_dir($base_dir)) {
                return null;
            }

            $dirs = glob($base_dir . '/*_full_*', GLOB_ONLYDIR);
            if (empty($dirs)) {
                return null;
            }

            // Sort by name (date-prefixed) descending
            rsort($dirs);
            foreach ($dirs as $dir) {
                if (file_exists($dir . '/a-root.db')) {
                    return $dir;
                }
            }

            return null;

        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to find master snapshot', array('error' => $e->getMessage()));
            return null;
        }
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
