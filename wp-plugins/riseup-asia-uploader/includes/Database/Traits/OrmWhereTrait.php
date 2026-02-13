<?php
/**
 * ORM Where Trait
 *
 * All WHERE clause builder methods for the fluent query builder.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait OrmWhereTrait {

    /**
     * Generate unique parameter name.
     *
     * @param string $column Column name.
     * @return string Parameter name.
     */
    private function generate_param_name($column) {
        self::$param_counter++;
        $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        return ':' . $safe_column . '_' . self::$param_counter;
    }

    /**
     * Add a WHERE clause with = operator.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value.
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
     * @return $this
     */
    public function where_operator($column, $operator, $value) {
        $param_name = $this->generate_param_name($column);
        $this->where_clauses[] = "{$column} {$operator} {$param_name}";
        $this->where_params[$param_name] = $value;
        return $this;
    }

    /** @see where() */
    public function where_equal($column, $value) {
        return $this->where($column, $value);
    }

    /** WHERE column != value. */
    public function where_not_equal($column, $value) {
        return $this->where_operator($column, '!=', $value);
    }

    /** WHERE column > value. */
    public function where_gt($column, $value) {
        return $this->where_operator($column, '>', $value);
    }

    /** WHERE column >= value. */
    public function where_gte($column, $value) {
        return $this->where_operator($column, '>=', $value);
    }

    /** WHERE column < value. */
    public function where_lt($column, $value) {
        return $this->where_operator($column, '<', $value);
    }

    /** WHERE column <= value. */
    public function where_lte($column, $value) {
        return $this->where_operator($column, '<=', $value);
    }

    /** WHERE column LIKE value. */
    public function where_like($column, $value) {
        return $this->where_operator($column, 'LIKE', $value);
    }

    /** WHERE column IS NULL. */
    public function where_null($column) {
        $this->where_clauses[] = "{$column} IS NULL";
        return $this;
    }

    /** WHERE column IS NOT NULL. */
    public function where_not_null($column) {
        $this->where_clauses[] = "{$column} IS NOT NULL";
        return $this;
    }

    /**
     * WHERE column IN (values).
     *
     * @param string $column Column name.
     * @param array  $values Array of values.
     * @return $this
     */
    public function where_in($column, array $values) {
        if (empty($values)) {
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
     * @return $this
     */
    public function where_raw($clause, $params = array()) {
        $this->where_clauses[] = $clause;
        $this->where_params = array_merge($this->where_params, $params);
        return $this;
    }
}
