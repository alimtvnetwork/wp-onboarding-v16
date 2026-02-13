<?php
/**
 * SnapshotCrudMutateTrait — snapshot create, delete, restore, and helpers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load sub-traits
require_once __DIR__ . '/SnapshotCrudCreateTrait.php';
require_once __DIR__ . '/SnapshotCrudRestoreTrait.php';

trait SnapshotCrudMutateTrait {
    use SnapshotCrudCreateTrait;
    use SnapshotCrudRestoreTrait;
}
