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
                'sqliteFile'  => $row[$sqliteFileCol],
                'rowCount'    => (int) $row[$rowCountCol],
                'checksumMd5' => $row[$checksumCol],
            );
        }

        return $inventory;
    }

    private function getRestoreOrder(PDO $rootPdo, array $tableInventory): array {
        $allTables = array_keys($tableInventory);

        $depsTable = $this->resolveRootTable($rootPdo, 'TableDependencies', 'table_dependencies');
        $parentCol = $this->resolveRootCol($rootPdo, $depsTable, 'ParentTable', 'parent_table');
        $childCol = $this->resolveRootCol($rootPdo, $depsTable, 'ChildTable', 'child_table');

        $deps = $rootPdo->query(
            "SELECT {$parentCol} AS parentTable, {$childCol} AS childTable FROM {$depsTable}"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($deps)) {
            sort($allTables);

            return $allTables;
        }

        $graph = $this->buildDependencyGraph($allTables, $deps);

        return $this->topologicalSort($graph['adjacency'], $graph['inDegree'], $allTables);
    }

    private function buildDependencyGraph(array $allTables, array $deps): array {
        $graph = array();
        $inDegree = array();

        foreach ($allTables as $t) {
            $graph[$t] = array();
            $inDegree[$t] = 0;
        }

        foreach ($deps as $dep) {
            $parent = $dep['parentTable'];
            $child = $dep['childTable'];

            $isParentOrChildMissing = (BooleanHelpers::isKeyMissing($graph, $parent) || BooleanHelpers::isKeyMissing($graph, $child));

            if ($isParentOrChildMissing) {
                continue;
            }

            $graph[$parent][] = $child;
            $inDegree[$child]++;
        }

        return array(
            'adjacency' => $graph,
            'inDegree'  => $inDegree,
        );
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
