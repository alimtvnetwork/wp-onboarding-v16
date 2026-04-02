# S-046 — Google Drive Folder Rotation

> **Status:** Spec Complete  
> **Priority:** Medium  
> **Dependencies:** Cloud Storage PHP trait, Go cloud storage service

---

## Overview

Implement automatic folder rotation for Google Drive cloud storage accounts. When a backup folder reaches a configured limit (by count or total size), older backups are automatically pruned or rotated to a secondary/archive folder.

## Current State

- **CloudStorage page** has Google Drive tab with account management
- **Go backend** has `CloudStorageTypes.go` with `CloudStorageSettings` including `retentionCount` and `rotationEnabled` fields
- **PHP** has cloud storage traits in the uploader plugin
- **Google Drive accounts** store `folderId` and `folderName`

## Features to Add

### 1. Rotation Configuration

Extend `CloudStorageSettings` for Google Drive:
- `rotationEnabled` (existing field) — enable/disable
- `maxBackupCount` — max number of backups to keep (default: 30)
- `maxTotalSizeMB` — max total size in MB before rotation (default: 5000)
- `archiveFolderId` — optional secondary folder to move old backups to (instead of delete)
- `rotationPolicy` — enum: `delete_oldest` | `archive_oldest` | `keep_full_delete_incremental`

### 2. Rotation Logic (Go Service)

On each new backup upload to Google Drive:
1. List existing files in the backup folder
2. Calculate total count and size
3. If exceeds limits, apply rotation policy:
   - **delete_oldest**: Delete oldest files until under limits
   - **archive_oldest**: Move oldest files to archive folder
   - **keep_full_delete_incremental**: Keep all full backups, delete oldest incrementals first

### 3. Go Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/cloud-storage/accounts/{id}/rotation-status` | Current rotation state (count, size, next action) |
| POST | `/cloud-storage/accounts/{id}/rotate` | Trigger manual rotation |
| PUT | `/cloud-storage/settings/rotation` | Update rotation settings |

### 4. PHP Integration

In the uploader plugin, after a successful Google Drive upload:
- Call the Go backend's rotation check endpoint
- Log rotation actions to the audit trail

### 5. React UI

Add to the Cloud Storage → Google Drive provider settings panel:
- Toggle for rotation enabled/disabled
- Max backup count input
- Max total size input (MB)
- Rotation policy dropdown
- Archive folder selector (optional)
- "Rotation Status" card showing current count/size vs limits
- "Rotate Now" button for manual trigger

### 6. Go Service Implementation

```go
type RotationConfig struct {
    MaxBackupCount int
    MaxTotalSizeMB int
    ArchiveFolderId string
    Policy         RotationPolicy // delete_oldest | archive_oldest | keep_full_delete_incremental
}

type RotationStatus struct {
    CurrentCount   int
    CurrentSizeMB  float64
    MaxCount       int
    MaxSizeMB      int
    IsOverLimit    bool
    NextAction     string // "none" | "delete N files" | "archive N files"
}
```

## Implementation Order

1. Add rotation config fields to Go types and DB schema
2. Implement rotation logic in Go cloud storage service
3. Add rotation status endpoint
4. Add manual rotation trigger endpoint
5. Update React provider settings panel with rotation UI
6. Integrate post-upload rotation check in PHP

## Edge Cases

- Empty archive folder creation if it doesn't exist
- Concurrent uploads during rotation — use file locking or queue
- Google Drive API rate limits — batch delete operations
- Handle partially failed rotations (some files deleted, some not)
