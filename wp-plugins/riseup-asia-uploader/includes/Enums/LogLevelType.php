<?php
/**
 * LogLevelType — Log Level Enum
 *
 * Defines the severity levels for file and transaction logging.
 * Includes encapsulated helper methods for level checking.
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

    /** Check if this level is Error. */
    public function isError(): bool
    {
        return $this === self::Error;
    }

    /** Check if this level is Warn. */
    public function isWarn(): bool
    {
        return $this === self::Warn;
    }

    /** Check if this level is Info. */
    public function isInfo(): bool
    {
        return $this === self::Info;
    }

    /** Check if this level is Debug. */
    public function isDebug(): bool
    {
        return $this === self::Debug;
    }

    /** Check if this level is Error or Warn. */
    public function isErrorOrWarn(): bool
    {
        return $this === self::Error || $this === self::Warn;
    }
}
