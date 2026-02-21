<?php
/**
 * HttpStatusType — HTTP status code constants.
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum HttpStatusType: int
{
    case Ok          = 200;
    case Created     = 201;
    case NoContent   = 204;

    // ── Redirect ────────────────────────────────────────────────────
    case MovedPermanently  = 301;
    case Found             = 302;
    case SeeOther          = 303;
    case TemporaryRedirect = 307;
    case PermanentRedirect = 308;

    // ── Client Error ────────────────────────────────────────────────
    case BadRequest  = 400;
    case Unauthorized = 401;
    case Forbidden   = 403;
    case NotFound    = 404;
    case RequestTimeout = 408;
    case Conflict    = 409;
    case TooManyRequests = 429;

    // ── Server Error ────────────────────────────────────────────────
    case ServerError = 500;
    case NotImplemented = 501;
    case BadGateway  = 502;
    case ServiceUnavailable = 503;
    case GatewayTimeout = 504;

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isSuccess(): bool     { return $this->value >= 200 && $this->value < 300; }
    public function isClientError(): bool { return $this->value >= 400 && $this->value < 500; }
    public function isServerError(): bool { return $this->value >= 500; }

    public function isRetryable(): bool
    {
        return $this->isAnyOf(
            self::RequestTimeout, self::TooManyRequests,
            self::ServerError, self::BadGateway,
            self::ServiceUnavailable, self::GatewayTimeout,
        );
    }

    public function isRedirect(): bool
    {
        return $this->isAnyOf(
            self::MovedPermanently, self::Found, self::SeeOther,
            self::TemporaryRedirect, self::PermanentRedirect,
        );
    }
}
