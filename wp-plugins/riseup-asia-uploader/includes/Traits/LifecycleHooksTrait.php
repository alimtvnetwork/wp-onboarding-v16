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
        if ($this->is_rest_request()) {
            return;
        }

        try {
            $slug = $this->extract_plugin_slug($plugin);
            $triggered_by = $this->detect_trigger_source();

            $this->file_logger->info('WordPress hook: Plugin activated', array(
                'plugin'       => $plugin,
                'slug'         => $slug,
                'network_wide' => $network_wide,
                'triggered_by' => $triggered_by,
            ));

            $this->logger->log_plugin_action(
                ACTION_ENABLE,
                $slug,
                STATUS_SUCCESS,
                array(
                    'plugin_file'   => $plugin,
                    'network_wide'  => $network_wide,
                    'triggered_by'  => $triggered_by,
                    'hook_source'   => 'activated_plugin',
                )
            );
        } catch (Throwable $e) {
            $this->file_logger->error('Failed to log plugin activation: ' . $e->getMessage());
        }
    }

    /**
     * Handle WordPress core deactivated_plugin hook.
     *
     * @param string $plugin               Plugin file path relative to plugins directory.
     * @param bool   $network_deactivating Whether deactivating across the network.
     */
    public function on_plugin_deactivated($plugin, $network_deactivating = false) {
        if ($this->is_rest_request()) {
            return;
        }

        try {
            $slug = $this->extract_plugin_slug($plugin);
            $triggered_by = $this->detect_trigger_source();

            $this->file_logger->info('WordPress hook: Plugin deactivated', array(
                'plugin'       => $plugin,
                'slug'         => $slug,
                'network'      => $network_deactivating,
                'triggered_by' => $triggered_by,
            ));

            $this->logger->log_plugin_action(
                ACTION_DISABLE,
                $slug,
                STATUS_SUCCESS,
                array(
                    'plugin_file'          => $plugin,
                    'network_deactivating' => $network_deactivating,
                    'triggered_by'         => $triggered_by,
                    'hook_source'          => 'deactivated_plugin',
                )
            );
        } catch (Throwable $e) {
            $this->file_logger->error('Failed to log plugin deactivation: ' . $e->getMessage());
        }
    }

    /**
     * Handle WordPress core deleted_plugin hook.
     *
     * @param string $plugin  Plugin file path relative to plugins directory.
     * @param bool   $deleted Whether the plugin was successfully deleted.
     */
    public function on_plugin_deleted($plugin, $deleted = true) {
        if ($this->is_rest_request()) {
            return;
        }

        if (!$deleted) {
            return;
        }

        try {
            $slug = $this->extract_plugin_slug($plugin);
            $triggered_by = $this->detect_trigger_source();

            $this->file_logger->info('WordPress hook: Plugin deleted', array(
                'plugin'       => $plugin,
                'slug'         => $slug,
                'triggered_by' => $triggered_by,
            ));

            $this->logger->log_plugin_action(
                ACTION_DELETE,
                $slug,
                STATUS_SUCCESS,
                array(
                    'plugin_file'  => $plugin,
                    'triggered_by' => $triggered_by,
                    'hook_source'  => 'deleted_plugin',
                )
            );
        } catch (Throwable $e) {
            $this->file_logger->error('Failed to log plugin deletion: ' . $e->getMessage());
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
