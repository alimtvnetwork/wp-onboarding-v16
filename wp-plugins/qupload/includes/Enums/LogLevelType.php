<?php
/**
 * LogLevelType — Log severity levels.
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum LogLevelType: string
{
    case Debug = 'Debug';
    case Info  = 'Info';
    case Warn  = 'Warn';
    case Error = 'Error';

    public function isError(): bool { return $this === self::Error; }
}
