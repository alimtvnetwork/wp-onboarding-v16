# Cloud Backup Workflow — Complete Data Flow

> **Created:** 2026-03-15  
> **Updated:** 2026-03-15  
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
│  ├── handleScheduledIncrementalBackup()                         │
│  └── handleManualBackup($label)          ← user-named backup   │
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
│  ZipSplitter.php — Custom PHP fread/fwrite chunking             │
│  ├── Input:  backup.zip (any size)                              │
│  ├── Output: backup.zip.001, .002, .003 (each ≤ 3 MB)         │
│  └── Output: manifest.json (SHA-256 checksums)                  │
│                                                                 │
│  NOTE: Native ZipArchive has no split API. 7-Zip (`7z -v`)     │
│  requires a binary that isn't guaranteed on shared WP hosts.    │
│  The Linux `split` command also isn't portable. Our custom      │
│  fread/fwrite approach is the most portable — works on any      │
│  PHP host with zero external dependencies.                      │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  FOLDER RESOLVER                                 │
│  BackupFolderResolver.php                                       │
│  ├── Full:  "full-backup/001 - 15 Mar 2026 - W11/"             │
│  ├── Full:  "full-backup/002 - 22 Mar 2026 - W12 - my-snap/"  │
│  └── Incr:  "incremental-backup/001 - 15 Mar 2026 - W11/001/" │
│                                                                 │
│  ⚠ Separator between parts: SPACE-DASH-SPACE ( - )             │
│  ⚠ Date format: DD MMM YYYY (e.g., 15 Mar 2026)               │
│  ⚠ Order: {seq} - {date} - W{week}[ - {label}]                │
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

## 2. Naming Convention

**Separator between parts: ` - ` (space-dash-space).**  
**Date format: `DD MMM YYYY` (e.g., `15 Mar 2026`).**

### Full Backup Folder Format

```
{seq} - {DD MMM YYYY} - W{weekNumber}[ - {label}]
```

| Part | Description | Example |
|------|-------------|---------|
| `{seq}` | 3-digit zero-padded sequence | `001` |
| `{DD MMM YYYY}` | Date with spaces | `15 Mar 2026` |
| `W{weekNumber}` | ISO week of year, zero-padded (01–53) | `W01`, `W11` |
| `{label}` | Optional user-provided name (sanitized, hyphens only) | `my-snapshot` |

**Examples:**
- Auto backup: `001 - 15 Mar 2026 - W11`
- Manual backup with label: `002 - 22 Mar 2026 - W12 - pre-deployment`

### Incremental Folder Format (unchanged)

```
incremental-backup/{parent-full-folder}/{inc-seq}/
```

**Example:** `incremental-backup/001 - 15 Mar 2026 - W11/003/`

---

## 3. Backup Triggers

| Trigger | Handler | Label |
|---------|---------|-------|
| WP-Cron (scheduled) | `handleScheduledFullBackup()` | Auto-generated (none) |
| WP-Cron (scheduled) | `handleScheduledIncrementalBackup()` | Auto-generated (none) |
| Manual UI button | `handleManualBackup($label)` | User-provided name (e.g., "pre-deployment") |
| Go pre-publish hook | `executeFullBackupForAccount()` | `"pre-publish"` |

---

## 4. Full Backup Flow (Step by Step)

| Step | Component | Action | Output |
|------|-----------|--------|--------|
| 1 | Trigger | WP-Cron fires `riseup_cloud_full_backup` or user clicks manual | — |
| 2 | ScheduleTrait | `handleScheduledFullBackup()` or `handleManualBackup($label)` | Account[] |
| 3 | ScheduleTrait | `executeFullBackupForAccount($account, $label)` | — |
| 4 | HistoryTrait | Insert history row with status = `Pending` | historyId |
| 5 | SnapshotOrchestrator | `createFullSnapshot()` — exports all WP tables + `uploads/` | `/tmp/backup.zip` |
| 6 | **ZipSplitter** | `split($zipPath, $outputDir, Full, $seq, $label)` | `backup.zip.001`, `.002`, `manifest.json` |
| 7 | **BackupFolderResolver** | `buildFullPath($seq, $timestamp, $label)` | `full-backup/001 - 15 Mar 2026 - W11/` |
| 8 | UploadTrait | Upload `manifest.json` + all chunks to resolved folder path | RemoteUrl |
| 9 | HistoryTrait | Update status = `Success`, store `folder_path`, `chunk_count`, `total_size` | — |
| 10 | ScheduleTrait | `applyFullBackupRotation()` — prune if count > retention | Deleted old folders |

---

## 5. Incremental Backup Flow (Step by Step)

| Step | Component | Action | Output |
|------|-----------|--------|--------|
| 1 | Trigger | WP-Cron fires `riseup_cloud_incremental_backup` | — |
| 2 | ScheduleTrait | `handleScheduledIncrementalBackup()` loops enabled accounts | Account[] |
| 3 | ScheduleTrait | `executeIncrementalBackupForAccount($account)` | — |
| 4 | HistoryTrait | Find latest full backup for this account | latestFull |
| 5 | HistoryTrait | If no full exists → fall back to `executeFullBackupForAccount()` | — |
| 6 | IncrementalBackup | `execute()` — detect changes via `post_modified_gmt` / `filemtime()` | `/tmp/incr.zip` |
| 7 | **ZipSplitter** | `split($zipPath, $outputDir, Incremental, $incSeq, $label)` | Chunks + manifest |
| 8 | **BackupFolderResolver** | `buildIncrementalPath($parentFolder, $incSeq)` | `incremental-backup/001 - 15 Mar 2026 - W11/003/` |
| 9 | UploadTrait | Upload chunks to resolved incremental folder path | RemoteUrl |
| 10 | HistoryTrait | Record with `BaseFullBackupId` link | — |

---

## 6. Restore Flow (Step by Step)

| Step | Component | Action | Output |
|------|-----------|--------|--------|
| 1 | User | Selects backup to restore in UI | backupId |
| 2 | RestoreTrait | Download `manifest.json` from folder path | manifest |
| 3 | RestoreTrait | Download all chunks listed in manifest | chunk files |
| 4 | **ZipReassembler** | `reassemble($chunksDir, $outputPath)` — verify SHA-256, concatenate | `restored.zip` |
| 5 | RestoreEngine | Apply restored ZIP (import tables + files) | — |
| 6 | RestoreEngine | If full + incrementals: apply full first, then each incremental in order | — |

---

## 7. Repository Folder Structure (Git)

```
repo-root/
├── full-backup/
│   ├── 001 - 15 Mar 2026 - W11/
│   │   ├── manifest.json
│   │   ├── backup.zip.001        ← ≤ 3 MB
│   │   ├── backup.zip.002        ← ≤ 3 MB
│   │   └── backup.zip.003        ← ≤ 3 MB (remainder)
│   └── 002 - 22 Mar 2026 - W12 - pre-deployment/
│       ├── manifest.json
│       └── backup.zip.001
│
├── incremental-backup/
│   ├── 001 - 15 Mar 2026 - W11/           ← matches full-backup/001-*
│   │   ├── 001/
│   │   │   ├── manifest.json
│   │   │   └── backup.zip.001
│   │   ├── 002/
│   │   │   ├── manifest.json
│   │   │   └── backup.zip.001
│   │   └── 003/
│   │       ├── manifest.json
│   │       └── backup.zip.001
│   └── 002 - 22 Mar 2026 - W12 - pre-deployment/
│       └── 001/
│           ├── manifest.json
│           └── backup.zip.001
│
└── README.md
```

---

## 8. ZIP Splitting — Why Custom PHP?

| Approach | Pros | Cons | Verdict |
|----------|------|------|---------|
| **PHP `ZipArchive`** | Built-in | **No split API** — can only create/read ZIPs, not split them | ❌ Not possible |
| **7-Zip (`7z -v3m`)** | Native volume splitting | Requires `p7zip` binary installed on host; not available on shared hosting | ❌ Not portable |
| **Linux `split -b 3m`** | Simple shell command | Not available on Windows hosts; exec() may be disabled | ❌ Not portable |
| **Custom `fread`/`fwrite`** | Zero dependencies, works on any PHP host, full control over manifest | Slightly more code | ✅ **Chosen** |

The `ZipSplitter` class reads the source ZIP in 3 MB blocks via `fread()`, writes each chunk as `backup.zip.001`, `.002`, etc., and generates a `manifest.json` with SHA-256 checksums for integrity verification during restore.

---

## 9. Key Changes from Previous Architecture

| Before (v1) | After (v2) | Why |
|-------------|-----------|-----|
| `main` + `incremental/YYYY-Www` branches | **`main` branch only** | Simpler, no branch management overhead |
| Git Blob API (base64 whole ZIP) | **Split ≤ 3 MB chunks** via Contents API | Avoids blob size issues, stays under API limits |
| Branch-based rotation | **Folder-based rotation** | Delete folder = delete backup, cleaner |
| `commit_sha` + `branch_name` in history | **`folder_path` + `chunk_count` + `total_size`** | Matches folder-based approach |
| Hyphen separators (`001-W11-15-Mar-2026`) | **Space-dash-space (`001 - 15 Mar 2026 - W11`)** | Readable, user-requested format |
| Date: `DD-MMM-YYYY` | **Date: `DD MMM YYYY`** | Spaces instead of hyphens in date |
| Order: seq-week-date | **Order: seq-date-week** | Date before week number |
| No manual backups | **Manual with user label** | Users can name their own backups |

---

## 10. Implementation Status

### ✅ Implemented (Ready)

| Component | File | Notes |
|-----------|------|-------|
| `ZipSplitter` | `CloudStorage/ZipSplitter.php` | Needs no changes — splitting logic is correct |
| `ZipReassembler` | `CloudStorage/ZipReassembler.php` | Needs no changes — reassembly logic is correct |
| `BackupFolderResolver` | `CloudStorage/BackupFolderResolver.php` | ✅ Updated — new naming format applied |
| `CloudStorageScheduleTrait` | `Traits/CloudStorage/CloudStorageScheduleTrait.php` | Has stubs — needs real wiring |
| `CloudStorageHistoryTrait` | `Traits/CloudStorage/CloudStorageHistoryTrait.php` | CRUD exists — needs v21 columns |
| `CloudStorageRestoreTrait` | `Traits/CloudStorage/CloudStorageRestoreTrait.php` | Git-first restore exists — needs ZipReassembler |

### ⚠️ Needs Changes (Existing Files)

| File | What Changes |
|------|-------------|
| `CloudStorageScheduleTrait.php` | Wire `createFullBackupZip()` / `createIncrementalBackupZip()` stubs → call Orchestrator + ZipSplitter |
| `CloudStorageScheduleTrait.php` | Add `handleManualBackup($label)` method for user-named backups |
| `CloudStorageUploadTrait.php` | Implement `dispatchCloudUpload()` — upload split chunks via Contents API |
| `CloudStorageRestoreTrait.php` | Wire `ZipReassembler` into `restoreFromZip()` |
| `CloudStorageHistoryTrait.php` | Support new `folder_path`, `chunk_count`, `total_size` columns after v21 |
| `applyFullBackupRotation()` | Switch from branch deletion to folder-based pruning |

### ❌ Not Yet Created

| Component | File | Purpose |
|-----------|------|---------|
| Migration v21 | `Database/Traits/DatabaseMigrationsV21Trait.php` | Add `folder_path`, `chunk_count`, `total_size`; drop `branch_name`, `commit_sha` |
| Go pipeline wiring | `ServicePublishPipeline.go` | Wire `cloud_upload` stage + WebSocket progress events |
| Google Drive adaptation | Phase 5F | Folder hierarchy for GDrive (non-Git provider) |

---

## 11. Change Summary for `BackupFolderResolver.php`

Current implementation reflects the approved naming format:

```
Format:     {seq} - {DD MMM YYYY} - W{week}[ - {label}]
Separator:  " - " (space-dash-space)
Date:       DD MMM YYYY (spaces, not hyphens)
Order:      sequence → date → week → label

Regex:      /^(\d{3}) - (\d{2} [A-Za-z]{3} \d{4}) - W(\d{1,2})(?:\s-\s(.+))?$/

Example (auto):    001 - 15 Mar 2026 - W11
Example (manual):  002 - 22 Mar 2026 - W12 - pre-deployment
```
