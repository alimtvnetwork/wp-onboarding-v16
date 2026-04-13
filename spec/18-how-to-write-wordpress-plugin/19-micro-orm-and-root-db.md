# Phase 19 — Micro-ORM and Cross-Plugin Root Database

> **Purpose:** Document the fluent micro-ORM query builder and the cross-plugin Root DB registration pattern for snapshot/backup coordination.

---

## 19.1 Micro-ORM — Fluent Query Builder

The micro-ORM is a lightweight, Idiorm-style fluent query builder for SQLite. It provides method chaining for SELECT, INSERT, UPDATE, and DELETE without requiring a full ORM framework.

### Architecture

```
Database/
├── Orm.php                      ← Shell class (static PDO, forTable factory)
└── Traits/
    ├── OrmWhereTrait.php        ← WHERE clause builders
    ├── OrmQueryTrait.php        ← SELECT, ORDER BY, GROUP BY, LIMIT, findOne/findMany
    └── OrmMutationTrait.php     ← create(), set(), save(), delete()
```

### Shell class — Orm.php

The Orm class holds shared static state and composes all three traits:

```php
class Orm {
    use OrmWhereTrait;
    use OrmQueryTrait;
    use OrmMutationTrait;

    private static ?PDO $pdo = null;
    private string $tableName = '';
    private array $data = [];
    private array $whereClauses = [];
    private array $whereParams = [];
    private array $orderBy = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $selectColumns = ['*'];
    private array $groupBy = [];
    private bool $isNew = false;
    private string $idColumn = 'Id';
    private static int $paramCounter = 0;

    public static function configure(PDO $pdo): void { self::$pdo = $pdo; }
    public static function getPdo(): ?PDO { return self::$pdo; }
    public static function forTable(string $tableName): self { /* factory */ }
    public static function rawExecute(string $sql, array $params = []): array { /* escape hatch */ }

    private function __construct() {}
}
```

### Key design decisions

| Decision | Rationale |
|----------|-----------|
| Static `$pdo` | Configured once via `Orm::configure($pdo)` during `Database::initDatabase()` — no injection needed |
| `forTable()` factory | Returns a fresh instance per query chain — prevents state leakage between queries |
| Private constructor | Forces use of `forTable()` — no accidental bare instantiation |
| `$paramCounter` static | Generates unique parameter names across all WHERE clauses to prevent collisions |

---

## 19.2 OrmWhereTrait — WHERE Clause Building

All WHERE methods return `$this` for chaining. Parameters are auto-named via `generateParamName()` to prevent collisions in complex queries.

### Methods

| Method | SQL Output |
|--------|-----------|
| `where($col, $val)` | `column = :param` |
| `whereEqual($col, $val)` | Alias for `where()` |
| `whereNotEqual($col, $val)` | `column != :param` |
| `whereGt($col, $val)` | `column > :param` |
| `whereGte($col, $val)` | `column >= :param` |
| `whereLt($col, $val)` | `column < :param` |
| `whereLte($col, $val)` | `column <= :param` |
| `whereLike($col, $val)` | `column LIKE :param` |
| `whereNull($col)` | `column IS NULL` |
| `whereNotNull($col)` | `column IS NOT NULL` |
| `whereIn($col, $values)` | `column IN (:p1, :p2, ...)` |
| `whereNotIn($col, $values)` | `column NOT IN (:p1, :p2, ...)` |
| `whereRaw($clause, $params)` | Raw SQL clause with manual params |

### Parameter generation

```php
private function generateParamName(string $column): string {
    self::$paramCounter++;
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    return ':' . $safeColumn . '_' . self::$paramCounter;
}
```

This ensures parameters like `:Status_1`, `:Status_2` never collide, even with multiple `where()` calls on the same column.

### Edge case — empty `whereIn()`

```php
public function whereIn(string $column, array $values) {
    if (empty($values)) {
        $this->whereClauses[] = '1 = 0';  // Always-false — returns no rows
        return $this;
    }
    // ...
}
```

---

## 19.3 OrmQueryTrait — SELECT Operations

### Column selection

| Method | Purpose |
|--------|---------|
| `select($columns)` | Set columns (array or variadic args) |
| `selectColumn($column)` | Single column shorthand |
| `selectCount($alias)` | `COUNT(*) as {alias}` |

### Ordering, grouping, pagination

| Method | Purpose |
|--------|---------|
| `orderByAsc($col)` | `ORDER BY column ASC` |
| `orderByDesc($col)` | `ORDER BY column DESC` |
| `orderBy($col, $dir)` | Custom direction with validation |
| `groupBy($col)` | `GROUP BY column` |
| `limit($n)` | `LIMIT n` |
| `offset($n)` | `OFFSET n` |

### Query execution

| Method | Returns | Purpose |
|--------|---------|---------|
| `findOne($id)` | `?array` | Single record by primary key |
| `findMany()` | `array` | Execute built query, return all rows |
| `count()` | `int` | Count matching records |

### SQL builder

`buildSelectSql()` assembles the final SQL from accumulated state:

```
SELECT {columns} FROM {table}
  [WHERE {clauses}]
  [GROUP BY {columns}]
  [ORDER BY {columns}]
  [LIMIT {n}]
  [OFFSET {n}]
```

All queries are wrapped in try-catch with `InitHelpers::errorLog()` fallback — the ORM never throws.

---

## 19.4 OrmMutationTrait — Write Operations

### Insert flow

```php
$id = Orm::forTable('Transactions')
    ->create()
    ->set('Action', ActionType::Upload->value)
    ->set('Status', 'Success')
    ->set('CreatedAt', DateHelper::nowUtc())
    ->save();
// Returns: int (last insert ID) or false on failure
```

Internally:
1. `create()` sets `$isNew = true` and clears `$data`
2. `set($col, $val)` accumulates column-value pairs
3. `save()` dispatches to `doInsert()` which builds `INSERT INTO ... VALUES (...)`

### Update flow

```php
$affected = Orm::forTable('Transactions')
    ->where('Id', $id)
    ->set('Status', 'Completed')
    ->save();
// Returns: int (rows affected) or false
```

When `$isNew` is false, `save()` dispatches to `doUpdate()` which builds `UPDATE ... SET ... WHERE ...`. **Requires at least one WHERE clause** — bare updates are blocked (returns 0).

### Delete flow

```php
$deleted = Orm::forTable('Transactions')
    ->where('Status', 'Failed')
    ->whereLt('CreatedAt', $cutoffDate)
    ->delete();
// Returns: int (rows deleted)
```

**Safety:** `delete()` refuses to execute without WHERE clauses (returns 0) — prevents accidental `DELETE FROM table`.

---

## 19.5 Usage Examples

### Paginated query with filters

```php
$results = Orm::forTable('Transactions')
    ->select('Id', 'Action', 'Status', 'CreatedAt')
    ->where('Status', 'Success')
    ->whereLike('Action', '%Upload%')
    ->orderByDesc('CreatedAt')
    ->limit(25)
    ->offset(50)
    ->findMany();
```

### Count with filters

```php
$total = Orm::forTable('Transactions')
    ->where('Status', 'Success')
    ->count();
```

### Raw SQL escape hatch

```php
$rows = Orm::rawExecute(
    "SELECT Action, COUNT(*) as Total FROM Transactions GROUP BY Action ORDER BY Total DESC",
);
```

Use `rawExecute()` only for complex queries that the builder cannot express (aggregations with HAVING, subqueries, JOINs).

---

## 19.6 Integration with Database class

The ORM is configured during `Database::initDatabase()` via the ConnectionTrait:

```php
// Inside DatabaseConnectionTrait::initDatabase()
$this->pdo = InitHelpers::initSqliteConnection($this->dbPath, $this->fileLogger);
Orm::configure($this->pdo);  // ← ORM now shares the same PDO
$this->createTables();        // ← migrations run
```

After this, `Orm::forTable()` is available anywhere in the plugin without passing a PDO instance.

---

## 19.7 Cross-Plugin Root Database (RootDb)

The Root Database (`a-root.db`) is a **cross-plugin coordination database** used during snapshot exports. It serves as a manifest that records which tables were exported, their checksums, dependency graphs, and plugin versions.

### Architecture

```
Database/
├── RootDb.php                        ← Shell class (singleton, create/read)
└── Traits/
    ├── RootDbSchemaTrait.php         ← Schema creation, metadata population, dependency graph
    └── RootDbRegistrationTrait.php   ← Table registration, stats, incrementals, plugin snapshots
```

### Shell class — RootDb.php

```php
class RootDb {
    use RootDbSchemaTrait;
    use RootDbRegistrationTrait;

    private FileLogger $logger;
    private DependencyAnalyzer $analyzer;
    private static ?self $instance = null;

    public static function getInstance(?FileLogger $logger = null, ?DependencyAnalyzer $analyzer = null): self;
    public function create(string $filepath): PDO;   // Creates a-root.db with schema
}
```

**Key:** Unlike the main `Database` class, `RootDb` creates a **separate PDO connection** per snapshot — it does not share the plugin's main PDO. Each `a-root.db` is an independent, self-contained manifest file.

### Schema (RootDbSchemaTrait)

The root database contains 5 tables:

#### SnapshotMeta — Export metadata

| Column | Type | Purpose |
|--------|------|---------|
| Id | INTEGER PK | Always 1 (single row) |
| Title | TEXT | Snapshot title |
| Type | TEXT | `Full` or `Incremental` (from `SnapshotModeType` enum) |
| CreatedAt | TEXT | ISO 8601 UTC timestamp |
| CreatedBy | TEXT | Hostname |
| MysqlVersion | TEXT | Source MySQL version |
| WpVersion | TEXT | Source WordPress version |
| PluginVersion | TEXT | Plugin version at export time |
| TableCount | INTEGER | Total tables exported |
| TotalRows | INTEGER | Total rows across all tables |
| ConfigJson | TEXT | Export configuration as JSON |

#### SnapshotTables — Per-table export records

| Column | Type | Purpose |
|--------|------|---------|
| TableName | TEXT UNIQUE | WordPress table name |
| RowCount | INTEGER | Rows exported |
| SqliteFile | TEXT | Relative path to the table's SQLite file |
| FileSizeBytes | INTEGER | File size |
| ChecksumMd5 | TEXT | MD5 checksum for integrity verification |
| ExportedAt | TEXT | When this table was exported |

#### TableDependencies — Foreign key dependency graph

| Column | Type | Purpose |
|--------|------|---------|
| ParentTable | TEXT | Referenced table |
| ChildTable | TEXT | Table with the foreign key |
| FkColumn | TEXT | Foreign key column name |
| RefColumn | TEXT | Referenced column name |

#### IncrementalBackups — Incremental backup log

| Column | Type | Purpose |
|--------|------|---------|
| SequenceNum | INTEGER | Monotonic sequence number |
| FolderName | TEXT | Backup folder name |
| CreatedAt | TEXT | When created |
| TablesChanged | INTEGER | Number of changed tables |
| TotalNewRows | INTEGER | New rows since last backup |
| RelativePath | TEXT | Path relative to snapshots dir |

#### PluginSnapshots — Plugin ZIP archives

| Column | Type | Purpose |
|--------|------|---------|
| PluginSlug | TEXT | Plugin identifier |
| PluginName | TEXT | Display name |
| PluginVersion | TEXT | Version at snapshot time |
| ZipFile | TEXT | Path to ZIP archive |
| FileSizeBytes | INTEGER | Archive size |
| ChecksumMd5 | TEXT | Archive checksum |

### Registration methods (RootDbRegistrationTrait)

| Method | Purpose |
|--------|---------|
| `registerTable($pdo, $tableName, $rowCount, $sqliteFile, ...)` | Record a table export with checksum |
| `updateStats($pdo, $tableCount, $totalRows)` | Update final counts in SnapshotMeta |
| `registerIncremental($pdo, $info)` | Log an incremental backup entry |
| `registerPluginSnapshot($pdo, $info)` | Record a plugin ZIP export |
| `readMetadata($filepath)` | Read full metadata from an existing `a-root.db` |

### Backward compatibility — Legacy table/column resolution

The RootDb supports reading older snapshots that used `snake_case` naming (before the PascalCase migration):

```php
public function resolveRootDbTableName(PDO $pdo, string $pascalName): string {
    // 1. Check if PascalCase table exists → use it
    // 2. Look up legacy map (SnapshotMeta → snapshot_meta)
    // 3. Check if legacy table exists → use it
    // 4. Fall back to PascalCase name
}
```

Legacy maps are maintained for both table names and column names, ensuring the plugin can read snapshots from any version.

### Metadata population flow

```php
$rootDb = RootDb::getInstance($logger, $analyzer);
$pdo = $rootDb->create($snapshotDir . '/a-root.db');

// 1. Populate metadata (MySQL/WP/plugin versions, title, config)
$rootDb->populateMetadata($pdo, $config);

// 2. Populate dependency graph (foreign key analysis)
$rootDb->populateDependencies($pdo, 'all');

// 3. Register each exported table
foreach ($exportedTables as $table) {
    $rootDb->registerTable($pdo, $table['name'], $table['rows'], $table['file'], ...);
}

// 4. Update final stats
$rootDb->updateStats($pdo, count($exportedTables), $totalRows);
```

---

## 19.8 Key Patterns Summary

| Pattern | Where used | Rule |
|---------|-----------|------|
| Static PDO configuration | `Orm::configure()` | Configure once during Database init; available globally after |
| Factory method | `Orm::forTable()` | Fresh instance per query — prevents state leakage |
| Refuse bare mutations | `doUpdate()`, `delete()` | Require WHERE clause — never allow unguarded UPDATE/DELETE |
| Unique param naming | `generateParamName()` | Static counter prevents parameter collisions |
| Separate PDO per manifest | `RootDb::create()` | Each snapshot gets its own independent `a-root.db` |
| Legacy compatibility | `resolveRootDbTableName()` | Always check PascalCase first, then fall back to snake_case |
| Error swallowing | `findMany()`, `count()`, `save()` | ORM catches exceptions and returns empty/zero/false — never throws |