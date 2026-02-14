<?php
/**
 * ResponseMessageType — Standardized API Response Messages
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Human-readable response messages for REST API and AJAX responses.
 */
enum ResponseMessageType: string
{
    case Success            = 'Operation completed successfully';
    case Unauthorized       = 'Authentication required';
    case Forbidden          = 'Insufficient permissions';
    case InvalidRequest     = 'Invalid request data';
    case PluginNotFound     = 'Plugin not found';
    case UploadFailed       = 'Upload failed';
    case ActivationFailed   = 'Plugin activation failed';
    case DeactivationFailed = 'Plugin deactivation failed';
    case DeleteFailed       = 'Plugin deletion failed';
    case PostCreateFailed   = 'Post creation failed';
    case PostUpdateFailed   = 'Post update failed';
    case CategoryCreateFailed = 'Category creation failed';
    case MediaUploadFailed  = 'Media upload failed';
    case DbError            = 'Database error';
    case FileIgnored        = 'File ignored by .uploadignore';
    case InvalidRequestBody = 'Invalid request body';
    case ServiceNotAvailable = 'Service not available';
    case InvalidId          = 'Invalid ID';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this is an error/failure message. */
    public function isFailure(): bool
    {
        return $this !== self::Success && $this !== self::FileIgnored;
    }
}
