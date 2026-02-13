<?php
/**
 * UploadInstallTrait — Shell delegating to UploadInstallExtractTrait and UploadInstallActivateTrait.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/UploadInstallExtractTrait.php';
require_once __DIR__ . '/UploadInstallActivateTrait.php';

trait UploadInstallTrait
{
    use UploadInstallExtractTrait;
    use UploadInstallActivateTrait;
}
