
# Rise Up Uploader Plugin - Enhancement Plan

## Summary
Transform the existing `Plugin Uploader Helper` WordPress plugin into a professional **Rise Up Uploader** plugin with:
- Centralized constants file (no magic strings)
- SQLite database for transaction logging (audit trail)
- REST API for querying logs with filtering and pagination
- Blog post/category publishing capabilities
- Corresponding Go backend constants and integration

---

## Phase 1: WordPress Plugin Restructure

### 1.1 Create Constants File
Create `plugins-uploader-helper/includes/constants.php` with all string constants:

```php
// Plugin Identity
RISEUP_UPLOADER_VERSION = '1.2.0'
RISEUP_UPLOADER_SLUG = 'riseup-uploader'
RISEUP_UPLOADER_NAME = 'Rise Up Uploader'

// REST API
RISEUP_API_NAMESPACE = 'riseup-uploader'
RISEUP_API_VERSION = 'v1'

// Database
RISEUP_DB_NAME = 'riseup_uploader.db'
RISEUP_TABLE_TRANSACTIONS = 'transactions'

// Actions
RISEUP_ACTION_UPLOAD = 'upload'
RISEUP_ACTION_ENABLE = 'enable'
RISEUP_ACTION_DISABLE = 'disable'
RISEUP_ACTION_DELETE = 'delete'
RISEUP_ACTION_FILE_REPLACE = 'file_replace'
RISEUP_ACTION_FILE_DELETE = 'file_delete'
RISEUP_ACTION_POST_CREATE = 'post_create'
RISEUP_ACTION_POST_UPDATE = 'post_update'
RISEUP_ACTION_CATEGORY_CREATE = 'category_create'

// Response Messages
RISEUP_MSG_SUCCESS = 'Operation completed successfully'
RISEUP_MSG_UNAUTHORIZED = 'Authentication required'
RISEUP_MSG_FORBIDDEN = 'Insufficient permissions'
// ... more messages
```

### 1.2 Create Database Class
Create `plugins-uploader-helper/includes/class-database.php`:

```text
+-----------------------------------------------------------+
|                    transactions table                      |
+-----------------------------------------------------------+
| id          | INTEGER PRIMARY KEY AUTOINCREMENT           |
| action      | TEXT NOT NULL (upload/enable/disable/etc)   |
| plugin_slug | TEXT (nullable for non-plugin operations)   |
| post_id     | INTEGER (nullable, for blog operations)     |
| user_login  | TEXT NOT NULL (WordPress username)          |
| user_id     | INTEGER (WordPress user ID)                 |
| ip_address  | TEXT NOT NULL                               |
| details     | TEXT (JSON blob with extra context)         |
| status      | TEXT NOT NULL (success/failed)              |
| error_msg   | TEXT (nullable)                             |
| created_at  | TEXT NOT NULL (ISO8601 timestamp)           |
+-----------------------------------------------------------+
```

Features:
- PDO SQLite connection with WAL mode
- Auto-create table on first use
- Log every operation with user context
- Query methods with filtering and pagination

### 1.3 Update Main Plugin File
Rename and restructure `plugin-uploader-helper.php` to `riseup-uploader.php`:

- Update plugin header (Rise Up Asia branding)
- Load constants file first
- Initialize database on activation
- Replace all magic strings with constants
- Add authentication verification wrapper
- Log every operation to SQLite

### 1.4 Add Blog Post Publishing Endpoints

New REST routes:
```text
POST /riseup-uploader/v1/posts          - Create a post
PUT  /riseup-uploader/v1/posts/{id}     - Update a post
GET  /riseup-uploader/v1/posts          - List posts
POST /riseup-uploader/v1/categories     - Create category
GET  /riseup-uploader/v1/categories     - List categories
```

Request body for post creation:
```json
{
  "title": "My Post Title",
  "slug": "my-post-slug",
  "content": "<p>HTML content...</p>",
  "status": "publish|draft",
  "categories": [1, 2, 3]
}
```

### 1.5 Add Transaction Log Query Endpoint

```text
GET /riseup-uploader/v1/logs
  ?plugin=<slug>          - Filter by plugin
  ?action=upload,enable   - Filter by action types
  ?user=<username>        - Filter by user
  ?status=success|failed  - Filter by status
  ?from=2026-01-01        - Date range start
  ?to=2026-02-01          - Date range end
  ?limit=50               - Page size (default 50, max 500)
  ?offset=0               - Pagination offset
```

Response:
```json
{
  "success": true,
  "total": 1234,
  "limit": 50,
  "offset": 0,
  "logs": [
    {
      "id": 1,
      "action": "upload",
      "plugin_slug": "category-generator",
      "user_login": "admin",
      "ip_address": "192.168.1.100",
      "status": "success",
      "created_at": "2026-02-04T12:00:00Z"
    }
  ]
}
```

---

## Phase 2: Go Backend Constants

### 2.1 Create WordPress Constants Package
Create `backend/internal/wordpress/constants.go`:

```go
package wordpress

// REST API Namespaces
const (
    RiseUpUploaderNamespace = "riseup-uploader/v1"
    OnboardNamespace        = "onboard-plugin/v1"  // legacy
)

// Endpoints
const (
    EndpointStatus     = "/status"
    EndpointUpload     = "/upload"
    EndpointPlugins    = "/plugins"
    EndpointPluginInfo = "/plugins/%s"
    EndpointEnable     = "/plugins/%s/enable"
    EndpointDisable    = "/plugins/%s/disable"
    EndpointDelete     = "/plugins/%s/delete"
    EndpointFiles      = "/plugins/%s/files"
    EndpointLogs       = "/logs"
    EndpointPosts      = "/posts"
    EndpointCategories = "/categories"
)

// Actions (match PHP constants)
const (
    ActionUpload      = "upload"
    ActionEnable      = "enable"
    ActionDisable     = "disable"
    ActionDelete      = "delete"
    ActionFileReplace = "file_replace"
    ActionFileDelete  = "file_delete"
    ActionPostCreate  = "post_create"
    ActionPostUpdate  = "post_update"
)

// HTTP Headers
const (
    HeaderAuth        = "Authorization"
    HeaderContentType = "Content-Type"
    HeaderUserAgent   = "User-Agent"
    UserAgentValue    = "WP-Plugin-Publish/1.0"
)

// Content Types
const (
    ContentTypeJSON      = "application/json"
    ContentTypeMultipart = "multipart/form-data"
)
```

### 2.2 Update uploader.go
Replace hardcoded strings with constants:
- Change `UploaderNamespace` to use `RiseUpUploaderNamespace`
- Update all endpoint construction to use constants
- Add methods for blog post publishing

### 2.3 Update publish service
- Add fallback chain: RiseUp Uploader → Onboard Plugin → WP Core API
- Log which companion was detected

---

## Phase 3: File Structure

### WordPress Plugin (Final)
```text
plugins-uploader-helper/
├── riseup-uploader.php              # Main plugin file (renamed)
├── includes/
│   ├── constants.php                # All string constants
│   ├── class-database.php           # SQLite database handler
│   ├── class-logger.php             # Transaction logging
│   └── class-post-manager.php       # Blog post operations
├── data/
│   └── .gitkeep                     # Database will be created here
└── README.md                        # Updated documentation
```

### Go Backend Changes
```text
backend/internal/wordpress/
├── constants.go       # NEW: All REST API constants
├── client.go          # Update to use constants
├── uploader.go        # Update namespace, add post methods
├── remote_files.go    # Update to use constants
└── powershell.go      # No changes needed
```

---

## Technical Details

### Authentication Flow
Every REST request will:
1. Check `Authorization` header (Basic auth with Application Password)
2. Validate credentials via `wp_authenticate_application_password()`
3. Verify user has required capability
4. Log the attempt (success or failure) to SQLite

### Database Location
SQLite file: `wp-content/plugins/riseup-uploader/data/riseup_uploader.db`
- Created with `PRAGMA journal_mode = WAL` for performance
- Auto-vacuum enabled
- Foreign keys off (single table)

### Pagination Implementation
```php
$stmt = $pdo->prepare("
    SELECT * FROM transactions
    WHERE (:plugin IS NULL OR plugin_slug = :plugin)
      AND (:action IS NULL OR action = :action)
      AND (:user IS NULL OR user_login = :user)
      AND (:status IS NULL OR status = :status)
      AND (:from IS NULL OR created_at >= :from)
      AND (:to IS NULL OR created_at <= :to)
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
```

---

## Implementation Order

1. **PHP Constants File** - Foundation for all strings
2. **PHP Database Class** - SQLite setup and queries
3. **PHP Logger Class** - Transaction logging wrapper
4. **Update Main Plugin** - Rebrand and integrate
5. **Add Log Query Endpoint** - REST API for logs
6. **Add Post Publishing** - Blog post/category endpoints
7. **Go Constants Package** - Backend string centralization
8. **Update Go Client** - Use new namespace and add post methods
9. **Update Memory Files** - Document the changes

---

## Testing Checklist

- [ ] Plugin activates without errors
- [ ] SQLite database is created on first use
- [ ] All operations are logged to database
- [ ] Log query endpoint returns correct results
- [ ] Pagination works correctly
- [ ] Authentication blocks unauthorized requests
- [ ] Blog post creation works
- [ ] Category creation works
- [ ] Go backend detects new plugin correctly
- [ ] Upload flow works end-to-end
