<?php
/**
 * Admin Menu & Settings Trait — Shell delegating to AdminMenuTrait and AdminSettingsTrait.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait AdminMenuSettingsTrait {
    use AdminMenuTrait;
    use AdminSettingsTrait;
}
