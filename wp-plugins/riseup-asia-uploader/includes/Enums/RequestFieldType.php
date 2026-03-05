<?php
/**
 * RequestFieldType — HTTP request field name constants.
 *
 * Eliminates magic strings for form/JSON field names used in upload
 * and related endpoints.
 *
 * @package RiseupAsia\Enums
 * @since   2.7.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum RequestFieldType: string
{
    case PluginZip     = 'plugin_zip';
    case Slug          = 'slug';
    case Activate      = 'activate';
    case UploadSource  = 'upload_source';
    case PluginVersion = 'plugin_version';
}
