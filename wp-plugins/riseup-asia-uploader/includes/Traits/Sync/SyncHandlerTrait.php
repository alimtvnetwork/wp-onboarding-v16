<?php
/**
 * SyncHandlerTrait — Delta file sync manifest and push handlers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/SyncManifestTrait.php';
require_once dirname(__FILE__) . '/SyncPushTrait.php';

trait SyncHandlerTrait
{
    use SyncManifestTrait;
    use SyncPushTrait;
}
