<?php
/**
 * Riseup Asia Uploader - Update Resolver
 *
 * Shell class delegating to UpdateResolverUrlTrait, UpdateResolverFetchTrait,
 * and UpdateResolverWpHooksTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HookType;

require_once __DIR__ . '/Traits/UpdateResolverUrlTrait.php';
require_once __DIR__ . '/Traits/UpdateResolverHooksTrait.php';
require_once __DIR__ . '/Traits/UpdateResolverWpHooksTrait.php';

/**
 * Class RiseupUpdateResolver
 *
 * Manages plugin auto-updates with 301 redirect resolution.
 */
class RiseupUpdateResolver {

    use UpdateResolverUrlTrait;
    use UpdateResolverFetchTrait;
    use UpdateResolverWpHooksTrait;

    const OPTION_NAME = 'riseup_update_settings';
    const DEFAULT_CACHE_DAYS = 7;

    /** @var RiseupFileLogger */
    private $fileLogger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupUpdateResolver|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseupUpdateResolver
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->fileLogger = RiseupFileLogger::getInstance();
        $this->db = RiseupDatabase::getInstance();

        $settings = $this->getSettings();
        if (!empty($settings['enabled'])) {
            add_filter(HookType::PreSetSiteTransientUpdatePlugins->value, array($this, 'check_for_plugin_update'));
            add_filter(HookType::PluginsApi->value, array($this, 'plugin_info'), 10, 3);
            $this->fileLogger->info('Auto-update hooks registered');
        }
    }

    /**
     * Get update settings.
     *
     * @return array Settings array with defaults.
     */
    public function getSettings() {
        $defaults = array(
            'enabled' => false, 'master_url' => '', 'resolved_url' => '', 'resolved_at' => '',
            'cache_days' => self::DEFAULT_CACHE_DAYS, 'last_check' => '', 'last_error' => '',
            'package_url' => '', 'new_version' => '', 'update_info' => array(),
        );
        $settings = get_option(self::OPTION_NAME, array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Save update settings.
     *
     * @param array $settings Settings to save.
     * @return bool True on success.
     */
    public function saveSettings($settings) {
        $current = $this->getSettings();
        $merged = wp_parse_args($settings, $current);
        return update_option(self::OPTION_NAME, $merged);
    }
}
