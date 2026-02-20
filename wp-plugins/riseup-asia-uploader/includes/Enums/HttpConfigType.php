<?php
/**
 * HttpConfigType — HTTP request configuration constants.
 *
 * @package RiseupAsia\Enums
 * @since   2.0.0
 */

namespace RiseupAsia\Enums;

enum HttpConfigType: int
{
    /** Default timeout for standard API requests (seconds). */
    case TimeoutDefault = 30;

    /** Short timeout for lightweight checks like HEAD redirects (seconds). */
    case TimeoutShort = 15;

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
}
