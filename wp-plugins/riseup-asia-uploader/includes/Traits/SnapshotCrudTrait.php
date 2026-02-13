<?php
/**
 * SnapshotCrudTrait — snapshot list, create, get, delete, and restore handlers.
 *
 * Shell trait delegating to SnapshotCrudListTrait and SnapshotCrudMutateTrait.
 *
 * @package RiseupAsiaUploader
 */

require_once __DIR__ . '/SnapshotCrudListTrait.php';
require_once __DIR__ . '/SnapshotCrudMutateTrait.php';

trait SnapshotCrudTrait {
    use SnapshotCrudListTrait;
    use SnapshotCrudMutateTrait;
}
