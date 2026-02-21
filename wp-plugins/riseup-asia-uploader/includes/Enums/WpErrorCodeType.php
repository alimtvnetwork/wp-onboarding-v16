<?php
/**
 * WpErrorCodeType — WordPress error code identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.2.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum WpErrorCodeType: string
{
    /** WordPress core error codes — values must match WP conventions. */
    case RestForbidden = 'rest_forbidden';
    case RestDisabled  = 'rest_disabled';

    /** Custom plugin error codes. */
    case DatabaseError     = 'db_error';
    case NoData            = 'no_data';
    case MissingFields     = 'missing_fields';
    case NotFound          = 'not_found';
    case ApiError          = 'api_error';
    case MaxRedirects      = 'max_redirects';
    case NoLocation        = 'no_location';
    case NoMasterUrl       = 'no_master_url';
    case Disabled          = 'disabled';
    case HttpError         = 'http_error';
    case InternalError     = 'internal_error';
    case FileNotFound      = 'file_not_found';
    case ChecksumMismatch  = 'checksum_mismatch';
    case BackupFailed      = 'backup_failed';
    case RollbackFailed    = 'rollback_failed';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isAuthError(): bool
    {
        return $this->isAnyOf(self::RestForbidden, self::RestDisabled);
    }

    public function isDatabaseError(): bool { return $this->isEqual(self::DatabaseError); }

    public function isValidationError(): bool
    {
        return $this->isAnyOf(self::MissingFields, self::NoData);
    }

    public function isNetworkError(): bool
    {
        return $this->isAnyOf(self::ApiError, self::HttpError, self::MaxRedirects, self::NoLocation);
    }
}
