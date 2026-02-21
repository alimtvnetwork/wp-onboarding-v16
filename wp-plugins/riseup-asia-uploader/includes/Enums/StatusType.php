<?php
/**
 * StatusType — Transaction result status.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum StatusType: string
{
    case Success = 'Success';
    case Failed  = 'Failed';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isSuccess(): bool { return $this->isEqual(self::Success); }
    public function isFailed(): bool  { return $this->isEqual(self::Failed); }
}
