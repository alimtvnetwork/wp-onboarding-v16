<?php
/**
 * NativeTableExportTrait — MySQL-to-SQLite table export and schema conversion.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait NativeTableExportTrait {

    /**
     * Export a single MySQL table to SQLite.
     *
     * @param PDO    $sqlite      SQLite PDO instance.
     * @param string $table       Table name.
     * @param int    $snapshot_id Snapshot ID for progress tracking.
     * @return array Export result.
     */
    private function exportTable($sqlite, $table, $snapshot_id) {
        try {
            $create_sql = $this->getCreateTableSql($table);
            if (!$create_sql) {
                throw new Exception('Failed to get table structure');
            }

            $sqlite_create = $this->convertCreateStatement($create_sql, $table);
            $sqlite->exec($sqlite_create);

            $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
            if ($count === 0) {
                return array('success' => true, 'rows' => 0, 'bytes' => 0);
            }

            return $this->exportTableRows($sqlite, $table, $count);
        } catch (Exception $e) {
            if ($sqlite->inTransaction()) {
                $sqlite->rollBack();
            }
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0, 'bytes' => 0);
        }
    }

    /**
     * Export rows from a MySQL table to SQLite in batches.
     *
     * @param PDO    $sqlite SQLite PDO instance.
     * @param string $table  Table name.
     * @param int    $count  Total row count.
     * @return array Export result.
     */
    private function exportTableRows($sqlite, $table, int $count): array {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $column_names = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
        $column_list = implode(', ', array_map(function($c) { return "`{$c}`"; }, $column_names));

        $stmt = $sqlite->prepare("INSERT INTO `{$table}` ({$column_list}) VALUES ({$placeholders})");

        $batch_size = SNAPSHOT_BATCH_SIZE;
        $offset = 0;
        $exported = 0;
        $bytes = 0;

        $sqlite->beginTransaction();

        while ($offset < $count) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $batch_size, $offset),
                ARRAY_N
            );

            foreach ($rows as $row) {
                $stmt->execute($row);
                $exported++;
                $bytes += strlen(implode('', array_map('strval', $row)));
            }

            $offset += $batch_size;
            $this->logExportProgress($table, $offset, $count, $batch_size);
        }

        $sqlite->commit();
        return array('success' => true, 'rows' => $exported, 'bytes' => $bytes);
    }

    /**
     * Log export progress at 25% intervals.
     *
     * @param string $table      Table name.
     * @param int    $offset     Current offset.
     * @param int    $count      Total count.
     * @param int    $batch_size Batch size.
     */
    private function logExportProgress(string $table, int $offset, int $count, int $batch_size) {
        $progress = ($offset / $count) * 100;
        $prev = (($offset - $batch_size) / $count) * 100;

        if ($progress >= 25 && $prev < 25) {
            $this->log(LOG_LEVEL_DEBUG, "{$table}: 25% complete");
        } elseif ($progress >= 50 && $prev < 50) {
            $this->log(LOG_LEVEL_DEBUG, "{$table}: 50% complete");
        } elseif ($progress >= 75 && $prev < 75) {
            $this->log(LOG_LEVEL_DEBUG, "{$table}: 75% complete");
        }
    }

    /**
     * Get MySQL CREATE TABLE statement.
     *
     * @param string $table Table name.
     * @return string|null CREATE statement or null.
     */
    private function getCreateTableSql($table) {
        $result = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        return $result ? $result[1] : null;
    }

    /**
     * Convert MySQL CREATE TABLE to SQLite compatible syntax.
     *
     * @param string $mysql_create MySQL CREATE statement.
     * @param string $table        Table name.
     * @return string SQLite CREATE statement.
     */
    private function convertCreateStatement($mysql_create, $table) {
        $sql = $mysql_create;

        $sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+COLLATE\s*=?\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+AUTO_INCREMENT\s*=\s*\d+/i', '', $sql);
        $sql = preg_replace('/\s+ROW_FORMAT\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);

        $sql = $this->convertMysqlDataTypes($sql);

        $sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+UNSIGNED\b/i', '', $sql);
        $sql = preg_replace('/\s+ZEROFILL\b/i', '', $sql);

        $sql = preg_replace('/,\s*KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*\)/', ')', $sql);

        return $sql;
    }

    /**
     * Apply MySQL-to-SQLite data type conversions.
     *
     * @param string $sql SQL statement.
     * @return string Converted SQL.
     */
    private function convertMysqlDataTypes(string $sql): string {
        $type_map = array(
            '/\bTINYINT\s*\(\d+\)/i' => 'INTEGER', '/\bSMALLINT\s*\(\d+\)/i' => 'INTEGER',
            '/\bMEDIUMINT\s*\(\d+\)/i' => 'INTEGER', '/\bBIGINT\s*\(\d+\)/i' => 'INTEGER',
            '/\bINT\s*\(\d+\)/i' => 'INTEGER', '/\bDOUBLE\b/i' => 'REAL',
            '/\bFLOAT\b/i' => 'REAL', '/\bDECIMAL\s*\([^)]+\)/i' => 'REAL',
            '/\bVARCHAR\s*\(\d+\)/i' => 'TEXT', '/\bCHAR\s*\(\d+\)/i' => 'TEXT',
            '/\bLONGTEXT\b/i' => 'TEXT', '/\bMEDIUMTEXT\b/i' => 'TEXT',
            '/\bTINYTEXT\b/i' => 'TEXT', '/\bDATETIME\b/i' => 'TEXT',
            '/\bTIMESTAMP\b/i' => 'TEXT', '/\bDATE\b/i' => 'TEXT', '/\bTIME\b/i' => 'TEXT',
            '/\bLONGBLOB\b/i' => 'BLOB', '/\bMEDIUMBLOB\b/i' => 'BLOB',
            '/\bTINYBLOB\b/i' => 'BLOB', '/\bENUM\s*\([^)]+\)/i' => 'TEXT',
            '/\bSET\s*\([^)]+\)/i' => 'TEXT', '/\bBIT\s*\(\d+\)/i' => 'INTEGER',
            '/\bYEAR\s*\(\d+\)/i' => 'INTEGER', '/\bBOOLEAN\b/i' => 'INTEGER',
            '/\bBOOL\b/i' => 'INTEGER',
        );

        foreach ($type_map as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        return $sql;
    }

    /**
     * Get tables for a given scope.
     *
     * @param string $scope  Scope type.
     * @param array  $custom Custom table list for 'custom' scope.
     * @return array List of table names.
     */
    private function getTablesForScope($scope, $custom = array()) {
        $all_tables = $this->wpdb->get_col("SHOW TABLES");
        $prefix = $this->wpdb->prefix;

        switch ($scope) {
            case SNAPSHOT_SCOPE_ALL:
                return $all_tables;
            case SNAPSHOT_SCOPE_WORDPRESS:
                return array_filter($all_tables, function($table) use ($prefix) { return strpos($table, $prefix) === 0; });
            case SNAPSHOT_SCOPE_CONTENT:
                return $this->getContentTables($all_tables, $prefix);
            case SNAPSHOT_SCOPE_CUSTOM:
                return array_filter($all_tables, function($table) use ($custom) { return in_array($table, $custom); });
            default:
                return array();
        }
    }

    /**
     * Get content-only tables.
     *
     * @param array  $all_tables All tables.
     * @param string $prefix     WP table prefix.
     * @return array Content tables.
     */
    private function getContentTables(array $all_tables, string $prefix): array {
        $content_tables = array(
            $prefix . 'posts', $prefix . 'postmeta', $prefix . 'comments', $prefix . 'commentmeta',
            $prefix . 'terms', $prefix . 'termmeta', $prefix . 'term_taxonomy', $prefix . 'term_relationships',
        );
        return array_filter($all_tables, function($table) use ($content_tables) { return in_array($table, $content_tables); });
    }
}
