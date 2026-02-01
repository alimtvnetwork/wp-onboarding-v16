# 02 - Error Management

> **Phase:** Foundation (FIRST)  
> **Dependencies:** `01-coding-spec.md`  
> **Estimated Time:** 2-3 hours

---

## 📋 Scope

Define error handling architecture, exception classes, error codes, and logging standards. Error management is the FOUNDATION—implement it BEFORE any feature code.

---

## 🎯 Core Philosophy

> **"Error management is the foundation. Every error MUST include file path, stack trace, and standardized error codes. Handle errors inside functions, not at call sites."**

### Error Principles

1. **File Paths Are Mandatory**: Every error MUST include the source file path
2. **Stack Traces Required**: Every error log must capture full stack trace
3. **Handle Inside, Not Outside**: Log errors inside exception handlers, not at call sites
4. **Standardized Error Codes**: Use `ErrorCodes::` constants, never raw strings
5. **Auto-Logging Exceptions**: BaseException logs automatically on construction
6. **Context Is King**: Always include relevant context (IDs, values, state)

---

## 📁 File Structure

```
/src/
├── Constants/
│   └── ErrorCodes.php       # Error code constants
├── Exceptions/
│   ├── BaseException.php    # Base exception with stack trace
│   ├── ValidationException.php
│   ├── DatabaseException.php
│   ├── FileException.php
│   ├── NotFoundException.php
│   └── BusinessException.php
└── Utils/
    └── Logger.php           # Enhanced logger (from Spec 07)
```

---

## 🔖 Error Codes Definition

**File:** `src/Constants/ErrorCodes.php`

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Constants;

/**
 * Standardized error codes
 * 
 * Error Code Ranges (ALIGNED WITH general-spec/01-foundation/02-error-management-foundation.md):
 * - 1xxx: Validation Errors
 * - 2xxx: Authentication/Authorization Errors
 * - 3xxx: Database Errors
 * - 4xxx: External Service Errors
 * - 5xxx: Business Logic Errors
 * - 6xxx: File Errors
 * - 7xxx: Configuration Errors
 * - 9xxx: System/Fatal Errors
 * 
 * @file src/Constants/ErrorCodes.php
 */
final class ErrorCodes {
    // Validation Errors (1xxx)
    public const VALIDATION_FAILED = 'ERR_1001';
    public const RESOURCE_NOT_FOUND = 'ERR_1002';
    public const DUPLICATE_ENTRY = 'ERR_1003';
    public const INVALID_FORMAT = 'ERR_1004';
    public const REQUIRED_FIELD_MISSING = 'ERR_1005';
    public const VALUE_OUT_OF_RANGE = 'ERR_1006';
    public const INVALID_EMAIL_FORMAT = 'ERR_1007';
    public const INVALID_DATE_FORMAT = 'ERR_1008';
    public const INVALID_ENUM_VALUE = 'ERR_1009';
    public const STRING_TOO_LONG = 'ERR_1010';
    public const STRING_TOO_SHORT = 'ERR_1011';
    
    // Authentication/Authorization Errors (2xxx)
    public const AUTH_REQUIRED = 'ERR_2001';
    public const AUTH_DENIED = 'ERR_2002';
    public const SESSION_EXPIRED = 'ERR_2003';
    public const AUTH_INVALID_TOKEN = 'ERR_2004';
    public const RATE_LIMIT_EXCEEDED = 'ERR_2005';
    public const AUTH_INSUFFICIENT_PERMISSIONS = 'ERR_2006';
    public const AUTH_INVALID_SECRET_KEY = 'ERR_2007';
    public const AUTH_SECRET_KEY_EXPIRED = 'ERR_2008';
    
    // Database Errors (3xxx)
    public const DB_CONNECTION_FAILED = 'ERR_3001';
    public const DB_QUERY_FAILED = 'ERR_3002';
    public const DB_CONSTRAINT_VIOLATION = 'ERR_3003';
    public const DB_TRANSACTION_FAILED = 'ERR_3004';
    public const DB_RECORD_NOT_FOUND = 'ERR_3005';
    public const DB_DUPLICATE_ENTRY = 'ERR_3006';
    public const DB_FOREIGN_KEY_VIOLATION = 'ERR_3007';
    public const DB_MIGRATION_FAILED = 'ERR_3008';
    
    // External Service Errors (4xxx)
    public const EXTERNAL_SERVICE_UNAVAILABLE = 'ERR_4001';
    public const EXTERNAL_SERVICE_TIMEOUT = 'ERR_4002';
    public const EXTERNAL_SERVICE_ERROR = 'ERR_4003';
    
    // Business Logic Errors (5xxx)
    public const DEADLINE_PASSED = 'ERR_5001';
    public const QUOTA_EXCEEDED = 'ERR_5002';
    public const INVALID_STATE_TRANSITION = 'ERR_5003';
    public const PREREQUISITE_NOT_MET = 'ERR_5004';
    public const EXAM_NOT_FOUND = 'ERR_5005';
    public const PARTICIPANT_NOT_FOUND = 'ERR_5006';
    public const STATUS_TRANSITION_INVALID = 'ERR_5007';
    public const EXTENSION_LIMIT_REACHED = 'ERR_5008';
    public const CIRCULAR_REFERENCE = 'ERR_5009';
    public const WIKI_NOT_FOUND = 'ERR_5010';
    public const CHECKLIST_INCOMPLETE = 'ERR_5011';
    
    // File Errors (6xxx)
    public const FILE_NOT_FOUND = 'ERR_6001';
    public const FILE_NOT_READABLE = 'ERR_6002';
    public const FILE_NOT_WRITABLE = 'ERR_6003';
    public const FILE_UPLOAD_FAILED = 'ERR_6004';
    public const FILE_TOO_LARGE = 'ERR_6005';
    public const FILE_INVALID_TYPE = 'ERR_6006';
    public const DIR_NOT_FOUND = 'ERR_6007';
    public const DIR_NOT_WRITABLE = 'ERR_6008';
    
    // Configuration Errors (7xxx)
    public const CONFIG_MISSING = 'ERR_7001';
    public const CONFIG_INVALID = 'ERR_7002';
    
    // System/Fatal Errors (9xxx)
    public const SYSTEM_ERROR = 'ERR_9001';
    public const CONFIGURATION_ERROR = 'ERR_9002';
    public const FATAL_ERROR = 'ERR_9999';
    public const INTERNAL_ERROR = 'ERR_9003';
    public const EMAIL_SEND_FAILED = 'ERR_9004';
    public const CRON_JOB_FAILED = 'ERR_9005';
    
    private function __construct() {}
}
```

---

## 🔴 Base Exception with Stack Trace

**File:** `src/Exceptions/BaseException.php`

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Exceptions;

use ExamQuestionsManager\Utils\Logger;

/**
 * Base exception that automatically captures stack trace and logs
 * 
 * CRITICAL: This exception auto-logs on construction.
 * Do NOT log at call sites when throwing this exception.
 * 
 * @file src/Exceptions/BaseException.php
 */
class BaseException extends \Exception {
    protected string $errorCode;
    protected array $context;
    protected string $sourceFile;
    protected int $sourceLine;
    
    public function __construct(
        string $message,
        string $errorCode = 'ERR_9001',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->errorCode = $errorCode;
        $this->context = $context;
        
        // Capture source location
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? $trace[0];
        $this->sourceFile = $caller['file'] ?? 'unknown';
        $this->sourceLine = $caller['line'] ?? 0;
        
        // Auto-log the exception
        $this->logException();
    }
    
    protected function logException(): void {
        Logger::error($this->formatLogMessage(), [
            'error_code' => $this->errorCode,
            'file' => $this->sourceFile,
            'line' => $this->sourceLine,
            'context' => $this->context,
            'stack_trace' => $this->getTraceAsString(),
        ]);
    }
    
    protected function formatLogMessage(): string {
        return sprintf(
            '[%s] %s [file=%s, line=%d]',
            $this->errorCode,
            $this->message,
            basename($this->sourceFile),
            $this->sourceLine
        );
    }
    
    public function getErrorCode(): string {
        return $this->errorCode;
    }
    
    public function getContext(): array {
        return $this->context;
    }
    
    public function getSourceFile(): string {
        return $this->sourceFile;
    }
    
    public function getSourceLine(): int {
        return $this->sourceLine;
    }
    
    public function toArray(): array {
        return [
            'error' => $this->message,
            'code' => $this->errorCode,
            'file' => $this->sourceFile,
            'line' => $this->sourceLine,
            'context' => $this->context,
            'stack_trace' => $this->getTraceAsString(),
        ];
    }
    
    public function toApiResponse(): array {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->message,
            ],
        ];
    }
}
```

---

## 📦 Specialized Exceptions

### ValidationException

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Exceptions;

use ExamQuestionsManager\Constants\ErrorCodes;

/**
 * @file src/Exceptions/ValidationException.php
 */
class ValidationException extends BaseException {
    private string $field;
    
    public function __construct(
        string $message,
        string $field = '',
        array $context = []
    ) {
        $this->field = $field;
        $context['field'] = $field;
        parent::__construct($message, ErrorCodes::VALIDATION_FAILED, $context);
    }
    
    public function getField(): string {
        return $this->field;
    }
}
```

### DatabaseException

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Exceptions;

use ExamQuestionsManager\Constants\ErrorCodes;

/**
 * @file src/Exceptions/DatabaseException.php
 */
class DatabaseException extends BaseException {
    private string $query;
    
    public function __construct(
        string $message,
        string $code = null,
        string $query = '',
        array $context = []
    ) {
        $this->query = $query;
        $context['query'] = $this->sanitizeQuery($query);
        parent::__construct($message, $code ?? ErrorCodes::DB_QUERY_FAILED, $context);
    }
    
    private function sanitizeQuery(string $query): string {
        // Remove sensitive values from query for logging
        return preg_replace('/\'[^\']*\'/', "'***'", $query);
    }
    
    public function getQuery(): string {
        return $this->query;
    }
}
```

### FileException

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Exceptions;

use ExamQuestionsManager\Constants\ErrorCodes;

/**
 * @file src/Exceptions/FileException.php
 */
class FileException extends BaseException {
    protected string $filePath;
    protected string $operation;
    
    public function __construct(
        string $filePath,
        string $operation,
        string $message,
        string $code = null
    ) {
        $this->filePath = realpath($filePath) ?: $filePath;
        $this->operation = $operation;
        
        $fullMessage = sprintf(
            '%s [path=%s, operation=%s]',
            $message,
            $this->filePath,
            $operation
        );
        
        parent::__construct($fullMessage, $code ?? ErrorCodes::FILE_NOT_FOUND, [
            'path' => $this->filePath,
            'operation' => $operation,
        ]);
    }
    
    public function getFilePath(): string {
        return $this->filePath;
    }
    
    public function getOperation(): string {
        return $this->operation;
    }
}
```

### NotFoundException

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Exceptions;

use ExamQuestionsManager\Constants\ErrorCodes;

/**
 * @file src/Exceptions/NotFoundException.php
 */
class NotFoundException extends BaseException {
    private string $entityType;
    private mixed $entityId;
    
    public function __construct(
        string $entityType,
        mixed $entityId,
        string $message = null
    ) {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        
        $defaultMessage = sprintf('%s not found: %s', $entityType, $entityId);
        
        parent::__construct(
            $message ?? $defaultMessage,
            ErrorCodes::DB_RECORD_NOT_FOUND,
            ['entity_type' => $entityType, 'entity_id' => $entityId]
        );
    }
    
    public function getEntityType(): string {
        return $this->entityType;
    }
    
    public function getEntityId(): mixed {
        return $this->entityId;
    }
}
```

### BusinessException

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Exceptions;

use ExamQuestionsManager\Constants\ErrorCodes;

/**
 * @file src/Exceptions/BusinessException.php
 */
class BusinessException extends BaseException {
    public function __construct(
        string $message,
        string $code = null,
        array $context = []
    ) {
        parent::__construct($message, $code ?? ErrorCodes::INTERNAL_ERROR, $context);
    }
}
```

---

## 📝 Usage Patterns

### ✅ CORRECT - Handle Inside

```php
use ExamQuestionsManager\Exceptions\NotFoundException;
use ExamQuestionsManager\Helpers\BooleanHelpers;

// ✅ CORRECT: Exception logs itself, no logging at call site
public function getParticipant(int $id): Participant {
    $participant = $this->repository->find($id);
    
    if (BooleanHelpers::is_null($participant)) {
        // BaseException auto-logs with stack trace
        throw new NotFoundException('Participant', $id);
    }
    
    return $participant;
}

// ✅ CORRECT: Clean calling code
$participant = $this->getParticipant($id);
// No try-catch needed for expected flow
```

### ❌ WRONG - Handle Outside

```php
// ❌ WRONG: Logging at call site (redundant)
try {
    $participant = $this->getParticipant($id);
} catch (NotFoundException $e) {
    Logger::error('Participant not found', ['id' => $id]); // WRONG: Already logged!
    throw $e;
}

// ❌ WRONG: Manual stack trace
if (BooleanHelpers::is_null($participant)) {
    Logger::error('Not found', ['trace' => debug_backtrace()]); // WRONG: Use exception!
    return null;
}
```

---

## 📊 Error Log Format

Every error log entry follows this structure:

```
[LEVEL] [TIMESTAMP] [COMPONENT] Message [key=value pairs]
Stack Trace:
  ClassName::methodName
    /full/path/to/file.php:123
  ClassName2::methodName2
    /full/path/to/file2.php:456
```

**Example Output:**

```
[ERROR] [2026-01-24T10:15:30Z] [ParticipantService] 
[ERR_2003] Participant not found: 42 [file=ParticipantService.php, line=142]
Stack Trace:
  ExamQuestionsManager\Services\ParticipantService::find
    /var/www/plugins/exam-questions-manager/src/Services/ParticipantService.php:142
  ExamQuestionsManager\API\ParticipantController::show
    /var/www/plugins/exam-questions-manager/src/API/ParticipantController.php:89
```

---

## 🔄 Error Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                     Exception Flow                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. Error Detected                                          │
│     │                                                       │
│     ▼                                                       │
│  2. throw new SpecificException(message, context)           │
│     │                                                       │
│     ▼                                                       │
│  3. BaseException::__construct()                            │
│     ├── Captures stack trace                                │
│     ├── Captures file path + line                           │
│     ├── AUTO-LOGS with full context                         │
│     └── Stores error code                                   │
│     │                                                       │
│     ▼                                                       │
│  4. Exception bubbles up to API handler                     │
│     │                                                       │
│     ▼                                                       │
│  5. API returns $exception->toApiResponse()                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🛡️ API Error Handler

```php
<?php
/**
 * Global API error handler
 * 
 * @file src/API/ErrorHandler.php
 */
class ErrorHandler {
    public static function handle(\Throwable $e): \WP_REST_Response {
        // BaseException already logged - just return response
        if ($e instanceof BaseException) {
            return new \WP_REST_Response(
                $e->toApiResponse(),
                self::getHttpStatus($e->getErrorCode())
            );
        }
        
        // Unknown exception - wrap and log
        $wrapped = new BaseException(
            $e->getMessage(),
            ErrorCodes::INTERNAL_ERROR,
            ['original_class' => get_class($e)],
            $e
        );
        
        return new \WP_REST_Response(
            $wrapped->toApiResponse(),
            500
        );
    }
    
    private static function getHttpStatus(string $code): int {
        return match(true) {
            str_starts_with($code, 'ERR_1') => 400, // Validation
            str_starts_with($code, 'ERR_2') => 404, // Database/Not Found
            str_starts_with($code, 'ERR_3') => 400, // File
            str_starts_with($code, 'ERR_4') => 401, // Auth
            str_starts_with($code, 'ERR_5') => 422, // Business Logic
            default => 500,
        };
    }
}
```

---

## ⚙️ Configuration Hierarchy

> **Pattern:** JSON Seed → Settings DB → Consts Fallback

All configurable error/logging values follow a three-tier hierarchy:

```
┌─────────────────────────────────────────────────────────────┐
│  1. INSTALL/MIGRATION (one-time seed)                       │
│     config/defaults.json → seeds → eqm_settings table       │
├─────────────────────────────────────────────────────────────┤
│  2. RUNTIME (always)                                        │
│     Settings::get('key') → fallback to Consts::DEFAULT      │
└─────────────────────────────────────────────────────────────┘
```

### Configurable Values

| Setting Key | Default | Description |
|------------|---------|-------------|
| `log_max_size_mb` | 10 | Max log file size before rotation |
| `log_retention_days` | 30 | Days to keep archived logs |
| `log_archive_limit` | 90 | Days before deleting old archives |
| `error_buffer_size` | 100 | In-memory buffer before flush |

### Seeding JSON (`config/defaults.json`)

```json
{
  "logging": {
    "log_max_size_mb": 10,
    "log_retention_days": 30,
    "log_archive_limit": 90,
    "error_buffer_size": 100
  }
}
```

### Constants Fallback (`src/Constants/Consts.php`)

```php
<?php
declare(strict_types=1);
namespace ExamQuestionsManager\Constants;

/**
 * Default constants - ultimate fallback when DB unavailable
 * @file src/Constants/Consts.php
 */
final class Consts {
    // Logging defaults
    public const LOG_MAX_SIZE_MB = 10;
    public const LOG_RETENTION_DAYS = 30;
    public const LOG_ARCHIVE_LIMIT = 90;
    public const ERROR_BUFFER_SIZE = 100;
    
    public static function getDefault(string $key): mixed {
        return match($key) {
            'log_max_size_mb' => self::LOG_MAX_SIZE_MB,
            'log_retention_days' => self::LOG_RETENTION_DAYS,
            'log_archive_limit' => self::LOG_ARCHIVE_LIMIT,
            'error_buffer_size' => self::ERROR_BUFFER_SIZE,
            default => null,
        };
    }
    
    private function __construct() {}
}
```

### Settings Service Usage

```php
<?php
use ExamQuestionsManager\Services\Settings;
use ExamQuestionsManager\Constants\Consts;

// Runtime always reads from Settings with Consts fallback
$maxSize = Settings::get('log_max_size_mb', Consts::LOG_MAX_SIZE_MB);

// Logger uses this pattern
public static function rotateIfNeeded(): void {
    $maxSize = Settings::get('log_max_size_mb', Consts::LOG_MAX_SIZE_MB) * 1024 * 1024;
    
    if (BooleanHelpers::is_file_larger_than(self::$logPath, $maxSize)) {
        self::archiveAndRotate();
    }
}
```

---

## 📁 Log Rotation System

### Archive Structure

```
/logs/
├── plugin.log           # Current active log
├── error.txt            # Current error log
└── archive/
    ├── 1/               # Oldest archive
    │   ├── plugin.log.gz
    │   └── error.txt.gz
    ├── 2/
    │   ├── plugin.log.gz
    │   └── error.txt.gz
    └── 3/               # Most recent archive
        ├── plugin.log.gz
        └── error.txt.gz
```

### Rotation Logic

```php
<?php
/**
 * @file src/Utils/LogRotator.php
 */
class LogRotator {
    private const MAX_ARCHIVES = 10;
    
    public static function rotate(string $logPath): void {
        $archiveDir = dirname($logPath) . '/archive';
        
        // Shift existing archives: 3→4, 2→3, 1→2
        self::shiftArchives($archiveDir);
        
        // Create new archive at position 1
        self::createArchive($logPath, $archiveDir . '/1');
        
        // Truncate current log
        file_put_contents($logPath, '');
        
        // Prune archives exceeding limit
        self::pruneOldArchives($archiveDir);
    }
    
    private static function shiftArchives(string $dir): void {
        $limit = Settings::get('log_archive_limit', Consts::LOG_ARCHIVE_LIMIT);
        
        for ($i = self::MAX_ARCHIVES; $i >= 1; $i--) {
            $current = "$dir/$i";
            $next = "$dir/" . ($i + 1);
            
            if (BooleanHelpers::is_dir_exists($current)) {
                rename($current, $next);
            }
        }
    }
    
    private static function createArchive(string $source, string $destDir): void {
        if (BooleanHelpers::is_dir_missing($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        $destFile = $destDir . '/' . basename($source) . '.gz';
        $data = file_get_contents($source);
        file_put_contents($destFile, gzencode($data, 9));
    }
}
```

---

## 🔧 Helper: logIfError

Reduces boilerplate for conditional error logging:

```php
<?php
/**
 * One-liner error logging helper
 * @file src/Utils/Logger.php
 */
class Logger {
    // ... existing methods ...
    
    /**
     * Log error only if exception is not null
     * 
     * Usage: Logger::logIfError($e, 'Failed to process exam');
     */
    public static function logIfError(?Throwable $e, string $context): void {
        if (BooleanHelpers::is_null($e)) {
            return;
        }
        
        self::error("$context: {$e->getMessage()}", [
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

// Usage example
$error = $this->tryOperation();
Logger::logIfError($error, 'Processing participant registration');
```

---

## 📋 Required Context Fields by Category

Different error categories require specific context fields for effective debugging:

| Error Category | Required Context Fields |
|---------------|------------------------|
| **Database** | `query_type`, `table`, `operation` |
| **File Operations** | `path`, `operation`, `permissions` |
| **Validation** | `field`, `value`, `constraint` |
| **Authentication** | `user_id`, `action`, `resource` |
| **Business Logic** | `entity_type`, `entity_id`, `state` |
| **External Service** | `service_name`, `endpoint`, `status_code` |

### Implementation

```php
<?php
/**
 * @file src/Exceptions/DatabaseException.php
 */
class DatabaseException extends BaseException {
    // Required context enforced in constructor
    public function __construct(
        string $message,
        string $queryType,      // Required: SELECT, INSERT, UPDATE, DELETE
        string $table,          // Required: Table name
        string $operation = '', // Optional: Specific operation
        array $additionalContext = []
    ) {
        $context = array_merge([
            'query_type' => $queryType,
            'table' => $table,
            'operation' => $operation,
        ], $additionalContext);
        
        parent::__construct($message, ErrorCodes::DB_QUERY_FAILED, $context);
    }
}

// Usage
throw new DatabaseException(
    'Failed to update participant status',
    'UPDATE',
    'eqm_participants',
    'status_transition'
);
```

---

## ✅ Acceptance Criteria

### Error Codes
- [ ] `ErrorCodes.php` defines all standardized codes
- [ ] Error codes follow range conventions (1xxx-9xxx)
- [ ] All exceptions use `ErrorCodes::` constants
- [ ] No hardcoded error strings

### Base Exception
- [ ] Every exception includes file path and line number
- [ ] Stack traces captured automatically
- [ ] Exceptions auto-log on construction
- [ ] `toArray()` and `toApiResponse()` methods work

### Specialized Exceptions
- [ ] ValidationException captures field name
- [ ] DatabaseException sanitizes query logs
- [ ] FileException captures operation type
- [ ] NotFoundException captures entity info
- [ ] BusinessException handles general cases

### Configuration Hierarchy
- [ ] JSON seed file exists at `config/defaults.json`
- [ ] Migration seeds settings to database on install
- [ ] Settings::get() falls back to Consts on DB failure
- [ ] All configurable values use hierarchy pattern

### Log Rotation
- [ ] Numbered archive directories (1, 2, 3...)
- [ ] Compression with gzip
- [ ] Configurable max size and retention
- [ ] Oldest archives pruned automatically

### Logging
- [ ] Log format follows standard structure
- [ ] Stack traces are complete and readable
- [ ] Context is included in all logs
- [ ] No duplicate logging (call site + exception)
- [ ] `logIfError()` helper available

---

## 📌 AI Instructions

When handling errors in this project:

1. **THROW EXCEPTIONS**: Never return null/false for errors - throw typed exceptions
2. **USE ERROR CODES**: Reference `ErrorCodes::` constants, not strings
3. **INCLUDE CONTEXT**: Always pass relevant context array
4. **DON'T LOG TWICE**: BaseException logs automatically - don't log at call site
5. **FILE PATHS MANDATORY**: Every error includes the source file via stack trace
6. **TYPED EXCEPTIONS**: Use specific exception types, not generic Exception
7. **CONFIG HIERARCHY**: Use `Settings::get()` with `Consts::` fallback for configurable values
8. **REQUIRED FIELDS**: Include category-specific required context fields

---

*Next: `03-plugin-structure.md`*
