<?php
/**
 * AdminNoticesTrait — displays boot diagnostics failure notices in WP admin.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.3.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\PluginConfigType;


trait AdminNoticesTrait {

    private const DIAGNOSTICS_TRANSIENT = 'riseup_boot_diagnostics';
    private const DISMISS_OPTION = 'riseup_boot_notice_dismissed';

    /**
     * Register the admin_notices hook for boot diagnostics.
     */
    private function registerBootNotices(): void {
        add_action(HookType::AdminNotices->value, [$this, 'renderBootDiagnosticsNotice']);
    }

    /**
     * Render admin notice if boot diagnostics found failures.
     */
    public function renderBootDiagnosticsNotice(): void {
        $diagnostics = get_transient(self::DIAGNOSTICS_TRANSIENT);
        $hasDiagnostics = !empty($diagnostics);
        $isDiagnosticsMissing = !$hasDiagnostics;

        if ($isDiagnosticsMissing) {
            return;
        }

        $hasNoFailures = ((int) ($diagnostics['failed_count'] ?? 0) === 0);
        $hasNoRuntimeFailures = (count($diagnostics['runtime_failures'] ?? []) === 0);

        if ($hasNoFailures && $hasNoRuntimeFailures) {
            return;
        }

        $isDismissed = (get_option(self::DISMISS_OPTION) === ($diagnostics['timestamp'] ?? ''));

        if ($isDismissed) {
            return;
        }

        $this->outputBootNoticeHtml($diagnostics);
    }

    /**
     * Output the HTML for the boot diagnostics admin notice.
     */
    private function outputBootNoticeHtml(array $diagnostics): void {
        $pluginSlug = PluginConfigType::Slug->value;
        $failedCount = (int) ($diagnostics['failed_count'] ?? 0);
        $runtimeCount = count($diagnostics['runtime_failures'] ?? []);
        $totalIssues = $failedCount + $runtimeCount;

        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html__('Riseup Asia Uploader — Boot Diagnostics', $pluginSlug) . '</strong></p>';
        echo '<p>' . sprintf(
            /* translators: %d is the number of issues found */
            esc_html__('Found %d file(s) with issues during plugin initialization:', $pluginSlug),
            $totalIssues,
        ) . '</p>';

        echo '<ul style="list-style: disc; padding-left: 20px;">';

        $failures = $diagnostics['failures'] ?? [];

        foreach ($failures as $failure) {
            echo '<li><code>' . esc_html(basename($failure['file'])) . '</code>: ' . esc_html($failure['error']) . '</li>';
        }

        $runtimeFailures = $diagnostics['runtime_failures'] ?? [];

        foreach ($runtimeFailures as $failure) {
            echo '<li><code>' . esc_html($failure['class']) . '</code>: ' . esc_html($failure['error']) . '</li>';
        }

        echo '</ul>';
        echo '<p><em>' . sprintf(
            /* translators: %s is the timestamp */
            esc_html__('Diagnostics run at: %s', $pluginSlug),
            esc_html($diagnostics['timestamp'] ?? 'unknown'),
        ) . '</em></p>';
        echo '</div>';
    }
}
