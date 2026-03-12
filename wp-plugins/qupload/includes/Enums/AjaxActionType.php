<?php
/**
 * AjaxActionType — AJAX action identifiers.
 *
 * @package QUpload\Enums
 * @since   2.1.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum AjaxActionType: string
{
    case ReadLogFile  = 'qupload_read_log_file';
    case ClearLogFile = 'qupload_clear_log_file';
}
