<?php
/**
 * LogValueTrait — Typed accessors for log arrays keyed by LogColumnType.
 *
 * Eliminates repetitive `isset($log[Enum->value]) ? $log[Enum->value] : ''`
 * patterns in templates and services that consume log rows.
 *
 * @package RiseupAsia\Traits\Log
 * @since   1.58.0
 */

namespace RiseupAsia\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogColumnType;

trait LogValueTrait
{
    /**
     * Get a log column value with a fallback default.
     *
     * @param  array         $log     The log row array.
     * @param  LogColumnType $column  The column enum case.
     * @param  mixed         $default Fallback when the key is missing or null.
     * @return mixed
     */
    protected function logValue(array $log, LogColumnType $column, mixed $default = ''): mixed
    {
        return $log[$column->value] ?? $default;
    }

    /**
     * Get a log column value cast to string.
     *
     * @param  array         $log     The log row array.
     * @param  LogColumnType $column  The column enum case.
     * @param  string        $default Fallback when the key is missing or null.
     * @return string
     */
    protected function logString(array $log, LogColumnType $column, string $default = ''): string
    {
        return (string) ($log[$column->value] ?? $default);
    }
}
