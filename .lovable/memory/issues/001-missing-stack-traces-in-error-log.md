# Issue #001: Missing Stack Traces in error_log Catch Blocks

**Severity:** Critical  
**Discovered:** 2026-03-03  
**Status:** Fixed  

## Root Cause

Multiple `catch (Throwable $e)` blocks across all three WordPress plugins were logging only `$e->getMessage()` via `error_log()`, completely discarding the stack trace. This made production debugging nearly impossible because the error origin (file, line, call chain) was lost.

## Affected Files (14 catch blocks total)

### QUpload
- `qupload.php` — `qupload_init()` and `qupload_deactivate()` (2 blocks)
- `includes/Autoloader.php` — PSR-4 class loading (1 block)

### Riseup Asia Uploader
- `includes/Autoloader.php` — PSR-4 class loading + file pre-scan (2 blocks)

### Plugins Onboard
- `plugins-onboard.php` — activation, settings init, plugin init, cleanup (4 blocks)
- `includes/class-database.php` — get_setting, save_setting, get_all_settings (3 blocks)
- `includes/class-config.php` — DB read, config set (2 blocks)

## Fix Applied

Every `error_log($e->getMessage())` was changed to:
```php
error_log('Context: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
```

## Prevention

Coding standard `.lovable/memory/coding-standards/php-exception-handling.md` now mandates that **every catch block with `$e` must include `$e->getTraceAsString()`**. This is a non-negotiable rule.
