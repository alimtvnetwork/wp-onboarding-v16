<?php
/**
 * AnalyzerQueryTrait — table listing, FK detection, analysis, and logging.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Helpers\BooleanHelpers;

trait AnalyzerQueryTrait {
    /**
     * Get all tables in the current database.
     *
     * @param string $scope Filter scope matching SnapshotScopeType values.
     * @return array Table names.
     */
    public function getTables($scope = 'all') { // Default matches SnapshotScopeType::All->value
        $prefix = $this->wpdb->prefix;

        $tables = $this->wpdb->get_col("SHOW TABLES");

        $resolvedScope = SnapshotScopeType::tryFrom($scope);
        $isWordPress = ($resolvedScope !== null && $resolvedScope->isWordPress());
        if ($isWordPress) {
            $tables = array_filter($tables, function($t) use ($prefix) {
                return strpos($t, $prefix) === 0;
            });
        }

        $isContent = ($resolvedScope !== null && $resolvedScope->isContent());
        if ($isContent) {
            $content_suffixes = array(
                'posts',
                'postmeta',
                'terms',
                'term_taxonomy',
                'term_relationships',
                'comments',
                'commentmeta',
                'options',
                'users',
                'usermeta',
            );
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
     * @return array Dependency edges.
     */
    public function detectDependencies() {
        $db_name = $this->wpdb->dbname;

        $sql = $this->wpdb->prepare(
            "SELECT TABLE_NAME AS child_table, COLUMN_NAME AS fk_column,
                    REFERENCED_TABLE_NAME AS parent_table, REFERENCED_COLUMN_NAME AS ref_column
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = %s AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, COLUMN_NAME",
            $db_name
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if (empty($rows)) {
            $this->log(LogLevelType::Info->value, 'No foreign key dependencies detected', array('database' => $db_name));

            return array();
        }

        $deps = array();
        foreach ($rows as $row) {
            $deps[] = array(
                ResponseKeyType::ParentTable->value => $row['parent_table'],
                ResponseKeyType::ChildTable->value  => $row['child_table'],
                ResponseKeyType::FkColumn->value    => $row['fk_column'],
                ResponseKeyType::RefColumn->value   => $row['ref_column'],
            );
        }

        $this->log(LogLevelType::Info->value, 'Dependencies detected', array('count' => count($deps), 'database' => $db_name));

        return $deps;
    }

    /**
     * Get the full dependency analysis result.
     *
     * @param string $scope Table scope.
     * @return array Analysis result.
     */
    public function analyze($scope = 'all') { // Default matches SnapshotScopeType::All->value
        $tables = $this->getTables($scope);
        $dependencies = $this->detectDependencies();

        $table_set = array_flip($tables);
        $scoped_deps = array_filter($dependencies, function($dep) use ($table_set) {
            return isset($table_set[$dep[ResponseKeyType::ParentTable->value]]) && isset($table_set[$dep[ResponseKeyType::ChildTable->value]]);
        });
        $scoped_deps = array_values($scoped_deps);

        $sorted = $this->topologicalSort($tables, $scoped_deps);

        return array(
            'tables'       => $tables,
            'dependencies' => $scoped_deps,
            ResponseKeyType::SeedOrder->value  => $sorted,
            ResponseKeyType::TableCount->value => count($tables),
            ResponseKeyType::DepCount->value   => count($scoped_deps),
        );
    }

    /**
     * Log a message with analyzer context.
     */
    private function log(
        $level,
        $message,
        $context = array(),
    ) {
        $full = '[SNAPSHOT] [DEPENDENCY] ' . $message;
        if (BooleanHelpers::hasValue($context)) {
            $full .= ' ' . json_encode($context);
        }

        $isLoggerMissing = ($this->logger === null);

        if ($isLoggerMissing) {
            return;
        }

        switch ($level) {
            case LogLevelType::Warn->value:  $this->logger->warn($full); break;
            case LogLevelType::Error->value: $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
