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
        $perm = [$this, 'checkPluginPermission'];

        $safeRegister(EndpointType::Upload->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleUpload'],
            'permission_callback' => $this->buildPermissionCallback('upload', $perm),
        ]);

        $safeRegister(EndpointType::UploadActive->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleUploadActive'],
            'permission_callback' => $this->buildPermissionCallback('upload_active', $perm),
        ]);

        $safeRegister(EndpointType::Plugins->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleListPlugins'],
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ]);

        $safeRegister(EndpointType::PluginInfo->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handlePluginInfo'],
            'permission_callback' => $this->buildPermissionCallback('plugin_info', $perm),
        ]);

        $safeRegister(EndpointType::ExportSelf->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleExportSelf'],
            'permission_callback' => $this->buildPermissionCallback('export_self', $perm),
        ]);

        $safeRegister(EndpointType::PluginFiles->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handlePluginFiles'],
            'permission_callback' => $this->buildPermissionCallback('plugin_files', $perm),
        ]);

        $safeRegister(EndpointType::SyncManifest->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleSyncManifest'],
            'permission_callback' => $this->buildPermissionCallback('sync_manifest', $perm),
        ]);

        $safeRegister(EndpointType::Sync->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleSyncPush'],
            'permission_callback' => $this->buildPermissionCallback('sync_push', $perm),
        ]);

        $safeRegister(EndpointType::PluginFile->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handlePluginFileContent'],
            'permission_callback' => $this->buildPermissionCallback('plugin_file', $perm),
        ]);

        $safeRegister(EndpointType::PluginExists->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handlePluginExists'],
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ]);

        $safeRegister(EndpointType::PluginEnable->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleEnablePlugin'],
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ]);

        $safeRegister(EndpointType::PluginDisable->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleDisablePlugin'],
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ]);

        $safeRegister(EndpointType::PluginDelete->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleDeletePlugin'],
            'permission_callback' => $this->buildPermissionCallback('plugins', $perm),
        ]);

        $safeRegister(EndpointType::PluginExport->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleExportPlugin'],
            'permission_callback' => $this->buildPermissionCallback('plugin_export', $perm),
        ]);

        // Plugin backup routes
        $safeRegister(EndpointType::PluginBackup->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handlePluginBackup'],
            'permission_callback' => $this->buildPermissionCallback('plugin_backup', $perm),
        ]);

        $safeRegister(EndpointType::PluginBackupRestore->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handlePluginBackupRestore'],
            'permission_callback' => $this->buildPermissionCallback('plugin_backup_restore', $perm),
        ]);

        $safeRegister(EndpointType::PluginBackupList->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handlePluginBackupList'],
            'permission_callback' => $this->buildPermissionCallback('plugin_backup_list', $perm),
        ]);

        $safeRegister(EndpointType::PluginBackupDelete->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handlePluginBackupDelete'],
            'permission_callback' => $this->buildPermissionCallback('plugin_backup_delete', $perm),
        ]);

        try {
            $safeRegister(EndpointType::Media->route(), [
                'methods'             => HttpMethodType::Post->value,
                'callback'            => [$this, 'handleMediaUpload'],
                'permission_callback' => $this->buildPermissionCallback('media', $perm),
            ]);
        } catch (Throwable $e) {
            InitHelpers::errorLogAndThrow($e, 'PluginRouteRegistrationTrait: Optional media endpoint registration failed:');
        }
    }

    /**
     * Register agent management routes.
     */
    private function registerAgentRoutes(callable $safeRegister, int &$failed): void {
        $agentRoutes = [
            ['endpoint' => EndpointType::Agents,        'method' => HttpMethodType::Get,  'handler' => 'handleListAgents'],
            ['endpoint' => EndpointType::AgentsAdd,     'method' => HttpMethodType::Post, 'handler' => 'handleAddAgent'],
            ['endpoint' => EndpointType::AgentsRemove,  'method' => HttpMethodType::Post, 'handler' => 'handleRemoveAgent'],
            ['endpoint' => EndpointType::AgentsTest,    'method' => HttpMethodType::Post, 'handler' => 'handleTestAgent'],
            ['endpoint' => EndpointType::AgentsSync,    'method' => HttpMethodType::Post, 'handler' => 'handleSyncAgent'],
            ['endpoint' => EndpointType::AgentsPlugins, 'method' => HttpMethodType::Post, 'handler' => 'handleAgentPlugins'],
            ['endpoint' => EndpointType::AgentAction,   'method' => HttpMethodType::Post, 'handler' => 'handleAgentAction'],
            ['endpoint' => EndpointType::AgentHistory,  'method' => HttpMethodType::Get,  'handler' => 'handleAgentHistory'],
        ];

        foreach ($agentRoutes as $route) {
            try {
                $safeRegister($route['endpoint']->route(), [
                    'methods'             => $route['method']->value,
                    'callback'            => [$this, $route['handler']],
                    'permission_callback' => $this->buildPermissionCallback('agents', [$this, 'checkPluginPermission']),
                ]);
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->logCriticalException($e, 'Agent route ' . $route['endpoint']->name . ' failed');
            }
        }
    }
}
