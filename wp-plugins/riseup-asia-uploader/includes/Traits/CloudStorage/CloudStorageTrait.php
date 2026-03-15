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
    use CloudStorageGitLabTrait;
    use CloudStorageUploadTrait;
    use CloudStorageFileTrait;

    /** POST /cloud-storage/oauth/initiate — Stub for Phase 3 (Google Drive). */
    public function handleCloudStorageOAuthInitiate(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(array(
            'Success' => false,
            'Error'   => 'OAuth flow not yet implemented. Google Drive support coming in Phase 3.',
        ), 501);
    }

    /** GET /cloud-storage/oauth/callback — Stub for Phase 3 (Google Drive). */
    public function handleCloudStorageOAuthCallback(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(array(
            'Success' => false,
            'Error'   => 'OAuth callback not yet implemented. Google Drive support coming in Phase 3.',
        ), 501);
    }
}
