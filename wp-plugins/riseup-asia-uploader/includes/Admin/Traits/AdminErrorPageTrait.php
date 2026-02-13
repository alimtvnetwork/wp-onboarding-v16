<?php
/**
 * Admin Error Page Trait — Shell delegating to AdminErrorRenderTrait and AdminErrorStateTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AdminErrorRenderTrait.php';
require_once __DIR__ . '/AdminErrorStateTrait.php';

trait AdminErrorPageTrait {
    use AdminErrorRenderTrait;
    use AdminErrorStateTrait;
}
