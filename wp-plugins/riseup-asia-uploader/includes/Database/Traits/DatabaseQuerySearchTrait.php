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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Database\Orm;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;

trait DatabaseQuerySearchTrait {

    public function queryTransactions(
        array $filters = array(),
        int $limit = 50, // PaginationConfigType::DefaultLimit (PHP constraint: no enum in defaults)
        int $offset = 0,
    ): array {
        $isDbUnready = ($this->isReady() === false);

        if ($isDbUnready) {
            $this->fileLogger->warn('Database not ready for query');

            return array(ResponseKeyType::Total->value => 0, ResponseKeyType::Logs->value => array());
        }

        $limit = min(max(1, $limit), PaginationConfigType::MaxLimit->value);
        $offset = max(0, $offset);

        try {
            return $this->executeTransactionQuery($filters, $limit, $offset);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to query transactions');

            return array(ResponseKeyType::Total->value => 0, ResponseKeyType::Logs->value => array());
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
        $logs = $dataQuery->orderByDesc('CreatedAt')->limit($limit)->offset($offset)->findMany();

        $this->decodeLogDetails($logs);
        $this->fileLogger->debug('Query complete', array('total' => $total, 'returned' => count($logs)));

        return array(ResponseKeyType::Total->value => $total, ResponseKeyType::Logs->value => $logs);
    }

    private function decodeLogDetails(array &$logs): void {
        foreach ($logs as &$log) {
            $hasDetails = !empty($log['Details'] ?? null);

            if ($hasDetails) {
                $log['Details'] = json_decode($log['Details'], true);
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
            $query->where('PluginSlug', $filters[FilterKeyType::Plugin->value]);
        }

        $hasAction = BooleanHelpers::hasFilterValue($filters, FilterKeyType::Action->value);

        if ($hasAction) {
            $actions = array_map('trim', explode(',', $filters[FilterKeyType::Action->value]));

            if (count($actions) === 1) {
                $query->where('Action', $actions[0]);
            } else {
                $query->whereIn('Action', $actions);
            }
        }

        $hasUser = BooleanHelpers::hasFilterValue($filters, FilterKeyType::User->value);

        if ($hasUser) {
            $query->where('UserLogin', $filters[FilterKeyType::User->value]);
        }

        $hasStatus = BooleanHelpers::hasFilterValue($filters, FilterKeyType::Status->value);

        if ($hasStatus) {
            $query->where('Status', $filters[FilterKeyType::Status->value]);
        }

        $hasTriggeredBy = BooleanHelpers::hasFilterValue($filters, FilterKeyType::TriggeredBy->value);

        if ($hasTriggeredBy) {
            $query->where('TriggeredBy', $filters[FilterKeyType::TriggeredBy->value]);
        }

        $hasUploadSource = BooleanHelpers::hasFilterValue($filters, FilterKeyType::UploadSource->value);

        if ($hasUploadSource) {
            $query->where('UploadSource', $filters[FilterKeyType::UploadSource->value]);
        }
    }

    private function applyDateRangeFilters(RiseupORM $query, array $filters): void {
        $hasFrom = BooleanHelpers::hasFilterValue($filters, FilterKeyType::From->value);

        if ($hasFrom) {
            $query->whereGte('CreatedAt', $filters[FilterKeyType::From->value] . 'T00:00:00Z');
        }

        $hasTo = BooleanHelpers::hasFilterValue($filters, FilterKeyType::To->value);

        if ($hasTo) {
            $query->whereLte('CreatedAt', $filters[FilterKeyType::To->value] . 'T23:59:59Z');
        }
    }

    private function applyTextFilters(RiseupORM $query, array $filters): void {
        $hasSourceMachine = BooleanHelpers::hasFilterValue($filters, FilterKeyType::SourceMachine->value);

        if ($hasSourceMachine) {
            $query->whereLike('SourceMachine', '%' . $filters[FilterKeyType::SourceMachine->value] . '%');
        }
    }

    public function getStats(): array {
        $isDbUnready = ($this->isReady() === false);

        if ($isDbUnready) {
            return array();
        }

        try {
            return array(
                ResponseKeyType::TotalTransactions->value => Orm::forTable(TableType::Transactions->value)->count(),
                ResponseKeyType::ByAction->value          => $this->countByColumn('Action'),
                ResponseKeyType::ByStatus->value          => $this->countByColumn('Status'),
                ResponseKeyType::Last24h->value           => Orm::forTable(TableType::Transactions->value)
                    ->whereGte('CreatedAt', DateHelper::formatUtc(time() - DAY_IN_SECONDS))
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
            $cutoff = DateHelper::formatUtc(time() - ($daysToKeep * DAY_IN_SECONDS));

            $deleted = Orm::forTable(TableType::Transactions->value)
                ->whereLt('CreatedAt', $cutoff)
                ->delete();

            $this->fileLogger->info('Cleanup complete', array('deleted' => $deleted));

            return $deleted;
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to cleanup transactions');

            return 0;
        }
    }
}
