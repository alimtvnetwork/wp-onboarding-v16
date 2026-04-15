<?php
/**
 * UploadInstallExtractTrait — Shell orchestrator composing extraction sub-traits.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

trait UploadInstallExtractTrait
{
    use UploadInstallExtractCoreTrait;
    use UploadInstallExtractRollbackTrait;
    use UploadInstallExtractZipTrait;
}
