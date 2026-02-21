<?php
/**
 * LogColumnType — PascalCase database column names for the Logs table.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum LogColumnType: string
{
    case Id            = 'Id';
    case Action        = 'Action';
    case PluginSlug    = 'PluginSlug';
    case PluginFile    = 'PluginFile';
    case PluginVersion = 'PluginVersion';
    case PostId        = 'PostId';
    case Status        = 'Status';
    case Details       = 'Details';
    case ErrorMsg      = 'ErrorMsg';
    case UserLogin     = 'UserLogin';
    case UserId        = 'UserId';
    case IpAddress     = 'IpAddress';
    case TriggeredBy   = 'TriggeredBy';
    case UploadSource  = 'UploadSource';
    case SourceMachine = 'SourceMachine';
    case CreatedAt     = 'CreatedAt';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
