<?php
/**
 * DbResult — Single-item query result wrapper.
 *
 * Mirrors Go's dbutil.Result[T] pattern. Uses closure-based mappers
 * and @template PHPDoc for static analysis type safety.
 *
 * @package RiseupAsia\Database
 * @since   1.58.0
 *
 * @template T
 */

namespace RiseupAsia\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @template T
 */
final class DbResult {

    /**
     * @param T|null     $value
     * @param \Throwable|null $error
     * @param string     $stackTrace
     * @param bool       $defined
     */
    private function __construct(
        private readonly mixed $value = null,
        private readonly ?\Throwable $error = null,
        private readonly string $stackTrace = '',
        private readonly bool $defined = false,
    ) {
    }

    /**
     * Create a successful result with a value.
     *
     * @template U
     * @param U $value
     * @return self<U>
     */
    public static function of(mixed $value): self {
        return new self(value: $value, defined: true);
    }

    /**
     * Create an empty result (no row found, not an error).
     *
     * @template U
     * @return self<U>
     */
    public static function empty(): self {
        return new self();
    }

    /**
     * Create an error result with stack trace.
     *
     * @template U
     * @return self<U>
     */
    public static function error(\Throwable $error): self {
        return new self(
            error: $error,
            stackTrace: $error->getTraceAsString(),
        );
    }

    /** True when no row was found (no error, just absent). */
    public function isEmpty(): bool {
        return !$this->defined;
    }

    /** True when a row was successfully mapped. */
    public function isDefined(): bool {
        return $this->defined;
    }

    /** True when the query failed. */
    public function hasError(): bool {
        return $this->error !== null;
    }

    /** True when a value exists and there is no error. */
    public function isSafe(): bool {
        return $this->defined && $this->error === null;
    }

    /**
     * Return the mapped value (null if not defined).
     *
     * @return T|null
     */
    public function value(): mixed {
        return $this->value;
    }

    /** Return the underlying error, or null. */
    public function error(): ?\Throwable {
        return $this->error;
    }

    /** Return the captured stack trace if an error occurred. */
    public function stackTrace(): string {
        return $this->stackTrace;
    }
}
