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
        $inDegree = array();

        foreach ($allTables as $table) {
            $graph[$table] = array();
            $inDegree[$table] = 0;
        }

        foreach ($dependencies as $dep) {
            $parent = $dep['parent_table'];
            $child = $dep['child_table'];

            if ($parent === $child) {
                continue;
            }

            if (isset($graph[$parent]) && isset($graph[$child])) {
                $graph[$parent][] = $child;
                $inDegree[$child]++;
            }
        }

        return array('graph' => $graph, 'inDegree' => $inDegree);
    }

    /** Topological sort using Kahn's algorithm. */
    public function topologicalSort(array $allTables, array $dependencies): array {
        $adj = $this->buildAdjacencyList($dependencies, $allTables);
        $graph = $adj['graph'];
        $inDegree = $adj['inDegree'];

        $queue = array();
        foreach ($inDegree as $table => $degree) {
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
                    $inDegree[$neighbor]--;
                    if ($inDegree[$neighbor] === 0) {
                        $queue[] = $neighbor;
                    }
                }
            }
        }

        if (count($sorted) < count($allTables)) {
            $cycled = array_diff($allTables, $sorted);
            $this->log(LogLevelType::Warn->value, 'Cycle detected in table dependencies', array(
                'cycledTables' => array_values($cycled),
                'sortedCount'  => count($sorted),
                'totalCount'   => count($allTables),
            ));

            foreach ($cycled as $table) {
                $sorted[] = $table;
            }
        }

        $this->log(LogLevelType::Info->value, 'Topological sort complete', array(
            'tableCount' => count($sorted),
        ));

        return $sorted;
    }
}
