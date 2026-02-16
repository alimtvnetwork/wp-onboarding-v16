<?php
/**
 * PluginLifecycleActionsTrait — Shell trait for enable, disable, delete plugin handlers.
 *
 * Logic delegated to PluginLifecycleEnableTrait and PluginLifecycleDeleteTrait.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

trait PluginLifecycleActionsTrait {
    use PluginLifecycleEnableTrait;
    use PluginLifecycleDeleteTrait;
}