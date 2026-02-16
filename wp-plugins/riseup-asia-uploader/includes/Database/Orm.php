<?php
/**
 * Riseup Asia Uploader - Micro ORM
 *
 * A lightweight Idiorm-style fluent query builder for SQLite.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Database
 * @since   1.4.0
 */

namespace RiseupAsia\Database;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Database\Traits\OrmWhereTrait;
use RiseupAsia\Database\Traits\OrmQueryTrait;
use RiseupAsia\Database\Traits\OrmMutationTrait;

/**
 * Class Orm
 *
 * Fluent query builder with method chaining support.
 */
class Orm {

    use OrmWhereTrait;
    use OrmQueryTrait;
    use OrmMutationTrait;

    /** @var \PDO|null */
    private static $pdo = null;

    /** @var string */
    private $tableName;

    /** @var array */
    private $data = array();

    /** @var array */
    private $whereClauses = array();

    /** @var array */
    private $whereParams = array();

    /** @var array */
    private $orderBy = array();

    /** @var int|null */
    private $limitValue = null;

    /** @var int|null */
    private $offsetValue = null;

    /** @var array */
    private $selectColumns = array('*');

    /** @var array */
    private $groupBy = array();

    /** @var bool */
    private $isNew = false;

    /** @var mixed */
    private $id = null;

    /** @var string */
    private $idColumn = 'id';

    /** @var int */
    private static $paramCounter = 0;

    /**
     * Configure the ORM with a PDO instance.
     */
    public static function configure(\PDO $pdo): void {
        self::$pdo = $pdo;
    }

    public static function getPdo(): ?\PDO {
        return self::$pdo;
    }

    /**
     * Start a query for a specific table.
     */
    public static function forTable(string $tableName): self {
        $orm = new self();
        $orm->tableName = $tableName;
        return $orm;
    }

    /**
     * Execute raw SQL query.
     */
    public static function rawExecute(string $sql, array $params = array()): array {
        if (!self::$pdo) {
            return array();
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return array();
        }
    }

    /** Private constructor - use forTable() instead. */
    private function __construct() {
    }
}

class_alias(Orm::class, 'RiseupORM');
