# 05. Conditional Helpers

> **Applies To:** All languages (PHP, TypeScript, Python)  
> **Priority:** HIGH - Reduces boilerplate and enforces consistent patterns

---

## 1. Core Principles

### 1.1 The Problem With Traditional Conditionals

Traditional if-statements lead to:
- Boilerplate code
- Inconsistent patterns
- Nested complexity
- Hard-to-test logic

### 1.2 The Solution: Conditional Helpers

Replace common conditional patterns with helper functions that:
- Reduce code duplication
- Enforce consistent behavior
- Improve readability
- Simplify testing

---

## 2. Core Helper Functions

### 2.1 execIf - Conditional Execution

Execute a callback only if a condition is true.

#### PHP Implementation

```php
class ConditionalHelpers {
    /**
     * Execute callback if condition is true
     * 
     * @param bool $condition The condition to check
     * @param callable $callback The callback to execute
     * @param mixed ...$args Arguments to pass to callback
     * @return mixed|null Result of callback or null
     */
    public static function execIf(bool $condition, callable $callback, mixed ...$args): mixed {
        if (isFalse($condition)) {
            return null;
        }
        
        return $callback(...$args);
    }
    
    /**
     * Execute callback if condition is false
     */
    public static function execUnless(bool $condition, callable $callback, mixed ...$args): mixed {
        return self::execIf(isFalse($condition), $callback, ...$args);
    }
    
    /**
     * Execute one of two callbacks based on condition
     */
    public static function execIfElse(
        bool $condition,
        callable $ifTrue,
        callable $ifFalse,
        mixed ...$args
    ): mixed {
        return $condition ? $ifTrue(...$args) : $ifFalse(...$args);
    }
}
```

#### TypeScript Implementation

```typescript
export const ConditionalHelpers = {
  /**
   * Execute callback if condition is true
   */
  execIf<T>(condition: boolean, callback: () => T): T | null {
    return condition ? callback() : null;
  },
  
  /**
   * Execute callback if condition is false
   */
  execUnless<T>(condition: boolean, callback: () => T): T | null {
    return condition ? null : callback();
  },
  
  /**
   * Execute one of two callbacks based on condition
   */
  execIfElse<T, U>(
    condition: boolean,
    ifTrue: () => T,
    ifFalse: () => U
  ): T | U {
    return condition ? ifTrue() : ifFalse();
  },
  
  /**
   * Async version of execIf
   */
  async execIfAsync<T>(
    condition: boolean,
    callback: () => Promise<T>
  ): Promise<T | null> {
    return condition ? await callback() : null;
  },
};
```

#### Python Implementation

```python
from typing import TypeVar, Callable, Optional, Any

T = TypeVar('T')
U = TypeVar('U')

class ConditionalHelpers:
    @staticmethod
    def exec_if(condition: bool, callback: Callable[..., T], *args: Any) -> Optional[T]:
        """Execute callback if condition is true"""
        if not condition:
            return None
        return callback(*args)
    
    @staticmethod
    def exec_unless(condition: bool, callback: Callable[..., T], *args: Any) -> Optional[T]:
        """Execute callback if condition is false"""
        return ConditionalHelpers.exec_if(not condition, callback, *args)
    
    @staticmethod
    def exec_if_else(
        condition: bool,
        if_true: Callable[..., T],
        if_false: Callable[..., U],
        *args: Any
    ) -> T | U:
        """Execute one of two callbacks based on condition"""
        return if_true(*args) if condition else if_false(*args)
```

### 2.2 logIf - Conditional Logging

Log only when a condition is met.

#### PHP Implementation

```php
class ConditionalHelpers {
    /**
     * Log message if condition is true
     */
    public static function logIf(bool $condition, string $level, string $message, array $context = []): void {
        if (isFalse($condition)) {
            return;
        }
        
        match ($level) {
            'debug' => Logger::debug($message, $context),
            'info' => Logger::info($message, $context),
            'warning' => Logger::warning($message, $context),
            'error' => Logger::error($message, $context),
            'critical' => Logger::critical($message, $context),
            default => Logger::info($message, $context),
        };
    }
    
    /**
     * Log error if exception is not null
     */
    public static function logIfError(?Throwable $error, string $context): void {
        if (isNull($error)) {
            return;
        }
        
        Logger::error("{$context}: {$error->getMessage()}", [
            'exception' => get_class($error),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ]);
    }
    
    /**
     * Log warning if value exceeds threshold
     */
    public static function logIfExceeds(
        float $value,
        float $threshold,
        string $message,
        array $context = []
    ): void {
        if ($value <= $threshold) {
            return;
        }
        
        Logger::warning($message, array_merge($context, [
            'value' => $value,
            'threshold' => $threshold,
        ]));
    }
}
```

#### TypeScript Implementation

```typescript
export const ConditionalHelpers = {
  /**
   * Log if condition is true
   */
  logIf(
    condition: boolean,
    level: 'debug' | 'info' | 'warn' | 'error',
    message: string,
    context?: Record<string, unknown>
  ): void {
    if (!condition) return;
    
    const logFn = {
      debug: console.debug,
      info: console.info,
      warn: console.warn,
      error: console.error,
    }[level];
    
    logFn(message, context);
  },
  
  /**
   * Log error if not null/undefined
   */
  logIfError(error: Error | null | undefined, context: string): void {
    if (!error) return;
    
    console.error(`${context}: ${error.message}`, {
      name: error.name,
      stack: error.stack,
    });
  },
  
  /**
   * Log warning if value exceeds threshold
   */
  logIfExceeds(
    value: number,
    threshold: number,
    message: string,
    context?: Record<string, unknown>
  ): void {
    if (value <= threshold) return;
    
    console.warn(message, { ...context, value, threshold });
  },
};
```

### 2.3 returnIf - Conditional Return Values

Return different values based on conditions.

#### PHP Implementation

```php
class ConditionalHelpers {
    /**
     * Return first value if condition true, second otherwise
     */
    public static function returnIf(bool $condition, mixed $ifTrue, mixed $ifFalse = null): mixed {
        return $condition ? $ifTrue : $ifFalse;
    }
    
    /**
     * Return value if not null, default otherwise
     */
    public static function returnIfNotNull(mixed $value, mixed $default): mixed {
        return isNotNull($value) ? $value : $default;
    }
    
    /**
     * Return value if not empty, default otherwise
     */
    public static function returnIfNotEmpty(mixed $value, mixed $default): mixed {
        return isNotEmpty($value) ? $value : $default;
    }
    
    /**
     * Return first non-null value from list
     */
    public static function coalesce(mixed ...$values): mixed {
        foreach ($values as $value) {
            if (isNotNull($value)) {
                return $value;
            }
        }
        return null;
    }
}
```

#### TypeScript Implementation

```typescript
export const ConditionalHelpers = {
  /**
   * Return first value if condition true, second otherwise
   */
  returnIf<T, U>(condition: boolean, ifTrue: T, ifFalse: U): T | U {
    return condition ? ifTrue : ifFalse;
  },
  
  /**
   * Return value if not nullish, default otherwise
   */
  returnIfNotNull<T>(value: T | null | undefined, defaultValue: T): T {
    return value ?? defaultValue;
  },
  
  /**
   * Return first defined value (coalesce)
   */
  coalesce<T>(...values: (T | null | undefined)[]): T | null {
    for (const value of values) {
      if (value != null) return value;
    }
    return null;
  },
};
```

---

## 3. Array/Collection Helpers

### 3.1 filterIf - Conditional Filtering

#### PHP Implementation

```php
class ConditionalHelpers {
    /**
     * Apply filter only if condition is true
     */
    public static function filterIf(array $array, bool $condition, callable $callback): array {
        if (isFalse($condition)) {
            return $array;
        }
        
        return array_filter($array, $callback);
    }
    
    /**
     * Apply multiple conditional filters
     */
    public static function filterWhere(array $array, array $conditions): array {
        $result = $array;
        
        foreach ($conditions as $key => $value) {
            if (isNull($value)) {
                continue; // Skip null conditions
            }
            
            $result = array_filter($result, fn($item) => 
                is_object($item) 
                    ? $item->{$key} === $value 
                    : $item[$key] === $value
            );
        }
        
        return array_values($result);
    }
}
```

#### TypeScript Implementation

```typescript
export const ConditionalHelpers = {
  /**
   * Apply filter only if condition is true
   */
  filterIf<T>(
    array: T[],
    condition: boolean,
    predicate: (item: T) => boolean
  ): T[] {
    return condition ? array.filter(predicate) : array;
  },
  
  /**
   * Apply multiple conditional filters
   */
  filterWhere<T extends Record<string, unknown>>(
    array: T[],
    conditions: Partial<T>
  ): T[] {
    return array.filter(item => {
      for (const [key, value] of Object.entries(conditions)) {
        if (value === null || value === undefined) continue;
        if (item[key] !== value) return false;
      }
      return true;
    });
  },
};
```

### 3.2 mapIf - Conditional Mapping

```php
class ConditionalHelpers {
    /**
     * Apply map only if condition is true
     */
    public static function mapIf(array $array, bool $condition, callable $callback): array {
        if (isFalse($condition)) {
            return $array;
        }
        
        return array_map($callback, $array);
    }
    
    /**
     * Map with index
     */
    public static function mapWithIndex(array $array, callable $callback): array {
        $result = [];
        $index = 0;
        
        foreach ($array as $key => $value) {
            $result[$key] = $callback($value, $index, $key);
            $index++;
        }
        
        return $result;
    }
}
```

---

## 4. Validation Helpers

### 4.1 validateIf - Conditional Validation

```php
class ConditionalHelpers {
    /**
     * Validate only if condition is true
     */
    public static function validateIf(
        bool $condition,
        mixed $value,
        callable $validator,
        string $errorMessage
    ): ?string {
        if (isFalse($condition)) {
            return null; // Skip validation
        }
        
        if (isFalse($validator($value))) {
            return $errorMessage;
        }
        
        return null;
    }
    
    /**
     * Validate and throw if invalid
     */
    public static function validateOrThrow(
        mixed $value,
        callable $validator,
        string $errorCode,
        string $errorMessage
    ): void {
        if (isTrue($validator($value))) {
            return;
        }
        
        throw new ValidationException($errorMessage, $errorCode);
    }
    
    /**
     * Validate multiple fields
     */
    public static function validateAll(array $rules): array {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $error = self::validateIf(
                $rule['condition'] ?? true,
                $rule['value'],
                $rule['validator'],
                $rule['message']
            );
            
            if (isNotNull($error)) {
                $errors[$field] = $error;
            }
        }
        
        return $errors;
    }
}
```

```typescript
export const ConditionalHelpers = {
  /**
   * Validate only if condition is true
   */
  validateIf<T>(
    condition: boolean,
    value: T,
    validator: (v: T) => boolean,
    errorMessage: string
  ): string | null {
    if (!condition) return null;
    return validator(value) ? null : errorMessage;
  },
  
  /**
   * Validate and throw if invalid
   */
  validateOrThrow<T>(
    value: T,
    validator: (v: T) => boolean,
    errorMessage: string
  ): void {
    if (!validator(value)) {
      throw new Error(errorMessage);
    }
  },
  
  /**
   * Validate multiple fields
   */
  validateAll(rules: Array<{
    field: string;
    value: unknown;
    validator: (v: unknown) => boolean;
    message: string;
    condition?: boolean;
  }>): Record<string, string> {
    const errors: Record<string, string> = {};
    
    for (const rule of rules) {
      if (rule.condition === false) continue;
      if (!rule.validator(rule.value)) {
        errors[rule.field] = rule.message;
      }
    }
    
    return errors;
  },
};
```

---

## 5. Async Helpers

### 5.1 retryIf - Conditional Retry

```php
class ConditionalHelpers {
    /**
     * Retry operation if it fails
     */
    public static function retryIf(
        callable $operation,
        callable $shouldRetry,
        int $maxAttempts = 3,
        int $delayMs = 1000
    ): mixed {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $maxAttempts) {
            try {
                return $operation();
            } catch (Throwable $e) {
                $lastException = $e;
                $attempt++;
                
                if (isFalse($shouldRetry($e)) || $attempt >= $maxAttempts) {
                    break;
                }
                
                usleep($delayMs * 1000);
            }
        }
        
        throw $lastException;
    }
    
    /**
     * Retry with exponential backoff
     */
    public static function retryWithBackoff(
        callable $operation,
        int $maxAttempts = 3,
        int $initialDelayMs = 100
    ): mixed {
        return self::retryIf(
            $operation,
            fn() => true,
            $maxAttempts,
            $initialDelayMs
        );
    }
}
```

```typescript
export const ConditionalHelpers = {
  /**
   * Retry operation if it fails
   */
  async retryIf<T>(
    operation: () => Promise<T>,
    shouldRetry: (error: Error) => boolean,
    maxAttempts = 3,
    delayMs = 1000
  ): Promise<T> {
    let attempt = 0;
    let lastError: Error;
    
    while (attempt < maxAttempts) {
      try {
        return await operation();
      } catch (error) {
        lastError = error as Error;
        attempt++;
        
        if (!shouldRetry(lastError) || attempt >= maxAttempts) {
          break;
        }
        
        await new Promise(resolve => setTimeout(resolve, delayMs));
      }
    }
    
    throw lastError!;
  },
  
  /**
   * Retry with exponential backoff
   */
  async retryWithBackoff<T>(
    operation: () => Promise<T>,
    maxAttempts = 3,
    initialDelayMs = 100
  ): Promise<T> {
    let attempt = 0;
    let delay = initialDelayMs;
    
    while (attempt < maxAttempts) {
      try {
        return await operation();
      } catch (error) {
        attempt++;
        if (attempt >= maxAttempts) throw error;
        
        await new Promise(resolve => setTimeout(resolve, delay));
        delay *= 2; // Exponential backoff
      }
    }
    
    throw new Error('Max attempts reached');
  },
};
```

---

## 6. Usage Examples

### 6.1 Before and After Comparison

#### Without Helpers (Verbose)

```php
// Logging
if ($duration > 100) {
    Logger::warning("Slow query detected", ['duration' => $duration]);
}

// Conditional execution
if ($user->isAdmin()) {
    $this->loadAdminDashboard();
}

// Validation
if ($shouldValidateEmail) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
}

// Null checking
if ($value !== null) {
    return $value;
} else {
    return $default;
}
```

#### With Helpers (Clean)

```php
// Logging
logIfExceeds($duration, 100, "Slow query detected");

// Conditional execution
execIf($user->isAdmin(), fn() => $this->loadAdminDashboard());

// Validation
$errors['email'] = validateIf(
    $shouldValidateEmail,
    $email,
    fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL),
    'Invalid email format'
);

// Null checking
return returnIfNotNull($value, $default);
```

### 6.2 Complex Example

```php
class UserService {
    public function processUser(int $userId, array $options): User {
        // Conditional logging
        logIf(
            $options['verbose'] ?? false,
            'debug',
            "Processing user",
            ['user_id' => $userId]
        );
        
        // Retry with backoff
        $user = retryWithBackoff(
            fn() => $this->repository->find($userId),
            maxAttempts: 3
        );
        
        // Conditional validation
        $errors = validateAll([
            'email' => [
                'condition' => hasKey($options, 'email'),
                'value' => $options['email'] ?? '',
                'validator' => fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL),
                'message' => 'Invalid email',
            ],
            'age' => [
                'condition' => hasKey($options, 'age'),
                'value' => $options['age'] ?? 0,
                'validator' => fn($a) => $a >= 18,
                'message' => 'Must be 18 or older',
            ],
        ]);
        
        // Conditional execution based on validation
        execIf(isNotEmpty($errors), fn() => throw new ValidationException($errors));
        
        // Conditional update
        execIf(
            hasKey($options, 'email'),
            fn() => $user->setEmail($options['email'])
        );
        
        return $user;
    }
}
```

---

## 7. Anti-Patterns

### 7.1 Overusing Helpers

```php
// ❌ INCORRECT - Simple check doesn't need helper
execIf(isTrue($enabled), fn() => doSomething());

// ✅ CORRECT - Simple boolean check is fine
if ($enabled) {
    doSomething();
}
```

### 7.2 Nested Helpers

```php
// ❌ INCORRECT - Hard to read
execIf(
    returnIf(isNotNull($user), $user->isAdmin(), false),
    fn() => logIf(true, 'info', 'Admin action')
);

// ✅ CORRECT - Break into steps
$isAdmin = isNotNull($user) && $user->isAdmin();
execIf($isAdmin, fn() => Logger::info('Admin action'));
```

### 7.3 Side Effects in Conditions

```php
// ❌ INCORRECT - Side effect in condition
execIf(($count = count($items)) > 0, fn() => process($count));

// ✅ CORRECT - Separate concerns
$count = count($items);
execIf($count > 0, fn() => process($count));
```

---

## 8. Integration with Boolean Helpers

Conditional helpers work best with Boolean helpers:

```php
// Combined usage
execIf(isNotNull($user), fn() => $this->loadProfile($user));
execIf(isNotEmpty($items), fn() => $this->processItems($items));
execIf(hasKey($options, 'debug'), fn() => $this->enableDebugMode());

logIf(isPositive($duration), 'debug', 'Operation completed', ['ms' => $duration]);

$result = returnIf(isNotNull($cached), $cached, $this->fetchFresh());
```

---

## Mandatory Implementation Checklist

Before considering any implementation complete, verify:

- [ ] Common conditional patterns use helper functions
- [ ] Helpers are used for logging conditions (logIf, logIfError)
- [ ] Retry logic uses retryIf or retryWithBackoff
- [ ] Validation uses validateIf pattern
- [ ] Null coalescing uses helper functions
- [ ] Helpers are combined with Boolean helpers
- [ ] No nested helper calls (break into steps)
- [ ] Side effects are not in condition expressions
- [ ] Simple single-line conditions don't use helpers
- [ ] Async operations use async helper variants

---

*This document establishes conditional helper patterns. See [01-testing-standards-quality.md](../03-quality/01-testing-standards-quality.md) for testing patterns.*
