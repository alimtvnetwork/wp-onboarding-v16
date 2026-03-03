<?php
/**
 * LicenseStatusType — License validation status values.
 *
 * @package RiseupAsia\Enums
 * @since   2.7.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum LicenseStatusType: string
{
    case Active    = 'active';
    case Expired   = 'expired';
    case Suspended = 'suspended';
    case Revoked   = 'revoked';
    case NotFound  = 'not_found';
    case Unknown   = 'unknown';

    public function isActive(): bool { return $this === self::Active; }
    public function isExpired(): bool { return $this === self::Expired; }
    public function isSuspended(): bool { return $this === self::Suspended; }
    public function isRevoked(): bool { return $this === self::Revoked; }
    public function isUsable(): bool { return $this === self::Active; }

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
