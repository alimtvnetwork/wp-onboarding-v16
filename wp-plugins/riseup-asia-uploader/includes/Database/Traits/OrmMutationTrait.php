<?php
/**
 * ORM Mutation Trait
 *
 * CREATE, UPDATE, and DELETE operations for the fluent query builder.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Database\Traits;

use PDOException;

use RiseupAsia\Helpers\InitHelpers;

if (!defined('ABSPATH')) {
    exit;
}

trait OrmMutationTrait {

    /** Create a new record instance. */
    public function create(): static {
        $this->isNew = true;
        $this->data = array();

        return $this;
    }

    /** Set a column value. */
    public function set(string $column, string|int|float|bool|null $value): static {
        $this->data[$column] = $value;

        return $this;
    }

    /** Save the record (insert or update). */
    public function save(): int|false {
        if (!self::$pdo) {
            return false;
        }

        try {
            return $this->isNew ? $this->doInsert() : $this->doUpdate();
        } catch (PDOException $e) {
            InitHelpers::errorLog($e, 'Orm::save() failed:');

            return false;
        }
    }

    /** Perform INSERT. */
    private function doInsert(): int|false {
        if (empty($this->data)) {
            return false;
        }

        $columns = array_keys($this->data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->tableName,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $params = array();

        foreach ($this->data as $col => $val) {
            $params[':' . $col] = $val;
        }

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);

        return (int) self::$pdo->lastInsertId();
    }

    /**
     * Perform UPDATE.
     *
     * @return int Rows affected.
     */
    private function doUpdate(): int {
        if (empty($this->data) || empty($this->whereClauses)) {
            return 0;
        }

        $setClauses = array();
        $params = array();

        foreach ($this->data as $col => $val) {
            $paramName = ':set_' . $col;
            $setClauses[] = "{$col} = {$paramName}";
            $params[$paramName] = $val;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->tableName,
            implode(', ', $setClauses),
            implode(' AND ', $this->whereClauses),
        );

        $params = array_merge($params, $this->whereParams);

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Delete records.
     */
    public function delete(): int {
        if (!self::$pdo || empty($this->whereClauses)) {
            return 0;
        }

        try {
            $sql = "DELETE FROM {$this->tableName} WHERE " . implode(' AND ', $this->whereClauses);
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($this->whereParams);

            return $stmt->rowCount();
        } catch (PDOException $e) {
            InitHelpers::errorLog($e, 'Orm::delete() failed:');

            return 0;
        }
    }
}
