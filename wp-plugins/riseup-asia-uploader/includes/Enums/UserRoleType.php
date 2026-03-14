<?php
/**
 * UserRoleType — WordPress user role slugs.
 *
 * @package RiseupAsia\Enums
 * @since   2.13.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum UserRoleType: string
{
    case Administrator = 'administrator';
    case Editor        = 'editor';
    case Author        = 'author';
    case Contributor   = 'contributor';
    case Subscriber    = 'subscriber';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public static function isValidSlug(string $slug): bool
    {
        $matched = self::tryFrom($slug);

        return $matched !== null;
    }

    /** @return string[] */
    public static function allSlugs(): array
    {
        return array_map(fn(self $r) => $r->value, self::cases());
    }
}
