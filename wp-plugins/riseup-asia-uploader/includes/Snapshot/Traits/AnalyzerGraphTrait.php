<?php
/**
 * AnalyzerGraphTrait — adjacency list building and topological sort.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Helpers\BooleanHelpers;

trait AnalyzerGraphTrait {

    /** Build adjacency list from dependency edges. */
    private function buildAdjacencyList(array $dependencies, array $allTables): array {
        $graph = array();
        $in_degree = array();

        foreach ($allTables as $table) {
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

    /** Topological sort using Kahn's algorithm. */
    public function topologicalSort(array $allTables, array $dependencies): array {
        $adj = $this->buildAdjacencyList($dependencies, $allTables);
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
        while (BooleanHelpers::hasValue($queue)) {
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

        if (count($sorted) < count($allTables)) {
            $cycled = array_diff($allTables, $sorted);
            $this->log(LogLevelType::Warn->value, 'Cycle detected in table dependencies', array(
                'cycled_tables' => array_values($cycled),
                'sorted_count'  => count($sorted),
                'total_count'   => count($allTables),
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
