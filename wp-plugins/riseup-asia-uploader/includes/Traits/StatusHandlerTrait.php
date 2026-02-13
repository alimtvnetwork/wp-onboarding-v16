<?php
/**
 * StatusHandlerTrait — Status, OpenAPI, and OPcache reset handlers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load sub-traits
require_once __DIR__ . '/StatusPayloadTrait.php';
require_once __DIR__ . '/StatusOpsTrait.php';

trait StatusHandlerTrait
{
    use StatusPayloadTrait;
    use StatusOpsTrait;
}
