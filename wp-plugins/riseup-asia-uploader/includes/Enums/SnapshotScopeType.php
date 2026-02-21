<?php
/**
 * SnapshotScopeType — Snapshot scope values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum SnapshotScopeType: string
{
    case All       = 'All';
    case WordPress = 'WordPress';
    case Content   = 'Content';
    case Custom    = 'Custom';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isAll(): bool       { return $this->isEqual(self::All); }
    public function isWordPress(): bool { return $this->isEqual(self::WordPress); }
    public function isContent(): bool   { return $this->isEqual(self::Content); }
    public function isCustom(): bool    { return $this->isEqual(self::Custom); }
}
