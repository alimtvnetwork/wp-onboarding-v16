<?php
/**
 * ResponseKeyType — Standardized response array keys (PascalCase values).
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum ResponseKeyType: string
{
    case Success       = 'Success';
    case Error         = 'Error';
    case Message       = 'Message';
    case Slug          = 'Slug';
    case PluginSlug    = 'PluginSlug';
    case IsUpdate      = 'IsUpdate';
    case Activated     = 'Activated';
    case Deactivated   = 'Deactivated';
    case PluginVersion = 'PluginVersion';
    case TempFile      = 'TempFile';
    case Version       = 'Version';
    case Plugin        = 'Plugin';
    case Timestamp     = 'Timestamp';
    case PhpVersion    = 'PhpVersion';
    case WpVersion     = 'WpVersion';
    case ActivationError = 'ActivationError';
}
