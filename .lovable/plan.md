

## Fix Circular Dependency Crash in PHP Plugin

### Problem

The plugin crashes on startup with `Class "RiseupFileLogger" not found` due to two issues:

1. **Wrong class name**: `RiseupPathUtils::getLogger()` references `RiseupFileLogger::getInstance()` but the actual class is `Riseup_File_Logger::get_instance()`
2. **Circular dependency**: Even with the correct name, there is an infinite loop:
   - `Riseup_File_Logger::write()` calls `initialize_paths()` 
   - which calls `ensure_directories()` 
   - which calls `RiseupInitHelpers::ensureDir()` 
   - which delegates to `RiseupPathUtils::ensureDir()` 
   - which calls `getLogger()` 
   - which tries to create `Riseup_File_Logger` again

### Solution

Break the circular dependency by making `RiseupPathUtils` logger-independent for core operations, and fix initialization order throughout.

### Changes

#### 1. `class-path-utils.php` - Remove logger dependency from `ensureDir()`

- Replace `getLogger()` with a **safe logger pattern**: if the logger is not yet available, fall back to `error_log()` 
- Fix the class name from `RiseupFileLogger` to `Riseup_File_Logger`
- Make `ensureDir()` work without logging when called during logger initialization (detect re-entrant calls)
- Add a static `$initializing` guard flag to prevent circular calls

#### 2. `class-init-helpers.php` - Use native fallback during early init

- In `ensureDir()`, when called from `Riseup_File_Logger` initialization context, always use the `ensureDirNative()` fallback instead of delegating to `RiseupPathUtils` (which triggers the circular dependency)
- Add a static `$in_early_init` flag that `Riseup_File_Logger` can set before calling directory creation

#### 3. `class-file-logger.php` - Signal early init phase

- In `ensure_directories()`, use `RiseupInitHelpers::ensureDirNative()` directly instead of `ensureDir()` to avoid the PathUtils circular path entirely
- This is the simplest and most defensive fix: the logger creates its own directories using raw PHP, not the full PathUtils chain

#### 4. `riseup-asia-uploader.php` - Swap load order in manifest

- Move `PathUtils` **before** `FileLogger` in the dependency manifest so the class is available when the logger initializes
- Bump version to **v1.24.0**

#### 5. `includes/constants.php` - Version bump to v1.24.0

### Technical Details

The core principle: **the logger must never depend on utilities that depend on the logger**. The fix ensures `Riseup_File_Logger::ensure_directories()` uses only native PHP (`mkdir`, `wp_mkdir_p`) to create its directories, completely bypassing `RiseupPathUtils` during logger bootstrap. All other code can continue using `RiseupPathUtils` with logging as normal, since by the time they run, the logger is fully initialized.

Additionally, `RiseupPathUtils::getLogger()` will be updated with:
- Correct class name (`Riseup_File_Logger::get_instance()`)
- A re-entrancy guard so it returns `null` if called during logger initialization
- Null-safe logging calls throughout the class (check if logger is available before calling `->info()`, `->error()`, etc.)

