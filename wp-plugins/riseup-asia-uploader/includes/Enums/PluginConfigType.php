<?php
/**
 * PluginConfigType — Plugin identity and configuration constants.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Plugin identity and configuration constants.
 */
enum PluginConfigType: string
{
    case Slug            = 'riseup-asia-uploader';
    case Name            = 'Riseup Asia Uploader';
    case Version         = '1.59.0';
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

    /** Get the full REST API namespace (e.g., 'riseup-asia-uploader/v1'). */
    public static function apiFullNamespace(): string
    {
        return self::ApiNamespace->value . '/' . self::ApiVersion->value;
    }
}
