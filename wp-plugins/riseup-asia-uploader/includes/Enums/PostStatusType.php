<?php
/**
 * PostStatusType — WordPress post publication statuses.
 *
 * Backed enum replacing POST_STATUS_PUBLISH / DRAFT / PENDING constants.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress post statuses used by the plugin.
 */
enum PostStatusType: string
{
    case Publish = 'publish';
    case Draft   = 'draft';
    case Pending = 'pending';

    /** Check if this status is publicly visible. */
    public function isPublic(): bool
    {
        return $this === self::Publish;
    }

    /**
     * Return all valid status values as an array.
     *
     * @return string[]
     */
    public static function validValues(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}
