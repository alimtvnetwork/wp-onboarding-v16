<?php
/**
 * BackupStatusType — Plugin backup lifecycle statuses.
 *
 * @package RiseupAsia\Enums
 * @since   1.64.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum BackupStatusType: string
{
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Complete   = 'complete';
    case Failed     = 'failed';
    case Restored   = 'restored';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isTerminal(): bool
    {
        return $this->isAnyOf(self::Complete, self::Failed, self::Restored);
    }
}
