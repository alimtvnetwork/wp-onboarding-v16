<?php
/**
 * SnapshotCrudTrait — snapshot list, create, get, delete, and restore handlers.
 *
 * Shell trait delegating to SnapshotCrudListTrait and SnapshotCrudMutateTrait.
 *
 * @package RiseupAsia\Traits\Snapshot
 */

namespace RiseupAsia\Traits\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

trait SnapshotCrudTrait {
    use SnapshotCrudListTrait;
    use SnapshotCrudMutateTrait;
}