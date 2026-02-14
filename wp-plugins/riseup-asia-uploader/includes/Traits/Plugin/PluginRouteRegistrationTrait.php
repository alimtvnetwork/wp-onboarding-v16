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
use RiseupAsia\Enums\EndpointType;

trait PluginRouteRegistrationTrait {

    /**
     * Register plugin management routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerPluginRoutes($safeRegister) {
        $perm = array($this, 'checkPluginPermission');

        $safeRegister(EndpointType::Upload->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleUpload'),
            'permission_callback' => $this->buildPermissionCallback('upload', $perm),
        ));

        $safeRegister(EndpointType::Plugins->value, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleListPlugins'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::ExportSelf->value, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleExportSelf'),
            'permission_callback' => $this->buildPermissionCallback('export_self', $perm),
        ));

        $safeRegister(EndpointType::PluginFiles->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginFiles'),
            'permission_callback' => $this->buildPermissionCallback('plugin_files', $perm),
        ));

        $safeRegister(EndpointType::SyncManifest->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleSyncManifest'),
            'permission_callback' => $this->buildPermissionCallback('sync_manifest', $perm),
        ));

        $safeRegister(EndpointType::Sync->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleSyncPush'),
            'permission_callback' => $this->buildPermissionCallback('sync_push', $perm),
        ));

        $safeRegister(EndpointType::PluginFile->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginFileContent'),
            'permission_callback' => $this->buildPermissionCallback('plugin_file', $perm),
        ));

        $safeRegister(EndpointType::PluginExists->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginExists'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginEnable->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleEnablePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginDisable->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleDisablePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginDelete->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleDeletePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginExport->value, array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleExportPlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugin_export', $perm),
        ));

        try {
            $safeRegister(EndpointType::Media->value, array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handleMediaUpload'),
                'permission_callback' => $this->buildPermissionCallback('media', $perm),
            ));
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
            array('endpoint' => EndpointType::Agents,        'method' => HttpMethodType::Get,  'handler' => 'handleListAgents'),
            array('endpoint' => EndpointType::AgentsAdd,     'method' => HttpMethodType::Post, 'handler' => 'handleAddAgent'),
            array('endpoint' => EndpointType::AgentsRemove,  'method' => HttpMethodType::Post, 'handler' => 'handleRemoveAgent'),
            array('endpoint' => EndpointType::AgentsTest,    'method' => HttpMethodType::Post, 'handler' => 'handleTestAgent'),
            array('endpoint' => EndpointType::AgentsSync,    'method' => HttpMethodType::Post, 'handler' => 'handleSyncAgent'),
            array('endpoint' => EndpointType::AgentsPlugins, 'method' => HttpMethodType::Post, 'handler' => 'handleAgentAction'),
        );

        foreach ($agent_routes as $route) {
            try {
                $safeRegister($route['endpoint']->value, array(
                    'methods'             => $route['method']->value,
                    'callback'            => array($this, $route['handler']),
                    'permission_callback' => $this->buildPermissionCallback('agents', array($this, 'checkPluginPermission')),
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->error('Agent route ' . $route['endpoint']->name . ' failed: ' . $e->getMessage());
            }
        }
    }
}
