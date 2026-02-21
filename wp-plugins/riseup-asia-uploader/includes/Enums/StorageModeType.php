<?php
/**
 * StorageModeType — Snapshot storage mode values.
 *
 * @package RiseupAsia\Enums
 * @since   2.2.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum StorageModeType: string
{
    case PerTable = 'PerTable';
    case Single   = 'Single';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isPerTable(): bool { return $this->isEqual(self::PerTable); }
    public function isSingle(): bool   { return $this->isEqual(self::Single); }

    public static function validValues(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
