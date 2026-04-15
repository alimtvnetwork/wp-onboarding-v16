<?php
/**
 * RouteRegistrationTrait — Shell orchestrator composing route registration sub-traits.
 *
 * Contains registerRoutes and delegates domain-specific registrars to sub-traits.
 * Plugin-specific routes (plugins, agents, snapshots) live in PluginRoutesTrait.
 *
 * @package RiseupAsia\Traits\Route
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Route;

if (!defined('ABSPATH')) {
    exit;
}

trait RouteRegistrationTrait
{
    use RouteRegistrationCoreTrait;
    use RouteRegistrationCloudStorageTrait;
    use RouteRegistrationLogTrait;
    use RouteRegistrationUserSettingsTrait;
}
