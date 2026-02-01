# 05 - Logging System (Dual-File)

> **Phase:** Foundation  
> **Dependencies:** `01-plugin-structure.md`  
> **Estimated Time:** 2-3 hours

---

## 📋 Scope

Implement dual-file logging system:
- `plugin.log` - All events (info, warnings, etc.)
- `error.txt` - Only errors with stack traces

---

## 📁 Log File Locations

```
/wp-content/uploads/exam-questions-manager/logs/
├── plugin.log          # General logs (all levels)
└── error.txt           # Error logs only (with stack traces)
```

---

## 🔧 Logger Utility

**File:** `src/Utils/Logger.php`

```php
<?php
namespace ExamQuestionsManager\Utils;

use ExamQuestionsManager\Enums\LogLevel;

class Logger {
    private static string $generalLogFile;
    private static string $errorLogFile;
    private static bool $initialized = false;
    
    /**
     * Initialize logger
     */
    private static function init(): void {
        if (self::$initialized) {
            return;
        }
        
        $logDir = EQM_UPLOADS_DIR . 'logs/';
        
        // Ensure log directory exists
        if (!file_exists($logDir)) {
            wp_mkdir_p($logDir);
        }
        
        self::$generalLogFile = $logDir . 'plugin.log';
        self::$errorLogFile = $logDir . 'error.txt';
        self::$initialized = true;
    }
    
    /**
     * Log a message
     */
    public static function log(LogLevel $level, string $message, array $context = []): void {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = self::formatMessage($timestamp, $level, $message, $context);
        
        // Write to general log
        self::writeToFile(self::$generalLogFile, $formattedMessage);
        
        // Write to error log if applicable
        if ($level->isError()) {
            $errorMessage = self::formatErrorMessage($timestamp, $level, $message, $context);
            self::writeToFile(self::$errorLogFile, $errorMessage);
        }
    }
    
    /**
     * Format general log message
     */
    private static function formatMessage(
        string $timestamp, 
        LogLevel $level, 
        string $message, 
        array $context
    ): string {
        $contextStr = '';
        
        if (!empty($context)) {
            $contextParts = [];
            foreach ($context as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $contextParts[] = "{$key}={$value}";
            }
            $contextStr = ' ' . implode(' ', $contextParts);
        }
        
        return "[{$timestamp}] {$level->value} {$message}{$contextStr}\n";
    }
    
    /**
     * Format error log message with stack trace
     */
    private static function formatErrorMessage(
        string $timestamp, 
        LogLevel $level, 
        string $message, 
        array $context
    ): string {
        $output = "[{$timestamp}] {$level->value} {$message}\n";
        
        // Add context
        if (!empty($context)) {
            foreach ($context as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_PRETTY_PRINT);
                }
                $output .= "  {$key}: {$value}\n";
            }
        }
        
        // Add stack trace
        $output .= "Stack Trace:\n";
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Skip the first few entries (Logger internals)
        $trace = array_slice($trace, 2);
        
        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? '?';
            $function = $frame['function'] ?? 'unknown';
            $class = isset($frame['class']) ? $frame['class'] . '::' : '';
            
            $output .= "  #{$index} {$file}:{$line} {$class}{$function}()\n";
        }
        
        $output .= "---\n\n";
        
        return $output;
    }
    
    /**
     * Write to file with locking
     */
    private static function writeToFile(string $file, string $content): void {
        $handle = fopen($file, 'a');
        
        if ($handle) {
            flock($handle, LOCK_EX);
            fwrite($handle, $content);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
    
    /**
     * Log debug message
     */
    public static function debug(string $message, array $context = []): void {
        self::log(LogLevel::DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info(string $message, array $context = []): void {
        self::log(LogLevel::INFO, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning(string $message, array $context = []): void {
        self::log(LogLevel::WARNING, $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error(string $message, array $context = []): void {
        self::log(LogLevel::ERROR, $message, $context);
    }
    
    /**
     * Log critical message
     */
    public static function critical(string $message, array $context = []): void {
        self::log(LogLevel::CRITICAL, $message, $context);
    }
    
    /**
     * Log exception
     */
    public static function exception(\Throwable $e, array $context = []): void {
        $context['exception_class'] = get_class($e);
        $context['exception_code'] = $e->getCode();
        $context['exception_file'] = $e->getFile();
        $context['exception_line'] = $e->getLine();
        
        self::error($e->getMessage(), $context);
    }
    
    /**
     * Read general log file
     */
    public static function readGeneralLog(int $lines = 100): array {
        self::init();
        return self::readLastLines(self::$generalLogFile, $lines);
    }
    
    /**
     * Read error log file
     */
    public static function readErrorLog(int $lines = 100): array {
        self::init();
        return self::readLastLines(self::$errorLogFile, $lines);
    }
    
    /**
     * Read last N lines from file
     */
    private static function readLastLines(string $file, int $lines): array {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        $allLines = explode("\n", trim($content));
        
        return array_slice($allLines, -$lines);
    }
    
    /**
     * Clear general log file
     */
    public static function clearGeneralLog(): void {
        self::init();
        file_put_contents(self::$generalLogFile, '');
        self::info('General log cleared');
    }
    
    /**
     * Clear error log file
     */
    public static function clearErrorLog(): void {
        self::init();
        file_put_contents(self::$errorLogFile, '');
        self::info('Error log cleared');
    }
    
    /**
     * Clear all logs
     */
    public static function clearAllLogs(): void {
        self::init();
        file_put_contents(self::$generalLogFile, '');
        file_put_contents(self::$errorLogFile, '');
    }
    
    /**
     * Get log file sizes
     */
    public static function getLogSizes(): array {
        self::init();
        
        return [
            'general' => file_exists(self::$generalLogFile) 
                ? filesize(self::$generalLogFile) 
                : 0,
            'error' => file_exists(self::$errorLogFile) 
                ? filesize(self::$errorLogFile) 
                : 0,
        ];
    }
    
    /**
     * Rotate logs if they exceed size limit
     */
    public static function rotateIfNeeded(int $maxSizeBytes = 10485760): void { // 10MB default
        self::init();
        
        $files = [
            self::$generalLogFile => 'plugin',
            self::$errorLogFile => 'error',
        ];
        
        foreach ($files as $file => $prefix) {
            if (file_exists($file) && filesize($file) > $maxSizeBytes) {
                $rotatedFile = dirname($file) . "/{$prefix}-" . date('Y-m-d-His') . '.log';
                rename($file, $rotatedFile);
                self::info("Log rotated: {$prefix}");
            }
        }
    }
}
```

---

## 📝 Usage Examples

```php
use ExamQuestionsManager\Utils\Logger;

// Basic logging
Logger::info('Plugin activated');
Logger::debug('Processing request', ['userId' => 123]);
Logger::warning('Soft deadline approaching', ['participantId' => 45]);

// Error logging (goes to both files)
Logger::error('Database connection failed', ['host' => 'localhost']);

// Exception logging
try {
    // risky operation
} catch (\Exception $e) {
    Logger::exception($e, ['userId' => 123]);
}

// With context (structured data)
Logger::info('Exam created', [
    'examId' => 5,
    'title' => 'JavaScript Basics',
    'slug' => 'javascript-basics',
    'adminId' => 1
]);

// Critical errors
Logger::critical('Payment processing failed', [
    'orderId' => 999,
    'amount' => 49.99
]);
```

---

## 📋 Log Format Examples

### plugin.log (General Log)

```
[2026-01-24 13:00:00] INFO Plugin activated
[2026-01-24 13:05:30] INFO Exam created examId=5 title="JavaScript Basics" slug="javascript-basics" adminId=1
[2026-01-24 13:10:45] INFO Participant signup email="john@example.com" examId=5 softDeadline="2026-01-27 13:05:30" hardDeadline="2026-01-31 13:05:30"
[2026-01-24 14:20:00] INFO Section marked done participantId=12 sectionNumber=3 examId=5
[2026-01-24 14:30:00] WARNING Soft deadline approaching participantId=12 examId=5
[2026-01-24 15:00:00] ERROR Email sending failed error="SMTP connection timeout" recipient="john@example.com"
```

### error.txt (Error Log)

```
[2026-01-24 15:00:00] ERROR Email sending failed
  error: SMTP connection timeout
  recipient: john@example.com
Stack Trace:
  #0 /wp-content/plugins/exam-questions-manager/src/Services/EmailService.php:142 ExamQuestionsManager\Services\EmailService::send()
  #1 /wp-content/plugins/exam-questions-manager/src/Cron/DailyDigestJob.php:58 ExamQuestionsManager\Cron\DailyDigestJob::execute()
  #2 /wp-includes/cron.php:123 wp_cron()
---

[2026-01-24 16:30:00] CRITICAL Database connection lost
  host: localhost
  database: exam-questions
Stack Trace:
  #0 /wp-content/plugins/exam-questions-manager/src/Database/Connection.php:25 ExamQuestionsManager\Database\Connection::getInstance()
  #1 /wp-content/plugins/exam-questions-manager/src/ORM/Model.php:15 ExamQuestionsManager\ORM\Model::db()
---
```

---

## ✅ Acceptance Criteria

### Dual-File System
- [ ] `plugin.log` receives all log levels (DEBUG through CRITICAL)
- [ ] `error.txt` receives only ERROR and CRITICAL levels
- [ ] Error logs include full stack trace

### Log Format
- [ ] Timestamp format: `[YYYY-MM-DD HH:MM:SS]`
- [ ] Level in uppercase: INFO, DEBUG, WARNING, ERROR, CRITICAL
- [ ] Context displayed as `key=value` pairs in general log
- [ ] Context displayed with proper indentation in error log

### Convenience Methods
- [ ] `Logger::info()` works correctly
- [ ] `Logger::debug()` works correctly
- [ ] `Logger::warning()` works correctly
- [ ] `Logger::error()` works correctly
- [ ] `Logger::critical()` works correctly
- [ ] `Logger::exception()` captures exception details

### File Operations
- [ ] `readGeneralLog()` returns last N lines
- [ ] `readErrorLog()` returns last N lines
- [ ] `clearGeneralLog()` empties file
- [ ] `clearErrorLog()` empties file
- [ ] `getLogSizes()` returns file sizes

### Safety
- [ ] File locking prevents concurrent write corruption
- [ ] Log rotation works when files exceed size limit
- [ ] Directories created if they don't exist
- [ ] Graceful handling when file operations fail

### Best Practices
- [ ] No passwords or sensitive data logged
- [ ] Context IDs included (examId, participantId, etc.)
- [ ] Consistent log messages across codebase

---

## 📝 Logging Best Practices

### DO
```php
// Include relevant IDs
Logger::info('Participant signup', [
    'participantId' => $participant->id,
    'examId' => $exam->id,
    'email' => $participant->email
]);

// Include error details
Logger::error('Validation failed', [
    'field' => 'email',
    'value' => $email,
    'reason' => 'Invalid format'
]);

// Use appropriate levels
Logger::debug('Query executed', ['sql' => '...']); // Development only
Logger::info('Exam created', ['examId' => 5]);      // Normal operation
Logger::warning('Rate limit approaching', [...]);    // Potential issue
Logger::error('API call failed', [...]);             // Actual error
Logger::critical('Database down', [...]);            // System failure
```

### DON'T
```php
// ❌ Don't log passwords
Logger::info('Login attempt', ['password' => $password]);

// ❌ Don't use generic messages
Logger::error('Error occurred');

// ❌ Don't forget context IDs
Logger::info('Exam created'); // Which exam?

// ❌ Don't log raw SQL
Logger::debug('Query', ['sql' => "SELECT * FROM users WHERE id = 5"]);
```

---

*Next: `06-entity-models.md`*
