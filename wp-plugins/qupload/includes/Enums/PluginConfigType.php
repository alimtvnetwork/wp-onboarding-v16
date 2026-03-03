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
    case Name          = 'Quick Upload';
    case Version       = '1.1.0';
    case MinWpVersion  = '5.6';
    case MinPhpVersion = '8.1';
    case UploadsSubdir = 'qupload';
    case ApiNamespace  = 'qupload';
    case ApiVersion    = 'v1';
    case LogPrefix     = '[QUpload]';

    public static function apiFullNamespace(): string
    {
        return self::ApiNamespace->value . '/' . self::ApiVersion->value;
    }
}
