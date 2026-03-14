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
    case LogsStatus   = 'logs/status';
    case LogsClear    = 'logs/clear';
    case LogsConfirm  = 'logs/clear/confirm';
    case LogsEmail    = 'logs/email';

    /** Prefixes value with '/' for register_rest_route(). */
    public function route(): string
    {
        return '/' . $this->value;
    }
}
