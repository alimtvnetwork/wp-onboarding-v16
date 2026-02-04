
# Riseup Asia Plugin - Phase 2 Enhancement Plan

## Summary

This plan addresses four requirements:
1. Rename namespace constant to `RiseupAsiaNamespace` with value `"riseup-asia-uploader/v1"`
2. Add Riseup Asia plugin to seed configuration so it auto-maps to all sites
3. Add single-button "Deploy Self" capability to upload the plugin to connected websites
4. Implement `.uploadignore` parser and delta sync endpoint for multi-file uploads

---

## Part 1: Namespace Renaming

### 1.1 Go Backend Constants
**File:** `backend/internal/wordpress/constants.go`

Change:
```go
// OLD
RiseUpAsiaNamespace = "riseup-asia/v1"

// NEW  
RiseupAsiaNamespace = "riseup-asia-uploader/v1"
```

Update all references from `RiseUpAsiaNamespace` to `RiseupAsiaNamespace` throughout the backend.

### 1.2 PHP Plugin Constants
**File:** `plugins-uploader-helper/includes/constants.php`

Change:
```php
// OLD
define('RISEUP_API_NAMESPACE', 'riseup-asia');

// NEW
define('RISEUP_API_NAMESPACE', 'riseup-asia-uploader');
```

---

## Part 2: Add Rise Up Asia to Seed Configuration

### 2.1 Update Seed Config
**File:** `backend/config.json`

Add to the `plugins` array:
```json
{
  "name": "Riseup Asia Uploader",
  "path": "riseup-asia-uploader",
  "category": "Core",
  "gitEnabled": false,
  "autoPublish": false,
  "siteNames": ["Atto Property Demo"]
}
```

so change path name accordingly

The `path` should be relative to the project root or an absolute path depending on your deployment.

**Note:** Since the seed config uses `siteNames: []`, it will auto-map to all sites due to the `ensureMappingsExist` logic that runs on every startup.

---

## Part 3: Deploy Self Capability

### 3.1 PHP Export-Self Endpoint
**File:** `plugins-uploader-helper/riseup-asia.php`

Add new REST route:
```
GET /riseup-asia-uploader/v1/export-self
```

Response:
```json
{
  "success": true,
  "plugin_name": "Riseup Asia Uploader",
  "version": "1.3.0",
  "plugin_zip": "<base64-encoded-zip>",
  "checksum": "<md5-hash>"
}
```

Implementation:
- Zip the current plugin directory (excluding `.uploadignore` patterns)
- Base64 encode the ZIP
- Calculate MD5 checksum
- Return in response

### 3.2 Go Backend Methods
**File:** `backend/internal/wordpress/uploader.go`

Add new methods:
```go
// ExportSelfFromSite fetches the Rise Up Asia plugin ZIP from a site
func (c *Client) ExportSelfFromSite() (*ExportSelfResult, error)

// BootstrapRiseupAsiaToSite uploads Rise Up Asia plugin to a new site
func (c *Client) BootstrapRiseupAsiaToSite(targetSite SiteCredentials) error
```

### 3.3 API Endpoint for Deploy Self
**File:** `backend/internal/api/handlers/handlers.go`

Add new endpoint:
```
POST /api/v1/sites/{id}/bootstrap-uploader
```

This endpoint:
1. Reads Riseup Asia plugin from local `plugins-uploader-helper/` directory
2. Creates a ZIP file
3. Uploads to the target site using standard WordPress API
4. Activates the plugin

### 3.4 Frontend Deploy Button
**File:** `src/components/sites/SiteCard.tsx`

Add a "Deploy Uploader" button that:
- Calls `POST /api/v1/sites/{id}/bootstrap-uploader`
- Shows progress in a dialog
- Reports success/failure

---

## Part 4: Upload Ignore Parser

### 4.1 Create Upload Ignore Class
**File:** `plugins-uploader-helper/includes/class-upload-ignore.php`

```php
class RiseUp_Upload_Ignore {
    private $patterns = [];
    private $negations = [];
    
    // Load patterns from .uploadignore file
    public function load($plugin_dir): bool
    
    // Check if a path should be ignored
    public function should_ignore($relative_path): bool
    
    // Get all active patterns
    public function get_patterns(): array
}
```

**Supported Pattern Syntax:**
| Pattern | Description |
|---------|-------------|
| `*.log` | Ignore all .log files |
| `node_modules/` | Ignore directory |
| `/build/` | Ignore only root build/ |
| `!important.log` | Exception (don't ignore) |
| `# comment` | Comments |
| `**/*.tmp` | Recursive glob |

### 4.2 Go Upload Ignore Parser
**File:** `backend/internal/services/plugin/ignore.go`

Create Go equivalent for local file operations:
```go
type UploadIgnore struct {
    patterns  []string
    negations []string
}

func LoadUploadIgnore(pluginDir string) (*UploadIgnore, error)
func (u *UploadIgnore) ShouldIgnore(relPath string) bool
func (u *UploadIgnore) GetPatterns() []string
```

### 4.3 Example .uploadignore File
Create `plugins-uploader-helper/.uploadignore.example`:
```text
# Riseup Asia - Upload Ignore Patterns

# Development files
.git/
.gitignore
.uploadignore

# Dependencies
node_modules/
vendor/

# Build artifacts
*.log
*.tmp
.DS_Store

# IDE
.idea/
.vscode/
*.swp

# Keep these files (negation)
!vendor/autoload.php
```

---

## Part 5: Delta Sync Endpoint

### 5.1 PHP Sync Endpoint
**File:** `plugins-uploader-helper/riseup-asia.php`

Add REST route:
```
POST /riseup-asia-uploader/v1/plugins/{slug}/sync
```

Request:
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
  "ignored_files": ["debug.log", "node_modules/package.json"],
  "results": [
    {"path": "includes/class-helper.php", "action": "replaced", "status": "success"},
    {"path": "old-file.php", "action": "deleted", "status": "success"}
  ]
}
```

### 5.2 Sync Handler Logic
1. Load `.uploadignore` from target plugin directory
2. For each file in request:
   - Check if path matches ignore patterns → skip with reason
   - If `action: "replace"`: decode base64, write file, create parent dirs
   - If `action: "delete"`: remove file if exists
3. Log transaction to SQLite with user context
4. Return summary with ignored files list

### 5.3 Go Delta Sync Method
**File:** `backend/internal/wordpress/uploader.go`

```go
type SyncFile struct {
    Path    string `json:"path"`
    Content string `json:"content,omitempty"` // base64
    Action  string `json:"action"`            // "replace" or "delete"
}

type SyncResult struct {
    Success       bool           `json:"success"`
    FilesUpdated  int            `json:"files_updated"`
    FilesDeleted  int            `json:"files_deleted"`
    FilesIgnored  int            `json:"files_ignored"`
    IgnoredFiles  []string       `json:"ignored_files"`
    Results       []SyncFileResult `json:"results"`
}

func (c *Client) SyncPluginFilesViaUploader(slug string, files []SyncFile) (*SyncResult, error)
```

---

## Implementation Order

1. **Namespace Update** (Go + PHP)
   - Update `constants.go` 
   - Update `constants.php`
   - Update all references in `uploader.go`

2. **Seed Configuration**
   - Add Riseup Asia to `config.json`

3. **Upload Ignore Parser**
   - Create `class-upload-ignore.php`
   - Create `ignore.go`
   - Add `.uploadignore.example`

4. **Delta Sync Endpoint**
   - Register `/sync` route in PHP
   - Implement sync handler with ignore support
   - Add `SyncPluginFilesViaUploader()` to Go client

5. **Export-Self Endpoint**
   - Add `/export-self` route in PHP
   - Implement ZIP generation

6. **Bootstrap Uploader API**
   - Add `/api/v1/sites/{id}/bootstrap-uploader` endpoint
   - Implement ZIP creation from local folder
   - Add frontend button

7. **Memory Updates**
   - Update documentation files

---

## File Changes Summary

| File | Action |
|------|--------|
| `backend/internal/wordpress/constants.go` | Update namespace |
| `backend/internal/wordpress/uploader.go` | Update references, add methods |
| `backend/config.json` | Add Riseup Asia to seed plugins |
| `plugins-uploader-helper/includes/constants.php` | Update namespace |
| `plugins-uploader-helper/includes/class-upload-ignore.php` | **NEW** |
| `plugins-uploader-helper/riseup-asia.php` | Add `/sync` and `/export-self` routes |
| `plugins-uploader-helper/.uploadignore.example` | **NEW** |
| `backend/internal/services/plugin/ignore.go` | **NEW** |
| `backend/internal/api/handlers/handlers.go` | Add bootstrap endpoint |
| `src/components/sites/SiteCard.tsx` | Add deploy button |
| `.lovable/memory/architecture/backend/wordpress-integration.md` | Update docs |

---

## Testing Checklist

- [ ] Namespace `riseup-asia-uploader/v1` works in PHP and Go
- [ ] Riseup Asia appears in seeded plugins list
- [ ] Plugin maps to all configured sites on startup
- [ ] `.uploadignore` patterns are correctly parsed
- [ ] Ignored files are skipped during sync
- [ ] Delta sync updates/deletes correct files
- [ ] Export-self returns valid base64 ZIP
- [ ] Bootstrap uploader deploys plugin to new site
- [ ] All transactions logged to SQLite
