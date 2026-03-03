<?php
/**
 * QUpload Uninstall Handler
 *
 * Removes all plugin data when the plugin is deleted from WordPress.
 * This file is called by WordPress directly — no autoloader is available.
 *
 * @package QUpload
 * @since   1.2.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Recursively delete a directory and all its contents.
 *
 * @param string $dir Absolute path to the directory.
 */
function qupload_delete_directory(string $dir): void {
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
            qupload_delete_directory($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

// ---------------------------------------------------------------------------
// 1. Remove the uploads subdirectory (logs, temp, and any other data)
// ---------------------------------------------------------------------------
// Literal string required — autoloader and enums are unavailable during uninstall.
$uploadDir = wp_upload_dir();
$pluginDataDir = rtrim($uploadDir['basedir'], '/') . '/qupload';

qupload_delete_directory($pluginDataDir);

// ---------------------------------------------------------------------------
// 2. Remove any WordPress options (future-proofing — none stored currently)
// ---------------------------------------------------------------------------
// delete_option('qupload_settings');
