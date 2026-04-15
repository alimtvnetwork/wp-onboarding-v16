<?php
/**
 * RouteRegistrationLogTrait — Log and log management route registration.
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

trait RouteRegistrationLogTrait
{
    /**
     * Register log query and stats routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerLogRoutes(callable $safeRegister): void {
        $logPerm = [$this, 'checkLogsPermission'];

        $safeRegister(EndpointType::Logs->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleQueryLogs'],
            'permission_callback' => $this->buildPermissionCallback('logs', $logPerm),
        ]);

        $safeRegister(EndpointType::LogsStats->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleLogsStats'],
            'permission_callback' => $this->buildPermissionCallback('logs', $logPerm),
        ]);

        $safeRegister(EndpointType::ErrorLogs->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleErrorLogs'],
            'permission_callback' => $this->buildPermissionCallback('error_logs', $logPerm),
        ]);

        $safeRegister(EndpointType::ErrorSessions->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleErrorSessions'],
            'permission_callback' => $this->buildPermissionCallback('error_sessions', $logPerm),
        ]);
    }

    /**
     * Register remote log management routes (status, clear, confirm).
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerLogManagementRoutes(callable $safeRegister): void {
        $logPerm = [$this, 'checkLogsPermission'];

        $safeRegister(EndpointType::LogsStatus->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleLogsStatus'],
            'permission_callback' => $this->buildPermissionCallback('logs_status', $logPerm),
        ]);

        $safeRegister(EndpointType::LogsRotationStatus->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleLogsRotationStatus'],
            'permission_callback' => $this->buildPermissionCallback('logs_rotation_status', $logPerm),
        ]);

        $safeRegister(EndpointType::LogsClear->route(), [
            'methods'             => HttpMethodType::Delete->value,
            'callback'            => [$this, 'handleLogsClearRequest'],
            'permission_callback' => $this->buildPermissionCallback('logs_clear', [$this, 'checkPluginPermission']),
        ]);

        $safeRegister(EndpointType::LogsClearAll->route(), [
            'methods'             => HttpMethodType::Delete->value,
            'callback'            => [$this, 'handleLogsClearAll'],
            'permission_callback' => $this->buildPermissionCallback('logs_clear_all', [$this, 'checkPluginPermission']),
        ]);

        $safeRegister(EndpointType::LogsConfirm->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleLogsClearConfirm'],
            'permission_callback' => $this->buildPermissionCallback('logs_confirm', [$this, 'checkPluginPermission']),
        ]);

        $safeRegister(EndpointType::LogsEmail->route(), [
            'methods'             => HttpMethodType::Post->value,
            'callback'            => [$this, 'handleLogsEmail'],
            'permission_callback' => $this->buildPermissionCallback('logs_email', [$this, 'checkPluginPermission']),
        ]);

        $safeRegister(EndpointType::LogsRetrieve->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleLogsRetrieve'],
            'permission_callback' => $this->buildPermissionCallback('logs_retrieve', [$this, 'checkPluginPermission']),
        ]);

        $safeRegister(EndpointType::LogsDedupRegistry->route(), [
            'methods'             => HttpMethodType::Get->value . ', ' . HttpMethodType::Delete->value,
            'callback'            => [$this, 'handleLogsDedupRegistry'],
            'permission_callback' => $this->buildPermissionCallback('logs_dedup_registry', [$this, 'checkPluginPermission']),
        ]);

        $safeRegister(EndpointType::MachinesApprove->route(), [
            'methods'             => HttpMethodType::Put->value,
            'callback'            => [$this, 'handleApproveMachine'],
            'permission_callback' => $this->buildPermissionCallback('machines_approve', [$this, 'checkPluginPermission']),
        ]);
    }
}
