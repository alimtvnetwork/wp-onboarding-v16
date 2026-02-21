<?php
/**
 * NativeTableExportConvertTrait — MySQL-to-SQLite schema conversion and scope resolution.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotScopeType;

trait NativeTableExportConvertTrait {
    private function convertCreateStatement(string $mysqlCreate, string $table): string {
        $sql = $this->stripMysqlTableOptions($mysqlCreate);
        $sql = $this->convertMysqlDataTypes($sql);
        $sql = $this->stripMysqlColumnModifiers($sql);
        $sql = $this->stripMysqlIndexDefinitions($sql);

        return $sql;
    }

    private function stripMysqlTableOptions(string $sql): string {
        $sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+COLLATE\s*=?\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+AUTO_INCREMENT\s*=\s*\d+/i', '', $sql);
        $sql = preg_replace('/\s+ROW_FORMAT\s*=\s*\w+/i', '', $sql);

        return preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);
    }

    private function stripMysqlColumnModifiers(string $sql): string {
        $sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+UNSIGNED\b/i', '', $sql);

        return preg_replace('/\s+ZEROFILL\b/i', '', $sql);
    }

    private function stripMysqlIndexDefinitions(string $sql): string {
        $sql = preg_replace('/,\s*KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);

        return preg_replace('/,\s*\)/', ')', $sql);
    }

    private function convertMysqlDataTypes(string $sql): string {
        $type_map = array(
            '/\bTINYINT\s*\(\d+\)/i'      => 'INTEGER',
            '/\bSMALLINT\s*\(\d+\)/i'     => 'INTEGER',
            '/\bMEDIUMINT\s*\(\d+\)/i'    => 'INTEGER',
            '/\bBIGINT\s*\(\d+\)/i'       => 'INTEGER',
            '/\bINT\s*\(\d+\)/i'          => 'INTEGER',
            '/\bDOUBLE\b/i'               => 'REAL',
            '/\bFLOAT\b/i'                => 'REAL',
            '/\bDECIMAL\s*\([^)]+\)/i'    => 'REAL',
            '/\bVARCHAR\s*\(\d+\)/i'      => 'TEXT',
            '/\bCHAR\s*\(\d+\)/i'         => 'TEXT',
            '/\bLONGTEXT\b/i'             => 'TEXT',
            '/\bMEDIUMTEXT\b/i'           => 'TEXT',
            '/\bTINYTEXT\b/i'             => 'TEXT',
            '/\bDATETIME\b/i'             => 'TEXT',
            '/\bTIMESTAMP\b/i'            => 'TEXT',
            '/\bDATE\b/i'                 => 'TEXT',
            '/\bTIME\b/i'                 => 'TEXT',
            '/\bLONGBLOB\b/i'             => 'BLOB',
            '/\bMEDIUMBLOB\b/i'           => 'BLOB',
            '/\bTINYBLOB\b/i'             => 'BLOB',
            '/\bENUM\s*\([^)]+\)/i'       => 'TEXT',
            '/\bSET\s*\([^)]+\)/i'        => 'TEXT',
            '/\bBIT\s*\(\d+\)/i'          => 'INTEGER',
            '/\bYEAR\s*\(\d+\)/i'         => 'INTEGER',
            '/\bBOOLEAN\b/i'              => 'INTEGER',
            '/\bBOOL\b/i'                 => 'INTEGER',
        );

        foreach ($type_map as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        return $sql;
    }

    private function getTablesForScope(string $scope, array $custom = array()): array {
        $all_tables = $this->wpdb->get_col("SHOW TABLES");
        $prefix = $this->wpdb->prefix;

        switch ($scope) {
            case SnapshotScopeType::All->value:
                return $all_tables;
            case SnapshotScopeType::WordPress->value:
                return array_filter($all_tables, function($table) use ($prefix) { return strpos($table, $prefix) === 0; });
            case SnapshotScopeType::Content->value:
                return $this->getContentTables($all_tables, $prefix);
            case SnapshotScopeType::Custom->value:
                return array_filter($all_tables, function($table) use ($custom) { return in_array($table, $custom); });
            default:
                return array();
        }
    }

    private function getContentTables(array $allTables, string $prefix): array {
        $content_tables = array(
            $prefix . 'posts',
            $prefix . 'postmeta',
            $prefix . 'comments',
            $prefix . 'commentmeta',
            $prefix . 'terms',
            $prefix . 'termmeta',
            $prefix . 'term_taxonomy',
            $prefix . 'term_relationships',
        );

        return array_filter($all_tables, function($table) use ($content_tables) { return in_array($table, $content_tables); });
    }
}
