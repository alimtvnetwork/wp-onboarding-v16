<?php
/**
 * ErrorLogTrait — error log and error session retrieval handlers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits\Error
 */

namespace RiseupAsia\Traits\Error;

if (!defined('ABSPATH')) {
    exit;
}

trait ErrorLogTrait {
    use ErrorLogHandlerTrait;
    use ErrorSessionHandlerTrait;
}