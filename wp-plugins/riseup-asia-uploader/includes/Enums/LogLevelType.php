<?php
/**
 * LogLevelType — Log Level Enum
 *
 * Defines the severity levels for file and transaction logging.
 *
 * @package RiseupAsia\Enums
 * @since   1.57.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log severity levels.
 */
enum LogLevelType: string
{
    case Debug = 'DEBUG';
    case Info  = 'INFO';
    case Warn  = 'WARN';
    case Error = 'ERROR';
}
