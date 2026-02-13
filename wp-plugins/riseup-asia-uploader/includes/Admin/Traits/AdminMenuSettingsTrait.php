<?php
/**
 * Admin Menu & Settings Trait — Shell delegating to AdminMenuTrait and AdminSettingsTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AdminMenuTrait.php';
require_once __DIR__ . '/AdminSettingsTrait.php';

trait AdminMenuSettingsTrait {
    use AdminMenuTrait;
    use AdminSettingsTrait;
}
