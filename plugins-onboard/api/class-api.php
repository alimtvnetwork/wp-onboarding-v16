<?php
/**
 * REST API class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardAPI
 *
 * Handles REST API endpoint registration and callbacks.
 */
class OnboardAPI {

    /**
     * Database instance.
     *
     * @var OnboardDatabase
     */
    private $db;

    /**
     * OAuth instance.
     *
     * @var OnboardOAuth
     */
    private $oauth;

    /**
     * Mutation token instance.
     *
     * @var OnboardMutationToken
     */
    private $mutation_token;

    /**
     * IP whitelist instance.
     *
     * @var OnboardIPWhitelist
     */
    private $ip_whitelist;

    /**
     * Snapshot instance.
     *
     * @var OnboardSnapshot
     */
    private $snapshot;

    /**
     * Backup manager instance.
     *
     * @var OnboardBackupManager
     */
    private $backup_manager;

    /**
     * Plugin manager instance.
     *
     * @var OnboardPluginManager
     */
    private $plugin_manager;

    /**
     * Audit logger instance.
     *
     * @var OnboardAuditLogger
     */
    private $audit_logger;

    /**
     * Constructor.
     */
    public function __construct(
        OnboardDatabase $db,
        OnboardOAuth $oauth,
        OnboardMutationToken $mutation_token,
        OnboardIPWhitelist $ip_whitelist,
        OnboardSnapshot $snapshot,
        OnboardBackupManager $backup_manager,
        OnboardPluginManager $plugin_manager,
        OnboardAuditLogger $audit_logger
    ) {
        $this->db = $db;
        $this->oauth = $oauth;
        $this->mutation_token = $mutation_token;
        $this->ip_whitelist = $ip_whitelist;
        $this->snapshot = $snapshot;
        $this->backup_manager = $backup_manager;
        $this->plugin_manager = $plugin_manager;
        $this->audit_logger = $audit_logger;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        $namespace = ONBOARD_API_NAMESPACE . '/' . ONBOARD_API_VERSION;

        // Authentication endpoints.
        register_rest_route($namespace, '/auth/request', array(
            'methods' => 'GET',
            'callback' => array($this, 'auth_request'),
            'permission_callback' => '__return_true',
            'args' => array(
                'client_id' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'redirect_uri' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'state' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route($namespace, '/auth/callback', array(
            'methods' => 'POST',
            'callback' => array($this, 'auth_callback'),
            'permission_callback' => '__return_true',
            'args' => array(
                'code' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'client_id' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'client_secret' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route($namespace, '/refresh-token', array(
            'methods' => 'POST',
            'callback' => array($this, 'refresh_token'),
            'permission_callback' => '__return_true',
            'args' => array(
                'refresh_token' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        // Mutation request endpoint.
        register_rest_route($namespace, '/request-mutation', array(
            'methods' => 'GET',
            'callback' => array($this, 'request_mutation'),
            'permission_callback' => array($this, 'verify_access_token'),
            'args' => array(
                'action' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => OnboardMutationToken::get_valid_actions(),
                ),
            ),
        ));

        // Plugin management endpoints.
        register_rest_route($namespace, '/plugins/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_plugins'),
            'permission_callback' => array($this, 'verify_access_token'),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/plugins/(?P<slug>[a-z0-9\-_]+)/enable', array(
            'methods' => 'POST',
            'callback' => array($this, 'enable_plugin'),
            'permission_callback' => array($this, 'verify_mutation_token_enable'),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/plugins/(?P<slug>[a-z0-9\-_]+)/disable', array(
            'methods' => 'POST',
            'callback' => array($this, 'disable_plugin'),
            'permission_callback' => array($this, 'verify_mutation_token_disable'),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/plugins/(?P<slug>[a-z0-9\-_]+)/delete', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_plugin'),
            'permission_callback' => array($this, 'verify_mutation_token_delete'),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/plugins/upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_plugin'),
            'permission_callback' => array($this, 'verify_mutation_token_upload'),
        ));

        // Backup endpoints.
        register_rest_route($namespace, '/plugins/(?P<slug>[a-z0-9\-_]+)/backups', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_backups'),
            'permission_callback' => array($this, 'verify_access_token'),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/plugins/(?P<slug>[a-z0-9\-_]+)/restore', array(
            'methods' => 'POST',
            'callback' => array($this, 'restore_plugin'),
            'permission_callback' => array($this, 'verify_mutation_token_restore'),
            'args' => array(
                'version' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'backup_date' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/plugins/(?P<slug>[a-z0-9\-_]+)/backup_manual', array(
            'methods' => 'POST',
            'callback' => array($this, 'manual_backup'),
            'permission_callback' => array($this, 'verify_mutation_token_backup'),
        ));

        // Download endpoints.
        register_rest_route($namespace, '/plugins/backups/download-all', array(
            'methods' => 'GET',
            'callback' => array($this, 'download_all_plugins'),
            'permission_callback' => array($this, 'verify_access_token'),
        ));

        register_rest_route($namespace, '/plugins/backups/download-active', array(
            'methods' => 'GET',
            'callback' => array($this, 'download_active_plugins'),
            'permission_callback' => array($this, 'verify_access_token'),
        ));

        register_rest_route($namespace, '/plugins/backups/download-snapshots', array(
            'methods' => 'GET',
            'callback' => array($this, 'download_snapshots'),
            'permission_callback' => array($this, 'verify_access_token'),
        ));

        // Debug & Maintenance endpoints.
        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/debug/enable', array(
            'methods' => 'POST',
            'callback' => array($this, 'enable_debug'),
            'permission_callback' => array($this, 'verify_mutation_token_debug'),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/debug/disable', array(
            'methods' => 'POST',
            'callback' => array($this, 'disable_debug'),
            'permission_callback' => array($this, 'verify_mutation_token_debug'),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/maintenance/enable', array(
            'methods' => 'POST',
            'callback' => array($this, 'enable_maintenance'),
            'permission_callback' => array($this, 'verify_mutation_token_maintenance'),
        ));

        register_rest_route($namespace, '/mutations/(?P<mutation_token>[a-f0-9]+)/maintenance/disable', array(
            'methods' => 'POST',
            'callback' => array($this, 'disable_maintenance'),
            'permission_callback' => array($this, 'verify_mutation_token_maintenance'),
        ));

        // Database endpoints.
        register_rest_route($namespace, '/database/info', array(
            'methods' => 'GET',
            'callback' => array($this, 'database_info'),
            'permission_callback' => array($this, 'verify_access_token'),
        ));

        register_rest_route($namespace, '/database/download', array(
            'methods' => 'GET',
            'callback' => array($this, 'download_database'),
            'permission_callback' => array($this, 'verify_access_token'),
        ));

        register_rest_route($namespace, '/temp-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'temp_info'),
            'permission_callback' => array($this, 'verify_access_token'),
        ));

        // Audit log endpoints.
        register_rest_route($namespace, '/audit-logs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_audit_logs'),
            'permission_callback' => array($this, 'verify_access_token'),
            'args' => array(
                'limit' => array(
                    'default' => 50,
                    'type' => 'integer',
                ),
                'offset' => array(
                    'default' => 0,
                    'type' => 'integer',
                ),
                'action' => array(
                    'type' => 'string',
                ),
                'status' => array(
                    'type' => 'string',
                ),
            ),
        ));
    }

    /**
     * Verify access token permission callback.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function verify_access_token($request) {
        // Check HTTPS requirement.
        if (ONBOARD_REQUIRE_HTTPS && !onboard_is_https()) {
            return new WP_Error('https_required', 'HTTPS is required', array('status' => 403));
        }

        $auth_header = $request->get_header('Authorization');
        if (!$auth_header || strpos($auth_header, 'Bearer ') !== 0) {
            return new WP_Error('missing_token', 'Missing Authorization header', array('status' => 401));
        }

        $token = substr($auth_header, 7);
        $decoded = $this->oauth->validate_access_token($token);

        if (!$decoded) {
            return new WP_Error('invalid_token', 'Invalid or expired access token', array('status' => 401));
        }

        // Store decoded token in request for later use.
        $request->set_param('_decoded_token', $decoded);
        $request->set_param('_app_id', $decoded['app_id']);

        return true;
    }

    /**
     * Create mutation token verification callback.
     *
     * @param string $expected_action Expected action.
     * @return callable
     */
    private function create_mutation_verifier($expected_action) {
        return function($request) use ($expected_action) {
            $token = $request->get_param('mutation_token');
            if (!$token) {
                return new WP_Error('missing_mutation_token', 'Missing mutation token', array('status' => 401));
            }

            $ip_address = onboard_get_client_ip();
            $result = $this->mutation_token->validate_and_consume($token, $expected_action, $ip_address);

            if (is_wp_error($result)) {
                return $result;
            }

            $request->set_param('_mutation_data', $result);
            $request->set_param('_app_id', $result['app_id']);

            return true;
        };
    }

    // Mutation token verifiers.
    public function verify_mutation_token_enable($request) {
        return $this->create_mutation_verifier('enable')($request);
    }

    public function verify_mutation_token_disable($request) {
        return $this->create_mutation_verifier('disable')($request);
    }

    public function verify_mutation_token_delete($request) {
        return $this->create_mutation_verifier('delete')($request);
    }

    public function verify_mutation_token_upload($request) {
        return $this->create_mutation_verifier('upload')($request);
    }

    public function verify_mutation_token_restore($request) {
        return $this->create_mutation_verifier('restore')($request);
    }

    public function verify_mutation_token_backup($request) {
        return $this->create_mutation_verifier('backup_manual')($request);
    }

    public function verify_mutation_token_debug($request) {
        $token = $request->get_param('mutation_token');
        $ip_address = onboard_get_client_ip();

        // Try both debug_enable and debug_disable.
        $result = $this->mutation_token->validate_and_consume($token, 'debug_enable', $ip_address);
        if (is_wp_error($result)) {
            $result = $this->mutation_token->validate_and_consume($token, 'debug_disable', $ip_address);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $request->set_param('_mutation_data', $result);
        $request->set_param('_app_id', $result['app_id']);

        return true;
    }

    public function verify_mutation_token_maintenance($request) {
        $token = $request->get_param('mutation_token');
        $ip_address = onboard_get_client_ip();

        // Try both maintenance_enable and maintenance_disable.
        $result = $this->mutation_token->validate_and_consume($token, 'maintenance_enable', $ip_address);
        if (is_wp_error($result)) {
            $result = $this->mutation_token->validate_and_consume($token, 'maintenance_disable', $ip_address);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $request->set_param('_mutation_data', $result);
        $request->set_param('_app_id', $result['app_id']);

        return true;
    }

    /**
     * Handle auth request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function auth_request($request) {
        $client_id = $request->get_param('client_id');
        $redirect_uri = $request->get_param('redirect_uri');
        $state = $request->get_param('state');

        // Rate limit.
        if (!OnboardRateLimiter::is_allowed(onboard_get_client_ip(), 'auth')) {
            return new WP_Error('rate_limit_exceeded', 'Too many authentication requests', array('status' => 429));
        }

        // Validate client.
        $app = $this->oauth->get_application_by_client_id($client_id);
        if (!$app) {
            return new WP_Error('invalid_client', 'Invalid client ID', array('status' => 400));
        }

        if ($app['redirect_uri'] !== $redirect_uri) {
            return new WP_Error('invalid_redirect', 'Redirect URI mismatch', array('status' => 400));
        }

        // Generate authorization code.
        $auth_data = $this->oauth->generate_auth_code($app['app_id'], $state);

        return rest_ensure_response(array(
            'auth_code' => $auth_data['auth_code'],
            'state' => $auth_data['state'],
            'redirect_uri' => $redirect_uri . '?code=' . $auth_data['auth_code'] . ($state ? '&state=' . $state : ''),
            'expires_in' => $auth_data['expires_in'],
        ));
    }

    /**
     * Handle auth callback (exchange code for tokens).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function auth_callback($request) {
        $code = $request->get_param('code');
        $client_id = $request->get_param('client_id');
        $client_secret = $request->get_param('client_secret');

        $result = $this->oauth->exchange_code($code, $client_id, $client_secret);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Handle token refresh.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function refresh_token($request) {
        $refresh_token = $request->get_param('refresh_token');

        $result = $this->oauth->refresh_tokens($refresh_token);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Handle mutation token request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function request_mutation($request) {
        $decoded = $request->get_param('_decoded_token');
        $action = $request->get_param('action');
        $ip_address = onboard_get_client_ip();

        // Check IP whitelist.
        if (ONBOARD_IP_WHITELIST_ENABLED && !ONBOARD_IP_AUTO_APPROVE) {
            if (!$this->ip_whitelist->is_whitelisted($decoded['app_id'], $ip_address)) {
                // Request approval.
                $approval = $this->ip_whitelist->request_approval($decoded['app_id'], $ip_address, $action);
                return new WP_Error(
                    'ip_pending_approval',
                    $approval['message'],
                    array('status' => 403)
                );
            }
        }

        // Generate mutation token.
        $result = $this->mutation_token->generate($decoded['app_id'], $action, $ip_address);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * List all plugins.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function list_plugins($request) {
        $plugins = $this->plugin_manager->get_all_plugins();
        return rest_ensure_response(array(
            'total' => count($plugins),
            'plugins' => $plugins,
        ));
    }

    /**
     * Enable a plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function enable_plugin($request) {
        $slug = $request->get_param('slug');
        $mutation_data = $request->get_param('_mutation_data');

        $result = $this->plugin_manager->enable(
            $slug,
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Disable a plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function disable_plugin($request) {
        $slug = $request->get_param('slug');
        $mutation_data = $request->get_param('_mutation_data');

        $result = $this->plugin_manager->disable(
            $slug,
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Delete a plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_plugin($request) {
        $slug = $request->get_param('slug');
        $mutation_data = $request->get_param('_mutation_data');

        $result = $this->plugin_manager->delete(
            $slug,
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Upload a plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function upload_plugin($request) {
        $files = $request->get_file_params();
        if (!isset($files['file'])) {
            return new WP_Error('no_file', 'No file uploaded', array('status' => 400));
        }

        $mutation_data = $request->get_param('_mutation_data');
        $auto_backup = $request->get_param('auto_backup') !== 'false';

        $result = $this->plugin_manager->upload(
            $files['file'],
            $auto_backup,
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * List backups for a plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function list_backups($request) {
        $slug = $request->get_param('slug');
        $snapshots = $this->snapshot->get_snapshots($slug);

        return rest_ensure_response(array(
            'plugin_slug' => $slug,
            'total_snapshots' => count($snapshots),
            'snapshots' => $snapshots,
        ));
    }

    /**
     * Restore a plugin from backup.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function restore_plugin($request) {
        $slug = $request->get_param('slug');
        $version = $request->get_param('version');
        $backup_date = $request->get_param('backup_date');
        $mutation_data = $request->get_param('_mutation_data');

        // Find snapshot.
        $snapshot = $this->snapshot->get_snapshot_by_version($slug, $version, $backup_date);
        if (!$snapshot) {
            return new WP_Error('snapshot_not_found', 'Snapshot not found', array('status' => 404));
        }

        $result = $this->snapshot->restore(
            $snapshot['snapshot_id'],
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Create manual backup.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function manual_backup($request) {
        $slug = $request->get_param('slug');
        $mutation_data = $request->get_param('_mutation_data');

        $result = $this->snapshot->create(
            $slug,
            'manual',
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Download all plugins.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function download_all_plugins($request) {
        $file_path = $this->backup_manager->download_all_plugins();

        if (is_wp_error($file_path)) {
            return $file_path;
        }

        return rest_ensure_response(array(
            'download_url' => str_replace(ABSPATH, home_url('/'), $file_path),
            'file_path' => $file_path,
            'expires_in' => ONBOARD_TEMP_CLEANUP_DAYS * 86400,
        ));
    }

    /**
     * Download active plugins.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function download_active_plugins($request) {
        $file_path = $this->backup_manager->download_active_plugins();

        if (is_wp_error($file_path)) {
            return $file_path;
        }

        return rest_ensure_response(array(
            'download_url' => str_replace(ABSPATH, home_url('/'), $file_path),
            'file_path' => $file_path,
            'expires_in' => ONBOARD_TEMP_CLEANUP_DAYS * 86400,
        ));
    }

    /**
     * Download snapshots.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function download_snapshots($request) {
        $file_path = $this->backup_manager->download_snapshots();

        if (is_wp_error($file_path)) {
            return $file_path;
        }

        return rest_ensure_response(array(
            'download_url' => str_replace(ABSPATH, home_url('/'), $file_path),
            'file_path' => $file_path,
            'expires_in' => ONBOARD_TEMP_CLEANUP_DAYS * 86400,
        ));
    }

    /**
     * Enable debug mode.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function enable_debug($request) {
        $mutation_data = $request->get_param('_mutation_data');
        $result = OnboardDebugMaintenance::enable_debug(
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $this->audit_logger->log(
            'debug_mode_enabled',
            null,
            $mutation_data['app_id'],
            $mutation_data['ip_address'],
            'success'
        );

        return rest_ensure_response($result);
    }

    /**
     * Disable debug mode.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function disable_debug($request) {
        $mutation_data = $request->get_param('_mutation_data');
        $result = OnboardDebugMaintenance::disable_debug(
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $this->audit_logger->log(
            'debug_mode_disabled',
            null,
            $mutation_data['app_id'],
            $mutation_data['ip_address'],
            'success'
        );

        return rest_ensure_response($result);
    }

    /**
     * Enable maintenance mode.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function enable_maintenance($request) {
        $mutation_data = $request->get_param('_mutation_data');
        $result = OnboardDebugMaintenance::enable_maintenance(
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $this->audit_logger->log(
            'maintenance_mode_enabled',
            null,
            $mutation_data['app_id'],
            $mutation_data['ip_address'],
            'success'
        );

        return rest_ensure_response($result);
    }

    /**
     * Disable maintenance mode.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function disable_maintenance($request) {
        $mutation_data = $request->get_param('_mutation_data');
        $result = OnboardDebugMaintenance::disable_maintenance(
            $mutation_data['app_id'],
            $mutation_data['ip_address']
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $this->audit_logger->log(
            'maintenance_mode_disabled',
            null,
            $mutation_data['app_id'],
            $mutation_data['ip_address'],
            'success'
        );

        return rest_ensure_response($result);
    }

    /**
     * Get database info.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function database_info($request) {
        $info = $this->backup_manager->get_database_info();
        return rest_ensure_response($info);
    }

    /**
     * Download databases.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function download_database($request) {
        $file_path = $this->backup_manager->download_databases();

        if (is_wp_error($file_path)) {
            return $file_path;
        }

        return rest_ensure_response(array(
            'download_url' => str_replace(ABSPATH, home_url('/'), $file_path),
            'file_path' => $file_path,
            'expires_in' => ONBOARD_TEMP_CLEANUP_DAYS * 86400,
        ));
    }

    /**
     * Get temp directory info.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function temp_info($request) {
        $info = $this->backup_manager->get_temp_info();
        return rest_ensure_response($info);
    }

    /**
     * Get audit logs.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_audit_logs($request) {
        $limit = $request->get_param('limit');
        $offset = $request->get_param('offset');
        $filters = array(
            'action' => $request->get_param('action'),
            'status' => $request->get_param('status'),
            'app_id' => $request->get_param('app_id'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
        );

        $logs = $this->audit_logger->get_logs(array_filter($filters), $limit, $offset);
        $total = $this->audit_logger->get_log_count(array_filter($filters));

        return rest_ensure_response(array(
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'logs' => $logs,
        ));
    }
}
