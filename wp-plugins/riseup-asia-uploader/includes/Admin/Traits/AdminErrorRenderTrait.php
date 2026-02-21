<?php
/**
 * AdminErrorRenderTrait — Error page rendering, fetching, and querying.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RiseupAsia\Database\Database;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\BooleanHelpers;

trait AdminErrorRenderTrait {

    /** Render the error log page. */
    public function renderErrorsPage() {
        $defaults = $this->getErrorPageDefaults();
        extract($defaults);

        try {
            $result = $this->fetchErrorsForPage($defaults);
            extract($result);
        } catch (Throwable $e) {
            $db_error_message = sprintf(
                __('Database error: %s', 'riseup-asia-uploader'),
                esc_html($e->getMessage())
            );
        }

        include dirname(__FILE__) . '/../../templates/admin-errors.php';
    }

    /** Get safe default values for the error page. */
    private function getErrorPageDefaults(): array {
        return array(
            'errors'           => array(),
            'total'            => 0,
            'total_pages'      => 1,
            'page'             => 1,
            'last_seen_id'     => 0,
            'has_unseen'       => false,
            'unseen_count'     => 0,
            'latest_error_time' => '',
            'filter_level'     => isset($_GET['filter_level']) ? sanitize_text_field($_GET['filter_level']) : '',
            'filter_search'    => isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '',
            'db_error_message' => '',
        );
    }

    /** Fetch errors for the admin page with pagination and filters. */
    private function fetchErrorsForPage(array $defaults): array {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            $defaults['db_error_message'] = __('Database connection unavailable. The SQLite database may not be initialized yet.', 'riseup-asia-uploader');

            return $defaults;
        }

        $tableName = TableType::ErrorSessions->value;
        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $tableName . "'");
        $tableExists = $tableCheck && $tableCheck->fetchColumn();
        $isTableMissing = ($tableExists === false || $tableExists === null);

        if ($isTableMissing) {
            $defaults['db_error_message'] = __('The ErrorSessions table does not exist yet. Errors will appear here once the plugin captures its first error.', 'riseup-asia-uploader');

            return $defaults;
        }

        return $this->queryErrorPage($pdo, $defaults);
    }

    /** Query error sessions for page rendering. */
    private function queryErrorPage(PDO $pdo, array $defaults): array {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $perPage = PaginationConfigType::DefaultLimit->value;
        $offset = ($page - 1) * $perPage;

        $filter = $this->buildErrorFilters($defaults);
        $total = $this->countFilteredErrors($pdo, $filter);
        $totalPages = max(1, ceil($total / $perPage));
        $errors = $this->fetchFilteredErrors($pdo, $filter, $perPage, $offset);

        return $this->assembleErrorPageResult($errors, $total, $totalPages, $page, $defaults);
    }

    /** Build WHERE clause and params from filter defaults. */
    private function buildErrorFilters(array $defaults): array {
        $where = array();
        $params = array();

        $hasLevelFilter = BooleanHelpers::hasValue($defaults['filter_level']);

        if ($hasLevelFilter) {
            $where[] = 'Level = ?';
            $params[] = $defaults['filter_level'];
        }

        $hasSearchFilter = BooleanHelpers::hasValue($defaults['filter_search']);

        if ($hasSearchFilter) {
            $where[] = 'Message LIKE ?';
            $params[] = '%' . $defaults['filter_search'] . '%';
        }

        $hasWhereClause = BooleanHelpers::hasValue($where);
        $whereSql = $hasWhereClause ? 'WHERE ' . implode(' AND ', $where) : '';

        return array('where_sql' => $whereSql, 'params' => $params);
    }

    /** Count total filtered error sessions. */
    private function countFilteredErrors(PDO $pdo, array $filter): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TableType::ErrorSessions->value . " {$filter['where_sql']}");
        $stmt->execute($filter['params']);

        return (int) $stmt->fetchColumn();
    }

    /** Fetch paginated filtered error sessions. */
    private function fetchFilteredErrors(
        PDO $pdo,
        array $filter,
        int $perPage,
        int $offset,
    ): array {
        $stmt = $pdo->prepare("SELECT * FROM " . TableType::ErrorSessions->value . " {$filter['where_sql']} ORDER BY Id DESC LIMIT ? OFFSET ?");
        $stmt->execute(array_merge($filter['params'], array($perPage, $offset)));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Assemble the final error page result array. */
    private function assembleErrorPageResult(
        array $errors,
        int $total,
        int $totalPages,
        int $page,
        array $defaults,
    ): array {
        $lastSeenId = $this->getFlashValue('last_seen_error_id', 0);
        $hasUnseen = ($this->getFlashValue('has_unseen_errors', '0') === '1');
        $unseenCount = $this->getUnseenErrorCount();
        $latestErrorTime = $this->resolveLatestErrorTime($errors, $hasUnseen);

        return array(
            'errors' => $errors, 'total' => $total, 'total_pages' => $totalPages,
            'page' => $page, 'last_seen_id' => $lastSeenId, 'has_unseen' => $hasUnseen,
            'unseen_count' => $unseenCount, 'latest_error_time' => $latestErrorTime,
            'filter_level' => $defaults['filter_level'], 'filter_search' => $defaults['filter_search'],
            'db_error_message' => '',
        );
    }

    /** Resolve the latest error time string. */
    private function resolveLatestErrorTime(array $errors, bool $hasUnseen): string {
        $isErrorListClear = empty($errors) || ($hasUnseen === false);

        if ($isErrorListClear) {
            return '';
        }

        return date('Y-m-d H:i:s', strtotime($errors[0]['CreatedAt']));
    }
}
