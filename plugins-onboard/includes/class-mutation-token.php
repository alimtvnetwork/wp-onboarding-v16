<?php
/**
 * Mutation Token class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardMutationToken
 *
 * Handles ephemeral mutation tokens.
 */
class OnboardMutationToken {

    /**
     * Valid actions.
     */
    const VALID_ACTIONS = array(
        'enable',
        'disable',
        'delete',
        'upload',
        'restore',
        'debug_enable',
        'debug_disable',
        'maintenance_enable',
        'maintenance_disable',
        'backup_manual',
    );

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
     * Generate a mutation token.
     *
     * @param string $app_id     Application ID.
     * @param string $action     Requested action.
     * @param string $ip_address Requester IP address.
     * @return array|WP_Error
     */
    public function generate($app_id, $action, $ip_address) {
        // Validate action.
        if (!in_array($action, self::VALID_ACTIONS, true)) {
            return new WP_Error(
                'invalid_action',
                'Invalid action: ' . $action . '. Valid actions: ' . implode(', ', self::VALID_ACTIONS),
                array('status' => 400)
            );
        }

        // Check rate limit.
        if (!OnboardRateLimiter::is_allowed($app_id, 'mutation')) {
            $this->audit_logger->log(
                'mutation_token_rate_limited',
                null,
                $app_id,
                $ip_address,
                'failed',
                array('action' => $action),
                'Rate limit exceeded'
            );

            return new WP_Error(
                'rate_limit_exceeded',
                'Too many mutation requests. Please try again later.',
                array('status' => 429)
            );
        }

        $token_id = $this->db->generate_uuid();
        $token = OnboardTokenEncryption::generate_token(16);
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + ONBOARD_MUTATION_TOKEN_TTL);

        $this->db->query(
            'INSERT INTO mutation_tokens (token_id, app_id, token, action, ip_address, issued_at, expires_at, used) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $token_id,
                $app_id,
                OnboardTokenEncryption::encrypt($token),
                $action,
                $ip_address,
                $now,
                $expires,
                0,
            )
        );

        $this->audit_logger->log(
            'mutation_token_generated',
            null,
            $app_id,
            $ip_address,
            'success',
            array('action' => $action, 'expires_in' => ONBOARD_MUTATION_TOKEN_TTL)
        );

        // Build mutation endpoint based on action.
        $endpoint = $this->build_mutation_endpoint($token, $action);

        return array(
            'mutation_token' => $token,
            'mutation_endpoint' => $endpoint,
            'expires_in' => ONBOARD_MUTATION_TOKEN_TTL,
            'action' => $action,
        );
    }

    /**
     * Build mutation endpoint URL.
     *
     * @param string $token  Mutation token.
     * @param string $action Action.
     * @return string
     */
    private function build_mutation_endpoint($token, $action) {
        $base = rest_url(ONBOARD_API_NAMESPACE . '/' . ONBOARD_API_VERSION . '/mutations/' . $token);

        switch ($action) {
            case 'upload':
                return $base . '/plugins/upload';
            case 'enable':
            case 'disable':
            case 'delete':
            case 'restore':
            case 'backup_manual':
                return $base . '/plugins/{slug}/' . $action;
            case 'debug_enable':
            case 'debug_disable':
                return $base . '/debug/' . str_replace('debug_', '', $action);
            case 'maintenance_enable':
            case 'maintenance_disable':
                return $base . '/maintenance/' . str_replace('maintenance_', '', $action);
            default:
                return $base;
        }
    }

    /**
     * Validate and consume a mutation token.
     *
     * @param string $token      Mutation token.
     * @param string $action     Expected action.
     * @param string $ip_address Requester IP address.
     * @return array|WP_Error Token data or error.
     */
    public function validate_and_consume($token, $action, $ip_address) {
        // Find token in database.
        $tokens = $this->db->query('SELECT * FROM mutation_tokens WHERE used = 0 AND expires_at > ?', array(date('Y-m-d H:i:s')))->fetchAll();
        $token_record = null;

        foreach ($tokens as $record) {
            $decrypted = OnboardTokenEncryption::decrypt($record['token']);
            if ($decrypted === $token) {
                $token_record = $record;
                break;
            }
        }

        if (!$token_record) {
            return new WP_Error(
                'invalid_mutation_token',
                'Invalid or expired mutation token',
                array('status' => 401)
            );
        }

        // Verify IP matches.
        if ($token_record['ip_address'] !== $ip_address) {
            $this->audit_logger->log(
                'mutation_token_ip_mismatch',
                null,
                $token_record['app_id'],
                $ip_address,
                'failed',
                array(
                    'expected_ip' => $token_record['ip_address'],
                    'actual_ip' => $ip_address,
                ),
                'IP address mismatch'
            );

            return new WP_Error(
                'ip_mismatch',
                'IP address does not match token',
                array('status' => 403)
            );
        }

        // Verify action matches.
        if ($token_record['action'] !== $action) {
            $this->audit_logger->log(
                'mutation_token_action_mismatch',
                null,
                $token_record['app_id'],
                $ip_address,
                'failed',
                array(
                    'expected_action' => $token_record['action'],
                    'actual_action' => $action,
                ),
                'Action mismatch'
            );

            return new WP_Error(
                'action_mismatch',
                'Token action does not match request',
                array('status' => 403)
            );
        }

        // Mark token as used and delete it (one-time use).
        $this->db->query(
            'DELETE FROM mutation_tokens WHERE token_id = ?',
            array($token_record['token_id'])
        );

        $this->audit_logger->log(
            'mutation_token_consumed',
            null,
            $token_record['app_id'],
            $ip_address,
            'success',
            array('action' => $action),
            null,
            $token
        );

        return array(
            'app_id' => $token_record['app_id'],
            'action' => $token_record['action'],
            'ip_address' => $token_record['ip_address'],
        );
    }

    /**
     * Get valid actions.
     *
     * @return array
     */
    public static function get_valid_actions() {
        return self::VALID_ACTIONS;
    }

    /**
     * Clean up expired tokens.
     *
     * @return int Number of tokens deleted.
     */
    public function cleanup() {
        $now = date('Y-m-d H:i:s');

        $count_result = $this->db->query(
            'SELECT COUNT(*) as count FROM mutation_tokens WHERE expires_at < ?',
            array($now)
        )->fetch();

        $this->db->query('DELETE FROM mutation_tokens WHERE expires_at < ?', array($now));

        return (int) $count_result['count'];
    }

    /**
     * Get active tokens for an application.
     *
     * @param string $app_id Application ID.
     * @return array
     */
    public function get_active_tokens($app_id) {
        return $this->db->query(
            'SELECT token_id, action, ip_address, issued_at, expires_at FROM mutation_tokens WHERE app_id = ? AND used = 0 AND expires_at > ?',
            array($app_id, date('Y-m-d H:i:s'))
        )->fetchAll();
    }

    /**
     * Revoke all tokens for an application.
     *
     * @param string $app_id Application ID.
     * @return int Number of tokens revoked.
     */
    public function revoke_all($app_id) {
        $count_result = $this->db->query(
            'SELECT COUNT(*) as count FROM mutation_tokens WHERE app_id = ?',
            array($app_id)
        )->fetch();

        $this->db->query('DELETE FROM mutation_tokens WHERE app_id = ?', array($app_id));

        return (int) $count_result['count'];
    }
}
