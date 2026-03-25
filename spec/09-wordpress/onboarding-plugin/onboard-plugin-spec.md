# 📋 DETAILED WORDPRESS PLUGIN SPECIFICATION

**Project Name:** Plugins Onboard - Advanced Plugin Manager & Sync Tool

**Core Function:**
Build a WordPress REST API-based plugin manager that enables:
1. Remote control of plugins (enable, disable, delete)
2. Automatic versioned backups before changes
3. Restore to previous plugin versions
4. Cross-WordPress restoration
5. Centralized debugging and maintenance mode
6. Multi-application management with approval workflows
7. Complete database export/import and backup management

---

## AUTHENTICATION ARCHITECTURE

**Primary Method: OAuth 2.0 Authorization Code Flow**

```
Implementation Requirements:
- Use WordPress REST API with custom endpoint: /wp-json/onboard-plugin/v1/auth
- Register OAuth app details in SQLite database (plugin-manager.sqlite)
- Generate authorization URL with scopes: 
  * onboard:plugin_manage (enable/disable/delete plugins)
  * onboard:plugin_backup (create backups)
  * onboard:plugin_restore (restore from backups)
  * onboard:debug_mode (toggle debug)
  * onboard:maintenance (toggle maintenance)

- Admin clicks authorize link
- System redirects to callback with authorization code
- Backend exchanges code for access_token (JWT-based)
- Access token valid 1 hour, includes scopes
- Refresh token valid 30 days, stores in encrypted SQLite

Application Registration:
- External application provides: app_name, redirect_uri, description
- System generates: client_id (unique), client_secret (cryptographic)
- Admin must approve application before first use
- Application linked to IP whitelist (admin-approved IPs only)
- New IP from same application triggers admin email notification
- Admin must approve new IP from WordPress plugin settings panel
```

---

## TEMPORARY MUTATION TOKEN SYSTEM

**Workflow:**

```
1. External application sends GET /wp-json/onboard-plugin/v1/request-mutation
   - Headers: Authorization: Bearer {access_token}
   - Body (optional): action={specific_action}
   
2. WordPress validates access_token, checks scopes, verifies IP whitelist
   - If IP not in whitelist: send admin email notification
   - Pause mutations until admin approves new IP
   
3. If valid, generates MUTATION_TOKEN:
   - Type: Cryptographically secure random (32+ bytes, hex encoded)
   - Expires: MUTATION_TOKEN_TTL (configurable, default 20 minutes)
   - Scoped to: specific action (upload, delete, enable, disable)
   - Action: plugin_upload → grants ONLY /mutations/{token}/plugins/upload
   - IP Scoped: requesting client IP added to token validation
   
4. Returns JSON:
   {
     "mutation_token": "a7f2d8e9c3b4a1f6...",
     "mutation_endpoint": "/wp-json/onboard-plugin/v1/mutations/a7f2d8e9c3b4a1f6/plugins/upload",
     "expires_in": 1200,
     "action": "plugin_upload"
   }
   
5. External application uses mutation_endpoint only
   
6. After first successful mutation:
   - Token deleted from database
   - If requested again without fresh request-mutation call: 401 Unauthorized
   - Prevents token reuse across requests
   - Automatic token rotation on each mutation

Database Logging:
- Every mutation token request logged to audit.sqlite
- Include: app_name, app_id, requested_action, ip_address, timestamp
- Token generation attempt logged before consumption
```

---

## IP WHITELIST & APPROVAL WORKFLOW

**Workflow:**

```
First Connection from External Application:
1. Application attempts to access /wp-json/onboard-plugin/v1/request-mutation
2. System checks IP against whitelist
3. IP not in whitelist:
   - Log attempt to audit.sqlite (pending_approval status)
   - Generate unique approval_code (32 bytes)
   - Send email to ADMIN_EMAIL_ADDRESS (from settings)
   - Return 403 Forbidden with message: "IP pending admin approval"
   - Include approval_code in response for reference

Email Template:
Subject: "New IP Access Request - Plugins Onboard"
Body:
  Application: {app_name}
  IP Address: {ip_address}
  Time: {timestamp}
  Action Requested: {action}
  
  Approve this IP: /wp-admin/admin.php?page=plugins-onboard-settings&approve_ip={approval_code}
  
  If this wasn't you, ignore this email.

Admin Action in WordPress Panel:
1. Admin navigates to: Plugins Onboard > Settings > IP Requests
2. Reviews pending IP requests (app_name, ip_address, timestamp)
3. Clicks "Approve" button
4. System:
   - Adds IP to whitelist in SQLite
   - Marks approval in audit.sqlite
   - Sends notification email to application contact (optional)
   - Application can now use API from that IP

Subsequent Requests from Same IP:
- System validates IP immediately
- Mutations proceed without delay
- All actions logged to audit.sqlite
```

---

## BACKUP & RESTORE SYSTEM

**Directory Structure:**

```
/wp-content/
├─ plugins-onboard-snapshots/         (main backup folder)
│  ├─ {plugin_slug}/
│  │  ├─ v{version}/
│  │  │  ├─ 20240115/
│  │  │  │  └─ {plugin_slug}-v{version}-20240115.zip
│  │  │  └─ 20240120/
│  │  │     └─ {plugin_slug}-v{version}-20240120.zip
│  │  ├─ v{version}/
│  │  │  └─ 20240125/
│  │  │     └─ {plugin_slug}-v{version}-20240125.zip
│  │  └─ latest-snapshot.txt  ← tracks most recent version
│
├─ plugins-onboard/
│  ├─ data/
│  │  ├─ plugin-manager.sqlite   ← API tokens, settings, app registrations
│  │  └─ audit.sqlite            ← all mutations, approvals, logs
│  └─ [plugin files]

Key Features:
- Sortable timestamps: YYYYMMDD (machine readable)
- Separate folders per version for easy management
- latest-snapshot.txt file tracks current backup version
- Automatic cleanup based on snapshot retention settings
- Pre-mutation automatic snapshot creation
- `plugins-onboard-snapshots` names and all the folder names should come from settings.

```

**Backup Metadata Logging:**

All backup operations logged to audit.sqlite with:
- plugin_slug, version, backup_date
- trigger_action (pre_update, manual, pre_delete, pre_enable, pre_disable)
- file_path, file_size, checksum (SHA256)
- which_app_triggered (app_name, app_id)
- ip_address of requestor
- success/failure status

**Automatic Backup Triggers:**

```
Trigger Events:
- BEFORE plugin upload/replacement (pre_update)
- BEFORE plugin deletion (pre_delete)
- BEFORE plugin enable (pre_enable)
- BEFORE plugin disable (pre_disable)
- MANUAL backup request via API

Backup Strategy:
- Each action creates automatic snapshot before mutation
- Snapshot stored with timestamp: YYYYMMDD
- Version number tracked from current plugin
- File named: {plugin_slug}-v{version}-{YYYYMMDD}.zip

Auto-cleanup (configurable via SNAPSHOT_RETENTION_COUNT constant):
- Keep latest N versions per plugin (default 5, configurable)
- Older snapshots auto-deleted after expiration
- Deletion logged to audit.sqlite
- Cleanup runs on daily schedule
```

---

## REST API ENDPOINTS

**Base: /wp-json/onboard-plugin/v1/**

```
AUTHENTICATION ENDPOINTS:

GET    /auth/request
       - Initiates OAuth flow
       - Returns: authorization_url, state
       - No auth required (public)

GET    /auth/callback?code={code}&state={state}
       - Exchanges authorization code for tokens
       - Returns: access_token, refresh_token, expires_in
       - No auth required (public)

MUTATION REQUEST ENDPOINTS:

GET    /request-mutation?action={action}
       - Auth: Bearer {access_token}
       - IP must be whitelisted (auto-approval workflow if new)
       - Returns: mutation_token, mutation_endpoint, expires_in
       - Validates token hasn't expired
       - Logs request to audit.sqlite

POST   /refresh-token
       - Auth: Bearer {refresh_token}
       - Returns: new access_token, new refresh_token
       - Old tokens invalidated

PLUGIN MANAGEMENT ENDPOINTS:

POST   /mutations/{mutation_token}/plugins/upload
       - Multipart form-data: file (ZIP), auto_backup (bool)
       - Validates ZIP file (MIME, magic bytes, integrity)
       - Creates automatic snapshot of current plugin version
       - Extracts, validates, enables plugin
       - Logs all actions to audit.sqlite
       - Returns: success status, plugin_data, backup_location

POST   /mutations/{mutation_token}/plugins/{slug}/enable
POST   /mutations/{mutation_token}/plugins/{slug}/disable
POST   /mutations/{mutation_token}/plugins/{slug}/delete
       - All trigger automatic backup before action
       - Validates plugin exists and is manageable
       - Logs action with app_name, app_id, ip_address to audit.sqlite
       - Returns: success status, backup_path, plugin_status

GET    /mutations/{mutation_token}/plugins/list
       - Returns all plugins with status:
         * is_active (boolean)
         * has_snapshot (boolean)
         * snapshot_count (number of versions)
         * latest_snapshot_date
         * version
         * description

BACKUP & RESTORE ENDPOINTS:

GET    /plugins/{slug}/backups
       - Auth: Bearer {access_token}
       - Returns list of all backups for plugin:
         * version, backup_date, file_size, checksum
         * trigger_action, backup_type
       
POST   /mutations/{mutation_token}/plugins/{slug}/restore
       - Body: version={version}, backup_date={YYYYMMDD}
       - Creates backup of current plugin before restore
       - Restores to specific version/date
       - Logs restore action to audit.sqlite
       - Returns: success status, restored_location

GET    /plugins/backups/download-all
       - Auth: Bearer {access_token}
       - Downloads ZIP containing:
         * All plugin snapshots
         * Metadata about which plugins are active/inactive
         * Created in WordPress temp directory
       - Returns ZIP file for download
       - Logs export to audit.sqlite

GET    /plugins/backups/download-active
       - Auth: Bearer {access_token}
       - Downloads ZIP of currently active plugins only
       - Includes list of active plugin slugs
       - Created in WordPress temp directory

GET    /plugins/backups/download-snapshots
       - Auth: Bearer {access_token}
       - Downloads ZIP of all plugin snapshots
       - Maintains folder structure: {plugin_slug}/v{version}/{date}
       - Created in WordPress temp directory

DEBUGGING ENDPOINTS:

POST   /mutations/{mutation_token}/debug/enable
POST   /mutations/{mutation_token}/debug/disable
       - Toggles WP_DEBUG constant
       - Logs action with app_name, ip_address to audit.sqlite
       - Returns: debug_status, log_file_location

POST   /mutations/{mutation_token}/maintenance/enable
POST   /mutations/{mutation_token}/maintenance/disable
       - Toggles WordPress maintenance mode
       - Creates .maintenance file in wp-content
       - Logs action to audit.sqlite
       - Returns: maintenance_status

AUDIT & LOGS ENDPOINTS:

GET    /audit-logs?limit=50&offset=0
       - Auth: Bearer {access_token}
       - Returns paginated mutation log
       - Fields: action, plugin_slug, app_name, app_id, ip_address, 
                 status, timestamp, error_message

POST   /audit-logs/clear
       - Auth: Bearer {access_token}
       - Requires: current_user_can('manage_plugins')
       - Clears all audit logs from audit.sqlite
       - Logs the clearing action before deletion
       - Returns: success status, records_deleted

DATABASE MANAGEMENT ENDPOINTS:

GET    /database/info
       - Auth: Bearer {access_token}
       - Returns database stats:
         * plugin-manager.sqlite size (bytes)
         * audit.sqlite size (bytes)
         * last_backup_date, last_mutation_date

GET    /database/download
       - Auth: Bearer {access_token}
       - Downloads ZIP containing:
         * plugin-manager.sqlite
         * audit.sqlite
         * metadata.json (size, version, timestamp)
       - Created in WordPress temp directory
       - Logs export to audit.sqlite

POST   /database/upload
       - Auth: Bearer {access_token}
       - Multipart: file (ZIP containing SQLite files)
       - Validates ZIP structure and SQLite integrity
       - Creates backup of existing databases
       - Restores uploaded databases
       - Logs restore action to audit.sqlite
       - Returns: success status, restoration_details

GET    /temp-info
       - Auth: Bearer {access_token}
       - Returns temp directory stats:
         * temp_directory_path
         * size_of_onboard_temp_files (bytes)
         * file_list with timestamps
```

---

## WORDPRESS ADMIN PANEL MENU STRUCTURE

**Main Menu: Plugins Onboard**

```
Plugins Onboard
├─ Dashboard
│  ├─ Overview: Active plugins, snapshots, last mutations
│  ├─ Recent Actions: Last 10 mutations (option changable from settings) with app_name, ip, timestamp
│  └─ System Status: Debug mode, maintenance mode, temp size
│
├─ Plugins
│  ├─ Tab 1: All Installed Plugins
│  │  └─ Table: name, status (active/inactive), snapshot_count, 
│  │     latest_snapshot_date, actions (backup, restore, enable, disable, delete)
│  │
│  └─ Tab 2: Uploaded by Tool
│     └─ Table: name, upload_date, uploaded_by_app, current_version,
│        actions (restore to previous version, delete)
│
├─ Backups & Restore
│  ├─ Plugin Snapshots: List all snapshots by plugin_slug
│  │  └─ For each snapshot: version, date, size, restore button
│  │
│  └─ Download Backups
│     ├─ Button: Download All Plugins (ZIP)
│     ├─ Button: Download Active Plugins (ZIP)
│     ├─ Button: Download All Snapshots (ZIP)
│     └─ Display: Temp directory size, download history
│
├─ Database
│  ├─ Database Info:
│  │  ├─ plugin-manager.sqlite: size, last_modified
│  │  ├─ audit.sqlite: size, last_modified
│  │  └─ Total database size
│  │
│  ├─ Database Download:
│  │  └─ Button: Download All Databases (ZIP)
│  │     - Includes metadata file with timestamps
│  │
│  ├─ Database Restore:
│  │  └─ Upload form: select ZIP file, restore
│  │     - Validates structure before restore
│  │     - Creates backup of existing databases
│  │     - Logs restore action
│  │
│  └─ Temp Files Management:
│     ├─ Display: Temp directory path, current size
│     ├─ List: All onboard-plugin temp files with dates
│     └─ Button: Clear Old Temp Files (older than 2 days)
│
├─ Settings
│  ├─ General Settings:
│  │  ├─ Admin Email Address: text input (for IP approval notifications)
│  │  ├─ Enable Auto Backup: checkbox (default: enabled)
│  │
│  ├─ Backup Settings:
│  │  ├─ Snapshot Retention Count: select (5, 10, 15, 20) default 5
│  │  ├─ Auto Cleanup Enabled: checkbox
│  │  └─ Cleanup Interval: select (daily, weekly)
│  │
│  ├─ API & Token Settings:
│  │  ├─ Access Token TTL: input (seconds, default CONST_ACCESS_TOKEN_TTL)
│  │  ├─ Refresh Token TTL: input (seconds, default CONST_REFRESH_TOKEN_TTL)
│  │  ├─ Mutation Token TTL: input (seconds, default CONST_MUTATION_TOKEN_TTL)
│  │  └─ Rate Limit Settings:
│  │     - Auth requests per hour: input
│  │     - Mutation requests per hour: input
│  │
│  ├─ Security Settings:
│  │  ├─ Require HTTPS: toggle
│  │  ├─ Enable IP Whitelist: toggle
│  │  └─ Auto-approve IPs: checkbox (if disabled, requires manual approval)
│  │
│  ├─ Debug & Maintenance:
│  │  ├─ Current Debug Mode: status (enabled/disabled)
│  │  ├─ Current Maintenance Mode: status (enabled/disabled)
│  │  └─ Toggle buttons for quick enable/disable
│  │
│  └─ Save Settings button
│
├─ Applications
│  ├─ Registered Applications:
│  │  ├─ Table: app_name, client_id, created_date, last_used, 
│  │     whitelisted_ips, status (active/inactive)
│  │  ├─ Actions: edit, deactivate, view_secret, delete
│  │
│  ├─ IP Whitelist Requests (Pending):
│  │  ├─ Table: app_name, requested_ip, timestamp, approval_code
│  │  ├─ Actions: approve, reject, view_logs
│  │  └─ Batch actions: approve all, reject all
│  │
│  └─ Create New Application:
│     ├─ Form: app_name, description, redirect_uri
│     ├─ Auto-generates: client_id, client_secret (shown once)
│     └─ Initial IP whitelist from current request
│
├─ Audit Logs
│  ├─ Log Viewer:
│  │  ├─ Table (paginated): 
│  │     - Timestamp, app_name, app_id, action, plugin_slug,
│  │       ip_address, status (success/failed), details
│  │  ├─ Filters: by app_name, by action, by status, by date_range
│  │  ├─ Search: search by plugin_slug, ip_address, app_name
│  │
│  └─ Clear Logs:
│     ├─ Warning message: "This cannot be undone"
│     └─ Button: Clear All Audit Logs (requires confirmation)
│        - Logs the clearing action before deletion
│
└─ Help & Documentation
   ├─ API Documentation: link to /docs/API.md
   ├─ Security Guide: link to /docs/SECURITY.md
   ├─ Setup Guide: link to /docs/SETUP.md
   └─ FAQ
```

---

## UPLOAD & DOWNLOAD FUNCTIONALITY

**Multipart Upload:**

```
POST /wp-json/onboard-plugin/v1/mutations/{mutation_token}/plugins/upload

Form Data:
- file: ZIP file (application/zip or application/x-zip-compressed)
- auto_backup: boolean (true = create snapshot before upload)
- plugin_name: string (optional, for logging)
- version: string (optional, for metadata)

Validation:
1. ZIP file integrity check (magic bytes: PK\x03\x04)
2. ZIP structure validation (must contain plugin folder)
3. Scan for malicious patterns in PHP files
4. Verify plugin has valid plugin header (Plugin Name, etc)
5. File size check against server limits

Processing:
1. Create snapshot of current plugin version (if exists)
2. Extract ZIP to temporary location
3. Validate extracted files
4. Move to wp-content/plugins/
5. Activate plugin
6. Log to audit.sqlite: app_name, app_id, ip_address, file_size
7. Return: success status, plugin_data, backup_location
```

**Temp Directory Management:**

```
Location: wp-content/temp/plugins-onboard/
  or: wp-content/cache/plugins-onboard/

Files created:
- Downloaded ZIP files (all-plugins-{timestamp}.zip)
- Database exports (databases-{timestamp}.zip)
- Snapshot collections (snapshots-{timestamp}.zip)

Auto-cleanup:
- Files older than 2 days automatically deleted
- Cleanup job runs daily via WordPress cron
- Can be manually triggered from Database menu
- Logs cleanup actions to audit.sqlite

Tracking:
- Database stores temp directory stats
- API endpoint /temp-info returns current usage
- Admin panel shows temp directory size
- Warning if temp exceeds 500MB
```

**Download as ZIP:**

```
All downloads follow same pattern:

1. Query relevant files from filesystem
2. Create ZIP in WordPress temp directory
3. Add metadata.json:
   {
     "export_date": "2024-01-15T10:30:00Z",
     "export_type": "all_plugins|active_plugins|snapshots|databases",
     "wordpress_version": "6.4.1",
     "onboard_version": "1.0.0",
     "file_count": 45,
     "total_size": 5242880,
     "plugin_status": {
       "plugin_slug": {
         "is_active": true,
         "has_snapshot": true,
         "snapshot_count": 3
       }
     }
   }
4. Compress and serve as download
5. Log export to audit.sqlite
6. Set expiration for cleanup
```

---

## CONSTANTS FILE

**File: /plugins/plugins-onboard/includes/constants.php**

```
<?php
/**
 * Plugins Onboard - Constants
 * All configurable values as constants
 */

// all these settings should be coming from .sqlite db if not present then seed the default values first
// API Configuration
define('ONBOARD_API_NAMESPACE', 'onboard-plugin');
define('ONBOARD_API_VERSION', 'v1');

// Token TTL (Time To Live) in seconds
define('ONBOARD_ACCESS_TOKEN_TTL', 3600); // 1 hour
define('ONBOARD_REFRESH_TOKEN_TTL', 2592000); // 30 days
define('ONBOARD_MUTATION_TOKEN_TTL', 1200); // 20 minutes

// Rate Limiting
define('ONBOARD_RATE_LIMIT_AUTH_REQUESTS', 5); // per hour
define('ONBOARD_RATE_LIMIT_MUTATION_REQUESTS', 10); // per hour
define('ONBOARD_RATE_LIMIT_MUTATIONS', 20); // per hour

// Backup & Snapshot Configuration
define('ONBOARD_SNAPSHOT_RETENTION_COUNT', 5); // max versions to keep
define('ONBOARD_SNAPSHOT_CLEANUP_INTERVAL', 'daily'); // daily or weekly
define('ONBOARD_SNAPSHOT_BASE_PATH', WP_CONTENT_DIR . '/plugins-onboard-snapshots/');
define('ONBOARD_AUTO_BACKUP_ENABLED', true);

// Database Configuration
define('ONBOARD_DATA_PATH', WP_PLUGIN_DIR . '/plugins-onboard/data/');
define('ONBOARD_PLUGIN_MANAGER_DB', ONBOARD_DATA_PATH . 'plugin-manager.sqlite');
define('ONBOARD_AUDIT_DB', ONBOARD_DATA_PATH . 'audit.sqlite');

// Temp Directory Configuration
define('ONBOARD_TEMP_PATH', WP_CONTENT_DIR . '/temp/plugins-onboard/');
define('ONBOARD_TEMP_CLEANUP_DAYS', 2); // delete files older than 2 days
define('ONBOARD_TEMP_SIZE_WARNING', 536870912); // 500MB warning threshold
define('ONBOARD_MAX_UPLOAD_SIZE', 104857600); // 100MB max upload

// Security Configuration
define('ONBOARD_REQUIRE_HTTPS', true);
define('ONBOARD_IP_WHITELIST_ENABLED', true);
define('ONBOARD_IP_AUTO_APPROVE', false); // requires admin approval for new IPs
define('ONBOARD_TOKEN_ENCRYPTION', true);

// File Validation
define('ONBOARD_ALLOWED_ZIP_MIMETYPES', array('application/zip', 'application/x-zip-compressed'));
define('ONBOARD_ZIP_MAGIC_BYTES', 'PK\x03\x04');

// Logging
define('ONBOARD_LOG_LEVEL', 'info'); // debug, info, warning, error, should be coming from sqlite db if no then use info
define('ONBOARD_AUDIT_LOG_RETENTION_DAYS', 365); // keep audit logs for 1 year

// Email Configuration (for IP approval notifications)
define('ONBOARD_ADMIN_EMAIL_SETTING_KEY', 'onboard_admin_email');

// Feature Flags
define('ONBOARD_ENABLE_OAUTH', true);
define('ONBOARD_ENABLE_API', true);
define('ONBOARD_ENABLE_BACKUP', true);
define('ONBOARD_ENABLE_AUDIT_LOGS', true);

// Backup Triggers
define('ONBOARD_BACKUP_TRIGGER_UPLOAD', true);
define('ONBOARD_BACKUP_TRIGGER_ENABLE', true);
define('ONBOARD_BACKUP_TRIGGER_DISABLE', true);
define('ONBOARD_BACKUP_TRIGGER_DELETE', true);
```

---

## AUDIT LOGGING

**Every Mutation Action Logged:**

Each action logged to audit.sqlite with:
- `timestamp`: ISO 8601 format
- `action`: enable_plugin, disable_plugin, delete_plugin, upload_plugin, restore_plugin, etc.
- `plugin_slug`: which plugin affected
- `app_name`: name of external application
- `app_id`: unique identifier for application
- `ip_address`: IP address of requester
- `mutation_token`: which token was used
- `status`: success, failed, pending_approval
- `details`: JSON with additional context (file size, version, etc.)
- `error_message`: if failed, what went wrong

**IP Approval Actions Logged:**
- ip_approval_requested: new IP detected
- ip_approval_granted: admin approved IP
- ip_approval_rejected: admin rejected IP

**Admin Panel Actions Logged:**
- settings_changed: when admin modifies settings
- audit_logs_cleared: when admin clears logs
- database_exported: when admin downloads databases
- database_imported: when admin uploads databases

**Application Management Logged:**
- app_registered: new application registered
- app_deleted: application removed
- app_token_created: new token generated
- app_token_revoked: token invalidated

---

## EMAIL NOTIFICATIONS

**IP Approval Request Email:**

```
To: ADMIN_EMAIL_ADDRESS (from settings)
Subject: New IP Access Request - Plugins Onboard

Body:
A new IP address is requesting access to your Plugins Onboard API.

Application: {app_name}
IP Address: {ip_address}
Requested Action: {action}
Time: {timestamp} (server timezone)

--- APPROVE THIS REQUEST ---
Click here to approve: {approval_link_with_code}

Or copy this link into your browser:
{wp-admin-url}/admin.php?page=plugins-onboard&action=approve_ip&code={approval_code}

--- SECURITY NOTE ---
If you don't recognize this application or IP, do not approve.
Only approve requests you initiated or expect.

---

For security, this email contains no sensitive data.
The approval code is single-use and expires after 24 hours.
```

---

## IMPLEMENTATION PHASES

```
Phase 1: Core Infrastructure (Weeks 1-2)
├─ OAuth 2.0 authentication setup
├─ SQLite database creation & encryption
├─ Access token generation & validation
├─ Mutation token system
├─ Rate limiting framework
└─ Constants file & configuration

Phase 2: Plugin Management API (Weeks 3-4)
├─ Enable/disable/delete endpoints
├─ Backup system (pre-action snapshots)
├─ Restore functionality
├─ File validation & security checks
├─ Upload with multipart handling
└─ Audit logging for all mutations

Phase 3: Advanced Features (Weeks 5-6)
├─ IP whitelist & approval workflow
├─ Email notifications for approvals
├─ Debug mode toggle via API
├─ Maintenance mode toggle via API
├─ Database export/import endpoints
├─ Temp directory management
└─ Download backup collections

Phase 4: WordPress Admin Panel (Weeks 7-8)
├─ Settings page (all constants configurable)
├─ Application management interface
├─ IP whitelist approval dashboard
├─ Plugins view with snapshots
├─ Backup & restore interface
├─ Database management section
├─ Audit logs viewer with filters
└─ Dashboard overview

Phase 5: Security & Testing (Weeks 9-10)
├─ Penetration testing
├─ Rate limiting validation
├─ IP whitelist enforcement
├─ Token encryption verification
├─ Backup integrity checks
├─ Documentation generation
└─ Production hardening
```

---

## DOCUMENTATION FILES

**Generate these documentation files:**

- **API.md**: Complete REST API reference with examples, OAuth flow, error codes
- **SECURITY.md**: Security best practices, threat model, encryption details, compliance
- **SETUP.md**: Installation, configuration, initial setup, troubleshooting
- **FAQ.md**: Common questions about usage, backups, restoration, troubleshooting
