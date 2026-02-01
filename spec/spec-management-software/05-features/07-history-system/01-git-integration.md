# Git Integration

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Summary

Git integration specification for automated version control, including auto-commit, auto-push, and commit message generation.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Git Service                             │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │ CommitQueue │──│ GitExecutor │──│ RemotePushWorker   │ │
│  │  (Debounce) │  │ (go-git)    │  │ (Background)       │ │
│  └─────────────┘  └─────────────┘  └─────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
                    ┌───────────────┐
                    │  System Git   │
                    │  Repository   │
                    └───────────────┘
```

---

## Configuration

### Config Keys

| Key | Default | Description |
|-----|---------|-------------|
| `git_enabled` | `true` | Enable Git integration |
| `auto_commit_enabled` | `true` | Auto-commit on file changes |
| `auto_push_enabled` | `true` | Auto-push after commits |
| `commit_debounce_ms` | `2000` | Debounce delay before commit |
| `push_retry_count` | `3` | Retry attempts for failed push |
| `push_retry_delay_ms` | `5000` | Delay between push retries |
| `git_author_name` | `Spec Manager` | Commit author name |
| `git_author_email` | `spec@localhost` | Commit author email |

---

## Commit Message Format

### Standard Format

```
[Spec] {Action}: {Target}

{Optional Details}

Files: {count}
User: {username}
```

### Action Types

| Action | Trigger | Commit Required |
|--------|---------|-----------------|
| `Created` | New file or folder | Yes |
| `Updated` | File content changed | Yes |
| `Renamed` | File or folder renamed | **Immediate** |
| `Moved` | File or folder relocated | **Immediate** |
| `Deleted` | File or folder removed | Yes |
| `Snapshot` | Snapshot created | Yes |
| `Restored` | Snapshot restored | Yes |
| `Bulk` | Multiple operations batched | Yes |

> **CRITICAL:** Rename and Move operations trigger **immediate commits** (bypass debounce) to ensure full reversibility via `git revert`. This allows users to undo any file relocation through the history system.

### Examples

**Single File Created:**
```
[Spec] Created: general-spec/01-foundation/03-new-standard.md

Files: 1
User: johndoe
```

**File Renamed:**
```
[Spec] Renamed: old-name.md → new-name.md

Path: wp-plugin/exam-manager/ideas/
Files: 1
User: johndoe
```

**Bulk Operations:**
```
[Spec] Bulk: 5 files updated

- general-spec/00-overview.md
- general-spec/01-foundation/01-coding-standards.md
- general-spec/01-foundation/02-error-management.md
- wp-plugin/exam-manager/00-overview.md
- wp-plugin/exam-manager/01-admin-backend/01-overview.md

Files: 5
User: johndoe
```

**Snapshot Restored:**
```
[Spec] Restored: V03-2026-01-25-143022

Project: exam-manager
Snapshot: V03-2026-01-25-143022
Files: 47
User: johndoe
```

---

## Commit Queue

### Purpose

Debounce rapid file changes to avoid excessive commits.

### Flow

```
┌──────────┐     ┌─────────────┐     ┌─────────────┐     ┌──────────┐
│ File     │────▶│ Add to      │────▶│ Debounce    │────▶│ Execute  │
│ Changed  │     │ Queue       │     │ Timer       │     │ Commit   │
└──────────┘     └─────────────┘     └─────────────┘     └──────────┘
                        │
                        ▼
                 ┌─────────────┐
                 │ Merge Same  │
                 │ File Events │
                 └─────────────┘
```

### Queue Entry Structure

```go
type CommitQueueEntry struct {
    FilePath    string
    Action      string        // created, updated, renamed, moved, deleted
    OldPath     string        // for renames/moves
    Timestamp   time.Time
    UserId      string
    Username    string
}
```

### Debounce Logic

```go
const debounceDelay = 2 * time.Second

type CommitQueue struct {
    entries   map[string]*CommitQueueEntry
    timer     *time.Timer
    mutex     sync.Mutex
}

func (q *CommitQueue) Add(entry CommitQueueEntry) {
    q.mutex.Lock()
    defer q.mutex.Unlock()
    
    // Merge or replace existing entry for same path
    q.entries[entry.FilePath] = &entry
    
    // Reset debounce timer
    if q.timer != nil {
        q.timer.Stop()
    }
    q.timer = time.AfterFunc(debounceDelay, q.flush)
}

func (q *CommitQueue) flush() {
    q.mutex.Lock()
    entries := q.entries
    q.entries = make(map[string]*CommitQueueEntry)
    q.mutex.Unlock()
    
    if len(entries) > 0 {
        gitService.Commit(entries)
    }
}
```

---

## Git Executor

### Operations

#### Stage Files

```go
func (g *GitService) StageFiles(paths []string) error {
    worktree, err := g.repo.Worktree()
    if err != nil {
        return fmt.Errorf("ERR_6001: %w", err)
    }
    
    for _, path := range paths {
        _, err := worktree.Add(path)
        if err != nil {
            log.Error("Failed to stage file",
                "path", path,
                "error", err)
            // Continue with other files
        }
    }
    return nil
}
```

#### Commit Changes

```go
func (g *GitService) Commit(entries map[string]*CommitQueueEntry) error {
    // 1. Stage all files
    paths := make([]string, 0, len(entries))
    for path := range entries {
        paths = append(paths, path)
    }
    
    if err := g.StageFiles(paths); err != nil {
        return err
    }
    
    // 2. Generate commit message
    message := g.generateCommitMessage(entries)
    
    // 3. Execute commit
    worktree, _ := g.repo.Worktree()
    commit, err := worktree.Commit(message, &git.CommitOptions{
        Author: &object.Signature{
            Name:  g.config.AuthorName,
            Email: g.config.AuthorEmail,
            When:  time.Now(),
        },
    })
    
    if err != nil {
        log.Error("Git commit failed", "error", err)
        return fmt.Errorf("ERR_6001: %w", err)
    }
    
    log.Info("Git commit successful",
        "hash", commit.String()[:8],
        "files", len(entries))
    
    // 4. Trigger async push
    if g.config.AutoPushEnabled {
        go g.pushWithRetry()
    }
    
    return nil
}
```

#### Push to Remote

```go
func (g *GitService) pushWithRetry() {
    for attempt := 1; attempt <= g.config.PushRetryCount; attempt++ {
        err := g.repo.Push(&git.PushOptions{
            RemoteName: "origin",
            Auth:       g.getAuth(),
        })
        
        if err == nil {
            log.Info("Git push successful")
            return
        }
        
        if err == git.NoErrAlreadyUpToDate {
            return // Nothing to push
        }
        
        log.Warn("Git push failed, retrying",
            "attempt", attempt,
            "error", err)
        
        if attempt < g.config.PushRetryCount {
            time.Sleep(time.Duration(g.config.PushRetryDelayMs) * time.Millisecond)
        }
    }
    
    log.Error("Git push failed after retries",
        "attempts", g.config.PushRetryCount)
    // Store failed push for manual retry
    g.recordFailedPush()
}
```

---

## Commit Message Generator

```go
func (g *GitService) generateCommitMessage(entries map[string]*CommitQueueEntry) string {
    var sb strings.Builder
    
    // Determine action summary
    if len(entries) == 1 {
        for path, entry := range entries {
            action := capitalizeFirst(entry.Action)
            if entry.Action == "renamed" && entry.OldPath != "" {
                oldName := filepath.Base(entry.OldPath)
                newName := filepath.Base(path)
                sb.WriteString(fmt.Sprintf("[Spec] Renamed: %s → %s\n\n", oldName, newName))
                sb.WriteString(fmt.Sprintf("Path: %s\n", filepath.Dir(path)))
            } else {
                sb.WriteString(fmt.Sprintf("[Spec] %s: %s\n\n", action, path))
            }
            sb.WriteString(fmt.Sprintf("Files: 1\n"))
            sb.WriteString(fmt.Sprintf("User: %s\n", entry.Username))
        }
    } else {
        // Bulk operation
        sb.WriteString(fmt.Sprintf("[Spec] Bulk: %d files updated\n\n", len(entries)))
        
        // List files (max 10)
        count := 0
        for path := range entries {
            if count >= 10 {
                sb.WriteString(fmt.Sprintf("- ... and %d more\n", len(entries)-10))
                break
            }
            sb.WriteString(fmt.Sprintf("- %s\n", path))
            count++
        }
        
        sb.WriteString(fmt.Sprintf("\nFiles: %d\n", len(entries)))
        // Get username from first entry
        for _, entry := range entries {
            sb.WriteString(fmt.Sprintf("User: %s\n", entry.Username))
            break
        }
    }
    
    return sb.String()
}
```

---

## Authentication

### SSH Key Auth

```go
func (g *GitService) getSSHAuth() transport.AuthMethod {
    homeDir, _ := os.UserHomeDir()
    keyPath := filepath.Join(homeDir, ".ssh", "id_rsa")
    
    publicKeys, err := ssh.NewPublicKeysFromFile("git", keyPath, "")
    if err != nil {
        log.Error("Failed to load SSH key", "error", err)
        return nil
    }
    return publicKeys
}
```

### HTTPS Token Auth

```go
func (g *GitService) getHTTPSAuth() transport.AuthMethod {
    token := g.config.GitToken
    if token == "" {
        return nil
    }
    return &http.BasicAuth{
        Username: "git",
        Password: token,
    }
}
```

---

## API Endpoints

### GET /git/status

Get current Git repository status.

**Response:**
```json
{
  "success": true,
  "data": {
    "branch": "main",
    "clean": false,
    "staged": ["path/to/file.md"],
    "modified": ["path/to/another.md"],
    "untracked": [],
    "ahead": 2,
    "behind": 0,
    "lastCommit": {
      "hash": "abc123de",
      "message": "[Spec] Updated: overview.md",
      "author": "johndoe",
      "timestamp": "2026-01-27T14:30:00Z"
    },
    "pendingPush": false
  }
}
```

### POST /git/commit

Manual commit trigger.

**Request:**
```json
{
  "message": "Optional custom message",
  "paths": ["path/to/file.md"]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "hash": "def456gh",
    "message": "[Spec] Manual commit",
    "filesCommitted": 1
  }
}
```

### POST /git/push

Manual push trigger.

**Response:**
```json
{
  "success": true,
  "data": {
    "pushed": true,
    "commits": 3,
    "branch": "main"
  }
}
```

### POST /git/pull

Pull latest changes from remote.

**Response:**
```json
{
  "success": true,
  "data": {
    "updated": true,
    "commits": 2,
    "filesChanged": 5
  }
}
```

---

## Error Handling

### Error Codes

| Code | Description | Recovery |
|------|-------------|----------|
| ERR_6001 | Commit failed | Log and notify user |
| ERR_6002 | Push failed | Retry with backoff |
| ERR_6003 | Repository not found | Check spec path config |
| ERR_6004 | Auth failed | Check credentials |
| ERR_6005 | Merge conflict | Require manual resolution |

### Conflict Detection

```go
func (g *GitService) hasConflicts() bool {
    worktree, err := g.repo.Worktree()
    if err != nil {
        return false
    }
    
    status, err := worktree.Status()
    if err != nil {
        return false
    }
    
    for _, s := range status {
        if s.Staging == git.Unmerged || s.Worktree == git.Unmerged {
            return true
        }
    }
    return false
}
```

---

## Logging

All Git operations log to `app.log`:

```
2026-01-27T14:30:00Z INFO  Git commit successful hash=abc123de files=3
2026-01-27T14:30:01Z INFO  Git push successful
2026-01-27T14:35:00Z WARN  Git push failed, retrying attempt=1 error="network timeout"
2026-01-27T14:35:05Z INFO  Git push successful
```

---

## Acceptance Criteria

### Commit Queue (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CQ-001 | File changes added to commit queue | Critical | Queue add test |
| CQ-002 | Debounce timer resets on new changes | Critical | Debounce test |
| CQ-003 | Queue flushes after debounce delay (default 2s) | Critical | Timer test |
| CQ-004 | Multiple changes to same file merged | High | Merge test |
| CQ-005 | Empty queue does not trigger commit | Medium | Empty queue test |

### Commit Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CO-001 | Files staged before commit | Critical | Stage test |
| CO-002 | Commit message follows [Spec] format | Critical | Message format test |
| CO-003 | Single file commit includes full path | High | Single file test |
| CO-004 | Bulk commit lists up to 10 files | High | Bulk message test |
| CO-005 | Commit author name/email from config | High | Author config test |
| CO-006 | Commit hash logged on success | Medium | Logging test |

### Push Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PO-001 | Auto-push triggered after commit if enabled | Critical | Auto-push test |
| PO-002 | Push retries on failure (configurable count) | High | Retry test |
| PO-003 | Retry delay between attempts (configurable) | High | Delay test |
| PO-004 | Failed push recorded for manual retry | High | Failed push tracking |
| PO-005 | NoErrAlreadyUpToDate handled gracefully | Medium | Up-to-date test |

### Pull Operations (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| PL-001 | POST /git/pull fetches and merges remote | Critical | Pull test |
| PL-002 | Conflict detection identifies unmerged files | Critical | Conflict test |
| PL-003 | ERR_6005 returned on merge conflict | Critical | Error code test |
| PL-004 | Files changed count returned in response | High | Response test |

### Authentication (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AU-001 | SSH key auth loads from ~/.ssh/id_rsa | High | SSH auth test |
| AU-002 | HTTPS token auth uses configured token | High | HTTPS auth test |
| AU-003 | ERR_6004 returned on auth failure | Critical | Auth error test |

### API Endpoints (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AE-001 | GET /git/status returns branch, clean, ahead/behind | Critical | Status test |
| AE-002 | POST /git/commit triggers manual commit | Critical | Manual commit test |
| AE-003 | POST /git/push triggers manual push | Critical | Manual push test |
| AE-004 | POST /git/pull pulls latest changes | Critical | Pull test |
| AE-005 | Last commit info included in status | High | Last commit test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | ERR_6001 for commit failure | Critical | Error code test |
| EH-002 | ERR_6002 for push failure | Critical | Error code test |
| EH-003 | ERR_6003 for repository not found | Critical | Error code test |
| EH-004 | ERR_6004 for auth failure | Critical | Error code test |
| EH-005 | ERR_6005 for merge conflict | Critical | Error code test |
| EH-006 | All errors logged with context | High | Logging test |

---

## Related Specs

- [History System Overview](./00-overview.md)
- [History System](./02-history-system.md)
- [File Operations](../02-file-management/01-file-operations.md)
- [General Spec: Logging](../../general-spec/02-systems/01-logging-system-systems.md)
