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

use RiseupAsia\Enums\ActionType;

trait LifecycleHooksTrait
{
    /**
     * Handle WordPress core activated_plugin hook.
     *
     * @param string $plugin       Plugin file path relative to plugins directory.
     * @param bool   $networkWide  Whether activated for the entire network.
     */
    public function onPluginActivated($plugin, $networkWide = false) {
        $this->logLifecycleEvent(ActionType::Enable->value, $plugin, 'activated_plugin', array(
            'network_wide' => $networkWide,
        ));
    }

    /**
     * Handle WordPress core deactivated_plugin hook.
     *
     * @param string $plugin               Plugin file path relative to plugins directory.
     * @param bool   $networkDeactivating   Whether deactivating across the network.
     */
    public function onPluginDeactivated($plugin, $networkDeactivating = false) {
        $this->logLifecycleEvent(ActionType::Disable->value, $plugin, 'deactivated_plugin', array(
            'network_deactivating' => $networkDeactivating,
        ));
    }

    /**
     * Handle WordPress core deleted_plugin hook.
     *
     * @param string $plugin  Plugin file path relative to plugins directory.
     * @param bool   $isDeleted Whether the plugin was successfully deleted.
     */
    public function onPluginDeleted($plugin, $isDeleted = true) {
        if (!$isDeleted) {
            return;
        }

        $this->logLifecycleEvent(ActionType::Delete->value, $plugin, 'deleted_plugin', array());
    }

    /**
     * Log a plugin lifecycle event with trigger source detection.
     *
     * @param string $action     Action constant (enable/disable/delete).
     * @param string $plugin     Plugin file path.
     * @param string $hookSource WordPress hook name.
     * @param array  $extra      Additional context fields.
     */
    private function logLifecycleEvent(string $action, string $plugin, string $hookSource, array $extra) {
        if ($this->isRestRequest()) {
            return;
        }

        try {
            $slug = $this->extractPluginSlug($plugin);
            $triggeredBy = $this->detectTriggerSource();

            $this->fileLogger->info('WordPress hook: Plugin lifecycle event', array(
                'action'       => $action,
                'plugin'       => $plugin,
                'slug'         => $slug,
                'triggered_by' => $triggeredBy,
            ));

            $details = array_merge($extra, array(
                'plugin_file'  => $plugin,
                'triggered_by' => $triggeredBy,
                'hook_source'  => $hookSource,
            ));

            $this->logger->logPluginAction($action, $slug, STATUS_SUCCESS, $details);
        } catch (\Throwable $e) {
            $this->fileLogger->error('Failed to log plugin lifecycle: ' . $e->getMessage());
        }
    }

    /**
     * Detect the source that triggered the current action.
     *
     * @return string One of the TRIGGERED_BY_* constants.
     */
    private function detectTriggerSource() {
        if (defined('WP_CLI') && WP_CLI) {
            return TRIGGERED_BY_CLI;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return TRIGGERED_BY_CRON;
        }

        if ($this->isRestRequest()) {
            return TRIGGERED_BY_API;
        }

        return TRIGGERED_BY_DASHBOARD;
    }

    /**
     * Check if the current request is a REST API request.
     *
     * @return bool True if REST request.
     */
    private function isRestRequest() {
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
     * @param string $pluginFile Plugin file path (e.g., "akismet/akismet.php").
     * @return string Plugin slug.
     */
    private function extractPluginSlug($pluginFile) {
        if (strpos($pluginFile, '/') !== false) {
            $parts = explode('/', $pluginFile);
            return $parts[0];
        }

        return str_replace('.php', '', $pluginFile);
    }
}
