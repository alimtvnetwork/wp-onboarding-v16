<?php
/**
 * LogCategoryType — Transaction log category identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.2.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum LogCategoryType: string
{
    case Snapshot = 'Snapshot';
    case Agent    = 'Agent';
    case Sync     = 'Sync';
    case Plugin   = 'Plugin';
    case Update   = 'Update';
    case Post     = 'Post';
    case Media    = 'Media';
    case Auth     = 'Auth';
    case Export       = 'Export';
    case CloudStorage = 'CloudStorage';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
