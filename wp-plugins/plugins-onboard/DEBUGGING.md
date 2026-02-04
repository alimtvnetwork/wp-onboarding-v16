# Plugins Onboard - Debugging Guide

## Overview

The plugin includes comprehensive logging and proper initialization order to help trace execution and identify issues during initialization and activation.

## Initialization Order (CRITICAL)

The plugin follows a strict initialization order to prevent errors:

1. **STEP 1: Directories** - All directories are created and verified FIRST
2. **STEP 2: Database** - Database is initialized ONLY AFTER directories exist
3. **STEP 3+: Components** - All other components initialize after database is ready

This order is enforced by **reusable helper functions** in `OnboardInitHelpers` class:
- `ensure_directories_exist()` - Creates/verifies all directories (only runs once)
- `ensure_database_ready()` - Initializes database (only runs once, requires directories)

## Log Files Location

All log files are stored in:
```
wp-content/uploads/plugins-onboard/logs/
```

### Available Log Files

1. **debug.log** - Detailed execution trace
2. **error.log** - Errors and exceptions with stack traces

## Enabling/Disabling Logging

Logging is controlled by constants in `includes/constants.php`:

```php
// Enable debug logging (trace every step)
define('ONBOARD_DEBUG_LOGGING', true);  // Set to false to disable

// Enable error logging (exceptions and errors)
define('ONBOARD_ERROR_LOGGING', true);  // Set to false to disable
```

## What Gets Logged

### Debug Log (debug.log)
- Plugin initialization start
- PHP and WordPress versions
- Class loading (each file loaded)
- Constructor calls
- Hook registrations
- Database connection steps
- Directory creation
- Component initialization
- Success/failure for each step

### Error Log (error.log)
- Critical errors with full stack traces
- Database connection failures
- Missing classes or dependencies
- File loading errors
- Exception details (message, code, file, line, trace)

## How to Debug Plugin Activation

1. **Clear the logs** (optional):
   ```
   Delete: wp-content/uploads/plugins-onboard/logs/debug.log
   Delete: wp-content/uploads/plugins-onboard/logs/error.log
   ```

2. **Try to activate the plugin** in WordPress admin

3. **Check the debug log**:
   ```
   Open: wp-content/uploads/plugins-onboard/logs/debug.log
   ```
   - Look for the last successful step
   - The log stops where the error occurred

4. **Check the error log**:
   ```
   Open: wp-content/uploads/plugins-onboard/logs/error.log
   ```
   - Find exception details
   - Review stack traces
   - Identify the exact file and line number causing issues

## Common Issues and Solutions

### Issue: Plugin won't activate

**Check:**
1. PDO SQLite extension installed?
   - Look for: "pdo_sqlite extension is loaded" in debug.log
   - If not found, install PHP SQLite extension

2. Directory permissions
   - Look for: "Database directory is writable" in debug.log
   - If not, check permissions on wp-content/uploads/

3. Missing classes
   - Look for: "✓ Loaded successfully" for each file
   - If you see "✗ File not found", check file exists

### Issue: Database connection fails

**Check debug.log for:**
- "Database directory: [path]" - Is path correct?
- "Database directory created successfully" - Did creation work?
- "Database directory is writable" - Check permissions
- "Main database PDO connection established" - Did connection succeed?

**Check error.log for:**
- PDO exceptions with detailed error messages
- File permission errors
- SQLite errors

### Issue: Components not initializing

**Check debug.log for:**
- "Initializing OnboardAuditLogger..." - Each component logs initialization
- Look for "✓ OnboardXXX initialized" vs "✗ OnboardXXX class not found"
- Missing dependencies will be logged

## Log Format

### Debug Log Entry Format:
```
[2025-01-11 15:30:45] [DEBUG] [Memory: 12.5 MB] Message here
--------------------------------------------------------------------------------
```

### Error Log Entry Format:
```
[2025-01-11 15:30:45] [ERROR] [Memory: 12.5 MB] Error message
Context: Array
(
    [exception] => Array
        (
            [message] => Error details
            [code] => 0
            [file] => /path/to/file.php
            [line] => 123
            [trace] => Full stack trace here
        )
)
--------------------------------------------------------------------------------
```

## Reading the Logs

### Step-by-Step Execution Trace

The debug log shows exactly how far the plugin got before failing:

```
=== PLUGIN INITIALIZATION STARTED ===
Plugin Version: 1.0.6
WordPress Version: 6.4.2
PHP Version: 8.1.0
Loading OnboardPaths class...
OnboardPaths class loaded successfully
Loading OnboardConfig class...
OnboardConfig class loaded successfully
Loading plugin dependencies...
Loading file: includes/class-database.php
  ✓ Loaded successfully: includes/class-database.php
...
[Last successful line before error]
```

### Finding the Exact Failure Point

1. Scroll to the bottom of debug.log
2. The last logged message shows where execution stopped
3. Check error.log for the corresponding exception
4. The exception shows:
   - Exact file path
   - Line number
   - Full stack trace

## Disabling Logs in Production

Once debugging is complete, disable logs by editing `includes/constants.php`:

```php
define('ONBOARD_DEBUG_LOGGING', false);
define('ONBOARD_ERROR_LOGGING', false);
```

Or set environment variables:
```php
putenv('ONBOARD_DEBUG_LOGGING=false');
putenv('ONBOARD_ERROR_LOGGING=false');
```

## Cron Tasks

**NOTE:** All cron task scheduling has been removed from the plugin as requested. The plugin no longer schedules any automated cleanup tasks.

## Support

If you need help interpreting the logs:

1. Copy the last 50-100 lines from both log files
2. Include your PHP version and WordPress version
3. Include any error messages from WordPress admin
4. Contact support with this information

---

**Remember:** Debug logging can be verbose. It's recommended for development and troubleshooting only, not for production use.
