<?php
/**
 * Rise Up Asia - Micro ORM
 *
 * A lightweight Idiorm-style fluent query builder for SQLite.
 * Provides chainable methods for building and executing SQL queries.
 *
 * @package RiseUpAsia
 * @since   1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseUp_ORM
 *
 * Fluent query builder with method chaining support.
 */
class RiseUp_ORM {

    /**
     * PDO instance (shared across all ORM instances).
     *
     * @var PDO|null
     */
    private static $pdo = null;

    /**
     * Table name for this query.
     *
     * @var string
     */
    private $table_name;

    /**
     * Data for insert/update operations.
     *
     * @var array
     */
    private $data = array();

    /**
     * WHERE clauses.
     *
     * @var array
     */
    private $where_clauses = array();

    /**
     * WHERE parameters.
     *
     * @var array
     */
    private $where_params = array();

    /**
     * ORDER BY clauses.
     *
     * @var array
     */
    private $order_by = array();

    /**
     * LIMIT value.
     *
     * @var int|null
     */
    private $limit_value = null;

    /**
     * OFFSET value.
     *
     * @var int|null
     */
    private $offset_value = null;

    /**
     * SELECT columns.
     *
     * @var array
     */
    private $select_columns = array('*');

    /**
     * GROUP BY columns.
     *
     * @var array
     */
    private $group_by = array();

    /**
     * Is this a new record (for insert)?
     *
     * @var bool
     */
    private $is_new = false;

    /**
     * Record ID (for update/delete).
     *
     * @var mixed
     */
    private $id = null;

    /**
     * Primary key column name.
     *
     * @var string
     */
    private $id_column = 'id';

    /**
     * Parameter counter for unique placeholders.
     *
     * @var int
     */
    private static $param_counter = 0;

    /**
     * Configure the ORM with a PDO instance.
     *
     * @param PDO $pdo PDO connection.
     *
     * @return void
     */
    public static function configure($pdo) {
        self::$pdo = $pdo;
    }

    /**
     * Get the configured PDO instance.
     *
     * @return PDO|null
     */
    public static function get_pdo() {
        return self::$pdo;
    }

    /**
     * Start a query for a specific table.
     *
     * @param string $table_name Table name.
     *
     * @return RiseUp_ORM
     */
    public static function for_table($table_name) {
        $orm = new self();
        $orm->table_name = $table_name;
        return $orm;
    }

    /**
     * Private constructor - use for_table() instead.
     */
    private function __construct() {
        // Private constructor
    }

    // =========================================================================
    // SELECT METHODS
    // =========================================================================

    /**
     * Set columns to select.
     *
     * @param mixed $columns Column name(s) - string or array.
     *
     * @return $this
     */
    public function select($columns) {
        if (is_array($columns)) {
            $this->select_columns = $columns;
        } else {
            $this->select_columns = func_get_args();
        }
        return $this;
    }

    /**
     * Select a single column.
     *
     * @param string $column Column name.
     *
     * @return $this
     */
    public function select_column($column) {
        $this->select_columns = array($column);
        return $this;
    }

    /**
     * Select with COUNT(*).
     *
     * @param string $alias Alias for the count column.
     *
     * @return $this
     */
    public function select_count($alias = 'count') {
        $this->select_columns = array("COUNT(*) as {$alias}");
        return $this;
    }

    // =========================================================================
    // WHERE METHODS
    // =========================================================================

    /**
     * Add a WHERE clause with = operator.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     *
     * @return $this
     */
    public function where($column, $value) {
        return $this->where_operator($column, '=', $value);
    }

    /**
     * Add a WHERE clause with custom operator.
     *
     * @param string $column   Column name.
     * @param string $operator Operator.
     * @param mixed  $value    Value.
     *
     * @return $this
     */
    public function where_operator($column, $operator, $value) {
        $param_name = $this->generate_param_name($column);
        $this->where_clauses[] = "{$column} {$operator} {$param_name}";
        $this->where_params[$param_name] = $value;
        return $this;
    }

    /**
     * WHERE column = value.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     *
     * @return $this
     */
    public function where_equal($column, $value) {
        return $this->where($column, $value);
    }

    /**
     * WHERE column != value.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     *
     * @return $this
     */
    public function where_not_equal($column, $value) {
        return $this->where_operator($column, '!=', $value);
    }

    /**
     * WHERE column > value.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     *
     * @return $this
     */
    public function where_gt($column, $value) {
        return $this->where_operator($column, '>', $value);
    }

    /**
     * WHERE column >= value.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     *
     * @return $this
     */
    public function where_gte($column, $value) {
        return $this->where_operator($column, '>=', $value);
    }

    /**
     * WHERE column < value.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     *
     * @return $this
     */
    public function where_lt($column, $value) {
        return $this->where_operator($column, '<', $value);
    }

    /**
     * WHERE column <= value.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     *
     * @return $this
     */
    public function where_lte($column, $value) {
        return $this->where_operator($column, '<=', $value);
    }

    /**
     * WHERE column LIKE value.
     *
     * @param string $column Column name.
     * @param string $value  Value (include % wildcards).
     *
     * @return $this
     */
    public function where_like($column, $value) {
        return $this->where_operator($column, 'LIKE', $value);
    }

    /**
     * WHERE column IS NULL.
     *
     * @param string $column Column name.
     *
     * @return $this
     */
    public function where_null($column) {
        $this->where_clauses[] = "{$column} IS NULL";
        return $this;
    }

    /**
     * WHERE column IS NOT NULL.
     *
     * @param string $column Column name.
     *
     * @return $this
     */
    public function where_not_null($column) {
        $this->where_clauses[] = "{$column} IS NOT NULL";
        return $this;
    }

    /**
     * WHERE column IN (values).
     *
     * @param string $column Column name.
     * @param array  $values Array of values.
     *
     * @return $this
     */
    public function where_in($column, array $values) {
        if (empty($values)) {
            // Empty IN clause - always false
            $this->where_clauses[] = '1 = 0';
            return $this;
        }

        $placeholders = array();
        foreach ($values as $value) {
            $param_name = $this->generate_param_name($column);
            $placeholders[] = $param_name;
            $this->where_params[$param_name] = $value;
        }

        $this->where_clauses[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    /**
     * WHERE column NOT IN (values).
     *
     * @param string $column Column name.
     * @param array  $values Array of values.
     *
     * @return $this
     */
    public function where_not_in($column, array $values) {
        if (empty($values)) {
            return $this;
        }

        $placeholders = array();
        foreach ($values as $value) {
            $param_name = $this->generate_param_name($column);
            $placeholders[] = $param_name;
            $this->where_params[$param_name] = $value;
        }

        $this->where_clauses[] = "{$column} NOT IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    /**
     * Add raw WHERE clause.
     *
     * @param string $clause Raw SQL clause.
     * @param array  $params Parameters for the clause.
     *
     * @return $this
     */
    public function where_raw($clause, $params = array()) {
        $this->where_clauses[] = $clause;
        $this->where_params = array_merge($this->where_params, $params);
        return $this;
    }

    // =========================================================================
    // ORDER BY METHODS
    // =========================================================================

    /**
     * Add ORDER BY ASC.
     *
     * @param string $column Column name.
     *
     * @return $this
     */
    public function order_by_asc($column) {
        $this->order_by[] = "{$column} ASC";
        return $this;
    }

    /**
     * Add ORDER BY DESC.
     *
     * @param string $column Column name.
     *
     * @return $this
     */
    public function order_by_desc($column) {
        $this->order_by[] = "{$column} DESC";
        return $this;
    }

    /**
     * Add ORDER BY with custom direction.
     *
     * @param string $column    Column name.
     * @param string $direction Direction (ASC or DESC).
     *
     * @return $this
     */
    public function order_by($column, $direction = 'ASC') {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->order_by[] = "{$column} {$direction}";
        return $this;
    }

    // =========================================================================
    // GROUP BY METHODS
    // =========================================================================

    /**
     * Add GROUP BY clause.
     *
     * @param string $column Column name.
     *
     * @return $this
     */
    public function group_by($column) {
        $this->group_by[] = $column;
        return $this;
    }

    // =========================================================================
    // LIMIT / OFFSET METHODS
    // =========================================================================

    /**
     * Set LIMIT.
     *
     * @param int $limit Number of records.
     *
     * @return $this
     */
    public function limit($limit) {
        $this->limit_value = (int) $limit;
        return $this;
    }

    /**
     * Set OFFSET.
     *
     * @param int $offset Offset.
     *
     * @return $this
     */
    public function offset($offset) {
        $this->offset_value = (int) $offset;
        return $this;
    }

    // =========================================================================
    // FIND METHODS (READ)
    // =========================================================================

    /**
     * Find a single record by ID.
     *
     * @param mixed $id Record ID.
     *
     * @return array|null Record data or null.
     */
    public function find_one($id = null) {
        if ($id !== null) {
            $this->where($this->id_column, $id);
        }
        $this->limit(1);

        $results = $this->execute_select();
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Find multiple records.
     *
     * @return array Array of records.
     */
    public function find_many() {
        return $this->execute_select();
    }

    /**
     * Get the count of matching records.
     *
     * @return int Count.
     */
    public function count() {
        $this->select_count('cnt');
        $result = $this->find_one();
        return $result ? (int) $result['cnt'] : 0;
    }

    // =========================================================================
    // CREATE / UPDATE / DELETE METHODS
    // =========================================================================

    /**
     * Create a new record instance.
     *
     * @param array $data Initial data.
     *
     * @return $this
     */
    public function create($data = array()) {
        $this->is_new = true;
        $this->data = $data;
        return $this;
    }

    /**
     * Set a column value.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
     *
     * @return $this
     */
    public function set($column, $value) {
        $this->data[$column] = $value;
        return $this;
    }

    /**
     * Set multiple values at once.
     *
     * @param array $data Associative array of column => value.
     *
     * @return $this
     */
    public function set_array($data) {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Set the ID (for updates).
     *
     * @param mixed $id Record ID.
     *
     * @return $this
     */
    public function set_id($id) {
        $this->id = $id;
        $this->is_new = false;
        return $this;
    }

    /**
     * Save the record (insert or update).
     *
     * @return int|false Insert ID for new records, row count for updates, false on error.
     */
    public function save() {
        if (!self::$pdo || empty($this->data)) {
            return false;
        }

        try {
            if ($this->is_new) {
                return $this->execute_insert();
            } else {
                return $this->execute_update();
            }
        } catch (PDOException $e) {
            error_log('[RiseUp Asia ORM] Save failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete records matching the current query.
     *
     * @return int|false Number of deleted rows or false on error.
     */
    public function delete() {
        if (!self::$pdo) {
            return false;
        }

        try {
            return $this->execute_delete();
        } catch (PDOException $e) {
            error_log('[RiseUp Asia ORM] Delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a record by ID.
     *
     * @param mixed $id Record ID.
     *
     * @return int|false Number of deleted rows or false on error.
     */
    public function delete_by_id($id) {
        return $this->where($this->id_column, $id)->delete();
    }

    // =========================================================================
    // RAW QUERY METHODS
    // =========================================================================

    /**
     * Execute a raw SQL query.
     *
     * @param string $sql    SQL query.
     * @param array  $params Parameters.
     *
     * @return PDOStatement|false Statement or false on error.
     */
    public static function raw_query($sql, $params = array()) {
        if (!self::$pdo) {
            return false;
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('[RiseUp Asia ORM] Raw query failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute raw SQL and return all results.
     *
     * @param string $sql    SQL query.
     * @param array  $params Parameters.
     *
     * @return array Results.
     */
    public static function raw_execute($sql, $params = array()) {
        $stmt = self::raw_query($sql, $params);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
    }

    // =========================================================================
    // INTERNAL METHODS
    // =========================================================================

    /**
     * Execute SELECT query.
     *
     * @return array Results.
     */
    private function execute_select() {
        if (!self::$pdo) {
            return array();
        }

        $sql = "SELECT " . implode(', ', $this->select_columns);
        $sql .= " FROM " . $this->table_name;

        if (!empty($this->where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $this->where_clauses);
        }

        if (!empty($this->group_by)) {
            $sql .= " GROUP BY " . implode(', ', $this->group_by);
        }

        if (!empty($this->order_by)) {
            $sql .= " ORDER BY " . implode(', ', $this->order_by);
        }

        if ($this->limit_value !== null) {
            $sql .= " LIMIT " . $this->limit_value;
        }

        if ($this->offset_value !== null) {
            $sql .= " OFFSET " . $this->offset_value;
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($this->where_params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[RiseUp Asia ORM] Select failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            return array();
        }
    }

    /**
     * Execute INSERT query.
     *
     * @return int|false Insert ID or false.
     */
    private function execute_insert() {
        $columns = array_keys($this->data);
        $placeholders = array_map(function($col) {
            return ':' . $col;
        }, $columns);

        $sql = "INSERT INTO " . $this->table_name;
        $sql .= " (" . implode(', ', $columns) . ")";
        $sql .= " VALUES (" . implode(', ', $placeholders) . ")";

        $params = array();
        foreach ($this->data as $column => $value) {
            $params[':' . $column] = $value;
        }

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);

        return self::$pdo->lastInsertId();
    }

    /**
     * Execute UPDATE query.
     *
     * @return int Number of affected rows.
     */
    private function execute_update() {
        $set_clauses = array();
        $params = array();

        foreach ($this->data as $column => $value) {
            $param_name = ':set_' . $column;
            $set_clauses[] = "{$column} = {$param_name}";
            $params[$param_name] = $value;
        }

        $sql = "UPDATE " . $this->table_name;
        $sql .= " SET " . implode(', ', $set_clauses);

        // Add ID condition if set
        if ($this->id !== null) {
            $this->where($this->id_column, $this->id);
        }

        if (!empty($this->where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $this->where_clauses);
        }

        $params = array_merge($params, $this->where_params);

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Execute DELETE query.
     *
     * @return int Number of affected rows.
     */
    private function execute_delete() {
        $sql = "DELETE FROM " . $this->table_name;

        if (!empty($this->where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $this->where_clauses);
        }

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($this->where_params);

        return $stmt->rowCount();
    }

    /**
     * Generate a unique parameter name.
     *
     * @param string $column Column name.
     *
     * @return string Parameter name.
     */
    private function generate_param_name($column) {
        self::$param_counter++;
        // Sanitize column name for use in parameter
        $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        return ':p_' . $safe_column . '_' . self::$param_counter;
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Begin a transaction.
     *
     * @return bool Success.
     */
    public static function begin_transaction() {
        if (self::$pdo) {
            return self::$pdo->beginTransaction();
        }
        return false;
    }

    /**
     * Commit a transaction.
     *
     * @return bool Success.
     */
    public static function commit() {
        if (self::$pdo) {
            return self::$pdo->commit();
        }
        return false;
    }

    /**
     * Rollback a transaction.
     *
     * @return bool Success.
     */
    public static function rollback() {
        if (self::$pdo) {
            return self::$pdo->rollBack();
        }
        return false;
    }

    /**
     * Get the last insert ID.
     *
     * @return string|false Last insert ID or false.
     */
    public static function last_insert_id() {
        if (self::$pdo) {
            return self::$pdo->lastInsertId();
        }
        return false;
    }
}
