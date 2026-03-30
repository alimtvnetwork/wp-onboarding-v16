# Issue 019 — FileCache::getManifest() Type Mismatch (HTTP 500)

## Symptom

Two REST API endpoints fail with HTTP 500:
- `POST /plugins/sync-manifest`
- `POST /plugins/files`

Error: `FileCache::getManifest(): Argument #3 ($ignore) must be of type RiseupAsia\Database\Traits\RiseupUploadIgnore, but given: RiseupAsia\Upload\UploadIgnore`

## Root Cause

`FileCacheScanTrait` type-hinted `$ignore` as `RiseupUploadIgnore` (a non-existent interface in the `Database\Traits` namespace). PHP resolved this as a class reference within the trait's namespace. Callers passed `RiseupAsia\Upload\UploadIgnore` which is a concrete class that didn't implement the expected type.

## Solution

1. **Created** `RiseupUploadIgnore` as an interface in `Database\Traits` with `shouldIgnore()` and `isLoaded()` methods.
2. **Updated** `Upload\UploadIgnore` to `implements RiseupUploadIgnore`.
3. **Fixed** `PluginExportTrait` which used a global `\RiseupUploadIgnore` reference → now uses the fully-qualified namespace.

## Files Modified

| File | Change |
|------|--------|
| `includes/Database/Traits/RiseupUploadIgnore.php` | Created interface |
| `includes/Upload/UploadIgnore.php` | Added `implements RiseupUploadIgnore` |
| `includes/Traits/Plugin/PluginExportTrait.php` | Fixed FQCN reference |
