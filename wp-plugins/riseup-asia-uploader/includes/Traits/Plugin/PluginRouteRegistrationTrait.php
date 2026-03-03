<?php
/**
 * PluginRouteRegistrationTrait — plugin and agent route registration.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;

trait PluginRouteRegistrationTrait {

    /**
     * Register plugin management routes.
     */
    private function registerPluginRoutes(callable $safeRegister): void {
        $perm = array($this, 'checkPluginPermission');

        $safeRegister(EndpointType::Upload->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleUpload'),
            'permission_callback' => $this->buildPermissionCallback('upload', $perm),
        ));

        $safeRegister(EndpointType::UploadActive->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleUploadActive'),
            'permission_callback' => $this->buildPermissionCallback('upload_active', $perm),
        ));

        $safeRegister(EndpointType::Plugins->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleListPlugins'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginInfo->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginInfo'),
            'permission_callback' => $this->buildPermissionCallback('plugin_info', $perm),
        ));

        $safeRegister(EndpointType::ExportSelf->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleExportSelf'),
            'permission_callback' => $this->buildPermissionCallback('export_self', $perm),
        ));

        $safeRegister(EndpointType::PluginFiles->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginFiles'),
            'permission_callback' => $this->buildPermissionCallback('plugin_files', $perm),
        ));

        $safeRegister(EndpointType::SyncManifest->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleSyncManifest'),
            'permission_callback' => $this->buildPermissionCallback('sync_manifest', $perm),
        ));

        $safeRegister(EndpointType::Sync->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleSyncPush'),
            'permission_callback' => $this->buildPermissionCallback('sync_push', $perm),
        ));

        $safeRegister(EndpointType::PluginFile->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginFileContent'),
            'permission_callback' => $this->buildPermissionCallback('plugin_file', $perm),
        ));

        $safeRegister(EndpointType::PluginExists->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginExists'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginEnable->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleEnablePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginDisable->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleDisablePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginDelete->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleDeletePlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ));

        $safeRegister(EndpointType::PluginExport->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleExportPlugin'),
            'permission_callback' => $this->buildPermissionCallback('plugin_export', $perm),
        ));

        // Plugin backup routes
        $safeRegister(EndpointType::PluginBackup->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginBackup'),
            'permission_callback' => $this->buildPermissionCallback('plugin_backup', $perm),
        ));

        $safeRegister(EndpointType::PluginBackupRestore->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginBackupRestore'),
            'permission_callback' => $this->buildPermissionCallback('plugin_backup_restore', $perm),
        ));

        $safeRegister(EndpointType::PluginBackupList->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handlePluginBackupList'),
            'permission_callback' => $this->buildPermissionCallback('plugin_backup_list', $perm),
        ));

        $safeRegister(EndpointType::PluginBackupDelete->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handlePluginBackupDelete'),
            'permission_callback' => $this->buildPermissionCallback('plugin_backup_delete', $perm),
        ));

        try {
            $safeRegister(EndpointType::Media->route(), array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handleMediaUpload'),
                'permission_callback' => $this->buildPermissionCallback('media', $perm),
            ));
        } catch (Throwable $e) {
            InitHelpers::errorLog($e, 'PluginRouteRegistrationTrait: Optional media endpoint registration failed:');
        }
    }

    /**
     * Register agent management routes.
     */
    private function registerAgentRoutes(callable $safeRegister, int &$failed): void {
        $agentRoutes = array(
            array('endpoint' => EndpointType::Agents,        'method' => HttpMethodType::Get,  'handler' => 'handleListAgents'),
            array('endpoint' => EndpointType::AgentsAdd,     'method' => HttpMethodType::Post, 'handler' => 'handleAddAgent'),
            array('endpoint' => EndpointType::AgentsRemove,  'method' => HttpMethodType::Post, 'handler' => 'handleRemoveAgent'),
            array('endpoint' => EndpointType::AgentsTest,    'method' => HttpMethodType::Post, 'handler' => 'handleTestAgent'),
            array('endpoint' => EndpointType::AgentsSync,    'method' => HttpMethodType::Post, 'handler' => 'handleSyncAgent'),
            array('endpoint' => EndpointType::AgentsPlugins, 'method' => HttpMethodType::Post, 'handler' => 'handleAgentPlugins'),
            array('endpoint' => EndpointType::AgentAction,   'method' => HttpMethodType::Post, 'handler' => 'handleAgentAction'),
            array('endpoint' => EndpointType::AgentHistory,  'method' => HttpMethodType::Get,  'handler' => 'handleAgentHistory'),
        );

        foreach ($agentRoutes as $route) {
            try {
                $safeRegister($route['endpoint']->route(), array(
                    'methods'             => $route['method']->value,
                    'callback'            => array($this, $route['handler']),
                    'permission_callback' => $this->buildPermissionCallback('agents', array($this, 'checkPluginPermission')),
                ));
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->logException($e, 'Agent route ' . $route['endpoint']->name . ' failed');
            }
        }
    }
}
