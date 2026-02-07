<?php
/**
 * Riseup Asia Uploader - Dependency Analyzer
 *
 * Detects foreign key relationships between MySQL tables using
 * INFORMATION_SCHEMA and produces a topologically sorted seed order.
 *
 * @package RiseupAsiaUploader
 * @since   1.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dependency Analyzer class.
 *
 * Queries INFORMATION_SCHEMA.KEY_COLUMN_USAGE to build a directed graph
 * of table dependencies and returns a topologically sorted order for
 * safe restore/seed operations.
 */
class RiseupDependencyAnalyzer {

    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Logger instance.
     *
     * @var RiseupFileLogger
     */
    private $logger;

    /**
     * Singleton instance.
     *
     * @var RiseupDependencyAnalyzer|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger|null $logger Logger instance.
     * @return RiseupDependencyAnalyzer
     */
    public static function getInstance($logger = null) {
        if (self::$instance === null && $logger) {
            self::$instance = new self($logger);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger instance.
     */
    private function __construct($logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = $logger;
    }

    /**
     * Get all tables in the current database.
     *
     * @param string $scope Filter: 'all', 'wordpress', 'content'.
     * @return array Table names.
     */
    public function getTables($scope = 'all') {
        $prefix = $this->wpdb->prefix;
        $db_name = $this->wpdb->dbname;

        $tables = $this->wpdb->get_col("SHOW TABLES");

        if ($scope === 'wordpress') {
            // Only tables with the WP prefix
            $tables = array_filter($tables, function($t) use ($prefix) {
                return strpos($t, $prefix) === 0;
            });
        } elseif ($scope === 'content') {
            // Core content tables only
            $content_suffixes = array('posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'comments', 'commentmeta', 'options', 'users', 'usermeta');
            $content_tables = array_map(function($s) use ($prefix) {
                return $prefix . $s;
            }, $content_suffixes);
            $tables = array_intersect($tables, $content_tables);
        }

        return array_values($tables);
    }

    /**
     * Detect foreign key relationships from INFORMATION_SCHEMA.
     *
     * @return array Array of dependency edges: [parent_table, child_table, fk_column, ref_column].
     */
    public function detectDependencies() {
        $db_name = $this->wpdb->dbname;

        $sql = $this->wpdb->prepare(
            "SELECT
                TABLE_NAME AS child_table,
                COLUMN_NAME AS fk_column,
                REFERENCED_TABLE_NAME AS parent_table,
                REFERENCED_COLUMN_NAME AS ref_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = %s
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, COLUMN_NAME",
            $db_name
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if (empty($rows)) {
            $this->log('INFO', 'No foreign key dependencies detected', array('database' => $db_name));
            return array();
        }

        $deps = array();
        foreach ($rows as $row) {
            $deps[] = array(
                'parent_table' => $row['parent_table'],
                'child_table'  => $row['child_table'],
                'fk_column'    => $row['fk_column'],
                'ref_column'   => $row['ref_column'],
            );
        }

        $this->log('INFO', 'Dependencies detected', array(
            'count'    => count($deps),
            'database' => $db_name,
        ));

        return $deps;
    }

    /**
     * Build adjacency list from dependency edges.
     *
     * @param array $dependencies Dependency edges from detectDependencies().
     * @param array $all_tables   All table names to include (even orphans).
     * @return array Adjacency list: [table => [dependent tables]].
     */
    private function buildAdjacencyList($dependencies, $all_tables) {
        $graph = array();
        $in_degree = array();

        // Initialize all tables
        foreach ($all_tables as $table) {
            $graph[$table] = array();
            $in_degree[$table] = 0;
        }

        // Add edges: parent → child (parent must be seeded first)
        foreach ($dependencies as $dep) {
            $parent = $dep['parent_table'];
            $child = $dep['child_table'];

            // Skip self-references
            if ($parent === $child) {
                continue;
            }

            // Only add if both tables are in our set
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
     * Returns tables in dependency order (parents first, children last).
     * Detects cycles and logs a warning if found.
     *
     * @param array $all_tables   All table names.
     * @param array $dependencies Dependency edges.
     * @return array Sorted table names.
     */
    public function topologicalSort($all_tables, $dependencies) {
        $adj = $this->buildAdjacencyList($dependencies, $all_tables);
        $graph = $adj['graph'];
        $in_degree = $adj['in_degree'];

        // Queue starts with all tables that have no incoming edges
        $queue = array();
        foreach ($in_degree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }

        // Sort the initial queue for deterministic output
        sort($queue);

        $sorted = array();
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            if (isset($graph[$current])) {
                // Sort neighbors for deterministic output
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

        // Detect cycles
        if (count($sorted) < count($all_tables)) {
            $cycled = array_diff($all_tables, $sorted);
            $this->log('WARN', 'Cycle detected in table dependencies', array(
                'cycled_tables' => array_values($cycled),
                'sorted_count'  => count($sorted),
                'total_count'   => count($all_tables),
            ));

            // Append cycled tables at the end (best effort)
            foreach ($cycled as $table) {
                $sorted[] = $table;
            }
        }

        $this->log('INFO', 'Topological sort complete', array(
            'table_count' => count($sorted),
        ));

        return $sorted;
    }

    /**
     * Get the full dependency analysis result.
     *
     * @param string $scope Table scope: 'all', 'wordpress', 'content'.
     * @return array Analysis result with tables, dependencies, and sorted order.
     */
    public function analyze($scope = 'all') {
        $tables = $this->getTables($scope);
        $dependencies = $this->detectDependencies();

        // Filter dependencies to only include tables in scope
        $table_set = array_flip($tables);
        $scoped_deps = array_filter($dependencies, function($dep) use ($table_set) {
            return isset($table_set[$dep['parent_table']]) && isset($table_set[$dep['child_table']]);
        });
        $scoped_deps = array_values($scoped_deps);

        $sorted = $this->topologicalSort($tables, $scoped_deps);

        return array(
            'tables'       => $tables,
            'dependencies' => $scoped_deps,
            'seed_order'   => $sorted,
            'table_count'  => count($tables),
            'dep_count'    => count($scoped_deps),
        );
    }

    /**
     * Log a message with analyzer context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [DEPENDENCY]';
        $full = $prefix . ' ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }

        if ($this->logger) {
            switch ($level) {
                case 'WARN':
                    $this->logger->warn($full);
                    break;
                case 'ERROR':
                    $this->logger->error($full);
                    break;
                default:
                    $this->logger->info($full);
            }
        }
    }
}
