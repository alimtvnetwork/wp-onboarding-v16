<?php
/**
 * Database Query Trait — Shell.
 *
 * Transaction logging, querying, filtering, statistics, and cleanup.
 * Logic delegated to sub-traits.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseQueryTrait {
    use DatabaseQueryLogTrait;
    use DatabaseQuerySearchTrait;
}
