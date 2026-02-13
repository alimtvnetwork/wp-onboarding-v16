<?php
/**
 * DatabaseQuerySearchTrait — Transaction querying, filtering, stats, and cleanup.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseQuerySearchTrait {

    /**
     * Query transactions with filtering and pagination using ORM.
     *
     * @param array $filters Filters.
     * @param int   $limit   Number of records.
     * @param int   $offset  Offset.
     * @return array Array with 'total' and 'logs'.
     */
    public function query_transactions($filters = array(), $limit = self::DEFAULT_LIMIT, $offset = 0) {
        if (!$this->is_ready()) {
            $this->file_logger->warn('Database not ready for query');
            return array('total' => 0, 'logs' => array());
        }

        $limit = min(max(1, (int) $limit), self::MAX_LIMIT);
        $offset = max(0, (int) $offset);

        try {
            $this->file_logger->debug('Querying transactions', array('filters' => $filters));

            $count_query = RiseupORM::for_table(self::TABLE_TRANSACTIONS);
            $data_query = RiseupORM::for_table(self::TABLE_TRANSACTIONS);

            $this->apply_filters($count_query, $filters);
            $this->apply_filters($data_query, $filters);

            $total = $count_query->count();
            $logs = $data_query
                ->order_by_desc('created_at')
                ->limit($limit)
                ->offset($offset)
                ->find_many();

            $this->decodeLogDetails($logs);
            $this->file_logger->debug('Query complete', array('total' => $total, 'returned' => count($logs)));

            return array('total' => $total, 'logs' => $logs);
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to query transactions');
            return array('total' => 0, 'logs' => array());
        }
    }

    /** Decode JSON details in log records. */
    private function decodeLogDetails(array &$logs) {
        foreach ($logs as &$log) {
            if (!empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }
        }
    }

    /** Apply filters to an ORM query. */
    private function apply_filters($query, $filters) {
        $this->applyEqualityFilters($query, $filters);
        $this->applyDateRangeFilters($query, $filters);
        $this->applyTextFilters($query, $filters);
    }

    /** Apply equality-based filters. */
    private function applyEqualityFilters($query, array $filters) {
        if (!empty($filters['plugin'])) {
            $query->where('plugin_slug', $filters['plugin']);
        }
        if (!empty($filters['action'])) {
            $actions = array_map('trim', explode(',', $filters['action']));
            if (count($actions) === 1) {
                $query->where('action', $actions[0]);
            } else {
                $query->where_in('action', $actions);
            }
        }
        if (!empty($filters['user'])) {
            $query->where('user_login', $filters['user']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['triggered_by'])) {
            $query->where('triggered_by', $filters['triggered_by']);
        }
        if (!empty($filters['upload_source'])) {
            $query->where('upload_source', $filters['upload_source']);
        }
    }

    /** Apply date range filters. */
    private function applyDateRangeFilters($query, array $filters) {
        if (!empty($filters['from'])) {
            $query->where_gte('created_at', $filters['from'] . 'T00:00:00Z');
        }
        if (!empty($filters['to'])) {
            $query->where_lte('created_at', $filters['to'] . 'T23:59:59Z');
        }
    }

    /** Apply text search filters. */
    private function applyTextFilters($query, array $filters) {
        if (!empty($filters['source_machine'])) {
            $query->where_like('source_machine', '%' . $filters['source_machine'] . '%');
        }
    }

    /**
     * Get statistics summary.
     */
    public function get_stats() {
        if (!$this->is_ready()) {
            return array();
        }

        try {
            return array(
                'total_transactions' => RiseupORM::for_table(self::TABLE_TRANSACTIONS)->count(),
                'by_action'          => $this->countByColumn('action'),
                'by_status'          => $this->countByColumn('status'),
                'last_24h'           => RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                    ->where_gte('created_at', gmdate('Y-m-d\TH:i:s\Z', time() - 86400))
                    ->count(),
            );
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to get stats');
            return array();
        }
    }

    /** Count rows grouped by a column. */
    private function countByColumn(string $column): array {
        $rows = RiseupORM::raw_execute(
            "SELECT {$column}, COUNT(*) as count FROM " . self::TABLE_TRANSACTIONS . " GROUP BY {$column}"
        );
        $result = array();
        foreach ($rows as $row) {
            $result[$row[$column]] = (int) $row['count'];
        }
        return $result;
    }

    /**
     * Cleanup old transactions.
     *
     * @param int $days_to_keep Number of days to keep.
     * @return int Number of deleted records.
     */
    public function cleanup_old_transactions($days_to_keep = 365) {
        if (!$this->is_ready()) {
            return 0;
        }

        try {
            $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - ($days_to_keep * 86400));

            $deleted = RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                ->where_lt('created_at', $cutoff)
                ->delete();

            $this->file_logger->info('Cleanup complete', array('deleted' => $deleted));
            return $deleted;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to cleanup transactions');
            return 0;
        }
    }
}
