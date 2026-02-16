<?php
/**
 * StatusHandlerTrait — Status, OpenAPI, and OPcache reset handlers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits\Status
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Status;

if (!defined('ABSPATH')) {
    exit;
}

trait StatusHandlerTrait
{
    use StatusPayloadTrait;
    use StatusOpsTrait;
}