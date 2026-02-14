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
     * @param callable $safeRegister Route registration closure.
     */
    private function registerPluginRoutes($safeRegister) {
        $perm = array($this, 'checkPluginPermission');

        $safeRegister(ENDPOINT_UPLOAD, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_upload'),
            'permission_callback' => $this->buildPermissionCallback('upload', $perm),
        ));

        $safeRegister(ENDPOINT_PLUGINS, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleListPlugins'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(ENDPOINT_EXPORT_SELF, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleExportSelf'),
            'permission_callback' => $this->buildPermissionCallback('export_self', $perm),
        ));

        $safeRegister(ENDPOINT_PLUGIN_FILES, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginFiles'),
            'permission_callback' => $this->buildPermissionCallback('plugin_files', $perm),
        ));

        $safeRegister(ENDPOINT_SYNC_MANIFEST, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_sync_manifest'),
            'permission_callback' => $this->buildPermissionCallback('sync_manifest', $perm),
        ));

        $safeRegister(ENDPOINT_SYNC, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handle_sync_push'),
            'permission_callback' => $this->buildPermissionCallback('sync_push', $perm),
        ));

        $safeRegister(ENDPOINT_PLUGIN_FILE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginFileContent'),
            'permission_callback' => $this->buildPermissionCallback('plugin_file', $perm),
        ));

        $safeRegister(ENDPOINT_PLUGIN_EXISTS, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginExists'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(ENDPOINT_PLUGIN_ENABLE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleEnablePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(ENDPOINT_PLUGIN_DISABLE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleDisablePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(ENDPOINT_PLUGIN_DELETE, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleDeletePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(ENDPOINT_PLUGIN_EXPORT, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleExportPlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugin_export', $perm),
        ));

        try {
            if (defined('ENDPOINT_MEDIA')) {
                $safeRegister(ENDPOINT_MEDIA, array(
                    'methods'             => HttpMethodType::Post->value,
                    'callback'            => array($this, 'handle_media_upload'),
                    'permission_callback' => $this->buildPermissionCallback('media', $perm),
                ));
            }
        } catch (Throwable $e) {
            // Optional endpoint, ignore
        }
    }

    /**
     * Register agent management routes.
     *
     * @param callable $safeRegister Route registration closure.
     * @param int      &$failed      Failed registration counter.
     */
    private function registerAgentRoutes($safeRegister, &$failed) {
        $agent_routes = array(
            array('const' => 'ENDPOINT_AGENTS_LIST',    'method' => HttpMethodType::Get,  'handler' => 'handleListAgents'),
            array('const' => 'ENDPOINT_AGENTS_ADD',     'method' => HttpMethodType::Post, 'handler' => 'handleAddAgent'),
            array('const' => 'ENDPOINT_AGENTS_REMOVE',  'method' => HttpMethodType::Post, 'handler' => 'handleRemoveAgent'),
            array('const' => 'ENDPOINT_AGENTS_TEST',    'method' => HttpMethodType::Post, 'handler' => 'handleTestAgent'),
            array('const' => 'ENDPOINT_AGENTS_SYNC',    'method' => HttpMethodType::Post, 'handler' => 'handle_sync_to_agent'),
            array('const' => 'ENDPOINT_AGENTS_PLUGINS', 'method' => HttpMethodType::Post, 'handler' => 'handle_agent_plugin_action'),
        );

        foreach ($agent_routes as $route) {
            try {
                $endpoint = constant($route['const']);
                $safeRegister($endpoint, array(
                    'methods'             => $route['method']->value,
                    'callback'            => array($this, $route['handler']),
                    'permission_callback' => $this->buildPermissionCallback('agents', array($this, 'checkPluginPermission')),
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->error('Agent route ' . $route['const'] . ' failed: ' . $e->getMessage());
            }
        }
    }
}
