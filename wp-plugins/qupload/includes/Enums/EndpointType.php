<?php
/**
 * EndpointType — REST API endpoint path fragments.
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum EndpointType: string
{
    case Status       = 'status';
    case Upload       = 'upload';
    case Activate     = 'activate';
    case Deactivate   = 'deactivate';
    case Plugins      = 'plugins';
    case LogsStatus         = 'logs/status';
    case LogsRotationStatus = 'logs/rotation-status';
    case LogsClear          = 'logs/clear';
    case LogsConfirm        = 'logs/clear/confirm';
    case LogsEmail          = 'logs/email';
    case LogsRetrieve       = 'logs/retrieve';
    case MachinesApprove = 'machines/approve';
    case DebugRoutes     = 'debug/routes';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    /** Prefixes value with '/' for register_rest_route(). */
    public function route(): string
    {
        return '/' . $this->value;
    }
}
