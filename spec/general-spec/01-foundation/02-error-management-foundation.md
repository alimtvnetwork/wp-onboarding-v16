# 02. Error Management

> **Applies To:** All languages (PHP, TypeScript, Python)  
> **Priority:** CRITICAL - Proper error handling prevents silent failures

---

## 1. Core Principles

### 1.1 The Three Laws of Error Management

1. **Never swallow exceptions** - Every error must be logged or re-thrown
2. **Fail fast** - Detect and report errors at the earliest point
3. **Provide context** - Error messages must include actionable information

### 1.2 Error Philosophy

```
Exception → Log with context → Notify if critical → Return structured error
```

Never do this:
```php
try {
    $result = $service->process();
} catch (Exception $e) {
    // Silent failure - FORBIDDEN
}
```

---

## 2. Exception Hierarchy

### 2.1 Base Exception Pattern

All custom exceptions MUST extend a BaseException that auto-logs:

#### PHP Implementation

```php
<?php
declare(strict_types=1);

abstract class BaseException extends Exception {
    protected string $errorCode;
    protected array $context;
    
    public function __construct(
        string $message,
        string $errorCode,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->errorCode = $errorCode;
        $this->context = $context;
        
        // Auto-log on construction
        $this->logException();
    }
    
    private function logException(): void {
        $logData = [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
            'previous' => $this->getPrevious()?->getMessage(),
        ];
        
        Logger::error($this->errorCode . ': ' . $this->getMessage(), $logData);
    }
    
    public function getErrorCode(): string {
        return $this->errorCode;
    }
    
    public function getContext(): array {
        return $this->context;
    }
    
    public function toArray(): array {
        return [
            'error' => true,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
```

#### TypeScript Implementation

```typescript
export abstract class BaseException extends Error {
  readonly errorCode: string;
  readonly context: Record<string, unknown>;
  readonly timestamp: Date;
  
  constructor(
    message: string,
    errorCode: string,
    context: Record<string, unknown> = {},
    cause?: Error
  ) {
    super(message, { cause });
    this.name = this.constructor.name;
    this.errorCode = errorCode;
    this.context = context;
    this.timestamp = new Date();
    
    // Auto-log on construction
    this.logException();
  }
  
  private logException(): void {
    const logData = {
      errorCode: this.errorCode,
      message: this.message,
      context: this.context,
      stack: this.stack,
      cause: this.cause?.message,
      timestamp: this.timestamp.toISOString(),
    };
    
    console.error(`[${this.errorCode}] ${this.message}`, logData);
    
    // In production, send to logging service
    // LoggingService.error(this.errorCode, logData);
  }
  
  toJSON(): Record<string, unknown> {
    return {
      error: true,
      code: this.errorCode,
      message: this.message,
      context: this.context,
    };
  }
}
```

#### Python Implementation

```python
import traceback
from datetime import datetime
from typing import Optional, Any
import logging

logger = logging.getLogger(__name__)

class BaseException(Exception):
    def __init__(
        self,
        message: str,
        error_code: str,
        context: Optional[dict[str, Any]] = None,
        cause: Optional[Exception] = None
    ):
        super().__init__(message)
        self.error_code = error_code
        self.context = context or {}
        self.cause = cause
        self.timestamp = datetime.utcnow()
        
        # Auto-log on construction
        self._log_exception()
    
    def _log_exception(self) -> None:
        log_data = {
            'error_code': self.error_code,
            'message': str(self),
            'context': self.context,
            'traceback': traceback.format_exc(),
            'cause': str(self.cause) if self.cause else None,
            'timestamp': self.timestamp.isoformat(),
        }
        
        logger.error(f"[{self.error_code}] {self}", extra=log_data)
    
    def to_dict(self) -> dict[str, Any]:
        return {
            'error': True,
            'code': self.error_code,
            'message': str(self),
            'context': self.context,
        }
```

### 2.2 Exception Types

Create specific exception classes for each error category:

```php
// PHP Exception Hierarchy
class ValidationException extends BaseException {
    public function __construct(string $message, array $context = [], ?Throwable $previous = null) {
        parent::__construct($message, 'ERR_1001', $context, $previous);
    }
}

class NotFoundException extends BaseException {
    public function __construct(string $resource, mixed $id, ?Throwable $previous = null) {
        parent::__construct(
            "{$resource} with ID {$id} not found",
            'ERR_1002',
            ['resource' => $resource, 'id' => $id],
            $previous
        );
    }
}

class AuthenticationException extends BaseException {
    public function __construct(string $message, array $context = [], ?Throwable $previous = null) {
        parent::__construct($message, 'ERR_2001', $context, $previous);
    }
}

class AuthorizationException extends BaseException {
    public function __construct(string $action, string $resource, ?Throwable $previous = null) {
        parent::__construct(
            "Not authorized to {$action} on {$resource}",
            'ERR_2002',
            ['action' => $action, 'resource' => $resource],
            $previous
        );
    }
}

class DatabaseException extends BaseException {
    public function __construct(string $message, string $query, ?Throwable $previous = null) {
        parent::__construct($message, 'ERR_3001', ['query_type' => $this->extractQueryType($query)], $previous);
    }
    
    private function extractQueryType(string $query): string {
        return strtoupper(explode(' ', trim($query))[0] ?? 'UNKNOWN');
    }
}

class ExternalServiceException extends BaseException {
    public function __construct(string $service, int $statusCode, string $message, ?Throwable $previous = null) {
        parent::__construct(
            "External service '{$service}' failed: {$message}",
            'ERR_4001',
            ['service' => $service, 'status_code' => $statusCode],
            $previous
        );
    }
}

class BusinessLogicException extends BaseException {
    public function __construct(string $message, string $rule, array $context = [], ?Throwable $previous = null) {
        $context['rule'] = $rule;
        parent::__construct($message, 'ERR_5001', $context, $previous);
    }
}

class SystemException extends BaseException {
    public function __construct(string $message, array $context = [], ?Throwable $previous = null) {
        parent::__construct($message, 'ERR_9001', $context, $previous);
    }
}
```

---

## 3. Error Code System

### 3.1 Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| `ERR_1xxx` | Validation | Input validation, format errors |
| `ERR_2xxx` | Auth | Authentication and authorization |
| `ERR_3xxx` | Database | Query failures, connection issues |
| `ERR_4xxx` | External | Third-party API failures |
| `ERR_5xxx` | Business | Business rule violations |
| `ERR_6xxx` | File | File I/O, upload issues |
| `ERR_7xxx` | Configuration | Missing/invalid config |
| `ERR_8xxx` | Reserved | Future use |
| `ERR_9xxx` | System | Fatal, unrecoverable errors |

### 3.2 Code Registry

Maintain a centralized error code registry:

```php
// PHP
final class ErrorCodes {
    // Validation (1xxx)
    public const VALIDATION_FAILED = 'ERR_1001';
    public const RESOURCE_NOT_FOUND = 'ERR_1002';
    public const DUPLICATE_ENTRY = 'ERR_1003';
    public const INVALID_FORMAT = 'ERR_1004';
    public const REQUIRED_FIELD_MISSING = 'ERR_1005';
    public const VALUE_OUT_OF_RANGE = 'ERR_1006';
    
    // Authentication (2xxx)
    public const AUTHENTICATION_FAILED = 'ERR_2001';
    public const AUTHORIZATION_DENIED = 'ERR_2002';
    public const SESSION_EXPIRED = 'ERR_2003';
    public const INVALID_TOKEN = 'ERR_2004';
    public const RATE_LIMIT_EXCEEDED = 'ERR_2005';
    
    // Database (3xxx)
    public const DB_CONNECTION_FAILED = 'ERR_3001';
    public const DB_QUERY_FAILED = 'ERR_3002';
    public const DB_CONSTRAINT_VIOLATION = 'ERR_3003';
    public const DB_TRANSACTION_FAILED = 'ERR_3004';
    
    // External Services (4xxx)
    public const EXTERNAL_SERVICE_UNAVAILABLE = 'ERR_4001';
    public const EXTERNAL_SERVICE_TIMEOUT = 'ERR_4002';
    public const EXTERNAL_SERVICE_ERROR = 'ERR_4003';
    
    // Business Logic (5xxx)
    public const DEADLINE_PASSED = 'ERR_5001';
    public const QUOTA_EXCEEDED = 'ERR_5002';
    public const INVALID_STATE_TRANSITION = 'ERR_5003';
    public const PREREQUISITE_NOT_MET = 'ERR_5004';
    
    // System (9xxx)
    public const SYSTEM_ERROR = 'ERR_9001';
    public const CONFIGURATION_ERROR = 'ERR_9002';
    public const FATAL_ERROR = 'ERR_9999';
}
```

```typescript
// TypeScript
export const ErrorCodes = {
  // Validation (1xxx)
  VALIDATION_FAILED: 'ERR_1001',
  RESOURCE_NOT_FOUND: 'ERR_1002',
  DUPLICATE_ENTRY: 'ERR_1003',
  INVALID_FORMAT: 'ERR_1004',
  REQUIRED_FIELD_MISSING: 'ERR_1005',
  VALUE_OUT_OF_RANGE: 'ERR_1006',
  
  // Authentication (2xxx)
  AUTHENTICATION_FAILED: 'ERR_2001',
  AUTHORIZATION_DENIED: 'ERR_2002',
  SESSION_EXPIRED: 'ERR_2003',
  INVALID_TOKEN: 'ERR_2004',
  RATE_LIMIT_EXCEEDED: 'ERR_2005',
  
  // Database (3xxx)
  DB_CONNECTION_FAILED: 'ERR_3001',
  DB_QUERY_FAILED: 'ERR_3002',
  DB_CONSTRAINT_VIOLATION: 'ERR_3003',
  DB_TRANSACTION_FAILED: 'ERR_3004',
  
  // External Services (4xxx)
  EXTERNAL_SERVICE_UNAVAILABLE: 'ERR_4001',
  EXTERNAL_SERVICE_TIMEOUT: 'ERR_4002',
  EXTERNAL_SERVICE_ERROR: 'ERR_4003',
  
  // Business Logic (5xxx)
  DEADLINE_PASSED: 'ERR_5001',
  QUOTA_EXCEEDED: 'ERR_5002',
  INVALID_STATE_TRANSITION: 'ERR_5003',
  PREREQUISITE_NOT_MET: 'ERR_5004',
  
  // System (9xxx)
  SYSTEM_ERROR: 'ERR_9001',
  CONFIGURATION_ERROR: 'ERR_9002',
  FATAL_ERROR: 'ERR_9999',
} as const;

export type ErrorCode = typeof ErrorCodes[keyof typeof ErrorCodes];
```

---

## 4. Error Chaining

### 4.1 Always Preserve Original Errors

When catching and re-throwing, always chain the original exception:

#### ❌ INCORRECT - Original Error Lost

```php
try {
    $result = $database->query($sql);
} catch (PDOException $e) {
    throw new DatabaseException("Query failed"); // Original error lost!
}
```

#### ✅ CORRECT - Error Chained

```php
try {
    $result = $database->query($sql);
} catch (PDOException $e) {
    throw new DatabaseException(
        "Query failed: " . $e->getMessage(),
        $sql,
        $e  // Original exception preserved
    );
}
```

### 4.2 Multi-Level Chaining

```php
// Service layer catches and re-throws with context
class UserService {
    public function createUser(array $data): User {
        try {
            return $this->repository->create($data);
        } catch (DatabaseException $e) {
            throw new BusinessLogicException(
                "Failed to create user",
                'user_creation',
                ['email' => $data['email'] ?? 'unknown'],
                $e  // Chain the database exception
            );
        }
    }
}

// Controller catches and formats for API response
class UserController {
    public function create(Request $request): Response {
        try {
            $user = $this->userService->createUser($request->all());
            return Response::json($user, 201);
        } catch (BusinessLogicException $e) {
            return Response::json($e->toArray(), 400);
        }
    }
}
```

---

## 5. Context Requirements

### 5.1 Mandatory Context By Error Type

| Error Type | Required Context |
|------------|-----------------|
| Validation | `field`, `value`, `constraint` |
| NotFound | `resource`, `identifier` |
| Auth | `user_id`, `action`, `resource` |
| Database | `query_type`, `table` |
| External | `service`, `endpoint`, `status_code` |
| Business | `rule`, `entity_id`, `current_state` |

### 5.2 Context Examples

```php
// Validation error context
throw new ValidationException(
    "Email format invalid",
    [
        'field' => 'email',
        'value' => $email,
        'constraint' => 'email_format',
    ]
);

// Not found error context
throw new NotFoundException(
    "User",
    $userId,
    // Exception auto-adds: ['resource' => 'User', 'id' => $userId]
);

// Database error context
throw new DatabaseException(
    "Insert failed",
    "INSERT INTO users...",
    // Exception auto-extracts: ['query_type' => 'INSERT']
);
```

---

## 6. Helper Functions

### 6.1 logIfError Pattern

Reduce boilerplate with conditional logging helpers:

```php
class ErrorHelpers {
    /**
     * Log error if exception is not null
     */
    public static function logIfError(?Throwable $e, string $context): void {
        if (isNull($e)) {
            return;
        }
        
        Logger::error("{$context}: {$e->getMessage()}", [
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    
    /**
     * Execute callback and return null on exception
     */
    public static function tryOrNull(callable $callback): mixed {
        try {
            return $callback();
        } catch (Throwable $e) {
            self::logIfError($e, 'tryOrNull');
            return null;
        }
    }
    
    /**
     * Execute callback and return default on exception
     */
    public static function tryOrDefault(callable $callback, mixed $default): mixed {
        try {
            return $callback();
        } catch (Throwable $e) {
            self::logIfError($e, 'tryOrDefault');
            return $default;
        }
    }
    
    /**
     * Execute callback, log error, and re-throw
     */
    public static function tryOrThrow(callable $callback, string $context): mixed {
        try {
            return $callback();
        } catch (Throwable $e) {
            Logger::error("{$context}: {$e->getMessage()}", [
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }
}
```

```typescript
// TypeScript equivalent
export const ErrorHelpers = {
  logIfError(error: Error | null | undefined, context: string): void {
    if (isNullish(error)) return;
    
    console.error(`[${context}] ${error.message}`, {
      name: error.name,
      stack: error.stack,
    });
  },
  
  async tryOrNull<T>(fn: () => Promise<T>): Promise<T | null> {
    try {
      return await fn();
    } catch (error) {
      this.logIfError(error as Error, 'tryOrNull');
      return null;
    }
  },
  
  async tryOrDefault<T>(fn: () => Promise<T>, defaultValue: T): Promise<T> {
    try {
      return await fn();
    } catch (error) {
      this.logIfError(error as Error, 'tryOrDefault');
      return defaultValue;
    }
  },
};
```

---

## 7. API Error Responses

### 7.1 Standard Error Envelope

All API errors should follow a consistent structure:

```json
{
  "error": true,
  "code": "ERR_1001",
  "message": "Validation failed",
  "details": {
    "field": "email",
    "constraint": "email_format"
  },
  "timestamp": "2026-01-26T10:30:00Z",
  "request_id": "req_abc123"
}
```

### 7.2 HTTP Status Code Mapping

| Error Code Range | HTTP Status |
|-----------------|-------------|
| ERR_1xxx | 400 Bad Request |
| ERR_2001 | 401 Unauthorized |
| ERR_2002-2005 | 403 Forbidden |
| ERR_1002 (NotFound) | 404 Not Found |
| ERR_3xxx | 500 Internal Server Error |
| ERR_4xxx | 502 Bad Gateway |
| ERR_5xxx | 422 Unprocessable Entity |
| ERR_9xxx | 500 Internal Server Error |

### 7.3 Implementation

```php
class ApiErrorHandler {
    public function handle(Throwable $e): JsonResponse {
        $status = $this->getHttpStatus($e);
        
        $response = [
            'error' => true,
            'code' => $e instanceof BaseException ? $e->getErrorCode() : 'ERR_9001',
            'message' => $e->getMessage(),
            'timestamp' => date('c'),
            'request_id' => RequestContext::getId(),
        ];
        
        if ($e instanceof BaseException) {
            $response['details'] = $e->getContext();
        }
        
        // In development, include stack trace
        if (isEqual(getenv('APP_ENV'), 'development')) {
            $response['trace'] = $e->getTraceAsString();
        }
        
        return new JsonResponse($response, $status);
    }
    
    private function getHttpStatus(Throwable $e): int {
        return match (true) {
            $e instanceof ValidationException => 400,
            $e instanceof NotFoundException => 404,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof BusinessLogicException => 422,
            $e instanceof ExternalServiceException => 502,
            default => 500,
        };
    }
}
```

---

## 8. Anti-Patterns

### 8.1 Silent Catch

```php
// ❌ FORBIDDEN - Silent failure
try {
    $data = $service->fetch();
} catch (Exception $e) {
    // Nothing here - bug will be invisible
}

// ✅ CORRECT - Log and handle
try {
    $data = $service->fetch();
} catch (Exception $e) {
    Logger::error("Fetch failed", ['error' => $e->getMessage()]);
    throw new ServiceException("Data unavailable", $e);
}
```

### 8.2 Generic Exception Catch

```php
// ❌ INCORRECT - Too broad
try {
    $user = $this->createUser($data);
} catch (Exception $e) {
    return null; // What type of error? Validation? Database?
}

// ✅ CORRECT - Specific catches
try {
    $user = $this->createUser($data);
} catch (ValidationException $e) {
    return Response::json($e->toArray(), 400);
} catch (DatabaseException $e) {
    Logger::critical("Database error during user creation", $e->getContext());
    return Response::json(['error' => 'System error'], 500);
}
```

### 8.3 String-Based Error Checking

```php
// ❌ INCORRECT - Brittle string matching
try {
    $result = $service->process();
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'not found')) {
        // Handle not found
    }
}

// ✅ CORRECT - Type-based matching
try {
    $result = $service->process();
} catch (NotFoundException $e) {
    // Handle not found
} catch (ValidationException $e) {
    // Handle validation
}
```

### 8.4 Exposing Internal Errors

```php
// ❌ INCORRECT - Leaks internal details
return Response::json([
    'error' => $e->getMessage(), // "SQLSTATE[23000]: Duplicate entry for key 'email'"
]);

// ✅ CORRECT - User-friendly message
return Response::json([
    'error' => true,
    'code' => 'ERR_1003',
    'message' => 'An account with this email already exists',
]);
```

---

## 9. Stack Trace Management

### 9.1 What to Include

| Environment | Stack Trace Behavior |
|-------------|---------------------|
| Development | Full trace in response + logs |
| Staging | Full trace in logs only |
| Production | Full trace in logs, sanitized response |

### 9.2 Log Format

```php
[2026-01-26 10:30:00] ERROR ERR_3001: Database query failed
Context: {"query_type":"INSERT","table":"users"}
File: /app/src/Repository/UserRepository.php:45
Trace:
#0 /app/src/Service/UserService.php(23): UserRepository->create()
#1 /app/src/Controller/UserController.php(15): UserService->createUser()
#2 /app/vendor/framework/Router.php(100): UserController->create()
Previous: SQLSTATE[23000]: Integrity constraint violation
```

---

## Mandatory Implementation Checklist

Before considering any implementation complete, verify:

- [ ] All exceptions extend BaseException
- [ ] All exceptions auto-log on construction
- [ ] Error codes follow ERR_xxxx format with correct ranges
- [ ] Original exceptions are always chained (passed as `previous`)
- [ ] Required context is included for each error type
- [ ] API responses use standard error envelope
- [ ] HTTP status codes map correctly to error types
- [ ] No silent catches exist
- [ ] No generic `Exception` catches without re-throw
- [ ] Stack traces are logged but not exposed in production
- [ ] Error code registry is maintained and up-to-date

---

*This document establishes error management patterns. See [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) for detailed logging implementation.*
