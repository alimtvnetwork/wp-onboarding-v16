<?php
/**
 * AdminErrorStateTrait — Unseen error tracking, flash state, and global notice.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Database\Database;
use RiseupAsia\Enums\AdminPageType;

trait AdminErrorStateTrait {

    /** Get unseen error count. */
    private function getUnseenErrorCount(): int {
        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            $isPdoMissing = ($pdo === null);
            if ($isPdoMissing) {
                return 0;
            }

            $lastSeen = $this->getFlashValue('last_seen_error_id', 0);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM error_sessions WHERE id > ?');
            $stmt->execute(array($lastSeen));

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Get a flash state value. */
    private function getFlashValue(string $key, string|int $default = ''): string|int {
        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            $isPdoMissing = ($pdo === null);
            if ($isPdoMissing) {
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

    /** Render global admin notice when there are unseen errors. */
    public function renderGlobalErrorNotice(): void {
        $unseen = $this->getUnseenErrorCount();

        if ($unseen <= 0) {
            return;
        }

        $currentPage = isset($_GET['page']) ? $_GET['page'] : '';

        if ($currentPage === AdminPageType::Errors->value) {
            return;
        }

        echo $this->buildErrorNoticeHtml($unseen);
    }

    /** Build the HTML for the global error notice. */
    private function buildErrorNoticeHtml(int $unseen): string {
        $url = AdminPageType::Errors->adminUrl();

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
