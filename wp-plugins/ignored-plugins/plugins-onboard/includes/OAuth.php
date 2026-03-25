<?php
/**
 * OAuth class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardOAuth
 *
 * Handles OAuth 2.0 authentication.
 */
class OnboardOAuth {

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
     * Create a new application.
     *
     * @param array $data Application data.
     * @return array Created application.
     */
    public function create_application($data) {
        $app_id = $this->db->generate_uuid();
        $client_id = OnboardTokenEncryption::generate_token(16);
        $client_secret = OnboardTokenEncryption::generate_token(32);
        $now = date('Y-m-d H:i:s');

        $this->db->query(
            'INSERT INTO applications (app_id, client_id, client_secret, app_name, description, redirect_uri, created_at, status, ip_whitelist, scopes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $app_id,
                $client_id,
                OnboardTokenEncryption::hash_secret($client_secret),
                $data['app_name'],
                isset($data['description']) ? $data['description'] : '',
                $data['redirect_uri'],
                $now,
                'active',
                json_encode(array()),
                json_encode(isset($data['scopes']) ? $data['scopes'] : array('onboard:plugin_manage', 'onboard:backup')),
            )
        );

        $this->audit_logger->log('application_created', null, $app_id, null, 'success', array(
            'app_name' => $data['app_name'],
        ));

        return array(
            'app_id' => $app_id,
            'client_id' => $client_id,
            'client_secret' => $client_secret, // Only returned once.
            'app_name' => $data['app_name'],
            'redirect_uri' => $data['redirect_uri'],
            'created_at' => $now,
        );
    }

    /**
     * Get application by client ID.
     *
     * @param string $client_id Client ID.
     * @return array|null
     */
    public function get_application_by_client_id($client_id) {
        return $this->db->query(
            'SELECT * FROM applications WHERE client_id = ? AND status = ?',
            array($client_id, 'active')
        )->fetch();
    }

    /**
     * Get application by ID.
     *
     * @param string $app_id Application ID.
     * @return array|null
     */
    public function get_application($app_id) {
        return $this->db->query(
            'SELECT * FROM applications WHERE app_id = ?',
            array($app_id)
        )->fetch();
    }

    /**
     * Get all applications.
     *
     * @return array
     */
    public function get_all_applications() {
        return $this->db->query('SELECT * FROM applications ORDER BY created_at DESC')->fetchAll();
    }

    /**
     * Update application.
     *
     * @param string $app_id Application ID.
     * @param array  $data   Data to update.
     * @return bool
     */
    public function update_application($app_id, $data) {
        $fields = array();
        $params = array();

        $allowed = array('app_name', 'description', 'redirect_uri', 'status', 'scopes');

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = ?';
        $params[] = date('Y-m-d H:i:s');
        $params[] = $app_id;

        $sql = 'UPDATE applications SET ' . implode(', ', $fields) . ' WHERE app_id = ?';
        $this->db->query($sql, $params);

        $this->audit_logger->log('application_updated', null, $app_id, null, 'success', $data);

        return true;
    }

    /**
     * Delete application.
     *
     * @param string $app_id Application ID.
     * @return bool
     */
    public function delete_application($app_id) {
        $app = $this->get_application($app_id);
        $isAppMissing = !$app;

        if ($isAppMissing) {
            return false;
        }

        $this->db->query('DELETE FROM applications WHERE app_id = ?', array($app_id));

        $this->audit_logger->log('application_deleted', null, $app_id, null, 'success', array(
            'app_name' => $app['app_name'],
        ));

        return true;
    }

    /**
     * Verify client credentials.
     *
     * @param string $client_id     Client ID.
     * @param string $client_secret Client secret.
     * @return array|false Application if valid, false otherwise.
     */
    public function verify_credentials($client_id, $client_secret) {
        $app = $this->get_application_by_client_id($client_id);
        $isAppMissing = !$app;

        if ($isAppMissing) {
            return false;
        }

        $isSecretInvalid = !OnboardTokenEncryption::verify_secret($client_secret, $app['client_secret']);

        if ($isSecretInvalid) {
            return false;
        }

        return $app;
    }

    /**
     * Generate authorization code.
     *
     * @param string      $app_id Application ID.
     * @param string|null $state  State parameter.
     * @return array
     */
    public function generate_auth_code($app_id, $state = null) {
        $code_id = $this->db->generate_uuid();
        $auth_code = OnboardTokenEncryption::generate_token(16);
        $now = date('Y-m-d H:i:s');
        
        // Use constant with safe default.
        $auth_code_ttl = defined('ONBOARD_AUTH_CODE_TTL') ? ONBOARD_AUTH_CODE_TTL : 600;
        $expires = date('Y-m-d H:i:s', time() + $auth_code_ttl);

        $this->db->query(
            'INSERT INTO oauth_codes (code_id, app_id, auth_code, state, issued_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
            array($code_id, $app_id, $auth_code, $state, $now, $expires)
        );

        return array(
            'auth_code' => $auth_code,
            'state' => $state,
            'expires_in' => $auth_code_ttl,
        );
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param string $auth_code     Authorization code.
     * @param string $client_id     Client ID.
     * @param string $client_secret Client secret.
     * @return array|WP_Error
     */
    public function exchange_code($auth_code, $client_id, $client_secret) {
        // Verify credentials.
        $app = $this->verify_credentials($client_id, $client_secret);
        $isCredentialsInvalid = !$app;

        if ($isCredentialsInvalid) {
            return new WP_Error('invalid_credentials', 'Invalid client credentials', array('status' => 401));
        }

        // Get and validate code.
        $code = $this->db->query(
            'SELECT * FROM oauth_codes WHERE auth_code = ? AND app_id = ? AND used = 0 AND expires_at > ?',
            array($auth_code, $app['app_id'], date('Y-m-d H:i:s'))
        )->fetch();

        $isCodeInvalid = !$code;

        if ($isCodeInvalid) {
            return new WP_Error('invalid_code', 'Invalid or expired authorization code', array('status' => 400));
        }

        // Mark code as used.
        $this->db->query(
            'UPDATE oauth_codes SET used = 1 WHERE code_id = ?',
            array($code['code_id'])
        );

        // Generate tokens.
        return $this->generate_tokens($app);
    }

    /**
     * Generate access and refresh tokens.
     *
     * @param array $app Application data.
     * @return array
     */
    public function generate_tokens($app) {
        $token_id = $this->db->generate_uuid();
        $now = date('Y-m-d H:i:s');
        
        // Use constants with safe defaults.
        $access_ttl = defined('ONBOARD_ACCESS_TOKEN_TTL') ? ONBOARD_ACCESS_TOKEN_TTL : 3600;
        $refresh_ttl = defined('ONBOARD_REFRESH_TOKEN_TTL') ? ONBOARD_REFRESH_TOKEN_TTL : 2592000;
        
        $access_expires = date('Y-m-d H:i:s', time() + $access_ttl);
        $refresh_expires = date('Y-m-d H:i:s', time() + $refresh_ttl);

        $scopes = json_decode($app['scopes'], true) ?: array();

        // Generate JWT access token.
        $access_token = OnboardTokenEncryption::generate_jwt(array(
            'app_id' => $app['app_id'],
            'client_id' => $app['client_id'],
            'scopes' => $scopes,
        ), $access_ttl);

        // Generate refresh token.
        $refresh_token = OnboardTokenEncryption::generate_token(32);

        // Store tokens.
        $this->db->query(
            'INSERT INTO oauth_tokens (token_id, app_id, access_token, refresh_token, token_type, scopes, issued_at, access_expires_at, refresh_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $token_id,
                $app['app_id'],
                OnboardTokenEncryption::encrypt($access_token),
                OnboardTokenEncryption::encrypt($refresh_token),
                'Bearer',
                json_encode($scopes),
                $now,
                $access_expires,
                $refresh_expires,
            )
        );

        $this->audit_logger->log('tokens_generated', null, $app['app_id'], null, 'success');

        return array(
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'token_type' => 'Bearer',
            'expires_in' => $access_ttl,
            'scopes' => $scopes,
        );
    }

    /**
     * Refresh access token.
     *
     * @param string $refresh_token Refresh token.
     * @return array|WP_Error
     */
    public function refresh_tokens($refresh_token) {
        // Find token record.
        $tokens = $this->db->query('SELECT * FROM oauth_tokens')->fetchAll();
        $token_record = null;

        foreach ($tokens as $record) {
            $decrypted = OnboardTokenEncryption::decrypt($record['refresh_token']);
            if ($decrypted === $refresh_token) {
                $token_record = $record;
                break;
            }
        }

        $isTokenRecordMissing = !$token_record;

        if ($isTokenRecordMissing) {
            return new WP_Error('invalid_refresh_token', 'Invalid refresh token', array('status' => 401));
        }

        // Check expiry.
        $isRefreshExpired = strtotime($token_record['refresh_expires_at']) < time();

        if ($isRefreshExpired) {
            return new WP_Error('refresh_token_expired', 'Refresh token expired', array('status' => 401));
        }

        // Get application.
        $app = $this->get_application($token_record['app_id']);
        $isAppMissing = !$app;

        if ($isAppMissing) {
            return new WP_Error('app_not_found', 'Application not found', array('status' => 400));
        }

        // Delete old token.
        $this->db->query('DELETE FROM oauth_tokens WHERE token_id = ?', array($token_record['token_id']));

        // Generate new tokens.
        return $this->generate_tokens($app);
    }

    /**
     * Validate access token.
     *
     * @param string $access_token Access token.
     * @return array|false Decoded token or false.
     */
    public function validate_access_token($access_token) {
        $decoded = OnboardTokenEncryption::verify_jwt($access_token);
        $isTokenInvalid = !$decoded;

        if ($isTokenInvalid) {
            return false;
        }

        // Verify application exists and is active.
        $app = $this->get_application($decoded['app_id']);
        $isAppMissing = !$app;
        $isAppInactive = $app && $app['status'] !== 'active';
        $isAppUnavailable = $isAppMissing || $isAppInactive;

        if ($isAppUnavailable) {
            return false;
        }

        return $decoded;
    }

    /**
     * Revoke all tokens for an application.
     *
     * @param string $app_id Application ID.
     * @return int Number of tokens revoked.
     */
    public function revoke_all_tokens($app_id) {
        $count_result = $this->db->query(
            'SELECT COUNT(*) as count FROM oauth_tokens WHERE app_id = ?',
            array($app_id)
        )->fetch();

        $this->db->query('DELETE FROM oauth_tokens WHERE app_id = ?', array($app_id));

        $this->audit_logger->log('tokens_revoked', null, $app_id, null, 'success', array(
            'count' => $count_result['count'],
        ));

        return (int) $count_result['count'];
    }

    /**
     * Clean up expired codes and tokens.
     */
    public function cleanup() {
        $now = date('Y-m-d H:i:s');

        // Delete expired authorization codes.
        $this->db->query('DELETE FROM oauth_codes WHERE expires_at < ?', array($now));

        // Delete expired refresh tokens (access tokens will be invalid but we keep record until refresh expires).
        $this->db->query('DELETE FROM oauth_tokens WHERE refresh_expires_at < ?', array($now));
    }

    /**
     * Regenerate client secret.
     *
     * @param string $app_id Application ID.
     * @return string|false New client secret or false.
     */
    public function regenerate_secret($app_id) {
        $app = $this->get_application($app_id);
        $isAppMissing = !$app;

        if ($isAppMissing) {
            return false;
        }

        $new_secret = OnboardTokenEncryption::generate_token(32);
        $hashed = OnboardTokenEncryption::hash_secret($new_secret);

        $this->db->query(
            'UPDATE applications SET client_secret = ?, updated_at = ? WHERE app_id = ?',
            array($hashed, date('Y-m-d H:i:s'), $app_id)
        );

        // Revoke all existing tokens.
        $this->revoke_all_tokens($app_id);

        $this->audit_logger->log('client_secret_regenerated', null, $app_id, null, 'success');

        return $new_secret;
    }
}
