<?php
/**
 * Admin AJAX Trait — Shell delegating to AdminAjaxUpdateTrait and AdminAjaxSnapshotTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AdminAjaxUpdateTrait.php';
require_once __DIR__ . '/AdminAjaxSnapshotTrait.php';

trait AdminAjaxTrait {
    use AdminAjaxUpdateTrait;
    use AdminAjaxSnapshotTrait;
}
