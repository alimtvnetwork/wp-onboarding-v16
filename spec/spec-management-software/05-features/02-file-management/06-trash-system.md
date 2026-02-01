# Trash Bin System

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Parent:** [File Management](./00-overview.md)

---

## Purpose

Define a recoverable trash bin system ensuring **no file is permanently deleted without explicit user confirmation**. All delete operations move files to a trash bin first, providing a safety net against accidental data loss.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      TRASH BIN SYSTEM                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────┐   ┌──────────────────┐                    │
│  │   Trash Manager  │──▶│  Trash Storage   │                    │
│  │                  │   │                  │                    │
│  │  • Soft delete   │   │  .trash/{date}/  │                    │
│  │  • Restore       │   │  Original path   │                    │
│  │  • Empty trash   │   │  Metadata JSON   │                    │
│  └──────────────────┘   └──────────────────┘                    │
│           │                                                      │
│           ▼                                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                TRASH DATABASE TABLE                       │   │
│  │                                                          │   │
│  │  TrashEntry                                              │   │
│  │  ├── ID: string                                          │   │
│  │  ├── OriginalPath: string                                │   │
│  │  ├── TrashPath: string                                   │   │
│  │  ├── OriginalHash: string                                │   │
│  │  ├── DeletedAt: timestamp                                │   │
│  │  ├── DeletedBy: string (user_id)                         │   │
│  │  ├── DeleteReason: string                                │   │
│  │  ├── IsDirectory: bool                                   │   │
│  │  ├── SizeBytes: int64                                    │   │
│  │  ├── RetentionDeadline: timestamp                        │   │
│  │  └── PermanentlyDeleted: bool                            │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Directory Structure

```
{workDirectory}/{ProjectSlug}/
├── spec/
│   └── ...
├── .trash/
│   ├── 2026-01-30/
│   │   ├── abc123_03-old-spec.md           # Deleted file
│   │   ├── abc123_03-old-spec.md.meta      # Metadata
│   │   ├── def456_01-feature/              # Deleted directory
│   │   │   ├── 01-overview.md
│   │   │   └── 02-implementation.md
│   │   └── def456_01-feature.meta          # Directory metadata
│   └── 2026-01-29/
│       └── ...
└── .history/
    └── ...
```

---

## Trash Manager

### Core Operations

```go
type TrashManager struct {
    db          *gorm.DB
    projectRoot string
    trashDir    string
    retention   time.Duration // Default: 30 days
}

type TrashEntry struct {
    ID                  string    `gorm:"primaryKey"`
    ProjectID           string    `gorm:"index"`
    OriginalPath        string    `gorm:"index"`
    TrashPath           string
    OriginalHash        string
    DeletedAt           time.Time `gorm:"index"`
    DeletedBy           string    `gorm:"index"`
    DeleteReason        string
    IsDirectory         bool
    SizeBytes           int64
    FileCount           int       // For directories
    RetentionDeadline   time.Time `gorm:"index"`
    PermanentlyDeleted  bool      `gorm:"default:false"`
}

func NewTrashManager(db *gorm.DB, projectRoot string) *TrashManager {
    return &TrashManager{
        db:          db,
        projectRoot: projectRoot,
        trashDir:    filepath.Join(projectRoot, ".trash"),
        retention:   30 * 24 * time.Hour, // 30 days
    }
}
```

### Soft Delete

```go
func (tm *TrashManager) SoftDelete(ctx context.Context, path string, userID string, reason string) (*TrashEntry, error) {
    absPath := filepath.Join(tm.projectRoot, path)
    
    // Check if path exists
    info, err := os.Stat(absPath)
    if err != nil {
        return nil, fmt.Errorf("ERR_FS_NOT_FOUND: %w", err)
    }
    
    // Generate unique trash path
    now := time.Now()
    dateDir := now.Format("2006-01-02")
    entryID := generateID()
    baseName := filepath.Base(path)
    trashPath := filepath.Join(tm.trashDir, dateDir, entryID+"_"+baseName)
    
    // Calculate size
    var sizeBytes int64
    var fileCount int
    if info.IsDir() {
        sizeBytes, fileCount = tm.calculateDirSize(absPath)
    } else {
        sizeBytes = info.Size()
        fileCount = 1
    }
    
    // Compute content hash (for files)
    var contentHash string
    if !info.IsDir() {
        contentHash, _ = tm.computeHash(absPath)
    }
    
    // Create trash directory structure
    if err := os.MkdirAll(filepath.Dir(trashPath), 0755); err != nil {
        return nil, fmt.Errorf("ERR_FS_WRITE: failed to create trash dir: %w", err)
    }
    
    // Move file/directory to trash
    if err := os.Rename(absPath, trashPath); err != nil {
        return nil, fmt.Errorf("ERR_FS_DELETE: failed to move to trash: %w", err)
    }
    
    // Create database entry
    entry := &TrashEntry{
        ID:                 entryID,
        ProjectID:          tm.getProjectID(),
        OriginalPath:       path,
        TrashPath:          trashPath,
        OriginalHash:       contentHash,
        DeletedAt:          now,
        DeletedBy:          userID,
        DeleteReason:       reason,
        IsDirectory:        info.IsDir(),
        SizeBytes:          sizeBytes,
        FileCount:          fileCount,
        RetentionDeadline:  now.Add(tm.retention),
        PermanentlyDeleted: false,
    }
    
    if err := tm.db.Create(entry).Error; err != nil {
        // Rollback: move back
        os.Rename(trashPath, absPath)
        return nil, fmt.Errorf("ERR_DB_WRITE: %w", err)
    }
    
    // Write metadata file
    tm.writeMetadata(trashPath, entry)
    
    return entry, nil
}
```

### Restore from Trash

```go
func (tm *TrashManager) Restore(ctx context.Context, entryID string) error {
    var entry TrashEntry
    if err := tm.db.First(&entry, "id = ?", entryID).Error; err != nil {
        return fmt.Errorf("ERR_FS_NOT_FOUND: trash entry not found")
    }
    
    if entry.PermanentlyDeleted {
        return fmt.Errorf("ERR_FS_NOT_FOUND: file was permanently deleted")
    }
    
    // Check if original path is now occupied
    originalAbs := filepath.Join(tm.projectRoot, entry.OriginalPath)
    if _, err := os.Stat(originalAbs); err == nil {
        return fmt.Errorf("ERR_FS_EXISTS: original path already exists")
    }
    
    // Ensure parent directory exists
    if err := os.MkdirAll(filepath.Dir(originalAbs), 0755); err != nil {
        return fmt.Errorf("ERR_FS_WRITE: %w", err)
    }
    
    // Move back from trash
    if err := os.Rename(entry.TrashPath, originalAbs); err != nil {
        return fmt.Errorf("ERR_FS_WRITE: failed to restore: %w", err)
    }
    
    // Remove from trash database
    if err := tm.db.Delete(&entry).Error; err != nil {
        // Rollback
        os.Rename(originalAbs, entry.TrashPath)
        return fmt.Errorf("ERR_DB_WRITE: %w", err)
    }
    
    // Remove metadata file
    os.Remove(entry.TrashPath + ".meta")
    
    return nil
}
```

### Permanent Delete

```go
func (tm *TrashManager) PermanentDelete(ctx context.Context, entryID string) error {
    var entry TrashEntry
    if err := tm.db.First(&entry, "id = ?", entryID).Error; err != nil {
        return fmt.Errorf("ERR_FS_NOT_FOUND: trash entry not found")
    }
    
    // Permanently remove from filesystem
    if err := os.RemoveAll(entry.TrashPath); err != nil {
        return fmt.Errorf("ERR_FS_DELETE: %w", err)
    }
    
    // Remove metadata file
    os.Remove(entry.TrashPath + ".meta")
    
    // Mark as permanently deleted in database (keep for audit)
    entry.PermanentlyDeleted = true
    if err := tm.db.Save(&entry).Error; err != nil {
        return fmt.Errorf("ERR_DB_WRITE: %w", err)
    }
    
    return nil
}
```

### Empty Trash

```go
func (tm *TrashManager) EmptyTrash(ctx context.Context, beforeDate *time.Time) (int, error) {
    query := tm.db.Where("permanently_deleted = ?", false)
    
    if beforeDate != nil {
        query = query.Where("deleted_at < ?", beforeDate)
    }
    
    var entries []TrashEntry
    if err := query.Find(&entries).Error; err != nil {
        return 0, fmt.Errorf("ERR_DB_READ: %w", err)
    }
    
    deletedCount := 0
    for _, entry := range entries {
        if err := tm.PermanentDelete(ctx, entry.ID); err == nil {
            deletedCount++
        }
    }
    
    return deletedCount, nil
}
```

### Auto-Cleanup (Retention Policy)

```go
func (tm *TrashManager) RunRetentionCleanup(ctx context.Context) (int, error) {
    now := time.Now()
    
    var expiredEntries []TrashEntry
    err := tm.db.Where("retention_deadline < ? AND permanently_deleted = ?", now, false).
        Find(&expiredEntries).Error
    if err != nil {
        return 0, err
    }
    
    cleanedCount := 0
    for _, entry := range expiredEntries {
        if err := tm.PermanentDelete(ctx, entry.ID); err == nil {
            cleanedCount++
        }
    }
    
    return cleanedCount, nil
}
```

---

## TypeScript Types

```typescript
interface TrashEntry {
  readonly id: string;
  readonly projectId: string;
  readonly originalPath: string;
  readonly trashPath: string;
  readonly originalHash: string;
  readonly deletedAt: string; // ISO8601
  readonly deletedBy: string;
  readonly deleteReason: string;
  readonly isDirectory: boolean;
  readonly sizeBytes: number;
  readonly fileCount: number;
  readonly retentionDeadline: string; // ISO8601
  readonly permanentlyDeleted: boolean;
}

interface TrashListResponse {
  readonly entries: readonly TrashEntry[];
  readonly totalSize: number;
  readonly totalCount: number;
}

interface TrashStats {
  readonly totalItems: number;
  readonly totalSizeBytes: number;
  readonly oldestItem: string | null; // ISO8601
  readonly nearingExpiration: number; // Items expiring within 7 days
}
```

---

## API Endpoints

### GET /api/v1/projects/{projectId}/trash

List items in trash.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `limit` | int | 50 | Items per page |
| `sortBy` | string | `deletedAt` | Sort field |
| `sortDir` | string | `desc` | Sort direction |

**Response:**
```json
{
  "success": true,
  "data": {
    "entries": [
      {
        "id": "abc123",
        "originalPath": "spec/01-feature/03-old-spec.md",
        "deletedAt": "2026-01-30T10:00:00Z",
        "deletedBy": "user_123",
        "deleteReason": "No longer needed",
        "isDirectory": false,
        "sizeBytes": 2048,
        "retentionDeadline": "2026-03-01T10:00:00Z"
      }
    ],
    "totalCount": 15,
    "totalSize": 524288
  }
}
```

### POST /api/v1/projects/{projectId}/trash/{entryId}/restore

Restore item from trash.

**Response:**
```json
{
  "success": true,
  "data": {
    "restoredPath": "spec/01-feature/03-old-spec.md",
    "restoredAt": "2026-01-30T12:00:00Z"
  }
}
```

### DELETE /api/v1/projects/{projectId}/trash/{entryId}

Permanently delete item from trash.

**Response:**
```json
{
  "success": true,
  "data": {
    "permanentlyDeletedAt": "2026-01-30T12:00:00Z"
  }
}
```

### DELETE /api/v1/projects/{projectId}/trash

Empty trash (all or before date).

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `before` | string | ISO8601 date - only delete items before this date |

**Response:**
```json
{
  "success": true,
  "data": {
    "deletedCount": 15,
    "freedBytes": 524288
  }
}
```

---

## UI Components

### Trash Bin Panel

```tsx
import { useState } from 'react';
import { Trash2, RotateCcw, AlertTriangle } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog';

export function TrashBinPanel({ projectId }: { projectId: string }) {
  const queryClient = useQueryClient();
  
  const { data: trash, isLoading } = useQuery({
    queryKey: ['trash', projectId],
    queryFn: () => fetchTrash(projectId),
  });
  
  const restoreMutation = useMutation({
    mutationFn: (entryId: string) => restoreFromTrash(projectId, entryId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['trash', projectId] }),
  });
  
  const emptyTrashMutation = useMutation({
    mutationFn: () => emptyTrash(projectId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['trash', projectId] }),
  });
  
  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between p-4 border-b">
        <h2 className="text-lg font-semibold flex items-center gap-2">
          <Trash2 className="h-5 w-5" />
          Trash
        </h2>
        
        <AlertDialog>
          <AlertDialogTrigger asChild>
            <Button variant="destructive" size="sm" disabled={!trash?.totalCount}>
              Empty Trash
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Empty Trash?</AlertDialogTitle>
              <AlertDialogDescription>
                This will permanently delete {trash?.totalCount} items. This action cannot be undone.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction onClick={() => emptyTrashMutation.mutate()}>
                Empty Trash
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
      
      <ScrollArea className="flex-1">
        {trash?.entries.map((entry) => (
          <TrashEntryRow
            key={entry.id}
            entry={entry}
            onRestore={() => restoreMutation.mutate(entry.id)}
          />
        ))}
      </ScrollArea>
      
      <div className="p-4 border-t text-sm text-muted-foreground">
        {trash?.totalCount} items • {formatBytes(trash?.totalSize ?? 0)}
      </div>
    </div>
  );
}
```

---

## Integration with File Operations

Update the delete operation to use trash:

```go
// In file-operations.go
func (fs *FileService) DeleteFile(ctx context.Context, fileID string, permanent bool) error {
    file, err := fs.GetFile(ctx, fileID)
    if err != nil {
        return err
    }
    
    if permanent {
        // Direct permanent delete (with appropriate permission check)
        return fs.permanentDelete(ctx, file)
    }
    
    // Default: soft delete to trash
    _, err = fs.trashManager.SoftDelete(ctx, file.Path, ctx.Value("userID").(string), "User deleted")
    return err
}
```

---

## Acceptance Criteria

| ID | Criterion | Priority | Validation |
|----|-----------|----------|------------|
| TSH-01 | All deletes go to trash by default | MUST | Integration test |
| TSH-02 | Trash preserves original path | MUST | Unit test |
| TSH-03 | Restore returns file to original location | MUST | E2E test |
| TSH-04 | 30-day retention policy auto-cleanup | MUST | Scheduled job test |
| TSH-05 | Empty trash requires confirmation | MUST | E2E test |
| TSH-06 | Trash UI shows size and item count | SHOULD | Visual test |
| TSH-07 | Permanent delete removes from filesystem | MUST | Integration test |
| TSH-08 | Metadata preserved in .meta files | SHOULD | Unit test |
| TSH-09 | Restore fails gracefully if path occupied | MUST | Unit test |
| TSH-10 | Directory deletion preserves structure | MUST | E2E test |

---

## Related Specs

- [01-file-operations.md](./01-file-operations.md) — Delete operation integration
- [05-external-file-safety.md](./05-external-file-safety.md) — External file consent
- [07-history-system/02-history-system.md](../07-history-system/02-history-system.md) — Snapshots
