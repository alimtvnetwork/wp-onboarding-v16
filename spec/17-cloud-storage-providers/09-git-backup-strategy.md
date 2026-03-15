# Cloud Storage Providers — Git Backup Strategy

**Spec**: `spec/17-cloud-storage-providers/09-git-backup-strategy.md`
**Depends On**: `01-overview.md`, `05-github-implementation.md`, `06-gitlab-implementation.md`
**Version**: 2.15.0+

### Design Decisions (Confirmed 2026-03-15)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Incremental detection** | Timestamp-based (`post_modified_gmt`) | Fastest approach; avoids full table scans. Only exports rows where `post_modified_gmt > lastBackupTimestamp`. Works natively for `wp_posts`, `wp_postmeta` (via post join), `wp_options` (compare serialized values). Tables without timestamps are included in full backups only. |
| **Cron reliability** | WP-Cron default + real cron documentation | WP-Cron requires zero server config. For low-traffic sites, document a `*/15 * * * * curl` system cron as recommended best practice. Plugin does NOT disable `DISABLE_WP_CRON` — that's the user's choice. |
| **Restore method** | Git-first (shallow clone), API fallback | `git clone --depth 1 --single-branch` is the fastest way to fetch a single branch. Falls back to GitHub Contents API / GitLab Files API when `exec('git')` is unavailable (shared hosting without shell access). Fallback has a 100 MB limit on GitHub. |
| **Branch cleanup** | Auto-delete with full backup rotation | When a full backup is rotated out (exceeds retention count), the system automatically deletes the associated `incremental/{YYYY-Www}` branch via `DELETE /git/refs/heads/...`. This prevents branch clutter. Orphaned branches are never left behind. |

---

## Purpose

Define a comprehensive Git-based backup strategy for GitHub and GitLab providers that supports:

1. **Repo selection** — create a new repository OR select an existing one (with branch selection)
2. **Full (standalone) backups** — complete site snapshot ZIPs on the `main` branch
3. **Incremental backups** — delta-only ZIPs on dated branches, rebased on the latest full backup
4. **Automated scheduling** — weekly full + daily incremental (configurable)
5. **Git clone restore** — pull backups via `git clone` into a temp folder, extract and restore
6. **Backup history visualization** — timeline/tree view of full + incremental backup history

---

## 1. Repository Selection

### 1.1 Two Modes

When adding or editing a GitHub/GitLab cloud storage account, the user chooses one of:

| Mode | Description |
|------|-------------|
| **Create New Repository** | Plugin creates a private repo with `auto_init: true`. User provides a repo name (default: `wp-backups`). |
| **Select Existing Repository** | Plugin lists the user's accessible repos (paginated). User picks one and optionally selects a branch. |

### 1.2 API: List Repositories

**GitHub**:
```
GET /user/repos?per_page=100&sort=updated&affiliation=owner,collaborator,organization_member
```

For organizations:
```
GET /orgs/{org}/repos?per_page=100&sort=updated
```

**GitLab**:
```
GET /projects?membership=true&per_page=100&order_by=last_activity_at
```

### 1.3 API: List Branches

**GitHub**:
```
GET /repos/{owner}/{repo}/branches?per_page=100
```

**GitLab**:
```
GET /projects/{id}/repository/branches?per_page=100
```

### 1.4 Database Changes

Add columns to `CloudStorageAccounts` (Migration v19):

```sql
ALTER TABLE CloudStorageAccounts ADD COLUMN RepoSelectionMode TEXT NOT NULL DEFAULT 'create';
-- Values: 'create' | 'existing'

ALTER TABLE CloudStorageAccounts ADD COLUMN DefaultBranch TEXT NOT NULL DEFAULT 'main';
-- The branch used for full (standalone) backups
```

### 1.5 REST Endpoints (New)

```
GET  /cloud-storage/repos?account_id={id}
     → { Success: true, Repositories: [{ Name, FullName, IsPrivate, DefaultBranch, UpdatedAt }] }

GET  /cloud-storage/branches?account_id={id}&repo={owner/repo}
     → { Success: true, Branches: [{ Name, IsDefault, LastCommitSha, LastCommitDate }] }
```

### 1.6 React UI: Repository Selector

In `CloudStorageAccountDialog`, replace the single `RepoName` text field with:

```
┌──────────────────────────────────────────┐
│ Repository Mode                          │
│ ○ Create new repository                  │
│   └ Name: [wp-backups_______________]    │
│ ○ Select existing repository             │
│   └ [▼ Search repositories...        ]   │
│     └ Branch: [▼ main               ]    │
└──────────────────────────────────────────┘
```

---

## 2. Backup Types

### 2.1 Full (Standalone) Backup

- **What**: Complete site snapshot — all database tables exported + wp-content files
- **Where**: Committed to the **default branch** (typically `main`)
- **File path**: `backups/{prefix}-full-{YYYY-MM-DD-HHmmss}.zip`
- **Commit message**: `Full backup: {prefix}-full-{YYYY-MM-DD-HHmmss}.zip`
- **Tag** (optional): `v{YYYY.WW}` (year.week-number) for easy reference

### 2.2 Incremental Backup

- **What**: Only changed database rows (since last full or incremental) + modified files
- **Where**: Committed to a **dated branch** named `incremental/{YYYY-Www}` (ISO week)
  - Example: `incremental/2026-W11` for the week of March 9–15, 2026
- **File path**: `backups/{prefix}-incr-{YYYY-MM-DD-HHmmss}.zip`
- **Commit message**: `Incremental backup: {prefix}-incr-{YYYY-MM-DD-HHmmss}.zip (base: {full-backup-name})`
- **Branch creation**: Branch is created from the latest full backup commit on `main`

### 2.3 Branch Structure Visualization

```
main ─────●──────────────────────●──────────────────●────
          │ Full backup (Week 10) │ Full backup (W11) │ Full (W12)
          │                       │                   │
          ├─ incremental/2026-W10 ├─ incremental/W11  ├─ incremental/W12
          │  ●─●─●─●─●─●         │  ●─●─●─●─●─●     │  ●─●─...
          │  Mon-Sat incremental  │  Mon-Sat          │
```

### 2.4 Incremental Detection

The plugin uses **timestamp-based detection** to identify changed data since the last backup.

#### Detection Strategy

1. **`wp_posts` / `wp_postmeta`**: Query `WHERE post_modified_gmt > '{lastBackupTimestamp}'`
2. **`wp_options`**: Compare `option_value` checksums against a stored manifest from the last backup
3. **`wp_comments` / `wp_commentmeta`**: Query `WHERE comment_date_gmt > '{lastBackupTimestamp}'`
4. **`wp_terms` / `wp_term_taxonomy`**: Always included (small tables, no reliable timestamp)
5. **Custom tables without timestamps**: Excluded from incrementals — only captured in full backups
6. **`wp-content/uploads/`**: Compare `filemtime()` against last backup timestamp

#### Manifest (included in incremental ZIP)

```json
{
  "baseFullBackup": "wp-backup-full-2026-03-09-000000.zip",
  "baseCommitSha": "abc123...",
  "lastBackupTimestamp": "2026-03-09T02:00:00Z",
  "detectionMethod": "timestamp",
  "tablesChanged": ["wp_posts", "wp_postmeta", "wp_options"],
  "filesChanged": ["wp-content/uploads/2026/03/new-image.jpg"],
  "totalRowsChanged": 42,
  "snapshotId": "snap-20260310-120000",
  "createdAt": "2026-03-10T12:00:00Z"
}
```

This manifest is included inside the incremental ZIP alongside the delta data.

#### Limitations

- Tables without `modified_date` or `created_at` columns are only captured in full backups
- If the WordPress database clock drifts, rows may be missed — full weekly backups serve as the safety net

### 2.5 Database Changes

Add to `CloudStorageSettings` (Migration v19):

```sql
ALTER TABLE CloudStorageSettings ADD COLUMN BackupType TEXT NOT NULL DEFAULT 'full';
-- Values: 'full_only' | 'full_and_incremental'

ALTER TABLE CloudStorageSettings ADD COLUMN FullBackupSchedule TEXT NOT NULL DEFAULT 'weekly';
-- Values: 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'manual'

ALTER TABLE CloudStorageSettings ADD COLUMN IncrementalBackupSchedule TEXT NOT NULL DEFAULT 'daily';
-- Values: 'hourly' | 'daily' | 'manual'

ALTER TABLE CloudStorageSettings ADD COLUMN FullBackupDayOfWeek INTEGER NOT NULL DEFAULT 0;
-- 0 = Sunday, 6 = Saturday

ALTER TABLE CloudStorageSettings ADD COLUMN FullBackupTimeUtc TEXT NOT NULL DEFAULT '02:00';
-- HH:MM in UTC

ALTER TABLE CloudStorageSettings ADD COLUMN IncrementalBackupTimeUtc TEXT NOT NULL DEFAULT '02:00';
-- HH:MM in UTC
```

New table for tracking backup history (Migration v20):

```sql
CREATE TABLE IF NOT EXISTS CloudStorageBackupHistory (
    Id              INTEGER PRIMARY KEY AUTOINCREMENT,
    AccountId       INTEGER NOT NULL,
    BackupType      TEXT    NOT NULL,          -- 'full' | 'incremental'
    FileName        TEXT    NOT NULL,
    RemotePath      TEXT    NOT NULL,
    RemoteUrl       TEXT    DEFAULT '',
    CommitSha       TEXT    DEFAULT '',
    BranchName      TEXT    NOT NULL DEFAULT 'main',
    BaseFullBackupId INTEGER DEFAULT NULL,     -- FK to self (for incremental → full link)
    FileSizeBytes   INTEGER NOT NULL DEFAULT 0,
    TablesChanged   TEXT    DEFAULT '',        -- JSON array of table names
    RowsChanged     INTEGER NOT NULL DEFAULT 0,
    Duration        REAL    NOT NULL DEFAULT 0,
    Status          TEXT    NOT NULL DEFAULT 'pending',  -- 'pending' | 'uploading' | 'success' | 'failed'
    ErrorMessage    TEXT    DEFAULT '',
    CreatedAt       TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (AccountId) REFERENCES CloudStorageAccounts(Id) ON DELETE CASCADE,
    FOREIGN KEY (BaseFullBackupId) REFERENCES CloudStorageBackupHistory(Id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_csbh_account  ON CloudStorageBackupHistory(AccountId);
CREATE INDEX IF NOT EXISTS idx_csbh_type     ON CloudStorageBackupHistory(BackupType);
CREATE INDEX IF NOT EXISTS idx_csbh_branch   ON CloudStorageBackupHistory(BranchName);
CREATE INDEX IF NOT EXISTS idx_csbh_created  ON CloudStorageBackupHistory(CreatedAt);
```

### 2.6 Enums

```php
enum BackupStrategyType: string
{
    case FullOnly           = 'full_only';
    case FullAndIncremental = 'full_and_incremental';

    public function isFullOnly(): bool           { return $this === self::FullOnly; }
    public function isFullAndIncremental(): bool  { return $this === self::FullAndIncremental; }
}

enum BackupScheduleType: string
{
    case Hourly   = 'hourly';
    case Daily    = 'daily';
    case Weekly   = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly  = 'monthly';
    case Manual   = 'manual';

    public function isManual(): bool  { return $this === self::Manual; }
    public function isAutomatic(): bool { return !$this->isManual(); }
}

enum CloudStorageBackupType: string
{
    case Full        = 'full';
    case Incremental = 'incremental';

    public function isFull(): bool        { return $this === self::Full; }
    public function isIncremental(): bool  { return $this === self::Incremental; }
}

enum CloudStorageBackupStatusType: string
{
    case Pending   = 'pending';
    case Uploading = 'uploading';
    case Success   = 'success';
    case Failed    = 'failed';

    public function isTerminal(): bool { return $this === self::Success || $this === self::Failed; }
}
```

---

## 3. Automated Scheduling

### 3.1 WordPress Cron (Default)

Register two WP-Cron events. WP-Cron fires on page visits, which is acceptable for most sites.

```php
// In Plugin activation hook
if (!wp_next_scheduled('riseup_cloud_full_backup')) {
    wp_schedule_event(
        $this->calculateNextFullBackupTimestamp(),
        'weekly',
        'riseup_cloud_full_backup',
    );
}

if (!wp_next_scheduled('riseup_cloud_incremental_backup')) {
    wp_schedule_event(
        $this->calculateNextIncrementalTimestamp(),
        'daily',
        'riseup_cloud_incremental_backup',
    );
}
```

### 3.2 Real System Cron (Recommended for Reliability)

For low-traffic sites where WP-Cron may miss schedules, document this setup:

```bash
# Add to crontab (crontab -e) or cPanel Cron Jobs:
*/15 * * * * curl -sf https://your-site.com/wp-cron.php >/dev/null 2>&1
```

**Important**: Do NOT set `DISABLE_WP_CRON` in the plugin — that's the user's choice. The plugin should work with both WP-Cron and real cron.

### 3.3 Cron Handlers

```php
add_action('riseup_cloud_full_backup', array($this, 'handleScheduledFullBackup'));
add_action('riseup_cloud_incremental_backup', array($this, 'handleScheduledIncrementalBackup'));
```

### 3.3 Full Backup Handler

```
1. Create full site snapshot ZIP (existing backup system)
2. For each enabled cloud storage account:
   a. Checkout / ensure default branch (main)
   b. Commit the full backup ZIP
   c. Tag with release number: v{YYYY.WW}
   d. Apply rotation (delete oldest full backups beyond retention)
3. Record in CloudStorageBackupHistory
4. Log to Transactions
```

### 3.4 Incremental Backup Handler

```
1. Get latest full backup reference from CloudStorageBackupHistory
2. Create incremental snapshot (delta since last full/incremental)
3. For each enabled cloud storage account:
   a. Ensure incremental branch exists (create from full backup commit if not)
   b. Commit the incremental ZIP to the branch
   c. Apply rotation on the branch (delete oldest incremental beyond retention)
4. Record in CloudStorageBackupHistory with BaseFullBackupId
5. Log to Transactions
```

### 3.5 Branch Management (Git Operations)

**Create branch from commit**:

GitHub:
```
POST /repos/{owner}/{repo}/git/refs
Body: { "ref": "refs/heads/incremental/2026-W11", "sha": "{fullBackupCommitSha}" }
```

GitLab:
```
POST /projects/{id}/repository/branches
Body: { "branch": "incremental/2026-W11", "ref": "{fullBackupCommitSha}" }
```

**Check if branch exists**:

GitHub:
```
GET /repos/{owner}/{repo}/branches/incremental/2026-W11
```
200 = exists, 404 = create it.

---

## 4. Git Clone Restore

### 4.1 Restore Flow (Git-First Strategy)

The restore always attempts `git clone` first. If the `git` binary is unavailable (shared hosting), it falls back to the provider's file download API.
1. User selects a backup from the history (full or incremental)
2. System creates a temp directory: /tmp/riseup-restore-{uuid}/
3. System performs a sparse/shallow git clone of the specific branch:
   git clone --depth 1 --branch {branchName} --single-branch {repoUrl} /tmp/riseup-restore-{uuid}/
4. Extract the target ZIP from the clone
5. Restore using existing backup restore system
6. Clean up temp directory
```

### 4.2 PHP Implementation

```php
public function handleCloudStorageRestore(WP_REST_Request $request): WP_REST_Response
{
    $backupId = (int) $request->get_param('BackupId');
    $backup   = $this->getBackupHistoryById($backupId);

    $isMissing = ($backup === null);

    if ($isMissing) {
        return $this->errorResponse('Backup not found', HttpStatusType::NotFound);
    }

    $account  = $this->getCloudStorageAccountById($backup['AccountId']);
    $tempDir  = sys_get_temp_dir() . '/riseup-restore-' . wp_generate_uuid4();

    try {
        // Clone the specific branch (shallow)
        $this->gitCloneShallow($account, $backup['BranchName'], $tempDir);

        // Locate the backup ZIP
        $zipPath = $tempDir . '/' . $backup['RemotePath'];
        $isZipMissing = !file_exists($zipPath);

        if ($isZipMissing) {
            throw new RuntimeException('Backup ZIP not found in cloned repository');
        }

        // For incremental restore: also need the base full backup
        $isIncremental = ($backup['BackupType'] === CloudStorageBackupType::Incremental->value);

        if ($isIncremental) {
            $fullBackup = $this->getBackupHistoryById($backup['BaseFullBackupId']);
            $fullTempDir = sys_get_temp_dir() . '/riseup-restore-full-' . wp_generate_uuid4();

            $this->gitCloneShallow($account, $fullBackup['BranchName'], $fullTempDir);

            // Restore full first, then apply incremental on top
            $this->restoreFromZip($fullTempDir . '/' . $fullBackup['RemotePath']);
            $this->restoreFromZip($zipPath, true); // true = incremental merge

            $this->cleanupTempDir($fullTempDir);
        } else {
            $this->restoreFromZip($zipPath);
        }

        $this->cleanupTempDir($tempDir);

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => true,
            ResponseKeyType::Message->value => 'Backup restored successfully',
        ), HttpStatusType::Ok->value);

    } catch (Throwable $e) {
        $this->cleanupTempDir($tempDir);
        $this->fileLogger->logException($e, 'Cloud storage restore failed');

        throw $e;
    }
}
```

### 4.3 Git Clone Helper

```php
private function gitCloneShallow(array $account, string $branch, string $destDir): void
{
    $provider = CloudStorageProviderType::from($account['Provider']);
    $token    = $this->decryptToken($account['AccessToken']);

    $repoUrl = match(true) {
        $provider->isGitHub() => sprintf(
            'https://%s@github.com/%s/%s.git',
            $token,
            $account['RepoOwner'],
            $account['RepoName'],
        ),
        $provider->isGitLab() => sprintf(
            'https://oauth2:%s@%s/%s/%s.git',
            $token,
            rtrim($account['BaseUrl'] ?: 'gitlab.com', '/'),
            $account['RepoOwner'],
            $account['RepoName'],
        ),
        default => throw new RuntimeException('Git clone not supported for ' . $provider->label()),
    };

    $command = sprintf(
        'git clone --depth 1 --branch %s --single-branch %s %s 2>&1',
        escapeshellarg($branch),
        escapeshellarg($repoUrl),
        escapeshellarg($destDir),
    );

    $output    = array();
    $exitCode  = 0;
    exec($command, $output, $exitCode);

    $isCloneFailed = ($exitCode !== 0);

    if ($isCloneFailed) {
        throw new RuntimeException(
            sprintf('Git clone failed (exit %d): %s', $exitCode, implode("\n", $output))
        );
    }
}
```

### 4.4 REST Endpoint (New)

```
POST /cloud-storage/restore
Body: { "BackupId": 42 }
→ { Success: true, Message: "Backup restored successfully" }
```

### 4.5 Fallback: Download via API (No Git Binary)

For shared hosting environments where `exec('git')` is unavailable, the restore handler
detects this and falls back to downloading the ZIP via the provider's REST API.

**Detection**:
```php
$isGitAvailable = $this->isShellCommandAvailable('git');

if ($isGitAvailable) {
    $this->gitCloneShallow($account, $branch, $tempDir);
} else {
    $this->downloadViaApi($account, $backup['RemotePath'], $branch, $tempDir);
}
```

**GitHub** (files ≤100 MB):
```
GET /repos/{owner}/{repo}/contents/{path}?ref={branch}
```
Response includes base64-encoded content. Decode and write to temp dir.

**GitHub** (files >100 MB — use Git Data API):
```
GET /repos/{owner}/{repo}/git/blobs/{sha}
```

**GitLab** (no size limit on raw endpoint):
```
GET /projects/{id}/repository/files/{urlEncodedPath}/raw?ref={branch}
```

---

## 5. Backup History Visualization

### 5.1 React Components

**`CloudStorageBackupTimeline.tsx`** — Main visualization component:

```
┌──────────────────────────────────────────────────────────────────┐
│ Backup History                                    [Refresh] [⚙] │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Week 12 (Mar 16–22)                                            │
│  ┌─ ● Full Backup — Mar 16 02:00 UTC              125 MB       │
│  │    v2026.12 · main · abc123                    [Restore ▾]   │
│  │                                                               │
│  ├── ○ Incremental — Mar 17 02:00 UTC              3.2 MB      │
│  │     incremental/2026-W12 · 5 tables · 142 rows [Restore ▾]  │
│  ├── ○ Incremental — Mar 18 02:00 UTC              1.8 MB      │
│  │     incremental/2026-W12 · 2 tables · 38 rows  [Restore ▾]  │
│  └── ○ Incremental — Mar 19 02:00 UTC (pending...)             │
│                                                                  │
│  Week 11 (Mar 9–15)                                             │
│  ┌─ ● Full Backup — Mar 9 02:00 UTC               118 MB       │
│  │    v2026.11 · main · def456                    [Restore ▾]   │
│  │                                                               │
│  ├── ○ Incremental — Mar 10 · 4.1 MB             [Restore ▾]   │
│  ├── ○ Incremental — Mar 11 · 2.9 MB             [Restore ▾]   │
│  ├── ○ Incremental — Mar 12 · 5.7 MB             [Restore ▾]   │
│  ├── ○ Incremental — Mar 13 · 1.2 MB             [Restore ▾]   │
│  ├── ○ Incremental — Mar 14 · 3.3 MB             [Restore ▾]   │
│  └── ○ Incremental — Mar 15 · 2.1 MB             [Restore ▾]   │
│                                                                  │
│  [Load more...]                                                  │
└──────────────────────────────────────────────────────────────────┘
```

**`CloudStorageBackupCard.tsx`** — Individual backup entry with:
- Type indicator (● full / ○ incremental)
- Date/time, file size, branch name, commit SHA
- Tables changed count, rows changed count (incremental only)
- Status badge (success / failed / uploading / pending)
- Action dropdown: Restore, Download, Delete, View on GitHub/GitLab

**`CloudStorageScheduleSettings.tsx`** — Schedule configuration:

```
┌──────────────────────────────────────────────────────────────┐
│ Backup Schedule                                              │
│                                                              │
│ Strategy:  ○ Full backups only                               │
│            ● Full + Incremental backups                      │
│                                                              │
│ Full Backup                                                  │
│   Frequency: [▼ Weekly     ]                                │
│   Day:       [▼ Sunday     ]                                │
│   Time:      [▼ 02:00 UTC  ]                                │
│                                                              │
│ Incremental Backup                                           │
│   Frequency: [▼ Daily      ]                                │
│   Time:      [▼ 02:00 UTC  ]                                │
│                                                              │
│ Retention                                                    │
│   Full backups to keep:        [▼ 4  ] (≈ 1 month)          │
│   Incremental per full cycle:  [▼ 6  ] (Mon–Sat)            │
│                                                              │
│                                         [Save Schedule]     │
└──────────────────────────────────────────────────────────────┘
```

### 5.2 REST Endpoints (New)

```
GET  /cloud-storage/backup-history?account_id={id}&page=1&per_page=20
     → { Success, BackupHistory: [...], Total, Page, PerPage }

GET  /cloud-storage/backup-history/{id}
     → { Success, Backup: { ...full details including manifest... } }

DELETE /cloud-storage/backup-history/{id}
     → { Success, Message }
```

---

## 6. Rotation Strategy

### 6.1 Full Backup Rotation

- Retention count applies to full backups on `main` branch
- When a full backup is deleted, its associated incremental branch is also deleted
- Default retention: 4 full backups (≈ 1 month of weekly fulls)

### 6.2 Incremental Rotation

- Each full backup cycle has its own incremental branch
- Incrementals within a branch are capped (default: 6 per cycle)
- When the full backup is rotated out, the entire incremental branch is pruned

### 6.3 Branch Deletion

**GitHub**:
```
DELETE /repos/{owner}/{repo}/git/refs/heads/incremental/2026-W10
```

**GitLab**:
```
DELETE /projects/{id}/repository/branches/incremental/2026-W10
```

---

## 7. Google Drive Adaptation

Google Drive does not use Git branches. Instead, the folder structure mirrors the branch strategy:

```
WordPress Backups/
├── full/
│   ├── wp-backup-full-2026-03-09-020000.zip
│   ├── wp-backup-full-2026-03-16-020000.zip
│   └── ...
├── incremental/
│   ├── 2026-W10/
│   │   ├── wp-backup-incr-2026-03-02-020000.zip
│   │   └── ...
│   ├── 2026-W11/
│   │   ├── wp-backup-incr-2026-03-10-020000.zip
│   │   └── ...
│   └── ...
└── manifests/
    ├── manifest-2026-03-10.json
    └── ...
```

---

## 8. Implementation Plan

### Phase 5A: Repo Selection + Branch Management (GitHub/GitLab)
1. Migration v19 — add `RepoSelectionMode`, `DefaultBranch` columns
2. New endpoints: list repos, list branches
3. Update `CloudStorageAccountDialog` with repo selector UI
4. Add branch create/delete helpers to GitHub/GitLab traits

### Phase 5B: Full + Incremental Backup Engine
1. Migration v20 — `CloudStorageBackupHistory` table
2. New enums: `BackupStrategyType`, `BackupScheduleType`, `CloudStorageBackupType`, `CloudStorageBackupStatusType`
3. Migration v19 additions to `CloudStorageSettings` — schedule columns
4. Implement full backup handler (commit to main, tag)
5. Implement incremental backup handler (branch, commit, manifest)

### Phase 5C: Scheduling
1. Register WP-Cron events on plugin activation
2. Implement `CloudStorageScheduleTrait.php` — cron handlers
3. Update `CloudStorageScheduleSettings.tsx` in React dashboard

### Phase 5D: Restore
1. Implement `handleCloudStorageRestore()` — git clone + extract + restore
2. API download fallback for non-git environments
3. New endpoint: `POST /cloud-storage/restore`

### Phase 5E: Visualization
1. Backup history endpoints (list, get, delete)
2. `CloudStorageBackupTimeline.tsx` — weekly grouped timeline
3. `CloudStorageBackupCard.tsx` — individual entry with actions
4. `CloudStorageScheduleSettings.tsx` — schedule configuration UI

### Phase 5F: Google Drive Folders
1. Adapt folder structure for full/incremental separation
2. Manifest file uploads alongside backups
3. Folder-based rotation

---

## 9. New File Inventory

### PHP Files

| File | Purpose |
|------|---------|
| `Enums/BackupStrategyType.php` | Full-only vs full+incremental enum |
| `Enums/BackupScheduleType.php` | Schedule frequency enum |
| `Enums/CloudStorageBackupType.php` | Full vs incremental enum |
| `Enums/CloudStorageBackupStatusType.php` | Backup status enum |
| `Database/Traits/DatabaseMigrationsV19Trait.php` | Schema additions to accounts + settings |
| `Database/Traits/DatabaseMigrationsV20Trait.php` | BackupHistory table |
| `Traits/CloudStorage/CloudStorageScheduleTrait.php` | Cron registration + handlers |
| `Traits/CloudStorage/CloudStorageRestoreTrait.php` | Git clone restore logic |
| `Traits/CloudStorage/CloudStorageHistoryTrait.php` | Backup history CRUD |
| `Traits/CloudStorage/CloudStorageBranchTrait.php` | Branch create/delete/list helpers |

### React Files

| File | Purpose |
|------|---------|
| `CloudStorageBackupTimeline.tsx` | Weekly grouped backup timeline |
| `CloudStorageBackupCard.tsx` | Individual backup entry component |
| `CloudStorageScheduleSettings.tsx` | Schedule configuration form |
| `CloudStorageRepoSelector.tsx` | Create new / select existing repo widget |

### Modified Files

| File | Changes |
|------|---------|
| `CloudStorageAccountDialog.tsx` | Add repo selector mode |
| `CloudStorageSettingsPage.tsx` | Add backup history tab, schedule settings |
| `CloudStorageTrait.php` | Compose new sub-traits |
| `RouteRegistrationTrait.php` | Register 5 new endpoints |
| `EndpointType.php` | Add 5 new endpoint cases |

---

## 10. Testing Checklist

- [ ] Create new repo mode → verify repo created with README
- [ ] Select existing repo mode → verify repo list loads, branch list loads
- [ ] Full backup → committed to main, tagged with version
- [ ] Incremental backup → committed to correct weekly branch
- [ ] Incremental branch created from full backup commit SHA
- [ ] Schedule settings saved → WP-Cron events registered
- [ ] Scheduled full backup fires on configured day/time
- [ ] Scheduled incremental backup fires daily
- [ ] Restore full backup → git clone, extract, verify restored data
- [ ] Restore incremental → full cloned first, then incremental applied
- [ ] API fallback restore (no git binary) → download via Contents API
- [ ] Rotation: full backup deleted → associated incremental branch pruned
- [ ] Backup history timeline renders correctly with weekly grouping
- [ ] Google Drive: folder structure matches spec (full/, incremental/YYYY-Www/)
- [ ] Large backup (>100 MB) → Git Data API used for full, chunked for Google Drive

---

## Cross-References

- Existing backup system: `knowledge://memory/features/plugin/remote-backups`
- Cloud storage overview: [01-overview.md](./01-overview.md)
- GitHub implementation: [05-github-implementation.md](./05-github-implementation.md)
- GitLab implementation: [06-gitlab-implementation.md](./06-gitlab-implementation.md)
- Google Drive implementation: [07-google-drive-implementation.md](./07-google-drive-implementation.md)
- Database schema: [02-database-schema.md](./02-database-schema.md)
- Endpoints: [04-endpoints.md](./04-endpoints.md)
