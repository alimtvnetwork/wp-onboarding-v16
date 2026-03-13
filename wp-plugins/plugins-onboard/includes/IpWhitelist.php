<?php
/**
 * IP Whitelist class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardIPWhitelist
 *
 * Handles IP whitelist management and approval workflows.
 */
class OnboardIPWhitelist {

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
     * Check if IP is whitelisted for an application.
     *
     * @param string $app_id     Application ID.
     * @param string $ip_address IP address.
     * @return bool
     */
    public function is_whitelisted($app_id, $ip_address) {
        // Use constant with safe default (config may not be fully initialized).
        $whitelist_enabled = defined('ONBOARD_IP_WHITELIST_ENABLED') ? ONBOARD_IP_WHITELIST_ENABLED : true;
        if (!$whitelist_enabled) {
            return true;
        }

        // Check application whitelist.
        $app = $this->db->query(
            'SELECT ip_whitelist FROM applications WHERE app_id = ?',
            array($app_id)
        )->fetch();

        if (!$app) {
            return false;
        }

        $whitelist = json_decode($app['ip_whitelist'], true) ?: array();
        return in_array($ip_address, $whitelist, true);
    }

    /**
     * Add IP to whitelist.
     *
     * @param string $app_id     Application ID.
     * @param string $ip_address IP address.
     * @return bool
     */
    public function add_to_whitelist($app_id, $ip_address) {
        $app = $this->db->query(
            'SELECT ip_whitelist FROM applications WHERE app_id = ?',
            array($app_id)
        )->fetch();

        if (!$app) {
            return false;
        }

        $whitelist = json_decode($app['ip_whitelist'], true) ?: array();
        
        if (!in_array($ip_address, $whitelist, true)) {
            $whitelist[] = $ip_address;
            $this->db->query(
                'UPDATE applications SET ip_whitelist = ?, updated_at = ? WHERE app_id = ?',
                array(json_encode($whitelist), date('Y-m-d H:i:s'), $app_id)
            );

            $this->audit_logger->log_ip_approval($app_id, $ip_address, 'added_to_whitelist');
        }

        return true;
    }

    /**
     * Remove IP from whitelist.
     *
     * @param string $app_id     Application ID.
     * @param string $ip_address IP address.
     * @return bool
     */
    public function remove_from_whitelist($app_id, $ip_address) {
        $app = $this->db->query(
            'SELECT ip_whitelist FROM applications WHERE app_id = ?',
            array($app_id)
        )->fetch();

        if (!$app) {
            return false;
        }

        $whitelist = json_decode($app['ip_whitelist'], true) ?: array();
        $whitelist = array_values(array_diff($whitelist, array($ip_address)));

        $this->db->query(
            'UPDATE applications SET ip_whitelist = ?, updated_at = ? WHERE app_id = ?',
            array(json_encode($whitelist), date('Y-m-d H:i:s'), $app_id)
        );

        $this->audit_logger->log_ip_approval($app_id, $ip_address, 'removed_from_whitelist');

        return true;
    }

    /**
     * Get whitelist for an application.
     *
     * @param string $app_id Application ID.
     * @return array
     */
    public function get_whitelist($app_id) {
        $app = $this->db->query(
            'SELECT ip_whitelist FROM applications WHERE app_id = ?',
            array($app_id)
        )->fetch();

        if (!$app) {
            return array();
        }

        return json_decode($app['ip_whitelist'], true) ?: array();
    }

    /**
     * Request IP approval.
     *
     * @param string $app_id     Application ID.
     * @param string $ip_address IP address.
     * @param string $action     Requested action.
     * @return array Approval request details.
     */
    public function request_approval($app_id, $ip_address, $action) {
        // Check if already pending.
        $existing = $this->db->query(
            'SELECT * FROM ip_approvals WHERE app_id = ? AND ip_address = ? AND status = ?',
            array($app_id, $ip_address, 'pending')
        )->fetch();

        if ($existing) {
            return array(
                'approval_id' => $existing['approval_id'],
                'status' => 'already_pending',
                'message' => 'An approval request is already pending for this IP.',
            );
        }

        // Generate approval code.
        $approval_id = $this->db->generate_uuid();
        $approval_code = OnboardTokenEncryption::generate_token(16);
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours.

        $this->db->query(
            'INSERT INTO ip_approvals (approval_id, app_id, ip_address, approval_code, status, requested_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            array(
                $approval_id,
                $app_id,
                $ip_address,
                OnboardTokenEncryption::encrypt($approval_code),
                'pending',
                $now,
                $expires,
            )
        );

        // Send approval email.
        $this->send_approval_email($app_id, $ip_address, $action, $approval_code);

        // Log the request.
        $this->audit_logger->log_ip_approval($app_id, $ip_address, 'requested', array(
            'action' => $action,
            'approval_id' => $approval_id,
        ));

        return array(
            'approval_id' => $approval_id,
            'status' => 'pending',
            'message' => 'IP pending admin approval. Check your email.',
            'expires_in' => 86400,
        );
    }

    /**
     * Approve IP request.
     *
     * @param string $approval_code Approval code.
     * @param int    $approved_by   Admin user ID.
     * @return array|WP_Error
     */
    public function approve($approval_code, $approved_by = null) {
        // Find approval request.
        $approvals = $this->db->query(
            'SELECT * FROM ip_approvals WHERE status = ? AND expires_at > ?',
            array('pending', date('Y-m-d H:i:s'))
        )->fetchAll();

        $approval = null;
        foreach ($approvals as $record) {
            $decrypted = OnboardTokenEncryption::decrypt($record['approval_code']);
            if ($decrypted === $approval_code) {
                $approval = $record;
                break;
            }
        }

        if (!$approval) {
            return new WP_Error(
                'invalid_approval_code',
                'Invalid or expired approval code',
                array('status' => 400)
            );
        }

        // Update approval status.
        $this->db->query(
            'UPDATE ip_approvals SET status = ?, approved_at = ?, approved_by = ? WHERE approval_id = ?',
            array('approved', date('Y-m-d H:i:s'), $approved_by ?: get_current_user_id(), $approval['approval_id'])
        );

        // Add to whitelist.
        $this->add_to_whitelist($approval['app_id'], $approval['ip_address']);

        // Log approval.
        $this->audit_logger->log_ip_approval($approval['app_id'], $approval['ip_address'], 'approved', array(
            'approved_by' => $approved_by ?: get_current_user_id(),
        ));

        // Send notification email to app owner (if configured).
        $this->send_approval_notification($approval['app_id'], $approval['ip_address'], true);

        return array(
            'status' => 'approved',
            'app_id' => $approval['app_id'],
            'ip_address' => $approval['ip_address'],
            'message' => 'IP address has been approved.',
        );
    }

    /**
     * Reject IP request.
     *
     * @param string $approval_code Approval code.
     * @param int    $rejected_by   Admin user ID.
     * @return array|WP_Error
     */
    public function reject($approval_code, $rejected_by = null) {
        // Find approval request.
        $approvals = $this->db->query(
            'SELECT * FROM ip_approvals WHERE status = ? AND expires_at > ?',
            array('pending', date('Y-m-d H:i:s'))
        )->fetchAll();

        $approval = null;
        foreach ($approvals as $record) {
            $decrypted = OnboardTokenEncryption::decrypt($record['approval_code']);
            if ($decrypted === $approval_code) {
                $approval = $record;
                break;
            }
        }

        if (!$approval) {
            return new WP_Error(
                'invalid_approval_code',
                'Invalid or expired approval code',
                array('status' => 400)
            );
        }

        // Update approval status.
        $this->db->query(
            'UPDATE ip_approvals SET status = ?, approved_at = ?, approved_by = ? WHERE approval_id = ?',
            array('rejected', date('Y-m-d H:i:s'), $rejected_by ?: get_current_user_id(), $approval['approval_id'])
        );

        // Log rejection.
        $this->audit_logger->log_ip_approval($approval['app_id'], $approval['ip_address'], 'rejected', array(
            'rejected_by' => $rejected_by ?: get_current_user_id(),
        ));

        return array(
            'status' => 'rejected',
            'app_id' => $approval['app_id'],
            'ip_address' => $approval['ip_address'],
            'message' => 'IP address has been rejected.',
        );
    }

    /**
     * Get pending approvals.
     *
     * @param string|null $app_id Filter by application ID.
     * @return array
     */
    public function get_pending_approvals($app_id = null) {
        $sql = 'SELECT ia.*, a.app_name FROM ip_approvals ia 
                LEFT JOIN applications a ON ia.app_id = a.app_id 
                WHERE ia.status = ? AND ia.expires_at > ?';
        $params = array('pending', date('Y-m-d H:i:s'));

        if ($app_id) {
            $sql .= ' AND ia.app_id = ?';
            $params[] = $app_id;
        }

        $sql .= ' ORDER BY ia.requested_at DESC';

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Get all approvals for an application.
     *
     * @param string $app_id Application ID.
     * @return array
     */
    public function get_all_approvals($app_id) {
        return $this->db->query(
            'SELECT * FROM ip_approvals WHERE app_id = ? ORDER BY requested_at DESC',
            array($app_id)
        )->fetchAll();
    }

    /**
     * Send approval email to admin.
     *
     * @param string $app_id     Application ID.
     * @param string $ip_address IP address.
     * @param string $action     Requested action.
     * @param string $approval_code Approval code.
     */
    private function send_approval_email($app_id, $ip_address, $action, $approval_code) {
        $app = $this->db->query('SELECT app_name FROM applications WHERE app_id = ?', array($app_id))->fetch();
        $app_name = $app ? $app['app_name'] : 'Unknown';

        $admin_email = $this->db->get_setting('admin_email') ?: get_option('admin_email');
        $approval_url = admin_url('admin.php?page=plugins-onboard-applications&action=approve_ip&code=' . urlencode($approval_code));

        $subject = __('New IP Access Request - Plugins Onboard', 'plugins-onboard');

        $message = sprintf(
            __(
                "A new IP address is requesting access to your Plugins Onboard API.\n\n" .
                "Application: %s\n" .
                "IP Address: %s\n" .
                "Requested Action: %s\n" .
                "Timestamp: %s\n\n" .
                "APPROVE THIS REQUEST:\n%s\n\n" .
                "SECURITY NOTE:\n" .
                "If you don't recognize this application or IP, do not approve.\n" .
                "Only approve requests you initiated or expect.\n\n" .
                "For security, this email contains no sensitive data.\n" .
                "The approval code is single-use and expires after 24 hours.",
                'plugins-onboard'
            ),
            $app_name,
            $ip_address,
            $action,
            current_time('mysql'),
            $approval_url
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Send approval notification to app.
     *
     * @param string $app_id     Application ID.
     * @param string $ip_address IP address.
     * @param bool   $approved   Whether approved or rejected.
     */
    private function send_approval_notification($app_id, $ip_address, $approved) {
        // This could be extended to notify the application via webhook.
        // For now, we just log it.
        $this->audit_logger->log(
            $approved ? 'ip_approval_notified' : 'ip_rejection_notified',
            null,
            $app_id,
            $ip_address,
            'success'
        );
    }

    /**
     * Clean up expired approval requests.
     *
     * @return int Number of records deleted.
     */
    public function cleanup() {
        $now = date('Y-m-d H:i:s');

        $count_result = $this->db->query(
            'SELECT COUNT(*) as count FROM ip_approvals WHERE status = ? AND expires_at < ?',
            array('pending', $now)
        )->fetch();

        $this->db->query(
            'DELETE FROM ip_approvals WHERE status = ? AND expires_at < ?',
            array('pending', $now)
        );

        return (int) $count_result['count'];
    }

    /**
     * Approve by approval ID (from admin panel).
     *
     * @param string $approval_id Approval ID.
     * @return array|WP_Error
     */
    public function approve_by_id($approval_id) {
        $approval = $this->db->query(
            'SELECT * FROM ip_approvals WHERE approval_id = ? AND status = ?',
            array($approval_id, 'pending')
        )->fetch();

        if (!$approval) {
            return new WP_Error(
                'approval_not_found',
                'Approval request not found or already processed',
                array('status' => 404)
            );
        }

        // Decrypt approval code and use existing approve method.
        $approval_code = OnboardTokenEncryption::decrypt($approval['approval_code']);
        return $this->approve($approval_code);
    }

    /**
     * Reject by approval ID (from admin panel).
     *
     * @param string $approval_id Approval ID.
     * @return array|WP_Error
     */
    public function reject_by_id($approval_id) {
        $approval = $this->db->query(
            'SELECT * FROM ip_approvals WHERE approval_id = ? AND status = ?',
            array($approval_id, 'pending')
        )->fetch();

        if (!$approval) {
            return new WP_Error(
                'approval_not_found',
                'Approval request not found or already processed',
                array('status' => 404)
            );
        }

        $approval_code = OnboardTokenEncryption::decrypt($approval['approval_code']);
        return $this->reject($approval_code);
    }
}
