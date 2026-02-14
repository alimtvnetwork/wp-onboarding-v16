<?php
/**
 * AdminErrorRenderTrait — Error page rendering, fetching, and querying.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AdminErrorRenderTrait {

    /**
     * Render the error log page.
     */
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

    /**
     * Get safe default values for the error page.
     *
     * @return array Default variables.
     */
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

    /**
     * Fetch errors for the admin page with pagination and filters.
     *
     * @param array $defaults Default page variables.
     * @return array Updated page variables.
     */
    private function fetchErrorsForPage(array $defaults): array {
        $db = RiseupDatabase::getInstance();
        $pdo = $db->getPdo();

        if (!$pdo) {
            $defaults['db_error_message'] = __('Database connection unavailable. The SQLite database may not be initialized yet.', 'riseup-asia-uploader');
            return $defaults;
        }

        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='error_sessions'");
        $tableExists = $tableCheck && $tableCheck->fetchColumn();

        if (!$tableExists) {
            $defaults['db_error_message'] = __('The error_sessions table does not exist yet. Errors will appear here once the plugin captures its first error.', 'riseup-asia-uploader');
            return $defaults;
        }

        return $this->queryErrorPage($pdo, $defaults);
    }

    /**
     * Query error sessions for page rendering.
     *
     * @param PDO   $pdo      Database connection.
     * @param array $defaults Default variables.
     * @return array Updated variables.
     */
    private function queryErrorPage(PDO $pdo, array $defaults): array {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filter = $this->buildErrorFilters($defaults);
        $total = $this->countFilteredErrors($pdo, $filter);
        $totalPages = max(1, ceil($total / $perPage));
        $errors = $this->fetchFilteredErrors($pdo, $filter, $perPage, $offset);

        return $this->assembleErrorPageResult($errors, $total, $totalPages, $page, $defaults);
    }

    /**
     * Build WHERE clause and params from filter defaults.
     */
    private function buildErrorFilters(array $defaults): array {
        $where = array();
        $params = array();

        if (!empty($defaults['filter_level'])) {
            $where[] = 'level = ?';
            $params[] = $defaults['filter_level'];
        }

        if (!empty($defaults['filter_search'])) {
            $where[] = 'message LIKE ?';
            $params[] = '%' . $defaults['filter_search'] . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return array('where_sql' => $whereSql, 'params' => $params);
    }

    /**
     * Count total filtered error sessions.
     */
    private function countFilteredErrors(PDO $pdo, array $filter): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM error_sessions {$filter['where_sql']}");
        $stmt->execute($filter['params']);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Fetch paginated filtered error sessions.
     */
    private function fetchFilteredErrors(PDO $pdo, array $filter, int $perPage, int $offset): array {
        $stmt = $pdo->prepare("SELECT * FROM error_sessions {$filter['where_sql']} ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute(array_merge($filter['params'], array($perPage, $offset)));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assemble the final error page result array.
     */
    private function assembleErrorPageResult(array $errors, int $total, int $totalPages, int $page, array $defaults): array {
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

    /**
     * Resolve the latest error time string.
     */
    private function resolveLatestErrorTime(array $errors, bool $hasUnseen): string {
        if (empty($errors) || !$hasUnseen) {
            return '';
        }

        return date('Y-m-d H:i:s', strtotime($errors[0]['created_at']));
    }
}
