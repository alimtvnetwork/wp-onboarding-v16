<?php
/**
 * AnalyzerGraphTrait — adjacency list building and topological sort.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait AnalyzerGraphTrait {

    /**
     * Build adjacency list from dependency edges.
     *
     * @param array $dependencies Dependency edges.
     * @param array $all_tables   All table names.
     * @return array Adjacency list and in-degree map.
     */
    private function buildAdjacencyList($dependencies, $all_tables) {
        $graph = array();
        $in_degree = array();

        foreach ($all_tables as $table) {
            $graph[$table] = array();
            $in_degree[$table] = 0;
        }

        foreach ($dependencies as $dep) {
            $parent = $dep['parent_table'];
            $child = $dep['child_table'];

            if ($parent === $child) {
                continue;
            }

            if (isset($graph[$parent]) && isset($graph[$child])) {
                $graph[$parent][] = $child;
                $in_degree[$child]++;
            }
        }

        return array('graph' => $graph, 'in_degree' => $in_degree);
    }

    /**
     * Topological sort using Kahn's algorithm.
     *
     * @param array $all_tables   All table names.
     * @param array $dependencies Dependency edges.
     * @return array Sorted table names.
     */
    public function topologicalSort($all_tables, $dependencies) {
        $adj = $this->buildAdjacencyList($dependencies, $all_tables);
        $graph = $adj['graph'];
        $in_degree = $adj['in_degree'];

        $queue = array();
        foreach ($in_degree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }

        sort($queue);

        $sorted = array();
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            if (isset($graph[$current])) {
                $neighbors = $graph[$current];
                sort($neighbors);
                foreach ($neighbors as $neighbor) {
                    $in_degree[$neighbor]--;
                    if ($in_degree[$neighbor] === 0) {
                        $queue[] = $neighbor;
                    }
                }
            }
        }

        if (count($sorted) < count($all_tables)) {
            $cycled = array_diff($all_tables, $sorted);
            $this->log(LogLevelType::Warn->value, 'Cycle detected in table dependencies', array(
                'cycled_tables' => array_values($cycled),
                'sorted_count'  => count($sorted),
                'total_count'   => count($all_tables),
            ));

            foreach ($cycled as $table) {
                $sorted[] = $table;
            }
        }

        $this->log(LogLevelType::Info->value, 'Topological sort complete', array(
            'table_count' => count($sorted),
        ));

        return $sorted;
    }
}
