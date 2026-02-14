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

use RiseupAsia\Enums\TableType;

trait DatabaseQuerySearchTrait {

    public function queryTransactions(array $filters = array(), int $limit = self::DEFAULT_LIMIT, int $offset = 0): array {
        if (!$this->isReady()) {
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

        $countQuery = RiseupORM::forTable(TableType::Transactions->value);
        $dataQuery = RiseupORM::forTable(TableType::Transactions->value);

        $this->applyFilters($countQuery, $filters);
        $this->applyFilters($dataQuery, $filters);

        $total = $countQuery->count();
        $logs = $dataQuery->orderByDesc('created_at')->limit($limit)->offset($offset)->findMany();

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

    private function applyFilters($query, array $filters): void {
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
                $query->whereIn('action', $actions);
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
            $query->whereGte('created_at', $filters['from'] . 'T00:00:00Z');
        }
        if (!empty($filters['to'])) {
            $query->whereLte('created_at', $filters['to'] . 'T23:59:59Z');
        }
    }

    private function applyTextFilters($query, array $filters): void {
        if (!empty($filters['source_machine'])) {
            $query->whereLike('source_machine', '%' . $filters['source_machine'] . '%');
        }
    }

    public function getStats(): array {
        if (!$this->isReady()) {
            return array();
        }

        try {
            return array(
                'total_transactions' => RiseupORM::forTable(TableType::Transactions->value)->count(),
                'by_action'          => $this->countByColumn('action'),
                'by_status'          => $this->countByColumn('status'),
                'last_24h'           => RiseupORM::forTable(TableType::Transactions->value)
                    ->whereGte('created_at', gmdate('Y-m-d\TH:i:s\Z', time() - 86400))
                    ->count(),
            );
        } catch (Exception $e) {
            $this->fileLogger->logException($e, 'Failed to get stats');
            return array();
        }
    }

    private function countByColumn(string $column): array {
        $rows = RiseupORM::rawExecute(
            "SELECT {$column}, COUNT(*) as count FROM " . TableType::Transactions->value . " GROUP BY {$column}"
        );
        $result = array();
        foreach ($rows as $row) {
            $result[$row[$column]] = (int) $row['count'];
        }
        return $result;
    }

    public function cleanupOldTransactions(int $daysToKeep = 365): int {
        if (!$this->isReady()) {
            return 0;
        }

        try {
            $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - ($daysToKeep * 86400));

            $deleted = RiseupORM::forTable(TableType::Transactions->value)
                ->whereLt('created_at', $cutoff)
                ->delete();

            $this->fileLogger->info('Cleanup complete', array('deleted' => $deleted));
            return $deleted;
        } catch (Exception $e) {
            $this->fileLogger->logException($e, 'Failed to cleanup transactions');
            return 0;
        }
    }
}
