# Technical Notes

## Duplicate Plugin Root Cause (Phase 3)

The `extractTo()` in `riseup-asia-uploader.php` used whatever folder name was inside the ZIP archive. If the ZIP contained `Category Generator/` but the expected slug was `category-generator/`, both folders existed after extraction.

**Solution**: Extract to temp dir → find extracted folder → rename to correct slug → cleanup temp.

```php
$temp_extract = $temp_dir . '/extract_' . uniqid();
$zip->extractTo($temp_extract);
$extracted_dirs = glob($temp_extract . '/*', GLOB_ONLYDIR);
$extracted_folder = $extracted_dirs[0];
$target_dir = $plugins_dir . '/' . $slug;
if (is_dir($target_dir)) { $this->delete_directory($target_dir); }
rename($extracted_folder, $target_dir);
$this->delete_directory($temp_extract);
```

## Error Handling Pattern

All errors use `apperror.Wrap(err, code, message)` or `apperror.New(code, message)` with `.WithContext(key, value)` for diagnostic data. `fmt.Errorf` is prohibited for errors leaving a service boundary.

## PHP Class Naming Convention

All WordPress plugin classes use PascalCase without underscores (e.g., `RiseupSnapshotScheduler`, `RiseupAgentManager`).
