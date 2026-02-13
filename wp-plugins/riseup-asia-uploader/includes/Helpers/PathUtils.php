<?php
/**
 * Riseup Asia Uploader - Path Utilities
 *
 * Shell class delegating to PathUtilsCoreTrait, PathUtilsDirTrait, PathUtilsFileTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Traits/PathUtilsCoreTrait.php';
require_once __DIR__ . '/Traits/PathUtilsDirTrait.php';
require_once __DIR__ . '/Traits/PathUtilsFileTrait.php';

/**
 * Path utility class for safe path operations.
 */
class RiseupPathUtils {

    use PathUtilsCoreTrait;
    use PathUtilsDirTrait;
    use PathUtilsFileTrait;
}
