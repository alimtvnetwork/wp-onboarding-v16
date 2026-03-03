<?php
/**
 * PathLogFileType — Log file path fragments.
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PathLogFileType: string
{
    case Log        = '/log.txt';
    case Error      = '/error.txt';
    case Stacktrace = '/stacktrace.txt';
}
