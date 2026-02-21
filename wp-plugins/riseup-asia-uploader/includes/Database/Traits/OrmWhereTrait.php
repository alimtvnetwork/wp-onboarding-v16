<?php
/**
 * ORM Where Trait
 *
 * All WHERE clause builder methods for the fluent query builder.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait OrmWhereTrait {

    /**
     * Generate unique parameter name.
     */
    private function generateParamName(string $column): string {
        self::$paramCounter++;
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

        return ':' . $safeColumn . '_' . self::$paramCounter;
    }

    /**
     * Add a WHERE clause with = operator.
     *
     * @return $this
     */
    public function where(string $column, $value) {
        return $this->whereOperator($column, '=', $value);
    }

    /**
     * Add a WHERE clause with custom operator.
     *
     * @return $this
     */
    public function whereOperator(
        string $column,
        string $operator,
        $value,
    ) {
        $paramName = $this->generateParamName($column);
        $this->whereClauses[] = "{$column} {$operator} {$paramName}";
        $this->whereParams[$paramName] = $value;

        return $this;
    }

    /** @see where() */
    public function whereEqual(string $column, $value) {
        return $this->where($column, $value);
    }

    /** WHERE column != value. */
    public function whereNotEqual(string $column, $value) {
        return $this->whereOperator($column, '!=', $value);
    }

    /** WHERE column > value. */
    public function whereGt(string $column, $value) {
        return $this->whereOperator($column, '>', $value);
    }

    /** WHERE column >= value. */
    public function whereGte(string $column, $value) {
        return $this->whereOperator($column, '>=', $value);
    }

    /** WHERE column < value. */
    public function whereLt(string $column, $value) {
        return $this->whereOperator($column, '<', $value);
    }

    /** WHERE column <= value. */
    public function whereLte(string $column, $value) {
        return $this->whereOperator($column, '<=', $value);
    }

    /** WHERE column LIKE value. */
    public function whereLike(string $column, $value) {
        return $this->whereOperator($column, 'LIKE', $value);
    }

    /** WHERE column IS NULL. */
    public function whereNull(string $column) {
        $this->whereClauses[] = "{$column} IS NULL";

        return $this;
    }

    /** WHERE column IS NOT NULL. */
    public function whereNotNull(string $column) {
        $this->whereClauses[] = "{$column} IS NOT NULL";

        return $this;
    }

    /**
     * WHERE column IN (values).
     *
     * @return $this
     */
    public function whereIn(string $column, array $values) {
        if (empty($values)) {
            $this->whereClauses[] = '1 = 0';

            return $this;
        }

        $placeholders = array();

        foreach ($values as $value) {
            $paramName = $this->generateParamName($column);
            $placeholders[] = $paramName;
            $this->whereParams[$paramName] = $value;
        }

        $this->whereClauses[] = "{$column} IN (" . implode(', ', $placeholders) . ")";

        return $this;
    }

    /**
     * WHERE column NOT IN (values).
     *
     * @return $this
     */
    public function whereNotIn(string $column, array $values) {
        if (empty($values)) {
            return $this;
        }

        $placeholders = array();

        foreach ($values as $value) {
            $paramName = $this->generateParamName($column);
            $placeholders[] = $paramName;
            $this->whereParams[$paramName] = $value;
        }

        $this->whereClauses[] = "{$column} NOT IN (" . implode(', ', $placeholders) . ")";

        return $this;
    }

    /**
     * Add raw WHERE clause.
     *
     * @return $this
     */
    public function whereRaw(string $clause, array $params = array()) {
        $this->whereClauses[] = $clause;
        $this->whereParams = array_merge($this->whereParams, $params);

        return $this;
    }
}
