<?php
/**
 * AgentFieldType — Standardized agent data array keys.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keys used in the agent $data array passed between REST handlers and the manager.
 */
enum AgentFieldType: string
{
    case Name        = 'name';
    case Url         = 'url';
    case Username    = 'username';
    case AppPassword = 'appPassword';
    case RedirectUrl = 'redirectUrl';
    case Status      = 'status';
    case LastSync    = 'lastSync';
    case LastError   = 'lastError';
    case CreatedAt   = 'createdAt';
    case UpdatedAt   = 'updatedAt';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
