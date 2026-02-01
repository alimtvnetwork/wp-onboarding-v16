# 41a - Test Specification: Conditional Helpers

> **Component:** `src/Helpers/ConditionalHelpers.php`  
> **Test File:** `tests/php/Unit/Helpers/ConditionalHelpersTest.php`  
> **Priority:** High (Foundation layer)

---

## 📋 Test Coverage Summary

| Method | Test Cases | Edge Cases |
|--------|------------|------------|
| `logIf()` | 5 | 3 |
| `logIfError()` | 4 | 2 |
| `logIfDebug()` | 3 | 1 |
| `execIf()` | 4 | 2 |
| `execIfOrDefault()` | 4 | 2 |
| `returnIf()` | 3 | 1 |
| `choose()` | 3 | 1 |
| `ifNotNull()` | 4 | 2 |
| `orDefault()` | 3 | 1 |

---

## 🧪 Test Cases: `logIf()`

### Test Case 1: Logs when condition is true

```php
/**
 * @test
 * @covers ConditionalHelpers::logIf
 */
public function logIf_logsMessage_whenConditionIsTrue(): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('log')
        ->with(
            LogLevel::INFO,
            'Test message',
            ['key' => 'value']
        );
    
    // Act
    ConditionalHelpers::logIf(
        true,
        LogLevel::INFO,
        'Test message',
        ['key' => 'value']
    );
    
    // Assert - handled by expects()
}
```

### Test Case 2: Does NOT log when condition is false

```php
/**
 * @test
 * @covers ConditionalHelpers::logIf
 */
public function logIf_skipsLogging_whenConditionIsFalse(): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->never())
        ->method('log');
    
    // Act
    ConditionalHelpers::logIf(
        false,
        LogLevel::INFO,
        'Should not log'
    );
    
    // Assert - handled by expects(never())
}
```

### Test Case 3: Handles all log levels correctly

```php
/**
 * @test
 * @covers ConditionalHelpers::logIf
 * @dataProvider logLevelProvider
 */
public function logIf_handlesAllLogLevels(LogLevel $level): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('log')
        ->with($level, $this->anything(), $this->anything());
    
    // Act
    ConditionalHelpers::logIf(true, $level, 'Message');
    
    // Assert - handled by expects()
}

public static function logLevelProvider(): array
{
    return [
        'DEBUG' => [LogLevel::DEBUG],
        'INFO' => [LogLevel::INFO],
        'WARNING' => [LogLevel::WARNING],
        'ERROR' => [LogLevel::ERROR],
        'CRITICAL' => [LogLevel::CRITICAL],
    ];
}
```

### Test Case 4: Empty context array handled

```php
/**
 * @test
 * @covers ConditionalHelpers::logIf
 */
public function logIf_handlesEmptyContext(): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('log')
        ->with(LogLevel::INFO, 'Message', []);
    
    // Act
    ConditionalHelpers::logIf(true, LogLevel::INFO, 'Message');
    
    // Assert - default empty array passed
}
```

### Test Case 5: Complex context objects serialized

```php
/**
 * @test
 * @covers ConditionalHelpers::logIf
 */
public function logIf_handlesComplexContextObjects(): void
{
    // Arrange
    $context = [
        'participant' => ['id' => 1, 'email' => 'test@example.com'],
        'nested' => ['level1' => ['level2' => 'value']],
    ];
    
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('log')
        ->with(LogLevel::INFO, $this->anything(), $context);
    
    // Act
    ConditionalHelpers::logIf(true, LogLevel::INFO, 'Message', $context);
    
    // Assert - complex context passed correctly
}
```

---

## 🧪 Test Cases: `logIfError()`

### Test Case 1: Logs exception when not null

```php
/**
 * @test
 * @covers ConditionalHelpers::logIfError
 */
public function logIfError_logsException_whenThrowableProvided(): void
{
    // Arrange
    $exception = new \RuntimeException('Test error', 500);
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('exception')
        ->with($exception, $this->callback(function($extra) {
            return $extra['context'] === 'Test context';
        }));
    
    // Act
    ConditionalHelpers::logIfError($exception, 'Test context');
    
    // Assert - handled by expects()
}
```

### Test Case 2: Does nothing when exception is null

```php
/**
 * @test
 * @covers ConditionalHelpers::logIfError
 */
public function logIfError_skipsLogging_whenExceptionIsNull(): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->never())
        ->method('exception');
    
    // Act
    ConditionalHelpers::logIfError(null, 'Context');
    
    // Assert - no logging occurred
}
```

### Test Case 3: Extra context merged correctly

```php
/**
 * @test
 * @covers ConditionalHelpers::logIfError
 */
public function logIfError_mergesExtraContext(): void
{
    // Arrange
    $exception = new \InvalidArgumentException('Invalid');
    $extra = ['userId' => 42, 'action' => 'save'];
    
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('exception')
        ->with($exception, $this->callback(function($ctx) {
            return $ctx['userId'] === 42 
                && $ctx['action'] === 'save'
                && $ctx['context'] === 'Processing';
        }));
    
    // Act
    ConditionalHelpers::logIfError($exception, 'Processing', $extra);
    
    // Assert - all context merged
}
```

### Test Case 4: Handles Error objects (not just Exception)

```php
/**
 * @test
 * @covers ConditionalHelpers::logIfError
 */
public function logIfError_handlesErrorObjects(): void
{
    // Arrange
    $error = new \Error('Fatal error');
    
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('exception')
        ->with($this->isInstanceOf(\Throwable::class), $this->anything());
    
    // Act
    ConditionalHelpers::logIfError($error, 'Fatal context');
    
    // Assert - Error handled same as Exception
}
```

---

## 🧪 Test Cases: `execIf()`

### Test Case 1: Executes callback when true

```php
/**
 * @test
 * @covers ConditionalHelpers::execIf
 */
public function execIf_executesCallback_whenConditionTrue(): void
{
    // Arrange
    $executed = false;
    $callback = function() use (&$executed) {
        $executed = true;
        return 'result';
    };
    
    // Act
    $result = ConditionalHelpers::execIf(true, $callback);
    
    // Assert
    $this->assertTrue($executed);
    $this->assertEquals('result', $result);
}
```

### Test Case 2: Returns null when condition false

```php
/**
 * @test
 * @covers ConditionalHelpers::execIf
 */
public function execIf_returnsNull_whenConditionFalse(): void
{
    // Arrange
    $executed = false;
    $callback = function() use (&$executed) {
        $executed = true;
        return 'should not see this';
    };
    
    // Act
    $result = ConditionalHelpers::execIf(false, $callback);
    
    // Assert
    $this->assertFalse($executed);
    $this->assertNull($result);
}
```

### Test Case 3: Callback can return various types

```php
/**
 * @test
 * @covers ConditionalHelpers::execIf
 * @dataProvider callbackReturnTypesProvider
 */
public function execIf_preservesReturnType(mixed $returnValue): void
{
    // Arrange
    $callback = fn() => $returnValue;
    
    // Act
    $result = ConditionalHelpers::execIf(true, $callback);
    
    // Assert
    $this->assertSame($returnValue, $result);
}

public static function callbackReturnTypesProvider(): array
{
    return [
        'string' => ['hello'],
        'integer' => [42],
        'float' => [3.14],
        'array' => [['a', 'b', 'c']],
        'object' => [new \stdClass()],
        'null' => [null],
        'boolean true' => [true],
        'boolean false' => [false],
    ];
}
```

---

## 🧪 Test Cases: `ifNotNull()`

### Test Case 1: Executes callback when value not null

```php
/**
 * @test
 * @covers ConditionalHelpers::ifNotNull
 */
public function ifNotNull_executesCallback_whenValueExists(): void
{
    // Arrange
    $value = 'test string';
    $callback = fn($v) => strtoupper($v);
    
    // Act
    $result = ConditionalHelpers::ifNotNull($value, $callback);
    
    // Assert
    $this->assertEquals('TEST STRING', $result);
}
```

### Test Case 2: Returns null when value is null

```php
/**
 * @test
 * @covers ConditionalHelpers::ifNotNull
 */
public function ifNotNull_returnsNull_whenValueIsNull(): void
{
    // Arrange
    $executed = false;
    $callback = function($v) use (&$executed) {
        $executed = true;
        return $v;
    };
    
    // Act
    $result = ConditionalHelpers::ifNotNull(null, $callback);
    
    // Assert
    $this->assertNull($result);
    $this->assertFalse($executed);
}
```

### Test Case 3: Handles falsy non-null values

```php
/**
 * @test
 * @covers ConditionalHelpers::ifNotNull
 * @dataProvider falsyNonNullProvider
 */
public function ifNotNull_executesCallback_forFalsyNonNullValues(mixed $value): void
{
    // Arrange
    $callback = fn($v) => 'processed';
    
    // Act
    $result = ConditionalHelpers::ifNotNull($value, $callback);
    
    // Assert
    $this->assertEquals('processed', $result);
}

public static function falsyNonNullProvider(): array
{
    return [
        'empty string' => [''],
        'zero' => [0],
        'zero float' => [0.0],
        'empty array' => [[]],
        'false' => [false],
    ];
}
```

---

## 🔴 Edge Cases & Anti-Patterns

### Edge Case 1: Callback throws exception

```php
/**
 * @test
 * @covers ConditionalHelpers::execIf
 */
public function execIf_propagatesException_fromCallback(): void
{
    // Arrange
    $callback = function() {
        throw new \RuntimeException('Callback error');
    };
    
    // Assert
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Callback error');
    
    // Act
    ConditionalHelpers::execIf(true, $callback);
}
```

### Edge Case 2: Recursive callback calls

```php
/**
 * @test
 * @covers ConditionalHelpers::execIf
 */
public function execIf_handlesRecursiveCallbacks(): void
{
    // Arrange
    $counter = 0;
    $callback = function() use (&$counter) {
        $counter++;
        if ($counter < 3) {
            return ConditionalHelpers::execIf(true, fn() => $counter * 2);
        }
        return $counter;
    };
    
    // Act
    $result = ConditionalHelpers::execIf(true, $callback);
    
    // Assert
    $this->assertEquals(4, $result); // 2 * 2
}
```

---

## ✅ Acceptance Criteria

- [ ] All `logIf()` tests pass with mock Logger
- [ ] All `logIfError()` tests handle null and Throwable correctly
- [ ] All `execIf()` tests verify callback execution/skip
- [ ] All `ifNotNull()` tests distinguish null from falsy values
- [ ] Edge cases (exceptions, recursion) handled gracefully
- [ ] 100% line coverage for ConditionalHelpers class
- [ ] No direct Logger calls in tests (always mocked)

---

*Next: `41b-test-spec-file-loader.md`*
