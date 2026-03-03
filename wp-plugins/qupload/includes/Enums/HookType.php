<?php
/**
 * HookType — WordPress hook name constants.
 *
 * @package QUpload\Enums
 * @since   1.1.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum HookType: string
{
    case PluginsLoaded = 'plugins_loaded';
    case RestApiInit   = 'rest_api_init';
    case AdminMenu     = 'admin_menu';
}
