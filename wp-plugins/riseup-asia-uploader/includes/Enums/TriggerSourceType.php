<?php
/**
 * TriggerSourceType — Transaction trigger source identifiers.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Transaction trigger source identifiers.
 */
enum TriggerSourceType: string
{
    case Api       = 'api';
    case Dashboard = 'dashboard';
    case Agent     = 'agent_push';
    case Cron      = 'cron';
    case Cli       = 'cli';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    public function isApi(): bool       { return $this->isEqual(self::Api); }
    public function isDashboard(): bool { return $this->isEqual(self::Dashboard); }
    public function isAgent(): bool     { return $this->isEqual(self::Agent); }
    public function isCron(): bool      { return $this->isEqual(self::Cron); }
    public function isCli(): bool       { return $this->isEqual(self::Cli); }
}
