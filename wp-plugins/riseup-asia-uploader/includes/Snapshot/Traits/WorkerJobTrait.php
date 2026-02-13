<?php
/**
 * WorkerJobTrait — Shell trait for snapshot job management.
 *
 * Logic delegated to WorkerJobLifecycleTrait and WorkerJobProgressTrait.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

require_once __DIR__ . '/WorkerJobLifecycleTrait.php';
require_once __DIR__ . '/WorkerJobProgressTrait.php';

trait WorkerJobTrait {
    use WorkerJobLifecycleTrait;
    use WorkerJobProgressTrait;
}
