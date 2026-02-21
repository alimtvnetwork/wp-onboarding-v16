<?php
/**
 * PostStatusType — WordPress post publication statuses.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PostStatusType: string
{
    case Publish = 'publish';
    case Draft   = 'draft';
    case Pending = 'pending';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isPublic(): bool { return $this->isEqual(self::Publish); }

    public static function validValues(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
