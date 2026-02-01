# 03 - ORM Base Classes

> **Phase:** Foundation  
> **Dependencies:** `02-database-schema.md`  
> **Estimated Time:** 4-6 hours

---

## 📋 Scope

Create base ORM classes for database operations. All database access must go through ORM - no raw SQL queries allowed.

---

## 🏗️ Base Model Class

**File:** `src/ORM/Model.php`

```php
<?php
namespace ExamQuestionsManager\ORM;

use ExamQuestionsManager\Database\Connection;
use PDO;

abstract class Model {
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];
    protected static array $casts = [];
    
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;
    
    /**
     * Get PDO connection
     */
    protected static function db(): PDO {
        return Connection::getInstance();
    }
    
    /**
     * Get table name
     */
    public static function getTable(): string {
        return static::$table;
    }
    
    /**
     * Create new model instance
     */
    public function __construct(array $attributes = []) {
        $this->fill($attributes);
    }
    
    /**
     * Fill model with attributes
     */
    public function fill(array $attributes): self {
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::$fillable) || $key === static::$primaryKey) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }
    
    /**
     * Set attribute with casting
     */
    public function setAttribute(string $key, mixed $value): void {
        $this->attributes[$key] = $this->castAttribute($key, $value);
    }
    
    /**
     * Get attribute with casting
     */
    public function getAttribute(string $key): mixed {
        return $this->attributes[$key] ?? null;
    }
    
    /**
     * Cast attribute to proper type
     */
    protected function castAttribute(string $key, mixed $value): mixed {
        if ($value === null) {
            return null;
        }
        
        $cast = static::$casts[$key] ?? null;
        
        return match($cast) {
            'int', 'integer' => (int) $value,
            'bool', 'boolean' => (bool) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $value,
            default => $value
        };
    }
    
    /**
     * Prepare value for database storage
     */
    protected function prepareForStorage(string $key, mixed $value): mixed {
        if ($value === null) {
            return null;
        }
        
        $cast = static::$casts[$key] ?? null;
        
        return match($cast) {
            'bool', 'boolean' => $value ? 1 : 0,
            'array', 'json' => json_encode($value),
            default => $value
        };
    }
    
    /**
     * Magic getter
     */
    public function __get(string $name): mixed {
        return $this->getAttribute($name);
    }
    
    /**
     * Magic setter
     */
    public function __set(string $name, mixed $value): void {
        $this->setAttribute($name, $value);
    }
    
    /**
     * Magic isset
     */
    public function __isset(string $name): bool {
        return isset($this->attributes[$name]);
    }
    
    /**
     * Get primary key value
     */
    public function getId(): ?int {
        return $this->getAttribute(static::$primaryKey);
    }
    
    /**
     * Check if model exists in database
     */
    public function exists(): bool {
        return $this->exists;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return $this->attributes;
    }
    
    /**
     * Find by ID
     */
    public static function find(int $id): ?static {
        $sql = sprintf(
            "SELECT * FROM %s WHERE %s = ? LIMIT 1",
            static::$table,
            static::$primaryKey
        );
        
        $stmt = self::db()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }
        
        return static::hydrate($row);
    }
    
    /**
     * Find by ID or throw exception
     */
    public static function findOrFail(int $id): static {
        $model = static::find($id);
        
        if (!$model) {
            throw new \RuntimeException(static::class . " with ID {$id} not found");
        }
        
        return $model;
    }
    
    /**
     * Get all records
     */
    public static function all(): array {
        $sql = sprintf("SELECT * FROM %s", static::$table);
        $stmt = self::db()->query($sql);
        
        return array_map(fn($row) => static::hydrate($row), $stmt->fetchAll());
    }
    
    /**
     * Create a new query builder
     */
    public static function query(): QueryBuilder {
        return new QueryBuilder(static::class);
    }
    
    /**
     * Where shortcut
     */
    public static function where(string $column, mixed $operator, mixed $value = null): QueryBuilder {
        return static::query()->where($column, $operator, $value);
    }
    
    /**
     * Create new record
     */
    public static function create(array $attributes): static {
        $model = new static($attributes);
        $model->save();
        return $model;
    }
    
    /**
     * Save model to database
     */
    public function save(): bool {
        if ($this->exists) {
            return $this->update();
        }
        
        return $this->insert();
    }
    
    /**
     * Insert new record
     */
    protected function insert(): bool {
        // Add timestamps
        $now = date('Y-m-d H:i:s');
        if (in_array('createdAt', static::$fillable)) {
            $this->attributes['createdAt'] = $this->attributes['createdAt'] ?? $now;
        }
        if (in_array('updatedAt', static::$fillable)) {
            $this->attributes['updatedAt'] = $now;
        }
        
        $columns = [];
        $placeholders = [];
        $values = [];
        
        foreach ($this->attributes as $key => $value) {
            if ($key === static::$primaryKey) {
                continue;
            }
            $columns[] = $key;
            $placeholders[] = '?';
            $values[] = $this->prepareForStorage($key, $value);
        }
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            static::$table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $stmt = self::db()->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            $this->attributes[static::$primaryKey] = (int) self::db()->lastInsertId();
            $this->exists = true;
            $this->original = $this->attributes;
        }
        
        return $result;
    }
    
    /**
     * Update existing record
     */
    protected function update(): bool {
        // Update timestamp
        if (in_array('updatedAt', static::$fillable)) {
            $this->attributes['updatedAt'] = date('Y-m-d H:i:s');
        }
        
        $sets = [];
        $values = [];
        
        foreach ($this->attributes as $key => $value) {
            if ($key === static::$primaryKey) {
                continue;
            }
            $sets[] = "{$key} = ?";
            $values[] = $this->prepareForStorage($key, $value);
        }
        
        $values[] = $this->getId();
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            static::$table,
            implode(', ', $sets),
            static::$primaryKey
        );
        
        $stmt = self::db()->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            $this->original = $this->attributes;
        }
        
        return $result;
    }
    
    /**
     * Delete record
     */
    public function delete(): bool {
        if (!$this->exists) {
            return false;
        }
        
        $sql = sprintf(
            "DELETE FROM %s WHERE %s = ?",
            static::$table,
            static::$primaryKey
        );
        
        $stmt = self::db()->prepare($sql);
        $result = $stmt->execute([$this->getId()]);
        
        if ($result) {
            $this->exists = false;
        }
        
        return $result;
    }
    
    /**
     * Hydrate model from database row
     */
    public static function hydrate(array $row): static {
        $model = new static();
        $model->attributes = [];
        
        foreach ($row as $key => $value) {
            $model->attributes[$key] = $model->castAttribute($key, $value);
        }
        
        $model->original = $model->attributes;
        $model->exists = true;
        
        return $model;
    }
    
    /**
     * Refresh model from database
     */
    public function refresh(): self {
        $fresh = static::find($this->getId());
        
        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
        }
        
        return $this;
    }
}
```

---

## 🔍 Query Builder

**File:** `src/ORM/QueryBuilder.php`

```php
<?php
namespace ExamQuestionsManager\ORM;

use ExamQuestionsManager\Database\Connection;
use PDO;

class QueryBuilder {
    protected string $modelClass;
    protected string $table;
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected array $selects = ['*'];
    
    public function __construct(string $modelClass) {
        $this->modelClass = $modelClass;
        $this->table = $modelClass::getTable();
    }
    
    /**
     * Add where clause
     */
    public function where(string $column, mixed $operator, mixed $value = null): self {
        // Handle 2-argument form: where('column', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = "{$column} {$operator} ?";
        $this->bindings[] = $value;
        
        return $this;
    }
    
    /**
     * Where null
     */
    public function whereNull(string $column): self {
        $this->wheres[] = "{$column} IS NULL";
        return $this;
    }
    
    /**
     * Where not null
     */
    public function whereNotNull(string $column): self {
        $this->wheres[] = "{$column} IS NOT NULL";
        return $this;
    }
    
    /**
     * Where in array
     */
    public function whereIn(string $column, array $values): self {
        if (empty($values)) {
            $this->wheres[] = '1 = 0'; // Always false
            return $this;
        }
        
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} IN ({$placeholders})";
        $this->bindings = array_merge($this->bindings, $values);
        
        return $this;
    }
    
    /**
     * Where between
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self {
        $this->wheres[] = "{$column} BETWEEN ? AND ?";
        $this->bindings[] = $min;
        $this->bindings[] = $max;
        
        return $this;
    }
    
    /**
     * Order by
     */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "{$column} {$direction}";
        return $this;
    }
    
    /**
     * Limit results
     */
    public function limit(int $limit): self {
        $this->limitValue = $limit;
        return $this;
    }
    
    /**
     * Offset results
     */
    public function offset(int $offset): self {
        $this->offsetValue = $offset;
        return $this;
    }
    
    /**
     * Select specific columns
     */
    public function select(array $columns): self {
        $this->selects = $columns;
        return $this;
    }
    
    /**
     * Build SQL query
     */
    protected function buildSql(): string {
        $sql = sprintf(
            "SELECT %s FROM %s",
            implode(', ', $this->selects),
            $this->table
        );
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        
        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        
        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }
        
        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }
        
        return $sql;
    }
    
    /**
     * Execute and get results
     */
    public function get(): array {
        $sql = $this->buildSql();
        $stmt = Connection::getInstance()->prepare($sql);
        $stmt->execute($this->bindings);
        
        $modelClass = $this->modelClass;
        return array_map(fn($row) => $modelClass::hydrate($row), $stmt->fetchAll());
    }
    
    /**
     * Get first result
     */
    public function first(): ?object {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }
    
    /**
     * Get first or throw
     */
    public function firstOrFail(): object {
        $result = $this->first();
        
        if (!$result) {
            throw new \RuntimeException("No results found for query on {$this->table}");
        }
        
        return $result;
    }
    
    /**
     * Count results
     */
    public function count(): int {
        $sql = sprintf("SELECT COUNT(*) as cnt FROM %s", $this->table);
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        
        $stmt = Connection::getInstance()->prepare($sql);
        $stmt->execute($this->bindings);
        
        return (int) $stmt->fetch()['cnt'];
    }
    
    /**
     * Check if any records exist
     */
    public function exists(): bool {
        return $this->count() > 0;
    }
    
    /**
     * Delete matching records
     */
    public function delete(): int {
        $sql = sprintf("DELETE FROM %s", $this->table);
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        
        $stmt = Connection::getInstance()->prepare($sql);
        $stmt->execute($this->bindings);
        
        return $stmt->rowCount();
    }
    
    /**
     * Update matching records
     */
    public function update(array $values): int {
        $sets = [];
        $bindings = [];
        
        foreach ($values as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s",
            $this->table,
            implode(', ', $sets)
        );
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        
        $bindings = array_merge($bindings, $this->bindings);
        
        $stmt = Connection::getInstance()->prepare($sql);
        $stmt->execute($bindings);
        
        return $stmt->rowCount();
    }
    
    /**
     * Pluck single column values
     */
    public function pluck(string $column): array {
        $this->selects = [$column];
        $sql = $this->buildSql();
        
        $stmt = Connection::getInstance()->prepare($sql);
        $stmt->execute($this->bindings);
        
        return array_column($stmt->fetchAll(), $column);
    }
}
```

---

## 📝 Example Usage

```php
// Find by ID
$exam = Exam::find(1);
$exam = Exam::findOrFail(1);

// Query builder
$exams = Exam::where('isEnabled', true)
    ->whereNull('parentExamId')
    ->orderBy('createdAt', 'DESC')
    ->limit(10)
    ->get();

// First result
$exam = Exam::where('slug', 'javascript-basics')->first();

// Create
$exam = Exam::create([
    'title' => 'New Exam',
    'slug' => 'new-exam',
    'description' => 'Description here',
    'markdownFilePath' => 'questions/new-exam.md',
]);

// Update
$exam->title = 'Updated Title';
$exam->save();

// Delete
$exam->delete();

// Count
$count = Exam::where('isEnabled', true)->count();

// Exists check
$exists = Exam::where('slug', 'test-exam')->exists();

// Bulk update
Exam::where('isEnabled', false)->update(['isEnabled' => true]);

// Bulk delete
Exam::where('createdAt', '<', '2025-01-01')->delete();
```

---

## ✅ Acceptance Criteria

### Model Class
- [ ] Abstract base class created with required properties
- [ ] Getter/setter magic methods working
- [ ] Type casting for int, bool, float, string, array, datetime
- [ ] JSON encoding/decoding for array fields
- [ ] Boolean fields converted to 0/1 for SQLite storage

### CRUD Operations
- [ ] `find($id)` returns model or null
- [ ] `findOrFail($id)` throws exception if not found
- [ ] `create($attributes)` inserts and returns model with ID
- [ ] `save()` handles both insert and update
- [ ] `delete()` removes record
- [ ] Timestamps auto-managed (createdAt, updatedAt)

### Query Builder
- [ ] `where()` supports 2 and 3 argument forms
- [ ] `whereNull()` and `whereNotNull()` work
- [ ] `whereIn()` handles arrays
- [ ] `whereBetween()` works for ranges
- [ ] `orderBy()` supports ASC/DESC
- [ ] `limit()` and `offset()` work
- [ ] `count()` returns integer
- [ ] `exists()` returns boolean
- [ ] Bulk `update()` and `delete()` work

### Security
- [ ] All queries use prepared statements
- [ ] No SQL injection vulnerabilities
- [ ] No raw SQL exposed to consumers

---

## 📝 Notes

- All models extend this base class
- No raw SQL should be written outside ORM classes
- Query builder is immutable - each method returns new builder
- Models use `hydrate()` to load from database rows

---

*Next: `04-enums-constants.md`*
