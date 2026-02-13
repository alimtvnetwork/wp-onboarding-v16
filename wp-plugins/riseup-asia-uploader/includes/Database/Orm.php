<?php
/**
 * Riseup Asia Uploader - Micro ORM
 *
 * A lightweight Idiorm-style fluent query builder for SQLite.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load traits
require_once dirname(__FILE__) . '/Traits/OrmWhereTrait.php';
require_once dirname(__FILE__) . '/Traits/OrmQueryTrait.php';
require_once dirname(__FILE__) . '/Traits/OrmMutationTrait.php';

/**
 * Class RiseupORM
 *
 * Fluent query builder with method chaining support.
 */
class RiseupORM {

    use OrmWhereTrait;
    use OrmQueryTrait;
    use OrmMutationTrait;

    /** @var PDO|null */
    private static $pdo = null;

    /** @var string */
    private $table_name;

    /** @var array */
    private $data = array();

    /** @var array */
    private $where_clauses = array();

    /** @var array */
    private $where_params = array();

    /** @var array */
    private $order_by = array();

    /** @var int|null */
    private $limit_value = null;

    /** @var int|null */
    private $offset_value = null;

    /** @var array */
    private $select_columns = array('*');

    /** @var array */
    private $group_by = array();

    /** @var bool */
    private $is_new = false;

    /** @var mixed */
    private $id = null;

    /** @var string */
    private $id_column = 'id';

    /** @var int */
    private static $param_counter = 0;

    /**
     * Configure the ORM with a PDO instance.
     *
     * @param PDO $pdo PDO connection.
     */
    public static function configure($pdo) {
        self::$pdo = $pdo;
    }

    /** @return PDO|null */
    public static function get_pdo() {
        return self::$pdo;
    }

    /**
     * Start a query for a specific table.
     *
     * @param string $table_name Table name.
     * @return RiseupORM
     */
    public static function for_table($table_name) {
        $orm = new self();
        $orm->table_name = $table_name;
        return $orm;
    }

    /**
     * Execute raw SQL query.
     *
     * @param string $sql    SQL query.
     * @param array  $params Parameters.
     * @return array Results.
     */
    public static function raw_execute($sql, $params = array()) {
        if (!self::$pdo) {
            return array();
        }

        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return array();
        }
    }

    /** Private constructor - use for_table() instead. */
    private function __construct() {
    }
}
