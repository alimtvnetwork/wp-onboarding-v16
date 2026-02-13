<?php
/**
 * Riseup Asia Uploader - SQLite Schema Converter
 *
 * Shared utility for converting MySQL CREATE TABLE statements
 * to SQLite-compatible syntax. Used by both SnapshotWorker and
 * IncrementalBackup to eliminate code duplication.
 *
 * @package RiseupAsiaUploader
 * @since   1.19.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SQLite Schema Converter utility.
 *
 * Stateless converter: call SqliteSchemaConverter::convert() with
 * a MySQL CREATE TABLE statement and receive a SQLite-compatible version.
 */
class RiseupSqliteSchemaConverter {

    /**
     * MySQL-to-SQLite type mapping.
     *
     * @var array<string, string>
     */
    private static $typeMap = array(
        '/\bTINYINT\s*\(\d+\)/i'    => 'INTEGER',
        '/\bSMALLINT\s*\(\d+\)/i'   => 'INTEGER',
        '/\bMEDIUMINT\s*\(\d+\)/i'  => 'INTEGER',
        '/\bBIGINT\s*\(\d+\)/i'     => 'INTEGER',
        '/\bINT\s*\(\d+\)/i'        => 'INTEGER',
        '/\bDOUBLE\b/i'             => 'REAL',
        '/\bFLOAT\b/i'              => 'REAL',
        '/\bDECIMAL\s*\([^)]+\)/i'  => 'REAL',
        '/\bVARCHAR\s*\(\d+\)/i'    => 'TEXT',
        '/\bCHAR\s*\(\d+\)/i'       => 'TEXT',
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
        '/\bBIT\s*\(\d+\)/i'        => 'INTEGER',
        '/\bYEAR\s*\(\d+\)/i'       => 'INTEGER',
        '/\bBOOLEAN\b/i'            => 'INTEGER',
        '/\bBOOL\b/i'               => 'INTEGER',
    );

    /**
     * Convert a MySQL CREATE TABLE statement to SQLite syntax.
     *
     * @param string $mysqlCreate MySQL CREATE TABLE statement.
     * @param string $table       Table name (unused, reserved for future use).
     * @return string SQLite-compatible CREATE TABLE statement.
     */
    public static function convert(string $mysqlCreate, string $table): string {
        $sql = self::removeEngineAttributes($mysqlCreate);
        $sql = self::convertAutoIncrement($sql);
        $sql = self::convertDataTypes($sql);
        $sql = self::removeInlineAttributes($sql);
        $sql = self::removeIndexDefinitions($sql);
        $sql = self::cleanTrailingCommas($sql);

        return $sql;
    }

    /**
     * Remove MySQL engine-level attributes (ENGINE, CHARSET, etc.).
     *
     * @param string $sql SQL statement.
     * @return string Cleaned SQL.
     */
    private static function removeEngineAttributes(string $sql): string {
        $sql = preg_replace('/\s+ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+COLLATE\s*=?\s*\w+/i', '', $sql);
        $sql = preg_replace('/\s+AUTO_INCREMENT\s*=\s*\d+/i', '', $sql);
        $sql = preg_replace('/\s+ROW_FORMAT\s*=\s*\w+/i', '', $sql);

        return $sql;
    }

    /**
     * Convert AUTO_INCREMENT to SQLite AUTOINCREMENT.
     *
     * @param string $sql SQL statement.
     * @return string Converted SQL.
     */
    private static function convertAutoIncrement(string $sql): string {
        return preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);
    }

    /**
     * Convert MySQL data types to SQLite equivalents.
     *
     * @param string $sql SQL statement.
     * @return string Converted SQL.
     */
    private static function convertDataTypes(string $sql): string {
        foreach (self::$typeMap as $pattern => $replacement) {
            $sql = preg_replace($pattern, $replacement, $sql);
        }

        return $sql;
    }

    /**
     * Remove inline column attributes unsupported by SQLite.
     *
     * @param string $sql SQL statement.
     * @return string Cleaned SQL.
     */
    private static function removeInlineAttributes(string $sql): string {
        $sql = preg_replace('/\s+COLLATE\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s+UNSIGNED\b/i', '', $sql);
        $sql = preg_replace('/\s+ZEROFILL\b/i', '', $sql);

        return $sql;
    }

    /**
     * Remove KEY/INDEX definitions unsupported inline in SQLite.
     *
     * @param string $sql SQL statement.
     * @return string Cleaned SQL.
     */
    private static function removeIndexDefinitions(string $sql): string {
        $sql = preg_replace('/,\s*KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*FULLTEXT\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);
        $sql = preg_replace('/,\s*SPATIAL\s+KEY\s+[^,]+(?=,|\))/i', '', $sql);

        return $sql;
    }

    /**
     * Remove trailing commas before closing parenthesis.
     *
     * @param string $sql SQL statement.
     * @return string Cleaned SQL.
     */
    private static function cleanTrailingCommas(string $sql): string {
        return preg_replace('/,\s*\)/', ')', $sql);
    }
}
