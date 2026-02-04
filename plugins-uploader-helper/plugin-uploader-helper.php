<?php
/**
 * Plugin Name: Plugin Uploader Helper
 * Plugin URI: https://riseup-asia.com/
 * Description: Secure REST API for remote plugin management - upload, enable, disable, delete, and replace single files.
 * Version: 1.1.0
 * Author: Riseup Asia / Alim Ul Karim
 * Author URI: https://riseup-asia.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: plugin-uploader-helper
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PUH_VERSION', '1.1.0');
define('PUH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PUH_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin Uploader Helper - Secure REST API for remote plugin management.
 */
class Plugin_Uploader_Helper {
    
    /**
     * REST namespace.
     */
    const REST_NAMESPACE = 'plugin-uploader/v1';

    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register all REST routes.
     */
    public function register_routes() {
        // Status endpoint
        register_rest_route(self::REST_NAMESPACE, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_status'),
            'permission_callback' => array($this, 'check_read_permission'),
        ));

        // Upload plugin (ZIP or base64)
        register_rest_route(self::REST_NAMESPACE, '/upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_upload'),
            'permission_callback' => array($this, 'check_install_permission'),
        ));

        // List all plugins
        register_rest_route(self::REST_NAMESPACE, '/plugins', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_list_plugins'),
            'permission_callback' => array($this, 'check_read_permission'),
        ));

        // Get single plugin info
        register_rest_route(self::REST_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_get_plugin'),
            'permission_callback' => array($this, 'check_read_permission'),
        ));

        // Enable (activate) plugin
        register_rest_route(self::REST_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/enable', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_enable_plugin'),
            'permission_callback' => array($this, 'check_activate_permission'),
        ));

        // Disable (deactivate) plugin
        register_rest_route(self::REST_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/disable', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_disable_plugin'),
            'permission_callback' => array($this, 'check_activate_permission'),
        ));

        // Delete plugin
        register_rest_route(self::REST_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/delete', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'handle_delete_plugin'),
            'permission_callback' => array($this, 'check_delete_permission'),
        ));

        // Replace single file within a plugin
        register_rest_route(self::REST_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/files', array(
            'methods' => 'PUT',
            'callback' => array($this, 'handle_replace_file'),
            'permission_callback' => array($this, 'check_install_permission'),
        ));

        // List files in a plugin
        register_rest_route(self::REST_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/files', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_list_files'),
            'permission_callback' => array($this, 'check_read_permission'),
        ));

        // Delete single file from a plugin
        register_rest_route(self::REST_NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/files', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'handle_delete_file'),
            'permission_callback' => array($this, 'check_install_permission'),
        ));
    }

    // =========================================================================
    // Permission Callbacks
    // =========================================================================

    /**
     * Check read permission.
     */
    public function check_read_permission($request) {
        if (!current_user_can('read')) {
            return $this->permission_error('read');
        }
        return true;
    }

    /**
     * Check install plugins permission.
     */
    public function check_install_permission($request) {
        if (!current_user_can('install_plugins')) {
            return $this->permission_error('install_plugins');
        }
        return true;
    }

    /**
     * Check activate plugins permission.
     */
    public function check_activate_permission($request) {
        if (!current_user_can('activate_plugins')) {
            return $this->permission_error('activate_plugins');
        }
        return true;
    }

    /**
     * Check delete plugins permission.
     */
    public function check_delete_permission($request) {
        if (!current_user_can('delete_plugins')) {
            return $this->permission_error('delete_plugins');
        }
        return true;
    }

    /**
     * Build permission denied error.
     */
    private function permission_error($capability) {
        return new WP_Error(
            'rest_forbidden',
            sprintf(__('You do not have the "%s" capability.', 'plugin-uploader-helper'), $capability),
            array('status' => 403)
        );
    }

    // =========================================================================
    // Route Handlers
    // =========================================================================

    /**
     * GET /status - Check helper availability.
     */
    public function handle_status($request) {
        return rest_ensure_response(array(
            'status' => 'ok',
            'message' => 'Plugin Uploader Helper is active',
            'version' => PUH_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'endpoints' => array(
                'upload' => '/wp-json/' . self::REST_NAMESPACE . '/upload',
                'plugins' => '/wp-json/' . self::REST_NAMESPACE . '/plugins',
                'enable' => '/wp-json/' . self::REST_NAMESPACE . '/plugins/{slug}/enable',
                'disable' => '/wp-json/' . self::REST_NAMESPACE . '/plugins/{slug}/disable',
                'delete' => '/wp-json/' . self::REST_NAMESPACE . '/plugins/{slug}/delete',
                'files' => '/wp-json/' . self::REST_NAMESPACE . '/plugins/{slug}/files',
            ),
        ));
    }

    /**
     * POST /upload - Upload and install a plugin.
     * Supports: multipart form-data with 'plugin' or 'file' field, OR JSON with 'plugin_data' (base64).
     */
    public function handle_upload($request) {
        $files = $request->get_file_params();
        $activate = true;
        
        // Check for file upload
        if (!empty($files['plugin']) || !empty($files['file'])) {
            $file = !empty($files['plugin']) ? $files['plugin'] : $files['file'];
            
            // Validate file type
            $file_type = wp_check_filetype($file['name']);
            if ($file_type['ext'] !== 'zip') {
                return new WP_Error('invalid_type', 'Only ZIP files are allowed.', array('status' => 400));
            }
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return new WP_Error('upload_error', 'File upload error code: ' . $file['error'], array('status' => 400));
            }
            
            $params = $request->get_params();
            $activate = isset($params['activate']) ? filter_var($params['activate'], FILTER_VALIDATE_BOOLEAN) : true;
            
            return $this->install_plugin($file['tmp_name'], $activate);
        }
        
        // Check for base64-encoded plugin data
        $params = $request->get_json_params();
        if (!empty($params['plugin_data'])) {
            $plugin_data = base64_decode($params['plugin_data']);
            $plugin_name = sanitize_file_name($params['plugin_name'] ?? 'plugin.zip');
            
            if ($plugin_data === false) {
                return new WP_Error('invalid_data', 'Invalid base64 plugin data.', array('status' => 400));
            }
            
            // Save to temp file
            $temp_file = wp_tempnam($plugin_name);
            file_put_contents($temp_file, $plugin_data);
            
            $activate = isset($params['activate']) ? filter_var($params['activate'], FILTER_VALIDATE_BOOLEAN) : true;
            
            return $this->install_plugin($temp_file, $activate);
        }
        
        return new WP_Error(
            'no_file',
            'No plugin file provided. Use "plugin" or "file" field for multipart, or "plugin_data" for base64.',
            array('status' => 400)
        );
    }

    /**
     * GET /plugins - List all installed plugins.
     */
    public function handle_list_plugins($request) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        
        $plugins = array();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }
            
            $plugins[] = array(
                'slug' => $slug,
                'file' => $plugin_file,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'description' => $plugin_data['Description'],
                'active' => in_array($plugin_file, $active_plugins),
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'count' => count($plugins),
            'plugins' => $plugins,
        ));
    }

    /**
     * GET /plugins/{slug} - Get single plugin info.
     */
    public function handle_get_plugin($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        $plugin_file = $this->resolve_plugin_file($slug);
        
        if (!$plugin_file) {
            return new WP_Error('not_found', 'Plugin not found: ' . $slug, array('status' => 404));
        }
        
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        $plugin_data = get_plugin_data($plugin_path);
        $active_plugins = get_option('active_plugins', array());
        
        return rest_ensure_response(array(
            'success' => true,
            'slug' => $slug,
            'file' => $plugin_file,
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'author' => $plugin_data['Author'],
            'description' => $plugin_data['Description'],
            'active' => in_array($plugin_file, $active_plugins),
            'plugin_uri' => $plugin_data['PluginURI'],
            'text_domain' => $plugin_data['TextDomain'],
            'requires_wp' => $plugin_data['RequiresWP'],
            'requires_php' => $plugin_data['RequiresPHP'],
        ));
    }

    /**
     * POST /plugins/{slug}/enable - Activate a plugin.
     */
    public function handle_enable_plugin($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        $plugin_file = $this->resolve_plugin_file($slug);
        
        if (!$plugin_file) {
            return new WP_Error('not_found', 'Plugin not found: ' . $slug, array('status' => 404));
        }
        
        $result = activate_plugin($plugin_file);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                'activation_failed',
                $result->get_error_message(),
                array('status' => 500)
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Plugin activated successfully.',
            'slug' => $slug,
            'file' => $plugin_file,
        ));
    }

    /**
     * POST /plugins/{slug}/disable - Deactivate a plugin.
     */
    public function handle_disable_plugin($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        $plugin_file = $this->resolve_plugin_file($slug);
        
        if (!$plugin_file) {
            return new WP_Error('not_found', 'Plugin not found: ' . $slug, array('status' => 404));
        }
        
        deactivate_plugins($plugin_file);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Plugin deactivated successfully.',
            'slug' => $slug,
            'file' => $plugin_file,
        ));
    }

    /**
     * DELETE /plugins/{slug}/delete - Delete a plugin.
     */
    public function handle_delete_plugin($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        $plugin_file = $this->resolve_plugin_file($slug);
        
        if (!$plugin_file) {
            return new WP_Error('not_found', 'Plugin not found: ' . $slug, array('status' => 404));
        }
        
        // Deactivate first
        deactivate_plugins($plugin_file);
        
        // Delete plugin
        if (!function_exists('delete_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $result = delete_plugins(array($plugin_file));
        
        if (is_wp_error($result)) {
            return new WP_Error(
                'delete_failed',
                $result->get_error_message(),
                array('status' => 500)
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Plugin deleted successfully.',
            'slug' => $slug,
        ));
    }

    /**
     * GET /plugins/{slug}/files - List files in a plugin.
     */
    public function handle_list_files($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        
        if (!is_dir($plugin_dir)) {
            return new WP_Error('not_found', 'Plugin directory not found: ' . $slug, array('status' => 404));
        }
        
        $files = $this->scan_directory($plugin_dir, $plugin_dir);
        
        return rest_ensure_response(array(
            'success' => true,
            'slug' => $slug,
            'count' => count($files),
            'files' => $files,
        ));
    }

    /**
     * PUT /plugins/{slug}/files - Replace or create a single file within a plugin.
     * Body: { "path": "relative/path/to/file.php", "content": "<?php ...", "encoding": "plain|base64" }
     */
    public function handle_replace_file($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        
        if (!is_dir($plugin_dir)) {
            return new WP_Error('not_found', 'Plugin directory not found: ' . $slug, array('status' => 404));
        }
        
        $params = $request->get_json_params();
        $rel_path = isset($params['path']) ? ltrim($params['path'], '/\\') : '';
        $content = isset($params['content']) ? $params['content'] : '';
        $encoding = isset($params['encoding']) ? $params['encoding'] : 'plain';
        
        if (empty($rel_path)) {
            return new WP_Error('invalid_path', 'File path is required.', array('status' => 400));
        }
        
        // Security: prevent path traversal
        $safe_path = realpath($plugin_dir) . '/' . $rel_path;
        if (strpos($safe_path, realpath($plugin_dir)) !== 0) {
            return new WP_Error('invalid_path', 'Path traversal not allowed.', array('status' => 400));
        }
        
        // Decode if base64
        if ($encoding === 'base64') {
            $content = base64_decode($content);
            if ($content === false) {
                return new WP_Error('invalid_content', 'Invalid base64 content.', array('status' => 400));
            }
        }
        
        // Ensure parent directory exists
        $target_path = $plugin_dir . '/' . $rel_path;
        $parent_dir = dirname($target_path);
        if (!is_dir($parent_dir)) {
            wp_mkdir_p($parent_dir);
        }
        
        // Write file
        $result = file_put_contents($target_path, $content);
        
        if ($result === false) {
            return new WP_Error('write_failed', 'Failed to write file.', array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'File updated successfully.',
            'slug' => $slug,
            'path' => $rel_path,
            'bytes_written' => $result,
        ));
    }

    /**
     * DELETE /plugins/{slug}/files - Delete a single file from a plugin.
     * Body: { "path": "relative/path/to/file.php" }
     */
    public function handle_delete_file($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        
        if (!is_dir($plugin_dir)) {
            return new WP_Error('not_found', 'Plugin directory not found: ' . $slug, array('status' => 404));
        }
        
        $params = $request->get_json_params();
        $rel_path = isset($params['path']) ? ltrim($params['path'], '/\\') : '';
        
        if (empty($rel_path)) {
            return new WP_Error('invalid_path', 'File path is required.', array('status' => 400));
        }
        
        // Security: prevent path traversal
        $target_path = $plugin_dir . '/' . $rel_path;
        $real_target = realpath($target_path);
        $real_plugin_dir = realpath($plugin_dir);
        
        if (!$real_target || strpos($real_target, $real_plugin_dir) !== 0) {
            return new WP_Error('invalid_path', 'Path traversal not allowed or file not found.', array('status' => 400));
        }
        
        if (!file_exists($target_path)) {
            return new WP_Error('not_found', 'File not found: ' . $rel_path, array('status' => 404));
        }
        
        if (!unlink($target_path)) {
            return new WP_Error('delete_failed', 'Failed to delete file.', array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'File deleted successfully.',
            'slug' => $slug,
            'path' => $rel_path,
        ));
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Install plugin from a ZIP file path.
     */
    private function install_plugin($zip_path, $activate = true) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        
        WP_Filesystem();
        
        // Use silent skin to capture output
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        
        // Install the plugin
        $result = $upgrader->install($zip_path);
        
        // Clean up temp file
        @unlink($zip_path);
        
        if (is_wp_error($result)) {
            return new WP_Error('install_failed', $result->get_error_message(), array('status' => 500));
        }
        
        if ($result === false) {
            $errors = $skin->get_errors();
            if (is_wp_error($errors) && $errors->has_errors()) {
                return new WP_Error('install_failed', $errors->get_error_message(), array('status' => 500));
            }
            return new WP_Error('install_failed', 'Installation failed for unknown reason.', array('status' => 500));
        }
        
        // Get the installed plugin info
        $plugin_info = $upgrader->plugin_info();
        
        $response = array(
            'success' => true,
            'message' => 'Plugin installed successfully.',
            'plugin' => $plugin_info,
            'activated' => false,
        );
        
        // Activate if requested
        if ($activate && $plugin_info) {
            $activate_result = activate_plugin($plugin_info);
            
            if (is_wp_error($activate_result)) {
                $response['activation_error'] = $activate_result->get_error_message();
            } else {
                $response['activated'] = true;
                $response['message'] = 'Plugin installed and activated successfully.';
            }
        }
        
        // Get plugin details
        if ($plugin_info) {
            $all_plugins = get_plugins();
            if (isset($all_plugins[$plugin_info])) {
                $response['plugin_details'] = array(
                    'name' => $all_plugins[$plugin_info]['Name'],
                    'version' => $all_plugins[$plugin_info]['Version'],
                    'author' => $all_plugins[$plugin_info]['Author'],
                    'description' => $all_plugins[$plugin_info]['Description'],
                );
            }
        }
        
        return rest_ensure_response($response);
    }

    /**
     * Resolve plugin slug to plugin file.
     */
    private function resolve_plugin_file($slug) {
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
     * Recursively scan a directory for files.
     */
    private function scan_directory($dir, $base_dir) {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            $rel_path = str_replace($base_dir . '/', '', $file->getPathname());
            $files[] = array(
                'path' => $rel_path,
                'size' => $file->getSize(),
                'modified' => date('c', $file->getMTime()),
                'hash' => md5_file($file->getPathname()),
            );
        }
        
        return $files;
    }
}

// Initialize the plugin
Plugin_Uploader_Helper::get_instance();
