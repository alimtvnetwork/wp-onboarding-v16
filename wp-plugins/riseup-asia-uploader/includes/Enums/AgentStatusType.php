<?php
/**
 * AgentStatusType — Agent connection status values.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Agent connection status values.
 */
enum AgentStatusType: string
{
    case Pending   = 'Pending';
    case Connected = 'Connected';
    case Error     = 'Error';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    public function isPending(): bool   { return $this->isEqual(self::Pending); }
    public function isConnected(): bool { return $this->isEqual(self::Connected); }
    public function isError(): bool     { return $this->isEqual(self::Error); }
}
