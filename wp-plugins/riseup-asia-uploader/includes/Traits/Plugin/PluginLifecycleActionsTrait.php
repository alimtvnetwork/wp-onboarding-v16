<?php
/**
 * PluginLifecycleActionsTrait — Shell trait for enable, disable, delete plugin handlers.
 *
 * Logic delegated to PluginLifecycleEnableTrait and PluginLifecycleDeleteTrait.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/PluginLifecycleEnableTrait.php';
require_once __DIR__ . '/PluginLifecycleDeleteTrait.php';

trait PluginLifecycleActionsTrait {
    use PluginLifecycleEnableTrait;
    use PluginLifecycleDeleteTrait;
}
