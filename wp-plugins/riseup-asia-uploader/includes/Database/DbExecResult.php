<?php
/**
 * DbExecResult — Mutation (INSERT/UPDATE/DELETE) result wrapper.
 *
 * Mirrors Go's dbutil.ExecResult pattern.
 *
 * @package RiseupAsia\Database
 * @since   1.58.0
 */

namespace RiseupAsia\Database;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

final class DbExecResult {
    private function __construct(
        private readonly int $affectedRows = 0,
        private readonly int $lastInsertId = 0,
        private readonly ?Throwable $error = null,
        private readonly string $stackTrace = '',
    ) {
    }

    /** Create a successful exec result. */
    public static function of(int $affectedRows, int $lastInsertId = 0): self {
        return new self(affectedRows: $affectedRows, lastInsertId: $lastInsertId);
    }

    /** Create an error exec result with stack trace. */
    public static function error(Throwable $error): self {
        return new self(
            error: $error,
            stackTrace: $error->getTraceAsString(),
        );
    }

    /** True when the exec failed. */
    public function hasError(): bool {
        return $this->error !== null;
    }

    /** True when there is no error. */
    public function isSafe(): bool {
        return $this->error === null;
    }

    /** True when zero rows were affected. */
    public function isEmpty(): bool {
        return $this->affectedRows === 0;
    }

    public function affectedRows(): int {
        return $this->affectedRows;
    }

    public function lastInsertId(): int {
        return $this->lastInsertId;
    }

    public function error(): ?Throwable {
        return $this->error;
    }

    public function stackTrace(): string {
        return $this->stackTrace;
    }
}
