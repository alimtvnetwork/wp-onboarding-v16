<?php
/**
 * PluginRoutesTrait — Plugin, agent, and snapshot route registration.
 *
 * Shell trait delegating to PluginRouteRegistrationTrait and SnapshotRouteRegistrationTrait.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Traits\Snapshot\SnapshotRouteRegistrationTrait;

trait PluginRoutesTrait
{
    use PluginRouteRegistrationTrait;
    use SnapshotRouteRegistrationTrait;
}