<?php
/**
 * UploadExtractionTrait — ZIP validation, extraction, activation, and version detection.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/UploadZipTrait.php';
require_once dirname(__FILE__) . '/UploadInstallTrait.php';

trait UploadExtractionTrait
{
    use UploadZipTrait;
    use UploadInstallTrait;
}
