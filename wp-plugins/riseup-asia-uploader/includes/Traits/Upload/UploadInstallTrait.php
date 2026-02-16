<?php
/**
 * UploadInstallTrait — Shell delegating to UploadInstallExtractTrait and UploadInstallActivateTrait.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

trait UploadInstallTrait
{
    use UploadInstallExtractTrait;
    use UploadInstallActivateTrait;
}