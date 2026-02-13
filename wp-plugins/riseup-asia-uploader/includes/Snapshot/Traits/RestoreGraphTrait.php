<?php
/**
 * Restore Graph Trait
 *
 * Dependency graph construction, topological sort, table inventory, and metadata.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait RestoreGraphTrait {

    /**
     * Get snapshot metadata from a-root.db.
     *
     * @param PDO $rootPdo a-root.db PDO.
     * @return array Metadata.
     */
    private function getSnapshotMeta($rootPdo) {
        $row = $rootPdo->query("SELECT * FROM snapshot_meta WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        return $row ?: array();
    }

    /**
     * Get table inventory from a-root.db.
     *
     * @param PDO $rootPdo a-root.db PDO.
     * @return array Map of table_name => { sqlite_file, row_count, checksum_md5 }.
     */
    private function getTableInventory($rootPdo) {
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

    /**
     * Determine the restore order using the dependency graph (topological sort).
     *
     * @param PDO   $rootPdo        a-root.db PDO.
     * @param array $table_inventory Table inventory map.
     * @return array Ordered list of table names.
     */
    private function getRestoreOrder($rootPdo, $table_inventory) {
        $all_tables = array_keys($table_inventory);

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

    /**
     * Build an adjacency list and in-degree map from dependencies.
     *
     * @param array $allTables All table names.
     * @param array $deps      Dependency records.
     * @return array Graph with adjacency and in_degree.
     */
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

    /**
     * Perform Kahn's topological sort.
     *
     * @param array $graph    Adjacency list.
     * @param array $inDegree In-degree map.
     * @param array $allTables All table names.
     * @return array Sorted table names.
     */
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
            if (!in_array($t, $sorted)) {
                $sorted[] = $t;
            }
        }

        return $sorted;
    }
}
