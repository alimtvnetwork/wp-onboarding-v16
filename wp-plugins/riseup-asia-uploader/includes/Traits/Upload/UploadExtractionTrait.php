<?php
/**
 * UploadExtractionTrait — ZIP validation, extraction, activation, and version detection.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

trait UploadExtractionTrait
{
    use UploadZipTrait;
    use UploadInstallTrait;
}