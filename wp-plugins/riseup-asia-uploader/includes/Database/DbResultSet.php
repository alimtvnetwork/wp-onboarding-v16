<?php
/**
 * DbResultSet — Multi-row query result wrapper.
 *
 * Mirrors Go's dbutil.ResultSet[T] pattern.
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

use Throwable;

/**
 * @template T
 */
final class DbResultSet {

    /**
     * @param array<T>        $items
     * @param Throwable|null $error
     * @param string          $stackTrace
     */
    private function __construct(
        private readonly array $items = [],
        private readonly ?Throwable $error = null,
        private readonly string $stackTrace = '',
    ) {
    }

    /**
     * Create a successful result set.
     *
     * @template U
     * @param array<U> $items
     * @return self<U>
     */
    public static function of(array $items): self {
        return new self(items: $items);
    }

    /**
     * Create an error result set with stack trace.
     *
     * @template U
     * @return self<U>
     */
    public static function error(Throwable $error): self {
        return new self(
            error: $error,
            stackTrace: $error->getTraceAsString(),
        );
    }

    /** True when the result contains zero items. */
    public function isEmpty(): bool {
        return count($this->items) === 0;
    }

    /** True when the result contains at least one item. */
    public function hasAny(): bool {
        return count($this->items) > 0;
    }

    /** Return the number of items. */
    public function count(): int {
        return count($this->items);
    }

    /** True when the query failed. */
    public function hasError(): bool {
        return $this->error !== null;
    }

    /** True when there is no error (items may still be empty). */
    public function isSafe(): bool {
        return $this->error === null;
    }

    /**
     * Return the mapped items.
     *
     * @return array<T>
     */
    public function items(): array {
        return $this->items;
    }

    /**
     * Return the first item as a DbResult.
     *
     * @return DbResult<T>
     */
    public function first(): DbResult {
        if ($this->error !== null) {
            return DbResult::error($this->error);
        }

        if (count($this->items) === 0) {
            return DbResult::empty();
        }

        return DbResult::of($this->items[0]);
    }

    /** Return the underlying error, or null. */
    public function error(): ?Throwable {
        return $this->error;
    }

    /** Return the captured stack trace if an error occurred. */
    public function stackTrace(): string {
        return $this->stackTrace;
    }
}
