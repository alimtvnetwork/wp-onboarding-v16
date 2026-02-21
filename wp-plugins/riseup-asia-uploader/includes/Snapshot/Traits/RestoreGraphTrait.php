<?php
/**
 * Restore Graph Trait
 *
 * Dependency graph construction, topological sort, table inventory, and metadata.
 * Supports both old snake_case and new PascalCase root DB schemas.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.15.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RiseupAsia\Helpers\BooleanHelpers;

trait RestoreGraphTrait {

    use RootDbCompatTrait;

    private function getSnapshotMeta(PDO $rootPdo): array {
        $table = $this->resolveRootTable($rootPdo, 'SnapshotMeta', 'snapshot_meta');
        $idCol = $this->resolveRootCol($rootPdo, $table, 'Id', 'id');
        $row = $rootPdo->query("SELECT * FROM {$table} WHERE {$idCol} = 1")->fetch(PDO::FETCH_ASSOC);

        return $row ?: array();
    }

    private function getTableInventory(PDO $rootPdo): array {
        $table = $this->resolveRootTable($rootPdo, 'SnapshotTables', 'snapshot_tables');
        $tableNameCol = $this->resolveRootCol($rootPdo, $table, 'TableName', 'table_name');
        $sqliteFileCol = $this->resolveRootCol($rootPdo, $table, 'SqliteFile', 'sqlite_file');
        $rowCountCol = $this->resolveRootCol($rootPdo, $table, 'RowCount', 'row_count');
        $checksumCol = $this->resolveRootCol($rootPdo, $table, 'ChecksumMd5', 'checksum_md5');

        $rows = $rootPdo->query(
            "SELECT {$tableNameCol}, {$sqliteFileCol}, {$rowCountCol}, {$checksumCol} FROM {$table} ORDER BY {$tableNameCol}"
        )->fetchAll(PDO::FETCH_ASSOC);

        $inventory = array();
        foreach ($rows as $row) {
            $name = $row[$tableNameCol];
            $inventory[$name] = array(
                'sqlite_file'  => $row[$sqliteFileCol],
                'row_count'    => (int) $row[$rowCountCol],
                'checksum_md5' => $row[$checksumCol],
            );
        }

        return $inventory;
    }

    private function getRestoreOrder(PDO $rootPdo, array $tableInventory): array {
        $all_tables = array_keys($tableInventory);

        $depsTable = $this->resolveRootTable($rootPdo, 'TableDependencies', 'table_dependencies');
        $parentCol = $this->resolveRootCol($rootPdo, $depsTable, 'ParentTable', 'parent_table');
        $childCol = $this->resolveRootCol($rootPdo, $depsTable, 'ChildTable', 'child_table');

        $deps = $rootPdo->query(
            "SELECT {$parentCol} AS parent_table, {$childCol} AS child_table FROM {$depsTable}"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($deps)) {
            sort($all_tables);

            return $all_tables;
        }

        $graph = $this->buildDependencyGraph($all_tables, $deps);

        return $this->topologicalSort($graph['adjacency'], $graph['in_degree'], $all_tables);
    }

    private function buildDependencyGraph(array $allTables, array $deps): array {
        $graph = array();
        $in_degree = array();

        foreach ($allTables as $t) {
            $graph[$t] = array();
            $in_degree[$t] = 0;
        }

        foreach ($deps as $dep) {
            $parent = $dep['parent_table'];
            $child = $dep['child_table'];

            $isParentOrChildMissing = (BooleanHelpers::isKeyMissing($graph, $parent) || BooleanHelpers::isKeyMissing($graph, $child));
            if ($isParentOrChildMissing) {
                continue;
            }

            $graph[$parent][] = $child;
            $in_degree[$child]++;
        }

        return array('adjacency' => $graph, 'in_degree' => $in_degree);
    }

    private function topologicalSort(
        array $graph,
        array $inDegree,
        array $allTables,
    ): array {
        $queue = array();
        foreach ($inDegree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }

        $sorted = array();
        while (BooleanHelpers::hasValue($queue)) {
            sort($queue);
            $table = array_shift($queue);
            $sorted[] = $table;

            foreach ($graph[$table] as $child) {
                $inDegree[$child]--;
                if ($inDegree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }

        foreach ($allTables as $t) {
            if (BooleanHelpers::isAbsentFromList($t, $sorted)) {
                $sorted[] = $t;
            }
        }

        return $sorted;
    }
}
