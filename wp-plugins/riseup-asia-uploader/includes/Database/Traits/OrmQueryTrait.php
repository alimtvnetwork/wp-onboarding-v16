<?php
/**
 * ORM Query Trait
 *
 * SELECT, ORDER BY, GROUP BY, LIMIT/OFFSET, and find methods.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait OrmQueryTrait {

    /**
     * Set columns to select.
     *
     * @param mixed $columns Column name(s) - string or array.
     * @return $this
     */
    public function select($columns) {
        $this->select_columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /** Select a single column. */
    public function select_column($column) {
        $this->select_columns = array($column);
        return $this;
    }

    /** Select with COUNT(*). */
    public function select_count($alias = 'count') {
        $this->select_columns = array("COUNT(*) as {$alias}");
        return $this;
    }

    /** Add ORDER BY ASC. */
    public function order_by_asc($column) {
        $this->order_by[] = "{$column} ASC";
        return $this;
    }

    /** Add ORDER BY DESC. */
    public function order_by_desc($column) {
        $this->order_by[] = "{$column} DESC";
        return $this;
    }

    /**
     * Add ORDER BY with custom direction.
     *
     * @param string $column    Column name.
     * @param string $direction Direction (ASC or DESC).
     * @return $this
     */
    public function order_by($column, $direction = 'ASC') {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->order_by[] = "{$column} {$direction}";
        return $this;
    }

    /** Add GROUP BY clause. */
    public function group_by($column) {
        $this->group_by[] = $column;
        return $this;
    }

    /** Set LIMIT. */
    public function limit($limit) {
        $this->limit_value = (int) $limit;
        return $this;
    }

    /** Set OFFSET. */
    public function offset($offset) {
        $this->offset_value = (int) $offset;
        return $this;
    }

    /**
     * Find a single record by ID.
     *
     * @param int $id Record ID.
     * @return array|null Record data or null.
     */
    public function find_one($id) {
        if (!self::$pdo) {
            return null;
        }

        $sql = "SELECT * FROM {$this->table_name} WHERE {$this->id_column} = :id LIMIT 1";

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute(array(':id' => $id));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Find multiple records.
     *
     * @return array Records.
     */
    public function find_many() {
        if (!self::$pdo) {
            return array();
        }

        $sql = $this->build_select_sql();

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($this->where_params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return array();
        }
    }

    /**
     * Count records.
     *
     * @return int Count.
     */
    public function count() {
        if (!self::$pdo) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as count FROM {$this->table_name}";

        if (!empty($this->where_clauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where_clauses);
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($this->where_params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Build SELECT SQL.
     *
     * @return string SQL query.
     */
    private function build_select_sql() {
        $columns = implode(', ', $this->select_columns);
        $sql = "SELECT {$columns} FROM {$this->table_name}";

        if (!empty($this->where_clauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where_clauses);
        }

        if (!empty($this->group_by)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->group_by);
        }

        if (!empty($this->order_by)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->order_by);
        }

        if ($this->limit_value !== null) {
            $sql .= ' LIMIT ' . $this->limit_value;
        }

        if ($this->offset_value !== null) {
            $sql .= ' OFFSET ' . $this->offset_value;
        }

        return $sql;
    }
}
