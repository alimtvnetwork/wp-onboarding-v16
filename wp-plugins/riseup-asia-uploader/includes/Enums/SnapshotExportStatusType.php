<?php
/**
 * SnapshotExportStatusType — Snapshot export status values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SnapshotExportStatusType: string
{
    case Valid    = 'Valid';
    case Expired  = 'Expired';
    case Building = 'Building';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isValid(): bool    { return $this->isEqual(self::Valid); }
    public function isExpired(): bool  { return $this->isEqual(self::Expired); }
    public function isBuilding(): bool { return $this->isEqual(self::Building); }
}
