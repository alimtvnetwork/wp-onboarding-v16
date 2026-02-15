<?php
/**
 * SnapshotRouteRegistrationTrait — snapshot route registration.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpMethodType;
use RiseupAsia\Enums\EndpointType;

trait SnapshotRouteRegistrationTrait {

    /** Register snapshot management routes. */
    private function registerSnapshotRoutes(callable $safeRegister): void {
        $perm = $this->buildPermissionCallback('snapshots', array($this, 'checkPluginPermission'));

        $safeRegister(EndpointType::SnapshotList->route(), array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshots'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotSchedule->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleScheduleSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotInfo->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotInfo'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotDelete->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleDeleteSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotRestore->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleRestoreSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotExport->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleExportSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotImport->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleImportSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotSettings->route(), array(
            array(
                'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleGetSnapshotSettings'), 'permission_callback' => $perm,
            ),
            array(
                'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleUpdateSnapshotSettings'), 'permission_callback' => $perm,
            ),
        ));
        $safeRegister(EndpointType::SnapshotProviders->route(), array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshotProviders'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotTables->route(), array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshotTables'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotDependencies->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleAnalyzeDependencies'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotExportPertable->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleExportPertable'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotFullBackup->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleFullBackup'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotIncremental->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleIncrementalBackup'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotCleanup->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotCleanup'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotProgress->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotProgress'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotDownload->route(), array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotDownload'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotDownloadFile->route(), array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleSnapshotDownloadFile'),
            'permission_callback' => '__return_true', // Nonce-validated in handler
        ));
    }
}