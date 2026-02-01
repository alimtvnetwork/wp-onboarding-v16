<?php
/**
 * Cleanup class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardCleanup
 *
 * Handles cleanup operations for expired tokens, old temp files, and snapshots.
 */
class OnboardCleanup {

    /**
     * Database instance.
     *
     * @var OnboardDatabase
     */
    private $db;

    /**
     * Audit logger instance.
     *
     * @var OnboardAuditLogger
     */
    private $audit_logger;

    /**
     * Constructor.
     *
     * @param OnboardDatabase     $db           Database instance.
     * @param OnboardAuditLogger $audit_logger Audit logger instance.
     */
    public function __construct(OnboardDatabase $db, OnboardAuditLogger $audit_logger) {
        $this->db = $db;
        $this->audit_logger = $audit_logger;
    }

    /**
     * Run all cleanup tasks.
     *
     * @return array Summary of cleanup actions.
     */
    public function run_all() {
        $results = array(
            'expired_tokens' => $this->cleanup_expired_tokens(),
            'old_temp_files' => $this->cleanup_old_temp_files(),
            'old_snapshots' => $this->cleanup_old_snapshots(),
            'expired_approvals' => $this->cleanup_expired_approvals(),
            'old_audit_logs' => $this->cleanup_old_audit_logs(),
        );

        // Log cleanup.
        $this->audit_logger->log(
            'cleanup_completed',
            null,
            null,
            null,
            'success',
            $results
        );

        return $results;
    }

    /**
     * Cleanup expired OAuth codes and mutation tokens.
     *
     * @return array
     */
    public function cleanup_expired_tokens() {
        $now = date('Y-m-d H:i:s');

        // Delete expired authorization codes.
        $codes_result = $this->db->query(
            'SELECT COUNT(*) as count FROM oauth_codes WHERE expires_at < ?',
            array($now)
        )->fetch();
        $this->db->query('DELETE FROM oauth_codes WHERE expires_at < ?', array($now));

        // Delete expired mutation tokens.
        $mutation_result = $this->db->query(
            'SELECT COUNT(*) as count FROM mutation_tokens WHERE expires_at < ?',
            array($now)
        )->fetch();
        $this->db->query('DELETE FROM mutation_tokens WHERE expires_at < ?', array($now));

        // Delete expired refresh tokens.
        $refresh_result = $this->db->query(
            'SELECT COUNT(*) as count FROM oauth_tokens WHERE refresh_expires_at < ?',
            array($now)
        )->fetch();
        $this->db->query('DELETE FROM oauth_tokens WHERE refresh_expires_at < ?', array($now));

        return array(
            'auth_codes_deleted' => (int) $codes_result['count'],
            'mutation_tokens_deleted' => (int) $mutation_result['count'],
            'refresh_tokens_deleted' => (int) $refresh_result['count'],
        );
    }

    /**
     * Cleanup old temp files.
     *
     * @return array
     */
    public function cleanup_old_temp_files() {
        // Use constant with safe default.
        $cleanup_days = defined('ONBOARD_TEMP_CLEANUP_DAYS') ? ONBOARD_TEMP_CLEANUP_DAYS : 2;
        $temp_path = OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS);
        
        $cutoff_time = time() - ($cleanup_days * 86400);
        $deleted_count = 0;
        $deleted_size = 0;

        if (empty($temp_path) || !is_dir($temp_path)) {
            return array(
                'files_deleted' => 0,
                'space_freed' => 0,
            );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $to_delete = array();

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoff_time) {
                $to_delete[] = array(
                    'path' => $file->getRealPath(),
                    'size' => $file->getSize(),
                    'is_dir' => false,
                );
            } elseif ($file->isDir()) {
                $to_delete[] = array(
                    'path' => $file->getRealPath(),
                    'size' => 0,
                    'is_dir' => true,
                );
            }
        }

        foreach ($to_delete as $item) {
            if ($item['is_dir']) {
                @rmdir($item['path']);
            } else {
                if (@unlink($item['path'])) {
                    $deleted_count++;
                    $deleted_size += $item['size'];
                }
            }
        }

        return array(
            'files_deleted' => $deleted_count,
            'space_freed' => $deleted_size,
        );
    }

    /**
     * Cleanup old snapshots based on retention policy.
     *
     * @return array
     */
    public function cleanup_old_snapshots() {
        // Use constant with safe default.
        $default_retention = defined('ONBOARD_SNAPSHOT_RETENTION_COUNT') ? ONBOARD_SNAPSHOT_RETENTION_COUNT : 5;
        $retention_count = $this->db->get_setting('snapshot_retention_count') ?: $default_retention;

        // Get all plugins with snapshots.
        $plugins = $this->db->query(
            'SELECT DISTINCT plugin_slug FROM snapshots'
        )->fetchAll();

        $deleted_count = 0;
        $deleted_size = 0;

        foreach ($plugins as $plugin) {
            $slug = $plugin['plugin_slug'];

            // Get snapshots to delete (beyond retention count).
            $old_snapshots = $this->db->query(
                'SELECT * FROM snapshots WHERE plugin_slug = ? ORDER BY created_at DESC LIMIT -1 OFFSET ?',
                array($slug, $retention_count)
            )->fetchAll();

            foreach ($old_snapshots as $snapshot) {
                // Delete file.
                if (file_exists($snapshot['file_path'])) {
                    $deleted_size += filesize($snapshot['file_path']);
                    unlink($snapshot['file_path']);
                }

                // Delete record.
                $this->db->query(
                    'DELETE FROM snapshots WHERE snapshot_id = ?',
                    array($snapshot['snapshot_id'])
                );

                $deleted_count++;
            }
        }

        // Clean up empty directories.
        $snapshot_path = OnboardPaths::get(OnboardPaths::DIR_PLUGIN_SNAPSHOTS);
        if (!empty($snapshot_path)) {
            $this->cleanup_empty_directories($snapshot_path);
        }

        return array(
            'snapshots_deleted' => $deleted_count,
            'space_freed' => $deleted_size,
        );
    }

    /**
     * Cleanup expired IP approval requests.
     *
     * @return array
     */
    public function cleanup_expired_approvals() {
        $now = date('Y-m-d H:i:s');

        $result = $this->db->query(
            'SELECT COUNT(*) as count FROM ip_approvals WHERE status = ? AND expires_at < ?',
            array('pending', $now)
        )->fetch();

        $this->db->query(
            'DELETE FROM ip_approvals WHERE status = ? AND expires_at < ?',
            array('pending', $now)
        );

        return array(
            'approvals_deleted' => (int) $result['count'],
        );
    }

    /**
     * Cleanup old audit logs based on retention policy.
     *
     * @return array
     */
    public function cleanup_old_audit_logs() {
        // Use constant with safe default.
        $default_retention = defined('ONBOARD_AUDIT_LOG_RETENTION_DAYS') ? ONBOARD_AUDIT_LOG_RETENTION_DAYS : 365;
        $retention_days = $this->db->get_setting('audit_log_retention_days') ?: $default_retention;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $result = $this->db->audit_query(
            'SELECT COUNT(*) as count FROM audit_logs WHERE timestamp < ?',
            array($cutoff)
        )->fetch();

        $this->db->audit_query(
            'DELETE FROM audit_logs WHERE timestamp < ?',
            array($cutoff)
        );

        return array(
            'logs_deleted' => (int) $result['count'],
        );
    }

    /**
     * Clean up empty directories recursively.
     *
     * @param string $path Base path.
     */
    private function cleanup_empty_directories($path) {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $dir_path = $file->getRealPath();
                $files = array_diff(scandir($dir_path), array('.', '..'));
                if (empty($files)) {
                    @rmdir($dir_path);
                }
            }
        }
    }

    /**
     * Clear all temp files.
     *
     * @return array
     */
    public function clear_all_temp_files() {
        $deleted_count = 0;
        $deleted_size = 0;

        // Use function with safe default.
        $temp_path = OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS);

        if (empty($temp_path) || !is_dir($temp_path)) {
            return array(
                'files_deleted' => 0,
                'space_freed' => 0,
            );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                $deleted_size += $file->getSize();
                if (@unlink($file->getRealPath())) {
                    $deleted_count++;
                }
            }
        }

        // Log cleanup.
        $this->audit_logger->log(
            'temp_files_cleared',
            null,
            null,
            null,
            'success',
            array(
                'files_deleted' => $deleted_count,
                'space_freed' => $deleted_size,
            )
        );

        return array(
            'files_deleted' => $deleted_count,
            'space_freed' => $deleted_size,
        );
    }

    /**
     * Get cleanup status/statistics.
     *
     * @return array
     */
    public function get_status() {
        $now = time();

        // Expired tokens count.
        $expired_codes = $this->db->query(
            'SELECT COUNT(*) as count FROM oauth_codes WHERE expires_at < ?',
            array(date('Y-m-d H:i:s'))
        )->fetch();

        $expired_mutations = $this->db->query(
            'SELECT COUNT(*) as count FROM mutation_tokens WHERE expires_at < ?',
            array(date('Y-m-d H:i:s'))
        )->fetch();

        // Use constants with safe defaults.
        $cleanup_days = defined('ONBOARD_TEMP_CLEANUP_DAYS') ? ONBOARD_TEMP_CLEANUP_DAYS : 2;
        $temp_path = OnboardPaths::get(OnboardPaths::DIR_TEMP_UPLOADS);
        
        // Temp files info.
        $temp_size = 0;
        $temp_count = 0;
        $old_temp_count = 0;
        $cutoff_time = $now - ($cleanup_days * 86400);

        if (!empty($temp_path) && is_dir($temp_path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $temp_size += $file->getSize();
                    $temp_count++;
                    if ($file->getMTime() < $cutoff_time) {
                        $old_temp_count++;
                    }
                }
            }
        }

        // Expired approvals.
        $expired_approvals = $this->db->query(
            'SELECT COUNT(*) as count FROM ip_approvals WHERE status = ? AND expires_at < ?',
            array('pending', date('Y-m-d H:i:s'))
        )->fetch();

        // Last cleanup.
        $last_cleanup = $this->audit_logger->get_logs(array('action' => 'cleanup_completed'), 1);
        $last_cleanup_time = !empty($last_cleanup) ? $last_cleanup[0]['timestamp'] : null;

        return array(
            'expired_auth_codes' => (int) $expired_codes['count'],
            'expired_mutation_tokens' => (int) $expired_mutations['count'],
            'expired_approvals' => (int) $expired_approvals['count'],
            'temp_files' => array(
                'count' => $temp_count,
                'size' => $temp_size,
                'old_count' => $old_temp_count,
            ),
            'last_cleanup' => $last_cleanup_time,
            'next_scheduled' => wp_next_scheduled('onboard_cleanup_cron'),
        );
    }
}
