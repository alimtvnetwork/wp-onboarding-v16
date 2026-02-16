<?php
/**
 * Riseup Asia Uploader - Path Utilities
 *
 * @package RiseupAsia\Helpers
 * @since   1.9.0
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Helpers\Traits\PathUtilsCoreTrait;
use RiseupAsia\Helpers\Traits\PathUtilsDirTrait;
use RiseupAsia\Helpers\Traits\PathUtilsFileTrait;

class PathUtils {

    use PathUtilsCoreTrait;
    use PathUtilsDirTrait;
    use PathUtilsFileTrait;
}
