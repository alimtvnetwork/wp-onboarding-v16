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
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
