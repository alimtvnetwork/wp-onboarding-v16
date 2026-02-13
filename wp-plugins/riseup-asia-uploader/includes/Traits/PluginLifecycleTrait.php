<?php
/**
 * PluginLifecycleTrait — Plugin exists, enable, disable, delete handlers.
 *
 * Shell trait delegating to PluginLifecycleHelpersTrait and PluginLifecycleActionsTrait.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/PluginLifecycleHelpersTrait.php';
require_once __DIR__ . '/PluginLifecycleActionsTrait.php';

trait PluginLifecycleTrait
{
    use PluginLifecycleHelpersTrait;
    use PluginLifecycleActionsTrait;
}
