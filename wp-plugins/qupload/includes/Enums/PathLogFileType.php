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

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
