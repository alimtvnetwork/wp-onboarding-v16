# Persistent Log Deduplication (Info + Debug)

> **Applies to:** QUpload, RiseUp Asia Uploader  
> **Status:** Implementation  
> **Date:** 2026-03-31

---

## Problem

Boot-time info messages (e.g., "Routes registered: 88 OK, 0 failed") are logged on every WordPress request. Because the in-memory dedup only prevents duplicates within a single PHP request, these messages flood the info log across requests — producing hundreds of identical lines (see screenshot evidence).

## Solution

Add **persistent deduplication** for **Info and Debug** level log entries using a JSON registry file stored in the plugin's logs directory. The file tracks MD5 hashes of previously logged messages, keyed by hash with the log level as the value. If a hash is found in the registry, the log call is silently skipped.

## Scope

| Aspect | Decision |
|--------|----------|
| **Log levels affected** | Info + Debug — errors and warnings always log |
| **Reset strategy** | Reset on every deployment (version change clears the registry) |
| **Storage format** | JSON file: `logs/dedup-registry.json` |
| **Plugins** | Both QUpload and RiseUp Asia Uploader |

## Registry File Format

```json
{
  "version": "2.32.0",
  "hashes": {
    "a1b2c3d4e5f6...": "info",
    "f6e5d4c3b2a1...": "debug"
  }
}
```

- `version` — current plugin version at time of write
- `hashes` — map of MD5 hashes → log level string (`"info"` or `"debug"`)

## Dedup Flow

1. **On first Info log call**: load `dedup-registry.json` (lazy, once per request)
2. **Version check**: if stored version ≠ current plugin version → discard all hashes (fresh start)
3. **Hash check**: compute MD5 of `message|basename(file)|line` (no level prefix since only info)
4. **If hash exists** → skip logging, return `true`
5. **If hash is new** → log normally, add hash to in-memory map, persist to JSON file

## File Locking

All writes use `LOCK_EX` to prevent corruption from concurrent PHP requests.

## Implementation — RiseUp Asia

### New Trait: `LoggerPersistentDedupTrait`

**Location:** `wp-plugins/riseup-asia-uploader/includes/Logging/Traits/LoggerPersistentDedupTrait.php`

**Methods:**
- `isPersistentDuplicate(string $message, string $file, int $line): bool` — main check
- `loadPersistentDedupRegistry(): void` — lazy-load JSON, validate version
- `savePersistentDedupRegistry(): void` — write JSON with LOCK_EX
- `clearPersistentDedupRegistry(): void` — delete the JSON file (for log clear operations)

**Integration:** The `info()` method in `LoggerLevelMethodsTrait` calls `isPersistentDuplicate()` before the existing in-memory `isDuplicate()` check.

### FileLogger Changes

- Add `use LoggerPersistentDedupTrait;`
- Add properties: `private array $persistentDedupHashes = []`, `private bool $persistentDedupLoaded = false`
- Wire `clearPersistentDedupRegistry()` into `clearAllLogFiles()`

## Implementation — QUpload

Same logic added directly to QUpload's monolithic `FileLogger.php`:
- Add equivalent private methods
- Add same properties
- Wire into `info()` and `clearAllLogFiles()`

## Cleanup Integration

When `clearAllLogFiles()` is called (plugin activation, remote clear), also delete `dedup-registry.json` so fresh logs appear after a reset.

## Constants

```php
private const DEDUP_REGISTRY_FILENAME = 'dedup-registry.json';
private const DEDUP_MAX_ENTRIES = 500; // Cap to prevent unbounded growth
```

If `hashes` count exceeds 500, the oldest half is pruned on next save (simple array_slice since JSON objects maintain insertion order in PHP).
