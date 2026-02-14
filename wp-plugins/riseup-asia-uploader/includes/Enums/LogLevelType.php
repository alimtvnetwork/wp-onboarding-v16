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

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this level is Error. */
    public function isError(): bool
    {
        return $this->isEqual(self::Error);
    }

    /** Check if this level is Warn. */
    public function isWarn(): bool
    {
        return $this->isEqual(self::Warn);
    }

    /** Check if this level is Info. */
    public function isInfo(): bool
    {
        return $this->isEqual(self::Info);
    }

    /** Check if this level is Debug. */
    public function isDebug(): bool
    {
        return $this->isEqual(self::Debug);
    }

    /** Check if this level is Error or Warn. */
    public function isErrorOrWarn(): bool
    {
        return $this->isEqual(self::Error) || $this->isEqual(self::Warn);
    }
}
