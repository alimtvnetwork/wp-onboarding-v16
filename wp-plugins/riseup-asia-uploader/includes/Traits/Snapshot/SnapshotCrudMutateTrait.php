<?php
/**
 * SnapshotCrudMutateTrait — snapshot create, delete, restore, and helpers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits\Snapshot
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

trait SnapshotCrudMutateTrait {
    use SnapshotCrudCreateTrait;
    use SnapshotCrudRestoreTrait;
}