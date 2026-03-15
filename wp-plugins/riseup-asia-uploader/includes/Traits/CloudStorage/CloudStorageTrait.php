<?php
/**
 * CloudStorageTrait — Shell trait composing all cloud storage sub-traits.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.15.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

trait CloudStorageTrait {
    use CloudStorageEncryptionTrait;
    use CloudStorageAccountCrudTrait;
    use CloudStorageSettingsTrait;
    use CloudStorageGitHubTrait;
    use CloudStorageUploadTrait;
    use CloudStorageFileTrait;
}
