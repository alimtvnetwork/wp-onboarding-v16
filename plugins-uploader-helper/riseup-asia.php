<?php
/**
 * Plugin Name: Rise Up Asia
 * Plugin URI: https://riseup-asia.com/
 * Description: Remote plugin management, blog post publishing, delta file sync, and audit logging via REST API with Application Password authentication.
 * Version: 1.3.0
 * Author: Rise Up Asia
 * Author URI: https://riseup-asia.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: riseup-asia
 * Requires at least: 5.6
 * Requires PHP: 7.4
 *
 * @package RiseUpAsia
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// LOAD DEPENDENCIES
// =============================================================================

// Load constants first.
require_once __DIR__ . '/includes/constants.php';

// Load ORM before database.
require_once __DIR__ . '/includes/class-orm.php';

// Load classes.
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-logger.php';
require_once __DIR__ . '/includes/class-post-manager.php';
require_once __DIR__ . '/includes/class-upload-ignore.php';

// =============================================================================
// PLUGIN CLASS
// =============================================================================

/**
 * Main plugin class.
 */
class RiseUp_Asia {

    /**
     * Logger instance.
     *
     * @var RiseUp_Logger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var RiseUp_Database
     */
    private $db;

    /**
     * Post manager instance.
     *
     * @var RiseUp_Post_Manager
     */
    private $post_manager;

    /**
     * Singleton instance.
     *
     * @var RiseUp_Asia|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseUp_Asia
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
        // Initialize components.
        $this->db           = RiseUp_Database::get_instance();
        $this->logger       = RiseUp_Logger::get_instance();
        $this->post_manager = RiseUp_Post_Manager::get_instance();

        // Register REST API routes.
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes() {
        // Status endpoint (public).
        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/status', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_status'),
            'permission_callback' => '__return_true',
        ));

        // Plugin management endpoints.
        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/upload', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_upload'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/plugins', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_list_plugins'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_get_plugin'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/enable', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_enable_plugin'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/disable', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_disable_plugin'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/delete', array(
            'methods'             => 'DELETE',
            'callback'            => array($this, 'handle_delete_plugin'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/files', array(
            'methods'             => array('GET', 'POST', 'DELETE'),
            'callback'            => array($this, 'handle_plugin_files'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        // Delta sync endpoint.
        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/sync', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_plugin_sync'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        // Export-self endpoint.
        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/export-self', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_export_self'),
            'permission_callback' => array($this, 'check_plugin_permission'),
        ));

        // Blog post endpoints.
        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/posts', array(
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

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/posts/(?P<id>\d+)', array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'handle_get_post'),
                'permission_callback' => array($this, 'check_post_permission'),
            ),
            array(
                'methods'             => 'PUT',
                'callback'            => array($this, 'handle_update_post'),
                'permission_callback' => array($this, 'check_post_permission'),
            ),
        ));

        // Category endpoints.
        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/categories', array(
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
        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/logs', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_query_logs'),
            'permission_callback' => array($this, 'check_logs_permission'),
        ));

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/logs/stats', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_logs_stats'),
            'permission_callback' => array($this, 'check_logs_permission'),
        ));

        register_rest_route(RISEUP_API_FULL_NAMESPACE, '/logs/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_get_log'),
            'permission_callback' => array($this, 'check_logs_permission'),
        ));
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
        // Get Authorization header.
        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header)) {
            $this->logger->log_auth_failure('Missing Authorization header');
            return new WP_Error(
                'rest_forbidden',
                RISEUP_MSG_UNAUTHORIZED,
                array('status' => RISEUP_HTTP_UNAUTHORIZED)
            );
        }

        // Parse Basic auth.
        if (strpos($auth_header, 'Basic ') !== 0) {
            $this->logger->log_auth_failure('Invalid Authorization header format');
            return new WP_Error(
                'rest_forbidden',
                RISEUP_MSG_UNAUTHORIZED,
                array('status' => RISEUP_HTTP_UNAUTHORIZED)
            );
        }

        $credentials = base64_decode(substr($auth_header, 6));
        if (!$credentials || strpos($credentials, ':') === false) {
            $this->logger->log_auth_failure('Invalid credentials format');
            return new WP_Error(
                'rest_forbidden',
                RISEUP_MSG_UNAUTHORIZED,
                array('status' => RISEUP_HTTP_UNAUTHORIZED)
            );
        }

        list($username, $password) = explode(':', $credentials, 2);

        // Authenticate using application password.
        $user = wp_authenticate_application_password(null, $username, $password);

        if (is_wp_error($user) || !$user) {
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

        // Check capability.
        if (!current_user_can($capability)) {
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

        return true;
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
        $data = $request->get_json_params();

        if (empty($data['plugin_zip'])) {
            return $this->error_response(RISEUP_MSG_INVALID_REQUEST . ': plugin_zip is required', RISEUP_HTTP_BAD_REQUEST);
        }

        // Decode base64.
        $zip_content = base64_decode($data['plugin_zip']);
        if ($zip_content === false) {
            return $this->error_response('Invalid base64 data', RISEUP_HTTP_BAD_REQUEST);
        }

        // Get optional parameters.
        $slug     = sanitize_file_name($data['slug'] ?? '');
        $activate = !empty($data['activate']);

        // Create temp file.
        $temp_dir  = $this->get_temp_dir();
        $temp_file = $temp_dir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

        if (file_put_contents($temp_file, $zip_content) === false) {
            $this->logger->log_upload_failed($slug, 'Failed to write temp file');
            return $this->error_response(RISEUP_MSG_UPLOAD_FAILED, RISEUP_HTTP_SERVER_ERROR);
        }

        // Validate ZIP.
        $zip = new ZipArchive();
        if ($zip->open($temp_file) !== true) {
            @unlink($temp_file);
            $this->logger->log_upload_failed($slug, 'Invalid ZIP archive');
            return $this->error_response('Invalid ZIP archive', RISEUP_HTTP_BAD_REQUEST);
        }

        // Determine plugin slug from ZIP.
        $detected_slug = $this->detect_plugin_slug_from_zip($zip);
        $zip->close();

        if (!$detected_slug) {
            @unlink($temp_file);
            $this->logger->log_upload_failed($slug, 'Could not detect plugin in ZIP');
            return $this->error_response('Could not detect plugin in ZIP', RISEUP_HTTP_BAD_REQUEST);
        }

        // Use detected slug if not provided.
        if (empty($slug)) {
            $slug = $detected_slug;
        }

        // Extract to plugins directory.
        $plugins_dir  = WP_PLUGIN_DIR;
        $target_dir   = $plugins_dir . '/' . $slug;
        $is_update    = is_dir($target_dir);

        // If updating, check if currently active.
        $was_active = false;
        if ($is_update) {
            $plugin_file = $this->find_plugin_file($slug);
            if ($plugin_file) {
                $was_active = is_plugin_active($plugin_file);
                if ($was_active) {
                    deactivate_plugins($plugin_file);
                }
            }
            // Remove old version.
            $this->delete_directory($target_dir);
        }

        // Extract ZIP.
        $zip = new ZipArchive();
        $zip->open($temp_file);
        $zip->extractTo($plugins_dir);
        $zip->close();
        @unlink($temp_file);

        // Find the main plugin file.
        $plugin_file = $this->find_plugin_file($slug);
        if (!$plugin_file) {
            $this->logger->log_upload_failed($slug, 'Could not find plugin file after extraction');
            return $this->error_response('Could not find plugin file after extraction', RISEUP_HTTP_SERVER_ERROR);
        }

        // Activate if requested or was previously active.
        $activated = false;
        if ($activate || $was_active) {
            $result = activate_plugin($plugin_file);
            if (is_wp_error($result)) {
                $this->logger->log_upload_failed($slug, RISEUP_MSG_ACTIVATION_FAILED . ': ' . $result->get_error_message());
                // Plugin uploaded but activation failed.
                return new WP_REST_Response(array(
                    'success'          => true,
                    'plugin_slug'      => $slug,
                    'is_update'        => $is_update,
                    'activated'        => false,
                    'activation_error' => $result->get_error_message(),
                ), RISEUP_HTTP_OK);
            }
            $activated = true;
        }

        // Log success.
        $this->logger->log_upload($slug, array(
            'is_update' => $is_update,
            'activated' => $activated,
            'file_size' => strlen($zip_content),
        ));

        return new WP_REST_Response(array(
            'success'     => true,
            'plugin_slug' => $slug,
            'is_update'   => $is_update,
            'activated'   => $activated,
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle list plugins.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_list_plugins($request) {
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

        return new WP_REST_Response(array(
            'success' => true,
            'plugins' => $plugins,
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle get single plugin.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_get_plugin($request) {
        $slug = $request->get_param('slug');

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = $this->find_plugin_file($slug);
        if (!$plugin_file) {
            return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND, RISEUP_HTTP_NOT_FOUND);
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_data    = $all_plugins[$plugin_file];

        return new WP_REST_Response(array(
            'success' => true,
            'plugin'  => array(
                'slug'        => $slug,
                'name'        => $plugin_data['Name'],
                'version'     => $plugin_data['Version'],
                'author'      => $plugin_data['Author'],
                'description' => $plugin_data['Description'],
                'active'      => in_array($plugin_file, $active_plugins, true),
                'plugin_file' => $plugin_file,
            ),
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle enable plugin.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_enable_plugin($request) {
        $slug = $request->get_param('slug');

        $plugin_file = $this->find_plugin_file($slug);
        if (!$plugin_file) {
            return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND, RISEUP_HTTP_NOT_FOUND);
        }

        if (is_plugin_active($plugin_file)) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Plugin already active',
            ), RISEUP_HTTP_OK);
        }

        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            $this->logger->log_plugin_action(RISEUP_ACTION_ENABLE, $slug, RISEUP_STATUS_FAILED, array(), $result->get_error_message());
            return $this->error_response(RISEUP_MSG_ACTIVATION_FAILED . ': ' . $result->get_error_message(), RISEUP_HTTP_SERVER_ERROR);
        }

        $this->logger->log_enable($slug);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Plugin activated',
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle disable plugin.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_disable_plugin($request) {
        $slug = $request->get_param('slug');

        $plugin_file = $this->find_plugin_file($slug);
        if (!$plugin_file) {
            return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND, RISEUP_HTTP_NOT_FOUND);
        }

        if (!is_plugin_active($plugin_file)) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Plugin already inactive',
            ), RISEUP_HTTP_OK);
        }

        deactivate_plugins($plugin_file);
        $this->logger->log_disable($slug);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Plugin deactivated',
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle delete plugin.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_delete_plugin($request) {
        $slug = $request->get_param('slug');

        $plugin_file = $this->find_plugin_file($slug);
        if (!$plugin_file) {
            return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND, RISEUP_HTTP_NOT_FOUND);
        }

        // Deactivate first.
        if (is_plugin_active($plugin_file)) {
            deactivate_plugins($plugin_file);
        }

        // Delete plugin directory.
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (is_dir($plugin_dir)) {
            $this->delete_directory($plugin_dir);
        } else {
            // Single-file plugin.
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
            if (file_exists($plugin_path)) {
                @unlink($plugin_path);
            }
        }

        $this->logger->log_delete($slug);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Plugin deleted',
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle plugin file operations (replace/delete).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_plugin_files($request) {
        $slug   = $request->get_param('slug');
        $method = $request->get_method();
        $data   = $request->get_json_params();

        // Verify plugin exists.
        $plugin_file = $this->find_plugin_file($slug);
        if (!$plugin_file) {
            return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND, RISEUP_HTTP_NOT_FOUND);
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (!is_dir($plugin_dir)) {
            return $this->error_response('Plugin directory not found', RISEUP_HTTP_NOT_FOUND);
        }

        if (empty($data['path'])) {
            return $this->error_response('File path is required', RISEUP_HTTP_BAD_REQUEST);
        }

        $relative_path = ltrim($data['path'], '/\\');
        $target_file   = $plugin_dir . '/' . $relative_path;

        // Prevent directory traversal.
        $real_plugin_dir = realpath($plugin_dir);
        $real_target     = realpath(dirname($target_file));
        if ($real_target && strpos($real_target, $real_plugin_dir) !== 0) {
            return $this->error_response('Invalid file path', RISEUP_HTTP_BAD_REQUEST);
        }

        if ($method === 'DELETE') {
            // Delete file.
            if (!file_exists($target_file)) {
                return $this->error_response('File not found', RISEUP_HTTP_NOT_FOUND);
            }

            if (!@unlink($target_file)) {
                return $this->error_response('Failed to delete file', RISEUP_HTTP_SERVER_ERROR);
            }

            $this->logger->log_file_delete($slug, $relative_path);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'File deleted',
            ), RISEUP_HTTP_OK);
        }

        // POST = Replace/create file.
        if (empty($data['content'])) {
            return $this->error_response('File content is required', RISEUP_HTTP_BAD_REQUEST);
        }

        // Decode base64 content.
        $content = base64_decode($data['content']);
        if ($content === false) {
            return $this->error_response('Invalid base64 content', RISEUP_HTTP_BAD_REQUEST);
        }

        // Create directory if needed.
        $target_dir = dirname($target_file);
        if (!is_dir($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        if (file_put_contents($target_file, $content) === false) {
            return $this->error_response('Failed to write file', RISEUP_HTTP_SERVER_ERROR);
        }

        $this->logger->log_file_replace($slug, $relative_path, array('size' => strlen($content)));

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'File updated',
            'path'    => $relative_path,
        ), RISEUP_HTTP_OK);
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
        $data   = $request->get_json_params();
        $result = $this->post_manager->create_post($data);

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_CREATED : RISEUP_HTTP_BAD_REQUEST);
    }

    /**
     * Handle get post.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_get_post($request) {
        $post_id = (int) $request->get_param('id');
        $post    = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return $this->error_response('Post not found', RISEUP_HTTP_NOT_FOUND);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'post'    => array(
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'slug'       => $post->post_name,
                'content'    => $post->post_content,
                'status'     => $post->post_status,
                'permalink'  => get_permalink($post->ID),
                'categories' => wp_get_post_categories($post->ID),
                'created_at' => $post->post_date_gmt . 'Z',
                'updated_at' => $post->post_modified_gmt . 'Z',
            ),
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle update post.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_update_post($request) {
        $post_id = (int) $request->get_param('id');
        $data    = $request->get_json_params();
        $result  = $this->post_manager->update_post($post_id, $data);

        return new WP_REST_Response($result, $result['success'] ? RISEUP_HTTP_OK : RISEUP_HTTP_BAD_REQUEST);
    }

    /**
     * Handle list categories.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_list_categories($request) {
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
        $filters = array(
            'plugin' => $request->get_param('plugin'),
            'action' => $request->get_param('action'),
            'user'   => $request->get_param('user'),
            'status' => $request->get_param('status'),
            'from'   => $request->get_param('from'),
            'to'     => $request->get_param('to'),
        );

        $limit  = (int) ($request->get_param('limit') ?? RISEUP_DEFAULT_LIMIT);
        $offset = (int) ($request->get_param('offset') ?? 0);

        $result = $this->db->query_transactions($filters, $limit, $offset);

        return new WP_REST_Response(array(
            'success' => true,
            'total'   => $result['total'],
            'limit'   => $limit,
            'offset'  => $offset,
            'logs'    => $result['logs'],
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle logs stats.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_logs_stats($request) {
        $stats = $this->db->get_stats();

        return new WP_REST_Response(array(
            'success' => true,
            'stats'   => $stats,
        ), RISEUP_HTTP_OK);
    }

    /**
     * Handle get single log.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_get_log($request) {
        $id  = (int) $request->get_param('id');
        $log = $this->db->get_transaction($id);

        if (!$log) {
            return $this->error_response('Log entry not found', RISEUP_HTTP_NOT_FOUND);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'log'     => $log,
        ), RISEUP_HTTP_OK);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Create error response.
     *
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     *
     * @return WP_REST_Response
     */
    private function error_response($message, $status) {
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
        $temp_dir = __DIR__ . '/' . RISEUP_TEMP_DIR;
        if (!is_dir($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        return $temp_dir;
    }

    /**
     * Find plugin file by slug.
     *
     * @param string $slug Plugin slug.
     *
     * @return string|false Plugin file path or false.
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

        return false;
    }

    /**
     * Detect plugin slug from ZIP archive.
     *
     * @param ZipArchive $zip ZIP archive.
     *
     * @return string|false Plugin slug or false.
     */
    private function detect_plugin_slug_from_zip($zip) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Look for plugin-name/plugin-name.php pattern.
            if (preg_match('#^([^/]+)/\1\.php$#', $name, $matches)) {
                return $matches[1];
            }
            // Look for any .php file in root folder with Plugin Name header.
            if (preg_match('#^([^/]+)/[^/]+\.php$#', $name, $matches)) {
                $content = $zip->getFromIndex($i);
                if (stripos($content, 'Plugin Name:') !== false) {
                    return $matches[1];
                }
            }
        }
        return false;
    }

    /**
     * Delete directory recursively.
     *
     * @param string $dir Directory path.
     *
     * @return bool
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

    // =========================================================================
    // DELTA SYNC HANDLER
    // =========================================================================

    /**
     * Handle delta file sync (multi-file update/delete).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_plugin_sync($request) {
        $slug = $request->get_param('slug');
        $data = $request->get_json_params();

        // Verify plugin exists.
        $plugin_file = $this->find_plugin_file($slug);
        if (!$plugin_file) {
            return $this->error_response(RISEUP_MSG_PLUGIN_NOT_FOUND, RISEUP_HTTP_NOT_FOUND);
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if (!is_dir($plugin_dir)) {
            return $this->error_response('Plugin directory not found', RISEUP_HTTP_NOT_FOUND);
        }

        if (empty($data['files']) || !is_array($data['files'])) {
            return $this->error_response('Files array is required', RISEUP_HTTP_BAD_REQUEST);
        }

        // Load upload ignore patterns.
        $ignore = RiseUp_Upload_Ignore::from_directory($plugin_dir);

        $results        = array();
        $files_updated  = 0;
        $files_deleted  = 0;
        $files_ignored  = 0;
        $ignored_files  = array();

        foreach ($data['files'] as $file) {
            if (empty($file['path'])) {
                continue;
            }

            $relative_path = ltrim($file['path'], '/\\');
            $action        = $file['action'] ?? 'replace';

            // Check if file should be ignored.
            if ($ignore->should_ignore($relative_path)) {
                $files_ignored++;
                $ignored_files[] = $relative_path;
                $results[] = array(
                    'path'   => $relative_path,
                    'action' => 'ignored',
                    'status' => 'skipped',
                    'reason' => RISEUP_MSG_FILE_IGNORED,
                );
                continue;
            }

            $target_file = $plugin_dir . '/' . $relative_path;

            // Prevent directory traversal.
            $real_plugin_dir = realpath($plugin_dir);
            $parent_dir      = dirname($target_file);
            
            if ($action === 'replace') {
                // Create parent directories if needed.
                if (!is_dir($parent_dir)) {
                    wp_mkdir_p($parent_dir);
                }
                
                $real_parent = realpath($parent_dir);
                if ($real_parent === false || strpos($real_parent, $real_plugin_dir) !== 0) {
                    $results[] = array(
                        'path'   => $relative_path,
                        'action' => $action,
                        'status' => 'error',
                        'reason' => 'Invalid path (directory traversal)',
                    );
                    continue;
                }

                // Decode and write content.
                if (empty($file['content'])) {
                    $results[] = array(
                        'path'   => $relative_path,
                        'action' => $action,
                        'status' => 'error',
                        'reason' => 'Content is required for replace action',
                    );
                    continue;
                }

                $content = base64_decode($file['content']);
                if ($content === false) {
                    $results[] = array(
                        'path'   => $relative_path,
                        'action' => $action,
                        'status' => 'error',
                        'reason' => 'Invalid base64 content',
                    );
                    continue;
                }

                if (file_put_contents($target_file, $content) !== false) {
                    $files_updated++;
                    $results[] = array(
                        'path'   => $relative_path,
                        'action' => 'replaced',
                        'status' => 'success',
                    );
                } else {
                    $results[] = array(
                        'path'   => $relative_path,
                        'action' => $action,
                        'status' => 'error',
                        'reason' => 'Failed to write file',
                    );
                }
            } elseif ($action === 'delete') {
                if (file_exists($target_file)) {
                    $real_target = realpath($target_file);
                    if ($real_target === false || strpos($real_target, $real_plugin_dir) !== 0) {
                        $results[] = array(
                            'path'   => $relative_path,
                            'action' => $action,
                            'status' => 'error',
                            'reason' => 'Invalid path (directory traversal)',
                        );
                        continue;
                    }

                    if (@unlink($target_file)) {
                        $files_deleted++;
                        $results[] = array(
                            'path'   => $relative_path,
                            'action' => 'deleted',
                            'status' => 'success',
                        );
                    } else {
                        $results[] = array(
                            'path'   => $relative_path,
                            'action' => $action,
                            'status' => 'error',
                            'reason' => 'Failed to delete file',
                        );
                    }
                } else {
                    $results[] = array(
                        'path'   => $relative_path,
                        'action' => 'deleted',
                        'status' => 'success',
                        'reason' => 'File did not exist',
                    );
                }
            }
        }

        // Log the sync operation.
        $this->logger->log_plugin_action(
            RISEUP_ACTION_SYNC,
            $slug,
            RISEUP_STATUS_SUCCESS,
            array(
                'files_updated' => $files_updated,
                'files_deleted' => $files_deleted,
                'files_ignored' => $files_ignored,
            )
        );

        return new WP_REST_Response(array(
            'success'       => true,
            'files_updated' => $files_updated,
            'files_deleted' => $files_deleted,
            'files_ignored' => $files_ignored,
            'ignored_files' => $ignored_files,
            'results'       => $results,
        ), RISEUP_HTTP_OK);
    }

    // =========================================================================
    // EXPORT SELF HANDLER
    // =========================================================================

    /**
     * Handle export-self (export this plugin as a ZIP).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function handle_export_self($request) {
        $plugin_dir = dirname(__FILE__);
        $plugin_slug = basename($plugin_dir);

        // Load upload ignore patterns.
        $ignore = RiseUp_Upload_Ignore::from_directory($plugin_dir);

        // Create temp ZIP file.
        $temp_dir  = $this->get_temp_dir();
        $zip_file  = $temp_dir . '/' . $plugin_slug . '-' . time() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) !== true) {
            return $this->error_response('Failed to create ZIP archive', RISEUP_HTTP_SERVER_ERROR);
        }

        // Add files recursively.
        $files_added = $this->add_directory_to_zip($zip, $plugin_dir, $plugin_slug, $ignore);
        $zip->close();

        if (!file_exists($zip_file)) {
            return $this->error_response('Failed to create ZIP file', RISEUP_HTTP_SERVER_ERROR);
        }

        // Read and encode ZIP.
        $zip_content = file_get_contents($zip_file);
        $checksum    = md5($zip_content);
        $base64_zip  = base64_encode($zip_content);

        // Clean up temp file.
        @unlink($zip_file);

        // Log the export.
        $this->logger->log_plugin_action(
            RISEUP_ACTION_EXPORT_SELF,
            $plugin_slug,
            RISEUP_STATUS_SUCCESS,
            array(
                'files_included' => $files_added,
                'zip_size'       => strlen($zip_content),
            )
        );

        return new WP_REST_Response(array(
            'success'     => true,
            'plugin_name' => RISEUP_NAME,
            'version'     => RISEUP_VERSION,
            'plugin_slug' => $plugin_slug,
            'plugin_zip'  => $base64_zip,
            'checksum'    => $checksum,
            'file_count'  => $files_added,
        ), RISEUP_HTTP_OK);
    }

    /**
     * Add directory to ZIP archive recursively.
     *
     * @param ZipArchive           $zip        ZIP archive.
     * @param string               $dir        Directory path.
     * @param string               $base       Base path in ZIP.
     * @param RiseUp_Upload_Ignore $ignore     Upload ignore instance.
     *
     * @return int Number of files added.
     */
    private function add_directory_to_zip($zip, $dir, $base, $ignore) {
        $files_added = 0;
        $iterator    = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $real_path     = $file->getRealPath();
            $relative_path = substr($real_path, strlen($dir) + 1);
            $relative_path = str_replace('\\', '/', $relative_path);
            $zip_path      = $base . '/' . $relative_path;

            // Check if should be ignored.
            if ($ignore->should_ignore($relative_path)) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($zip_path);
            } else {
                $zip->addFile($real_path, $zip_path);
                $files_added++;
            }
        }

        return $files_added;
    }
}

// Initialize plugin.
add_action('plugins_loaded', array('RiseUp_Asia', 'get_instance'));
