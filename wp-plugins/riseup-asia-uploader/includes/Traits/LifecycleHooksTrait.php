<?php
/**
 * LifecycleHooksTrait — WordPress plugin lifecycle event handlers.
 *
 * Handles activated_plugin, deactivated_plugin, and deleted_plugin hooks
 * with source detection and audit logging.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait LifecycleHooksTrait
{
    /**
     * Handle WordPress core activated_plugin hook.
     *
     * @param string $plugin       Plugin file path relative to plugins directory.
     * @param bool   $network_wide Whether activated for the entire network.
     */
    public function on_plugin_activated($plugin, $network_wide = false) {
        $this->logLifecycleEvent(ACTION_ENABLE, $plugin, 'activated_plugin', array(
            'network_wide' => $network_wide,
        ));
    }

    /**
     * Handle WordPress core deactivated_plugin hook.
     *
     * @param string $plugin               Plugin file path relative to plugins directory.
     * @param bool   $network_deactivating Whether deactivating across the network.
     */
    public function on_plugin_deactivated($plugin, $network_deactivating = false) {
        $this->logLifecycleEvent(ACTION_DISABLE, $plugin, 'deactivated_plugin', array(
            'network_deactivating' => $network_deactivating,
        ));
    }

    /**
     * Handle WordPress core deleted_plugin hook.
     *
     * @param string $plugin  Plugin file path relative to plugins directory.
     * @param bool   $deleted Whether the plugin was successfully deleted.
     */
    public function on_plugin_deleted($plugin, $deleted = true) {
        if (!$deleted) {
            return;
        }

        $this->logLifecycleEvent(ACTION_DELETE, $plugin, 'deleted_plugin', array());
    }

    /**
     * Log a plugin lifecycle event with trigger source detection.
     *
     * @param string $action      Action constant (enable/disable/delete).
     * @param string $plugin      Plugin file path.
     * @param string $hook_source WordPress hook name.
     * @param array  $extra       Additional context fields.
     */
    private function logLifecycleEvent(string $action, string $plugin, string $hook_source, array $extra) {
        if ($this->is_rest_request()) {
            return;
        }

        try {
            $slug = $this->extract_plugin_slug($plugin);
            $triggered_by = $this->detect_trigger_source();

            $this->file_logger->info('WordPress hook: Plugin lifecycle event', array(
                'action'       => $action,
                'plugin'       => $plugin,
                'slug'         => $slug,
                'triggered_by' => $triggered_by,
            ));

            $details = array_merge($extra, array(
                'plugin_file'  => $plugin,
                'triggered_by' => $triggered_by,
                'hook_source'  => $hook_source,
            ));

            $this->logger->log_plugin_action($action, $slug, STATUS_SUCCESS, $details);
        } catch (\Throwable $e) {
            $this->file_logger->error('Failed to log plugin lifecycle: ' . $e->getMessage());
        }
    }

    /**
     * Detect the source that triggered the current action.
     *
     * @return string One of the TRIGGERED_BY_* constants.
     */
    private function detect_trigger_source() {
        if (defined('WP_CLI') && WP_CLI) {
            return TRIGGERED_BY_CLI;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return TRIGGERED_BY_CRON;
        }

        if ($this->is_rest_request()) {
            return TRIGGERED_BY_API;
        }

        return TRIGGERED_BY_DASHBOARD;
    }

    /**
     * Check if the current request is a REST API request.
     *
     * @return bool True if REST request.
     */
    private function is_rest_request() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Extract plugin slug from full plugin file path.
     *
     * @param string $plugin_file Plugin file path (e.g., "akismet/akismet.php").
     * @return string Plugin slug.
     */
    private function extract_plugin_slug($plugin_file) {
        if (strpos($plugin_file, '/') !== false) {
            $parts = explode('/', $plugin_file);
            return $parts[0];
        }

        return str_replace('.php', '', $plugin_file);
    }
}
