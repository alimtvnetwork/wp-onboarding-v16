<?php
/**
 * Riseup Asia Uploader - Database Convenience Trait
 *
 * Thin PDO wrappers for common SQL operations.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use PDOException;
use PDOStatement;

/**
 * Trait DatabaseConvenienceTrait
 *
 * Provides queryAll, querySingle, insert, update, delete, execute, and lastInsertId
 * convenience methods. Expects $this->pdo to be a connected PDO instance.
 */
trait DatabaseConvenienceTrait {

    /**
     * Execute a SELECT and return all matching rows.
     *
     * @param string $sql    SQL query with optional placeholders.
     * @param array  $params Bound parameters.
     * @param int    $mode   PDO fetch mode.
     * @return array
     */
    public function queryAll(string $sql, array $params = [], int $mode = PDO::FETCH_ASSOC): array {
        $stmt = $this->prepareAndExecute($sql, $params);
        return $stmt ? $stmt->fetchAll($mode) : [];
    }

    /**
     * Execute a SELECT and return a single row.
     *
     * @param string $sql    SQL query with optional placeholders.
     * @param array  $params Bound parameters.
     * @param int    $mode   PDO fetch mode.
     * @return array|false
     */
    public function querySingle(string $sql, array $params = [], int $mode = PDO::FETCH_ASSOC) {
        $stmt = $this->prepareAndExecute($sql, $params);
        return $stmt ? $stmt->fetch($mode) : false;
    }

    /**
     * Insert a row into the given table.
     *
     * @param string $table  Table name.
     * @param array  $data   Associative array of column => value.
     * @return bool
     */
    public function insert(string $table, array $data): bool {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql          = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        return $this->execute($sql, array_values($data));
    }

    /**
     * Update rows in the given table.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value to set.
     * @param string $where WHERE clause (without the keyword).
     * @param array  $whereParams Bound parameters for the WHERE clause.
     * @return bool
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): bool {
        $setParts = [];
        foreach (array_keys($data) as $col) {
            $setParts[] = "{$col} = ?";
        }

        $setClause = implode(', ', $setParts);
        $sql       = "UPDATE {$table} SET {$setClause} WHERE {$where}";

        return $this->execute($sql, array_merge(array_values($data), $whereParams));
    }

    /**
     * Delete rows from the given table.
     *
     * @param string $table       Table name.
     * @param string $where       WHERE clause (without the keyword).
     * @param array  $whereParams Bound parameters for the WHERE clause.
     * @return bool
     */
    public function delete(string $table, string $where, array $whereParams = []): bool {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->execute($sql, $whereParams);
    }

    /**
     * Execute a statement (INSERT, UPDATE, DELETE, or DDL).
     *
     * @param string $sql    SQL statement.
     * @param array  $params Bound parameters.
     * @return bool
     */
    public function execute(string $sql, array $params = []): bool {
        $stmt = $this->prepareAndExecute($sql, $params);
        return $stmt !== false;
    }

    /**
     * Return the last inserted row ID.
     *
     * @return string
     */
    public function lastInsertId(): string {
        return $this->pdo ? $this->pdo->lastInsertId() : '0';
    }

    /**
     * Prepare and execute a PDO statement.
     *
     * @param string $sql    SQL statement.
     * @param array  $params Bound parameters.
     * @return PDOStatement|false
     */
    private function prepareAndExecute(string $sql, array $params = []) {
        if (!$this->pdo) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt && $stmt->execute($params)) {
                return $stmt;
            }
            return false;
        } catch (PDOException $e) {
            $this->fileLogger->error('DB query failed', [
                'sql'   => $sql,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
