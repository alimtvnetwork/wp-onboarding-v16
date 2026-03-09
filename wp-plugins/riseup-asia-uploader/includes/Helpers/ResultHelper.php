<?php
/**
 * ResultHelper — Lightweight factory for internal service result arrays.
 *
 * Eliminates boilerplate around ResponseKeyType::Success, Error, and Code keys.
 * These are NOT REST API envelopes (use EnvelopeBuilder for that).
 * They are the small arrays passed between services, traits, and internal callers.
 *
 * @package RiseupAsia\Helpers
 * @since   2.2.0
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

use RiseupAsia\Enums\ResponseKeyType;

class ResultHelper
{
    /**
     * Build a success result with optional extra key-value pairs.
     *
     * @param array $extra Additional key => value pairs merged into the result.
     * @return array{success: true, ...}
     */
    public static function ok(array $extra = array()): array
    {
        return array_merge(
            array(ResponseKeyType::Success->value => true),
            $extra,
        );
    }

    /**
     * Build a bare failure result (no message).
     *
     * @param array $extra Additional key => value pairs merged into the result.
     * @return array{success: false, ...}
     */
    public static function failed(array $extra = array()): array
    {
        return array_merge(
            array(ResponseKeyType::Success->value => false),
            $extra,
        );
    }

    /**
     * Build an error result with a human-readable message.
     *
     * @param string $message Error description.
     * @param array  $extra   Additional key => value pairs merged into the result.
     * @return array{success: false, error: string, ...}
     */
    public static function error(string $message, array $extra = array()): array
    {
        return array_merge(
            array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $message,
            ),
            $extra,
        );
    }

    /**
     * Build an error result that includes an error code.
     *
     * @param string $message Error description.
     * @param string $code    Machine-readable error code (typically from an enum).
     * @param array  $extra   Additional key => value pairs merged into the result.
     * @return array{success: false, error: string, code: string, ...}
     */
    public static function errorWithCode(string $message, string $code, array $extra = array()): array
    {
        return array_merge(
            array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $message,
                ResponseKeyType::Code->value    => $code,
            ),
            $extra,
        );
    }

    /**
     * Build an error result from a caught exception.
     *
     * @param Throwable $exception The caught exception.
     * @param array     $extra     Additional key => value pairs merged into the result.
     * @return array{success: false, error: string, ...}
     */
    public static function errorFromException(Throwable $exception, array $extra = array()): array
    {
        return self::error($exception->getMessage(), $extra);
    }
}
