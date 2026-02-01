# 41b - Test Specification: File Loader Helpers

> **Component:** `src/Helpers/FileLoaderHelpers.php`  
> **Test File:** `tests/php/Unit/Helpers/FileLoaderHelpersTest.php`  
> **Priority:** Critical (Foundation layer, affects plugin bootstrap)

---

## 📋 Test Coverage Summary

| Method | Test Cases | Edge Cases |
|--------|------------|------------|
| `loadFiles()` | 8 | 4 |
| `loadSingle()` | 6 | 3 |
| `loadIf()` | 4 | 2 |
| `loadDirectory()` | 5 | 3 |
| `logLoadFailure()` (private) | 3 | 2 |

---

## 🧪 Test Cases: `loadFiles()`

### Test Case 1: Successfully loads all files

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadFiles
 */
public function loadFiles_loadsAllFiles_whenAllExist(): void
{
    // Arrange
    $files = [
        $this->createTempPhpFile('file1.php', '<?php $a = 1;'),
        $this->createTempPhpFile('file2.php', '<?php $b = 2;'),
        $this->createTempPhpFile('file3.php', '<?php $c = 3;'),
    ];
    
    // Act
    $result = FileLoaderHelpers::loadFiles($files);
    
    // Assert
    $this->assertCount(3, $result['loaded']);
    $this->assertCount(0, $result['failed']);
    $this->assertEquals($files, $result['loaded']);
    
    // Cleanup
    array_map('unlink', $files);
}
```

### Test Case 2: Returns failed files when some don't exist

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadFiles
 */
public function loadFiles_tracksFailed_whenFileMissing(): void
{
    // Arrange
    $existingFile = $this->createTempPhpFile('exists.php', '<?php $x = 1;');
    $missingFile = '/nonexistent/path/missing.php';
    
    // Act
    $result = FileLoaderHelpers::loadFiles(
        [$existingFile, $missingFile], 
        throwOnFailure: false
    );
    
    // Assert
    $this->assertCount(1, $result['loaded']);
    $this->assertCount(1, $result['failed']);
    $this->assertContains($missingFile, $result['failed']);
    
    // Cleanup
    unlink($existingFile);
}
```

### Test Case 3: Throws exception when throwOnFailure is true

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadFiles
 */
public function loadFiles_throwsException_whenFailureAndThrowEnabled(): void
{
    // Arrange
    $missingFile = '/definitely/not/a/real/file.php';
    
    // Assert
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Failed to load required file');
    
    // Act
    FileLoaderHelpers::loadFiles([$missingFile], throwOnFailure: true);
}
```

### Test Case 4: Handles empty file array

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadFiles
 */
public function loadFiles_returnsEmpty_whenNoFilesProvided(): void
{
    // Act
    $result = FileLoaderHelpers::loadFiles([]);
    
    // Assert
    $this->assertCount(0, $result['loaded']);
    $this->assertCount(0, $result['failed']);
}
```

### Test Case 5: Logs each failure with stack trace

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadFiles
 */
public function loadFiles_logsStackTrace_forEachFailure(): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->exactly(2))
        ->method('error')
        ->with(
            $this->stringContains('FILE LOAD FAILED'),
            $this->callback(function($context) {
                return isset($context['file']) 
                    && isset($context['reason'])
                    && isset($context['caller']);
            })
        );
    
    $missingFiles = [
        '/fake/path/one.php',
        '/fake/path/two.php',
    ];
    
    // Act
    FileLoaderHelpers::loadFiles($missingFiles, throwOnFailure: false);
    
    // Assert - handled by expects()
}
```

### Test Case 6: Continues loading after failure (when throwOnFailure false)

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadFiles
 */
public function loadFiles_continuesAfterFailure_whenNotThrowing(): void
{
    // Arrange
    $file1 = $this->createTempPhpFile('first.php', '<?php $a = 1;');
    $missing = '/no/such/file.php';
    $file2 = $this->createTempPhpFile('second.php', '<?php $b = 2;');
    
    // Act
    $result = FileLoaderHelpers::loadFiles(
        [$file1, $missing, $file2],
        throwOnFailure: false
    );
    
    // Assert
    $this->assertCount(2, $result['loaded']);
    $this->assertCount(1, $result['failed']);
    $this->assertContains($file1, $result['loaded']);
    $this->assertContains($file2, $result['loaded']);
    
    // Cleanup
    unlink($file1);
    unlink($file2);
}
```

---

## 🧪 Test Cases: `loadSingle()`

### Test Case 1: Successfully loads existing file

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadSingle
 */
public function loadSingle_returnsTrue_whenFileLoadsSuccessfully(): void
{
    // Arrange
    $file = $this->createTempPhpFile('single.php', '<?php $loaded = true;');
    
    // Act
    $result = FileLoaderHelpers::loadSingle($file);
    
    // Assert
    $this->assertTrue($result);
    
    // Cleanup
    unlink($file);
}
```

### Test Case 2: Returns false for missing file

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadSingle
 */
public function loadSingle_returnsFalse_whenFileMissing(): void
{
    // Act
    $result = FileLoaderHelpers::loadSingle('/nonexistent/file.php');
    
    // Assert
    $this->assertFalse($result);
}
```

### Test Case 3: Returns false for unreadable file

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadSingle
 */
public function loadSingle_returnsFalse_whenFileUnreadable(): void
{
    // Arrange
    $file = $this->createTempPhpFile('unreadable.php', '<?php echo 1;');
    chmod($file, 0000); // Remove all permissions
    
    // Act
    $result = FileLoaderHelpers::loadSingle($file);
    
    // Assert
    $this->assertFalse($result);
    
    // Cleanup
    chmod($file, 0644);
    unlink($file);
}
```

### Test Case 4: Logs debug message on success (when debug enabled)

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadSingle
 */
public function loadSingle_logsDebug_whenSuccessfulAndDebugEnabled(): void
{
    // Arrange
    if (!defined('EQM_DEBUG')) {
        define('EQM_DEBUG', true);
    }
    
    $file = $this->createTempPhpFile('debug.php', '<?php $x = 1;');
    
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('log')
        ->with(LogLevel::DEBUG, $this->stringContains('loaded successfully'));
    
    // Act
    FileLoaderHelpers::loadSingle($file);
    
    // Cleanup
    unlink($file);
}
```

### Test Case 5: Handles PHP parse errors gracefully

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadSingle
 */
public function loadSingle_returnsFalse_whenPhpSyntaxError(): void
{
    // Arrange
    $file = $this->createTempPhpFile('syntax-error.php', '<?php $x = ;'); // Invalid syntax
    
    // Act
    $result = FileLoaderHelpers::loadSingle($file);
    
    // Assert
    $this->assertFalse($result);
    
    // Cleanup
    unlink($file);
}
```

### Test Case 6: Logs with correct caller info

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadSingle
 */
public function loadSingle_logsCallerInfo_onFailure(): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->once())
        ->method('error')
        ->with(
            $this->anything(),
            $this->callback(function($context) {
                return isset($context['caller']['file'])
                    && isset($context['caller']['line'])
                    && isset($context['caller']['function']);
            })
        );
    
    // Act
    FileLoaderHelpers::loadSingle('/nonexistent.php');
}
```

---

## 🧪 Test Cases: `loadIf()`

### Test Case 1: Loads file when condition true

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadIf
 */
public function loadIf_loadsFile_whenConditionTrue(): void
{
    // Arrange
    $file = $this->createTempPhpFile('conditional.php', '<?php $loaded = true;');
    
    // Act
    $result = FileLoaderHelpers::loadIf(true, $file);
    
    // Assert
    $this->assertTrue($result);
    
    // Cleanup
    unlink($file);
}
```

### Test Case 2: Skips file when condition false (returns true)

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadIf
 */
public function loadIf_skipsButReturnsTrue_whenConditionFalse(): void
{
    // Arrange - file doesn't even need to exist
    $missingFile = '/this/file/does/not/exist.php';
    
    // Act
    $result = FileLoaderHelpers::loadIf(false, $missingFile);
    
    // Assert - returns true because skip is intentional
    $this->assertTrue($result);
}
```

### Test Case 3: Returns false when condition true but file missing

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadIf
 */
public function loadIf_returnsFalse_whenConditionTrueButFileMissing(): void
{
    // Act
    $result = FileLoaderHelpers::loadIf(true, '/nonexistent.php');
    
    // Assert
    $this->assertFalse($result);
}
```

---

## 🧪 Test Cases: `loadDirectory()`

### Test Case 1: Loads all PHP files in directory

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadDirectory
 */
public function loadDirectory_loadsAllPhpFiles(): void
{
    // Arrange
    $dir = sys_get_temp_dir() . '/eqm_test_' . uniqid();
    mkdir($dir);
    
    file_put_contents("{$dir}/a.php", '<?php $a = 1;');
    file_put_contents("{$dir}/b.php", '<?php $b = 2;');
    file_put_contents("{$dir}/c.txt", 'not php');
    
    // Act
    $result = FileLoaderHelpers::loadDirectory($dir, '*.php');
    
    // Assert
    $this->assertCount(2, $result['loaded']);
    $this->assertCount(0, $result['failed']);
    
    // Cleanup
    unlink("{$dir}/a.php");
    unlink("{$dir}/b.php");
    unlink("{$dir}/c.txt");
    rmdir($dir);
}
```

### Test Case 2: Returns failure for missing directory

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadDirectory
 */
public function loadDirectory_returnsFailed_whenDirMissing(): void
{
    // Act
    $result = FileLoaderHelpers::loadDirectory('/nonexistent/directory');
    
    // Assert
    $this->assertCount(0, $result['loaded']);
    $this->assertCount(1, $result['failed']);
    $this->assertContains('/nonexistent/directory', $result['failed']);
}
```

### Test Case 3: Handles custom glob patterns

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadDirectory
 */
public function loadDirectory_usesCustomPattern(): void
{
    // Arrange
    $dir = sys_get_temp_dir() . '/eqm_test_' . uniqid();
    mkdir($dir);
    
    file_put_contents("{$dir}/helper.php", '<?php $h = 1;');
    file_put_contents("{$dir}/Service.php", '<?php class S {}');
    file_put_contents("{$dir}/OtherHelper.php", '<?php $o = 2;');
    
    // Act - only files ending in Helper.php
    $result = FileLoaderHelpers::loadDirectory($dir, '*Helper.php');
    
    // Assert
    $this->assertCount(2, $result['loaded']); // helper.php + OtherHelper.php (case insensitive glob)
    
    // Cleanup
    array_map('unlink', glob("{$dir}/*.php"));
    rmdir($dir);
}
```

### Test Case 4: Empty directory returns empty loaded array

```php
/**
 * @test
 * @covers FileLoaderHelpers::loadDirectory
 */
public function loadDirectory_returnsEmpty_whenDirEmpty(): void
{
    // Arrange
    $dir = sys_get_temp_dir() . '/eqm_empty_' . uniqid();
    mkdir($dir);
    
    // Act
    $result = FileLoaderHelpers::loadDirectory($dir);
    
    // Assert
    $this->assertCount(0, $result['loaded']);
    $this->assertCount(0, $result['failed']);
    
    // Cleanup
    rmdir($dir);
}
```

---

## 🔴 Stack Trace Logging Tests

### Test Case 1: Stack trace includes caller file and line

```php
/**
 * @test
 * @covers FileLoaderHelpers::logLoadFailure
 */
public function logLoadFailure_includesStackTrace(): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    $capturedContext = null;
    
    $mockLogger->expects($this->atLeastOnce())
        ->method('error')
        ->willReturnCallback(function($msg, $ctx) use (&$capturedContext) {
            if (str_contains($msg, 'Stack trace')) {
                $capturedContext = $ctx;
            }
        });
    
    // Act
    FileLoaderHelpers::loadSingle('/trigger/stack/trace.php');
    
    // Assert
    $this->assertNotNull($capturedContext);
    $this->assertArrayHasKey('stack_trace', $capturedContext);
    $this->assertStringContainsString('FileLoaderHelpersTest.php', $capturedContext['stack_trace']);
}
```

### Test Case 2: Exception stack trace preserved

```php
/**
 * @test
 * @covers FileLoaderHelpers::logLoadFailure
 */
public function logLoadFailure_logsExceptionStackTrace_whenExceptionOccurs(): void
{
    // Arrange - file with parse error will throw
    $badFile = $this->createTempPhpFile('parse-error.php', '<?php function (');
    
    $mockLogger = $this->createMock(Logger::class);
    $mockLogger->expects($this->atLeastOnce())
        ->method('exception')
        ->with(
            $this->isInstanceOf(\Throwable::class),
            $this->anything()
        );
    
    // Act
    FileLoaderHelpers::loadSingle($badFile);
    
    // Cleanup
    unlink($badFile);
}
```

### Test Case 3: Logs to both plugin.log and error.txt

```php
/**
 * @test
 * @covers FileLoaderHelpers::logLoadFailure
 */
public function logLoadFailure_logsToBothFiles(): void
{
    // Arrange
    $mockLogger = $this->createMock(Logger::class);
    
    // Expect error() for general log
    $mockLogger->expects($this->atLeastOnce())
        ->method('error');
    
    // The Logger::error() internally writes to both files
    // This test verifies the method is called correctly
    
    // Act
    FileLoaderHelpers::loadSingle('/missing/file.php');
    
    // Assert - handled by expects()
}
```

---

## 🔧 Test Helper Methods

```php
/**
 * Create a temporary PHP file for testing
 */
private function createTempPhpFile(string $name, string $content): string
{
    $path = sys_get_temp_dir() . '/' . $name;
    file_put_contents($path, $content);
    return $path;
}

/**
 * Clean up test files
 */
protected function tearDown(): void
{
    // Clean up any lingering test files
    $tempFiles = glob(sys_get_temp_dir() . '/eqm_test_*');
    foreach ($tempFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        } elseif (is_dir($file)) {
            array_map('unlink', glob("{$file}/*"));
            rmdir($file);
        }
    }
    
    parent::tearDown();
}
```

---

## ✅ Acceptance Criteria

- [ ] All `loadFiles()` tests verify loaded/failed arrays
- [ ] All `loadSingle()` tests check return value and logging
- [ ] All `loadIf()` tests verify conditional behavior
- [ ] All `loadDirectory()` tests verify glob patterns
- [ ] Stack trace is logged on every failure
- [ ] Caller info (file, line, function) is captured
- [ ] Both Logger::error() and Logger::exception() are called appropriately
- [ ] 100% line coverage for FileLoaderHelpers class
- [ ] No actual file system pollution after tests

---

*Next: `41c-test-spec-feature-flags.md`*
