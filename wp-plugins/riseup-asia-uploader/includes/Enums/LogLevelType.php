<?php
/**
 * LogLevelType — Log severity levels.
 *
 * @package RiseupAsia\Enums
 * @since   1.57.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum LogLevelType: string
{
    case Debug = 'Debug';
    case Info  = 'Info';
    case Warn  = 'Warn';
    case Error = 'Error';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isError(): bool      { return $this->isEqual(self::Error); }
    public function isWarn(): bool       { return $this->isEqual(self::Warn); }
    public function isInfo(): bool       { return $this->isEqual(self::Info); }
    public function isDebug(): bool      { return $this->isEqual(self::Debug); }
    public function isErrorOrWarn(): bool { return $this->isAnyOf(self::Error, self::Warn); }
}
