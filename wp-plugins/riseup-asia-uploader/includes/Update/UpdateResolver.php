<?php
/**
 * Riseup Asia Uploader - Update Resolver
 *
 * @package RiseupAsia\Update
 * @since   1.8.0
 */

namespace RiseupAsia\Update;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Update\Traits\UpdateResolverUrlTrait;
use RiseupAsia\Update\Traits\UpdateResolverFetchTrait;
use RiseupAsia\Update\Traits\UpdateResolverWpHooksTrait;
use RiseupAsia\Enums\HookType;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Database\Database;

class UpdateResolver {

    use UpdateResolverUrlTrait;
    use UpdateResolverFetchTrait;
    use UpdateResolverWpHooksTrait;

    const OPTION_NAME = 'riseup_update_settings';
    const DEFAULT_CACHE_DAYS = 7;

    private FileLogger $fileLogger;
    private Database $db;
    private static ?UpdateResolver $instance = null;

    public static function getInstance(): static {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $this->db = Database::getInstance();

        $settings = $this->getSettings();
        if (!empty($settings['enabled'])) {
            add_filter(HookType::PreSetSiteTransientUpdatePlugins->value, array($this, 'checkForPluginUpdate'));
            add_filter(HookType::PluginsApi->value, array($this, 'pluginInfo'), 10, 3);
            $this->fileLogger->info('Auto-update hooks registered');
        }
    }

    public function getSettings(): array {
        $defaults = array(
            'enabled' => false, 'master_url' => '', 'resolved_url' => '', 'resolved_at' => '',
            'cache_days' => self::DEFAULT_CACHE_DAYS, 'last_check' => '', 'last_error' => '',
            'package_url' => '', 'new_version' => '', 'update_info' => array(),
        );
        $settings = get_option(self::OPTION_NAME, array());
        return wp_parse_args($settings, $defaults);
    }

    public function saveSettings(array $settings): bool {
        $current = $this->getSettings();
        $merged = wp_parse_args($settings, $current);
        return update_option(self::OPTION_NAME, $merged);
    }
}

class_alias(UpdateResolver::class, 'RiseupUpdateResolver');
