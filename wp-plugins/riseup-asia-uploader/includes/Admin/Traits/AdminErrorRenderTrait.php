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
    public function render_errors_page() {
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
        $db = RiseupDatabase::get_instance();
        $pdo = $db->get_pdo();

        if (!$pdo) {
            $defaults['db_error_message'] = __('Database connection unavailable. The SQLite database may not be initialized yet.', 'riseup-asia-uploader');
            return $defaults;
        }

        $table_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='error_sessions'");
        $table_exists = $table_check && $table_check->fetchColumn();

        if (!$table_exists) {
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
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

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

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM error_sessions {$where_sql}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $total_pages = max(1, ceil($total / $per_page));

        $stmt = $pdo->prepare("SELECT * FROM error_sessions {$where_sql} ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute(array_merge($params, array($per_page, $offset)));
        $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $last_seen_id = $this->get_flash_value('last_seen_error_id', 0);
        $has_unseen = ($this->get_flash_value('has_unseen_errors', '0') === '1');
        $unseen_count = $this->get_unseen_error_count();
        $latest_error_time = '';
        if (!empty($errors) && $has_unseen) {
            $latest_error_time = date('Y-m-d H:i:s', strtotime($errors[0]['created_at']));
        }

        return array(
            'errors'            => $errors,
            'total'             => $total,
            'total_pages'       => $total_pages,
            'page'              => $page,
            'last_seen_id'      => $last_seen_id,
            'has_unseen'        => $has_unseen,
            'unseen_count'      => $unseen_count,
            'latest_error_time' => $latest_error_time,
            'filter_level'      => $defaults['filter_level'],
            'filter_search'     => $defaults['filter_search'],
            'db_error_message'  => '',
        );
    }
}
