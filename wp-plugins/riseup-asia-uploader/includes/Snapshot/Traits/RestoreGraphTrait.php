<?php
/**
 * Restore Graph Trait
 *
 * Dependency graph construction, topological sort, table inventory, and metadata.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.15.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;

trait RestoreGraphTrait {

    private function getSnapshotMeta(PDO $rootPdo): array {
        $row = $rootPdo->query("SELECT * FROM snapshot_meta WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        return $row ?: array();
    }

    private function getTableInventory(PDO $rootPdo): array {
        $rows = $rootPdo->query(
            "SELECT table_name, sqlite_file, row_count, checksum_md5 FROM snapshot_tables ORDER BY table_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $inventory = array();
        foreach ($rows as $row) {
            $inventory[$row['table_name']] = array(
                'sqlite_file'  => $row['sqlite_file'],
                'row_count'    => (int) $row['row_count'],
                'checksum_md5' => $row['checksum_md5'],
            );
        }

        return $inventory;
    }

    private function getRestoreOrder(PDO $rootPdo, array $tableInventory): array {
        $all_tables = array_keys($tableInventory);

        $deps = $rootPdo->query(
            "SELECT parent_table, child_table FROM table_dependencies"
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

            if (!isset($graph[$parent]) || !isset($graph[$child])) {
                continue;
            }

            $graph[$parent][] = $child;
            $in_degree[$child]++;
        }

        return array('adjacency' => $graph, 'in_degree' => $in_degree);
    }

    private function topologicalSort(array $graph, array $inDegree, array $allTables): array {
        $queue = array();
        foreach ($inDegree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }

        $sorted = array();
        while (!empty($queue)) {
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
            if (RiseupBooleanHelpers::isNotInList($t, $sorted)) {
                $sorted[] = $t;
            }
        }

        return $sorted;
    }
}
