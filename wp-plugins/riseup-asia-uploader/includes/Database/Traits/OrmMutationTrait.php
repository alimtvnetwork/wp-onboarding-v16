<?php
/**
 * ORM Mutation Trait
 *
 * CREATE, UPDATE, and DELETE operations for the fluent query builder.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait OrmMutationTrait {

    /**
     * Create a new record instance.
     *
     * @return $this
     */
    public function create() {
        $this->is_new = true;
        $this->data = array();
        return $this;
    }

    /**
     * Set a column value.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     * @return $this
     */
    public function set($column, $value) {
        $this->data[$column] = $value;
        return $this;
    }

    /**
     * Save the record (insert or update).
     *
     * @return int|false Insert ID or rows affected, false on error.
     */
    public function save() {
        if (!self::$pdo) {
            return false;
        }

        try {
            return $this->is_new ? $this->do_insert() : $this->do_update();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Perform INSERT.
     *
     * @return int|false Insert ID or false.
     */
    private function do_insert() {
        if (empty($this->data)) {
            return false;
        }

        $columns = array_keys($this->data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table_name,
            implode(', ', $columns),
            implode(', ', $placeholders)
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
    private function do_update() {
        if (empty($this->data) || empty($this->where_clauses)) {
            return 0;
        }

        $set_clauses = array();
        $params = array();

        foreach ($this->data as $col => $val) {
            $param_name = ':set_' . $col;
            $set_clauses[] = "{$col} = {$param_name}";
            $params[$param_name] = $val;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->table_name,
            implode(', ', $set_clauses),
            implode(' AND ', $this->where_clauses)
        );

        $params = array_merge($params, $this->where_params);

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Delete records.
     *
     * @return int Rows deleted.
     */
    public function delete() {
        if (!self::$pdo || empty($this->where_clauses)) {
            return 0;
        }

        try {
            $sql = "DELETE FROM {$this->table_name} WHERE " . implode(' AND ', $this->where_clauses);
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($this->where_params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
}
