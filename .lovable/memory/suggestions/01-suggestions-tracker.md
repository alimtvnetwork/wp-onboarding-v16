# Suggestions Tracker

> **Location:** `.lovable/memory/suggestions/01-suggestions-tracker.md`  
> **Purpose:** Track AI suggestions for improvements (consolidated single file)  
> **Updated:** 2026-02-24

---

## Active Suggestions (Open)

| ID | Created | Priority | Source | Status | Description |
|----|---------|----------|--------|--------|-------------|

*No open suggestions.*

---

## Completed Suggestions

| ID | Title | Completed | Notes |
|----|-------|-----------|-------|
| S-001 | WordPress API Error Examples | 2026-02-09 | 6 error types in `10-wp-rest-client.md` |
| S-002 | fsnotify Platform Differences | 2026-02-02 | Replaced with hybrid watcher mode |
| S-003 | Specify Hash Algorithm | 2026-02-02 | MD5 implemented in scanner.go |
| S-004 | Partial Publish Recovery | 2026-02-09 | 4 strategies in `08-publish-service.md` |
| S-005 | WebSocket Reconnection Recovery | 2026-02-09 | Broad invalidation on reconnect in `useWebSocket.ts` |
| S-006 | Verify Go Backend Compiles | 2026-02-05 | Confirmed working |
| S-007 | Verify React Frontend Builds | 2026-02-05 | Confirmed working |
| S-008 | Implement Site Service | 2026-02-02 | Full CRUD handlers |
| S-009 | Implement Publish Service | 2026-02-02 | Full pipeline |
| S-010 | WebSocket Real-time Sync | 2026-02-02 | Broadcasting helpers added |
| S-011 | E2E Testing Framework | 2026-02-02 | 20 test cases, Go runner, React UI |
| S-012 | Error Detail Modal | 2026-02-02 | Developer debug info with copy feature |
| S-013 | DRY Phase 7 — PHP Snapshot Factory | 2026-02-09 | `RiseupSnapshotFactory` with lazy singletons |
| S-014 | DRY Phase 8 — PHP Logger Consolidation | 2026-02-09 | `prepare_context()` method |
| S-015 | DRY Phase 9 — GlobalErrorModal Decomposition | 2026-02-09 | Split into 7 sub-components |
| S-016 | DRY Phase 10 — Envelope Schema Alignment | 2026-02-09 | `envelope.schema.json` v1.0.0 |
| S-017 | Post-Deploy Version Verification Pass | 2026-02-09 | Auto version drift detection via force-sync |
| S-018 | Remove Vestigial `pnpmVirtualStorePath` Config Key | 2026-02-09 | Removed from `powershell.json` |
| S-019 | Fix Database/*.php R12 violations | 2026-02-22 | Fixed in Database.php, Orm.php, RootDb.php |
| S-020 | Fix ErrorHandling/*.php formatting | 2026-02-22 | All 4 files verified compliant |
| S-021 | Fix R12 + formatting in Plugin.php, Admin.php, FileLogger.php | 2026-02-23 | Already compliant |
| S-022 | Fix formatting in Templates/*.php | 2026-02-23 | All 5 templates — no violations |
| S-023 | Fix formatting in root files | 2026-02-23 | Both files fully compliant |
| S-024 | Deduplicate Database pagination constants | 2026-02-23 | Already uses PaginationConfigType |
| S-025 | Audit old enum value comparisons | 2026-02-23 | Zero hardcoded old comparisons |
| S-026 | Update TS enum string values to PascalCase | 2026-02-23 | 3 enums + 8 files converted |
| S-027 | Fix admin-errors.php template magic strings | 2026-02-23 | Already uses enums |
| S-028 | Update core-enum-inventory with LogColumnType | 2026-02-23 | Added LogColumnType (16 cases) |
| S-029 | Add ABSPATH guards to enum files | 2026-02-23 | All 53 files already guarded |
| S-030 | Add ABSPATH guards to Logging/ErrorHandling files | 2026-02-23 | All 13 files already guarded |
| S-031 | Fix ActivationHandler R12, R4, indentation | 2026-02-23 | Already resolved |
| S-032 | Remove dead loadDependencies() + redundant class_exists | 2026-02-23 | Already removed |
| S-033 | Expand DateHelper + replace raw date() calls | 2026-02-23 | 6 constants + 6 methods; 21 files updated |
| S-034 | Rename snake_case vars in admin-logs.php to camelCase | 2026-02-23 | 19 variables renamed |
| S-035 | Replace magic string keys in Snapshot traits with ResponseKeyType | 2026-02-23 | 8 files updated |
| S-036 | Add SEPARATOR_WIDTH constant to AdminMailer | 2026-02-23 | 3 magic values replaced |
| S-037 | Replace gmdate() in AgentRemoteActionTraitTest | 2026-02-23 | Done with S-033 |
| S-038 | Add DateHelper::relativeDayKey() helper | 2026-02-23 | Extracted reusable method |
| S-039 | Fix FrameBuilder.php Rule 13 + namespace order | 2026-02-23 | Fixed |
| S-040 | Autoloader LOG_PREFIX — closed as N/A | 2026-02-23 | Exempt (bootstrapping dependency) |

→ Details in `.lovable/memory/suggestions/completed/01-completed-suggestions.md`

---

## Rejected Suggestions

| ID | Description | Date | Reason |
|----|-------------|------|--------|
| R-001 | Add rate limiting to QUpload upload endpoint | 2026-03-03 | User explicitly rejected — not wanted |

---

## Statistics

| Metric | Count |
|--------|-------|
| Open | 0 |
| Completed | 39 |
| Closed N/A | 1 |
| Rejected | 1 |
| **Total** | **41** |

---

## Suggestion Workflow Convention

### File Location
All suggestions tracked in this single file: `.lovable/memory/suggestions/01-suggestions-tracker.md`

### Adding a New Suggestion
Add to "Active Suggestions (Open)" section with:
- **ID:** S-NNN (sequential, next is S-041)
- **Created:** date
- **Source:** where the suggestion originated (e.g., "Lovable", "User", "Audit")
- **Priority:** low / medium / high
- **Status:** open → inProgress → completed
- **Description:** what to change

### Completing a Suggestion
1. Move from "Active" to "Completed" table
2. Add completion date and notes
3. Update statistics

### Archive
Completed suggestion details are in `.lovable/memory/suggestions/completed/01-completed-suggestions.md`

---

*Update this file when suggestions are added, started, completed, or rejected.*
