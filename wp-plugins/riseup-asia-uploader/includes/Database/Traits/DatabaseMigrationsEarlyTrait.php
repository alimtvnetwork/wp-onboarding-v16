<?php
/**
 * Database Migrations Early Trait — Shell.
 *
 * Schema migrations v1 through v5.
 * Logic delegated to sub-traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DatabaseMigrationsV1V3Trait.php';
require_once __DIR__ . '/DatabaseMigrationsV4V5Trait.php';

trait DatabaseMigrationsEarlyTrait {
    use DatabaseMigrationsV1V3Trait;
    use DatabaseMigrationsV4V5Trait;
}
