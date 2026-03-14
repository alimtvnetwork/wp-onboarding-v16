<?php
/**
 * RouteRegistrationTrait — REST API route registration orchestrator.
 *
 * Contains registerRoutes and the utility/post/log/catch-all sub-registrars.
 * Plugin-specific routes (plugins, agents, snapshots) live in PluginRoutesTrait.
 *
 * @package RiseupAsia\Traits\Route
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Route;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\PluginConfigType;

trait RouteRegistrationTrait
{
    /**
     * Register REST API routes.
     */
    public function registerRoutes() {
        $this->fileLogger->info('Registering REST API routes', array('namespace' => PluginConfigType::apiFullNamespace()));

        $registered = 0;
        $failed = 0;

        $safeRegister = function (string $route, array $args) use (&$registered, &$failed): void {
            try {
                register_rest_route(PluginConfigType::apiFullNamespace(), $route, $args);
                $registered++;
            } catch (Throwable $e) {
                $failed++;
                $this->fileLogger->logException($e, 'Failed to register route: ' . $route);
            }
        };

        $this->registerUtilityRoutes($safeRegister);
        $this->registerPluginRoutes($safeRegister);
        $this->registerPostRoutes($safeRegister);
        $this->registerLogRoutes($safeRegister);
        $this->registerLogManagementRoutes($safeRegister);
        $this->registerAgentRoutes($safeRegister, $failed);
        $this->registerSnapshotRoutes($safeRegister);
        $this->registerUserRoutes($safeRegister);
        $this->registerCatchAllRoute($safeRegister);

        $this->fileLogger->info("REST API route registration complete: $registered registered, $failed failed");
    }

    /**
     * Register utility routes (status, openapi, opcache-reset).
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerUtilityRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::Status->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleStatus'),
            'permission_callback' => $this->buildPermissionCallback('status', array($this, 'checkStatusPermission')),
        ));

        $safeRegister(EndpointType::Openapi->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleOpenapi'),
            'permission_callback' => $this->buildPermissionCallback('openapi', array($this, 'checkStatusPermission')),
        ));

        $safeRegister(EndpointType::OpcacheReset->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleOpcacheReset'),
            'permission_callback' => $this->buildPermissionCallback('opcache_reset', array($this, 'checkPluginPermission')),
        ));
    }

    /**
     * Register post and category routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerPostRoutes(callable $safeRegister): void {
        $safeRegister(EndpointType::Posts->route(), array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handleListPosts'),
                'permission_callback' => $this->buildPermissionCallback('posts', array($this, 'checkPostPermission')),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handleCreatePost'),
                'permission_callback' => $this->buildPermissionCallback('posts', array($this, 'checkPostPermission')),
            ),
        ));

        $safeRegister(EndpointType::Categories->route(), array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handleListCategories'),
                'permission_callback' => $this->buildPermissionCallback('categories', array($this, 'checkPostPermission')),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handleCreateCategory'),
                'permission_callback' => $this->buildPermissionCallback('categories', array($this, 'checkPostPermission')),
            ),
        ));
    }

    /**
     * Register log query and stats routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerLogRoutes(callable $safeRegister): void {
        $logPerm = array($this, 'checkLogsPermission');

        $safeRegister(EndpointType::Logs->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleQueryLogs'),
            'permission_callback' => $this->buildPermissionCallback('logs', $logPerm),
        ));

        $safeRegister(EndpointType::LogsStats->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleLogsStats'),
            'permission_callback' => $this->buildPermissionCallback('logs', $logPerm),
        ));

        $safeRegister(EndpointType::ErrorLogs->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleErrorLogs'),
            'permission_callback' => $this->buildPermissionCallback('error_logs', $logPerm),
        ));

        $safeRegister(EndpointType::ErrorSessions->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleErrorSessions'),
            'permission_callback' => $this->buildPermissionCallback('error_sessions', $logPerm),
        ));
    }

    /**
     * Register remote log management routes (status, clear, confirm).
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerLogManagementRoutes(callable $safeRegister): void {
        $logPerm = array($this, 'checkLogsPermission');

        $safeRegister(EndpointType::LogsStatus->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleLogsStatus'),
            'permission_callback' => $this->buildPermissionCallback('logs_status', $logPerm),
        ));

        $safeRegister(EndpointType::LogsClear->route(), array(
            'methods'             => HttpMethodType::Delete->value,
            'callback'            => array($this, 'handleLogsClearRequest'),
            'permission_callback' => $this->buildPermissionCallback('logs_clear', array($this, 'checkPluginPermission')),
        ));

        $safeRegister(EndpointType::LogsConfirm->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleLogsClearConfirm'),
            'permission_callback' => $this->buildPermissionCallback('logs_confirm', array($this, 'checkPluginPermission')),
        ));

        $safeRegister(EndpointType::LogsEmail->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleLogsEmail'),
            'permission_callback' => $this->buildPermissionCallback('logs_email', array($this, 'checkPluginPermission')),
        ));
    }

    /**
     * Register user management routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerUserRoutes(callable $safeRegister): void {
        $userPerm = array($this, 'checkUserPermission');

        // GET + POST /users
        $safeRegister(EndpointType::Users->route(), array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handleListUsers'),
                'permission_callback' => $this->buildPermissionCallback('users_list', $userPerm),
            ),
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handleCreateUser'),
                'permission_callback' => $this->buildPermissionCallback('users_create', $userPerm),
            ),
        ));

        // GET + PUT + DELETE /users/{id}
        $safeRegister(EndpointType::UserId->route(), array(
            array(
                'methods'             => HttpMethodType::Get->value,
                'callback'            => array($this, 'handleGetUser'),
                'permission_callback' => $this->buildPermissionCallback('users_get', $userPerm),
            ),
            array(
                'methods'             => HttpMethodType::Put->value,
                'callback'            => array($this, 'handleUpdateUser'),
                'permission_callback' => $this->buildPermissionCallback('users_update', $userPerm),
            ),
            array(
                'methods'             => HttpMethodType::Delete->value,
                'callback'            => array($this, 'handleDeleteUser'),
                'permission_callback' => $this->buildPermissionCallback('users_delete', $userPerm),
            ),
        ));

        // App passwords
        $safeRegister(EndpointType::UserAppPassword->route(), array(
            array(
                'methods'             => HttpMethodType::Post->value,
                'callback'            => array($this, 'handleCreateAppPass'),
                'permission_callback' => $this->buildPermissionCallback('users_app_password', $userPerm),
            ),
            array(
                'methods'             => HttpMethodType::Delete->value,
                'callback'            => array($this, 'handleRevokeAppPass'),
                'permission_callback' => $this->buildPermissionCallback('users_app_password', $userPerm),
            ),
        ));

        // Export/Import CSV
        $safeRegister(EndpointType::UsersExport->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleExportUsers'),
            'permission_callback' => $this->buildPermissionCallback('users_export', $userPerm),
        ));

        $safeRegister(EndpointType::UsersImport->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleImportUsers'),
            'permission_callback' => $this->buildPermissionCallback('users_import', $userPerm),
        ));

        // Export/Import SQLite
        $safeRegister(EndpointType::UsersExportSqlite->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleExportSqlite'),
            'permission_callback' => $this->buildPermissionCallback('users_export_sqlite', $userPerm),
        ));

        $safeRegister(EndpointType::UsersImportSqlite->route(), array(
            'methods'             => HttpMethodType::Post->value,
            'callback'            => array($this, 'handleImportSqlite'),
            'permission_callback' => $this->buildPermissionCallback('users_import_sqlite', $userPerm),
        ));
    }

    /**
     * Register catch-all route for invalid paths.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerCatchAllRoute(callable $safeRegister): void {
        $safeRegister('/(?P<invalid_path>.+)', array(
            'methods'             => array(
                HttpMethodType::Get->value,
                HttpMethodType::Post->value,
                HttpMethodType::Put->value,
                HttpMethodType::Patch->value,
                HttpMethodType::Delete->value,
            ),
            'callback'            => array($this, 'handleInvalidRoute'),
            'permission_callback' => '__return_true',
        ));
    }
}