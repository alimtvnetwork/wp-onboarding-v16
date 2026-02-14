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

    /**
     * Register snapshot management routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerSnapshotRoutes($safeRegister) {
        $perm = $this->buildPermissionCallback('snapshots', array($this, 'checkPluginPermission'));

        $safeRegister(EndpointType::SnapshotList->value, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshots'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotSchedule->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleScheduleSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotInfo->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotInfo'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotDelete->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleDeleteSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotRestore->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleRestoreSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotExport->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleExportSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotImport->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleImportSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotSettings->value, array(
            array(
                'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleGetSnapshotSettings'), 'permission_callback' => $perm,
            ),
            array(
                'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleUpdateSnapshotSettings'), 'permission_callback' => $perm,
            ),
        ));
        $safeRegister(EndpointType::SnapshotProviders->value, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshotProviders'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotTables->value, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshotTables'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotDependencies->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleAnalyzeDependencies'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotExportPertable->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleExportPertable'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotFullBackup->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleFullBackup'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotIncremental->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleIncrementalBackup'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotCleanup->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotCleanup'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotProgress->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotProgress'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotDownload->value, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotDownload'), 'permission_callback' => $perm,
        ));
        $safeRegister(EndpointType::SnapshotDownloadFile->value, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleSnapshotDownloadFile'),
            'permission_callback' => '__return_true', // Nonce-validated in handler
        ));
    }
}
