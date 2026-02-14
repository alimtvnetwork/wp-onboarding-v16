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

    public function query_transactions(array $filters = array(), int $limit = self::DEFAULT_LIMIT, int $offset = 0): array {
        if (!$this->is_ready()) {
            $this->fileLogger->warn('Database not ready for query');
            return array('total' => 0, 'logs' => array());
        }

        $limit = min(max(1, $limit), self::MAX_LIMIT);
        $offset = max(0, $offset);

        try {
            return $this->executeTransactionQuery($filters, $limit, $offset);
        } catch (Exception $e) {
            $this->fileLogger->logException($e, 'Failed to query transactions');
            return array('total' => 0, 'logs' => array());
        }
    }

    private function executeTransactionQuery(array $filters, int $limit, int $offset): array {
        $this->fileLogger->debug('Querying transactions', array('filters' => $filters));

        $countQuery = RiseupORM::for_table(self::TABLE_TRANSACTIONS);
        $dataQuery = RiseupORM::for_table(self::TABLE_TRANSACTIONS);

        $this->apply_filters($countQuery, $filters);
        $this->apply_filters($dataQuery, $filters);

        $total = $countQuery->count();
        $logs = $dataQuery->order_by_desc('created_at')->limit($limit)->offset($offset)->find_many();

        $this->decodeLogDetails($logs);
        $this->fileLogger->debug('Query complete', array('total' => $total, 'returned' => count($logs)));

        return array('total' => $total, 'logs' => $logs);
    }

    private function decodeLogDetails(array &$logs): void {
        foreach ($logs as &$log) {
            if (!empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }
        }
    }

    private function apply_filters($query, array $filters): void {
        $this->applyEqualityFilters($query, $filters);
        $this->applyDateRangeFilters($query, $filters);
        $this->applyTextFilters($query, $filters);
    }

    private function applyEqualityFilters($query, array $filters): void {
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

    private function applyDateRangeFilters($query, array $filters): void {
        if (!empty($filters['from'])) {
            $query->where_gte('created_at', $filters['from'] . 'T00:00:00Z');
        }
        if (!empty($filters['to'])) {
            $query->where_lte('created_at', $filters['to'] . 'T23:59:59Z');
        }
    }

    private function applyTextFilters($query, array $filters): void {
        if (!empty($filters['source_machine'])) {
            $query->where_like('source_machine', '%' . $filters['source_machine'] . '%');
        }
    }

    public function get_stats(): array {
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
            $this->fileLogger->logException($e, 'Failed to get stats');
            return array();
        }
    }

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

    public function cleanup_old_transactions(int $daysToKeep = 365): int {
        if (!$this->is_ready()) {
            return 0;
        }

        try {
            $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - ($daysToKeep * 86400));

            $deleted = RiseupORM::for_table(self::TABLE_TRANSACTIONS)
                ->where_lt('created_at', $cutoff)
                ->delete();

            $this->fileLogger->info('Cleanup complete', array('deleted' => $deleted));
            return $deleted;
        } catch (Exception $e) {
            $this->fileLogger->logException($e, 'Failed to cleanup transactions');
            return 0;
        }
    }
}
