<?php
/**
 * Plugin Name: Plugins Onboard
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Manages plugin onboarding and snapshots with robust security, OAuth 2.0 authentication, and comprehensive audit logging.
 * Version: 1.0.8
 * Author: MD ALIM UL KARIM
 * Author URI: https://rasia.pro/alim-r-profile-v1
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: plugins-onboard
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 8.2
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define core plugin constants (these never change).
define('ONBOARD_PLUGIN_VERSION', '1.0.8');
define('ONBOARD_PLUGIN_FILE', __FILE__);
define('ONBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ONBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ONBOARD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Foundation files — loaded raw (no loader available yet).
 * Order matters: constants → logger → boolean helpers → include-files loader.
 */
require_once ONBOARD_PLUGIN_DIR . 'includes/constants.php';
require_once ONBOARD_PLUGIN_DIR . 'includes/class-logger.php';
require_once ONBOARD_PLUGIN_DIR . 'includes/class-error-log.php';

// Log plugin initialization start.
OnboardLogger::debug('=== PLUGIN INITIALIZATION STARTED ===');
OnboardLogger::debug('Plugin Version: ' . ONBOARD_PLUGIN_VERSION);
OnboardLogger::debug('WordPress Version: ' . (function_exists('get_bloginfo') ? get_bloginfo('version') : 'Unknown'));
OnboardLogger::debug('PHP Version: ' . PHP_VERSION);

require_once ONBOARD_PLUGIN_DIR . 'includes/class-boolean-helpers.php';
require_once ONBOARD_PLUGIN_DIR . 'includes/class-include-files.php';

/**
 * Load remaining foundation files via OnboardIncludeFiles.
 */
OnboardLogger::debug('Loading foundation classes via OnboardIncludeFiles...');
OnboardIncludeFiles::load(OnboardIncludeFiles::PATHS);
OnboardIncludeFiles::load(OnboardIncludeFiles::INIT_HELPERS);
OnboardIncludeFiles::load(OnboardIncludeFiles::CONFIG);

/**
 * Load all remaining dependencies via OnboardIncludeFiles.
 */
OnboardLogger::debug('Loading plugin dependencies via OnboardIncludeFiles...');
OnboardIncludeFiles::loadMany(array(
    OnboardIncludeFiles::DATABASE,
    OnboardIncludeFiles::TOKEN_ENCRYPTION,
    OnboardIncludeFiles::RATE_LIMITER,
    OnboardIncludeFiles::AUDIT_LOGGER,
    OnboardIncludeFiles::OAUTH,
    OnboardIncludeFiles::MUTATION_TOKEN,
    OnboardIncludeFiles::IP_WHITELIST,
    OnboardIncludeFiles::SNAPSHOT,
    OnboardIncludeFiles::BACKUP_MANAGER,
    OnboardIncludeFiles::PLUGIN_MANAGER,
    OnboardIncludeFiles::UPLOAD_VALIDATOR,
    OnboardIncludeFiles::DEBUG_MAINTENANCE,
    OnboardIncludeFiles::CLEANUP,
    OnboardIncludeFiles::SECURITY_UTILS,
    OnboardIncludeFiles::API,
    OnboardIncludeFiles::API_PERMISSIONS,
    OnboardIncludeFiles::ADMIN_UI,
));
OnboardIncludeFiles::logSummary();
OnboardLogger::debug('Dependency loading completed');

/**
 * The main plugin class.
 */
class PluginsOnboard {

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version;

    /**
     * Config instance.
     *
     * @var OnboardConfig|null
     */
    public $config = null;

    /**
     * Database instance.
     *
     * @var OnboardDatabase|null
     */
    public $db = null;

    /**
     * Audit logger instance.
     *
     * @var OnboardAuditLogger|null
     */
    public $audit_logger = null;

    /**
     * OAuth instance.
     *
     * @var OnboardOAuth|null
     */
    public $oauth = null;

    /**
     * Mutation token instance.
     *
     * @var OnboardMutationToken|null
     */
    public $mutation_token = null;

    /**
     * IP whitelist instance.
     *
     * @var OnboardIPWhitelist|null
     */
    public $ip_whitelist = null;

    /**
     * Snapshot manager instance.
     *
     * @var OnboardSnapshot|null
     */
    public $snapshot = null;

    /**
     * Backup manager instance.
     *
     * @var OnboardBackupManager|null
     */
    public $backup_manager = null;

    /**
     * Plugin manager instance.
     *
     * @var OnboardPluginManager|null
     */
    public $plugin_manager = null;

    /**
     * Cleanup instance.
     *
     * @var OnboardCleanup|null
     */
    public $cleanup = null;

    /**
     * API instance.
     *
     * @var OnboardAPI|null
     */
    public $api = null;

    /**
     * Admin UI instance.
     *
     * @var OnboardAdminUI|null
     */
    public $admin_ui = null;

    /**
     * Initialization error.
     *
     * @var string|null
     */
    public $init_error = null;

    /**
     * Singleton instance.
     *
     * @var PluginsOnboard|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return PluginsOnboard
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
        OnboardLogger::debug('PluginsOnboard constructor called');

        try {
            $this->version = ONBOARD_PLUGIN_VERSION;
            OnboardLogger::debug('Version set: ' . $this->version);

            // Initialize config first.
            if (OnboardBooleanHelpers::isClassExists('OnboardConfig')) {
                OnboardLogger::debug('Initializing OnboardConfig...');
                $this->config = OnboardConfig::get_instance();
                OnboardLogger::debug('OnboardConfig initialized successfully');
            }
            if (OnboardBooleanHelpers::isClassMissing('OnboardConfig')) {
                OnboardLogger::error('OnboardConfig class not found');
            }

            OnboardLogger::debug('Initializing hooks...');
            $this->init_hooks();
            OnboardLogger::debug('Hooks initialized successfully');

        } catch (Throwable $e) {
            OnboardLogger::critical('Constructor failed', $e);
            $this->init_error = 'Plugin initialization failed: ' . $e->getMessage();
            OnboardErrorLog::logAndThrow($e, 'Plugins Onboard: Constructor failed:');
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        OnboardLogger::debug('Setting up WordPress hooks...');

        try {
            // Activation and deactivation hooks.
            OnboardLogger::debug('Registering activation hook');
            register_activation_hook(ONBOARD_PLUGIN_FILE, array($this, 'activate'));

            OnboardLogger::debug('Registering deactivation hook');
            register_deactivation_hook(ONBOARD_PLUGIN_FILE, array($this, 'deactivate'));

            // Initialize plugin on plugins_loaded.
            OnboardLogger::debug('Adding plugins_loaded action');
            add_action('plugins_loaded', array($this, 'init'), 10);

            // Register REST API routes.
            OnboardLogger::debug('Adding rest_api_init action');
            add_action('rest_api_init', array($this, 'register_api_routes'));

            // NOTE: Cron tasks removed as per user request
            // add_action('onboard_cleanup_cron', array($this, 'run_cleanup'));

            // Show admin notices for errors.
            OnboardLogger::debug('Adding admin_notices action');
            add_action('admin_notices', array($this, 'show_admin_notices'));

            OnboardLogger::debug('All hooks registered successfully');

        } catch (Throwable $e) {
            OnboardLogger::critical('Failed to register hooks', $e);
            OnboardErrorLog::logAndThrow($e, 'Plugins Onboard: Hook registration failed:');
        }
    }

    /**
     * Show admin notices.
     */
    public function show_admin_notices() {
        if ($this->init_error) {
            echo '<div class="notice notice-error"><p><strong>Plugins Onboard Error:</strong> ' . esc_html($this->init_error) . '</p></div>';
        }
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        OnboardLogger::debug('=== PLUGIN ACTIVATION STARTED ===');

        try {
            // STEP 1: Ensure directories exist (MUST BE FIRST).
            OnboardLogger::debug('STEP 1: Ensuring directories exist...');
            if (!OnboardInitHelpers::ensure_directories_exist()) {
                $base_path = $this->get_base_path_for_display();
                throw new Exception("Failed to create required directories. Check permissions on {$base_path}");
            }
            OnboardLogger::debug('✓ STEP 1 COMPLETE: Directories ready');

            // STEP 2: Ensure database is initialized (depends on directories).
            OnboardLogger::debug('STEP 2: Ensuring database is ready...');
            $this->db = OnboardInitHelpers::ensure_database_ready();
            if (!$this->db) {
                $error_log = $this->get_log_dir_for_display() . 'error.log';
                throw new Exception("Failed to initialize database. Check {$error_log} for details.");
            }
            OnboardLogger::debug('✓ STEP 2 COMPLETE: Database ready');

            // STEP 3: Seed default settings.
            OnboardLogger::debug('STEP 3: Seeding default settings...');
            $this->seed_default_settings();
            OnboardLogger::debug('✓ STEP 3 COMPLETE: Default settings seeded');

            // STEP 4: Reload config with database.
            if ($this->config) {
                OnboardLogger::debug('STEP 4: Reloading config with database...');
                $this->config->set_database($this->db);
                $this->config->reload();
                OnboardLogger::debug('✓ STEP 4 COMPLETE: Config reloaded');
            }

            // STEP 5: Flush rewrite rules.
            OnboardLogger::debug('STEP 5: Flushing rewrite rules...');
            flush_rewrite_rules();
            OnboardLogger::debug('✓ STEP 5 COMPLETE: Rewrite rules flushed');

            OnboardLogger::debug('=== PLUGIN ACTIVATION COMPLETED SUCCESSFULLY ===');

        } catch (Throwable $e) {
            OnboardLogger::critical('Plugin activation failed', $e);
            OnboardErrorLog::log($e, 'Plugins Onboard Activation Error:');

            // Get dynamic log paths for error display.
            $log_dir = $this->get_log_dir_for_display();
            $debug_log = $log_dir . 'debug.log';
            $error_log = $log_dir . 'error.log';

            // Show error to user with helpful information.
            wp_die(
                '<h1>Plugin Activation Failed</h1>' .
                '<p><strong>Error:</strong> ' . esc_html($e->getMessage()) . '</p>' .
                '<p>Please check the logs for more details:</p>' .
                '<ul>' .
                '<li><code>' . esc_html($debug_log) . '</code></li>' .
                '<li><code>' . esc_html($error_log) . '</code></li>' .
                '</ul>',
                'Plugins Onboard Activation Error',
                array('back_link' => true)
            );
        }
    }

    /**
     * Get base path for display in error messages.
     *
     * @return string Base path or fallback.
     */
    private function get_base_path_for_display() {
        try {
            if (class_exists('OnboardPaths')) {
                $base = OnboardPaths::get(OnboardPaths::DIR_DATABASE);
                return dirname($base) . '/';
            }
        } catch (Throwable $e) {
            OnboardErrorLog::log($e, 'Plugins Onboard: get_base_path_for_display failed:');
        }

        if (defined('WP_CONTENT_DIR')) {
            return WP_CONTENT_DIR . '/uploads/plugins-onboard/';
        }

        return 'wp-content/uploads/plugins-onboard/';
    }

    /**
     * Get log directory for display in error messages.
     *
     * @return string Log directory path.
     */
    private function get_log_dir_for_display() {
        try {
            if (class_exists('OnboardPaths')) {
                return OnboardPaths::get(OnboardPaths::DIR_SECURITY_LOGS);
            }
        } catch (Throwable $e) {
            OnboardErrorLog::log($e, 'Plugins Onboard: get_log_dir_for_display failed:');
        }

        if (defined('WP_CONTENT_DIR')) {
            return WP_CONTENT_DIR . '/uploads/plugins-onboard/logs/';
        }

        return 'wp-content/uploads/plugins-onboard/logs/';
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        OnboardLogger::debug('=== PLUGIN DEACTIVATION STARTED ===');

        try {
            // Clear any scheduled cron tasks (if any exist).
            OnboardLogger::debug('Clearing scheduled hooks...');
            wp_clear_scheduled_hook('onboard_cleanup_cron');
            OnboardLogger::debug('Scheduled hooks cleared');

            // Flush rewrite rules.
            OnboardLogger::debug('Flushing rewrite rules...');
            flush_rewrite_rules();
            OnboardLogger::debug('Rewrite rules flushed');

            OnboardLogger::debug('=== PLUGIN DEACTIVATION COMPLETED ===');

        } catch (Throwable $e) {
            OnboardLogger::error('Plugin deactivation error', $e);
            OnboardErrorLog::logAndThrow($e, 'Plugins Onboard: Deactivation failed:');
        }
    }


    /**
     * Seed default settings to database.
     */
    private function seed_default_settings() {
        if (OnboardBooleanHelpers::isDbDisconnected($this->db)) {
            return;
        }

        // Get defaults from constants.
        $defaults = OnboardConfig::get_defaults();

        foreach ($defaults as $key => $value) {
            try {
                $existing = $this->db->get_setting($key);
                if ($existing === null) {
                    $this->db->save_setting($key, $value);
                }
            } catch (Throwable $e) {
                OnboardErrorLog::log($e, 'Plugins Onboard: Failed to save setting ' . $key . ':');
            }
        }
    }

    /**
     * Initialize plugin components with fail-safe.
     */
    public function init() {
        OnboardLogger::debug('=== PLUGIN INIT() METHOD CALLED ===');

        try {
            // STEP 1: Ensure directories exist (MUST BE FIRST).
            OnboardLogger::debug('STEP 1: Ensuring directories exist...');
            if (!OnboardInitHelpers::ensure_directories_exist()) {
                throw new Exception('Failed to ensure directories exist');
            }
            OnboardLogger::debug('✓ STEP 1 COMPLETE: Directories ready');

            // STEP 2: Ensure database is ready (depends on directories).
            OnboardLogger::debug('STEP 2: Ensuring database is ready...');
            $this->db = OnboardInitHelpers::ensure_database_ready();
            if (!$this->db) {
                throw new Exception('Failed to initialize database');
            }
            OnboardLogger::debug('✓ STEP 2 COMPLETE: Database ready');

            // STEP 3: Set database in config for value resolution.
            if ($this->config) {
                OnboardLogger::debug('STEP 3: Setting database in config...');
                $this->config->set_database($this->db);
                OnboardLogger::debug('✓ STEP 3 COMPLETE: Database set in config');
            }

            // Initialize components.
            OnboardLogger::debug('Initializing OnboardAuditLogger');
            $this->audit_logger = new OnboardAuditLogger($this->db);

            OnboardLogger::debug('Initializing OnboardOAuth');
            $this->oauth = new OnboardOAuth($this->db, $this->audit_logger);

            OnboardLogger::debug('Initializing OnboardMutationToken');
            $this->mutation_token = new OnboardMutationToken($this->db, $this->audit_logger);

            OnboardLogger::debug('Initializing OnboardIPWhitelist');
            $this->ip_whitelist = new OnboardIPWhitelist($this->db, $this->audit_logger);

            OnboardLogger::debug('Initializing OnboardSnapshot');
            $this->snapshot = new OnboardSnapshot($this->db, $this->audit_logger);

            OnboardLogger::debug('Initializing OnboardBackupManager');
            $this->backup_manager = new OnboardBackupManager($this->db, $this->snapshot, $this->audit_logger);

            OnboardLogger::debug('Initializing OnboardPluginManager');
            $this->plugin_manager = new OnboardPluginManager($this->db, $this->snapshot, $this->audit_logger);

            OnboardLogger::debug('Initializing OnboardCleanup');
            $this->cleanup = new OnboardCleanup($this->db, $this->audit_logger);

            OnboardLogger::debug('Initializing OnboardAPI');
            $this->api = new OnboardAPI(
                $this->db,
                $this->oauth,
                $this->mutation_token,
                $this->ip_whitelist,
                $this->snapshot,
                $this->backup_manager,
                $this->plugin_manager,
                $this->audit_logger
            );

            // Initialize Admin UI.
            OnboardLogger::debug('Checking if admin UI should be initialized...');
            if (is_admin()) {
                OnboardLogger::debug('Is admin context - initializing OnboardAdminUI...');
                if (class_exists('OnboardAdminUI') && $this->oauth) {
                    $this->admin_ui = new OnboardAdminUI(
                        $this->db,
                        $this->oauth,
                        $this->ip_whitelist,
                        $this->snapshot,
                        $this->backup_manager,
                        $this->plugin_manager,
                        $this->audit_logger,
                        $this->cleanup
                    );
                    OnboardLogger::debug('  ✓ OnboardAdminUI initialized');
                } else {
                    OnboardLogger::error('  ✗ OnboardAdminUI class not found or dependencies missing');
                }
            } else {
                OnboardLogger::debug('Not admin context - skipping admin UI');
            }

            OnboardLogger::debug('=== PLUGIN INIT() COMPLETED SUCCESSFULLY ===');

        } catch (Throwable $e) {
            $this->init_error = $e->getMessage();
            OnboardLogger::critical('Plugin init() failed', $e);
            OnboardErrorLog::logAndThrow($e, 'Plugins Onboard Init Error:');
        }
    }

    /**
     * Register API routes.
     */
    public function register_api_routes() {
        if ($this->api) {
            $this->api->register_routes();
        }

        // Catch-all route for invalid endpoints within our namespace.
        $namespace = ONBOARD_API_NAMESPACE . '/' . ONBOARD_API_VERSION;
        register_rest_route($namespace, '/(?P<path>.+)', array(
            'methods'             => WP_REST_Server::ALLMETHODS,
            'callback'            => array($this, 'handle_invalid_route'),
            'permission_callback' => '__return_true',
            'priority'            => 999, // lowest priority so real routes match first
        ));

        // Enrich ALL error responses from our namespace with structured metadata.
        add_filter('rest_post_dispatch', array($this, 'enrich_error_response'), 10, 3);
    }

    /**
     * Handle requests to invalid routes within the plugin namespace.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_Error
     */
    public function handle_invalid_route($request) {
        $path = $request->get_param('path');
        $method = $request->get_method();

        OnboardLogger::error("Invalid route requested: {$method} /{$path}");

        return new WP_Error(
            'invalid_route',
            "The endpoint '/{$path}' does not exist. Check the API documentation for available endpoints.",
            array(
                'status'        => 404,
                'pluginVersion' => ONBOARD_PLUGIN_VERSION,
                'timestamp'     => gmdate('c'),
                'requestedPath' => $path,
                'method'        => $method,
                'logHint'       => 'Check the plugin error logs or the Activity Logs page for details.',
            )
        );
    }

    /**
     * Enrich error responses from our namespace with structured metadata.
     *
     * @param WP_REST_Response $response Response object.
     * @param WP_REST_Server   $server   REST server.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_REST_Response
     */
    public function enrich_error_response($response, $server, $request) {
        $namespace = ONBOARD_API_NAMESPACE . '/' . ONBOARD_API_VERSION;
        $route = $request->get_route();

        // Only enrich errors from our own namespace.
        $isExternalRoute = (strpos($route, '/' . $namespace) !== 0);

        if ($isExternalRoute) {
            return $response;
        }

        // Only enrich error responses (4xx/5xx).
        $status = $response->get_status();
        if ($status < 400) {
            return $response;
        }

        $data = $response->get_data();

        // Add metadata if not already present.
        if (is_array($data) && !isset($data['pluginVersion'])) {
            $data['pluginVersion'] = ONBOARD_PLUGIN_VERSION;
            $data['timestamp'] = gmdate('c');
            $data['logHint'] = 'Check the plugin error logs or the Activity Logs page for details.';
            $response->set_data($data);
        }

        return $response;
    }

    /**
     * Run cleanup tasks.
     */
    public function run_cleanup() {
        if ($this->cleanup) {
            try {
                $this->cleanup->run_all();
            } catch (Exception $e) {
                OnboardErrorLog::log($e, 'Plugins Onboard Cleanup Error:');
                error_log('Plugins Onboard: Cleanup failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }
    }
    
    /**
     * Get config value.
     *
     * @param string $key Config key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_config($key, $default = null) {
        if ($this->config) {
            return $this->config->get($key, $default);
        }
        return $default;
    }
}

/**
 * Get plugin instance.
 *
 * @return PluginsOnboard
 */
function plugins_onboard() {
    return PluginsOnboard::get_instance();
}

/**
 * Get config value helper.
 *
 * @param string $key Config key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function onboard_config($key, $default = null) {
    $plugin = plugins_onboard();
    if ($plugin && $plugin->config) {
        return $plugin->config->get($key, $default);
    }
    return $default;
}

// Initialize plugin.
plugins_onboard();
