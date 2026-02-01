# 21 - Wiki Revisions

## Overview

Version control system for Wiki pages, tracking all content changes with author attribution, diff generation, and rollback capabilities. Enables audit trails and content recovery.

---

## Dependencies

- `18-wiki-service.md` (WikiService integration)
- `06-entity-models.md` (WikiRevision entity)
- `44-audit-logging.md` (revision events logging)

---

## Functional Requirements

### 21.1 Revision Entity Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | Integer | Auto-increment PK |
| `wikiId` | Integer | FK to Wiki |
| `revisionNumber` | Integer | Sequential per wiki (1, 2, 3...) |
| `content` | Text | Full content snapshot |
| `contentHash` | String(64) | SHA-256 of content |
| `title` | String(255) | Title at this revision |
| `changeType` | Enum | CREATE, UPDATE, RESTORE |
| `changeSummary` | String(500)/null | Optional commit message |
| `authorId` | Integer | User who made change |
| `authorName` | String(255) | Denormalized for display |
| `bytesDelta` | Integer | Size change (+/-) |
| `createdAt` | DateTime | Revision timestamp |

### 21.2 Revision Service Methods

```
CLASS WikiRevisionService:
  
  METHODS:
    # Revision Creation
    + createRevision(wiki: Wiki, userId: int, summary: string|null): WikiRevision
    + shouldCreateRevision(oldContent: string, newContent: string): bool
    
    # Retrieval
    + getRevisions(wikiId: int, limit: int, offset: int): PaginatedResult
    + getRevision(revisionId: int): WikiRevision|null
    + getLatestRevision(wikiId: int): WikiRevision|null
    + getRevisionByNumber(wikiId: int, number: int): WikiRevision|null
    
    # Comparison
    + diff(fromRevision: WikiRevision, toRevision: WikiRevision): DiffResult
    + diffToLatest(revisionId: int): DiffResult
    + generateUnifiedDiff(from: string, to: string): string
    
    # Restoration
    + restore(revisionId: int, userId: int): Wiki
    + preview(revisionId: int): RenderedContent
    
    # Maintenance
    + pruneOldRevisions(wikiId: int, keepCount: int): int
    + getRevisionStats(wikiId: int): RevisionStats
```

### 21.3 Diff Algorithm

**Output Format**
```
DIFF_RESULT:
  - additions: array<{line: int, content: string}>
  - deletions: array<{line: int, content: string}>
  - modifications: array<{line: int, old: string, new: string}>
  - stats: {added: int, deleted: int, modified: int}
  - unifiedDiff: string (standard diff format)
```

**Diff Types**
- Line-by-line comparison
- Word-level highlighting within lines
- Moved content detection
- Unified diff export

---

## Business Rules

### 21.4 Revision Creation Rules

- [ ] New revision on every save (content or title change)
- [ ] Skip revision if content identical (hash match)
- [ ] Minimum 5-second gap between revisions (debounce rapid saves)
- [ ] Batch auto-saves into single revision
- [ ] Revision number always increments (no gaps)

### 21.5 Content Storage Strategy

**Option A: Full Content (Default)**
- Store complete content per revision
- Pros: Fast retrieval, simple restore
- Cons: More storage space

**Option B: Delta Storage (Optional)**
- Store diff from previous revision
- Reconstruct by applying deltas
- Pros: Less storage
- Cons: Slower retrieval, complex

### 21.6 Retention Policy

- [ ] Default: Keep last 50 revisions per wiki
- [ ] Configurable per wiki or globally
- [ ] Always keep first revision (creation)
- [ ] Always keep latest revision
- [ ] Prune runs on cron schedule
- [ ] Manual prune available to admins

### 21.7 Restore Behavior

- [ ] Restore creates NEW revision (non-destructive)
- [ ] Change type marked as RESTORE
- [ ] Summary auto-generated: "Restored from revision #X"
- [ ] Original revision preserved
- [ ] Audit log entry created

---

## UI Components

### 21.8 Revision History Panel

**List View**
- Revision number and date
- Author avatar and name
- Bytes changed (+/- indicator)
- Change summary (if provided)
- Quick actions: View, Compare, Restore

**Filters**
- Date range
- Author
- Change type

### 21.9 Diff Viewer

**Layout**
- Side-by-side comparison
- Unified diff toggle
- Line numbers
- Syntax highlighting preserved

**Visual Indicators**
- Green: Added lines
- Red: Deleted lines
- Yellow: Modified lines
- Gray: Context lines

**Controls**
- Previous/Next change navigation
- Expand/collapse context
- Copy diff to clipboard
- Compare any two revisions

### 21.10 Restore Confirmation

**Modal Content**
- Preview of content to restore
- Current vs restored comparison
- Warning about creating new revision
- Optional restore summary input
- Confirm/Cancel buttons

---

## Acceptance Criteria

### Revision Creation
- [ ] Revision created on save
- [ ] Duplicate content skipped
- [ ] Sequential numbering correct
- [ ] Author attribution accurate
- [ ] Bytes delta calculated correctly

### Diff Generation
- [ ] Line diff accurate
- [ ] Word-level highlighting works
- [ ] Unified diff format valid
- [ ] Large content diffed efficiently
- [ ] Empty content handled

### Restoration
- [ ] Restore creates new revision
- [ ] Content fully restored
- [ ] Original preserved
- [ ] Audit log updated
- [ ] Change type is RESTORE

### UI Components
- [ ] History panel lists revisions
- [ ] Pagination works
- [ ] Diff viewer renders correctly
- [ ] Side-by-side sync scrolls
- [ ] Restore confirmation flow works

### Retention
- [ ] Prune respects limit
- [ ] First revision kept
- [ ] Latest revision kept
- [ ] Cron prune runs correctly
- [ ] Stats reflect actual counts

### Performance
- [ ] Revision list loads < 200ms
- [ ] Diff computation < 500ms for 10k lines
- [ ] Restore completes < 1s
- [ ] Prune batched for large histories

---

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Revision not found | 404 with clear message |
| Restore conflict | Show current state, offer merge |
| Diff timeout | Partial diff with warning |
| Prune failure | Log error, retry next cycle |
| Hash collision | Log warning, create revision anyway |

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wikis/{id}/revisions` | List revisions |
| GET | `/revisions/{id}` | Get single revision |
| GET | `/revisions/{id}/diff` | Diff to previous |
| GET | `/revisions/{from}/diff/{to}` | Compare two revisions |
| POST | `/revisions/{id}/restore` | Restore revision |
| DELETE | `/wikis/{id}/revisions/prune` | Manual prune |

---

## Notes

- Consider implementing blame view (line-by-line author)
- Export revision history as JSON/CSV for audit
- Webhook on significant changes (optional)
- Revision content searchable in global search
