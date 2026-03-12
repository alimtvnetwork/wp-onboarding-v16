<?php
/**
 * PluginConfigType — Plugin identity and configuration constants.
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PluginConfigType: string
{
    case Slug          = 'qupload';
    case ShortName     = 'QUpload';
    case Name          = 'Quick Upload';
    case Version       = '2.2.0';
    case MinWpVersion  = '5.6';
    case MinPhpVersion = '8.1';
    case ApiNamespace  = 'qupload-api';
    case ApiVersion    = 'v1';
    case LogPrefix      = '[QUpload]';
    case SettingsGroup  = 'qupload_settings';

    public static function uploadsSubdir(): string
    {
        return self::Slug->value;
    }

    public static function apiFullNamespace(): string
    {
        return self::ApiNamespace->value . '/' . self::ApiVersion->value;
    }
}
