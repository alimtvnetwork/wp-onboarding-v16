<?php
/**
 * WpErrorCodeType — Standardized WP_Error code values.
 *
 * Backed enum replacing hardcoded error code strings in WP_Error constructors.
 *
 * @package RiseupAsia\Enums
 * @since   2.2.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress error code identifiers.
 */
enum WpErrorCodeType: string
{
    case RestForbidden = 'rest_forbidden';
    case RestDisabled  = 'rest_disabled';
    case DatabaseError = 'db_error';
    case NoData        = 'no_data';
    case MissingFields = 'missing_fields';
    case NotFound      = 'not_found';
    case ApiError      = 'api_error';
    case MaxRedirects  = 'max_redirects';
    case NoLocation    = 'no_location';
    case NoMasterUrl   = 'no_master_url';
    case Disabled      = 'disabled';
    case HttpError     = 'http_error';
    case InternalError    = 'internal_error';
    case FileNotFound     = 'file_not_found';
    case ChecksumMismatch = 'checksum_mismatch';
    case BackupFailed     = 'backup_failed';
    case RollbackFailed   = 'rollback_failed';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool
    {
        return $this !== $other;
    }

    /** Check if this code represents an authentication/authorization error. */
    public function isAuthError(): bool
    {
        return $this->isEqual(self::RestForbidden) || $this->isEqual(self::RestDisabled);
    }

    /** Check if this code represents a database error. */
    public function isDatabaseError(): bool
    {
        return $this->isEqual(self::DatabaseError);
    }

    /** Check if this code represents a validation error. */
    public function isValidationError(): bool
    {
        return $this->isEqual(self::MissingFields) || $this->isEqual(self::NoData);
    }

    /** Check if this code represents a network/HTTP error. */
    public function isNetworkError(): bool
    {
        return $this->isEqual(self::ApiError) || $this->isEqual(self::HttpError)
            || $this->isEqual(self::MaxRedirects) || $this->isEqual(self::NoLocation);
    }
}
