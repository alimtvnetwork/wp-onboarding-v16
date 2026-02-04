# Rise Up Asia Plugin - Enhancement Plan (Phase 2)

## Summary
Enhance the Rise Up Asia WordPress plugin with delta file sync, ignore patterns, micro-ORM, media library uploads, and self-upload capability.

**Previous Phase (Completed):**
- Created Rise Up Uploader with constants, SQLite logging, blog posts, categories
- Added Go backend constants

---

## Phase 1: Plugin Renaming & Branding

### 1.1 Rename to "Rise Up Asia"
- Update plugin header: `Rise Up Asia` (with lowercase "up")
- Update API namespace: `riseup-asia/v1`
- Update all constants to use `RISEUP_ASIA_` prefix
- Rename folder: `plugins-uploader-helper` → `riseup-asia`

---

## Phase 2: Micro-ORM Implementation

### 2.1 Create ORM Wrapper
Create `includes/class-orm.php` with Idiorm-style fluent interface:

```php
// Usage examples:
$logs = ORM::for_table('transactions')
    ->where('action', 'upload')
    ->where_gte('created_at', '2026-01-01')
    ->order_by_desc('created_at')
    ->limit(50)
    ->find_many();

$log = ORM::for_table('transactions')
    ->create()
    ->set('action', 'upload')
    ->set('plugin_slug', 'my-plugin')
    ->save();
```

Features:
- Fluent query builder
- Automatic escaping via PDO prepared statements
- CRUD operations (create, read, update, delete)
- Method chaining
- Works with existing PDO connection

### 2.2 Update Database Class
Refactor `class-database.php` to use the new ORM wrapper while maintaining backward compatibility.

---

## Phase 3: Delta File Sync with Ignore Patterns

### 3.1 Add `.uploadignore` Support
Create `includes/class-upload-ignore.php`:

```php
class RiseUp_Upload_Ignore {
    // Parse .uploadignore file (gitignore syntax)
    public function parse($plugin_dir);
    
    // Check if a file path matches ignore patterns
    public function should_ignore($relative_path);
}
```

Supported patterns:
- `*.log` - Ignore all .log files
- `node_modules/` - Ignore entire directory
- `!important.log` - Exception (don't ignore)
- `#` - Comments
- `vendor/` - Ignore vendor folder

### 3.2 New Endpoint: Delta Sync Upload
```
POST /riseup-asia/v1/plugins/{slug}/sync
```

Request body:
```json
{
  "files": [
    {
      "path": "includes/class-helper.php",
      "content": "<base64>",
      "action": "replace"
    },
    {
      "path": "old-file.php",
      "action": "delete"
    }
  ]
}
```

Response:
```json
{
  "success": true,
  "files_updated": 3,
  "files_deleted": 1,
  "files_ignored": 2,
  "ignored_patterns": ["*.log", "node_modules/"]
}
```

---

## Phase 4: Media Library Upload

### 4.1 Create Media Manager Class
Create `includes/class-media-manager.php`:

```php
class RiseUp_Media_Manager {
    // Upload image to WordPress Media Library
    public function upload_image($base64_data, $filename, $alt_text = '');
    
    // Get attachment URL by ID
    public function get_attachment_url($attachment_id);
    
    // Delete attachment
    public function delete_attachment($attachment_id);
}
```

### 4.2 New Endpoint: Media Upload
```
POST /riseup-asia/v1/media
```

Request body:
```json
{
  "filename": "hero-image.jpg",
  "content": "<base64>",
  "alt_text": "Hero banner image"
}
```

Response:
```json
{
  "success": true,
  "attachment_id": 123,
  "url": "https://example.com/wp-content/uploads/2026/02/hero-image.jpg",
  "thumbnail": "https://example.com/wp-content/uploads/2026/02/hero-image-150x150.jpg"
}
```

### 4.3 Update Post Creation
Enhance `/posts` endpoint to accept `featured_image` (attachment ID or base64).

---

## Phase 5: Upload + Enable Endpoint

### 5.1 New Endpoint: Upload Active
```
POST /riseup-asia/v1/upload-active
```

Same as `/upload` but always activates the plugin after upload.

Response includes activation status and any activation errors.

---

## Phase 6: Self-Upload Capability

### 6.1 New Endpoint: Export Self
```
GET /riseup-asia/v1/export-self
```

Returns the Rise Up Asia plugin as a base64-encoded ZIP, allowing it to be uploaded to other WordPress sites.

Response:
```json
{
  "success": true,
  "plugin_name": "Rise Up Asia",
  "version": "1.3.0",
  "plugin_zip": "<base64>",
  "checksum": "md5hash"
}
```

### 6.2 Backend Integration
- Add Rise Up Asia to seed configuration as a default plugin
- Create helper method to package and upload the plugin to target sites

---

## Phase 7: Go Backend Updates

### 7.1 Update Constants
Update `backend/internal/wordpress/constants.go`:
- Change namespace to `riseup-asia/v1`
- Add new endpoints (sync, media, upload-active, export-self)

### 7.2 Add to Seed Configuration
Update `backend/config.json`:
```json
{
  "plugins": [
    {
      "name": "Rise Up Asia",
      "path": "riseup-asia",
      "slug": "riseup-asia",
      "category": "core"
    }
  ]
}
```

### 7.3 New Uploader Methods
Add methods to Go client:
- `SyncFilesViaUploader()` - Delta file sync
- `UploadMediaViaUploader()` - Media library upload
- `ExportUploaderPlugin()` - Get self-export ZIP
- `BootstrapUploaderToSite()` - Install Rise Up Asia on a new site

---

## File Structure (Final)

```
riseup-asia/
├── riseup-asia.php               # Main plugin file
├── includes/
│   ├── constants.php             # All string constants
│   ├── class-orm.php             # NEW: Micro-ORM wrapper
│   ├── class-database.php        # Database handler (uses ORM)
│   ├── class-logger.php          # Transaction logging
│   ├── class-post-manager.php    # Blog post operations
│   ├── class-media-manager.php   # NEW: Media library uploads
│   └── class-upload-ignore.php   # NEW: .uploadignore parser
├── data/
│   └── riseup_asia.db            # SQLite database
├── .uploadignore.example         # Example ignore file
└── README.md
```

---

## Implementation Order

1. Rename plugin to "Rise Up Asia" and update namespace
2. Create micro-ORM wrapper
3. Refactor database class to use ORM
4. Create .uploadignore parser
5. Add delta sync endpoint
6. Create media manager class
7. Add media upload endpoint
8. Enhance post endpoint with featured image
9. Add upload-active endpoint
10. Add export-self endpoint
11. Update Go backend constants
12. Add to seed configuration
13. Update memory documentation

---

## Testing Checklist

- [ ] Plugin activates without errors
- [ ] ORM queries work correctly
- [ ] .uploadignore patterns are respected
- [ ] Delta sync updates correct files
- [ ] Media uploads to WordPress library
- [ ] Posts can have featured images
- [ ] Upload-active endpoint works
- [ ] Export-self returns valid ZIP
- [ ] Go backend detects new namespace
- [ ] Seed configuration includes Rise Up Asia
