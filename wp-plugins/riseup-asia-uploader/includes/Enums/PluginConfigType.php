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
    case Version         = '2.0.3';
    case MinWpVersion    = '5.6';
    case MinPhpVersion   = '8.2';
    case ApiNamespace    = 'riseup-asia-api';
    case ApiVersion      = 'v1';
    case LegacyNamespace = 'riseup-uploader/v1';
    case LogPrefix       = '[Riseup Asia]';
    case IgnoreFilename  = '.uploadignore';
    case SettingsGroup   = 'riseup_asia_settings_group';

    public static function uploadsSubdir(): string
    {
        return self::Slug->value;
    }

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public static function apiFullNamespace(): string
    {
        return self::ApiNamespace->value . '/' . self::ApiVersion->value;
    }
}
