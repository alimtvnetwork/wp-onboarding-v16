
# Riseup Asia Plugin - Phase 2 Enhancement Plan

## ✅ COMPLETED

### Part 1: Namespace Renaming
- [x] Updated `backend/internal/wordpress/constants.go` - `RiseupAsiaNamespace = "riseup-asia-uploader/v1"`
- [x] Updated `plugins-uploader-helper/includes/constants.php` - `RISEUP_API_NAMESPACE = 'riseup-asia-uploader'`
- [x] Updated all references in `uploader.go` to use new namespace

### Part 2: Seed Configuration
- [x] Added Riseup Asia Uploader to `backend/config.json` with category "Core"
- [x] Plugin auto-maps to all configured sites via existing seeding logic

### Part 3: Upload Ignore Parser
- [x] Created `plugins-uploader-helper/includes/class-upload-ignore.php` (PHP)
- [x] Created `backend/internal/services/plugin/ignore.go` (Go)
- [x] Created `plugins-uploader-helper/.uploadignore.example` template

### Part 4: Delta Sync Endpoint
- [x] Added `POST /riseup-asia-uploader/v1/plugins/{slug}/sync` PHP endpoint
- [x] Added `SyncPluginFilesViaUploader()` method to Go client
- [x] Supports `.uploadignore` pattern matching

### Part 5: Export-Self Endpoint
- [x] Added `GET /riseup-asia-uploader/v1/export-self` PHP endpoint
- [x] Added `ExportSelfFromSite()` method to Go client

### Part 6: Bootstrap Uploader API
- [x] Added `POST /api/v1/sites/{id}/bootstrap-uploader` backend endpoint
- [x] Added `BootstrapUploader()` method to Site service
- [x] Added "Deploy" button to SiteCard frontend component
- [x] Added `bootstrapUploader()` API method

### Part 7: Memory Updates
- [x] Updated `.lovable/memory/architecture/backend/wordpress-integration.md`

---

## File Changes Summary

| File | Status |
|------|--------|
| `backend/internal/wordpress/constants.go` | ✅ Updated |
| `backend/internal/wordpress/uploader.go` | ✅ Updated |
| `backend/config.json` | ✅ Updated |
| `plugins-uploader-helper/includes/constants.php` | ✅ Updated |
| `plugins-uploader-helper/includes/class-upload-ignore.php` | ✅ Created |
| `plugins-uploader-helper/riseup-asia.php` | ✅ Updated |
| `plugins-uploader-helper/.uploadignore.example` | ✅ Created |
| `backend/internal/services/plugin/ignore.go` | ✅ Created |
| `backend/internal/api/handlers/handlers.go` | ✅ Updated |
| `backend/internal/api/router.go` | ✅ Updated |
| `backend/internal/services/site/service.go` | ✅ Updated |
| `src/components/sites/SiteCard.tsx` | ✅ Updated |
| `src/lib/api.ts` | ✅ Updated |
| `.lovable/memory/architecture/backend/wordpress-integration.md` | ✅ Updated |

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
