<?php
/**
 * RootDbCompatTrait — Backward-compatible table/column name resolution for root DB.
 *
 * Shared by all traits that read from a-root.db files which may use
 * either old snake_case or new PascalCase naming.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.1.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;

trait RootDbCompatTrait {

    /** Resolve table name: try PascalCase first, fall back to snake_case. */
    private function resolveRootTable(PDO $pdo, string $pascal, string $legacy): string {
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$pascal}'");
        if ($check->fetch() !== false) {

            return $pascal;
        }

        return $legacy;
    }

    /** Resolve column name: check PRAGMA for PascalCase, fall back to legacy. */
    private function resolveRootCol(PDO $pdo, string $table, string $pascal, string $legacy): string {
        static $columnCache = array();
        $cacheKey = $table;

        $isCacheMissing = !isset($columnCache[$cacheKey]);

        if ($isCacheMissing) {
            $columnCache[$cacheKey] = array();
            $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                $columnCache[$cacheKey][$col['name']] = true;
            }
        }

        if (isset($columnCache[$cacheKey][$pascal])) {

            return $pascal;
        }

        return $legacy;
    }
}