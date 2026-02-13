<?php
/**
 * WorkerBatchTrait — Shell trait for batch processing.
 *
 * Logic delegated to WorkerBatchProcessTrait and WorkerBatchExportTrait.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

require_once __DIR__ . '/WorkerBatchProcessTrait.php';
require_once __DIR__ . '/WorkerBatchExportTrait.php';

trait WorkerBatchTrait {
    use WorkerBatchProcessTrait;
    use WorkerBatchExportTrait;
}
