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
    case Gone         = 410;
    case TooManyRequests = 429;
    case ServerError  = 500;

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
