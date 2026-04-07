# Suggestions Tracker

> **Location:** `.lovable/memory/suggestions/01-suggestions-tracker.md`  
> **Purpose:** Track AI suggestions for improvements (consolidated single file)  
> **Updated:** 2026-04-01

---

## Active Suggestions (Open)

| ID | Created | Priority | Source | Status | Description |
|----|---------|----------|--------|--------|-------------|
| S-044 | 2026-03-15 | Medium | Lovable | done | Cloud Storage Go pipeline cloud_upload stage — already implemented |
| S-046 | 2026-03-15 | Low | Lovable | done | Implement Phase 5F for Google Drive folder-based rotation adaptation |
| S-047 | 2026-03-15 | Medium | Lovable | done | Implement backup history visualization UI (Phase 5E) in the Cloud Storage dashboard |
| S-048 | 2026-03-15 | Medium | Lovable | done | Add Go backend validation for chunk reassembly manifest integrity during publishing |
| S-049 | 2026-03-16 | Low | Lovable | open | Create `wp-plugins/qupload/settings.json` with explicit logging config defaults |
| S-050 | 2026-03-16 | Low | Lovable | open | Add GET `/logs/rotation-status` endpoint to QUpload for remote rotation monitoring |
| S-051 | 2026-03-16 | Medium | Lovable | done | Add verbose endpoint probing to `-check` (HEAD requests per endpoint) |
| S-052 | 2026-03-16 | Medium | Lovable | done | Auto-invalidate cached ZIP when plugin source files change (hash-based) |
| S-053 | 2026-03-16 | Low | Lovable | open | Admin dashboard for licensing server (React SPA or Go templates) — **needs spec first** |
| S-054 | 2026-03-16 | Low | Lovable | open | Publish analytics / history reporting dashboard — **needs spec first** |
| S-055 | 2026-04-01 | High | User | done | Scan and fix all QUpload-incompatible PHP patterns (is_array, array(), magic strings) across all plugins |
| S-056 | 2026-04-01 | Medium | User | done | Create .ai-instructions files in plugin roots with PHP compatibility rules |

---

## Completed Suggestions

| ID | Title | Completed | Notes |
|----|-------|-----------|-------|
| S-001 | WordPress API Error Examples | 2026-02-09 | 6 error types in `10-wp-rest-client.md` |
| S-002 | fsnotify Platform Differences | 2026-02-02 | Replaced with hybrid watcher mode |
| S-003 | Specify Hash Algorithm | 2026-02-02 | MD5 implemented in scanner.go |
| S-004 | Partial Publish Recovery | 2026-02-09 | 4 strategies in `08-publish-service.md` |
| S-005 | WebSocket Reconnection Recovery | 2026-02-09 | Broad invalidation on reconnect |
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
| S-017 | Post-Deploy Version Verification Pass | 2026-02-09 | Auto version drift detection |
| S-018 | Remove Vestigial pnpmVirtualStorePath | 2026-02-09 | Removed from `powershell.json` |
| S-019 | Fix Database/*.php R12 violations | 2026-02-22 | Fixed |
| S-020 | Fix ErrorHandling/*.php formatting | 2026-02-22 | All 4 files verified |
| S-021 | Fix R12 + formatting in Plugin.php, Admin.php, FileLogger.php | 2026-02-23 | Compliant |
| S-022 | Fix formatting in Templates/*.php | 2026-02-23 | No violations |
| S-023 | Fix formatting in root files | 2026-02-23 | Fully compliant |
| S-024 | Deduplicate Database pagination constants | 2026-02-23 | Already uses PaginationConfigType |
| S-025 | Audit old enum value comparisons | 2026-02-23 | Zero hardcoded old comparisons |
| S-026 | Update TS enum string values to PascalCase | 2026-02-23 | 3 enums + 8 files converted |
| S-027 | Fix admin-errors.php template magic strings | 2026-02-23 | Already uses enums |
| S-028 | Update core-enum-inventory with LogColumnType | 2026-02-23 | Added LogColumnType (16 cases) |
| S-029 | Add ABSPATH guards to enum files | 2026-02-23 | All 53 files already guarded |
| S-030 | Add ABSPATH guards to Logging/ErrorHandling files | 2026-02-23 | All 13 files already guarded |
| S-031 | Fix ActivationHandler R12, R4, indentation | 2026-02-23 | Already resolved |
| S-032 | Remove dead loadDependencies() + redundant class_exists | 2026-02-23 | Already removed |
| S-033 | Expand DateHelper + replace raw date() calls | 2026-02-23 | 6 constants + 6 methods; 21 files |
| S-034 | Rename snake_case vars in admin-logs.php to camelCase | 2026-02-23 | 19 variables renamed |
| S-035 | Replace magic string keys with ResponseKeyType | 2026-02-23 | 8 files updated |
| S-036 | Add SEPARATOR_WIDTH constant to AdminMailer | 2026-02-23 | 3 magic values replaced |
| S-037 | Replace gmdate() in AgentRemoteActionTraitTest | 2026-02-23 | Done with S-033 |
| S-038 | Add DateHelper::relativeDayKey() helper | 2026-02-23 | Extracted reusable method |
| S-039 | Fix FrameBuilder.php Rule 13 + namespace order | 2026-02-23 | Fixed |
| S-040 | Autoloader LOG_PREFIX — closed as N/A | 2026-02-23 | Exempt (bootstrapping dependency) |
| S-041 | Conditionally show cloud_upload stage | 2026-03-15 | Integrated with CS-006 |
| S-042 | Add Google OAuth settings section | 2026-03-15 | Part of CS-002 |
| S-043 | Bump all plugin versions to 2.15.0 | 2026-03-15 | Done |
| S-044 | Add cloud_upload progress stage to backend | 2026-03-15 | Part of CS-006 |
| S-045 | Add -check diagnostic command to PowerShell | 2026-03-16 | mode-check.ps1 created |
| CS-001 | Cloud Storage Settings Page | 2026-03-15 | Complete |
| CS-002 | Google Drive OAuth2 + resumable uploads | 2026-03-15 | Complete |
| CS-003 | CloudStorageBackupSelector component | 2026-03-15 | Complete |
| CS-004 | Pass cloudStorageAccountIds through useQuickPublish | 2026-03-15 | Complete |
| CS-005 | Pass cloudStorageAccountIds through useBulkQuickPublish | 2026-03-15 | Complete |
| CS-006 | Add cloud_upload stage to PublishProgressDialog | 2026-03-15 | Complete |
| PS-001 | Preflight check in -am (version-aware) | 2026-03-16 | Complete |
| PS-002 | EnvelopeBuilder fallback in ResponseTrait | 2026-03-16 | Complete |
| PS-003 | Root README.md with full CLI reference | 2026-03-16 | Complete |
| PS-004 | -check preflight diagnostic command | 2026-03-16 | Complete |
| PS-005 | Log rotation confirmed (already implemented) | 2026-03-16 | Complete |

---

## Rejected Suggestions

| ID | Description | Date | Reason |
|----|-------------|------|--------|
| R-001 | Add rate limiting to QUpload upload endpoint | 2026-03-03 | User rejected |

---

## Statistics

| Metric | Count |
|--------|-------|
| Open | 9 |
| Completed | 59 |
| Closed N/A | 1 |
| Rejected | 1 |
| **Total** | **70** |

---

## Suggestion Workflow Convention

### File Location
All suggestions tracked in this single file: `.lovable/memory/suggestions/01-suggestions-tracker.md`

### Adding a New Suggestion
Add to "Active Suggestions (Open)" section with:
- **ID:** S-NNN (sequential, next is **S-057**)
- **Created:** date
- **Source:** where the suggestion originated (e.g., "Lovable", "User", "Audit")
- **Priority:** low / medium / high
- **Status:** open → inProgress → completed
- **Description:** what to change

### Completing a Suggestion
1. Move from "Active" to "Completed" table
2. Add completion date and notes
3. Update statistics
4. Never delete — keep completed in this file for audit trail

### Archive
Detailed completion notes for older suggestions: `.lovable/memory/suggestions/completed/01-completed-suggestions.md`

---

*Update this file when suggestions are added, started, completed, or rejected. Next ID: S-057.*
