<?php
/**
 * SnapshotBackupTrait — snapshot settings, providers, backup, cleanup, and progress handlers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits\Snapshot
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

trait SnapshotBackupTrait {
    use SnapshotSettingsHandlerTrait;
    use SnapshotBackupHandlerTrait;
}