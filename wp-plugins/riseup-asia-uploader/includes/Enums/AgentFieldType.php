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
    case AppPassword = 'app_password';
    case RedirectUrl = 'redirect_url';
    case Status      = 'status';
    case LastSync    = 'last_sync';
    case LastError   = 'last_error';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
}
