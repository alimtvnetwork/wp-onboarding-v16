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
     * @param callable $safe_register Route registration closure.
     */
    private function register_snapshot_routes($safe_register) {
        $perm = $this->build_permission_callback('snapshots', array($this, 'check_plugin_permission'));

        $safe_register(ENDPOINT_SNAPSHOT_LIST, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshots'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_SCHEDULE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_schedule_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_INFO, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_info'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_DELETE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_delete_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_RESTORE, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_restore_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_EXPORT, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_export_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_IMPORT, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_import_snapshot'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_SETTINGS, array(
            array(
                'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_get_snapshot_settings'), 'permission_callback' => $perm,
            ),
            array(
                'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_update_snapshot_settings'), 'permission_callback' => $perm,
            ),
        ));
        $safe_register(ENDPOINT_SNAPSHOT_PROVIDERS, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshot_providers'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_TABLES, array(
            'methods' => HttpMethodType::Get->value, 'callback' => array($this, 'handle_list_snapshot_tables'), 'permission_callback' => $perm,
        ));
        $safe_register('snapshots/dependencies', array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_analyze_dependencies'), 'permission_callback' => $perm,
        ));
        $safe_register('snapshots/export-pertable', array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_export_pertable'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_FULL_BACKUP, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_full_backup'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_INCREMENTAL, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_incremental_backup'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_CLEANUP, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_cleanup'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_PROGRESS, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_progress'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_DOWNLOAD, array(
            'methods' => HttpMethodType::Post->value, 'callback' => array($this, 'handle_snapshot_download'), 'permission_callback' => $perm,
        ));
        $safe_register(ENDPOINT_SNAPSHOT_DOWNLOAD_FILE, array(
            'methods'             => HttpMethodType::Get->value,
            'callback'            => array($this, 'handle_snapshot_download_file'),
            'permission_callback' => '__return_true', // Nonce-validated in handler
        ));
    }
}
