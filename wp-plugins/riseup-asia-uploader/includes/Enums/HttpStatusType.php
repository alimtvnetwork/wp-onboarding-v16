<?php
/**
 * HttpStatusType — HTTP Status Code Constants
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HTTP status codes used in REST API responses.
 */
enum HttpStatusType: int
{
    case Ok          = 200;
    case Created     = 201;
    case NoContent   = 204;
    case BadRequest  = 400;
    case Unauthorized = 401;
    case Forbidden   = 403;
    case NotFound    = 404;
    case Conflict    = 409;
    case RequestTimeout = 408;
    case TooManyRequests = 429;
    case ServerError = 500;
    case NotImplemented = 501;
    case BadGateway  = 502;
    case ServiceUnavailable = 503;
    case GatewayTimeout = 504;

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool
    {
        return $this !== $other;
    }

    /** Check if this status represents a successful response. */
    public function isSuccess(): bool
    {
        return $this->value >= 200 && $this->value < 300;
    }

    /** Check if this status represents a client error. */
    public function isClientError(): bool
    {
        return $this->value >= 400 && $this->value < 500;
    }

    /** Check if this status represents a server error. */
    public function isServerError(): bool
    {
        return $this->value >= 500;
    }

    /** Check if this status code indicates a transient/retryable failure. */
    public function isRetryable(): bool
    {
        return $this->isEqual(self::RequestTimeout)
            || $this->isEqual(self::TooManyRequests)
            || $this->isEqual(self::ServerError)
            || $this->isEqual(self::BadGateway)
            || $this->isEqual(self::ServiceUnavailable)
            || $this->isEqual(self::GatewayTimeout);
    }
}
