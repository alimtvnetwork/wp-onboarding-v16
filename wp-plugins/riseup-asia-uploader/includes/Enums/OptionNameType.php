<?php
/**
 * OptionNameType — WordPress option name keys.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum OptionNameType: string
{
    case SnapshotSettings   = 'RiseupSnapshotSettings';
    case LogRetrieval       = 'RiseupLogRetrievalSettings';
    case UpdateSettings     = 'RiseupUpdateSettings';
    case PluginSettings     = 'RiseupAsiaSettings';
    case ErrorNotification  = 'RiseupErrorNotificationSettings';

    /** WordPress core — value must remain snake_case. */
    case ActivePlugins      = 'active_plugins';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
