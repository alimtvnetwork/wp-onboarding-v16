<?php
/**
 * SnapshotBackupTrait — snapshot settings, providers, backup, cleanup, and progress handlers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsiaUploader
 */

require_once dirname(__FILE__) . '/SnapshotSettingsHandlerTrait.php';
require_once dirname(__FILE__) . '/SnapshotBackupHandlerTrait.php';

trait SnapshotBackupTrait {
    use SnapshotSettingsHandlerTrait;
    use SnapshotBackupHandlerTrait;
}
