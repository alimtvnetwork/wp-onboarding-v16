<?php
/**
 * SnapshotExportTrait — snapshot export, download, and import handlers.
 *
 * Shell trait delegating to SnapshotExportHandlerTrait and SnapshotImportStreamTrait.
 *
 * @package RiseupAsia\Traits\Snapshot
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

trait SnapshotExportTrait {
    use SnapshotExportHandlerTrait;
    use SnapshotImportStreamTrait;
}