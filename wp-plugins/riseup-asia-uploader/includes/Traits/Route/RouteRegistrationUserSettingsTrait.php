<?php
/**
 * RouteRegistrationUserSettingsTrait — User, site settings, debug, and catch-all routes.
 *
 * @package RiseupAsia\Traits\Route
 * @since   2.37.0
 */

namespace RiseupAsia\Traits\Route;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;

trait RouteRegistrationUserSettingsTrait
{
    /**
     * Register user management routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerUserRoutes(callable $safeRegister): void {
        $userPerm = [$this, 'checkUserPermission'];

        // GET + POST /users
        $safeRegister(EndpointType::Users->route(), [
            [
                'methods'             => HttpMethodType::Get->value,
                'callback'            => [$this, 'handleListUsers'],
                'permission_callback' => $this->buildPermissionCallback('users_list', $userPerm),
            ],
            [
                'methods'             => HttpMethodType::Post->value,
                'callback'            => [$this, 'handleCreateUser'],
                'permission_callback' => $this->buildPermissionCallback('users_create', $userPerm),
            ],
        ]);

        // GET + PUT + DELETE /users/{id}
        $safeRegister(EndpointType::UserId->route(), [
            [
                'methods'             => HttpMethodType::Get->value,
                'callback'            => [$this, 'handleGetUser'],
                'permission_callback' => $this->buildPermissionCallback('users_get', $userPerm),
            ],
            [
                'methods'             => HttpMethodType::Put->value,
                'callback'            => [$this, 'handleUpdateUser'],
                'permission_callback' => $this->buildPermissionCallback('users_update', $userPerm),
            ],
            [
                'methods'             => HttpMethodType::Delete->value,
                'callback'            => [$this, 'handleDeleteUser'],
                'permission_callback' => $this->buildPermissionCallback('users_delete', $userPerm),
            ],
        ]);

        // App passwords
        $safeRegister(EndpointType::UserAppPassword->route(), [
            [
                'methods'             => HttpMethodType::Post->value,
                'callback'            => [$this, 'handleCreateAppPass'],
                'permission_callback' => $this->buildPermissionCallback('users_app_password', $userPerm),
            ],
            [
                'methods'             => HttpMethodType::Delete->value,
                'callback'            => [$this, 'handleRevokeAppPass'],
                'permission_callback' => $this->buildPermissionCallback('users_app_password', $userPerm),
            ],
        ]);

        // Export/Import CSV
        $safeRegister(EndpointType::UsersExport->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleExportUsers'],
            'permission_callback' => $this->buildPermissionCallback('users_export', $userPerm),
        ]);

        $safeRegister(EndpointType::UsersImport->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleImportUsers'],
            'permission_callback' => $this->buildPermissionCallback('users_import', $userPerm),
        ]);

        // Export/Import SQLite
        $safeRegister(EndpointType::UsersExportSqlite->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleExportSqlite'],
            'permission_callback' => $this->buildPermissionCallback('users_export_sqlite', $userPerm),
        ]);

        $safeRegister(EndpointType::UsersImportSqlite->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleImportSqlite'],
            'permission_callback' => $this->buildPermissionCallback('users_import_sqlite', $userPerm),
        ]);
    }

    /**
     * Register site settings and health summary routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerSiteSettingsRoutes(callable $safeRegister): void {
        $settingsPerm = [$this, 'checkPluginPermission'];

        // GET + PUT /site-settings
        $safeRegister(EndpointType::SiteSettings->route(), [
            [
                'methods'             => HttpMethodType::Get->value,
                'callback'            => [$this, 'handleGetSiteSettings'],
                'permission_callback' => $this->buildPermissionCallback('site_settings', $settingsPerm),
            ],
            [
                'methods'             => HttpMethodType::Put->value,
                'callback'            => [$this, 'handleUpdateSiteSettings'],
                'permission_callback' => $this->buildPermissionCallback('site_settings_update', $settingsPerm),
            ],
        ]);

        // GET /site-health-summary
        $safeRegister(EndpointType::SiteHealthSummary->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleSiteHealthSummary'],
            'permission_callback' => $this->buildPermissionCallback('site_health_summary', $settingsPerm),
        ]);
    }

    /**
     * Register debug/diagnostic routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerDebugRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::DebugRoutes->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleDebugRoutes'],
            'permission_callback' => $this->buildPermissionCallback('debug_routes', [$this, 'checkPluginPermission']),
        ]);
    }

    /**
     * Register catch-all route for invalid paths.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerCatchAllRoute(callable $safeRegister): void {
        $safeRegister('/(?P<invalid_path>.+)', [
            'methods'             => [
                HttpMethodType::Get->value,
                HttpMethodType::Post->value,
                HttpMethodType::Put->value,
                HttpMethodType::Patch->value,
                HttpMethodType::Delete->value,
            ],
            'callback'            => [$this, 'handleInvalidRoute'],
            'permission_callback' => '__return_true',
        ]);
    }
}
