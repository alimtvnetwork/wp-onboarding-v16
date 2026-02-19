<?php
/**
 * SnapshotScopeType — Snapshot scope values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot scope values.
 */
enum SnapshotScopeType: string
{
    case All       = 'all';
    case WordPress = 'wordpress';
    case Content   = 'content';
    case Custom    = 'custom';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    public function isAll(): bool       { return $this->isEqual(self::All); }
    public function isWordPress(): bool { return $this->isEqual(self::WordPress); }
    public function isContent(): bool   { return $this->isEqual(self::Content); }
    public function isCustom(): bool    { return $this->isEqual(self::Custom); }
}
