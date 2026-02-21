<?php
/**
 * TriggerSourceType — Transaction trigger source identifiers.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum TriggerSourceType: string
{
    case Api       = 'Api';
    case Dashboard = 'Dashboard';
    case Agent     = 'AgentPush';
    case Cron      = 'Cron';
    case Cli       = 'Cli';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isApi(): bool       { return $this->isEqual(self::Api); }
    public function isDashboard(): bool { return $this->isEqual(self::Dashboard); }
    public function isAgent(): bool     { return $this->isEqual(self::Agent); }
    public function isCron(): bool      { return $this->isEqual(self::Cron); }
    public function isCli(): bool       { return $this->isEqual(self::Cli); }
}
