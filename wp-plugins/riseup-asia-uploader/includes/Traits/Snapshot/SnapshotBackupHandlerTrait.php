<?php
/**
 * SnapshotBackupHandlerTrait — Shell trait for backup REST handlers.
 *
 * Logic delegated to SnapshotBackupExecTrait and SnapshotBackupOpsTrait.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

require_once __DIR__ . '/SnapshotBackupExecTrait.php';
require_once __DIR__ . '/SnapshotBackupOpsTrait.php';

trait SnapshotBackupHandlerTrait {
    use SnapshotBackupExecTrait;
    use SnapshotBackupOpsTrait;
}
