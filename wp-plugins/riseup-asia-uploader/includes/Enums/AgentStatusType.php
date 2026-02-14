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
    case Pending   = 'pending';
    case Connected = 'connected';
    case Error     = 'error';

    public function isEqual(self $other): bool { return $this === $other; }

    public function isPending(): bool   { return $this->isEqual(self::Pending); }
    public function isConnected(): bool { return $this->isEqual(self::Connected); }
    public function isError(): bool     { return $this->isEqual(self::Error); }
}
