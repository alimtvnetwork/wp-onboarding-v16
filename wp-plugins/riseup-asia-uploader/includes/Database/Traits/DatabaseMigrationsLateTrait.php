<?php
/**
 * Database Migrations Late Trait — Shell.
 *
 * Schema migrations v6 through v11.
 * Logic delegated to sub-traits.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait DatabaseMigrationsLateTrait {
    use DatabaseMigrationsV6V8Trait;
    use DatabaseMigrationsV9V11Trait;
    use DatabaseMigrationsV12Trait;
}
