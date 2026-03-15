<?php
/**
 * Riseup Asia Uploader — Uninstall Handler
 *
 * Removes all plugin data when the plugin is deleted from WordPress.
 * This file is called by WordPress directly — no autoloader is available.
 *
 * @package RiseupAsiaUploader
 * @since   2.15.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Recursively delete a directory and all its contents.
 *
 * @param string $dir Absolute path to the directory.
 */
function riseup_asia_delete_directory(string $dir): void {
    $isDirMissing = !is_dir($dir);

    if ($isDirMissing) {
        return;
    }

    $items = scandir($dir);
    $isReadFailed = ($items === false);

    if ($isReadFailed) {
        return;
    }

    foreach ($items as $item) {
        $isNavEntry = ($item === '.' || $item === '..');

        if ($isNavEntry) {
            continue;
        }

        $path = $dir . '/' . $item;
        $isDirectory = is_dir($path);

        if ($isDirectory) {
            riseup_asia_delete_directory($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

// ---------------------------------------------------------------------------
// 1. Remove the uploads subdirectory (logs, temp, snapshots, and any other data)
// ---------------------------------------------------------------------------
// Literal string required — autoloader and enums are unavailable during uninstall.
$uploadDir = wp_upload_dir();
$pluginDataDir = rtrim($uploadDir['basedir'], '/') . '/riseup-asia-uploader';

riseup_asia_delete_directory($pluginDataDir);

// ---------------------------------------------------------------------------
// 2. Remove the SQLite database directory
// ---------------------------------------------------------------------------
$dbDir = rtrim($uploadDir['basedir'], '/') . '/riseup-asia-db';

riseup_asia_delete_directory($dbDir);

// ---------------------------------------------------------------------------
// 3. Remove stored WordPress options
// ---------------------------------------------------------------------------
// Literal strings required — enums unavailable during uninstall.
delete_option('RiseupSnapshotSettings');
delete_option('RiseupLogRetrievalSettings');
delete_option('RiseupUpdateSettings');
delete_option('RiseupAsiaSettings');
delete_option('RiseupErrorNotificationSettings');
delete_option('RiseupSupportSettings');
delete_option('riseup_asia_last_version');

// License options
delete_option('riseup_license_key');
delete_option('riseup_license_status');
delete_option('riseup_license_data');
delete_option('riseup_license_checked_at');

// Migration flag
delete_option('riseup_settings_migrated_v2');
