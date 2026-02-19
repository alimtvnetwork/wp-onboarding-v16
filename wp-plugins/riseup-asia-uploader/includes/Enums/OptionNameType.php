<?php
/**
 * OptionNameType — WordPress option name keys.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * WordPress option name keys used by the plugin.
 */
enum OptionNameType: string
{
    case SnapshotSettings   = 'riseup_snapshot_settings';
    case LogRetrieval       = 'riseup_log_retrieval_settings';
    case UpdateSettings     = 'riseup_update_settings';
    case PluginSettings     = 'riseup_asia_settings';
    case ErrorNotification  = 'riseup_error_notification_settings';
    case ActivePlugins      = 'active_plugins';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
}
