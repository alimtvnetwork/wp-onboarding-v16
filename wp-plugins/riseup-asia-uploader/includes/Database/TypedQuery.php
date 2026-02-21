<?php
/**
 * TypedQuery — Generic typed query helper wrapping PDO.
 *
 * Provides queryOne(), queryMany(), and exec() that return
 * DbResult<T>, DbResultSet<T>, and DbExecResult respectively.
 * Callers supply a Closure mapper (like Go's scanner functions).
 *
 * @package RiseupAsia\Database
 * @since   1.58.0
 */

namespace RiseupAsia\Database;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use PDOException;
use Throwable;

final class TypedQuery {

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Execute a query expected to return a single row.
     * Returns DbResult::empty() for no rows (not an error).
     *
     * @template T
     * @param string          $sql
     * @param array<mixed>    $params
     * @param \Closure(array<string,mixed>): T $mapper
     * @return DbResult<T>
     */
    public function queryOne(
        string $sql,
        array $params,
        \Closure $mapper,
    ): DbResult {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return DbResult::error($e);
        }

        if ($row === false) {
            return DbResult::empty();
        }

        return $this->mapSingle($row, $mapper);
    }

    /**
     * Execute a query expected to return multiple rows.
     *
     * @template T
     * @param string          $sql
     * @param array<mixed>    $params
     * @param \Closure(array<string,mixed>): T $mapper
     * @return DbResultSet<T>
     */
    public function queryMany(
        string $sql,
        array $params,
        \Closure $mapper,
    ): DbResultSet {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return DbResultSet::error($e);
        }

        return $this->mapMany($rows, $mapper);
    }

    /**
     * Execute a non-query statement (INSERT, UPDATE, DELETE).
     *
     * @param string       $sql
     * @param array<mixed> $params
     */
    public function exec(string $sql, array $params = []): DbExecResult {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $affected = $stmt->rowCount();
            $lastId = (int) $this->pdo->lastInsertId();

            return DbExecResult::of($affected, $lastId);
        } catch (PDOException $e) {
            return DbExecResult::error($e);
        }
    }

    /**
     * Map a single row through the mapper closure.
     *
     * @template T
     * @param array<string,mixed> $row
     * @param \Closure(array<string,mixed>): T $mapper
     * @return DbResult<T>
     */
    private function mapSingle(array $row, \Closure $mapper): DbResult {
        try {
            return DbResult::of($mapper($row));
        } catch (Throwable $e) {
            return DbResult::error($e);
        }
    }

    /**
     * Map multiple rows through the mapper closure.
     *
     * @template T
     * @param array<array<string,mixed>> $rows
     * @param \Closure(array<string,mixed>): T $mapper
     * @return DbResultSet<T>
     */
    private function mapMany(array $rows, \Closure $mapper): DbResultSet {
        try {
            $items = array_map($mapper, $rows);
            return DbResultSet::of($items);
        } catch (Throwable $e) {
            return DbResultSet::error($e);
        }
    }
}
