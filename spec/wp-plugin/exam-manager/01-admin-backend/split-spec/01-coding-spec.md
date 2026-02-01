# 01 - Coding Specification

> **Phase:** Foundation (FIRST)  
> **Dependencies:** None  
> **Estimated Time:** 1-2 hours

---

## 📋 Scope

Define PHP coding standards, boolean helper patterns, and simplicity rules for the Exam Questions Manager plugin. This specification MUST be implemented BEFORE any feature code.

---

## 🎯 Core Philosophy

> **"Simplicity is the ultimate sophistication. Code should be readable, predictable, and self-documenting."**

### Fundamental Principles

1. **Positive Conditionals Only**: NEVER use negations (`!`) in if statements
2. **Simplicity First**: Avoid unnecessary if/else statements; push validation into classes
3. **Maximum 15 Lines Per Function**: Break larger functions into smaller helpers
4. **Early Returns**: Use early returns instead of nested if-else chains
5. **Self-Validating Classes**: Classes validate their own requirements in constructors

---

## 📁 File Structure

```
/src/
├── Constants/
│   ├── Consts.php           # Global constants
│   └── LogLevels.php        # Log level constants
├── Helpers/
│   ├── BooleanHelpers.php   # Positive conditional helpers
│   ├── ConditionalHelpers.php # logIf, execIf, returnIf functions
│   ├── FileHelpers.php      # File operation helpers
│   ├── FileLoaderHelpers.php # Safe require/include with logging
│   └── StringHelpers.php    # String validation helpers
└── Utils/
    └── Logger.php           # Enhanced logger (from Spec 07)
```

---

## 🔖 Constants Definition

**File:** `src/Constants/Consts.php`

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Constants;

/**
 * Global constants for the Exam Questions Manager plugin
 * 
 * @file src/Constants/Consts.php
 */
final class Consts {
    // Plugin Information
    public const PLUGIN_NAME = 'Exam Questions Manager';
    public const PLUGIN_SLUG = 'exam-questions-manager';
    public const PLUGIN_VERSION = '1.0.0';
    public const API_NAMESPACE = 'eqm/v1';
    
    // Database
    public const DB_VERSION = '1.0.0';
    public const DB_FILENAME = 'eqm-database.sqlite';
    
    // Limits
    public const MAX_FUNCTION_LINES = 15;
    public const MAX_FILE_UPLOAD_MB = 10;
    public const MAX_IMAGE_UPLOAD_MB = 5;
    public const MAX_BATCH_SIZE = 100;
    public const MAX_EXTENSION_DAYS = 90;
    
    // Timeouts (seconds)
    public const DB_TIMEOUT = 30;
    public const API_TIMEOUT = 60;
    public const CACHE_TTL = 3600;
    
    // Pagination
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;
    
    // Retry Logic
    public const MAX_EMAIL_ATTEMPTS = 3;
    public const RETRY_DELAY_BASE = 5; // minutes
    
    // Log Rotation
    public const LOG_MAX_SIZE_MB = 10;
    public const LOG_MAX_ARCHIVES = 5;
    
    // Prevent instantiation
    private function __construct() {}
}
```

---

## ✅ Boolean Helpers (CRITICAL)

**RULE: NEVER use negations (`!`) in if statements. Always use positive conditional helpers.**

**File:** `src/Helpers/BooleanHelpers.php`

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Helpers;

/**
 * Boolean helper functions for positive conditionals
 * 
 * USAGE: Always use these helpers instead of negations (!)
 * 
 * @file src/Helpers/BooleanHelpers.php
 */
final class BooleanHelpers {
    
    // ========================================
    // Function Existence
    // ========================================
    
    public static function is_func_exists(string $functionName): bool {
        return function_exists($functionName);
    }
    
    public static function is_func_missing(string $functionName): bool {
        return function_exists($functionName) === false;
    }
    
    // ========================================
    // Class Existence
    // ========================================
    
    public static function is_class_exists(string $className): bool {
        return class_exists($className);
    }
    
    public static function is_class_missing(string $className): bool {
        return class_exists($className) === false;
    }
    
    // ========================================
    // Extension Loaded
    // ========================================
    
    public static function is_extension_loaded(string $extension): bool {
        return extension_loaded($extension);
    }
    
    public static function is_extension_missing(string $extension): bool {
        return extension_loaded($extension) === false;
    }
    
    // ========================================
    // Directory Operations
    // ========================================
    
    public static function is_dir_exists(string $path): bool {
        return is_dir($path);
    }
    
    public static function is_dir_missing(string $path): bool {
        return is_dir($path) === false;
    }
    
    public static function is_dir_writable(string $path): bool {
        return is_dir($path) && is_writable($path);
    }
    
    public static function is_dir_readonly(string $path): bool {
        return is_dir($path) && is_writable($path) === false;
    }
    
    // ========================================
    // File Operations
    // ========================================
    
    public static function is_file_exists(string $path): bool {
        return file_exists($path) && is_file($path);
    }
    
    public static function is_file_missing(string $path): bool {
        return file_exists($path) === false || is_file($path) === false;
    }
    
    public static function is_file_readable(string $path): bool {
        return is_file($path) && is_readable($path);
    }
    
    public static function is_file_unreadable(string $path): bool {
        return is_file($path) === false || is_readable($path) === false;
    }
    
    // ========================================
    // Value Checks
    // ========================================
    
    public static function is_empty(mixed $value): bool {
        return empty($value);
    }
    
    public static function has_content(mixed $value): bool {
        return empty($value) === false;
    }
    
    public static function is_null(mixed $value): bool {
        return $value === null;
    }
    
    public static function is_set(mixed $value): bool {
        return $value !== null;
    }
    
    public static function is_blank(string $value): bool {
        return trim($value) === '';
    }
    
    public static function has_text(string $value): bool {
        return trim($value) !== '';
    }
    
    // ========================================
    // Array Checks
    // ========================================
    
    public static function is_array_empty(array $arr): bool {
        return count($arr) === 0;
    }
    
    public static function has_items(array $arr): bool {
        return count($arr) > 0;
    }
    
    public static function has_key(array $arr, string|int $key): bool {
        return array_key_exists($key, $arr);
    }
    
    public static function is_key_missing(array $arr, string|int $key): bool {
        return array_key_exists($key, $arr) === false;
    }
    
    // ========================================
    // Database Checks
    // ========================================
    
    public static function is_db_connected(\PDO $pdo = null): bool {
        if ($pdo === null) {
            return false;
        }
        try {
            $pdo->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
    
    public static function is_db_disconnected(\PDO $pdo = null): bool {
        return self::is_db_connected($pdo) === false;
    }
    
    // ========================================
    // String Validation
    // ========================================
    
    public static function is_valid_email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function is_invalid_email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) === false;
    }
    
    public static function is_valid_url(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    public static function is_invalid_url(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) === false;
    }
    
    // Prevent instantiation
    private function __construct() {}
}
```

---

## 📝 Usage Examples

### ✅ CORRECT - Positive Conditionals

```php
use ExamQuestionsManager\Helpers\BooleanHelpers;

// ✅ CORRECT: Positive conditional
if (BooleanHelpers::is_func_missing('my_custom_function')) {
    throw new \RuntimeException('Required function not found');
}

// ✅ CORRECT: Positive conditional
if (BooleanHelpers::is_file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
}

// ✅ CORRECT: Early return pattern
public function processParticipant(int $id): Participant {
    $participant = $this->repository->find($id);
    
    if (BooleanHelpers::is_null($participant)) {
        throw new NotFoundException("Participant not found: {$id}");
    }
    
    if (BooleanHelpers::is_blank($participant->email)) {
        throw new ValidationException("Participant email is required");
    }
    
    return $this->process($participant);
}
```

### ❌ WRONG - Negations

```php
// ❌ WRONG: Using negation
if (!function_exists('my_function')) { }

// ❌ WRONG: Using negation
if (!file_exists($path)) { }

// ❌ WRONG: Using negation
if (!$participant) { }

// ❌ WRONG: Using negation with empty
if (!empty($value)) { }
```

---

## 🔧 Conditional Helpers (If-Avoidance Pattern)

**RULE: Minimize `if` statements by using functional helpers that encapsulate conditions.**

**File:** `src/Helpers/ConditionalHelpers.php`

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Helpers;

use ExamQuestionsManager\Utils\Logger;
use ExamQuestionsManager\Enums\LogLevel;

/**
 * Conditional helper functions to reduce if/else clutter
 * 
 * USAGE: Use these helpers to avoid repetitive if patterns
 * 
 * @file src/Helpers/ConditionalHelpers.php
 */
final class ConditionalHelpers {
    
    // ========================================
    // Conditional Logging (logIf)
    // ========================================
    
    /**
     * Log message only if condition is true
     * Eliminates: if (condition) { Logger::info(...); }
     */
    public static function logIf(
        bool $condition, 
        LogLevel $level, 
        string $message, 
        array $context = []
    ): void {
        if ($condition === false) {
            return;
        }
        Logger::log($level, $message, $context);
    }
    
    /**
     * Log error only if throwable is not null
     * Eliminates: if ($e !== null) { Logger::exception($e); }
     */
    public static function logIfError(
        ?\Throwable $e, 
        string $context = '',
        array $extra = []
    ): void {
        if ($e === null) {
            return;
        }
        $extra['context'] = $context;
        Logger::exception($e, $extra);
    }
    
    /**
     * Log debug only in debug mode
     */
    public static function logIfDebug(string $message, array $context = []): void {
        self::logIf(
            defined('EQM_DEBUG') && EQM_DEBUG === true,
            LogLevel::DEBUG,
            $message,
            $context
        );
    }
    
    // ========================================
    // Conditional Execution (execIf)
    // ========================================
    
    /**
     * Execute callback only if condition is true
     * Eliminates: if (condition) { doSomething(); }
     */
    public static function execIf(bool $condition, callable $callback): mixed {
        if ($condition === false) {
            return null;
        }
        return $callback();
    }
    
    /**
     * Execute callback and return result, or default if condition false
     */
    public static function execIfOrDefault(
        bool $condition, 
        callable $callback, 
        mixed $default = null
    ): mixed {
        if ($condition === false) {
            return $default;
        }
        return $callback();
    }
    
    // ========================================
    // Conditional Returns (returnIf)
    // ========================================
    
    /**
     * Return value only if condition is true, otherwise null
     * Use in method chains or expressions
     */
    public static function returnIf(bool $condition, mixed $value): mixed {
        return $condition ? $value : null;
    }
    
    /**
     * Return first value if condition true, otherwise second
     * Ternary wrapper for readability
     */
    public static function choose(bool $condition, mixed $ifTrue, mixed $ifFalse): mixed {
        return $condition ? $ifTrue : $ifFalse;
    }
    
    // ========================================
    // Null-Safe Operations
    // ========================================
    
    /**
     * Execute callback if value is not null
     */
    public static function ifNotNull(mixed $value, callable $callback): mixed {
        if ($value === null) {
            return null;
        }
        return $callback($value);
    }
    
    /**
     * Return value or default if null
     */
    public static function orDefault(mixed $value, mixed $default): mixed {
        return $value ?? $default;
    }
    
    // Prevent instantiation
    private function __construct() {}
}
```

### Usage Examples - Conditional Helpers

```php
use ExamQuestionsManager\Helpers\ConditionalHelpers;
use ExamQuestionsManager\Enums\LogLevel;

// ✅ CORRECT: Using logIf instead of if + Logger
ConditionalHelpers::logIf(
    $participant->isFirstLogin(),
    LogLevel::INFO,
    'First-time participant login',
    ['participantId' => $participant->id]
);

// ❌ WRONG: Explicit if
if ($participant->isFirstLogin()) {
    Logger::info('First-time participant login', ['participantId' => $participant->id]);
}

// ✅ CORRECT: Using logIfError for exception handling
try {
    $result = $this->processData();
} catch (\Throwable $e) {
    ConditionalHelpers::logIfError($e, 'Data processing failed');
}

// ✅ CORRECT: Using execIf for conditional actions
ConditionalHelpers::execIf(
    $user->hasPermission('email'),
    fn() => $this->emailService->send($user)
);

// ✅ CORRECT: Using ifNotNull for null-safe operations
$displayName = ConditionalHelpers::ifNotNull(
    $participant->displayName,
    fn($name) => strtoupper($name)
);
```

---

## 📦 File Loader with Stack Trace Logging

**RULE: NEVER use raw `require` or `include`. Always use FileLoaderHelpers for automatic error logging.**

**File:** `src/Helpers/FileLoaderHelpers.php`

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Helpers;

use ExamQuestionsManager\Utils\Logger;
use ExamQuestionsManager\Enums\LogLevel;

/**
 * Safe file loading with automatic stack trace logging
 * 
 * USAGE: Use loadFiles() instead of raw require/include
 * 
 * @file src/Helpers/FileLoaderHelpers.php
 */
final class FileLoaderHelpers {
    
    /**
     * Load multiple PHP files with error tracking
     * 
     * @param array<string> $files Array of file paths to load
     * @param bool $throwOnFailure Whether to throw exception on load failure
     * @return array{loaded: string[], failed: string[]} Results
     */
    public static function loadFiles(
        array $files, 
        bool $throwOnFailure = true
    ): array {
        $results = ['loaded' => [], 'failed' => []];
        
        foreach ($files as $file) {
            $success = self::loadSingle($file);
            
            if ($success) {
                $results['loaded'][] = $file;
                continue;
            }
            
            $results['failed'][] = $file;
            
            if ($throwOnFailure) {
                throw new \RuntimeException("Failed to load required file: {$file}");
            }
        }
        
        return $results;
    }
    
    /**
     * Load single PHP file with stack trace on failure
     */
    public static function loadSingle(string $file): bool {
        // Check file existence first
        if (BooleanHelpers::is_file_missing($file)) {
            self::logLoadFailure($file, 'File does not exist');
            return false;
        }
        
        // Check readability
        if (BooleanHelpers::is_file_unreadable($file)) {
            self::logLoadFailure($file, 'File is not readable');
            return false;
        }
        
        // Attempt to load with error capture
        try {
            require_once $file;
            
            ConditionalHelpers::logIfDebug(
                "File loaded successfully: {$file}",
                ['file' => $file]
            );
            
            return true;
        } catch (\Throwable $e) {
            self::logLoadFailure($file, $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Load file only if condition is true
     */
    public static function loadIf(bool $condition, string $file): bool {
        if ($condition === false) {
            return true; // Skip intentionally, not a failure
        }
        return self::loadSingle($file);
    }
    
    /**
     * Load all files from a directory
     */
    public static function loadDirectory(
        string $directory, 
        string $pattern = '*.php'
    ): array {
        if (BooleanHelpers::is_dir_missing($directory)) {
            self::logLoadFailure($directory, 'Directory does not exist');
            return ['loaded' => [], 'failed' => [$directory]];
        }
        
        $files = glob("{$directory}/{$pattern}") ?: [];
        return self::loadFiles($files, false);
    }
    
    /**
     * Log file load failure with full stack trace
     * Goes to both plugin.log AND error.txt
     */
    private static function logLoadFailure(
        string $file, 
        string $reason, 
        ?\Throwable $exception = null
    ): void {
        $context = [
            'file' => $file,
            'reason' => $reason,
            'caller' => self::getCallerInfo(),
        ];
        
        // Log to general log
        Logger::error("FILE LOAD FAILED: {$file}", $context);
        
        // Log exception with stack trace if available
        if ($exception !== null) {
            Logger::exception($exception, $context);
        } else {
            // Create synthetic stack trace for non-exception failures
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $context['stack_trace'] = self::formatStackTrace($trace);
            Logger::error("Stack trace for file load failure", $context);
        }
    }
    
    /**
     * Get caller information for logging
     */
    private static function getCallerInfo(): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        
        // Skip internal calls (loadSingle, logLoadFailure)
        $caller = $trace[3] ?? $trace[2] ?? [];
        
        return [
            'file' => $caller['file'] ?? 'unknown',
            'line' => $caller['line'] ?? 0,
            'function' => $caller['function'] ?? 'unknown',
        ];
    }
    
    /**
     * Format stack trace for logging
     */
    private static function formatStackTrace(array $trace): string {
        $output = [];
        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? '?';
            $function = $frame['function'] ?? 'unknown';
            $class = isset($frame['class']) ? $frame['class'] . '::' : '';
            
            $output[] = "#{$index} {$file}:{$line} {$class}{$function}()";
        }
        return implode("\n", $output);
    }
    
    // Prevent instantiation
    private function __construct() {}
}
```

### Usage Examples - File Loader

```php
use ExamQuestionsManager\Helpers\FileLoaderHelpers;

// ✅ CORRECT: Load multiple files with automatic error logging
FileLoaderHelpers::loadFiles([
    __DIR__ . '/Helpers/BooleanHelpers.php',
    __DIR__ . '/Helpers/ConditionalHelpers.php',
    __DIR__ . '/Utils/Logger.php',
    __DIR__ . '/Services/ExamService.php',
]);

// ✅ CORRECT: Conditional loading
FileLoaderHelpers::loadIf(
    defined('EQM_ENABLE_ANALYTICS'),
    __DIR__ . '/Services/AnalyticsService.php'
);

// ✅ CORRECT: Load all services from directory
$results = FileLoaderHelpers::loadDirectory(__DIR__ . '/Services');

// Log summary
Logger::info('Services loaded', [
    'loaded_count' => count($results['loaded']),
    'failed_count' => count($results['failed'])
]);

// ❌ WRONG: Raw require without error handling
require_once 'SomeFile.php';

// ❌ WRONG: Manual file checking
if (file_exists($file)) {
    require_once $file;
}
```

---

## 🚫 If-Avoidance Guidelines

### Core Principle

> **Every repeated `if` pattern should become a function. Every `else` should be questioned.**

### Avoidance Patterns

| Pattern | Problem | Solution |
|---------|---------|----------|
| `if (condition) { log(); }` | Clutters code | Use `logIf()` |
| `if (exception) { log(); }` | Boilerplate | Use `logIfError()` |
| `if (condition) { doX(); } else { doY(); }` | Hard to read | Use `choose()` or early return |
| `if (val !== null) { use(val); }` | Null checks everywhere | Use `ifNotNull()` |
| `if (exists) { require(); }` | Unsafe loading | Use `FileLoaderHelpers` |

### When `if` Is Acceptable

1. **Guard clauses with early return** - Always acceptable
2. **Validation in constructors** - Required pattern
3. **Single-purpose conditionals** - When not repeated

### When to Create a Helper

Create a new helper when you see:
- Same `if` pattern repeated 3+ times
- `if` with complex boolean logic
- `if` followed by logging
- Nested `if` statements

---

## 📋 Simplicity Rules

### Push Validation Into Classes

```php
// ✅ CORRECT: Class validates itself
class Participant {
    private string $email;
    private string $displayName;
    
    public function __construct(string $email, string $displayName) {
        // Validation happens HERE, not in calling code
        $this->validateEmail($email);
        $this->validateDisplayName($displayName);
        
        $this->email = strtolower(trim($email));
        $this->displayName = trim($displayName);
    }
    
    private function validateEmail(string $email): void {
        if (BooleanHelpers::is_invalid_email($email)) {
            throw new ValidationException("Invalid email format: {$email}");
        }
    }
    
    private function validateDisplayName(string $name): void {
        if (BooleanHelpers::is_blank($name)) {
            throw new ValidationException("Display name is required");
        }
    }
}

// ✅ CORRECT: Simple calling code
$participant = new Participant($email, $displayName);
Logger::debug('Participant created');

// ❌ WRONG: Validation in calling code
if (BooleanHelpers::is_valid_email($email)) {
    if (BooleanHelpers::has_text($displayName)) {
        $participant = new Participant($email, $displayName);
    }
}
```

### Early Returns Over Nested If-Else

```php
// ✅ CORRECT: Early returns
public function processExtensionRequest(int $requestId): ExtensionRequest {
    $request = $this->repository->find($requestId);
    
    if (BooleanHelpers::is_null($request)) {
        throw new NotFoundException("Extension request not found");
    }
    
    if ($request->isExpired()) {
        throw new BusinessException("Request has expired");
    }
    
    if ($request->isAlreadyProcessed()) {
        throw new BusinessException("Request already processed");
    }
    
    return $this->doProcess($request);
}

// ❌ WRONG: Nested if-else
public function processExtensionRequest(int $requestId): ?ExtensionRequest {
    $request = $this->repository->find($requestId);
    
    if (BooleanHelpers::is_set($request)) {
        if ($request->isActive()) {
            if ($request->isPending()) {
                return $this->doProcess($request);
            } else {
                throw new BusinessException("Already processed");
            }
        } else {
            throw new BusinessException("Expired");
        }
    } else {
        throw new NotFoundException("Not found");
    }
}
```

### Maximum 15 Lines Per Function

```php
// ✅ CORRECT: Small focused functions
public function createParticipant(array $data): Participant {
    $this->validateParticipantData($data);
    $participant = $this->buildParticipant($data);
    $this->repository->save($participant);
    $this->queueWelcomeEmail($participant);
    Logger::info('Participant created', ['id' => $participant->getId()]);
    return $participant;
}

private function validateParticipantData(array $data): void {
    $required = ['email', 'examId', 'displayName'];
    foreach ($required as $field) {
        if (BooleanHelpers::is_key_missing($data, $field)) {
            throw new ValidationException("Missing required field: {$field}");
        }
    }
}

private function buildParticipant(array $data): Participant {
    return new Participant(
        email: $data['email'],
        examId: $data['examId'],
        displayName: $data['displayName'],
        softDeadline: $this->calculateSoftDeadline($data),
        hardDeadline: $this->calculateHardDeadline($data)
    );
}
```

---

## 📝 File Header Standard

Every PHP file MUST start with proper annotations and debug logging:

```php
<?php
/**
 * ExamService - Core exam management logic
 * 
 * @file src/Services/ExamService.php
 * @package ExamQuestionsManager
 */
declare(strict_types=1);

// Log file load for debugging
if (defined('EQM_DEBUG') && EQM_DEBUG === true) {
    error_log('[EQM] Loading: ' . __FILE__);
}

namespace ExamQuestionsManager\Services;

use ExamQuestionsManager\Helpers\BooleanHelpers;
use ExamQuestionsManager\Exceptions\ValidationException;
use ExamQuestionsManager\Utils\Logger;
// ... other imports
```

---

## 📌 Available Boolean Helpers (Quick Reference)

| Category | Positive | Negative |
|----------|----------|----------|
| Functions | `is_func_exists()` | `is_func_missing()` |
| Classes | `is_class_exists()` | `is_class_missing()` |
| Extensions | `is_extension_loaded()` | `is_extension_missing()` |
| Directories | `is_dir_exists()` | `is_dir_missing()` |
| Dir Write | `is_dir_writable()` | `is_dir_readonly()` |
| Files | `is_file_exists()` | `is_file_missing()` |
| File Read | `is_file_readable()` | `is_file_unreadable()` |
| Empty | `is_empty()` | `has_content()` |
| Null | `is_null()` | `is_set()` |
| Blank String | `is_blank()` | `has_text()` |
| Arrays | `is_array_empty()` | `has_items()` |
| Array Keys | `has_key()` | `is_key_missing()` |
| Database | `is_db_connected()` | `is_db_disconnected()` |
| Email | `is_valid_email()` | `is_invalid_email()` |
| URL | `is_valid_url()` | `is_invalid_url()` |

---

## ✅ Acceptance Criteria

### Constants
- [ ] `Consts.php` contains all global constants
- [ ] Constants are grouped logically with comments
- [ ] No magic numbers/strings in codebase

### Boolean Helpers
- [ ] All positive/negative pairs implemented
- [ ] No negations (`!`) used anywhere in codebase
- [ ] IDE autocompletion works for all helpers
- [ ] Helpers are stateless (static methods only)

### Code Quality
- [ ] No function exceeds 15 lines
- [ ] No nested if-else chains (max 1 level)
- [ ] Classes validate in constructors
- [ ] Early returns used consistently

### File Headers
- [ ] Every file has `@file` annotation
- [ ] Debug mode logs file loading
- [ ] `declare(strict_types=1)` on all files

---

## 📌 AI Instructions

When writing code for this project:

1. **BEFORE any code**: Check if `BooleanHelpers` has the needed method
2. **NEVER use `!`**: Always use the negative helper (e.g., `is_file_missing()` not `!file_exists()`)
3. **EARLY RETURNS**: Use guard clauses, not nested if-else
4. **15 LINE MAX**: Split functions that exceed this limit
5. **VALIDATE IN CLASS**: Constructors validate, callers stay simple

---

*Next: `02-error-management.md`*
