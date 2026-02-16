<?php
/**
 * WorkerBatchTrait — Shell trait for batch processing.
 *
 * Logic delegated to WorkerBatchProcessTrait and WorkerBatchExportTrait.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait WorkerBatchTrait {
    use WorkerBatchProcessTrait;
    use WorkerBatchExportTrait;
}
