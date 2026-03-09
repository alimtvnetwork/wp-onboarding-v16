<?php
/**
 * LifecycleHooksTrait — WordPress plugin lifecycle event handlers.
 *
 * Handles activated_plugin, deactivated_plugin, and deleted_plugin hooks
 * with source detection and audit logging.
 *
 * @package RiseupAsia\Traits\Core
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\TriggerSourceType;

trait LifecycleHooksTrait
{
    /**
     * Handle WordPress core activated_plugin hook.
     */
    public function onPluginActivated(string $plugin, bool $networkWide = false): void {
        $this->logLifecycleEvent(ActionType::Enable->value, $plugin, 'activated_plugin', array(
            'networkWide' => $networkWide,
        ));
    }

    /**
     * Handle WordPress core deactivated_plugin hook.
     */
    public function onPluginDeactivated(string $plugin, bool $networkDeactivating = false): void {
        $this->logLifecycleEvent(ActionType::Disable->value, $plugin, 'deactivated_plugin', array(
            'networkDeactivating' => $networkDeactivating,
        ));
    }

    /**
     * Handle WordPress core deleted_plugin hook.
     */
    public function onPluginDeleted(string $plugin, bool $isDeleted = true): void {
        $isDeletionSkipped = ($isDeleted === false);

        if ($isDeletionSkipped) {
            return;
        }

        $this->logLifecycleEvent(ActionType::Delete->value, $plugin, 'deleted_plugin', array());
    }

    /**
     * Log a plugin lifecycle event with trigger source detection.
     */
    private function logLifecycleEvent(
        string $action,
        string $plugin,
        string $hookSource,
        array $extra,
    ) {
        if ($this->isRestRequest()) {
            return;
        }

        try {
            $slug = $this->extractPluginSlug($plugin);
            $triggeredBy = $this->detectTriggerSource();

            $this->fileLogger->info('WordPress hook: Plugin lifecycle event', array(
                'action'      => $action,
                'plugin'      => $plugin,
                'slug'        => $slug,
                'triggeredBy' => $triggeredBy,
            ));

            $details = array_merge($extra, array(
                'pluginFile'  => $plugin,
                'triggeredBy' => $triggeredBy,
                'hookSource'  => $hookSource,
            ));

            $this->logger->logPluginAction($action, $slug, StatusType::Success->value, $details);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to log plugin lifecycle');
        }
    }

    /**
     * Detect the source that triggered the current action.
     */
    private function detectTriggerSource(): string {
        if (defined('WP_CLI') && WP_CLI) {
            return TriggerSourceType::Cli->value;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return TriggerSourceType::Cron->value;
        }

        if ($this->isRestRequest()) {
            return TriggerSourceType::Api->value;
        }

        return TriggerSourceType::Dashboard->value;
    }

    /**
     * Check if the current request is a REST API request.
     */
    private function isRestRequest(): bool {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            $isRestApiRequest = (strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false);

            if ($isRestApiRequest) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract plugin slug from full plugin file path.
     */
    private function extractPluginSlug(string $pluginFile): string {
        $hasSubdirectory = (strpos($pluginFile, '/') !== false);

        if ($hasSubdirectory) {
            $parts = explode('/', $pluginFile);

            return $parts[0];
        }

        return str_replace('.php', '', $pluginFile);
    }
}
