<?php
/**
 * PluginConfigType — Plugin identity and configuration constants.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PluginConfigType: string
{
    case Slug            = 'riseup-asia-uploader';
    case ShortName       = 'RiseupAsia';
    case Name            = 'Riseup Asia Uploader';
    case Version         = '1.63.0';
    case MinWpVersion    = '5.6';
    case MinPhpVersion   = '8.2';
    case UploadsSubdir   = 'riseup-asia-uploader';
    case ApiNamespace    = 'riseup-asia-uploader';
    case ApiVersion      = 'v1';
    case LegacyNamespace = 'riseup-uploader/v1';
    case LogPrefix       = '[Riseup Asia]';
    case IgnoreFilename  = '.uploadignore';
    case SettingsGroup   = 'riseup_asia_settings_group';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public static function apiFullNamespace(): string
    {
        return self::ApiNamespace->value . '/' . self::ApiVersion->value;
    }
}
