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
use Throwable;
use RiseupAsia\Database\Database;
use RiseupAsia\Enums\PaginationConfigType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\TableType;


trait AdminErrorRenderTrait {

    /** Render the error log page. */
    public function renderErrorsPage() {
        $defaults = $this->getErrorPageDefaults();
        extract($defaults);

        try {
            $result = $this->fetchErrorsForPage($defaults);
            extract($result);
        } catch (Throwable $e) {
            $pluginSlug = PluginConfigType::Slug->value;
            $dbErrorMessage = sprintf(
                __('Database error: %s', $pluginSlug),
                esc_html($e->getMessage())
            );
        }

        include dirname(__FILE__, 4) . '/templates/admin-errors.php';
    }

    /** Get safe default values for the error page. */
    private function getErrorPageDefaults(): array {
        return array(
            'errors'          => array(),
            'total'           => 0,
            'totalPages'      => 1,
            'page'            => 1,
            'lastSeenId'      => 0,
            'hasUnseen'       => false,
            'unseenCount'     => 0,
            'latestErrorTime' => '',
            'filterLevel'     => isset($_GET['filter_level']) ? sanitize_text_field($_GET['filter_level']) : '',
            'filterSearch'    => isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '',
            'dbErrorMessage'  => '',
        );
    }

    /** Fetch errors for the admin page with pagination and filters. */
    private function fetchErrorsForPage(array $defaults): array {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            $defaults['dbErrorMessage'] = __('Database connection unavailable. The SQLite database may not be initialized yet.', PluginConfigType::Slug->value);

            return $defaults;
        }

        $tableName = TableType::ErrorSessions->value;
        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $tableName . "'");
        $tableExists = $tableCheck && $tableCheck->fetchColumn();
        $isTableMissing = ($tableExists === false || $tableExists === null);

        if ($isTableMissing) {
            $defaults['dbErrorMessage'] = __('The ErrorSessions table does not exist yet. Errors will appear here once the plugin captures its first error.', PluginConfigType::Slug->value);

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

        $hasLevelFilter = !empty($defaults['filterLevel']);

        if ($hasLevelFilter) {
            $where[] = 'Level = ?';
            $params[] = $defaults['filterLevel'];
        }

        $hasSearchFilter = !empty($defaults['filterSearch']);

        if ($hasSearchFilter) {
            $where[] = 'Message LIKE ?';
            $params[] = '%' . $defaults['filterSearch'] . '%';
        }

        $hasWhereClause = !empty($where);
        $whereSql = $hasWhereClause ? 'WHERE ' . implode(' AND ', $where) : '';

        return array('whereSql' => $whereSql, 'params' => $params);
    }

    /** Count total filtered error sessions. */
    private function countFilteredErrors(PDO $pdo, array $filter): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TableType::ErrorSessions->value . " {$filter['whereSql']}");
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
        $stmt = $pdo->prepare("SELECT * FROM " . TableType::ErrorSessions->value . " {$filter['whereSql']} ORDER BY Id DESC LIMIT ? OFFSET ?");
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
            'errors'          => $errors,
            'total'           => $total,
            'totalPages'      => $totalPages,
            'page'            => $page,
            'lastSeenId'      => $lastSeenId,
            'hasUnseen'       => $hasUnseen,
            'unseenCount'     => $unseenCount,
            'latestErrorTime' => $latestErrorTime,
            'filterLevel'     => $defaults['filterLevel'],
            'filterSearch'    => $defaults['filterSearch'],
            'dbErrorMessage'  => '',
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
