<?php
/**
 * RequestFieldType — HTTP request field name constants.
 *
 * Eliminates magic strings for form/JSON field names used in upload and
 * activation endpoints.
 *
 * @package QUpload\Enums
 * @since   1.2.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum RequestFieldType: string
{
    case PluginZip = 'plugin_zip';
    case Slug      = 'slug';
    case Activate  = 'activate';
}
