<?php
/**
 * Admin AJAX Trait — Shell delegating to AdminAjaxUpdateTrait and AdminAjaxSnapshotTrait.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait AdminAjaxTrait {
    use AdminAjaxUpdateTrait;
    use AdminAjaxSnapshotTrait;
}
