<?php
/**
 * PluginRouteRegistrationTrait — plugin and agent route registration.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;

trait PluginRouteRegistrationTrait {

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
}
