<?php
/**
 * SyncHandlerTrait — Delta file sync manifest and push handlers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits\Sync
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Sync;

if (!defined('ABSPATH')) {
    exit;
}

trait SyncHandlerTrait
{
    use SyncManifestTrait;
    use SyncPushTrait;
}