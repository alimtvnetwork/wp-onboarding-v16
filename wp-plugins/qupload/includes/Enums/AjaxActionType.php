<?php
/**
 * AjaxActionType — AJAX action identifiers.
 *
 * @package QUpload\Enums
 * @since   2.1.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum AjaxActionType: string
{
    case ReadLogFile  = 'qupload_read_log_file';
    case ClearLogFile = 'qupload_clear_log_file';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
