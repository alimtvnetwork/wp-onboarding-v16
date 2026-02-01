# 03. Logging System

> **Applies To:** All languages (PHP, TypeScript, Python)  
> **Priority:** HIGH - Proper logging enables debugging and monitoring

---

## 1. Core Principles

### 1.1 The Dual-File Strategy

Separate logs by purpose:

| File | Purpose | Content |
|------|---------|---------|
| `app.log` (or `plugin.log`) | General operations | INFO, WARNING, DEBUG |
| `error.log` (or `error.txt`) | Errors only | ERROR with full stack traces |

**Rationale:**
- Quick error scanning without noise
- General logs don't get buried by stack traces
- Different retention policies possible

### 1.2 Logging Philosophy

```
Log what matters → Include context → Enable filtering → Rotate old logs
```

---

## 2. Log Levels

### 2.1 Level Definitions

| Level | When to Use | Production Visible |
|-------|-------------|-------------------|
| `DEBUG` | Development details, variable dumps | ❌ No |
| `INFO` | Normal operations, state changes | ✅ Yes |
| `WARNING` | Recoverable issues, deprecations | ✅ Yes |
| `ERROR` | Failures requiring attention | ✅ Yes |
| `CRITICAL` | System-wide failures | ✅ Yes + Alert |

### 2.2 Level Selection Guide

```
Is it a failure?
├── Yes → Is the system still functional?
│   ├── Yes → WARNING
│   └── No → Is it recoverable?
│       ├── Yes → ERROR
│       └── No → CRITICAL
└── No → Is it useful for debugging?
    ├── Yes (dev only) → DEBUG
    └── Yes (always) → INFO
```

### 2.3 Examples By Level

```php
// DEBUG - Development only
Logger::debug("User query executed", ['sql' => $query, 'params' => $params]);

// INFO - Normal operations
Logger::info("User logged in", ['user_id' => $userId, 'ip' => $ip]);

// WARNING - Something unexpected but handled
Logger::warning("Rate limit approaching", ['user_id' => $userId, 'requests' => 95]);

// ERROR - Something failed
Logger::error("Payment processing failed", ['order_id' => $orderId, 'error' => $message]);

// CRITICAL - System-wide issue
Logger::critical("Database connection lost", ['host' => $dbHost, 'retry_count' => 3]);
```

---

## 3. Log Format

### 3.1 Standard Format

```
[TIMESTAMP] LEVEL [REQUEST_ID] MESSAGE | CONTEXT
```

### 3.2 Examples

```
[2026-01-26 10:30:00] INFO [req_abc123] User logged in | {"user_id":42,"ip":"192.168.1.1"}
[2026-01-26 10:30:01] ERROR [req_abc123] Payment failed | {"order_id":100,"error":"Card declined"}
```

### 3.3 Error Log Format (With Stack Trace)

```
[2026-01-26 10:30:00] ERROR [req_abc123] ERR_3001: Database query failed
Context: {"query_type":"INSERT","table":"users"}
File: /app/src/Repository/UserRepository.php:45
Trace:
#0 /app/src/Service/UserService.php(23): UserRepository->create()
#1 /app/src/Controller/UserController.php(15): UserService->createUser()
#2 /app/vendor/framework/Router.php(100): UserController->create()
Previous: SQLSTATE[23000]: Integrity constraint violation
---
```

---

## 4. Implementation

### 4.1 PHP Logger

```php
<?php
declare(strict_types=1);

class Logger {
    private const GENERAL_LOG = 'logs/app.log';
    private const ERROR_LOG = 'logs/error.txt';
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    
    private static ?string $requestId = null;
    
    public static function debug(string $message, array $context = []): void {
        if (isNotEqual(getenv('APP_ENV'), 'development')) {
            return; // DEBUG only in development
        }
        self::write('DEBUG', $message, $context);
    }
    
    public static function info(string $message, array $context = []): void {
        self::write('INFO', $message, $context);
    }
    
    public static function warning(string $message, array $context = []): void {
        self::write('WARNING', $message, $context);
    }
    
    public static function error(string $message, array $context = []): void {
        self::write('ERROR', $message, $context);
        self::writeError($message, $context);
    }
    
    public static function critical(string $message, array $context = []): void {
        self::write('CRITICAL', $message, $context);
        self::writeError($message, $context);
        self::sendAlert($message, $context);
    }
    
    private static function write(string $level, string $message, array $context): void {
        $requestId = self::getRequestId();
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = isNotEmpty($context) ? ' | ' . json_encode($context) : '';
        
        $line = "[{$timestamp}] {$level} [{$requestId}] {$message}{$contextJson}\n";
        
        self::rotateIfNeeded(self::GENERAL_LOG);
        file_put_contents(self::GENERAL_LOG, $line, FILE_APPEND | LOCK_EX);
    }
    
    private static function writeError(string $message, array $context): void {
        $requestId = self::getRequestId();
        $timestamp = date('Y-m-d H:i:s');
        
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $trace[2] ?? [];
        
        $entry = "[{$timestamp}] ERROR [{$requestId}] {$message}\n";
        $entry .= "Context: " . json_encode($context) . "\n";
        $entry .= "File: " . ($caller['file'] ?? 'unknown') . ":" . ($caller['line'] ?? 0) . "\n";
        
        if (hasKey($context, 'trace')) {
            $entry .= "Trace:\n{$context['trace']}\n";
        }
        
        if (hasKey($context, 'previous')) {
            $entry .= "Previous: {$context['previous']}\n";
        }
        
        $entry .= "---\n";
        
        self::rotateIfNeeded(self::ERROR_LOG);
        file_put_contents(self::ERROR_LOG, $entry, FILE_APPEND | LOCK_EX);
    }
    
    private static function rotateIfNeeded(string $file): void {
        if (isFalse(file_exists($file))) {
            return;
        }
        
        if (filesize($file) < self::MAX_FILE_SIZE) {
            return;
        }
        
        $archiveDir = dirname($file) . '/archive';
        if (isFalse(is_dir($archiveDir))) {
            mkdir($archiveDir, 0755, true);
        }
        
        $archiveFile = $archiveDir . '/' . basename($file) . '.' . date('Y-m-d-His') . '.gz';
        
        $content = file_get_contents($file);
        $compressed = gzencode($content, 9);
        file_put_contents($archiveFile, $compressed);
        
        file_put_contents($file, ''); // Clear original
    }
    
    private static function getRequestId(): string {
        if (isNull(self::$requestId)) {
            self::$requestId = 'req_' . bin2hex(random_bytes(8));
        }
        return self::$requestId;
    }
    
    private static function sendAlert(string $message, array $context): void {
        // Integration with alerting service (PagerDuty, Slack, etc.)
        // Implementation depends on infrastructure
    }
}
```

### 4.2 TypeScript Logger

```typescript
import fs from 'fs';
import path from 'path';
import zlib from 'zlib';

type LogLevel = 'DEBUG' | 'INFO' | 'WARNING' | 'ERROR' | 'CRITICAL';

interface LogContext {
  [key: string]: unknown;
}

class Logger {
  private static readonly GENERAL_LOG = 'logs/app.log';
  private static readonly ERROR_LOG = 'logs/error.txt';
  private static readonly MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
  
  private static requestId: string | null = null;
  
  static debug(message: string, context: LogContext = {}): void {
    if (process.env.NODE_ENV !== 'development') {
      return;
    }
    this.write('DEBUG', message, context);
  }
  
  static info(message: string, context: LogContext = {}): void {
    this.write('INFO', message, context);
  }
  
  static warning(message: string, context: LogContext = {}): void {
    this.write('WARNING', message, context);
  }
  
  static error(message: string, context: LogContext = {}): void {
    this.write('ERROR', message, context);
    this.writeError(message, context);
  }
  
  static critical(message: string, context: LogContext = {}): void {
    this.write('CRITICAL', message, context);
    this.writeError(message, context);
    this.sendAlert(message, context);
  }
  
  private static write(level: LogLevel, message: string, context: LogContext): void {
    const requestId = this.getRequestId();
    const timestamp = new Date().toISOString();
    const contextStr = Object.keys(context).length > 0 
      ? ` | ${JSON.stringify(context)}` 
      : '';
    
    const line = `[${timestamp}] ${level} [${requestId}] ${message}${contextStr}\n`;
    
    this.rotateIfNeeded(this.GENERAL_LOG);
    fs.appendFileSync(this.GENERAL_LOG, line);
  }
  
  private static writeError(message: string, context: LogContext): void {
    const requestId = this.getRequestId();
    const timestamp = new Date().toISOString();
    const stack = new Error().stack || '';
    
    let entry = `[${timestamp}] ERROR [${requestId}] ${message}\n`;
    entry += `Context: ${JSON.stringify(context)}\n`;
    entry += `Stack:\n${stack}\n`;
    entry += '---\n';
    
    this.rotateIfNeeded(this.ERROR_LOG);
    fs.appendFileSync(this.ERROR_LOG, entry);
  }
  
  private static rotateIfNeeded(filePath: string): void {
    if (!fs.existsSync(filePath)) return;
    
    const stats = fs.statSync(filePath);
    if (stats.size < this.MAX_FILE_SIZE) return;
    
    const archiveDir = path.join(path.dirname(filePath), 'archive');
    if (!fs.existsSync(archiveDir)) {
      fs.mkdirSync(archiveDir, { recursive: true });
    }
    
    const archiveName = `${path.basename(filePath)}.${Date.now()}.gz`;
    const archivePath = path.join(archiveDir, archiveName);
    
    const content = fs.readFileSync(filePath);
    const compressed = zlib.gzipSync(content);
    fs.writeFileSync(archivePath, compressed);
    
    fs.writeFileSync(filePath, '');
  }
  
  private static getRequestId(): string {
    if (this.requestId === null) {
      this.requestId = `req_${Math.random().toString(36).substring(2, 18)}`;
    }
    return this.requestId;
  }
  
  private static sendAlert(message: string, context: LogContext): void {
    // Integration with alerting service
  }
}

export { Logger };
```

### 4.3 Python Logger

```python
import os
import json
import gzip
import logging
import traceback
from datetime import datetime
from pathlib import Path
from typing import Any, Optional
import secrets

class DualFileLogger:
    GENERAL_LOG = Path('logs/app.log')
    ERROR_LOG = Path('logs/error.txt')
    MAX_FILE_SIZE = 10 * 1024 * 1024  # 10MB
    
    _request_id: Optional[str] = None
    
    @classmethod
    def debug(cls, message: str, context: dict[str, Any] = None) -> None:
        if os.getenv('APP_ENV') != 'development':
            return
        cls._write('DEBUG', message, context or {})
    
    @classmethod
    def info(cls, message: str, context: dict[str, Any] = None) -> None:
        cls._write('INFO', message, context or {})
    
    @classmethod
    def warning(cls, message: str, context: dict[str, Any] = None) -> None:
        cls._write('WARNING', message, context or {})
    
    @classmethod
    def error(cls, message: str, context: dict[str, Any] = None) -> None:
        context = context or {}
        cls._write('ERROR', message, context)
        cls._write_error(message, context)
    
    @classmethod
    def critical(cls, message: str, context: dict[str, Any] = None) -> None:
        context = context or {}
        cls._write('CRITICAL', message, context)
        cls._write_error(message, context)
        cls._send_alert(message, context)
    
    @classmethod
    def _write(cls, level: str, message: str, context: dict[str, Any]) -> None:
        request_id = cls._get_request_id()
        timestamp = datetime.utcnow().isoformat()
        context_str = f' | {json.dumps(context)}' if context else ''
        
        line = f'[{timestamp}] {level} [{request_id}] {message}{context_str}\n'
        
        cls._ensure_dir(cls.GENERAL_LOG)
        cls._rotate_if_needed(cls.GENERAL_LOG)
        
        with open(cls.GENERAL_LOG, 'a') as f:
            f.write(line)
    
    @classmethod
    def _write_error(cls, message: str, context: dict[str, Any]) -> None:
        request_id = cls._get_request_id()
        timestamp = datetime.utcnow().isoformat()
        stack = traceback.format_stack()
        
        entry = f'[{timestamp}] ERROR [{request_id}] {message}\n'
        entry += f'Context: {json.dumps(context)}\n'
        entry += f'Stack:\n{"".join(stack)}\n'
        entry += '---\n'
        
        cls._ensure_dir(cls.ERROR_LOG)
        cls._rotate_if_needed(cls.ERROR_LOG)
        
        with open(cls.ERROR_LOG, 'a') as f:
            f.write(entry)
    
    @classmethod
    def _rotate_if_needed(cls, file_path: Path) -> None:
        if not file_path.exists():
            return
        
        if file_path.stat().st_size < cls.MAX_FILE_SIZE:
            return
        
        archive_dir = file_path.parent / 'archive'
        archive_dir.mkdir(parents=True, exist_ok=True)
        
        archive_name = f'{file_path.name}.{datetime.utcnow().strftime("%Y%m%d%H%M%S")}.gz'
        archive_path = archive_dir / archive_name
        
        with open(file_path, 'rb') as f_in:
            with gzip.open(archive_path, 'wb') as f_out:
                f_out.write(f_in.read())
        
        file_path.write_text('')
    
    @classmethod
    def _ensure_dir(cls, file_path: Path) -> None:
        file_path.parent.mkdir(parents=True, exist_ok=True)
    
    @classmethod
    def _get_request_id(cls) -> str:
        if cls._request_id is None:
            cls._request_id = f'req_{secrets.token_hex(8)}'
        return cls._request_id
    
    @classmethod
    def _send_alert(cls, message: str, context: dict[str, Any]) -> None:
        # Integration with alerting service
        pass
```

---

## 5. Log Rotation

### 5.1 Rotation Strategy

| Trigger | Action |
|---------|--------|
| File exceeds MAX_SIZE (default 10MB) | Compress and archive |
| Archive count exceeds MAX_ARCHIVES (default 10) | Delete oldest |
| Archive age exceeds MAX_AGE (default 30 days) | Delete expired |

### 5.2 Archive Structure

```
logs/
├── app.log              # Current general log
├── error.txt            # Current error log
└── archive/
    ├── app.log.2026-01-25-103000.gz
    ├── app.log.2026-01-20-143000.gz
    ├── error.txt.2026-01-24-120000.gz
    └── error.txt.2026-01-18-090000.gz
```

### 5.3 Numbered Archive Alternative

For systems requiring predictable archive names:

```
logs/
├── app.log
├── error.txt
└── archive/
    ├── 1/
    │   ├── app.log.gz
    │   └── error.txt.gz
    ├── 2/
    │   ├── app.log.gz
    │   └── error.txt.gz
    └── 3/
        ├── app.log.gz
        └── error.txt.gz
```

---

## 6. Context Requirements

### 6.1 What to Always Log

| Event Type | Required Context |
|------------|-----------------|
| User action | `user_id`, `action`, `resource` |
| API request | `method`, `path`, `status_code`, `duration_ms` |
| Database query | `query_type`, `table`, `duration_ms` |
| External call | `service`, `endpoint`, `status_code`, `duration_ms` |
| Error | `error_code`, `message`, `stack_trace` |
| State change | `entity`, `id`, `old_state`, `new_state` |

### 6.2 What to NEVER Log

| Category | Examples | Risk |
|----------|----------|------|
| Credentials | Passwords, API keys, tokens | Security breach |
| PII | SSN, credit cards, health data | Compliance violation |
| Raw request bodies | Form data with sensitive fields | Data exposure |

### 6.3 Sanitization Helper

```php
class LogSanitizer {
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd',
        'token', 'api_key', 'apikey',
        'secret', 'credential',
        'ssn', 'social_security',
        'credit_card', 'card_number',
    ];
    
    public static function sanitize(array $context): array {
        return self::recursiveSanitize($context);
    }
    
    private static function recursiveSanitize(array $data): array {
        $result = [];
        
        foreach ($data as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = self::recursiveSanitize($value);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    private static function isSensitiveKey(string $key): bool {
        $lowerKey = strtolower($key);
        
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lowerKey, $sensitive)) {
                return true;
            }
        }
        
        return false;
    }
}

// Usage
Logger::info("User registered", LogSanitizer::sanitize([
    'email' => 'user@example.com',
    'password' => 'secret123',  // Will become '[REDACTED]'
]));
```

---

## 7. Request Tracing

### 7.1 Request ID Propagation

Every log entry should include a request ID that follows the request through all layers:

```php
class RequestContext {
    private static ?string $requestId = null;
    
    public static function init(): void {
        // Check for incoming request ID (from load balancer, etc.)
        self::$requestId = $_SERVER['HTTP_X_REQUEST_ID'] 
            ?? 'req_' . bin2hex(random_bytes(8));
    }
    
    public static function getId(): string {
        if (isNull(self::$requestId)) {
            self::init();
        }
        return self::$requestId;
    }
    
    public static function addToResponse(): void {
        header('X-Request-ID: ' . self::getId());
    }
}
```

### 7.2 Correlation Across Services

For microservices, pass the request ID in headers:

```php
// Outgoing request
$client->request('GET', '/api/users', [
    'headers' => [
        'X-Request-ID' => RequestContext::getId(),
    ],
]);
```

---

## 8. Performance Logging

### 8.1 Timing Helper

```php
class Timer {
    private float $start;
    private string $label;
    
    public function __construct(string $label) {
        $this->label = $label;
        $this->start = microtime(true);
    }
    
    public function stop(): float {
        $duration = (microtime(true) - $this->start) * 1000;
        Logger::debug("{$this->label} completed", ['duration_ms' => round($duration, 2)]);
        return $duration;
    }
    
    public function stopAndWarn(float $thresholdMs): float {
        $duration = (microtime(true) - $this->start) * 1000;
        
        $context = ['duration_ms' => round($duration, 2)];
        
        if ($duration > $thresholdMs) {
            Logger::warning("{$this->label} exceeded threshold", array_merge($context, [
                'threshold_ms' => $thresholdMs,
            ]));
        } else {
            Logger::debug("{$this->label} completed", $context);
        }
        
        return $duration;
    }
}

// Usage
$timer = new Timer('database_query');
$result = $db->query($sql);
$timer->stopAndWarn(100); // Warn if >100ms
```

---

## 9. Anti-Patterns

### 9.1 Logging Without Context

```php
// ❌ INCORRECT - No context
Logger::error("Something went wrong");

// ✅ CORRECT - With context
Logger::error("Payment processing failed", [
    'order_id' => $orderId,
    'amount' => $amount,
    'error' => $e->getMessage(),
]);
```

### 9.2 Logging Sensitive Data

```php
// ❌ INCORRECT - Password logged
Logger::info("User login attempt", ['email' => $email, 'password' => $password]);

// ✅ CORRECT - Password excluded
Logger::info("User login attempt", ['email' => $email]);
```

### 9.3 Excessive Logging

```php
// ❌ INCORRECT - Logging inside tight loop
foreach ($items as $item) {
    Logger::debug("Processing item", ['id' => $item->id]);
    // ...
}

// ✅ CORRECT - Log summary
Logger::debug("Processing batch", ['count' => count($items)]);
foreach ($items as $item) {
    // ...
}
Logger::debug("Batch complete", ['count' => count($items)]);
```

### 9.4 Wrong Log Level

```php
// ❌ INCORRECT - Error for normal operation
Logger::error("User not found", ['id' => $userId]); // This is expected sometimes!

// ✅ CORRECT - Appropriate level
Logger::info("User not found", ['id' => $userId]);
```

---

## Mandatory Implementation Checklist

Before considering any implementation complete, verify:

- [ ] Dual-file logging implemented (general + error)
- [ ] All log entries include timestamp and request ID
- [ ] Log levels used appropriately (DEBUG, INFO, WARNING, ERROR, CRITICAL)
- [ ] Stack traces included for ERROR and CRITICAL
- [ ] Sensitive data sanitized before logging
- [ ] Log rotation implemented with compression
- [ ] Request ID propagated across all layers
- [ ] Performance-critical operations timed
- [ ] No logging inside tight loops
- [ ] CRITICAL logs trigger alerts

---

*This document establishes logging patterns. See [02-configuration-hierarchy-systems.md](./02-configuration-hierarchy-systems.md) for configuration management.*
