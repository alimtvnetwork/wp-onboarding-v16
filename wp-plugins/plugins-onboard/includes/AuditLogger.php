<?php
/**
 * Audit Logger class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardAuditLogger
 *
 * Handles audit logging to SQLite database.
 */
class OnboardAuditLogger {

    /**
     * Database instance.
     *
     * @var OnboardDatabase
     */
    private $db;

    /**
     * Constructor.
     *
     * @param OnboardDatabase $db Database instance.
     */
    public function __construct(OnboardDatabase $db) {
        $this->db = $db;
    }

    /**
     * Log an action.
     *
     * @param string      $action         Action name.
     * @param string|null $plugin_slug    Plugin slug (if applicable).
     * @param string|null $app_id         Application ID.
     * @param string|null $ip_address     IP address.
     * @param string      $status         Status (success, failed, pending_approval).
     * @param array       $details        Additional details.
     * @param string|null $error_message  Error message (if failed).
     * @param string|null $mutation_token Mutation token used.
     */
    public function log(
        $action,
        $plugin_slug = null,
        $app_id = null,
        $ip_address = null,
        $status = 'success',
        $details = array(),
        $error_message = null,
        $mutation_token = null
    ) {
        // Use constant with safe default (config may not be fully initialized).
        $audit_enabled = defined('ONBOARD_ENABLE_AUDIT_LOGS') ? ONBOARD_ENABLE_AUDIT_LOGS : true;
        $isAuditDisabled = !$audit_enabled;

        if ($isAuditDisabled) {
            return;
        }

        $log_id = $this->db->generate_uuid();
        $timestamp = date('Y-m-d H:i:s');
        $app_name = $app_id ? $this->get_app_name($app_id) : null;
        $ip = $ip_address ?: $this->get_client_ip();

        $this->db->audit_query(
            'INSERT INTO audit_logs (log_id, timestamp, action, plugin_slug, app_id, app_name, ip_address, mutation_token, status, details, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $log_id,
                $timestamp,
                $action,
                $plugin_slug,
                $app_id,
                $app_name,
                $ip,
                $mutation_token,
                $status,
                json_encode($details),
                $error_message,
            )
        );
    }

    /**
     * Log IP approval action.
     *
     * @param string      $app_id     Application ID.
     * @param string      $ip_address IP address.
     * @param string      $action     Action (requested, approved, rejected).
     * @param array       $details    Additional details.
     */
    public function log_ip_approval($app_id, $ip_address, $action, $details = array()) {
        $log_id = $this->db->generate_uuid();
        $timestamp = date('Y-m-d H:i:s');
        $app_name = $app_id ? $this->get_app_name($app_id) : null;

        $this->db->audit_query(
            'INSERT INTO ip_approval_logs (log_id, app_id, app_name, ip_address, action, timestamp, details) VALUES (?, ?, ?, ?, ?, ?, ?)',
            array(
                $log_id,
                $app_id,
                $app_name,
                $ip_address,
                $action,
                $timestamp,
                json_encode($details),
            )
        );
    }

    /**
     * Get audit logs.
     *
     * @param array $filters Filters (app_id, action, status, date_from, date_to).
     * @param int   $limit   Limit.
     * @param int   $offset  Offset.
     * @return array
     */
    public function get_logs($filters = array(), $limit = 50, $offset = 0) {
        $sql = 'SELECT * FROM audit_logs WHERE 1=1';
        $params = array();

        if (!empty($filters['app_id'])) {
            $sql .= ' AND app_id = ?';
            $params[] = $filters['app_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= ' AND action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['plugin_slug'])) {
            $sql .= ' AND plugin_slug = ?';
            $params[] = $filters['plugin_slug'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND timestamp >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND timestamp <= ?';
            $params[] = $filters['date_to'];
        }

        $sql .= ' ORDER BY timestamp DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $results = $this->db->audit_query($sql, $params)->fetchAll();

        // Decode JSON details.
        foreach ($results as &$row) {
            if (!empty($row['details'])) {
                $row['details'] = json_decode($row['details'], true);
            }
        }

        return $results;
    }

    /**
     * Get log count.
     *
     * @param array $filters Filters.
     * @return int
     */
    public function get_log_count($filters = array()) {
        $sql = 'SELECT COUNT(*) as count FROM audit_logs WHERE 1=1';
        $params = array();

        if (!empty($filters['app_id'])) {
            $sql .= ' AND app_id = ?';
            $params[] = $filters['app_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= ' AND action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        $result = $this->db->audit_query($sql, $params)->fetch();
        return (int) $result['count'];
    }

    /**
     * Get IP approval logs.
     *
     * @param array $filters Filters.
     * @param int   $limit   Limit.
     * @param int   $offset  Offset.
     * @return array
     */
    public function get_ip_approval_logs($filters = array(), $limit = 50, $offset = 0) {
        $sql = 'SELECT * FROM ip_approval_logs WHERE 1=1';
        $params = array();

        if (!empty($filters['app_id'])) {
            $sql .= ' AND app_id = ?';
            $params[] = $filters['app_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= ' AND action = ?';
            $params[] = $filters['action'];
        }

        $sql .= ' ORDER BY timestamp DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->audit_query($sql, $params)->fetchAll();
    }

    /**
     * Clear all logs.
     *
     * @return int Number of records deleted.
     */
    public function clear_logs() {
        // Log the clearing action before deleting.
        $this->log('audit_logs_cleared', null, null, null, 'success', array(
            'cleared_by' => get_current_user_id(),
        ));

        $count_result = $this->db->audit_query('SELECT COUNT(*) as count FROM audit_logs')->fetch();
        $count = (int) $count_result['count'] - 1; // Exclude the log we just created.

        // Delete all logs except the one we just created.
        $this->db->audit_query(
            'DELETE FROM audit_logs WHERE action != ? OR timestamp < ?',
            array('audit_logs_cleared', date('Y-m-d H:i:s', strtotime('-1 second')))
        );

        return $count;
    }

    /**
     * Clear old logs based on retention policy.
     *
     * @param int $retention_days Days to retain logs.
     * @return int Number of records deleted.
     */
    public function clear_old_logs($retention_days = null) {
        if ($retention_days === null) {
            // Use constant with safe default.
            $retention_days = defined('ONBOARD_AUDIT_LOG_RETENTION_DAYS') ? ONBOARD_AUDIT_LOG_RETENTION_DAYS : 365;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $count_result = $this->db->audit_query(
            'SELECT COUNT(*) as count FROM audit_logs WHERE timestamp < ?',
            array($cutoff)
        )->fetch();
        $count = (int) $count_result['count'];

        $this->db->audit_query('DELETE FROM audit_logs WHERE timestamp < ?', array($cutoff));

        // Log cleanup.
        $this->log('audit_logs_cleanup', null, null, null, 'success', array(
            'retention_days' => $retention_days,
            'records_deleted' => $count,
        ));

        return $count;
    }

    /**
     * Get application name by ID.
     *
     * @param string $app_id Application ID.
     * @return string|null
     */
    private function get_app_name($app_id) {
        $result = $this->db->query(
            'SELECT app_name FROM applications WHERE app_id = ?',
            array($app_id)
        )->fetch();

        return $result ? $result['app_name'] : null;
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        }
        return trim($ip);
    }

    /**
     * Get unique actions from logs.
     *
     * @return array
     */
    public function get_unique_actions() {
        $results = $this->db->audit_query(
            'SELECT DISTINCT action FROM audit_logs ORDER BY action'
        )->fetchAll();

        return array_column($results, 'action');
    }

    /**
     * Get statistics.
     *
     * @return array
     */
    public function get_statistics() {
        $total = $this->get_log_count();
        $success = $this->get_log_count(array('status' => 'success'));
        $failed = $this->get_log_count(array('status' => 'failed'));
        $pending = $this->get_log_count(array('status' => 'pending_approval'));

        $today_count = $this->db->audit_query(
            'SELECT COUNT(*) as count FROM audit_logs WHERE DATE(timestamp) = DATE("now")',
            array()
        )->fetch();

        return array(
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'pending' => $pending,
            'today' => (int) $today_count['count'],
        );
    }
}
