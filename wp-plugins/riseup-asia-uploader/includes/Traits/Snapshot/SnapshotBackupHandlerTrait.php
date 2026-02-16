<?php
/**
 * SnapshotBackupHandlerTrait — Shell trait for backup REST handlers.
 *
 * Logic delegated to SnapshotBackupExecTrait and SnapshotBackupOpsTrait.
 *
 * @package RiseupAsia\Traits\Snapshot
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

trait SnapshotBackupHandlerTrait {
    use SnapshotBackupExecTrait;
    use SnapshotBackupOpsTrait;
}