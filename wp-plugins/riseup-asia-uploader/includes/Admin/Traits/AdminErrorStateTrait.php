<?php
/**
 * AdminErrorStateTrait — Unseen error tracking, flash state, and global notice.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AdminErrorStateTrait {

    /**
     * Get unseen error count.
     *
     * @return int
     */
    private function get_unseen_error_count() {
        try {
            $db = RiseupDatabase::get_instance();
            $pdo = $db->get_pdo();
            if (!$pdo) {
                return 0;
            }

            $last_seen = $this->get_flash_value('last_seen_error_id', 0);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM error_sessions WHERE id > ?');
            $stmt->execute(array($last_seen));

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Get a flash state value.
     *
     * @param string $key     Flash key.
     * @param mixed  $default Default value.
     * @return string
     */
    private function get_flash_value($key, $default = '') {
        try {
            $db = RiseupDatabase::get_instance();
            $pdo = $db->get_pdo();
            if (!$pdo) {
                return $default;
            }

            $stmt = $pdo->prepare('SELECT value FROM flash_state WHERE key = ?');
            $stmt->execute(array($key));
            $val = $stmt->fetchColumn();

            return ($val !== false) ? $val : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    /**
     * Render global admin notice when there are unseen errors.
     */
    public function render_global_error_notice() {
        $unseen = $this->get_unseen_error_count();
        if ($unseen <= 0) {
            return;
        }

        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        if ($current_page === 'riseup-asia-errors') {
            return;
        }

        echo $this->buildErrorNoticeHtml($unseen);
    }

    /** Build the HTML for the global error notice. */
    private function buildErrorNoticeHtml(int $unseen): string {
        $url = admin_url('admin.php?page=riseup-asia-errors');
        return sprintf(
            '<div class="notice notice-error is-dismissible" style="border-left-color: #dc3545;">
                <p><strong>⚠️ Riseup Asia Uploader:</strong> %s <a href="%s" style="font-weight:600;">%s →</a></p>
            </div>',
            esc_html(sprintf(
                _n('%d new error detected.', '%d new errors detected.', $unseen, 'riseup-asia-uploader'),
                $unseen
            )),
            esc_url($url),
            esc_html__('View Error Log', 'riseup-asia-uploader')
        );
    }
}
