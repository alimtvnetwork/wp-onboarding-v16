<?php
/**
 * Riseup Asia Uploader - Path Helper
 *
 * @package RiseupAsia\Helpers
 * @since   1.9.0
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Helpers\Traits\PathHelperCoreTrait;
use RiseupAsia\Helpers\Traits\PathHelperDirTrait;
use RiseupAsia\Helpers\Traits\PathHelperFileTrait;

class PathHelper {

    use PathHelperCoreTrait;
    use PathHelperDirTrait;
    use PathHelperFileTrait;
}
