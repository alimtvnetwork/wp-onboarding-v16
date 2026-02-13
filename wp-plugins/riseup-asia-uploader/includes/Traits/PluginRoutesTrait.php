<?php
/**
 * PluginRoutesTrait — Plugin, agent, and snapshot route registration.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;

trait PluginRoutesTrait
{
    /**
     * Register plugin management routes.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_plugin_routes($safe_register) {
        $perm = array($this, 'check_plugin_permission');

        $safe_register(ENDPOINT_UPLOAD, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_upload'),
            'permission_callback' => $this->build_permission_callback('upload', $perm),
        ));

        $safe_register(ENDPOINT_PLUGINS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_list_plugins'),
            'permission_callback' => $this->build_permission_callback('plugins', $perm),
        ));

        $safe_register(ENDPOINT_EXPORT_SELF, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_export_self'),
            'permission_callback' => $this->build_permission_callback('export_self', $perm),
        ));

        $safe_register(ENDPOINT_PLUGIN_FILES, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_plugin_files'),
            'permission_callback' => $this->build_permission_callback('plugin_files', $perm),
        ));

        $safe_register(ENDPOINT_SYNC_MANIFEST, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_sync_manifest'),
            'permission_callback' => $this->build_permission_callback('sync_manifest', $perm),
        ));

        $safe_register(ENDPOINT_SYNC, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_sync_push'),
            'permission_callback' => $this->build_permission_callback('sync_push', $perm),
        ));

        $safe_register(ENDPOINT_PLUGIN_FILE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_plugin_file_content'),
            'permission_callback' => $this->build_permission_callback('plugin_file', $perm),
        ));

        $safe_register(ENDPOINT_PLUGIN_EXISTS, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_plugin_exists'),
            'permission_callback' => $this->build_permission_callback('plugins', $perm),
        ));

        $safe_register(ENDPOINT_PLUGIN_ENABLE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_enable_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', $perm),
        ));

        $safe_register(ENDPOINT_PLUGIN_DISABLE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_disable_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', $perm),
        ));

        $safe_register(ENDPOINT_PLUGIN_DELETE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_delete_plugin'),
            'permission_callback' => $this->build_permission_callback('plugins', $perm),
        ));

        $safe_register(ENDPOINT_PLUGIN_EXPORT, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_export_plugin'),
            'permission_callback' => $this->build_permission_callback('plugin_export', $perm),
        ));

        try {
            if (defined('ENDPOINT_MEDIA')) {
                $safe_register(ENDPOINT_MEDIA, array(
                    'methods'             => HttpMethodType::Post->value,
                    'callback'            => array($this, 'handle_media_upload'),
                    'permission_callback' => $this->build_permission_callback('media', $perm),
                ));
            }
        } catch (Throwable $e) {
            // Optional endpoint, ignore
        }
    }

    /**
     * Register agent management routes.
     *
     * @param callable $safe_register Route registration closure.
     * @param int      &$failed      Failed registration counter.
     */
    private function register_agent_routes($safe_register, &$failed) {
        $agent_routes = array(
            array('const' => 'ENDPOINT_AGENTS_LIST',    'method' => HttpMethodType::Get,  'handler' => 'handle_list_agents'),
            array('const' => 'ENDPOINT_AGENTS_ADD',     'method' => HttpMethodType::Post, 'handler' => 'handle_add_agent'),
            array('const' => 'ENDPOINT_AGENTS_REMOVE',  'method' => HttpMethodType::Post, 'handler' => 'handle_remove_agent'),
            array('const' => 'ENDPOINT_AGENTS_TEST',    'method' => HttpMethodType::Post, 'handler' => 'handle_test_agent'),
            array('const' => 'ENDPOINT_AGENTS_SYNC',    'method' => HttpMethodType::Post, 'handler' => 'handle_sync_to_agent'),
            array('const' => 'ENDPOINT_AGENTS_PLUGINS', 'method' => HttpMethodType::Post, 'handler' => 'handle_agent_plugin_action'),
        );

        foreach ($agent_routes as $route) {
            try {
                $endpoint = constant($route['const']);
                $safe_register($endpoint, array(
                    'methods'             => $route['method']->value,
                    'callback'            => array($this, $route['handler']),
                    'permission_callback' => $this->build_permission_callback('agents', array($this, 'check_plugin_permission')),
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->file_logger->error('Agent route ' . $route['const'] . ' failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Register snapshot management routes.
     *
     * @param callable $safe_register Route registration closure.
     */
    private function register_snapshot_routes($safe_register) {
        $perm = $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission'));

        $safe_register(ENDPOINT_SNAPSHOT_LIST, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshots'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_SCHEDULE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_schedule_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_INFO, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_info'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_DELETE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_delete_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_RESTORE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_restore_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_EXPORT, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_export_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_IMPORT, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_import_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_SETTINGS, array(
            array(
                'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_get_snapshot_settings'), 'permission_callback' => $perm,
            ),
            array(
                'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_update_snapshot_settings'), 'permission_callback' => $perm,
            ),
        ));
        $safe_register(ENDPOINT_SNAPSHOT_PROVIDERS, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshot_providers'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_TABLES, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshot_tables'), 'permission_callback' => $perm,
        ));
        $safe_register('snapshots/dependencies', array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_analyze_dependencies'), 'permission_callback' => $perm,
        ));
        $safe_register('snapshots/export-pertable', array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_export_pertable'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_FULL_BACKUP, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_full_backup'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_INCREMENTAL, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_incremental_backup'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_CLEANUP, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_cleanup'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_PROGRESS, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_progress'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_DOWNLOAD, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_download'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_DOWNLOAD_FILE, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_snapshot_download_file'),
            'permission_callback' => '__return_true', // Nonce-validated in handler
        ));
    }
}
