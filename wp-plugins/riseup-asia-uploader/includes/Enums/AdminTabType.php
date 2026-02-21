<?php
/**
 * AdminTabType — Admin page tab identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.5.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum AdminTabType: string
{
    case Sessions   = 'sessions';
    case Log        = 'log';
    case Error      = 'error';
    case Stacktrace = 'stacktrace';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isLogFileTab(): bool
    {
        return $this->isAnyOf(self::Log, self::Error, self::Stacktrace);
    }
}
