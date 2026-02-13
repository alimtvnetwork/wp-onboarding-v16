<?php
/**
 * Database Query Trait — Shell.
 *
 * Transaction logging, querying, filtering, statistics, and cleanup.
 * Logic delegated to sub-traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DatabaseQueryLogTrait.php';
require_once __DIR__ . '/DatabaseQuerySearchTrait.php';

trait DatabaseQueryTrait {
    use DatabaseQueryLogTrait;
    use DatabaseQuerySearchTrait;
}
