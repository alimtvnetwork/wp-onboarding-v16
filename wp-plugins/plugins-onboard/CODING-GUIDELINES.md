# Plugins Onboard - Coding Guidelines

## Core Principles

### 1. Simplicity First

**Keep end-level code simple and concise. Avoid unnecessary if/else statements.**

Push complexity into classes, not into calling code. Let classes handle their own validation.

```php
// ✅ CORRECT: Simple, concise
$this->oauth = new OnboardOAuth($this->db, $this->audit_logger);
OnboardLogger::debug('OAuth initialized');

// ❌ WRONG: Unnecessary conditionals at call site
if (OnboardBooleanHelpers::is_class_exists('OnboardOAuth') && OnboardBooleanHelpers::is_set($this->audit_logger)) {
    $this->oauth = new OnboardOAuth($this->db, $this->audit_logger);
    OnboardLogger::debug('OAuth initialized');
}
```

**Let the class constructor handle validation internally:**

```php
// Inside OnboardOAuth constructor
public function __construct($db, $audit_logger) {
    if (!$db || !$audit_logger) {
        throw new Exception('Database and audit logger required');
    }
    $this->db = $db;
    $this->audit_logger = $audit_logger;
}
```

### 2. Boolean Functions

**Use positive boolean functions with `is_` or `has_` prefix**

1. **ALWAYS** use positive words (no "not", "un-", "non-")
2. **NEVER** use negations (`!`) in if statements
3. Create separate positive functions for both cases
4. Keep functions under 15 lines maximum

### Boolean Helper Class

Use the `OnboardBooleanHelpers` class for all boolean checks:

```php
// ✅ CORRECT: Use positive boolean functions
if (OnboardBooleanHelpers::is_func_exists('my_function')) {
    // Function exists
}

if (OnboardBooleanHelpers::is_func_missing('my_function')) {
    // Function is missing
}

// ❌ WRONG: Don't use negations
if (!function_exists('my_function')) {
    // DON'T DO THIS
}
```

### Available Boolean Functions

#### Function Checks
- `is_func_exists($function_name)` - Returns true if function exists
- `is_func_missing($function_name)` - Returns true if function is missing

#### Class Checks
- `is_class_exists($class_name)` - Returns true if class exists
- `is_class_missing($class_name)` - Returns true if class is missing

#### Extension Checks
- `is_extension_loaded($extension_name)` - Returns true if extension is loaded
- `is_extension_missing($extension_name)` - Returns true if extension is missing

#### Directory Checks
- `is_dir_exists($dir_path)` - Returns true if directory exists
- `is_dir_missing($dir_path)` - Returns true if directory is missing
- `is_dir_writable($dir_path)` - Returns true if directory is writable
- `is_dir_readonly($dir_path)` - Returns true if directory is read-only

#### File Checks
- `is_file_exists($file_path)` - Returns true if file exists
- `is_file_missing($file_path)` - Returns true if file is missing

#### Value Checks
- `is_empty($value)` - Returns true if value is empty
- `has_content($value)` - Returns true if value has content
- `is_null($value)` - Returns true if value is null
- `is_set($value)` - Returns true if value is set (not null)

#### Database Checks
- `is_db_connected($db)` - Returns true if database is connected
- `is_db_disconnected($db)` - Returns true if database is disconnected

### Real-World Examples

#### Example 1: Checking for Missing Class

```php
// ✅ CORRECT: Positive conditional
if (OnboardBooleanHelpers::is_class_missing('OnboardDatabase')) {
    OnboardLogger::error('OnboardDatabase class not found');
    return null;
}

// ❌ WRONG: Negative conditional
if (!class_exists('OnboardDatabase')) {
    OnboardLogger::error('OnboardDatabase class not found');
    return null;
}
```

#### Example 2: Separate If Blocks

```php
// ✅ CORRECT: Separate positive conditionals
if (OnboardBooleanHelpers::is_class_exists('OnboardConfig')) {
    $this->config = OnboardConfig::get_instance();
    OnboardLogger::debug('Config initialized');
}

if (OnboardBooleanHelpers::is_class_missing('OnboardConfig')) {
    OnboardLogger::error('OnboardConfig class not found');
}

// ❌ WRONG: if-else with negation
if (class_exists('OnboardConfig')) {
    $this->config = OnboardConfig::get_instance();
} else {
    OnboardLogger::error('OnboardConfig class not found');
}
```

#### Example 3: Directory Checks

```php
// ✅ CORRECT: Positive checks (using positive words)
if (OnboardBooleanHelpers::is_dir_missing($db_dir)) {
    OnboardLogger::error('Directory does not exist');
    return;
}

if (OnboardBooleanHelpers::is_dir_readonly($db_dir)) {
    OnboardLogger::error('Directory is read-only');
    return;
}

// ❌ WRONG: Negative checks with negations
if (!is_dir($db_dir)) {
    OnboardLogger::error('Directory does not exist');
    return;
}

if (!is_writable($db_dir)) {
    OnboardLogger::error('Directory is not writable');
    return;
}
```

#### Example 4: File Protection

```php
// ✅ CORRECT
if (OnboardBooleanHelpers::is_file_missing($htaccess_file)) {
    file_put_contents($htaccess_file, $content);
    OnboardLogger::debug('Created .htaccess');
}

// ❌ WRONG
if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file, $content);
}
```

## Benefits of This Approach

1. **Readability**: Code reads naturally with positive statements
2. **Clarity**: Intention is immediately clear without mental negation
3. **Consistency**: All conditionals follow the same pattern
4. **Maintainability**: Easier to understand and modify code
5. **Error Prevention**: Reduces logical errors from double negatives

## When to Create New Boolean Functions

If you find yourself writing:
- `if (!something())` - Create `is_something_missing()` or use a positive opposite
- `if (something() === null)` - Create `is_something_null()`
- `if (empty(something()))` - Create `is_something_empty()`

### Naming Guidelines

Always provide BOTH versions using positive words:

**Good Pairs:**
- `is_exists()` ↔ `is_missing()`
- `is_writable()` ↔ `is_readonly()`
- `is_empty()` ↔ `has_content()`
- `is_null()` ↔ `is_set()`
- `is_enabled()` ↔ `is_disabled()`
- `is_valid()` ↔ `is_invalid()`
- `is_active()` ↔ `is_inactive()`

**Avoid These:**
- ❌ `is_not_writable()` → Use `is_readonly()`
- ❌ `is_not_empty()` → Use `has_content()`
- ❌ `is_not_null()` → Use `is_set()`
- ❌ `is_not_valid()` → Use `is_invalid()`

### Function Size Limit

**MAXIMUM 15 LINES per function**

If a function exceeds 15 lines, break it into smaller helper functions.

## Simplicity Guidelines

### Avoid Unnecessary Conditionals

**DON'T wrap every call in conditionals. Let classes validate themselves.**

```php
// ✅ CORRECT: Clean and simple
$this->oauth = new OnboardOAuth($this->db, $this->audit_logger);
$this->mutation_token = new OnboardMutationToken($this->db, $this->audit_logger);
$this->ip_whitelist = new OnboardIPWhitelist($this->db, $this->audit_logger);

// ❌ WRONG: Unnecessary checks everywhere
if (OnboardBooleanHelpers::is_class_exists('OnboardOAuth')) {
    if (OnboardBooleanHelpers::is_set($this->audit_logger)) {
        $this->oauth = new OnboardOAuth($this->db, $this->audit_logger);
    }
}
```

### Logging Should Be Self-Contained

**Logger methods should check if logging is enabled internally.**

```php
// ✅ CORRECT: Just log
OnboardLogger::debug('Initializing OAuth');
OnboardLogger::error('Failed to connect');

// ❌ WRONG: Checking before every log
if ($logging_enabled) {
    OnboardLogger::debug('Initializing OAuth');
}
```

### Push Validation into Classes

**Classes should validate their own requirements in constructors or methods.**

```php
// ✅ CORRECT: Validation in class
class OnboardAPI {
    public function __construct($db, $oauth, $token) {
        if (!$db || !$oauth || !$token) {
            throw new Exception('Missing required dependencies');
        }
        $this->db = $db;
        $this->oauth = $oauth;
        $this->token = $token;
    }
}

// Consumer code is clean:
$this->api = new OnboardAPI($this->db, $this->oauth, $this->mutation_token);
```

### Avoid If-Else Chains

**Use early returns instead of nested if-else.**

```php
// ✅ CORRECT: Early returns
public function process() {
    if (OnboardBooleanHelpers::is_empty($data)) {
        return false;
    }

    if (OnboardBooleanHelpers::is_invalid($data)) {
        return false;
    }

    return $this->save($data);
}

// ❌ WRONG: Nested if-else
public function process() {
    if (!empty($data)) {
        if ($this->validate($data)) {
            return $this->save($data);
        } else {
            return false;
        }
    } else {
        return false;
    }
}
```

## Initialization Order

Always follow this order:

1. **Directories First**: Use `OnboardInitHelpers::ensure_directories_exist()`
2. **Database Second**: Use `OnboardInitHelpers::ensure_database_ready()`
3. **Components Third**: Initialize all other components

This ensures proper dependency resolution and prevents initialization errors.
