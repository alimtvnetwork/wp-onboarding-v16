# Suggestions Tracker

> **Location:** `.lovable/memory/suggestions/01-suggestions-tracker.md`  
> **Purpose:** Track AI suggestions for improvements (consolidated single file)  
> **Updated:** 2026-02-23

---

## Active Suggestions (Open)

| ID | Created | Priority | Source | Status | Description |
|----|---------|----------|--------|--------|-------------|


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
| S-020 | Fix ErrorHandling/*.php formatting | 2026-02-22 | All 4 files verified compliant — no changes needed |
| S-029 | Add ABSPATH guards to enum files | 2026-02-23 | Audit confirmed all 53 enum files already have guards — no changes needed |
| S-030 | Add ABSPATH guards to Logging/ErrorHandling files | 2026-02-23 | Audit confirmed all 13 files already have guards — no changes needed |
| S-021 | Fix R12 + formatting in Plugin.php, Admin.php, FileLogger.php | 2026-02-23 | Audit confirmed all 3 files already compliant — no changes needed |
| S-031 | Fix ActivationHandler R12, R4, indentation | 2026-02-23 | Audit confirmed all violations already resolved — no changes needed |
| S-032 | Remove dead loadDependencies() + redundant class_exists | 2026-02-23 | Audit confirmed both already removed — no changes needed |
| S-024 | Deduplicate Database pagination constants | 2026-02-23 | Audit confirmed DEFAULT_LIMIT/MAX_LIMIT already removed; all consumers use PaginationConfigType |
| S-025 | Audit old enum value comparisons | 2026-02-23 | Zero hardcoded old lowercase enum comparisons found — already resolved |
| S-027 | Fix admin-errors.php template magic strings | 2026-02-23 | Template already uses LogLevelType, AdminTabType, AdminPageType, NonceType enums — no magic strings |
| S-028 | Update core-enum-inventory with LogColumnType | 2026-02-23 | Added LogColumnType (16 cases) to inventory |
| S-022 | Fix formatting in Templates/*.php | 2026-02-23 | All 5 templates audited — no formatting violations found |
| S-023 | Fix formatting in root files | 2026-02-23 | Both riseup-asia-uploader.php and Autoloader.php audited — fully compliant |
| S-026 | Update TS enum string values to PascalCase | 2026-02-23 | Converted ActivityType, BackupOperation, NotificationType + all consumers (8 files) |

→ Details in `.lovable/memory/suggestions/completed/01-completed-suggestions.md`

---

## Rejected Suggestions

*None.*

---

## Statistics

| Metric | Count |
|--------|-------|
| Open | 0 |
| Completed | 32 |
| Rejected | 0 |
| **Total** | **32** |

---

## Suggestion Workflow Convention

### File Location
All suggestions tracked in this single file: `.lovable/memory/suggestions/01-suggestions-tracker.md`

### Adding a New Suggestion
Add to "Active Suggestions (Open)" section with:
- **ID:** S-NNN (sequential)
- **Created:** date
- **Source:** where the suggestion originated
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
