<?php
/**
 * Database Migrations Early Trait — Shell.
 *
 * Schema migrations v1 through v5.
 * Logic delegated to sub-traits.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseMigrationsEarlyTrait {
    use DatabaseMigrationsV1V3Trait;
    use DatabaseMigrationsV4V5Trait;
}
