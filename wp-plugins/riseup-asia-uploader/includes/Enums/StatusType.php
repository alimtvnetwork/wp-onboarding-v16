<?php
/**
 * StatusType — Transaction status values.
 *
 * Backed enum replacing STATUS_SUCCESS / STATUS_FAILED constants.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transaction result status.
 */
enum StatusType: string
{
    case Success = 'success';
    case Failed  = 'failed';

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

    /** Check if this status indicates success. */
    public function isSuccess(): bool
    {
        return $this->isEqual(self::Success);
    }

    /** Check if this status indicates failure. */
    public function isFailed(): bool
    {
        return $this->isEqual(self::Failed);
    }
}
