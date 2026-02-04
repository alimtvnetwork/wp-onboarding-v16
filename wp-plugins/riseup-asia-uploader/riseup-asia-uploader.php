<?php
/**
 * Plugin Name: Riseup Asia Uploader
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Remote plugin management, blog post publishing, delta file sync, and audit logging via REST API with Application Password authentication.
 * Version: 1.4.0
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
            // Initialize database (lazy init - doesn't connect until needed).
            $this->db = Riseup_Database::get_instance();
            $this->file_logger->info('Database instance created');

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
            // Status endpoint (public).
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_STATUS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_STATUS, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_status'),
                'permission_callback' => '__return_true',
            ));

            // Plugin upload endpoint.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_UPLOAD);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_UPLOAD, array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_upload'),
                'permission_callback' => array($this, 'check_plugin_permission'),
            ));

            // Plugin list endpoint.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_PLUGINS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_PLUGINS, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_list_plugins'),
                'permission_callback' => array($this, 'check_plugin_permission'),
            ));

            // Export-self endpoint.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_EXPORT_SELF);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_EXPORT_SELF, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_export_self'),
                'permission_callback' => array($this, 'check_plugin_permission'),
            ));

            // Blog post endpoints.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_POSTS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_POSTS, array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array($this, 'handle_list_posts'),
                    'permission_callback' => array($this, 'check_post_permission'),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'handle_create_post'),
                    'permission_callback' => array($this, 'check_post_permission'),
                ),
            ));

            // Category endpoints.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_CATEGORIES);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_CATEGORIES, array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array($this, 'handle_list_categories'),
                    'permission_callback' => array($this, 'check_post_permission'),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'handle_create_category'),
                    'permission_callback' => array($this, 'check_post_permission'),
                ),
            ));

            // Transaction log endpoints.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_LOGS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_LOGS, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_query_logs'),
                'permission_callback' => array($this, 'check_logs_permission'),
            ));

            // Logs stats endpoint.
            $this->file_logger->debug('Registering endpoint: ' . RISEUP_ENDPOINT_LOGS_STATS);
            register_rest_route(RISEUP_API_FULL_NAMESPACE, '/' . RISEUP_ENDPOINT_LOGS_STATS, array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_logs_stats'),
                'permission_callback' => array($this, 'check_logs_permission'),
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

            $this->file_logger->info($is_update ? 'Updating existing plugin' : 'Installing new plugin', array('slug' => $slug));

            // If updating, check if currently active.
            $was_active = false;
            if ($is_update) {
                $plugin_file = $this->find_plugin_file($slug);
                if ($plugin_file) {
                    $was_active = is_plugin_active($plugin_file);
                    if ($was_active) {
                        $this->file_logger->debug('Deactivating plugin before update');
                        deactivate_plugins($plugin_file);
                    }
                }
                // Remove old version.
                $this->file_logger->debug('Removing old plugin version');
                $this->delete_directory($target_dir);
            }

            // Extract ZIP.
            $this->file_logger->debug('Extracting ZIP to plugins directory');
            $zip = new ZipArchive();
            $zip->open($temp_file);
            $zip->extractTo($plugins_dir);
            $zip->close();
            @unlink($temp_file);

            // Find the main plugin file.
            $plugin_file = $this->find_plugin_file($slug);
            if (!$plugin_file) {
                $this->file_logger->error('Could not find plugin file after extraction');
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
            return $this->error_response('Upload failed: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR);
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
            return $this->error_response('Export failed: ' . $e->getMessage(), RISEUP_HTTP_SERVER_ERROR);
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
     * Create an error response.
     *
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     *
     * @return WP_REST_Response
     */
    private function error_response($message, $status) {
        $this->file_logger->error('Error response', array('message' => $message, 'status' => $status));
        return new WP_REST_Response(array(
            'success' => false,
            'error'   => $message,
        ), $status);
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
    return Riseup_Asia::get_instance();
}

// Initialize on plugins_loaded hook.
add_action('plugins_loaded', 'riseup_asia_init');
