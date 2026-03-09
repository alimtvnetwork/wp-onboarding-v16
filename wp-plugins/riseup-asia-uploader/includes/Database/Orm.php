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

use PDO;
use PDOException;

use RiseupAsia\Database\Traits\OrmMutationTrait;
use RiseupAsia\Database\Traits\OrmQueryTrait;
use RiseupAsia\Database\Traits\OrmWhereTrait;
use RiseupAsia\Logging\FileLogger;

/**
 * Class Orm
 *
 * Fluent query builder with method chaining support.
 */
class Orm {
    use OrmWhereTrait;
    use OrmQueryTrait;
    use OrmMutationTrait;

    private static ?PDO $pdo = null;
    private string $tableName = '';
    private array $data = array();
    private array $whereClauses = array();
    private array $whereParams = array();
    private array $orderBy = array();
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $selectColumns = array('*');
    private array $groupBy = array();
    private bool $isNew = false;
    private int|string|null $id = null;
    private string $idColumn = 'Id';
    private static int $paramCounter = 0;

    /**
     * Configure the ORM with a PDO instance.
     */
    public static function configure(PDO $pdo): void {
        self::$pdo = $pdo;
    }

    public static function getPdo(): ?PDO {
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

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $logger = FileLogger::getInstance();
            $logger->error('Orm::rawExecute() failed', array(
                'sql' => $sql,
                'error' => $e->getMessage(),
            ));

            return array();
        }
    }

    /** Private constructor - use forTable() instead. */
    private function __construct() {
    }
}
