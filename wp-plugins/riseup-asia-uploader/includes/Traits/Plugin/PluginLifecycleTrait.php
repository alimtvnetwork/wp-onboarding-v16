<?php
/**
 * PluginLifecycleTrait — Plugin exists, enable, disable, delete handlers.
 *
 * Shell trait delegating to PluginLifecycleHelpersTrait and PluginLifecycleActionsTrait.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

trait PluginLifecycleTrait
{
    use PluginLifecycleHelpersTrait;
    use PluginLifecycleActionsTrait;
}