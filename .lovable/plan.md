
# Implementation Plan: Error Handling Verification and Bulk Scan Feature

## Summary

This plan addresses two main areas:
1. **Error Handling Gaps** - Fix missing Git/Build error codes and ensure comprehensive stack traces
2. **New Feature** - Add "Scan All Directories" bulk action for the Plugins page

---

## Part 1: Error Handling Analysis and Fixes

### Current State (Verified)

The stack trace implementation is **correctly implemented** in these locations:

- **apperror.New()** and **apperror.Wrap()** - Both automatically capture full stack traces via `captureStackTrace()`
- **Logger** - Automatically appends stack traces for ERROR and FATAL level messages
- **Process Output** - `LogProcessOutput()` and `LogProcessError()` capture stdout/stderr from external commands

### Issue Found: Missing Error Code Definitions

The following error codes are **used** in the codebase but **not defined** in `codes.go`:

| Code Used | Location | Status |
|-----------|----------|--------|
| `ErrGitNotRepo` | git/service.go:143 | Missing |
| `ErrGitCommand` | git/service.go:560 | Missing |
| `ErrBuildNotConfigured` | git/service.go:259 | Missing |
| `ErrBuildFailed` | git/service.go:307 | Missing |

### Fix Required

Add Git and Build error code definitions to `backend/pkg/apperror/codes.go`:

```text
// Git errors (E7xxx)
const (
    ErrGitNotRepo         = "E7001" // Directory is not a git repository
    ErrGitCommand         = "E7002" // Git command execution failed
    ErrGitPull            = "E7003" // Git pull failed
    ErrGitPush            = "E7004" // Git push failed
    ErrGitCommit          = "E7005" // Git commit failed
    ErrGitBranch          = "E7006" // Git branch operation failed
)

// Build errors (E8xxx)
const (
    ErrBuildNotConfigured = "E8001" // Build not configured for plugin
    ErrBuildFailed        = "E8002" // Build command failed
    ErrBuildTimeout       = "E8003" // Build command timed out
)
```

---

## Part 2: Add "Scan All Directories" Bulk Action

### New Backend Endpoint

Create a new endpoint that scans multiple paths and returns results:

**Endpoint:** `POST /api/v1/plugins/scan-directories`

**Request Body:**
```text
{
  "paths": ["/path/to/plugins", "/another/path"],
  "createDetection": true
}
```

**Response:**
```text
{
  "success": true,
  "data": {
    "scanned": 5,
    "detected": 3,
    "results": [
      { "path": "...", "isPlugin": true, "metadata": {...} },
      ...
    ]
  }
}
```

### Frontend Changes

1. **Update BulkActionsBar** - Add a "Scan Directories" action button
2. **Create ScanAllDirectoriesDialog** - Dialog to select base paths to scan
3. **Update Plugins.tsx** - Wire up the new bulk action

### UI Flow

1. User selects plugins in the list
2. Clicks "Scan All" in the bulk actions bar
3. A dialog opens showing:
   - Option to scan parent directories of selected plugins
   - Checkbox for "Create wp-plugin-detected.json files"
   - Progress indicator during scan
4. Results show newly detected plugins with option to register them

---

## Implementation Order

### Step 1: Add Missing Error Codes
- File: `backend/pkg/apperror/codes.go`
- Add Git errors (E7xxx) and Build errors (E8xxx)

### Step 2: Create Bulk Scan Endpoint
- File: `backend/internal/api/handlers/handlers.go`
  - Add `ScanDirectoriesPath` handler
- File: `backend/internal/api/router.go`
  - Register new route
- File: `backend/internal/api/handlers/adapters.go`
  - Add interface method if needed

### Step 3: Add Frontend API Method
- File: `src/lib/api.ts`
  - Add `scanDirectories(paths: string[], createDetection: boolean)` method

### Step 4: Update BulkActionsBar
- File: `src/components/plugins/BulkActionsBar.tsx`
  - Add "Scan All" button
  - Add callback prop `onScanAll`

### Step 5: Wire Up in Plugins Page
- File: `src/pages/Plugins.tsx`
  - Add handler for bulk scan action
  - Show toast/dialog with scan results

---

## Technical Details

### Error Code Categories (Updated)

| Range | Category |
|-------|----------|
| E1xxx | Configuration |
| E2xxx | Database |
| E3xxx | WordPress API |
| E4xxx | File System |
| E5xxx | Sync |
| E6xxx | Backup |
| E7xxx | Git (new) |
| E8xxx | Build (new) |
| E9xxx | General |

### Stack Trace Flow (Verified)

```text
Error occurs
    |
    v
apperror.New/Wrap called
    |
    +-- captureContext() -> file, line, function
    |
    +-- captureStackTrace() -> full call stack
    |
    v
AppError returned with StackTrace field populated
    |
    v
logger.Error() called (optional)
    |
    +-- Appends additional stack trace to log output
    |
    v
Written to error.log.txt with full trace
```

---

## Files to Modify

| File | Changes |
|------|---------|
| `backend/pkg/apperror/codes.go` | Add E7xxx and E8xxx error codes |
| `backend/internal/api/handlers/handlers.go` | Add ScanDirectoriesPath handler |
| `backend/internal/api/router.go` | Register new route |
| `src/lib/api.ts` | Add scanDirectories method |
| `src/components/plugins/BulkActionsBar.tsx` | Add Scan All button |
| `src/pages/Plugins.tsx` | Wire up bulk scan functionality |


## Additional Strict Instructions

Below, you will see, uh, error log or error report from the, uh, frontend, uh, me- m-model. Uh, however, if you see the backend error, it's quite confusing. I mean, it does not tell what happened. And I'm not sure how this log is created because the format does not match, as we say it should be. And you can see, like it says, like, "Fail to upload plugin," uh, something like this. Okay? So first of all, rise up, uh, it should be together, and up should be lowercase. That's the first thing. Second, I asked you explicitly not to write this type of log, but the log needs to be meaningful. That means the exact file you try to upload. And what is the stack trace? That means when you try to upload there, what is the error that the server has given? It, it shouldn't be just 500. It should give you some stack trace from there. So that should be printed there. You are not following that. Also, the main root cause here, I think when you zipped it, um, z- zip file to upload, so when the first zip was done, it didn't also mention like what is the folder structure inside the zip. Because it could have been, uh, that either we have the root folder or don't have the root folder. Either one of these is causing the error. Um, but in this case, I do see that the error is uploading the file. Upload- uploading is the issue. Now, if the uploading is the issue, then my, my better knowledge says that you should not name the file like this. So, always name the file like a slug, um, and do not add the date or time. Just add the slug dot zip and upload. If you follow this, follow these patterns, what I'm saying, I hope you will get out of the issues. And in the future try to add more logs, uh, why things happens, how things happens. If these are there, then it's easier for you to solve the issue as well. Also, the UI on the, on the model for the, uh, for the stack section is not very good. I mean, the error location is almost hidden. So there should be, uh, let's say scroll. We could have a vertical scroll for, uh, both cases because the, uh, error model should be fixed if more things comes up, which we should be able to scroll through and see like what is the issue. So th- these are, uh, on top of my head. So fix the rise up naming and add more details. I think this, this will solve the issue. And also add line numbers. So when you are getting a, I mean, error, you are not mentioning the line number, file path, things like that. These are absolutely missing. Don't do that. Please don't do that. It has no value. Okay? You are not making something that has a value. The log needs to have this information. So based on that, please update your memory. Please update the specs that this is how the error needs to be written. Do you understand? Do you have any question, confusion?

The backend error log should be like 

[v1.x 2026-02-05 24:23] [package] Building package... [INFO] [exact file path : line number] 

instead of 
[2026-02-05T00:05:40.210Z] [INFO] [package] Building package...

Can you please fix it and prioritize it????


## Error Report

**App:** WP Plugin Publish v1.19.4
**Git Commit:** dev
**Build Time:** 2026-02-04T19:30:00Z

**ID:** 1770249955175-qt8nq5wa0
**Code:** E9001
**Level:** error
**Timestamp:** 2026-02-05T00:05:55.174Z

### Trigger Context
**Component:** PublishProgressDialog
**Action:** publish_failed
**Source:** PublishProgressDialog.onComplete

### Invocation Chain
```
PublishProgressDialog.onComplete
  └─ onClick (index-BuUPtVUG.js:625)
    └─ Wk (index-BuUPtVUG.js:37)
      └─ Hk (index-BuUPtVUG.js:37)
        └─ px (index-BuUPtVUG.js:37)
          └─ L0 (index-BuUPtVUG.js:37)
            └─ uh (index-BuUPtVUG.js:40)
```


### Message
[E3009] failed to upload plugin via uploader helper: upload plugin via Rise Up Uploader: status 500

### Details
Failed during stage: Uploading to Site

### Request
**POST** /plugins/3/sites/1/publish


### Backend Execution Logs
```
[2026-02-05T00:05:40.215Z] [INFO] [init] Starting publish for Category Generator to Atto Property Demo
[2026-02-05T00:05:40.215Z] [INFO] [init] Starting publish for Category Generator to Atto Property Demo
[2026-02-05T00:05:40.209Z] [INFO] [backup] Starting publish...
[2026-02-05T00:05:40.210Z] [INFO] [connect] Connecting to WordPress: https://demoat.attoproperty.com.au
[2026-02-05T00:05:40.210Z] [INFO] [package] Building package...
[2026-02-05T00:05:40.210Z] [INFO] [package] Packaging plugin from: D:\wp-work\riseup-asia\category-forge\wordpress-plugin\category-generator
[2026-02-05T00:05:40.210Z] [INFO] [package] Creating full ZIP with ~0 files
[2026-02-05T00:05:40.209Z] [INFO] [backup] Starting publish...
[2026-02-05T00:05:40.210Z] [INFO] [connect] Connecting to WordPress: https://demoat.attoproperty.com.au
[2026-02-05T00:05:40.210Z] [INFO] [package] Building package...
[2026-02-05T00:05:40.210Z] [INFO] [package] Packaging plugin from: D:\wp-work\riseup-asia\category-forge\wordpress-plugin\category-generator
[2026-02-05T00:05:40.210Z] [INFO] [package] Creating full ZIP with ~0 files
[2026-02-05T00:05:40.224Z] [INFO] [package] ZIP created: Category Generator-1770249940.zip (143386 bytes)
[2026-02-05T00:05:40.224Z] [INFO] [package] ZIP created: Category Generator-1770249940.zip (143386 bytes)
[2026-02-05T00:05:40.224Z] [INFO] [upload] Uploading to WordPress...
[2026-02-05T00:05:40.224Z] [INFO] [upload] Uploading to https://demoat.attoproperty.com.au as plugin: category-generator
[2026-02-05T00:05:40.224Z] [INFO] [upload] Uploading to WordPress...
[2026-02-05T00:05:40.224Z] [INFO] [upload] Uploading to https://demoat.attoproperty.com.au as plugin: category-generator
[2026-02-05T00:05:48.428Z] [ERROR] [upload] Upload failed: [E3009] failed to upload plugin via uploader helper: upload plugin via Rise Up Uploader: status 500
[2026-02-05T00:05:48.428Z] [ERROR] [upload] Upload failed: [E3009] failed to upload plugin via uploader helper: upload plugin via Rise Up Uploader: status 500
[2026-02-05T00:05:48.427Z] [ERROR] [complete] Publish failed: [E3009] failed to upload plugin via uploader helper: upload plugin via Rise Up Uploader: status 500
[2026-02-05T00:05:48.427Z] [ERROR] [complete] Publish failed: [E3009] failed to upload plugin via uploader helper: upload plugin via Rise Up Uploader: status 500
[2026-02-05T00:05:48.429Z] [ERROR] [failed] [E3009] failed to upload plugin via uploader helper: upload plugin via Rise Up Uploader: status 500
[2026-02-05T00:05:48.429Z] [DEBUG] [cleanup] Removing temp ZIP: .temp\Category Generator-1770249940.zip
[2026-02-05T00:05:48.429Z] [ERROR] [failed] [E3009] failed to upload plugin via uploader helper: upload plugin via Rise Up Uploader: status 500
[2026-02-05T00:05:48.429Z] [DEBUG] [cleanup] Removing temp ZIP: .temp\Category Generator-1770249940.zip
```


### Parsed Stack Frames
| # | Function | File | Line |
|---|----------|------|------|
| 1 | onClick | index-BuUPtVUG.js | 625 |
| 2 | Wk | index-BuUPtVUG.js | 37 |
| 3 | Hk | index-BuUPtVUG.js | 37 |
| 4 | px | index-BuUPtVUG.js | 37 |
| 5 | L0 | index-BuUPtVUG.js | 37 |
| 6 | anonymous | index-BuUPtVUG.js | 37 |
| 7 | uh | index-BuUPtVUG.js | 40 |

### Location
`index-BuUPtVUG.js:625` (onClick)

### Context
```json
{
  "source": "PublishProgressDialog.onComplete",
  "triggerComponent": "PublishProgressDialog",
  "triggerAction": "publish_failed",
  "pluginId": 3,
  "pluginName": "Category Generator",
  "siteId": 1,
  "siteName": "Atto Property Demo",
  "failedStage": "upload",
  "stageStatuses": [
    {
      "name": "backup",
      "status": "success"
    },
    {
      "name": "package",
      "status": "success",
      "message": "Building package..."
    },
    {
      "name": "upload",
      "status": "error",
      "message": "Uploading to WordPress..."
    },
    {
      "name": "activate",
      "status": "pending"
    }
  ],
  "backendLogFiles": {
    "log": "data/errors/log.txt",
    "errorLog": "data/errors/error.log.txt"
  }
}
```

### Frontend Stack Trace
```
    at onClick (http://localhost:8080/assets/index-BuUPtVUG.js:625:7221)
    at Object.Bk (http://localhost:8080/assets/index-BuUPtVUG.js:37:9855)
    at Wk (http://localhost:8080/assets/index-BuUPtVUG.js:37:10009)
    at Hk (http://localhost:8080/assets/index-BuUPtVUG.js:37:10066)
    at px (http://localhost:8080/assets/index-BuUPtVUG.js:37:31446)
    at L0 (http://localhost:8080/assets/index-BuUPtVUG.js:37:31863)
    at http://localhost:8080/assets/index-BuUPtVUG.js:37:36776
    at uh (http://localhost:8080/assets/index-BuUPtVUG.js:40:36935)
```

---
*Generated by WP Plugin Publish Error Reporter*
