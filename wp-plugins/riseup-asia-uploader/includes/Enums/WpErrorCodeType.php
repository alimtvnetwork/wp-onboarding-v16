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

    /** Custom plugin error codes — PascalCase values per enum standard. */
    case DatabaseError     = 'DbError';
    case NoData            = 'NoData';
    case MissingFields     = 'MissingFields';
    case NotFound          = 'NotFound';
    case ApiError          = 'ApiError';
    case MaxRedirects      = 'MaxRedirects';
    case NoLocation        = 'NoLocation';
    case NoMasterUrl       = 'NoMasterUrl';
    case Disabled          = 'Disabled';
    case HttpError         = 'HttpError';
    case InternalError     = 'InternalError';
    case FileNotFound      = 'FileNotFound';
    case ChecksumMismatch  = 'ChecksumMismatch';
    case BackupFailed      = 'BackupFailed';
    case RollbackFailed    = 'RollbackFailed';

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
