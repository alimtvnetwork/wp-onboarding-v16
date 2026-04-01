<?php
/**
 * SnapshotRouteRegistrationTrait — snapshot route registration.
 *
 * @package RiseupAsia\Traits\Snapshot
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;

trait SnapshotRouteRegistrationTrait {

    /** Register snapshot management routes. */
    private function registerSnapshotRoutes(callable $safeRegister): void {
        $perm = $this->buildPermissionCallback('snapshots', [$this, 'checkPluginPermission']);

        $safeRegister(EndpointType::SnapshotList->route(), [
            'methods' => HttpMethodType::Get->value, 'callback' => [$this, 'handleListSnapshots'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotSchedule->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleScheduleSnapshot'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotInfo->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleSnapshotInfo'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotDelete->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleDeleteSnapshot'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotRestore->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleRestoreSnapshot'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotExport->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleExportSnapshot'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotImport->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleImportSnapshot'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotSettings->route(), [
            [
                'methods' => HttpMethodType::Get->value, 'callback' => [$this, 'handleGetSnapshotSettings'], 'permission_callback' => $perm,
            ],
            [
                'methods' => HttpMethodType::Post->value . ',' . HttpMethodType::Put->value, 'callback' => [$this, 'handleUpdateSnapshotSettings'], 'permission_callback' => $perm,
            ],
        ]);
        $safeRegister(EndpointType::SnapshotProviders->route(), [
            'methods' => HttpMethodType::Get->value, 'callback' => [$this, 'handleListSnapshotProviders'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotTables->route(), [
            'methods' => HttpMethodType::Get->value, 'callback' => [$this, 'handleListSnapshotTables'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotDependencies->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleAnalyzeDependencies'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotExportPertable->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleExportPertable'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotFullBackup->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleFullBackup'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotIncremental->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleIncrementalBackup'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotCleanup->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleSnapshotCleanup'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotProgress->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleSnapshotProgress'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotDownload->route(), [
            'methods' => HttpMethodType::Post->value, 'callback' => [$this, 'handleSnapshotDownload'], 'permission_callback' => $perm,
        ]);
        $safeRegister(EndpointType::SnapshotDownloadFile->route(), [
            'methods'             => HttpMethodType::Get->value,
            'callback'            => [$this, 'handleSnapshotDownloadFile'],
            'permission_callback' => '__return_true', // Nonce-validated in handler
        ]);
    }
}