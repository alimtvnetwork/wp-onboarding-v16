<?php
/**
 * Debug and Maintenance Mode class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardDebugMaintenance
 *
 * Handles debug mode and maintenance mode toggling.
 */
class OnboardDebugMaintenance {

    /**
     * Enable debug mode.
     *
     * @param string|null $app_id     Application ID.
     * @param string|null $ip_address IP address.
     * @return array|WP_Error
     */
    public static function enable_debug($app_id = null, $ip_address = null) {
        $result = self::set_wp_debug(true);
        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'success' => true,
            'debug_mode' => true,
            'message' => 'Debug mode enabled',
            'log_file' => WP_CONTENT_DIR . '/debug.log',
        );
    }

    /**
     * Disable debug mode.
     *
     * @param string|null $app_id     Application ID.
     * @param string|null $ip_address IP address.
     * @return array|WP_Error
     */
    public static function disable_debug($app_id = null, $ip_address = null) {
        $result = self::set_wp_debug(false);
        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'success' => true,
            'debug_mode' => false,
            'message' => 'Debug mode disabled',
        );
    }

    /**
     * Get debug status.
     *
     * @return array
     */
    public static function get_debug_status() {
        return array(
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'log_file' => WP_CONTENT_DIR . '/debug.log',
            'log_exists' => file_exists(WP_CONTENT_DIR . '/debug.log'),
            'log_size' => file_exists(WP_CONTENT_DIR . '/debug.log') ? filesize(WP_CONTENT_DIR . '/debug.log') : 0,
        );
    }

    /**
     * Set WP_DEBUG in wp-config.php.
     *
     * @param bool $enabled Whether to enable debug.
     * @return true|WP_Error
     */
    private static function set_wp_debug($enabled) {
        $config_file = ABSPATH . 'wp-config.php';

        if (!file_exists($config_file)) {
            return new WP_Error('config_not_found', 'wp-config.php not found', array('status' => 500));
        }

        if (!is_writable($config_file)) {
            return new WP_Error('config_not_writable', 'wp-config.php is not writable', array('status' => 500));
        }

        $content = file_get_contents($config_file);
        $value = $enabled ? 'true' : 'false';

        // Update WP_DEBUG.
        if (preg_match("/define\s*\(\s*['"]WP_DEBUG['"]\s*,\s*(true|false)\s*\)/i", $content)) {
            $content = preg_replace(
                "/define\s*\(\s*['"]WP_DEBUG['"]\s*,\s*(true|false)\s*\)/i",
                "define('WP_DEBUG', {$value})",
                $content
            );
        } else {
            // Add before "That's all, stop editing!" comment.
            $content = preg_replace(
                "/([\/\*\s]+That's all.*?\*\/)/i",
                "define('WP_DEBUG', {$value});\n\$1",
                $content
            );
        }

        // Update WP_DEBUG_LOG.
        if (preg_match("/define\s*\(\s*['"]WP_DEBUG_LOG['"]\s*,\s*(true|false)\s*\)/i", $content)) {
            $content = preg_replace(
                "/define\s*\(\s*['"]WP_DEBUG_LOG['"]\s*,\s*(true|false)\s*\)/i",
                "define('WP_DEBUG_LOG', {$value})",
                $content
            );
        } elseif ($enabled) {
            $content = preg_replace(
                "/(define\s*\(\s*['"]WP_DEBUG['"].*?\);)/i",
                "\$1\ndefine('WP_DEBUG_LOG', true);",
                $content
            );
        }

        $result = file_put_contents($config_file, $content);
        if ($result === false) {
            return new WP_Error('config_write_failed', 'Failed to write to wp-config.php', array('status' => 500));
        }

        return true;
    }

    /**
     * Enable maintenance mode.
     *
     * @param string|null $app_id     Application ID.
     * @param string|null $ip_address IP address.
     * @return array|WP_Error
     */
    public static function enable_maintenance($app_id = null, $ip_address = null) {
        $maintenance_file = ABSPATH . '.maintenance';

        $content = '<?php $upgrading = ' . time() . ';';
        $result = file_put_contents($maintenance_file, $content);

        if ($result === false) {
            return new WP_Error('maintenance_enable_failed', 'Failed to create maintenance file', array('status' => 500));
        }

        return array(
            'success' => true,
            'maintenance_mode' => true,
            'message' => 'Maintenance mode enabled',
            'file' => $maintenance_file,
        );
    }

    /**
     * Disable maintenance mode.
     *
     * @param string|null $app_id     Application ID.
     * @param string|null $ip_address IP address.
     * @return array|WP_Error
     */
    public static function disable_maintenance($app_id = null, $ip_address = null) {
        $maintenance_file = ABSPATH . '.maintenance';

        if (file_exists($maintenance_file)) {
            $result = unlink($maintenance_file);
            if (!$result) {
                return new WP_Error('maintenance_disable_failed', 'Failed to remove maintenance file', array('status' => 500));
            }
        }

        return array(
            'success' => true,
            'maintenance_mode' => false,
            'message' => 'Maintenance mode disabled',
        );
    }

    /**
     * Get maintenance status.
     *
     * @return array
     */
    public static function get_maintenance_status() {
        $maintenance_file = ABSPATH . '.maintenance';
        $is_enabled = file_exists($maintenance_file);

        $started_at = null;
        if ($is_enabled) {
            include $maintenance_file;
            if (isset($upgrading)) {
                $started_at = date('Y-m-d H:i:s', $upgrading);
            }
        }

        return array(
            'maintenance_mode' => $is_enabled,
            'file' => $maintenance_file,
            'started_at' => $started_at,
        );
    }

    /**
     * Get recent debug log entries.
     *
     * @param int $lines Number of lines to retrieve.
     * @return array
     */
    public static function get_debug_log($lines = 100) {
        $log_file = WP_CONTENT_DIR . '/debug.log';

        if (!file_exists($log_file)) {
            return array(
                'exists' => false,
                'entries' => array(),
            );
        }

        $content = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = array_slice($content, -$lines);

        return array(
            'exists' => true,
            'size' => filesize($log_file),
            'entries' => $entries,
            'total_lines' => count($content),
        );
    }

    /**
     * Clear debug log.
     *
     * @return array|WP_Error
     */
    public static function clear_debug_log() {
        $log_file = WP_CONTENT_DIR . '/debug.log';

        if (!file_exists($log_file)) {
            return array(
                'success' => true,
                'message' => 'Debug log does not exist',
            );
        }

        $result = file_put_contents($log_file, '');
        if ($result === false) {
            return new WP_Error('clear_log_failed', 'Failed to clear debug log', array('status' => 500));
        }

        return array(
            'success' => true,
            'message' => 'Debug log cleared',
        );
    }
}
