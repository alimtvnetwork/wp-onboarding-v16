<?php
/**
 * SnapshotTriggerType — Snapshot trigger source values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Snapshot trigger source values.
 */
enum SnapshotTriggerType: string
{
    case Manual = 'manual';
    case Cron   = 'cron';
    case Api    = 'api';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    public function isManual(): bool { return $this->isEqual(self::Manual); }
    public function isCron(): bool   { return $this->isEqual(self::Cron); }
    public function isApi(): bool    { return $this->isEqual(self::Api); }
}
