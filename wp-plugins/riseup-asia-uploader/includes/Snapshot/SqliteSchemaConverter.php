<?php
/**
 * SqliteSchemaConverter — Converts MySQL schema definitions to SQLite equivalents.
 *
 * @package RiseupAsia\Snapshot
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

class SqliteSchemaConverter {
    private static $typeMap = array(
        '/\bTINYINT\s*\((\d+)\)/i'    => 'INTEGER',
        '/\bSMALLINT\s*\((\d+)\)/i'   => 'INTEGER',
        '/\bMEDIUMINT\s*\((\d+)\)/i'  => 'INTEGER',
        '/\bBIGINT\s*\((\d+)\)/i'     => 'INTEGER',
        '/\bINT\s*\((\d+)\)/i'        => 'INTEGER',
        '/\bDOUBLE\b/i'             => 'REAL',
        '/\bFLOAT\b/i'              => 'REAL',
        '/\bDECIMAL\s*\([^)]+\)/i'  => 'REAL',
        '/\bVARCHAR\s*\((\d+)\)/i'    => 'TEXT',
        '/\bCHAR\s*\((\d+)\)/i'       => 'TEXT',
        '/\bLONGTEXT\b/i'           => 'TEXT',
        '/\bMEDIUMTEXT\b/i'         => 'TEXT',
        '/\bTINYTEXT\b/i'           => 'TEXT',
        '/\bDATETIME\b/i'           => 'TEXT',
        '/\bTIMESTAMP\b/i'          => 'TEXT',
        '/\bDATE\b/i'               => 'TEXT',
        '/\bTIME\b/i'               => 'TEXT',
        '/\bLONGBLOB\b/i'           => 'BLOB',
        '/\bMEDIUMBLOB\b/i'         => 'BLOB',
        '/\bTINYBLOB\b/i'           => 'BLOB',
        '/\bENUM\s*\([^)]+\)/i'     => 'TEXT',
        '/\bSET\s*\([^)]+\)/i'      => 'TEXT',
        '/\bBIT\s*\((\d+)\)/i'        => 'INTEGER',
        '/\bYEAR\s*\((\d+)\)/i'       => 'INTEGER',
        '/\bBOOLEAN\b/i'            => 'INTEGER',
        '/\bBOOL\b/i'               => 'INTEGER',
    );

    public static function convert(string $mysqlCreate, string $table): string {
        $sql = self::removeEngineAttributes($mysqlCreate);
        $sql = self::convertAutoIncrement($sql);
        $sql = self::convertDataTypes($sql);
        $sql = self::removeInlineAttributes($sql);
        $sql = self::removeIndexDefinitions($sql);
        $sql = self::cleanTrailingCommas($sql);

        return $sql;
    }

    private static function removeEngineAttributes(string $sql): string {
        $sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+COLLATE\s*=?\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+AUTO_INCREMENT\s*=\s*\d+/i', '', $sql);
        $sql = preg_replace('/\s+ROW_FORMAT\s*=\s*\w+/i', '', $sql);

        return $sql;
    }

    private static function convertAutoIncrement(string $sql): string { return preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql); }
    private static function convertDataTypes(string $sql): string { foreach (self::$typeMap as $p => $r) { $sql = preg_replace($p, $r, $sql); } return $sql; }
    private static function removeInlineAttributes(string $sql): string { $sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql); $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql); $sql = preg_replace('/\s+UNSIGNED\b/i', '', $sql); return preg_replace('/\s+ZEROFILL\b/i', '', $sql); }
    private static function removeIndexDefinitions(string $sql): string { $sql = preg_replace('/,\s*KEY\s+[^,]+(?=,|\))/i', '', $sql); $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+[^,]+(?=,|\))/i', '', $sql); $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+[^,]+(?=,|\))/i', '', $sql); $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+[^,]+(?=,|\))/i', '', $sql); return $sql; }
    private static function cleanTrailingCommas(string $sql): string { return preg_replace('/,\s*\)/', ')', $sql); }
}
