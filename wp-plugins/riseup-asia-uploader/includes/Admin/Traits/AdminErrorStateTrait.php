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

use Throwable;
use RiseupAsia\Database\Orm;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Database\Database;
use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\TableType;

trait AdminErrorStateTrait {

    /** Get unseen error count. */
    private function getUnseenErrorCount(): int {
        try {
            $db = Database::getInstance();
            $isPdoMissing = ($db->getPdo() === null);

            if ($isPdoMissing) {
                return 0;
            }

            $lastSeen = $this->getFlashValue('last_seen_error_id', 0);

            return Orm::forTable(TableType::ErrorSessions->value)
                ->whereGt('Id', $lastSeen)
                ->count();
        } catch (Throwable $e) {
            InitHelpers::errorLog($e, 'AdminErrorStateTrait::getUnseenErrorCount() failed:');
            return 0;
        }
    }

    /** Get a flash state value. */
    private function getFlashValue(string $key, string|int $default = ''): string|int {
        try {
            $db = Database::getInstance();
            $isPdoMissing = ($db->getPdo() === null);

            if ($isPdoMissing) {
                return $default;
            }

            $row = Orm::forTable(TableType::FlashState->value)
                ->selectColumn('Value')
                ->where('Key', $key)
                ->findFirst();

            $isFound = ($row !== null);

            return $isFound ? $row['Value'] : $default;
        } catch (Throwable $e) {
            InitHelpers::errorLog($e, 'AdminErrorStateTrait::getFlashValue() failed:');
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

        $pluginSlug = PluginConfigType::Slug->value;

        return sprintf(
            '<div class="notice notice-error is-dismissible" style="border-left-color: #dc3545;">
                <p><strong>⚠️ ' . esc_html(PluginConfigType::Name->value) . ':</strong> %s <a href="%s" style="font-weight:600;">%s →</a></p>
            </div>',
            esc_html(sprintf(
                _n('%d new error detected.', '%d new errors detected.', $unseen, $pluginSlug),
                $unseen,
            )),
            esc_url($url),
            esc_html__('View Error Log', $pluginSlug),
        );
    }
}
