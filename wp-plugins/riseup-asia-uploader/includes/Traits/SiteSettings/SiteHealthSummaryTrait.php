<?php
/**
 * SiteHealthSummaryTrait — Provides a health summary endpoint similar to PowerShell -ps output.
 *
 * Returns:
 * - PHP/WP version, memory, disk space
 * - Active/inactive plugin counts
 * - WP Reset / Updraft availability and snapshot/backup counts
 * - Recent error count
 * - User count
 *
 * @package RiseupAsia\Traits\SiteSettings
 * @since   2.31.0
 */

namespace RiseupAsia\Traits\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Enums\PhpNativeType;

trait SiteHealthSummaryTrait
{
    /**
     * Handle GET /site-health-summary
     */
    public function handleSiteHealthSummary(WP_REST_Request $request): WP_REST_Response
    {
        $this->fileLogger->info('Site health summary requested');

        $payload = [
            'system'          => $this->buildSystemInfo(),
            'plugins'         => $this->buildPluginSummary(),
            'integrations'    => $this->buildIntegrationsSummary(),
            'users'           => $this->buildUserSummary(),
            'database'        => $this->buildDatabaseSummary(),
        ];

        return EnvelopeBuilder::success()
            ->setSingleResult($payload)
            ->toResponse();
    }

    /**
     * Build system info section.
     */
    private function buildSystemInfo(): array
    {
        $diskFree = function_exists('disk_free_space') ? @disk_free_space(ABSPATH) : false;
        $diskTotal = function_exists('disk_total_space') ? @disk_total_space(ABSPATH) : false;

        return [
            'phpVersion'        => PHP_VERSION,
            'wpVersion'         => get_bloginfo('version'),
            'memoryLimit'       => ini_get('memory_limit'),
            'memoryUsage'       => size_format(memory_get_usage(true)),
            'memoryPeak'        => size_format(memory_get_peak_usage(true)),
            'uploadMaxFilesize' => ini_get('upload_max_filesize'),
            'postMaxSize'       => ini_get('post_max_size'),
            'maxExecutionTime'  => (int) ini_get('max_execution_time'),
            'diskFree'          => $diskFree !== false ? size_format($diskFree) : 'unknown',
            'diskTotal'         => $diskTotal !== false ? size_format($diskTotal) : 'unknown',
            'diskFreeBytes'     => $diskFree !== false ? (int) $diskFree : 0,
            'diskTotalBytes'    => $diskTotal !== false ? (int) $diskTotal : 0,
            'serverSoftware'    => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field($_SERVER['SERVER_SOFTWARE']) : 'unknown',
            'sslEnabled'        => is_ssl(),
            'isMultisite'       => is_multisite(),
            'timezone'          => wp_timezone_string(),
            'wpDebug'           => defined('WP_DEBUG') && WP_DEBUG,
            'wpDebugLog'        => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
        ];
    }

    /**
     * Build plugin counts summary.
     */
    private function buildPluginSummary(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        $activePlugins = get_option(OptionNameType::ActivePlugins->value, []);

        $activeCount = 0;
        $inactiveCount = 0;

        foreach ($allPlugins as $file => $data) {
            if (in_array($file, $activePlugins, true)) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }
        }

        return [
            'total'    => count($allPlugins),
            'active'   => $activeCount,
            'inactive' => $inactiveCount,
        ];
    }

    /**
     * Build integrations (WP Reset, Updraft) availability summary.
     */
    private function buildIntegrationsSummary(): array
    {
        $integrations = [];

        // WP Reset detection
        $hasWpResetFree = class_exists('WP_Reset');
        $hasWpResetPro = class_exists('WP_Reset_Pro');
        $integrations['wpReset'] = [
            'available'  => $hasWpResetFree || $hasWpResetPro,
            'isPro'      => $hasWpResetPro,
            'snapshots'  => $this->countWpResetSnapshots(),
        ];

        // UpdraftPlus detection
        $hasUpdraft = class_exists('UpdraftPlus');
        $integrations['updraftPlus'] = [
            'available' => $hasUpdraft,
            'backups'   => $hasUpdraft ? $this->countUpdraftBackups() : 0,
        ];

        return $integrations;
    }

    /**
     * Count WP Reset snapshots.
     */
    private function countWpResetSnapshots(): int
    {
        if ($this->db === null) {
            return 0;
        }

        $hasWpReset = class_exists('WP_Reset') || class_exists('WP_Reset_Pro');
        if (!$hasWpReset) {
            return 0;
        }

        // Try to get snapshots from WP Reset's own storage
        global $wp_reset;
        if ($wp_reset !== null && method_exists($wp_reset, 'get_snapshots')) {
            $snapshots = $wp_reset->get_snapshots();
            return gettype($snapshots) === PhpNativeType::PhpArray->value ? count($snapshots) : 0;
        }

        return 0;
    }

    /**
     * Count UpdraftPlus backups.
     */
    private function countUpdraftBackups(): int
    {
        $hasUpdraft = class_exists('UpdraftPlus');
        if (!$hasUpdraft) {
            return 0;
        }

        $history = get_option('updraft_backup_history', []);
        return gettype($history) === PhpNativeType::PhpArray->value ? count($history) : 0;
    }

    /**
     * Build user summary.
     */
    private function buildUserSummary(): array
    {
        $userCount = count_users();

        return [
            'total'    => $userCount['total_users'],
            'byRole'   => $userCount['avail_roles'],
        ];
    }

    /**
     * Build database summary.
     */
    private function buildDatabaseSummary(): array
    {
        global $wpdb;

        $dbSize = 0;
        $tableCount = 0;

        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        if (gettype($tables) === PhpNativeType::PhpArray->value) {
            $tableCount = count($tables);
            foreach ($tables as $table) {
                $dbSize += (int) $table['Data_length'] + (int) $table['Index_length'];
            }
        }

        return [
            'tableCount' => $tableCount,
            'totalSize'  => size_format($dbSize),
            'totalBytes' => $dbSize,
            'prefix'     => $wpdb->prefix,
        ];
    }
}
