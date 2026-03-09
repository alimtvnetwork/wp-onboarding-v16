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

trait AnalyzerQueryTrait {
    /**
     * Get all tables in the current database.
     *
     * @param string $scope Filter scope matching SnapshotScopeType values.
     * @return array Table names.
     */
    public function getTables($scope = 'all') { // Default matches SnapshotScopeType::All->value
        $tables = $this->wpdb->get_col("SHOW TABLES");
        $resolvedScope = SnapshotScopeType::tryFrom($scope);

        return array_values($this->filterByScope($tables, $resolvedScope));
    }

    private function filterByScope(array $tables, ?SnapshotScopeType $scope): array {
        $isWordPress = ($scope !== null && $scope->isWordPress());

        if ($isWordPress) {
            return $this->filterWordPressTables($tables);
        }

        $isContent = ($scope !== null && $scope->isContent());

        if ($isContent) {
            return $this->filterContentTables($tables);
        }

        return $tables;
    }

    private function filterWordPressTables(array $tables): array {
        $prefix = $this->wpdb->prefix;

        return array_filter($tables, function($t) use ($prefix) {
            $isWpTable = (strpos($t, $prefix) === 0);

            return $isWpTable;
        });
    }

    private function filterContentTables(array $tables): array {
        $prefix = $this->wpdb->prefix;
        $contentSuffixes = array(
            'posts', 'postmeta', 'terms', 'term_taxonomy',
            'term_relationships', 'comments', 'commentmeta',
            'options', 'users', 'usermeta',
        );

        $contentTables = array_map(function($s) use ($prefix) {
            return $prefix . $s;
        }, $contentSuffixes);

        return array_intersect($tables, $contentTables);
    }

    /**
     * Detect foreign key relationships from INFORMATION_SCHEMA.
     *
     * @return array Dependency edges.
     */
    public function detectDependencies() {
        $dbName = $this->wpdb->dbname;
        $rows = $this->queryForeignKeys($dbName);

        if (empty($rows)) {
            $this->log(LogLevelType::Info->value, 'No foreign key dependencies detected', array('database' => $dbName));

            return array();
        }

        $deps = $this->mapDependencyRows($rows);
        $this->log(LogLevelType::Info->value, 'Dependencies detected', array('count' => count($deps), 'database' => $dbName));

        return $deps;
    }

    private function queryForeignKeys(string $dbName): array {
        $sql = $this->wpdb->prepare(
            "SELECT TABLE_NAME AS child_table, COLUMN_NAME AS fk_column,
                    REFERENCED_TABLE_NAME AS parent_table, REFERENCED_COLUMN_NAME AS ref_column
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = %s AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, COLUMN_NAME",
            $dbName,
        );

        return $this->wpdb->get_results($sql, ARRAY_A) ?: array();
    }

    private function mapDependencyRows(array $rows): array {
        $deps = array();

        foreach ($rows as $row) {
            $deps[] = array(
                ResponseKeyType::ParentTable->value => $row['parent_table'],
                ResponseKeyType::ChildTable->value  => $row['child_table'],
                ResponseKeyType::FkColumn->value    => $row['fk_column'],
                ResponseKeyType::RefColumn->value   => $row['ref_column'],
            );
        }

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

        $tableSet = array_flip($tables);
        $scopedDeps = array_filter($dependencies, function($dep) use ($tableSet) {
            return isset($tableSet[$dep[ResponseKeyType::ParentTable->value]]) && isset($tableSet[$dep[ResponseKeyType::ChildTable->value]]);
        });
        $scopedDeps = array_values($scopedDeps);

        $sorted = $this->topologicalSort($tables, $scopedDeps);

        return array(
            ResponseKeyType::Tables->value       => $tables,
            ResponseKeyType::Dependencies->value  => $scopedDeps,
            ResponseKeyType::SeedOrder->value     => $sorted,
            ResponseKeyType::TableCount->value    => count($tables),
            ResponseKeyType::DepCount->value      => count($scopedDeps),
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
        if (!empty($context)) {
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
