# Memory: architecture/wordpress-plugin/deployment-logic
Updated: 2026-02-05

---

## Overview

The Riseup Asia Uploader plugin uses a **temp-extract-then-rename** strategy to prevent duplicate plugin entries caused by folder name mismatches in ZIP archives.

## The Problem

When a ZIP archive contains a folder like `my-plugin-v2/` but the target slug is `my-plugin`, direct extraction to `WP_PLUGIN_DIR` creates a new folder instead of replacing the existing one, resulting in duplicate plugin entries.

## The Solution

The upload handler (`handle_upload`) follows this sequence:

1. **Extract to temp directory first**:
   ```php
   $temp_extract_dir = $this->get_temp_dir() . '/extract_' . uniqid();
   wp_mkdir_p($temp_extract_dir);
   $zip->extractTo($temp_extract_dir);
   ```

2. **Identify extracted folder**:
   ```php
   $extracted_folders = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
   $extracted_folder = $extracted_folders[0];
   ```

3. **Rename to correct slug location**:
   ```php
   $target_dir = WP_PLUGIN_DIR . '/' . $slug;
   rename($extracted_folder, $target_dir);
   ```

4. **Fallback to copy if rename fails** (cross-device moves):
   ```php
   $this->copy_directory($extracted_folder, $target_dir);
   $this->delete_directory($extracted_folder);
   ```

5. **Cleanup temp directory**:
   ```php
   $this->delete_directory($temp_extract_dir);
   ```

## Key Benefits

- **Prevents duplicates**: Target folder is always `$slug`, regardless of ZIP internal folder name
- **Clean updates**: Old version is deleted before new version is placed
- **Cross-device compatible**: Falls back to copy+delete if rename fails
- **Maintains activation state**: Tracks `$was_active` and re-activates after update

## Related Files

| File | Purpose |
|------|---------|
| `wp-plugins/riseup-asia-uploader/riseup-asia-uploader.php` | Main plugin with `handle_upload()` method |
| `backend/internal/wordpress/uploader.go` | Go backend that creates ZIP archives |
| `backend/internal/services/publish/service.go` | Orchestrates the publish pipeline |

---

*See also: `.lovable/memory/architecture/backend/zip-creation-rules.md`*
