<?php
/**
 * PluginRoutesTrait — Plugin, agent, and snapshot route registration.
 *
 * Shell trait delegating to PluginRouteRegistrationTrait and SnapshotRouteRegistrationTrait.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/PluginRouteRegistrationTrait.php';
require_once __DIR__ . '/SnapshotRouteRegistrationTrait.php';

trait PluginRoutesTrait
{
    use PluginRouteRegistrationTrait;
    use SnapshotRouteRegistrationTrait;
}
