<?php
/**
 * Admin Error Page Trait — Shell delegating to AdminErrorRenderTrait and AdminErrorStateTrait.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait AdminErrorPageTrait {
    use AdminErrorRenderTrait;
    use AdminErrorStateTrait;
}
