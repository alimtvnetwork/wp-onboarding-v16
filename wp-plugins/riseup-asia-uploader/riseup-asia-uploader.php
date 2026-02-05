<?php
/**
 * Plugin Name: Riseup Asia Uploader
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Remote plugin management, blog post publishing, delta file sync, and audit logging via REST API with Application Password authentication.
 * Version: 1.6.0
 * Author: MD ALIM UL KARIM
 * Author URI: https://rasia.pro/alim-r-profile-v1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: riseup-asia-uploader
 * Requires at least: 5.6
 * Requires PHP: 7.4
 *
 * @package RiseupAsiaUploader
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// GLOBAL ERROR HANDLER FOR JSON RESPONSES
// =============================================================================

/**
 * Custom error handler to catch fatal errors and return JSON response.
 * This ensures API consumers get proper error responses instead of HTML stack traces.
 */
function riseup_fatal_error_handler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
        // Only handle if this is a REST API request to our namespace
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($request_uri, 'riseup-asia-uploader') !== false || strpos($request_uri, 'wp-json') !== false) {
            // Clean any output
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Set proper headers
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('HTTP/1.1 500 Internal Server Error');
            }
            
            // Generate a pseudo stack trace from the error location
            $stack_trace = "#0 " . $error['file'] . "(" . $error['line'] . "): Fatal error occurred\n";
            $stack_trace .= "#1 [internal function]: PHP shutdown handler";
            
            // Return JSON error response with full details
            echo json_encode(array(
                'success' => false,
                'error' => array(
                    'code' => 'FATAL_ERROR',
                    'message' => 'A fatal error occurred in the plugin',
                    'details' => array(
                        'type'        => $error['type'],
                        'typeName'    => riseup_error_type_to_string($error['type']),
                        'message'     => $error['message'],
                        'file'        => basename($error['file']),
                        'fileFull'    => $error['file'],
                        'line'        => $error['line'],
                        'stackTrace'  => $stack_trace,
                        'phpVersion'  => phpversion(),
                        'wpVersion'   => defined('WP_VERSION') ? WP_VERSION : 'unknown',
                    ),
                ),
            ));
            exit;
        }
    }
}

/**
 * Convert PHP error type to human-readable string.
 *
 * @param int $type Error type constant.
 * @return string
 */
function riseup_error_type_to_string($type) {
    $types = array(
        E_ERROR             => 'E_ERROR',
        E_PARSE             => 'E_PARSE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_WARNING           => 'E_WARNING',
        E_NOTICE            => 'E_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
    );
    return isset($types[$type]) ? $types[$type] : 'UNKNOWN_ERROR_TYPE';
}
register_shutdown_function('riseup_fatal_error_handler');

// =============================================================================
// LOAD DEPENDENCIES IN ORDER
// =============================================================================

// Load constants first (must be before any other includes).
require_once __DIR__ . '/includes/constants.php';

// Load file logger second (used by all other classes).
require_once __DIR__ . '/includes/class-file-logger.php';

// Load ORM before database.
require_once __DIR__ . '/includes/class-orm.php';

// Load database (depends on file logger and ORM).
require_once __DIR__ . '/includes/class-database.php';

// Load transaction logger (depends on database and file logger).
require_once __DIR__ . '/includes/class-logger.php';

// Load other classes.
require_once __DIR__ . '/includes/class-post-manager.php';
require_once __DIR__ . '/includes/class-upload-ignore.php';
require_once __DIR__ . '/includes/class-admin.php';

// =============================================================================
// PLUGIN CLASS
// =============================================================================

/**
 * Main plugin class.
 */
class Riseup_Asia {

    /**
     * File logger instance.
     *
     * @var Riseup_File_Logger
     */
    private $file_logger;

    /**
     * Transaction logger instance.
     *
     * @var Riseup_Logger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var Riseup_Database
     */
    private $db;

    /**
     * Post manager instance.
     *
     * @var Riseup_Post_Manager
     */
    private $post_manager;

    /**
     * Singleton instance.
     *
     * @var Riseup_Asia|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Riseup_Asia
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Initialize file logger first (it doesn't depend on anything else).
        $this->file_logger = Riseup_File_Logger::get_instance();
        $this->file_logger->info('Plugin constructor starting', array('version' => RISEUP_VERSION));

        try {
            // Initialize database and run migrations immediately.
            $this->db = Riseup_Database::get_instance();
            $this->file_logger->info('Database instance created');
            
            // Run database initialization (creates tables if not exist).
            $this->file_logger->info('Running database migration/initialization');
            $db_ready = $this->db->init();
            if ($db_ready) {
                $this->file_logger->info('Database initialized successfully');
            } else {
                $this->file_logger->error('Database initialization failed - some features may not work');
            }

            // Initialize transaction logger (depends on database).
            $this->logger = Riseup_Logger::get_instance();
            $this->file_logger->info('Transaction logger initialized');

            // Initialize post manager (depends on logger).
            $this->post_manager = Riseup_Post_Manager::get_instance();
            $this->file_logger->info('Post manager initialized');

            // Register REST API routes.
            $this->file_logger->info('Registering REST API init hook');
            add_action('rest_api_init', array($this, 'register_routes'));

            $this->file_logger->info('Plugin constructor complete');
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Plugin initialization failed');
        } catch (Error $e) {
            // Catch PHP 7+ fatal errors (like class not found)
            $this->file_logger->error('Fatal error during plugin init: ' . $e->getMessage(), array(
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ));
        }
    }

    /**
     * Register REST API routes.
     * Using plain endpoint strings (no regex) from constants.
     *
     * @return void
     */
    public function register_routes() {
        $this->file_logger->info('Registering REST API routes', array('namespace' => RISEUP_API_FULL_NAMESPACE));

        try {
            // Status endpoint (authenticated - requires valid credentials).
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_STATUS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_STATUS, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_status'),
                'permission_callback' => $this->build_permission_callback('status', array($this, 'check_status_permission')),
            ));

            // OpenAPI specification endpoint (authenticated).
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_OPENAPI);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_OPENAPI, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_openapi'),
                'permission_callback' => $this->build_permission_callback('openapi', array($this, 'check_status_permission')),
            ));

            // Plugin upload endpoint.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_UPLOAD);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_UPLOAD, array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_upload'),
                'permission_callback' => $this->build_permission_callback('upload', array($this, 'check_plugin_permission')),
            ));

            // Plugin list endpoint.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_PLUGINS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_PLUGINS, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_list_plugins'),
                'permission_callback' => $this->build_permission_callback('plugins', array($this, 'check_plugin_permission')),
            ));

            // Export-self endpoint.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_EXPORT_SELF);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_EXPORT_SELF, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_export_self'),
                'permission_callback' => $this->build_permission_callback('export_self', array($this, 'check_plugin_permission')),
            ));

            // Blog post endpoints.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_POSTS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_POSTS, array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array($this, 'handle_list_posts'),
                    'permission_callback' => $this->build_permission_callback('posts', array($this, 'check_post_permission')),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'handle_create_post'),
                    'permission_callback' => $this->build_permission_callback('posts', array($this, 'check_post_permission')),
                ),
            ));

            // Category endpoints.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_CATEGORIES);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_CATEGORIES, array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array($this, 'handle_list_categories'),
                    'permission_callback' => $this->build_permission_callback('categories', array($this, 'check_post_permission')),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'handle_create_category'),
                    'permission_callback' => $this->build_permission_callback('categories', array($this, 'check_post_permission')),
                ),
            ));

            // Transaction log endpoints.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_LOGS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_LOGS, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_query_logs'),
                'permission_callback' => $this->build_permission_callback('logs', array($this, 'check_logs_permission')),
            ));

            // Logs stats endpoint.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_LOGS_STATS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_LOGS_STATS, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_logs_stats'),
                'permission_callback' => $this->build_permission_callback('logs_stats', array($this, 'check_logs_permission')),
            ));

            // Plugin files listing endpoint (for diff preview).
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_PLUGIN_FILES);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_PLUGIN_FILES, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_plugin_files'),
                'permission_callback' => $this->build_permission_callback('plugin_files', array($this, 'check_plugin_permission')),
                'args'                => array(
                    'slug' => array(
                        'required'          => true,
                        'validate_callback' => function($param) {
                            return is_string($param) && preg_match('/^[a-zA-Z0-9_-]+$/', $param);
                        },
                    ),
                ),
            ));

            // Plugin file content endpoint (for diff viewing).
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_PLUGIN_FILE);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_PLUGIN_FILE, array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_plugin_file_content'),
                'permission_callback' => $this->build_permission_callback('plugin_file', array($this, 'check_plugin_permission')),
                'args'                => array(
                    'slug' => array(
                        'required'          => true,
                        'validate_callback' => function($param) {
                            return is_string($param) && preg_match('/^[a-zA-Z0-9_-]+$/', $param);
                        },
                    ),
                ),
            ));

            $this->file_logger->info('All REST API routes registered successfully');
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Failed to register routes');
        }
    }

    // =========================================================================
    // PERMISSION CALLBACKS
    // =========================================================================

    /**
     * Check if an endpoint is enabled via settings.
     *
     * @param string $endpoint Endpoint key (e.g., 'status', 'upload').
     * @return bool True if enabled.
     */
    private function is_endpoint_enabled($endpoint) {
        return Riseup_Admin::is_endpoint_enabled($endpoint);
    }

    /**
     * Check if an endpoint requires authentication via settings.
     *
     * @param string $endpoint Endpoint key.
     * @return bool True if auth required.
     */
    private function is_auth_required($endpoint) {
        return Riseup_Admin::is_auth_required($endpoint);
    }

    /**
     * Build permission callback with optional auth bypass.
     *
     * @param string   $endpoint   Endpoint key for settings lookup.
     * @param callable $auth_check The actual auth check function.
     * @return callable Permission callback.
     */
    private function build_permission_callback($endpoint, $auth_check) {
        return function($request) use ($endpoint, $auth_check) {
            // Check if endpoint is enabled
            if (!$this->is_endpoint_enabled($endpoint)) {
                return new WP_Error(
                    'rest_disabled',
                    'This endpoint is disabled',
                    array('status' => 403)
                );
            }
            
            // Check if auth is required
            if (!$this->is_auth_required($endpoint)) {
                return true; // Allow without auth
            }
            
            // Perform normal auth check
            return call_user_func($auth_check, $request);
        };
    }

    /**
     * Check plugin management permission.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_plugin_permission($request) {
        $this->file_logger->debug('Checking plugin permission');
        return $this->check_authenticated_capability($request, RISEUP_CAP_MANAGE_PLUGINS);
    }

    /**
     * Check post management permission.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_post_permission($request) {
        $this->file_logger->debug('Checking post permission');
        return $this->check_authenticated_capability($request, RISEUP_CAP_MANAGE_POSTS);
    }

    /**
     * Check logs view permission.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_logs_permission($request) {
        $this->file_logger->debug('Checking logs permission');
        return $this->check_authenticated_capability($request, RISEUP_CAP_VIEW_LOGS);
    }

    /**
     * Check status/openapi permission (requires any authenticated user).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_status_permission($request) {
        $this->file_logger->debug('Checking status permission');
        return $this->check_authenticated_only($request);
    }

    /**
     * Verify authentication only (no capability check).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    private function check_authenticated_only($request) {
        $this->file_logger->debug('Authenticating request (any user)');

        try {
            // Get Authorization header.
            $auth_header = $request->get_header('Authorization');

            if (empty($auth_header)) {
                $this->file_logger->warn('Missing Authorization header');
                $this->logger->log_auth_failure('Missing Authorization header');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array(
                        'status' => RISEUP_HTTP_UNAUTHORIZED,
                        'headers' => array('WWW-Authenticate' => 'Basic realm="WordPress Application Password"'),
                    )
                );
            }

            // Parse Basic auth.
            if (strpos($auth_header, 'Basic ') !== 0) {
                $this->file_logger->warn('Invalid Authorization header format');
                $this->logger->log_auth_failure('Invalid Authorization header format');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            $credentials = base64_decode(substr($auth_header, 6));
            if (!$credentials || strpos($credentials, ':') === false) {
                $this->file_logger->warn('Invalid credentials format');
                $this->logger->log_auth_failure('Invalid credentials format');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            list($username, $password) = explode(':', $credentials, 2);
            $this->file_logger->debug('Authenticating user', array('username' => $username));

            // Authenticate using application password.
            $user = wp_authenticate_application_password(null, $username, $password);

            if (is_wp_error($user) || !$user) {
                $this->file_logger->warn('Invalid credentials', array('username' => $username));
                $this->logger->log_auth_failure(
                    'Invalid credentials',
                    array('username' => $username)
                );
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            // Set current user.
            wp_set_current_user($user->ID);
            $this->file_logger->info('Request authorized (status)', array('username' => $username));
            return true;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Authentication error');
            return new WP_Error(
                'rest_forbidden',
                RISEUP_MSG_UNAUTHORIZED,
                array('status' => RISEUP_HTTP_UNAUTHORIZED)
            );
        }
    }

    /**
     * Verify authentication and capability.
     *
     * @param WP_REST_Request $request    Request object.
     * @param string          $capability Required capability.
     *
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    private function check_authenticated_capability($request, $capability) {
        $this->file_logger->debug('Authenticating request', array('capability' => $capability));

        try {
            // Get Authorization header.
            $auth_header = $request->get_header('Authorization');

            if (empty($auth_header)) {
                $this->file_logger->warn('Missing Authorization header');
                $this->logger->log_auth_failure('Missing Authorization header');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            // Parse Basic auth.
            if (strpos($auth_header, 'Basic ') !== 0) {
                $this->file_logger->warn('Invalid Authorization header format');
                $this->logger->log_auth_failure('Invalid Authorization header format');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            $credentials = base64_decode(substr($auth_header, 6));
            if (!$credentials || strpos($credentials, ':') === false) {
                $this->file_logger->warn('Invalid credentials format');
                $this->logger->log_auth_failure('Invalid credentials format');
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            list($username, $password) = explode(':', $credentials, 2);
            $this->file_logger->debug('Authenticating user', array('username' => $username));

            // Authenticate using application password.
            $user = wp_authenticate_application_password(null, $username, $password);

            if (is_wp_error($user) || !$user) {
                $this->file_logger->warn('Invalid credentials', array('username' => $username));
                $this->logger->log_auth_failure(
                    'Invalid credentials',
                    array('username' => $username)
                );
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_UNAUTHORIZED,
                    array('status' => RISEUP_HTTP_UNAUTHORIZED)
                );
            }

            // Set current user for further capability checks.
            wp_set_current_user($user->ID);
            $this->file_logger->debug('User authenticated', array('user_id' => $user->ID));

            // Check capability.
            if (!current_user_can($capability)) {
                $this->file_logger->warn('Insufficient permissions', array(
                    'username'     => $username,
                    'required_cap' => $capability,
                ));
                $this->logger->log_auth_failure(
                    'Insufficient permissions',
                    array('username' => $username, 'required_cap' => $capability)
                );
                return new WP_Error(
                    'rest_forbidden',
                    RISEUP_MSG_FORBIDDEN,
                    array('status' => RISEUP_HTTP_FORBIDDEN)
                );
            }

            $this->file_logger->info('Request authorized', array('username' => $username));
            return true;
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Authentication error');
            return new WP_Error(
                'rest_forbidden',
                RISEUP_MSG_UNAUTHORIZED,
                array('status' => RISEUP_HTTP_UNAUTHORIZED)
            );
        }
    }

    // =========================================================================
    // STATUS HANDLERS
    // =========================================================================

    /**
     * Handle status check.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_status($request) {
        $this->file_logger->info('Status endpoint called');

        return new WP_REST_Response(array(
            'success'  => true,
            'plugin'   => RISEUP_NAME,
            'version'  => RISEUP_VERSION,
            'api'      => RISEUP_API_FULL_NAMESPACE,
            'wp'       => get_bloginfo('version'),
            'php'      => PHP_VERSION,
            'features' => array(
                'plugin_upload'   => true,
                'plugin_manage'   => true,
                'file_operations' => true,
                'delta_sync'      => true,
                'post_publish'    => true,
                'category_manage' => true,
                'transaction_log' => true,
                'export_self'     => true,
            ),
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle OpenAPI specification request.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_openapi($request) {
        $this->file_logger->info('OpenAPI endpoint called');

        // Read the OpenAPI spec from the data directory.
        $spec_file = plugin_dir_path(__FILE__) . 'data/openapi.json';
        
        if (!file_exists($spec_file)) {
            $this->file_logger->error('OpenAPI spec file not found', array('path' => $spec_file));
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'OpenAPI specification file not found',
            ), RISEUP_HTTP_NOT_FOUND);
        }

        $spec_content = file_get_contents($spec_file);
        if ($spec_content === false) {
            $this->file_logger->error('Failed to read OpenAPI spec file');
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Failed to read OpenAPI specification',
            ), RISEUP_HTTP_SERVER_ERROR);
        }

        $spec = json_decode($spec_content, true);
        if ($spec === null) {
            $this->file_logger->error('Invalid JSON in OpenAPI spec file');
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Invalid OpenAPI specification format',
            ), RISEUP_HTTP_SERVER_ERROR);
        }

        // Update the server URL dynamically.
        $spec['servers'][0]['variables']['baseUrl']['default'] = get_site_url();

        return new WP_REST_Response($spec, RISEUP_HTTP_OK);
    }

    // =========================================================================
    // PLUGIN HANDLERS
    // =========================================================================

    /**
     * Handle plugin upload (Base64 ZIP).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_upload($request) {
        $this->file_logger->info('Upload endpoint called');

        try {
            $data = $request->get_json_params();

            if (empty($data['plugin_zip'])) {
                $this->file_logger->warn('Upload failed: plugin_zip required');
                return $this->error_response(RISEUP_MSG_INVALID_REQUEST . ': plugin_zip is required', RISEUP_HTTP_BAD_REQUEST);
            }

            // Decode base64.
            $this->file_logger->debug('Decoding base64 ZIP data');
            $zip_content = base64_decode($data['plugin_zip']);
            if ($zip_content === false) {
                $this->file_logger->error('Invalid base64 data');
                return $this->error_response('Invalid base64 data', RISEUP_HTTP_BAD_REQUEST);
            }

            // Get optional parameters.
            $slug     = sanitize_file_name($data['slug'] ?? '');
            $activate = !empty($data['activate']);
            $this->file_logger->debug('Upload parameters', array('slug' => $slug, 'activate' => $activate));

            // Create temp file.
            $temp_dir  = $this->get_temp_dir();
            $temp_file = $temp_dir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

            $this->file_logger->debug('Writing temp file', array('path' => $temp_file));
            if (file_put_contents($temp_file, $zip_content) === false) {
                $this->file_logger->error('Failed to write temp file');
                $this->logger->log_upload_failed($slug, 'Failed to write temp file');
                return $this->error_response(RISEUP_MSG_UPLOAD_FAILED, RISEUP_HTTP_SERVER_ERROR);
            }

            // Validate ZIP.
            $this->file_logger->debug('Validating ZIP archive');
            $zip = new ZipArchive();
            if ($zip->open($temp_file) !== true) {
                @unlink($temp_file);
                $this->file_logger->error('Invalid ZIP archive');
                $this->logger->log_upload_failed($slug, 'Invalid ZIP archive');
                return $this->error_response('Invalid ZIP archive', RISEUP_HTTP_BAD_REQUEST);
            }

            // Determine plugin slug from ZIP.
            $detected_slug = $this->detect_plugin_slug_from_zip($zip);
            $zip->close();

            if (!$detected_slug) {
                @unlink($temp_file);
                $this->file_logger->error('Could not detect plugin in ZIP');
                $this->logger->log_upload_failed($slug, 'Could not detect plugin in ZIP');
                return $this->error_response('Could not detect plugin in ZIP', RISEUP_HTTP_BAD_REQUEST);
            }

            // Use detected slug if not provided.
            if (empty($slug)) {
                $slug = $detected_slug;
            }
            $this->file_logger->info('Plugin slug determined', array('slug' => $slug));

            // Extract to plugins directory.
            $plugins_dir  = WP_PLUGIN_DIR;
            $target_dir   = $plugins_dir . '/' . $slug;
            $is_update    = is_dir($target_dir);

            $this->file_logger->info($is_update ? 'Updating existing plugin' : 'Installing new plugin', array(
                'slug'       => $slug,
                'target_dir' => $target_dir,
            ));

            // If updating, check if currently active.
            $was_active = false;
            if ($is_update) {
                $plugin_file = $this->find_plugin_file($slug);
                if ($plugin_file) {
                    $was_active = is_plugin_active($plugin_file);
                    if ($was_active) {
                        $this->file_logger->debug('Deactivating plugin before update', array('plugin_file' => $plugin_file));
                        deactivate_plugins($plugin_file);
                    }
                }
                // Remove old version.
                $this->file_logger->debug('Removing old plugin version', array('target_dir' => $target_dir));
                $this->delete_directory($target_dir);
            }

            // =================================================================
            // EXTRACT TO TEMP FIRST TO AVOID FOLDER NAME MISMATCHES
            // This prevents duplicate plugins when ZIP folder name differs from slug
            // =================================================================
            $temp_extract_dir = $this->get_temp_dir() . '/extract_' . uniqid();
            wp_mkdir_p($temp_extract_dir);

            $this->file_logger->debug('Extracting ZIP to temp directory', array(
                'temp_dir'   => $temp_extract_dir,
                'temp_file'  => $temp_file,
            ));

            $zip = new ZipArchive();
            if ($zip->open($temp_file) !== true) {
                @unlink($temp_file);
                $this->delete_directory($temp_extract_dir);
                $this->file_logger->error('Failed to open ZIP for extraction');
                return $this->error_response('Failed to open ZIP for extraction', RISEUP_HTTP_SERVER_ERROR);
            }
            $zip->extractTo($temp_extract_dir);
            $zip->close();
            @unlink($temp_file);

            // Find the extracted folder (should be exactly one directory).
            $extracted_folders = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
            if (empty($extracted_folders)) {
                $this->delete_directory($temp_extract_dir);
                $this->file_logger->error('No folder found in extracted ZIP');
                $this->logger->log_upload_failed($slug, 'No folder found in extracted ZIP');
                return $this->error_response('No folder found in extracted ZIP', RISEUP_HTTP_SERVER_ERROR);
            }

            $extracted_folder = $extracted_folders[0];
            $extracted_name   = basename($extracted_folder);
            $this->file_logger->debug('Found extracted folder', array(
                'extracted_name' => $extracted_name,
                'target_slug'    => $slug,
                'needs_rename'   => $extracted_name !== $slug,
            ));

            // Move the extracted folder to the plugins directory with the correct slug name.
            // This ensures the folder name always matches the slug, preventing duplicates.
            if (rename($extracted_folder, $target_dir)) {
                $this->file_logger->info('Plugin installed to correct location', array(
                    'from' => $extracted_folder,
                    'to'   => $target_dir,
                ));
            } else {
                // Fallback: copy files if rename fails (cross-device move).
                $this->file_logger->warn('Rename failed, attempting copy', array(
                    'from' => $extracted_folder,
                    'to'   => $target_dir,
                ));
                $this->copy_directory($extracted_folder, $target_dir);
                $this->delete_directory($extracted_folder);
            }

            // Cleanup temp extraction directory.
            $this->delete_directory($temp_extract_dir);

            // Find the main plugin file.
            $plugin_file = $this->find_plugin_file($slug);
            if (!$plugin_file) {
                $this->file_logger->error('Could not find plugin file after extraction', array(
                    'slug'       => $slug,
                    'target_dir' => $target_dir,
                ));
                $this->logger->log_upload_failed($slug, 'Could not find plugin file after extraction');
                return $this->error_response('Could not find plugin file after extraction', RISEUP_HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('Plugin file found', array('plugin_file' => $plugin_file));

            // Activate if requested or was previously active.
            $activated = false;
            if ($activate || $was_active) {
                $this->file_logger->debug('Activating plugin');
                $result = activate_plugin($plugin_file);
                if (is_wp_error($result)) {
                    $error_msg = $result->get_error_message();
                    $this->file_logger->warn('Activation failed', array('error' => $error_msg));
                    $this->logger->log_upload_failed($slug, RISEUP_MSG_ACTIVATION_FAILED . ': ' . $error_msg);
                    // Plugin uploaded but activation failed.
                    return new WP_REST_Response(array(
                        'success'          => true,
                        'plugin_slug'      => $slug,
                        'is_update'        => $is_update,
                        'activated'        => false,
                        'activation_error' => $error_msg,
                    ), RISEUP_HTTP_OK);
                }
                $activated = true;
                $this->file_logger->info('Plugin activated successfully');
            }

            // Log success.
            $this->logger->log_upload($slug, array(
                'is_update' => $is_update,
                'activated' => $activated,
                'file_size' => strlen($zip_content),
            ));

            $this->file_logger->info('Upload complete', array(
                'slug'      => $slug,
                'is_update' => $is_update,
                'activated' => $activated,
            ));

            return new WP_REST_Response(array(
                'success'     => true,
                'plugin_slug' => $slug,
                'is_update'   => $is_update,
                'activated'   => $activated,
            ), RISEUP_HTTP_OK);
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Upload error');
            return $this->error_response('Upload failed: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Handle list plugins.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_list_plugins($request) {
        $this->file_logger->info('List plugins endpoint called');

        try {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $all_plugins    = get_plugins();
            $active_plugins = get_option('active_plugins', array());
            $plugins        = array();

            foreach ($all_plugins as $plugin_file => $plugin_data) {
                $slug = dirname($plugin_file);
                if ($slug === '.') {
                    $slug = basename($plugin_file, '.php');
                }

                $plugins[] = array(
                    'slug'        => $slug,
                    'name'        => $plugin_data['Name'],
                    'version'     => $plugin_data['Version'],
                    'author'      => $plugin_data['Author'],
                    'description' => $plugin_data['Description'],
                    'active'      => in_array($plugin_file, $active_plugins, true),
                    'plugin_file' => $plugin_file,
                );
            }

            $this->file_logger->debug('Plugins listed', array('count' => count($plugins)));

            return new WP_REST_Response(array(
                'success' => true,
                'plugins' => $plugins,
            ), RISEUP_HTTP_OK);
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'List plugins error');
            return $this->error_response('Failed to list plugins: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR);
        }
    }

    /**
     * Handle plugin files listing (for diff preview).
     * Returns all files in a plugin directory with their MD5 hashes.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_plugin_files($request) {
        $slug = $request->get_param('slug');
        $this->file_logger->info('Plugin files endpoint called', array('slug' => $slug));

        try {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            // Find the plugin directory
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            
            if (!is_dir($plugin_dir)) {
                $this->file_logger->warn('Plugin directory not found', array('slug' => $slug, 'path' => $plugin_dir));
                return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug, RISEUP_HTTP_NOT_FOUND);
            }

            // Load uploadignore patterns if available
            $ignore = Riseup_Upload_Ignore::from_directory($plugin_dir);

            $files = array();
            $this->scan_directory_for_files($plugin_dir, $plugin_dir, $ignore, $files);

            $this->file_logger->info('Plugin files scanned', array('slug' => $slug, 'count' => count($files)));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin'     => $slug,
                'totalFiles' => count($files),
                'files'      => $files,
            ), RISEUP_HTTP_OK);
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Plugin files error');
            return $this->error_response('Failed to list plugin files: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR);
        }
    }

    /**
     * Recursively scan a directory and collect file info with hashes.
     *
     * @param string               $base_dir The base directory for relative paths.
     * @param string               $dir      Current directory to scan.
     * @param Riseup_Upload_Ignore $ignore   Ignore patterns.
     * @param array                $files    Reference to files array to populate.
     *
     * @return void
     */
    private function scan_directory_for_files($base_dir, $dir, $ignore, &$files) {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = $dir . '/' . $item;
            $rel_path  = ltrim(str_replace($base_dir, '', $full_path), '/\\');

            // Check ignore patterns
            if ($ignore->should_ignore($rel_path)) {
                continue;
            }

            if (is_dir($full_path)) {
                // Recurse into subdirectory
                $this->scan_directory_for_files($base_dir, $full_path, $ignore, $files);
            } else {
                // Calculate MD5 hash
                $hash = @md5_file($full_path);
                $size = @filesize($full_path);
                $mtime = @filemtime($full_path);

                $files[] = array(
                    'path'       => str_replace('\\', '/', $rel_path),
                    'hash'       => $hash ?: '',
                    'size'       => $size ?: 0,
                    'modifiedAt' => $mtime ? gmdate('c', $mtime) : null,
                );
            }
        }
    }

    /**
     * Handle getting content of a single file from a plugin.
     * Used for diff viewing in the UI.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_plugin_file_content($request) {
        $slug = $request->get_param('slug');
        $json = $request->get_json_params();
        $file_path = isset($json['path']) ? $json['path'] : null;

        $this->file_logger->info('Plugin file content endpoint called', array(
            'slug' => $slug,
            'path' => $file_path,
        ));

        try {
            if (empty($file_path)) {
                return $this->error_response('File path is required', RISEUP_HTTP_BAD_REQUEST);
            }

            // Sanitize path - prevent directory traversal
            $file_path = ltrim($file_path, '/\\');
            if (strpos($file_path, '..') !== false) {
                return $this->error_response('Invalid file path', RISEUP_HTTP_BAD_REQUEST);
            }

            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            $full_path = $plugin_dir . '/' . $file_path;

            if (!is_dir($plugin_dir)) {
                return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND . ': ' . $slug, RISEUP_HTTP_NOT_FOUND);
            }

            // Verify the file is within the plugin directory
            $real_plugin_dir = realpath($plugin_dir);
            $real_file_path = realpath($full_path);

            if ($real_file_path === false || strpos($real_file_path, $real_plugin_dir) !== 0) {
                return $this->error_response('File not found or invalid path', RISEUP_HTTP_NOT_FOUND);
            }

            if (!is_file($real_file_path)) {
                return $this->error_response('File not found', RISEUP_HTTP_NOT_FOUND);
            }

            $content = @file_get_contents($real_file_path);
            if ($content === false) {
                return $this->error_response('Failed to read file', RISEUP_HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('File content read', array(
                'slug' => $slug,
                'path' => $file_path,
                'size' => strlen($content),
            ));

            return new WP_REST_Response(array(
                'success' => true,
                'path'    => $file_path,
                'content' => $content,
            ), RISEUP_HTTP_OK);
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Plugin file content error');
            return $this->error_response('Failed to read file: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Handle export-self (export this plugin as ZIP).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_export_self($request) {
        $this->file_logger->info('Export-self endpoint called');

        try {
            $plugin_dir = dirname(__FILE__);
            $temp_dir   = $this->get_temp_dir();
            $zip_file   = $temp_dir . '/' . RISEUP_SLUG . '.zip';

            $this->file_logger->debug('Creating ZIP', array('source' => $plugin_dir, 'target' => $zip_file));

            // Create ZIP.
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->file_logger->error('Failed to create ZIP file');
                return $this->error_response('Failed to create ZIP file', RISEUP_HTTP_SERVER_ERROR);
            }

            // Load uploadignore.
            $ignore = Riseup_Upload_Ignore::from_directory($plugin_dir);
            $this->file_logger->debug('Uploadignore loaded', array('has_patterns' => $ignore->is_loaded()));

            // Add files recursively.
            $this->add_dir_to_zip($zip, $plugin_dir, RISEUP_SLUG, $ignore);
            $zip->close();

            // Read and encode.
            $zip_content = file_get_contents($zip_file);
            @unlink($zip_file);

            if ($zip_content === false) {
                $this->file_logger->error('Failed to read ZIP file');
                return $this->error_response('Failed to read ZIP file', RISEUP_HTTP_SERVER_ERROR);
            }

            $this->file_logger->info('Export-self complete', array('size' => strlen($zip_content)));

            // Log the export.
            $this->logger->log_plugin_action(RISEUP_ACTION_EXPORT_SELF, RISEUP_SLUG, RISEUP_STATUS_SUCCESS, array(
                'size' => strlen($zip_content),
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => RISEUP_SLUG,
                'version'    => RISEUP_VERSION,
            ), RISEUP_HTTP_OK);
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Export-self error');
            return $this->error_response('Export failed: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR, $e);
        }
    }

    // =========================================================================
    // POST HANDLERS
    // =========================================================================

    /**
     * Handle list posts.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_list_posts($request) {
        $this->file_logger->debug('List posts endpoint called');

        $result = $this->post_manager->list_posts(array(
            'status' => $request->get_param('status'),
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_OK : RISEUP_HTTP_SERVER_ERROR);
    }

    /**
     * Handle create post.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_create_post($request) {
        $this->file_logger->info('Create post endpoint called');

        $data   = $request->get_json_params();
        $result = $this->post_manager->create_post($data);

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_CREATED : RISEUP_HTTP_BAD_REQUEST);
    }

    /**
     * Handle list categories.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_list_categories($request) {
        $this->file_logger->debug('List categories endpoint called');

        $result = $this->post_manager->list_categories(array(
            'limit'  => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
            'search' => $request->get_param('search'),
        ));

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_OK : RISEUP_HTTP_SERVER_ERROR);
    }

    /**
     * Handle create category.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_create_category($request) {
        $this->file_logger->info('Create category endpoint called');

        $data   = $request->get_json_params();
        $result = $this->post_manager->create_category($data);

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_CREATED : RISEUP_HTTP_BAD_REQUEST);
    }

    // =========================================================================
    // LOG HANDLERS
    // =========================================================================

    /**
     * Handle query logs.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_query_logs($request) {
        $this->file_logger->debug('Query logs endpoint called');

        try {
            // Initialize database if not already done.
            $this->db->init();

            $filters = array(
                'plugin' => $request->get_param('plugin'),
                'action' => $request->get_param('action'),
                'user'   => $request->get_param('user'),
                'status' => $request->get_param('status'),
                'from'   => $request->get_param('from'),
                'to'     => $request->get_param('to'),
            );

            $limit  = $request->get_param('limit') ?? RISEUP_DEFAULT_LIMIT;
            $offset = $request->get_param('offset') ?? 0;

            $result = $this->db->query_transactions($filters, $limit, $offset);

            return new WP_REST_Response(array(
                'success' => true,
                'total'   => $result['total'],
                'limit'   => (int) $limit,
                'offset'  => (int) $offset,
                'logs'    => $result['logs'],
            ), RISEUP_HTTP_OK);
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Query logs error');
            return $this->error_response('Failed to query logs: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR);
        }
    }

    /**
     * Handle logs stats.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_logs_stats($request) {
        $this->file_logger->debug('Logs stats endpoint called');

        try {
            // Initialize database if not already done.
            $this->db->init();

            $stats = $this->db->get_stats();

            return new WP_REST_Response(array(
                'success' => true,
                'stats'   => $stats,
            ), RISEUP_HTTP_OK);
        } catch (Exception $e) {
            $this->file_logger->log_exception($e, 'Logs stats error');
            return $this->error_response('Failed to get stats: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR);
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Create an error response with optional exception details.
     *
     * @param string         $message   Error message.
     * @param int            $status    HTTP status code.
     * @param Throwable|null $exception Optional exception for stack trace.
     *
     * @return WP_REST_Response
     */
    private function error_response($message, $status, $exception = null) {
        $error_data = array(
            'success' => false,
            'error'   => array(
                'code'    => 'ERROR_' . $status,
                'message' => $message,
            ),
        );

        // Add detailed exception info if available
        if ($exception instanceof Throwable) {
            $error_data['error']['code'] = $this->get_exception_code($exception);
            $error_data['error']['details'] = array(
                'exceptionClass' => get_class($exception),
                'file'           => basename($exception->getFile()),
                'fileFull'       => $exception->getFile(),
                'line'           => $exception->getLine(),
                'stackTrace'     => $exception->getTraceAsString(),
            );
            
            // Log full exception to file logger
            $this->file_logger->log_exception($exception, $message);
        } else {
            // Generate a stack trace even without an exception
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $trace_lines = array();
            foreach ($backtrace as $i => $frame) {
                $file = isset($frame['file']) ? basename($frame['file']) : '[internal]';
                $line = isset($frame['line']) ? $frame['line'] : '?';
                $func = isset($frame['function']) ? $frame['function'] : '';
                $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
                $trace_lines[] = "#{$i} {$file}({$line}): {$class}{$func}()";
            }
            $error_data['error']['details'] = array(
                'stackTrace' => implode("\n", $trace_lines),
            );
            
            $this->file_logger->error('Error response', array(
                'message'    => $message,
                'status'     => $status,
                'stackTrace' => implode("\n", $trace_lines),
            ));
        }

        return new WP_REST_Response($error_data, $status);
    }

    /**
     * Get a meaningful error code from an exception.
     *
     * @param Throwable $exception The exception.
     *
     * @return string
     */
    private function get_exception_code($exception) {
        $code = $exception->getCode();
        if (is_int($code) && $code > 0) {
            return 'E' . $code;
        }
        
        // Generate code from class name
        $class = get_class($exception);
        $short = str_replace(array('Exception', 'Error'), '', $class);
        if (empty($short)) {
            return 'EXCEPTION';
        }
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $short));
    }

    /**
     * Get temp directory path.
     *
     * @return string
     */
    private function get_temp_dir() {
        $temp_dir = $this->file_logger->get_base_dir() . '/' . RISEUP_TEMP_SUBDIR;

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        return $temp_dir;
    }

    /**
     * Find plugin file by slug.
     *
     * @param string $slug Plugin slug.
     *
     * @return string|null Plugin file or null.
     */
    private function find_plugin_file($slug) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_slug = dirname($plugin_file);
            if ($plugin_slug === '.') {
                $plugin_slug = basename($plugin_file, '.php');
            }
            if ($plugin_slug === $slug) {
                return $plugin_file;
            }
        }
        return null;
    }

    /**
     * Detect plugin slug from ZIP file.
     *
     * @param ZipArchive $zip ZIP archive.
     *
     * @return string|null Plugin slug or null.
     */
    private function detect_plugin_slug_from_zip($zip) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Look for a PHP file with a plugin header.
            if (preg_match('/^([^\/]+)\/[^\/]+\.php$/', $name, $matches)) {
                $content = $zip->getFromIndex($i);
                if ($content && strpos($content, 'Plugin Name:') !== false) {
                    return $matches[1];
                }
            }
        }
        return null;
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $dir Directory path.
     *
     * @return bool Success.
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Copy a directory recursively.
     * Used as fallback when rename fails (cross-device move).
     *
     * @param string $src Source directory path.
     * @param string $dst Destination directory path.
     *
     * @return bool Success.
     */
    private function copy_directory($src, $dst) {
        if (!is_dir($src)) {
            return false;
        }

        if (!is_dir($dst)) {
            wp_mkdir_p($dst);
        }

        $files = array_diff(scandir($src), array('.', '..'));
        foreach ($files as $file) {
            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;
            if (is_dir($src_path)) {
                $this->copy_directory($src_path, $dst_path);
            } else {
                copy($src_path, $dst_path);
            }
        }

        return true;
    }

    /**
     * Add directory to ZIP recursively.
     *
     * @param ZipArchive           $zip      ZIP archive.
     * @param string               $src_dir  Source directory.
     * @param string               $zip_dir  Directory name in ZIP.
     * @param Riseup_Upload_Ignore $ignore   Upload ignore parser.
     *
     * @return void
     */
    private function add_dir_to_zip($zip, $src_dir, $zip_dir, $ignore) {
        $dir = opendir($src_dir);
        if (!$dir) {
            return;
        }

        $zip->addEmptyDir($zip_dir);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src_path = $src_dir . '/' . $file;
            $zip_path = $zip_dir . '/' . $file;

            // Check if should be ignored.
            $relative = str_replace($src_dir . '/', '', $src_path);
            if ($ignore->should_ignore($relative)) {
                continue;
            }

            if (is_dir($src_path)) {
                $this->add_dir_to_zip($zip, $src_path, $zip_path, $ignore);
            } else {
                $zip->addFile($src_path, $zip_path);
            }
        }

        closedir($dir);
    }
}

// =============================================================================
// PLUGIN INITIALIZATION
// =============================================================================

/**
 * Initialize the plugin.
 */
function riseup_asia_init() {
    // Initialize main plugin class
    Riseup_Asia::get_instance();
    
    // Initialize admin pages (only in admin context)
    if (is_admin()) {
        Riseup_Admin::get_instance();
    }
}

// Initialize on plugins_loaded hook.
add_action('plugins_loaded', 'riseup_asia_init');
