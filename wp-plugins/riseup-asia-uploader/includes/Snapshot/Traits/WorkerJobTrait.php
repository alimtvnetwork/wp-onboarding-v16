<?php
/**
 * WorkerJobTrait — Shell trait for snapshot job management.
 *
 * Logic delegated to WorkerJobLifecycleTrait and WorkerJobProgressTrait.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait WorkerJobTrait {
    use WorkerJobLifecycleTrait;
    use WorkerJobProgressTrait;
}
