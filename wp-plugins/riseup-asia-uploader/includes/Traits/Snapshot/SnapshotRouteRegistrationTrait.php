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

trait SnapshotRouteRegistrationTrait {

    /**
     * Register snapshot management routes.
     *
     * @param callable $safeRegister Route registration closure.
     */
    private function registerSnapshotRoutes($safeRegister) {
        $perm = $this->buildPermissionCallback('snapshots', array($this, 'checkPluginPermission'));

        $safeRegister(ENDPOINT_SNAPSHOT_LIST, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshots'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_SCHEDULE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleScheduleSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_INFO, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotInfo'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_DELETE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleDeleteSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_RESTORE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleRestoreSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_EXPORT, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleExportSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_IMPORT, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleImportSnapshot'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_SETTINGS, array(
            array(
                'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleGetSnapshotSettings'), 'permission_callback' => $perm,
            ),
            array(
                'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleUpdateSnapshotSettings'), 'permission_callback' => $perm,
            ),
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_PROVIDERS, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshotProviders'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_TABLES, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handleListSnapshotTables'), 'permission_callback' => $perm,
        ));
        $safeRegister('snapshots/dependencies', array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleAnalyzeDependencies'), 'permission_callback' => $perm,
        ));
        $safeRegister('snapshots/export-pertable', array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleExportPertable'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_FULL_BACKUP, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleFullBackup'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_INCREMENTAL, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleIncrementalBackup'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_CLEANUP, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotCleanup'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_PROGRESS, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotProgress'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_DOWNLOAD, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handleSnapshotDownload'), 'permission_callback' => $perm,
        ));
        $safeRegister(ENDPOINT_SNAPSHOT_DOWNLOAD_FILE, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handleSnapshotDownloadFile'),
            'permission_callback' => '__return_true', // Nonce-validated in handler
        ));
    }
}
