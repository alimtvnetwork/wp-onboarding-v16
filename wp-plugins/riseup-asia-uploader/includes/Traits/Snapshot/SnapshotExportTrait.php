<?php
/**
 * SnapshotExportTrait — snapshot export, download, and import handlers.
 *
 * Shell trait delegating to SnapshotExportHandlerTrait and SnapshotImportStreamTrait.
 *
 * @package RiseupAsiaUploader
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/SnapshotExportHandlerTrait.php';
require_once __DIR__ . '/SnapshotImportStreamTrait.php';

trait SnapshotExportTrait {
    use SnapshotExportHandlerTrait;
    use SnapshotImportStreamTrait;
}
