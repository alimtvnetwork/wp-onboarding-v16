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

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isError(): bool { return $this->isEqual(self::Error); }
}
