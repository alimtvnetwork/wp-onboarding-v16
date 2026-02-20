<?php
/**
 * DatabaseQuerySearchTrait — Transaction querying, filtering, stats, and cleanup.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\FilterKeyType;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Database\Orm;
use RiseupAsia\Helpers\BooleanHelpers;

trait DatabaseQuerySearchTrait {

    public function queryTransactions(
        array $filters = array(),
        int $limit = 50, // PaginationConfigType::DefaultLimit (PHP constraint: no enum in defaults)
        int $offset = 0,
    ): array {
        $isDbUnready = ($this->isReady() === false);
        if ($isDbUnready) {
            $this->fileLogger->warn('Database not ready for query');

            return array('total' => 0, 'logs' => array());
        }

        $limit = min(max(1, $limit), PaginationConfigType::MaxLimit->value);
        $offset = max(0, $offset);

        try {
            return $this->executeTransactionQuery($filters, $limit, $offset);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to query transactions');

            return array('total' => 0, 'logs' => array());
        }
    }

    private function executeTransactionQuery(
        array $filters,
        int $limit,
        int $offset,
    ): array {
        $this->fileLogger->debug('Querying transactions', array('filters' => $filters));

        $countQuery = Orm::forTable(TableType::Transactions->value);
        $dataQuery = Orm::forTable(TableType::Transactions->value);

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
            $hasDetails = BooleanHelpers::hasValue($log['details'] ?? null);
            if ($hasDetails) {
                $log['details'] = json_decode($log['details'], true);
            }
        }
    }

    private function applyFilters(RiseupORM $query, array $filters): void {
        $this->applyEqualityFilters($query, $filters);
        $this->applyDateRangeFilters($query, $filters);
        $this->applyTextFilters($query, $filters);
    }

    private function applyEqualityFilters(RiseupORM $query, array $filters): void {
        $hasPlugin = BooleanHelpers::hasFilterValue($filters, FilterKeyType::Plugin->value);
        if ($hasPlugin) {
            $query->where('plugin_slug', $filters[FilterKeyType::Plugin->value]);
        }
        $hasAction = BooleanHelpers::hasFilterValue($filters, FilterKeyType::Action->value);
        if ($hasAction) {
            $actions = array_map('trim', explode(',', $filters[FilterKeyType::Action->value]));
            if (count($actions) === 1) {
                $query->where('action', $actions[0]);
            } else {
                $query->whereIn('action', $actions);
            }
        }
        $hasUser = BooleanHelpers::hasFilterValue($filters, FilterKeyType::User->value);
        if ($hasUser) {
            $query->where('user_login', $filters[FilterKeyType::User->value]);
        }
        $hasStatus = BooleanHelpers::hasFilterValue($filters, FilterKeyType::Status->value);
        if ($hasStatus) {
            $query->where('status', $filters[FilterKeyType::Status->value]);
        }
        $hasTriggeredBy = BooleanHelpers::hasFilterValue($filters, FilterKeyType::TriggeredBy->value);
        if ($hasTriggeredBy) {
            $query->where('triggered_by', $filters[FilterKeyType::TriggeredBy->value]);
        }
        $hasUploadSource = BooleanHelpers::hasFilterValue($filters, FilterKeyType::UploadSource->value);
        if ($hasUploadSource) {
            $query->where('upload_source', $filters[FilterKeyType::UploadSource->value]);
        }
    }

    private function applyDateRangeFilters(RiseupORM $query, array $filters): void {
        $hasFrom = BooleanHelpers::hasFilterValue($filters, FilterKeyType::From->value);
        if ($hasFrom) {
            $query->whereGte('created_at', $filters[FilterKeyType::From->value] . 'T00:00:00Z');
        }
        $hasTo = BooleanHelpers::hasFilterValue($filters, FilterKeyType::To->value);
        if ($hasTo) {
            $query->whereLte('created_at', $filters[FilterKeyType::To->value] . 'T23:59:59Z');
        }
    }

    private function applyTextFilters(RiseupORM $query, array $filters): void {
        $hasSourceMachine = BooleanHelpers::hasFilterValue($filters, FilterKeyType::SourceMachine->value);
        if ($hasSourceMachine) {
            $query->whereLike('source_machine', '%' . $filters[FilterKeyType::SourceMachine->value] . '%');
        }
    }

    public function getStats(): array {
        $isDbUnready = ($this->isReady() === false);
        if ($isDbUnready) {
            return array();
        }

        try {
            return array(
                'total_transactions' => Orm::forTable(TableType::Transactions->value)->count(),
                'by_action'          => $this->countByColumn('action'),
                'by_status'          => $this->countByColumn('status'),
                'last_24h'           => Orm::forTable(TableType::Transactions->value)
                    ->whereGte('created_at', gmdate('Y-m-d\TH:i:s\Z', time() - 86400))
                    ->count(),
            );
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to get stats');

            return array();
        }
    }

    private function countByColumn(string $column): array {
        $rows = Orm::rawExecute(
            "SELECT {$column}, COUNT(*) as count FROM " . TableType::Transactions->value . " GROUP BY {$column}"
        );
        $result = array();
        foreach ($rows as $row) {
            $result[$row[$column]] = (int) $row['count'];
        }

        return $result;
    }

    public function cleanupOldTransactions(int $daysToKeep = 365): int {
        $isDbUnready = ($this->isReady() === false);
        if ($isDbUnready) {
            return 0;
        }

        try {
            $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - ($daysToKeep * 86400));

            $deleted = Orm::forTable(TableType::Transactions->value)
                ->whereLt('created_at', $cutoff)
                ->delete();

            $this->fileLogger->info('Cleanup complete', array('deleted' => $deleted));

            return $deleted;
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to cleanup transactions');

            return 0;
        }
    }
}
