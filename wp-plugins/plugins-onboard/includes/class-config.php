<?php
/**
 * Configuration class.
 *
 * Handles configuration value resolution with priority:
 * 1. ENV variables (highest priority)
 * 2. Database values (if database exists)
 * 3. Constants (defaults)
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardConfig
 */
class OnboardConfig {

    /**
     * Singleton instance.
     *
     * @var OnboardConfig|null
     */
    private static $instance = null;

    /**
     * Database instance.
     *
     * @var OnboardDatabase|null
     */
    private $db = null;

    /**
     * Cached config values.
     *
     * @var array
     */
    private $cache = array();

    /**
     * Config key to constant/env mapping.
     *
     * @var array
     */
    private static $config_map = array(
        // API
        'api_namespace' => 'ONBOARD_API_NAMESPACE',
        'api_version' => 'ONBOARD_API_VERSION',
        
        // Token TTLs
        'access_token_ttl' => 'ONBOARD_ACCESS_TOKEN_TTL',
        'refresh_token_ttl' => 'ONBOARD_REFRESH_TOKEN_TTL',
        'mutation_token_ttl' => 'ONBOARD_MUTATION_TOKEN_TTL',
        'auth_code_ttl' => 'ONBOARD_AUTH_CODE_TTL',
        
        // Rate Limiting
        'rate_limit_auth_requests' => 'ONBOARD_RATE_LIMIT_AUTH_REQUESTS',
        'rate_limit_mutation_requests' => 'ONBOARD_RATE_LIMIT_MUTATION_REQUESTS',
        
        // Backup
        'snapshot_retention_count' => 'ONBOARD_SNAPSHOT_RETENTION_COUNT',
        'snapshot_cleanup_interval' => 'ONBOARD_SNAPSHOT_CLEANUP_INTERVAL',
        'auto_backup_enabled' => 'ONBOARD_AUTO_BACKUP_ENABLED',
        
        // Paths
        'data_path' => 'ONBOARD_DATA_PATH',
        'snapshot_base_path' => 'ONBOARD_SNAPSHOT_BASE_PATH',
        'temp_path' => 'ONBOARD_TEMP_PATH',
        'log_path' => 'ONBOARD_LOG_PATH',
        'plugin_manager_db' => 'ONBOARD_PLUGIN_MANAGER_DB',
        'audit_db' => 'ONBOARD_AUDIT_DB',
        
        // Temp/Upload
        'temp_cleanup_days' => 'ONBOARD_TEMP_CLEANUP_DAYS',
        'temp_size_warning' => 'ONBOARD_TEMP_SIZE_WARNING',
        'max_upload_size' => 'ONBOARD_MAX_UPLOAD_SIZE',
        
        // Security
        'require_https' => 'ONBOARD_REQUIRE_HTTPS',
        'ip_whitelist_enabled' => 'ONBOARD_IP_WHITELIST_ENABLED',
        'ip_auto_approve' => 'ONBOARD_IP_AUTO_APPROVE',
        'token_encryption' => 'ONBOARD_TOKEN_ENCRYPTION',
        
        // Logging
        'log_level' => 'ONBOARD_LOG_LEVEL',
        'audit_log_retention_days' => 'ONBOARD_AUDIT_LOG_RETENTION_DAYS',
        
        // Features
        'enable_oauth' => 'ONBOARD_ENABLE_OAUTH',
        'enable_api' => 'ONBOARD_ENABLE_API',
        'enable_backup' => 'ONBOARD_ENABLE_BACKUP',
        'enable_audit_logs' => 'ONBOARD_ENABLE_AUDIT_LOGS',
        
        // Backup Triggers
        'backup_trigger_upload' => 'ONBOARD_BACKUP_TRIGGER_UPLOAD',
        'backup_trigger_enable' => 'ONBOARD_BACKUP_TRIGGER_ENABLE',
        'backup_trigger_disable' => 'ONBOARD_BACKUP_TRIGGER_DISABLE',
        'backup_trigger_delete' => 'ONBOARD_BACKUP_TRIGGER_DELETE',
        
        // Other
        'admin_email' => 'ONBOARD_ADMIN_EMAIL',
    );

    /**
     * Get singleton instance.
     *
     * @return OnboardConfig
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
        // Cache will be populated on first access.
    }

    /**
     * Set database instance.
     *
     * @param OnboardDatabase $db Database instance.
     */
    public function set_database($db) {
        $this->db = $db;
        // Clear cache when database is set.
        $this->cache = array();
    }

    /**
     * Reload configuration (clear cache).
     */
    public function reload() {
        $this->cache = array();
    }

    /**
     * Get config value.
     *
     * Priority: ENV > Database > Constant/Function
     * 
     * The flow is:
     * 1. Constants/Functions provide defaults (always available)
     * 2. If database exists, database values override constants
     * 3. ENV variables have highest priority and override everything
     *
     * @param string $key     Config key.
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public function get($key, $default = null) {
        // Check cache first.
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $value = null;
        $constant_name = isset(self::$config_map[$key]) ? self::$config_map[$key] : strtoupper('ONBOARD_' . $key);

        // 1. Start with constant or function (base default) - always available.
        // For path keys, use functions instead of constants.
        $value = $this->get_default_value($key, $constant_name);

        // 2. Check database - overrides constant if database is available and has value.
        if ($this->db && $this->db->is_connected()) {
            try {
                $db_value = $this->db->get_setting($key);
                if ($db_value !== null) {
                    $value = $db_value;
                }
            } catch (Exception $e) {
                // Database read failed, keep constant value.
                OnboardErrorLog::errorLog($e, 'Onboard Config DB read error for ' . $key . ':');
            }
        }
        
        // 3. Check ENV variable (highest priority) - overrides everything.
        $env_value    = getenv($constant_name);
        $isEnvDefined = ($env_value !== false);
        $isEnvPopulated = $isEnvDefined && ($env_value !== '');

        if ($isEnvPopulated) {
            $value = $this->cast_value($env_value, $key);
        }
        
        // 4. Use provided default if nothing found.
        if ($value === null) {
            $value = $default;
        }

        // Cache the value.
        $this->cache[$key] = $value;

        return $value;
    }
    
    /**
     * Get default value from constant or Paths class.
     *
     * @param string $key           Config key.
     * @param string $constant_name Constant name.
     * @return mixed
     */
    private function get_default_value($key, $constant_name) {
        // Path keys use OnboardPaths class with meaningful constants.
        $path_type_map = array(
            'data_path'          => OnboardPaths::DIR_PLUGIN_DATA,
            'snapshot_base_path' => OnboardPaths::DIR_PLUGIN_SNAPSHOTS,
            'temp_path'          => OnboardPaths::DIR_TEMP_UPLOADS,
            'log_path'           => OnboardPaths::DIR_SECURITY_LOGS,
            'plugin_manager_db'  => OnboardPaths::FILE_MAIN_DATABASE,
            'audit_db'           => OnboardPaths::FILE_AUDIT_DATABASE,
        );
        
        if (isset($path_type_map[$key]) && class_exists('OnboardPaths')) {
            return OnboardPaths::get($path_type_map[$key]);
        }
        
        // Check constant.
        if (defined($constant_name)) {
            return constant($constant_name);
        }
        
        return null;
    }

    /**
     * Cast ENV string value to appropriate type.
     *
     * @param string $value Value to cast.
     * @param string $key   Config key for type hint.
     * @return mixed
     */
    private function cast_value($value, $key) {
        // Boolean keys.
        $boolean_keys = array(
            'auto_backup_enabled', 'require_https', 'ip_whitelist_enabled',
            'ip_auto_approve', 'token_encryption', 'enable_oauth', 'enable_api',
            'enable_backup', 'enable_audit_logs', 'backup_trigger_upload',
            'backup_trigger_enable', 'backup_trigger_disable', 'backup_trigger_delete',
        );
        
        if (in_array($key, $boolean_keys, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        
        // Integer keys.
        $integer_keys = array(
            'access_token_ttl', 'refresh_token_ttl', 'mutation_token_ttl',
            'auth_code_ttl', 'rate_limit_auth_requests', 'rate_limit_mutation_requests',
            'snapshot_retention_count', 'temp_cleanup_days', 'temp_size_warning',
            'max_upload_size', 'audit_log_retention_days',
        );
        
        if (in_array($key, $integer_keys, true)) {
            return intval($value);
        }
        
        return $value;
    }

    /**
     * Set config value in database.
     *
     * @param string $key   Config key.
     * @param mixed  $value Value to set.
     * @return bool
     */
    public function set($key, $value) {
        $isDbDisconnected = OnboardBooleanHelpers::is_db_disconnected($this->db);

        if ($isDbDisconnected) {
            return false;
        }

        try {
            $this->db->save_setting($key, $value);
            // Update cache.
            $this->cache[$key] = $value;
            return true;
        } catch (Exception $e) {
            OnboardErrorLog::errorLog($e, 'Onboard Config set error for ' . $key . ':');
            return false;
        }
    }

    /**
     * Get all config values.
     *
     * @return array
     */
    public function get_all() {
        $all = array();
        foreach (array_keys(self::$config_map) as $key) {
            $all[$key] = $this->get($key);
        }
        return $all;
    }

    /**
     * Get default values from constants.
     *
     * @return array
     */
    public static function get_defaults() {
        $defaults = array();
        
        foreach (self::$config_map as $key => $constant) {
            if (defined($constant)) {
                $defaults[$key] = constant($constant);
            }
        }
        
        // Add admin email from WP if not defined.
        if (!isset($defaults['admin_email'])) {
            $defaults['admin_email'] = get_option('admin_email', '');
        }
        
        return $defaults;
    }

    /**
     * Check if a config key exists.
     *
     * @param string $key Config key.
     * @return bool
     */
    public function has($key) {
        return isset(self::$config_map[$key]);
    }

    /**
     * Get the constant name for a config key.
     *
     * @param string $key Config key.
     * @return string|null
     */
    public function get_constant_name($key) {
        return isset(self::$config_map[$key]) ? self::$config_map[$key] : null;
    }
}
