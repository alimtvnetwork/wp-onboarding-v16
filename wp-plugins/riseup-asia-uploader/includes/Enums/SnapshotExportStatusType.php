<?php
/**
 * SnapshotExportStatusType — Snapshot export status values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot export status values.
 */
enum SnapshotExportStatusType: string
{
    case Valid    = 'valid';
    case Expired  = 'expired';
    case Building = 'building';

    public function isEqual(self $other): bool { return $this === $other; }

    public function isValid(): bool    { return $this->isEqual(self::Valid); }
    public function isExpired(): bool  { return $this->isEqual(self::Expired); }
    public function isBuilding(): bool { return $this->isEqual(self::Building); }
}
