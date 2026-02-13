<?php
/**
 * NativeTableExportConvertTrait — MySQL-to-SQLite schema conversion and scope resolution.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait NativeTableExportConvertTrait {

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
