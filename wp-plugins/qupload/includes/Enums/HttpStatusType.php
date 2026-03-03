<?php
/**
 * HttpStatusType — HTTP status code constants.
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum HttpStatusType: int
{
    case Ok           = 200;
    case BadRequest   = 400;
    case Unauthorized = 401;
    case Forbidden    = 403;
    case NotFound     = 404;
    case ServerError  = 500;
}
