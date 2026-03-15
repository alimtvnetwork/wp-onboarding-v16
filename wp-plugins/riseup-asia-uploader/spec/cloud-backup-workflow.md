# Cloud Backup Workflow — Complete Data Flow

> **Created:** 2026-03-15  
> **Location:** `wp-plugins/riseup-asia-uploader/`  
> **Status:** Approved — ready for wiring

---

## 1. High-Level Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        TRIGGER LAYER                            │
│  WP-Cron (auto)  │  Manual UI click  │  Pre-publish hook (Go)  │
└────────┬─────────────────┬────────────────────┬─────────────────┘
         │                 │                    │
         ▼                 ▼                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                     SCHEDULE TRAIT                               │
│  CloudStorageScheduleTrait.php                                  │
│  ├── handleScheduledFullBackup()                                │
│  └── handleScheduledIncrementalBackup()                         │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                     SNAPSHOT LAYER                               │
│  SnapshotOrchestrator  →  Full ZIP (all tables + uploads/)     │
│  IncrementalBackup     →  Delta ZIP (changed since timestamp)  │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                     ZIP SPLITTER                                 │
│  ZipSplitter.php                                                │
│  ├── Input:  backup.zip (any size)                              │
│  ├── Output: backup.zip.001, .002, .003 (each ≤ 3 MB)         │
│  └── Output: manifest.json (SHA-256 checksums)                  │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  FOLDER RESOLVER                                 │
│  BackupFolderResolver.php                                       │
│  ├── Full:  "full-backup/001_15-Mar-2026/"                     │
│  └── Incr:  "incremental-backup/001_15-Mar-2026/001/"          │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  CLOUD UPLOAD                                    │
│  CloudStorageUploadTrait.php                                    │
│  ├── GitHub:  Contents API (PUT per chunk, ≤ 4 MB base64)      │
│  ├── GitLab:  Repository Files API (PUT per chunk)             │
│  └── GDrive:  Resumable upload (chunks to folder)              │
│  All on `main` branch — single commit per backup               │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  HISTORY + ROTATION                              │
│  CloudStorageHistoryTrait.php                                   │
│  ├── Record: sequence, folder_path, chunk_count, total_size    │
│  └── Rotate: if full count > retention, delete oldest + incrs  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Full Backup Flow (Step by Step)

| Step | Component | Action | Output |
|------|-----------|--------|--------|
| 1 | Trigger | WP-Cron fires `riseup_cloud_full_backup` | — |
| 2 | ScheduleTrait | `handleScheduledFullBackup()` loops enabled accounts | Account[] |
| 3 | ScheduleTrait | `executeFullBackupForAccount($account)` | — |
| 4 | HistoryTrait | Insert history row with status = `Pending` | historyId |
| 5 | SnapshotOrchestrator | `createFullSnapshot()` — exports all WP tables + `uploads/` | `/tmp/backup.zip` |
| 6 | **ZipSplitter** | `split($zipPath, $outputDir, Full, $seq, $label)` | `backup.zip.001`, `.002`, `manifest.json` |
| 7 | **BackupFolderResolver** | `buildFullPath($seq, $timestamp, $label)` | `full-backup/001_15-Mar-2026/` |
| 8 | UploadTrait | Upload `manifest.json` + all chunks to resolved folder path | RemoteUrl |
| 9 | HistoryTrait | Update status = `Success`, store `folder_path`, `chunk_count`, `total_size` | — |
| 10 | ScheduleTrait | `applyFullBackupRotation()` — prune if count > retention | Deleted old folders |

---

## 3. Incremental Backup Flow (Step by Step)

| Step | Component | Action | Output |
|------|-----------|--------|--------|
| 1 | Trigger | WP-Cron fires `riseup_cloud_incremental_backup` | — |
| 2 | ScheduleTrait | `handleScheduledIncrementalBackup()` loops enabled accounts | Account[] |
| 3 | ScheduleTrait | `executeIncrementalBackupForAccount($account)` | — |
| 4 | HistoryTrait | Find latest full backup for this account | latestFull |
| 5 | HistoryTrait | If no full exists → fall back to `executeFullBackupForAccount()` | — |
| 6 | IncrementalBackup | `execute()` — detect changes via `post_modified_gmt` / `filemtime()` | `/tmp/incr.zip` |
| 7 | **ZipSplitter** | `split($zipPath, $outputDir, Incremental, $incSeq, $label)` | Chunks + manifest |
| 8 | **BackupFolderResolver** | `buildIncrementalPath($parentFolder, $incSeq)` | `incremental-backup/001_15-Mar-2026/003/` |
| 9 | UploadTrait | Upload chunks to resolved incremental folder path | RemoteUrl |
| 10 | HistoryTrait | Record with `BaseFullBackupId` link | — |

---

## 4. Restore Flow (Step by Step)

| Step | Component | Action | Output |
|------|-----------|--------|--------|
| 1 | User | Selects backup to restore in UI | backupId |
| 2 | RestoreTrait | Download `manifest.json` from folder path | manifest |
| 3 | RestoreTrait | Download all chunks listed in manifest | chunk files |
| 4 | **ZipReassembler** | `reassemble($chunksDir, $outputPath)` — verify SHA-256, concatenate | `restored.zip` |
| 5 | RestoreEngine | Apply restored ZIP (import tables + files) | — |
| 6 | RestoreEngine | If full + incrementals: apply full first, then each incremental in order | — |

---

## 5. Repository Folder Structure (Git)

```
repo-root/
├── full-backup/
│   ├── 001_15-Mar-2026/
│   │   ├── manifest.json
│   │   ├── backup.zip.001        ← ≤ 3 MB
│   │   ├── backup.zip.002        ← ≤ 3 MB
│   │   └── backup.zip.003        ← ≤ 3 MB (remainder)
│   └── 002_22-Mar-2026_weekly/
│       ├── manifest.json
│       └── backup.zip.001
│
├── incremental-backup/
│   ├── 001_15-Mar-2026/           ← matches full-backup/001_*
│   │   ├── 001/
│   │   │   ├── manifest.json
│   │   │   └── backup.zip.001
│   │   ├── 002/
│   │   │   ├── manifest.json
│   │   │   └── backup.zip.001
│   │   └── 003/
│   │       ├── manifest.json
│   │       └── backup.zip.001
│   └── 002_22-Mar-2026_weekly/
│       └── 001/
│           ├── manifest.json
│           └── backup.zip.001
│
└── README.md
```

---

## 6. Key Changes from Previous Architecture

| Before (v1) | After (v2) | Why |
|-------------|-----------|-----|
| `main` + `incremental/YYYY-Www` branches | **`main` branch only** | Simpler, no branch management overhead |
| Git Blob API (base64 whole ZIP) | **Split ≤ 3 MB chunks** via Contents API | Avoids blob size issues, stays under API limits |
| Branch-based rotation | **Folder-based rotation** | Delete folder = delete backup, cleaner |
| `commit_sha` + `branch_name` in history | **`folder_path` + `chunk_count` + `total_size`** | Matches folder-based approach |

---

## 7. Components Involved

| Component | File | Status |
|-----------|------|--------|
| `ZipSplitter` | `CloudStorage/ZipSplitter.php` | ✅ Implemented |
| `ZipReassembler` | `CloudStorage/ZipReassembler.php` | ✅ Implemented |
| `BackupFolderResolver` | `CloudStorage/BackupFolderResolver.php` | ✅ Implemented |
| `CloudStorageScheduleTrait` | `Traits/CloudStorage/CloudStorageScheduleTrait.php` | ⚠️ Needs wiring (stubs remain) |
| `CloudStorageUploadTrait` | `Traits/CloudStorage/CloudStorageUploadTrait.php` | ⚠️ Needs split-upload logic |
| `CloudStorageRestoreTrait` | `Traits/CloudStorage/CloudStorageRestoreTrait.php` | ⚠️ Needs ZipReassembler wiring |
| `CloudStorageHistoryTrait` | `Traits/CloudStorage/CloudStorageHistoryTrait.php` | ⚠️ Needs v21 migration columns |
| Migration v21 | `Database/Traits/DatabaseMigrationsV21Trait.php` | ❌ Not yet created |

---

## 8. What Needs to Happen Next

1. **Wire stubs** — Replace `createFullBackupZip()` and `createIncrementalBackupZip()` abstract stubs with real implementations that call `SnapshotOrchestrator` + `ZipSplitter`
2. **Update `dispatchCloudUpload()`** — Upload split chunks via Contents API using `BackupFolderResolver` paths
3. **Update rotation** — Delete by folder path instead of branch
4. **Migration v21** — Add `chunk_count`, `total_size`, `folder_path`; drop `branch_name`, `commit_sha`
5. **Wire restore** — Use `ZipReassembler` in `CloudStorageRestoreTrait`
