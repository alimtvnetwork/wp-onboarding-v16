<?php
/**
 * WpErrorCodeType — WordPress error code identifiers.
 *
 * @package QUpload\Enums
 * @since   1.2.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum WpErrorCodeType: string
{
    /** WordPress core error codes — values must match WP conventions. */
    case RestForbidden = 'rest_forbidden';

    /** Custom plugin error codes — PascalCase values per enum standard. */
    case InternalError = 'InternalError';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
