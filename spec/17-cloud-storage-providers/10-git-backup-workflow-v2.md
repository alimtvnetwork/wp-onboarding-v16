# Git Backup Workflow v2 — Simplified Branch + Split ZIP Strategy

> **Created:** 2026-03-15  
> **Status:** Draft — awaiting review  
> **Supersedes:** Multi-branch strategy from `09-git-backup-strategy.md`

---

## 1. Key Changes from v1

| Aspect | v1 (Previous) | v2 (Current) |
|--------|---------------|--------------|
| Branches | `main` + `incremental/YYYY-Www` | **`main` only** |
| Upload method | Git Data API (Blob/Tree/Commit) | **Standard file upload (split ZIPs ≤ 3 MB)** |
| Folder structure | Flat per branch | **Hierarchical: `full-backup/` + `incremental-backup/`** |
| Complexity | High (branch mgmt, blob encoding) | **Low (folder-based, no branch ops)** |

---

## 2. ZIP Splitting Strategy

All backup ZIPs are split into **≤ 3 MB** chunks before upload.

### 2.1 Splitting Rules

- Maximum chunk size: **3 MB** (3,145,728 bytes)
- Naming convention: `backup.zip.001`, `backup.zip.002`, `backup.zip.003`, …
- A manifest file (`manifest.json`) accompanies each set of chunks

### 2.2 Manifest File

```json
{
  "type": "full",
  "sequence": 1,
  "label": "001_15-Mar-2026",
  "createdAt": "2026-03-15T14:30:00Z",
  "totalSize": 8945632,
  "chunkSize": 3145728,
  "chunks": [
    { "file": "backup.zip.001", "size": 3145728, "sha256": "abc123..." },
    { "file": "backup.zip.002", "size": 3145728, "sha256": "def456..." },
    { "file": "backup.zip.003", "size": 2654176, "sha256": "ghi789..." }
  ]
}
```

### 2.3 Benefits

- No need for Git Blob API (base64 encoding doubles size)
- GitHub/GitLab file size limits are typically 100 MB; 3 MB chunks stay well under
- Simple sequential upload via standard REST API
- Easy to reassemble: `cat backup.zip.* > backup.zip`

---

## 3. Repository Folder Structure

All backups live on the **`main` branch** in a clean folder hierarchy:

```
repo-root/
├── full-backup/
│   ├── 001_15-Mar-2026/
│   │   ├── manifest.json
│   │   ├── backup.zip.001
│   │   ├── backup.zip.002
│   │   └── backup.zip.003
│   ├── 002_22-Mar-2026_weekly-checkpoint/
│   │   ├── manifest.json
│   │   ├── backup.zip.001
│   │   └── backup.zip.002
│   └── 003_01-Apr-2026/
│       ├── manifest.json
│       └── backup.zip.001
│
├── incremental-backup/
│   ├── 001_15-Mar-2026/              ← tied to full-backup/001_15-Mar-2026
│   │   ├── 001/
│   │   │   ├── manifest.json
│   │   │   ├── backup.zip.001
│   │   │   └── backup.zip.002
│   │   ├── 002/
│   │   │   ├── manifest.json
│   │   │   └── backup.zip.001
│   │   └── 003/
│   │       ├── manifest.json
│   │       └── backup.zip.001
│   └── 002_22-Mar-2026_weekly-checkpoint/
│       ├── 001/
│       │   ├── manifest.json
│       │   └── backup.zip.001
│       └── 002/
│           ├── manifest.json
│           └── backup.zip.001
│
└── README.md                         ← auto-generated repo description
```

### 3.1 Folder Naming Convention

**Format:** `{sequence}_{DD-MMM-YYYY}[_{label}]`

| Part | Description | Example |
|------|-------------|---------|
| `{sequence}` | Zero-padded 3-digit counter | `001`, `002`, `003` |
| `{DD-MMM-YYYY}` | Date of backup | `15-Mar-2026` |
| `{label}` | Optional user-provided name | `weekly-checkpoint` |

- If the backup is **manual** and the user provides a name → append as `_{label}`
- If the backup is **automatic** (WP-Cron) → no label suffix, just `{sequence}_{date}`

### 3.2 Incremental Sub-Folder Naming

Inside each `incremental-backup/{parent-folder}/`:

- Sub-folders are named `001`, `002`, `003`, … (zero-padded 3-digit sequence)
- Each sub-folder represents one incremental delta from the parent full backup
- The `manifest.json` inside each sub-folder links back to the parent full backup

---

## 4. Backup Lifecycle

### 4.1 Full Backup Flow

```
Trigger (Cron / Manual / Publish)
  │
  ▼
SnapshotOrchestrator.createFullSnapshot()
  │  ── Exports all WP tables + uploads/ into a single ZIP
  │
  ▼
ZipSplitter.split(fullBackup.zip, chunkSize=3MB)
  │  ── Produces: backup.zip.001, .002, .003, …
  │  ── Produces: manifest.json
  │
  ▼
CloudUploader.uploadToFolder("full-backup/{seq}_{date}[_{label}]/")
  │  ── Uploads manifest.json first
  │  ── Uploads each chunk sequentially
  │  ── Commits all files in a single Git commit
  │
  ▼
BackupHistory.recordFull(sequence, date, label, chunkCount, totalSize)
  │
  ▼
RotationManager.pruneExpiredFullBackups()
     ── Deletes oldest full-backup/ folder if count > retention
     ── Deletes corresponding incremental-backup/{same-folder}/ tree
```

### 4.2 Incremental Backup Flow

```
Trigger (Cron / Manual)
  │
  ▼
IncrementalBackup.execute()
  │  ── Detects changes since last backup (post_modified_gmt / filemtime)
  │  ── Exports only changed tables + files into a delta ZIP
  │
  ▼
ZipSplitter.split(incrementalBackup.zip, chunkSize=3MB)
  │
  ▼
Resolve parent: find latest full-backup folder name
  │
  ▼
Resolve next sequence: count existing sub-folders in incremental-backup/{parent}/
  │
  ▼
CloudUploader.uploadToFolder("incremental-backup/{parent}/{seq}/")
  │  ── Uploads manifest.json + chunks
  │  ── Single Git commit
  │
  ▼
BackupHistory.recordIncremental(parentSequence, incrementalSequence, …)
```

### 4.3 Restore Flow

```
User selects backup to restore (full or full + incrementals)
  │
  ▼
RestoreEngine.downloadBackup()
  │  ── Downloads manifest.json from target folder
  │  ── Downloads all chunks listed in manifest
  │  ── Verifies SHA-256 checksums
  │
  ▼
ZipReassembler.join(chunks[]) → fullBackup.zip
  │  ── cat backup.zip.001 + .002 + … → backup.zip
  │
  ▼
RestoreEngine.applyRestore(backup.zip)
  │  ── If restoring full only: apply full snapshot
  │  ── If restoring full + incrementals: apply full, then each incremental in sequence
```

---

## 5. Git Commit Strategy

Since we use **only the `main` branch**, commits are straightforward:

| Operation | Commit Message |
|-----------|---------------|
| Full backup | `backup: full #{seq} — {DD-MMM-YYYY}[ — {label}]` |
| Incremental | `backup: incremental #{parent-seq}/#{inc-seq} — {DD-MMM-YYYY}` |
| Rotation delete | `cleanup: remove full #{seq} + incrementals` |

### 5.1 Upload via Git Data API (Simplified)

Even though we avoid the blob system for large files, the actual upload uses the **Contents API** (single-file PUT):

```
PUT /repos/{owner}/{repo}/contents/{path}
{
  "message": "backup: full #001 — 15-Mar-2026",
  "content": "<base64 of chunk ≤ 3MB>"
}
```

Each chunk is ≤ 3 MB, so base64-encoded it becomes ≤ 4 MB — well within GitHub's **single-file API limit** of ~100 MB.

For **multiple files in one commit**, we still use the Git Trees API but with small files:
1. Create blobs for each chunk (≤ 4 MB base64 each)
2. Create a tree with all chunk paths
3. Create a commit pointing to the tree

---

## 6. Rotation / Retention

| Setting | Default | Description |
|---------|---------|-------------|
| `retentionCount` | `3` | Max full backups to keep |
| Auto-prune | On new full backup | If count > retention, delete oldest full + its incrementals |

### 6.1 Deletion Process

```
1. List full-backup/ folders, sorted by sequence
2. If count > retentionCount:
   a. Identify oldest folder(s) to remove
   b. Delete full-backup/{oldest}/  (all files)
   c. Delete incremental-backup/{oldest}/  (all sub-folders + files)
   d. Commit deletion: "cleanup: remove full #{seq} + incrementals"
   e. Update BackupHistory records (mark as deleted)
```

---

## 7. Provider Adaptations

| Provider | Upload Method | Delete Method |
|----------|--------------|---------------|
| GitHub | Contents API or Git Trees API | Contents API DELETE per file |
| GitLab | Repository Files API | Repository Files API DELETE |
| Google Drive | Resumable upload to folders | Drive API trash/delete |

### 7.1 Google Drive Folder Mapping

Google Drive mirrors the same folder hierarchy:

```
My Drive/
└── {app-name}-backups/
    ├── full-backup/
    │   └── 001_15-Mar-2026/
    │       ├── manifest.json
    │       ├── backup.zip.001
    │       └── ...
    └── incremental-backup/
        └── ...
```

---

## 8. Impact on Existing Code

### 8.1 Traits to Update

| Trait | Changes |
|-------|---------|
| `CloudStorageScheduleTrait` | Remove branch creation/deletion; use folder-based paths |
| `CloudStorageRestoreTrait` | Download by folder path instead of branch clone |
| `CloudStorageBranchTrait` | **Deprecate** — no longer needed for backup workflow |
| `CloudStorageUploadTrait` | Add split-upload logic with manifest generation |
| `CloudStorageHistoryTrait` | Add `chunkCount`, `totalSize`, `folderPath` fields |

### 8.2 New Components Needed

| Component | Purpose |
|-----------|---------|
| `ZipSplitter` (PHP) | Split ZIP into ≤ 3 MB chunks + generate manifest |
| `ZipReassembler` (PHP) | Reassemble chunks into original ZIP |
| `BackupFolderResolver` (PHP) | Generate folder names, resolve parent/child relationships |

### 8.3 Migration v21

Add columns to `CloudStorageBackupHistory`:
- `chunk_count` INT
- `total_size` BIGINT
- `folder_path` VARCHAR(500)
- Remove `branch_name` and `commit_sha` (no longer relevant)
