<?php
/**
 * ORM Query Trait
 *
 * SELECT, ORDER BY, GROUP BY, LIMIT/OFFSET, and find methods.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}
use RiseupAsia\Helpers\InitHelpers;

trait OrmQueryTrait {

    public function select($columns) {
        $this->selectColumns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /** Select a single column. */
    public function selectColumn(string $column) {
        $this->selectColumns = array($column);

        return $this;
    }

    /** Select with COUNT(*). */
    public function selectCount(string $alias = 'count') {
        $this->selectColumns = array("COUNT(*) as {$alias}");

        return $this;
    }

    /** Add ORDER BY ASC. */
    public function orderByAsc(string $column) {
        $this->orderBy[] = "{$column} ASC";

        return $this;
    }

    /** Add ORDER BY DESC. */
    public function orderByDesc(string $column) {
        $this->orderBy[] = "{$column} DESC";

        return $this;
    }

    /** Add ORDER BY with custom direction. */
    public function orderBy(string $column, string $direction = 'ASC') {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "{$column} {$direction}";

        return $this;
    }

    /** Add GROUP BY clause. */
    public function groupBy(string $column) {
        $this->groupBy[] = $column;

        return $this;
    }

    /** Set LIMIT. */
    public function limit(int $limit) {
        $this->limitValue = $limit;

        return $this;
    }

    /** Set OFFSET. */
    public function offset(int $offset) {
        $this->offsetValue = $offset;

        return $this;
    }

    /** Find a single record by ID. */
    public function findOne(int $id): ?array {
        $isPdoMissing = (self::$pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        $sql = "SELECT * FROM {$this->tableName} WHERE {$this->idColumn} = :id LIMIT 1";

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute(array(':id' => $id));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (Throwable $e) {
            InitHelpers::errorLog($e, 'OrmQueryTrait::findOne() failed:');
            return null;
        }
    }

    /** Find multiple records. */
    public function findMany(): array {
        $isPdoMissing = (self::$pdo === null);

        if ($isPdoMissing) {
            return array();
        }

        $sql = $this->buildSelectSql();

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($this->whereParams);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            InitHelpers::errorLog($e, 'OrmQueryTrait::findMany() failed:');
            return array();
        }
    }

    /** Count records. */
    public function count(): int {
        $isPdoMissing = (self::$pdo === null);

        if ($isPdoMissing) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as count FROM {$this->tableName}";

        $hasWhereClauses = !empty($this->whereClauses);

        if ($hasWhereClauses) {
            $sql .= ' WHERE ' . implode(' AND ', $this->whereClauses);
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($this->whereParams);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($result['count'] ?? 0);
        } catch (PDOException $e) {
            InitHelpers::errorLog($e, 'OrmQueryTrait::count() failed:');
            return 0;
        }
    }

    /** Build SELECT SQL. */
    private function buildSelectSql(): string {
        $columns = implode(', ', $this->selectColumns);
        $sql = "SELECT {$columns} FROM {$this->tableName}";

        $hasWhereClauses = !empty($this->whereClauses);

        if ($hasWhereClauses) {
            $sql .= ' WHERE ' . implode(' AND ', $this->whereClauses);
        }

        $hasGroupBy = !empty($this->groupBy);

        if ($hasGroupBy) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        $hasOrderBy = !empty($this->orderBy);

        if ($hasOrderBy) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }
}