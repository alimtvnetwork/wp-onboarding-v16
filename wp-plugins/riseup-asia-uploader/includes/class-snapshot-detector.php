<?php
/**
 * Riseup Asia Uploader - Snapshot Provider Detector
 *
 * Detects available snapshot providers (WP Reset, Updraft, Native)
 * and manages provider selection based on user preferences.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot Provider Detector class.
 * 
 * Responsible for detecting installed backup plugins and
 * instantiating the appropriate snapshot provider.
 */
class Riseup_Snapshot_Detector {

    /**
     * Logger instance.
     *
     * @var Riseup_File_Logger
     */
    private $logger;

    /**
     * Database instance.
     *
     * @var Riseup_Database
     */
    private $db;

    /**
     * Cached provider instances.
     *
     * @var array
     */
    private $provider_instances = array();

    /**
     * Constructor.
     *
     * @param Riseup_File_Logger $logger Logger instance.
     * @param Riseup_Database    $db     Database instance.
     */
    public function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Detect all available snapshot providers.
     *
     * @return array {
     *     @type string $id           Provider ID.
     *     @type string $name         Display name.
     *     @type bool   $available    Whether provider is available.
     *     @type array  $capabilities Provider capabilities.
     *     @type string $version      Plugin version (if applicable).
     * }[]
     */
    public function detect_available_providers() {
        $providers = array();

        // Check WP Reset
        $providers[] = $this->detect_wp_reset();

        // Check Updraft Plus
        $providers[] = $this->detect_updraft();

        // Native is always available
        $providers[] = $this->detect_native();

        $this->log_detection_results($providers);

        return $providers;
    }

    /**
     * Detect WP Reset plugin.
     *
     * @return array Provider detection result.
     */
    private function detect_wp_reset() {
        $result = array(
            'id' => RISEUP_SNAPSHOT_PROVIDER_WP_RESET,
            'name' => 'WP Reset',
            'available' => false,
            'capabilities' => array(),
            'version' => null,
            'detection_method' => null,
        );

        // Method 1: Check class exists
        if (class_exists('WP_Reset')) {
            $result['available'] = true;
            $result['detection_method'] = 'class_exists';
        }

        // Method 2: Check if plugin file exists and is active
        if (!$result['available']) {
            $plugin_file = 'wp-reset/wp-reset.php';
            if (is_plugin_active($plugin_file) || is_plugin_active_for_network($plugin_file)) {
                $result['available'] = true;
                $result['detection_method'] = 'plugin_active';
            }
        }

        // Method 3: Check for WP Reset Pro
        if (!$result['available'] && class_exists('WP_Reset_Pro')) {
            $result['available'] = true;
            $result['name'] = 'WP Reset Pro';
            $result['detection_method'] = 'class_exists_pro';
        }

        // Get version if available
        if ($result['available']) {
            if (defined('WP_RESET_VERSION')) {
                $result['version'] = WP_RESET_VERSION;
            }

            $result['capabilities'] = array(
                'full_site' => true,
                'database_only' => true,
                'selective' => true,
                'scheduled' => false, // WP Reset free doesn't have scheduling
                'restore' => true,
                'export' => true,
                'import' => true,
            );
        }

        return $result;
    }

    /**
     * Detect Updraft Plus plugin.
     *
     * @return array Provider detection result.
     */
    private function detect_updraft() {
        $result = array(
            'id' => RISEUP_SNAPSHOT_PROVIDER_UPDRAFT,
            'name' => 'UpdraftPlus',
            'available' => false,
            'capabilities' => array(),
            'version' => null,
            'detection_method' => null,
        );

        // Method 1: Check class exists
        if (class_exists('UpdraftPlus')) {
            $result['available'] = true;
            $result['detection_method'] = 'class_exists';
        }

        // Method 2: Check global instance
        if (!$result['available'] && isset($GLOBALS['updraftplus'])) {
            $result['available'] = true;
            $result['detection_method'] = 'global_instance';
        }

        // Method 3: Check if plugin file exists and is active
        if (!$result['available']) {
            $plugin_files = array(
                'updraftplus/updraftplus.php',
                'updraftplus-premium/updraftplus.php',
            );
            foreach ($plugin_files as $plugin_file) {
                if (is_plugin_active($plugin_file) || is_plugin_active_for_network($plugin_file)) {
                    $result['available'] = true;
                    $result['detection_method'] = 'plugin_active';
                    if (strpos($plugin_file, 'premium') !== false) {
                        $result['name'] = 'UpdraftPlus Premium';
                    }
                    break;
                }
            }
        }

        // Get version if available
        if ($result['available']) {
            if (defined('UPDRAFTPLUS_VERSION')) {
                $result['version'] = UPDRAFTPLUS_VERSION;
            }

            $is_premium = strpos($result['name'], 'Premium') !== false;

            $result['capabilities'] = array(
                'full_site' => true,
                'database_only' => true,
                'selective' => $is_premium, // Only premium has selective
                'scheduled' => true,
                'restore' => true,
                'export' => true,
                'import' => true,
            );
        }

        return $result;
    }

    /**
     * Detect native SQLite provider (always available).
     *
     * @return array Provider detection result.
     */
    private function detect_native() {
        // Check SQLite extension
        $has_sqlite = extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');

        return array(
            'id' => RISEUP_SNAPSHOT_PROVIDER_NATIVE,
            'name' => 'Native SQLite',
            'available' => $has_sqlite,
            'capabilities' => array(
                'full_site' => false, // Native only does database
                'database_only' => true,
                'selective' => true,
                'scheduled' => true,
                'restore' => true,
                'export' => true,
                'import' => true,
            ),
            'version' => RISEUP_VERSION,
            'detection_method' => $has_sqlite ? 'extension_loaded' : 'extension_missing',
            'sqlite_version' => $has_sqlite ? $this->get_sqlite_version() : null,
        );
    }

    /**
     * Get SQLite version.
     *
     * @return string|null SQLite library version.
     */
    private function get_sqlite_version() {
        if (class_exists('SQLite3')) {
            $version = SQLite3::version();
            return $version['versionString'];
        }
        if (extension_loaded('pdo_sqlite')) {
            try {
                $pdo = new PDO('sqlite::memory:');
                return $pdo->query('SELECT sqlite_version()')->fetchColumn();
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get the preferred provider based on settings.
     *
     * @return string Provider ID.
     */
    public function get_preferred_provider() {
        $settings = get_option(RISEUP_OPTION_SNAPSHOT_SETTINGS, array());
        $preferred = isset($settings['preferred_provider']) ? $settings['preferred_provider'] : RISEUP_SNAPSHOT_PROVIDER_AUTO;

        // If auto, determine best available
        if ($preferred === RISEUP_SNAPSHOT_PROVIDER_AUTO) {
            return $this->get_best_available_provider();
        }

        // Check if preferred is available
        $providers = $this->detect_available_providers();
        foreach ($providers as $provider) {
            if ($provider['id'] === $preferred && $provider['available']) {
                return $preferred;
            }
        }

        // Fallback to best available
        $this->logger->warn('[SNAPSHOT] Preferred provider not available, falling back', array(
            'preferred' => $preferred
        ));

        return $this->get_best_available_provider();
    }

    /**
     * Get the best available provider (priority: WP Reset > Updraft > Native).
     *
     * @return string Provider ID.
     */
    public function get_best_available_provider() {
        $providers = $this->detect_available_providers();
        
        $priority = array(
            RISEUP_SNAPSHOT_PROVIDER_WP_RESET,
            RISEUP_SNAPSHOT_PROVIDER_UPDRAFT,
            RISEUP_SNAPSHOT_PROVIDER_NATIVE,
        );

        foreach ($priority as $provider_id) {
            foreach ($providers as $provider) {
                if ($provider['id'] === $provider_id && $provider['available']) {
                    return $provider_id;
                }
            }
        }

        // This should never happen, but return native as absolute fallback
        return RISEUP_SNAPSHOT_PROVIDER_NATIVE;
    }

    /**
     * Get a provider instance.
     *
     * @param string|null $provider_id Provider ID, or null for preferred.
     * @return Riseup_Snapshot_Provider_Interface Provider instance.
     * @throws Exception If provider not available.
     */
    public function get_provider_instance($provider_id = null) {
        if ($provider_id === null) {
            $provider_id = $this->get_preferred_provider();
        }

        // Return cached instance if available
        if (isset($this->provider_instances[$provider_id])) {
            return $this->provider_instances[$provider_id];
        }

        // Check availability
        $providers = $this->detect_available_providers();
        $available = false;
        foreach ($providers as $provider) {
            if ($provider['id'] === $provider_id && $provider['available']) {
                $available = true;
                break;
            }
        }

        if (!$available) {
            throw new Exception(sprintf(
                'Snapshot provider "%s" is not available',
                $provider_id
            ));
        }

        // Instantiate provider
        $instance = null;
        switch ($provider_id) {
            case RISEUP_SNAPSHOT_PROVIDER_WP_RESET:
                require_once dirname(__FILE__) . '/class-snapshot-provider-wp-reset.php';
                $instance = new Riseup_Snapshot_Provider_WP_Reset($this->logger, $this->db);
                break;

            case RISEUP_SNAPSHOT_PROVIDER_UPDRAFT:
                require_once dirname(__FILE__) . '/class-snapshot-provider-updraft.php';
                $instance = new Riseup_Snapshot_Provider_Updraft($this->logger, $this->db);
                break;

            case RISEUP_SNAPSHOT_PROVIDER_NATIVE:
            default:
                require_once dirname(__FILE__) . '/class-snapshot-provider-native.php';
                $instance = new Riseup_Snapshot_Provider_Native($this->logger, $this->db);
                break;
        }

        // Cache instance
        $this->provider_instances[$provider_id] = $instance;

        return $instance;
    }

    /**
     * Get snapshot settings with defaults.
     *
     * @return array Snapshot settings.
     */
    public function get_settings() {
        $defaults = array(
            // Provider
            'preferred_provider' => RISEUP_SNAPSHOT_PROVIDER_AUTO,

            // Scheduling
            'schedule_enabled' => false,
            'schedule_frequency' => RISEUP_SNAPSHOT_FREQ_DAILY,
            'schedule_time' => '03:00',
            'schedule_day' => 1,

            // Scope
            'default_scope' => RISEUP_SNAPSHOT_SCOPE_WORDPRESS,
            'custom_tables' => array(),

            // Retention
            'retention_type' => 'days',
            'retention_days' => RISEUP_SNAPSHOT_RETENTION_DAYS_DEFAULT,
            'retention_count' => RISEUP_SNAPSHOT_RETENTION_COUNT_DEFAULT,

            // Safety
            'pre_restore_backup' => true,
            'require_restore_confirm' => true,

            // Limits
            'max_snapshot_size_mb' => RISEUP_SNAPSHOT_MAX_SIZE_MB,
            'batch_size' => RISEUP_SNAPSHOT_BATCH_SIZE,
        );

        $saved = get_option(RISEUP_OPTION_SNAPSHOT_SETTINGS, array());
        return array_merge($defaults, $saved);
    }

    /**
     * Update snapshot settings.
     *
     * @param array $settings Settings to update.
     * @return bool True if settings were updated.
     */
    public function update_settings($settings) {
        $current = $this->get_settings();
        $updated = array_merge($current, $settings);

        // Validate settings
        $updated = $this->validate_settings($updated);

        $result = update_option(RISEUP_OPTION_SNAPSHOT_SETTINGS, $updated);

        if ($result) {
            $this->logger->info('[SNAPSHOT] Settings updated', array(
                'changed_keys' => array_keys(array_diff_assoc($settings, $current))
            ));
        }

        return $result;
    }

    /**
     * Validate and sanitize settings.
     *
     * @param array $settings Settings to validate.
     * @return array Validated settings.
     */
    private function validate_settings($settings) {
        // Validate provider
        $valid_providers = array(
            RISEUP_SNAPSHOT_PROVIDER_AUTO,
            RISEUP_SNAPSHOT_PROVIDER_WP_RESET,
            RISEUP_SNAPSHOT_PROVIDER_UPDRAFT,
            RISEUP_SNAPSHOT_PROVIDER_NATIVE,
        );
        if (!in_array($settings['preferred_provider'], $valid_providers)) {
            $settings['preferred_provider'] = RISEUP_SNAPSHOT_PROVIDER_AUTO;
        }

        // Validate frequency
        $valid_frequencies = array(
            RISEUP_SNAPSHOT_FREQ_MANUAL,
            RISEUP_SNAPSHOT_FREQ_DAILY,
            RISEUP_SNAPSHOT_FREQ_WEEKLY,
            RISEUP_SNAPSHOT_FREQ_MONTHLY,
        );
        if (!in_array($settings['schedule_frequency'], $valid_frequencies)) {
            $settings['schedule_frequency'] = RISEUP_SNAPSHOT_FREQ_DAILY;
        }

        // Validate scope
        $valid_scopes = array(
            RISEUP_SNAPSHOT_SCOPE_ALL,
            RISEUP_SNAPSHOT_SCOPE_WORDPRESS,
            RISEUP_SNAPSHOT_SCOPE_CONTENT,
            RISEUP_SNAPSHOT_SCOPE_CUSTOM,
        );
        if (!in_array($settings['default_scope'], $valid_scopes)) {
            $settings['default_scope'] = RISEUP_SNAPSHOT_SCOPE_WORDPRESS;
        }

        // Validate retention type
        $valid_retention = array('days', 'count', 'none');
        if (!in_array($settings['retention_type'], $valid_retention)) {
            $settings['retention_type'] = 'days';
        }

        // Sanitize numbers
        $settings['retention_days'] = max(1, min(365, intval($settings['retention_days'])));
        $settings['retention_count'] = max(1, min(100, intval($settings['retention_count'])));
        $settings['schedule_day'] = max(1, min(28, intval($settings['schedule_day'])));
        $settings['max_snapshot_size_mb'] = max(50, min(2000, intval($settings['max_snapshot_size_mb'])));
        $settings['batch_size'] = max(100, min(10000, intval($settings['batch_size'])));

        // Sanitize booleans
        $settings['schedule_enabled'] = (bool) $settings['schedule_enabled'];
        $settings['pre_restore_backup'] = (bool) $settings['pre_restore_backup'];
        $settings['require_restore_confirm'] = (bool) $settings['require_restore_confirm'];

        // Sanitize time format (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings['schedule_time'])) {
            $settings['schedule_time'] = '03:00';
        }

        // Ensure custom_tables is array
        if (!is_array($settings['custom_tables'])) {
            $settings['custom_tables'] = array();
        }

        return $settings;
    }

    /**
     * Log detection results.
     *
     * @param array $providers Detection results.
     */
    private function log_detection_results($providers) {
        $available = array_filter($providers, function($p) {
            return $p['available'];
        });

        $this->logger->debug('[SNAPSHOT] Provider detection complete', array(
            'total_checked' => count($providers),
            'available' => count($available),
            'providers' => array_map(function($p) {
                return array(
                    'id' => $p['id'],
                    'available' => $p['available'],
                    'version' => $p['version'],
                );
            }, $providers),
        ));
    }
}
