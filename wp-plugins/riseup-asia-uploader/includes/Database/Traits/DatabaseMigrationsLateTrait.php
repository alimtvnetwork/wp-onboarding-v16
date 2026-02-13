<?php
/**
 * Database Migrations Late Trait — Shell.
 *
 * Schema migrations v6 through v11.
 * Logic delegated to sub-traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DatabaseMigrationsV6V8Trait.php';
require_once __DIR__ . '/DatabaseMigrationsV9V11Trait.php';

trait DatabaseMigrationsLateTrait {
    use DatabaseMigrationsV6V8Trait;
    use DatabaseMigrationsV9V11Trait;
}
