<?php
/**
 * AdminTabType — Tab identifiers for the error log page.
 *
 * @package QUpload\Enums
 * @since   2.1.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum AdminTabType: string
{
    case Log        = 'log';
    case Error      = 'error';
    case Stacktrace = 'stacktrace';
}
