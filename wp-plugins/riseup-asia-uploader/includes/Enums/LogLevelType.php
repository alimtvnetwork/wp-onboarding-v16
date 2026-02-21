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
    case Debug = 'Debug';
    case Info  = 'Info';
    case Warn  = 'Warn';
    case Error = 'Error';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool
    {
        return $this !== $other;
    }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
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
        return $this->isAnyOf(self::Error, self::Warn);
    }
}
