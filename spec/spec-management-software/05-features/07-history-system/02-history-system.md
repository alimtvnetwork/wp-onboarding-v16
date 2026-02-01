# History System

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Summary

Snapshot-based history system for version management, allowing users to create, restore, and manage point-in-time snapshots of project files.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Snapshot Service                          │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │ Create      │  │ Restore     │  │ Cleanup             │ │
│  │ Snapshot    │  │ Snapshot    │  │ (Retention)         │ │
│  └─────────────┘  └─────────────┘  └─────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                            │
              ┌─────────────┴─────────────┐
              ▼                           ▼
     ┌─────────────────┐        ┌─────────────────┐
     │ .history Folder │        │ SQLite Database │
     │ (File Storage)  │        │ (Metadata)      │
     └─────────────────┘        └─────────────────┘
```

---

## Folder Structure

### History Location

Each project/category has its own `.history` folder:

```
spec/
├── general-spec/
│   ├── .history/
│   │   ├── V01-2026-01-20/
│   │   │   ├── 00-overview.md
│   │   │   └── 01-foundation/
│   │   │       ├── 01-coding-standards-foundation.md
│   │   │       └── 02-error-management-foundation.md
│   │   ├── V02-2026-01-25/
│   │   │   └── ... (full snapshot)
│   │   └── V03-2026-01-27/
│   │       └── ... (full snapshot)
│   ├── 00-overview.md
│   └── 01-foundation/
│       └── ...
└── wp-plugin/
    └── exam-manager/
        ├── .history/
        │   ├── V01-2026-01-15/
        │   └── V02-2026-01-22/
        └── ...
```

### Snapshot Naming Convention

```
V{nn}-{YYYY-MM-DD}
```

| Component | Description |
|-----------|-------------|
| V | Version prefix |
| nn | Two-digit sequence (01-99) |
| YYYY-MM-DD | Date |

**Examples:**
- `V01-2026-01-20`
- `V15-2026-01-27`

---

## Configuration

| Key | Default | Description |
|-----|---------|-------------|
| `snapshot_retention_days` | `90` | Days to retain snapshots |
| `max_snapshots_per_project` | `50` | Maximum snapshots per project |
| `snapshot_cleanup_enabled` | `true` | Enable automatic cleanup |
| `snapshot_cleanup_cron` | `0 3 * * *` | Cleanup schedule (3 AM daily) |

---

## Snapshot Creation

### Flow

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│ User Request │────▶│ Generate     │────▶│ Copy Files   │
│ or Auto      │     │ Snapshot ID  │     │ to .history  │
└──────────────┘     └──────────────┘     └──────────────┘
                                                  │
                                                  ▼
                     ┌──────────────┐     ┌──────────────┐
                     │ Git Commit   │◀────│ Save to DB   │
                     │ (Optional)   │     │ (Metadata)   │
                     └──────────────┘     └──────────────┘
```

### Service Implementation

```go
type SnapshotService struct {
    db        *sql.DB
    fileRepo  *FileRepository
    gitSvc    *GitService
    config    *Config
}

func (s *SnapshotService) Create(ctx context.Context, req CreateSnapshotRequest) (*Snapshot, error) {
    // 1. Validate project exists
    project, err := s.projectRepo.GetById(ctx, req.ProjectId)
    if err != nil {
        return nil, fmt.Errorf("ERR_4002: project not found")
    }
    
    // 2. Generate snapshot name
    nextNum := s.getNextSnapshotNumber(ctx, req.ProjectId)
    timestamp := time.Now().Format("2006-01-02")
    name := fmt.Sprintf("V%02d-%s", nextNum, timestamp)
    
    // 3. Create .history folder path
    historyPath := filepath.Join(project.Path, ".history", name)
    
    // 4. Copy all project files to snapshot
    if err := s.copyProjectFiles(project.Path, historyPath); err != nil {
        return nil, fmt.Errorf("ERR_5002: %w", err)
    }
    
    // 5. Save snapshot metadata to database
    snapshot := &Snapshot{
        Id:          uuid.NewString(),
        ProjectId:   req.ProjectId,
        CreatedById: req.UserId,
        Name:        name,
        Description: req.Description,
        FolderPath:  historyPath,
        CreatedAt:   time.Now(),
    }
    
    if err := s.snapshotRepo.Create(ctx, snapshot); err != nil {
        // Rollback: delete copied files
        os.RemoveAll(historyPath)
        return nil, fmt.Errorf("ERR_9002: %w", err)
    }
    
    // 6. Git commit if enabled
    if s.config.AutoCommitEnabled {
        s.gitSvc.QueueCommit(CommitQueueEntry{
            FilePath:  historyPath,
            Action:    "snapshot",
            UserId:    req.UserId,
            Username:  req.Username,
        })
    }
    
    log.Info("Snapshot created",
        "name", name,
        "project", project.Name,
        "user", req.Username)
    
    return snapshot, nil
}

func (s *SnapshotService) copyProjectFiles(srcPath, destPath string) error {
    return filepath.Walk(srcPath, func(path string, info os.FileInfo, err error) error {
        if err != nil {
            return err
        }
        
        // Skip .history folder itself
        if info.IsDir() && info.Name() == ".history" {
            return filepath.SkipDir
        }
        
        // Calculate relative path
        relPath, _ := filepath.Rel(srcPath, path)
        destFilePath := filepath.Join(destPath, relPath)
        
        if info.IsDir() {
            return os.MkdirAll(destFilePath, 0755)
        }
        
        // Copy file
        return copyFile(path, destFilePath)
    })
}
```

---

## Snapshot Restoration

### Flow

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│ User Select  │────▶│ Create       │────▶│ Clear        │
│ Snapshot     │     │ Pre-Restore  │     │ Current      │
│              │     │ Snapshot     │     │ Files        │
└──────────────┘     └──────────────┘     └──────────────┘
                                                  │
                                                  ▼
                     ┌──────────────┐     ┌──────────────┐
                     │ Git Commit   │◀────│ Copy From    │
                     │ "Restored"   │     │ Snapshot     │
                     └──────────────┘     └──────────────┘
```

### Service Implementation

```go
func (s *SnapshotService) Restore(ctx context.Context, req RestoreSnapshotRequest) error {
    // 1. Get snapshot metadata
    snapshot, err := s.snapshotRepo.GetById(ctx, req.SnapshotId)
    if err != nil {
        return fmt.Errorf("ERR_4004: snapshot not found")
    }
    
    // 2. Get project
    project, err := s.projectRepo.GetById(ctx, snapshot.ProjectId)
    if err != nil {
        return fmt.Errorf("ERR_4002: project not found")
    }
    
    // 3. Create pre-restore snapshot (safety backup)
    preRestoreName := fmt.Sprintf("PRE-RESTORE-%s", time.Now().Format("2006-01-02-150405"))
    preRestorePath := filepath.Join(project.Path, ".history", preRestoreName)
    if err := s.copyProjectFiles(project.Path, preRestorePath); err != nil {
        log.Warn("Failed to create pre-restore snapshot", "error", err)
        // Continue anyway - user explicitly requested restore
    }
    
    // 4. Clear current project files (except .history)
    if err := s.clearProjectFiles(project.Path); err != nil {
        return fmt.Errorf("ERR_5002: failed to clear files: %w", err)
    }
    
    // 5. Copy snapshot files to project
    if err := s.copyProjectFiles(snapshot.FolderPath, project.Path); err != nil {
        return fmt.Errorf("ERR_5002: failed to restore: %w", err)
    }
    
    // 6. Update file database records
    if err := s.rebuildFileIndex(ctx, project); err != nil {
        log.Error("Failed to rebuild file index", "error", err)
    }
    
    // 7. Git commit
    if s.config.AutoCommitEnabled {
        s.gitSvc.QueueCommit(CommitQueueEntry{
            FilePath:  project.Path,
            Action:    "restored",
            Metadata:  map[string]string{"snapshot": snapshot.Name},
            UserId:    req.UserId,
            Username:  req.Username,
        })
    }
    
    log.Info("Snapshot restored",
        "snapshot", snapshot.Name,
        "project", project.Name,
        "user", req.Username)
    
    return nil
}

func (s *SnapshotService) clearProjectFiles(projectPath string) error {
    entries, err := os.ReadDir(projectPath)
    if err != nil {
        return err
    }
    
    for _, entry := range entries {
        // Preserve .history folder
        if entry.Name() == ".history" {
            continue
        }
        
        fullPath := filepath.Join(projectPath, entry.Name())
        if err := os.RemoveAll(fullPath); err != nil {
            return err
        }
    }
    return nil
}
```

---

## Snapshot Deletion

### Service Implementation

```go
func (s *SnapshotService) Delete(ctx context.Context, req DeleteSnapshotRequest) error {
    // 1. Get snapshot
    snapshot, err := s.snapshotRepo.GetById(ctx, req.SnapshotId)
    if err != nil {
        return fmt.Errorf("ERR_4004: snapshot not found")
    }
    
    // 2. Delete files from filesystem
    if err := os.RemoveAll(snapshot.FolderPath); err != nil {
        log.Error("Failed to delete snapshot files",
            "path", snapshot.FolderPath,
            "error", err)
        // Continue to delete DB record anyway
    }
    
    // 3. Delete from database
    if err := s.snapshotRepo.Delete(ctx, req.SnapshotId); err != nil {
        return fmt.Errorf("ERR_9002: %w", err)
    }
    
    log.Info("Snapshot deleted",
        "name", snapshot.Name,
        "user", req.Username)
    
    return nil
}
```

---

## Automatic Cleanup

### Retention Policy

```go
func (s *SnapshotService) RunCleanup(ctx context.Context) error {
    cutoffDate := time.Now().AddDate(0, 0, -s.config.SnapshotRetentionDays)
    
    // Find expired snapshots
    expired, err := s.snapshotRepo.FindOlderThan(ctx, cutoffDate)
    if err != nil {
        return err
    }
    
    log.Info("Starting snapshot cleanup",
        "expired", len(expired),
        "cutoffDate", cutoffDate.Format(time.RFC3339))
    
    for _, snapshot := range expired {
        if err := s.Delete(ctx, DeleteSnapshotRequest{
            SnapshotId: snapshot.Id,
            Username:   "system",
        }); err != nil {
            log.Error("Failed to delete expired snapshot",
                "snapshot", snapshot.Name,
                "error", err)
        }
    }
    
    // Enforce max snapshots per project
    projects, _ := s.projectRepo.GetAll(ctx)
    for _, project := range projects {
        if err := s.enforceMaxSnapshots(ctx, project.Id); err != nil {
            log.Error("Failed to enforce max snapshots",
                "project", project.Name,
                "error", err)
        }
    }
    
    return nil
}

func (s *SnapshotService) enforceMaxSnapshots(ctx context.Context, projectId string) error {
    snapshots, err := s.snapshotRepo.GetByProject(ctx, projectId, "CreatedAt DESC")
    if err != nil {
        return err
    }
    
    if len(snapshots) <= s.config.MaxSnapshotsPerProject {
        return nil
    }
    
    // Delete oldest snapshots exceeding limit
    toDelete := snapshots[s.config.MaxSnapshotsPerProject:]
    for _, snapshot := range toDelete {
        s.Delete(ctx, DeleteSnapshotRequest{
            SnapshotId: snapshot.Id,
            Username:   "system",
        })
    }
    
    return nil
}
```

### Cron Job

```go
func (app *App) setupCleanupCron() {
    c := cron.New()
    
    // Run at 3 AM daily
    c.AddFunc("0 3 * * *", func() {
        ctx := context.Background()
        if err := app.snapshotService.RunCleanup(ctx); err != nil {
            log.Error("Snapshot cleanup failed", "error", err)
        }
    })
    
    c.Start()
}
```

---

## API Endpoints

### GET /projects/:projectId/snapshots

List all snapshots for a project.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| sortBy | string | createdAt | Sort field |
| sortDir | string | desc | Sort direction |
| limit | int | 20 | Max results |
| offset | int | 0 | Pagination offset |

**Response:**
```json
{
  "success": true,
  "data": {
    "snapshots": [
      {
        "id": "snap-uuid-1",
        "name": "V03-2026-01-27-100000",
        "description": "Before major refactor",
        "createdBy": {
          "id": "user-uuid",
          "username": "johndoe",
          "displayName": "John Doe"
        },
        "createdAt": "2026-01-27T10:00:00Z",
        "fileCount": 47,
        "size": 245678
      },
      {
        "id": "snap-uuid-2",
        "name": "V02-2026-01-25-143022",
        "description": null,
        "createdBy": {...},
        "createdAt": "2026-01-25T14:30:22Z",
        "fileCount": 45,
        "size": 230456
      }
    ],
    "total": 3,
    "limit": 20,
    "offset": 0
  }
}
```

### GET /snapshots/:id

Get snapshot details.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "snap-uuid-1",
    "projectId": "proj-uuid",
    "name": "V03-2026-01-27-100000",
    "description": "Before major refactor",
    "folderPath": "spec/general-spec/.history/V03-2026-01-27-100000",
    "createdBy": {
      "id": "user-uuid",
      "username": "johndoe",
      "displayName": "John Doe"
    },
    "createdAt": "2026-01-27T10:00:00Z",
    "stats": {
      "fileCount": 47,
      "folderCount": 11,
      "totalSize": 245678
    }
  }
}
```

### GET /snapshots/:id/files

List files in a snapshot.

**Response:**
```json
{
  "success": true,
  "data": {
    "tree": [
      {
        "name": "00-overview.md",
        "type": "file",
        "size": 4532,
        "children": null
      },
      {
        "name": "01-foundation",
        "type": "folder",
        "children": [
          {
            "name": "01-coding-standards-foundation.md",
            "type": "file",
            "size": 8901
          }
        ]
      }
    ]
  }
}
```

### GET /snapshots/:id/files/:path/content

Get file content from snapshot.

**Response:**
```json
{
  "success": true,
  "data": {
    "path": "01-foundation/01-coding-standards-foundation.md",
    "content": "# Coding Standards\n\n...",
    "size": 8901
  }
}
```

### POST /projects/:projectId/snapshots

Create a new snapshot.

**Request:**
```json
{
  "description": "Before major refactor"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": "snap-uuid-new",
    "name": "V04-2026-01-27-160000",
    "description": "Before major refactor",
    "createdAt": "2026-01-27T16:00:00Z",
    "fileCount": 47
  }
}
```

### POST /snapshots/:id/restore

Restore a snapshot.

**Request:**
```json
{
  "createPreRestoreSnapshot": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Snapshot restored successfully",
    "restoredFrom": "V03-2026-01-27-100000",
    "preRestoreSnapshot": "PRE-RESTORE-2026-01-27-163000",
    "filesRestored": 47
  }
}
```

### DELETE /snapshots/:id

Delete a snapshot.

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Snapshot deleted successfully",
    "name": "V01-2026-01-20-093000"
  }
}
```

---

## External Change Detection

### File System Watcher

```go
type SyncService struct {
    watcher     *fsnotify.Watcher
    fileRepo    *FileRepository
    projectRepo *ProjectRepository
    debouncer   *Debouncer
}

func (s *SyncService) StartWatching(specPath string) error {
    watcher, err := fsnotify.NewWatcher()
    if err != nil {
        return err
    }
    s.watcher = watcher
    
    // Add all directories recursively
    filepath.Walk(specPath, func(path string, info os.FileInfo, err error) error {
        if info.IsDir() && info.Name() != ".history" && info.Name() != ".git" {
            watcher.Add(path)
        }
        return nil
    })
    
    go s.watchLoop()
    return nil
}

func (s *SyncService) watchLoop() {
    for {
        select {
        case event, ok := <-s.watcher.Events:
            if !ok {
                return
            }
            s.handleEvent(event)
            
        case err, ok := <-s.watcher.Errors:
            if !ok {
                return
            }
            log.Error("Watcher error", "error", err)
        }
    }
}

func (s *SyncService) handleEvent(event fsnotify.Event) {
    // Skip .history and .git
    if strings.Contains(event.Name, ".history") || strings.Contains(event.Name, ".git") {
        return
    }
    
    // Debounce rapid events
    s.debouncer.Add(event.Name, func() {
        s.syncFile(event.Name, event.Op)
    })
}

func (s *SyncService) syncFile(path string, op fsnotify.Op) {
    ctx := context.Background()
    
    switch {
    case op&fsnotify.Create != 0:
        s.handleCreate(ctx, path)
    case op&fsnotify.Write != 0:
        s.handleModify(ctx, path)
    case op&fsnotify.Remove != 0:
        s.handleDelete(ctx, path)
    case op&fsnotify.Rename != 0:
        s.handleRename(ctx, path)
    }
}
```

### Reconciliation

```go
func (s *SyncService) FullReconcile(ctx context.Context) error {
    log.Info("Starting full reconciliation")
    
    // 1. Get all files from database
    dbFiles, err := s.fileRepo.GetAll(ctx)
    if err != nil {
        return err
    }
    dbFileMap := make(map[string]*File)
    for _, f := range dbFiles {
        dbFileMap[f.Path] = f
    }
    
    // 2. Walk filesystem
    fsFiles := make(map[string]bool)
    filepath.Walk(s.specPath, func(path string, info os.FileInfo, err error) error {
        if strings.Contains(path, ".history") || strings.Contains(path, ".git") {
            if info.IsDir() {
                return filepath.SkipDir
            }
            return nil
        }
        
        relPath, _ := filepath.Rel(s.specPath, path)
        fsFiles[relPath] = true
        
        // Check if exists in DB
        if dbFile, exists := dbFileMap[relPath]; exists {
            // Check if modified
            if !info.IsDir() {
                hash := s.computeHash(path)
                if hash != dbFile.ContentHash {
                    s.updateFile(ctx, dbFile, hash)
                }
            }
        } else {
            // New file - add to DB
            s.createFile(ctx, relPath, info)
        }
        
        return nil
    })
    
    // 3. Find deleted files (in DB but not on FS)
    for path, file := range dbFileMap {
        if !fsFiles[path] {
            s.deleteFile(ctx, file)
        }
    }
    
    log.Info("Reconciliation complete")
    return nil
}
```

---

## Acceptance Criteria

### Snapshot Creation (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SC-001 | Snapshot creates full copy in .history/{name}/ | Critical | File copy test |
| SC-002 | Snapshot name follows V{nn}-{YYYY-MM-DD} format | Critical | Naming test |
| SC-003 | Sequence number derived from next available | High | Sequence test |
| SC-004 | Snapshot metadata saved to database | Critical | DB insert test |
| SC-005 | .history folder itself excluded from snapshot | Critical | Exclusion test |
| SC-006 | Git commit triggered if auto-commit enabled | High | Git integration test |
| SC-007 | Snapshot creation fails gracefully on disk full | High | Error handling test |

### Snapshot Restoration (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SR-001 | Pre-restore snapshot created automatically | Critical | Safety backup test |
| SR-002 | Current files cleared except .history | Critical | Clear test |
| SR-003 | Snapshot files copied to project root | Critical | Restore test |
| SR-004 | File database index rebuilt after restore | High | Index rebuild test |
| SR-005 | Git commit logged with snapshot name | High | Git commit test |
| SR-006 | Restore from non-existent snapshot returns ERR_4004 | Critical | Error code test |

### Snapshot Deletion (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SD-001 | Filesystem files removed on delete | Critical | File delete test |
| SD-002 | Database record removed on delete | Critical | DB delete test |
| SD-003 | Partial filesystem delete logs warning | Medium | Partial failure test |
| SD-004 | Delete non-existent snapshot returns ERR_4004 | Critical | Error code test |

### Automatic Cleanup (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AC-001 | Snapshots older than retention days deleted | Critical | Retention test |
| AC-002 | Max snapshots per project enforced | High | Max limit test |
| AC-003 | Oldest snapshots deleted first when over limit | High | FIFO deletion test |
| AC-004 | Cleanup runs on configured cron schedule | Medium | Cron test |
| AC-005 | Cleanup failures logged but don't crash app | High | Error resilience test |

### API Endpoints (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AE-001 | GET /snapshots returns paginated list | Critical | List test |
| AE-002 | POST /snapshots creates new snapshot | Critical | Create test |
| AE-003 | POST /snapshots/{id}/restore restores snapshot | Critical | Restore test |
| AE-004 | DELETE /snapshots/{id} deletes snapshot | Critical | Delete test |
| AE-005 | File count and size returned in response | High | Stats test |

### Reconciliation (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| RC-001 | Reconcile detects new files on filesystem | High | New file test |
| RC-002 | Reconcile detects modified files via hash | High | Modified file test |
| RC-003 | Reconcile detects deleted files | High | Deleted file test |
| RC-004 | .history and .git excluded from reconciliation | Critical | Exclusion test |

---

## Related Specs

- [History System Overview](./00-overview.md)
- [Git Integration](./01-git-integration.md)
- [Database Schema](../../07-database-design/01-schema.md)
