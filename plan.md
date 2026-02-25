# Future Work Roadmap

> **Location:** `plan.md` (repository root)  
> **Created:** 2026-02-24  
> **Purpose:** Prioritized backlog for AI handoff — defines what to build next

---

## Phase A: Code Quality & Migration (Current Priority)

### ~~A1. Define() Alias Caller Migration~~ ✅ ALREADY COMPLETE
- **Verified:** 2026-02-24 — No `constants.php` or `constants-compat.php` exists; all 53 enums in active use (354 ActionType refs, 285 EndpointType refs across 23+ files)

### ~~A2. Remove Define() Aliases from constants.php~~ ✅ ALREADY COMPLETE
- **Verified:** 2026-02-24 — File already deleted; no `require` references remain

### ~~A3. Go Backend interface{} Type Safety~~ ✅ ALREADY COMPLETE
- **Verified:** 2026-02-24 — Only 1 remaining `interface{}` in `site/service.go` (log fields slice) fixed to `[]any`. All other ~2,680 instances were already migrated per memory files (phases 1–5 completed 2026-02-14). Zero `interface{}` in production Go code.

### ~~A4. Naming Convention Phases 5-6~~ ✅ COMPLETE
- **Completed:** 2026-02-25 — **Hooks:** riseup-asia-uploader already 100% compliant (all hooks use `HookType` enum). **Paths:** Replaced `/a-root.db` magic string with `PathDatabaseType::Root->value` across 8 snapshot files (7 traits + 1 engine). Created 5 new `PathHelper` typed accessors (`getPluginDir`, `getPluginMainFile`, `getConstantsFile`, `getEndpointsJsonPath`, `getOpenApiJsonPath`) and migrated 4 inline `WP_PLUGIN_DIR`/`plugin_dir_path()` concatenations in `StatusPayloadTrait` and `StatusOpsTrait`. `plugins-onboard` uses magic hook strings but is a separate legacy plugin outside riseup-asia spec scope.

---

## Phase B: Template Magic String Elimination

### ~~B1. admin-agents.php JS Constants Block~~ ✅ ALREADY COMPLETE
- **Verified:** 2026-02-24 — All constant blocks (`ENDPOINTS`, `AGENT_STATUS`, `STATUS`, `RESPONSE_KEYS`, `PLUGIN_STATUS`, `ACTIONS`, `LABELS`) present at lines 290–355; zero magic strings in JS body

### ~~B2. admin-snapshots.php SNAP_ENDPOINTS + SNAP_LABELS~~ ✅ COMPLETE
- **Completed:** 2026-02-24 — Added 26 new labels to `SNAP_LABELS`; replaced all ~20 inline status/error strings with constants; all endpoints, response keys, and UI labels now use constant blocks

---

## Phase C: Go Backend Standards

### ~~C1. Go Phase 4 — Positive Logic & Boolean Standards~~ ✅ ALREADY COMPLIANT
- **Verified:** 2026-02-24 — Full audit found zero violations. `IsInvalid()` across 21 enum files is positive structure (checks `v == Invalid`). `IsNotFound()` names an error variant. All `!` negations are standard Go idioms. `DisableRemotePlugin` is a domain action. No `lint-negative.sh` needed — codebase is clean.

### ~~C2. Go Phase 5 — Code Organization Standards~~ ✅ COMPLETE
- **Completed:** 2026-02-25 — Renamed 4 service packages to snake_case directories (`sitehealth`→`site_health`, `errorhistory`→`error_history`, `publishhistory`→`publish_history`, `requestsession`→`request_session`). Updated all import paths across 4 files. Fixed import grouping in `main.go`. Split `publish/service.go` (1,883 lines) into 6 focused files: `service.go` (types/config ~155 lines), `service_publish.go` (pipeline ~537 lines), `service_broadcast.go` (WS/session logging ~285 lines), `service_zip.go` (ZIP creation ~230 lines), `service_preview.go` (diff preview ~215 lines), `service_helpers.go` (DB queries/utilities ~300 lines).

### ~~C3. Go Phase 6 — CI Lint Scripts & Integration~~ ✅ COMPLETE
- **Completed:** 2026-02-25 — Created 5th lint script (`lint-imports.sh` for Go import grouping: stdlib → third-party → internal). Created GitHub Actions CI workflow (`.github/workflows/go-lint.yml`) running: go build, go vet, go test, plus all 5 custom lint scripts (file-size, func-size, negative-naming, import-grouping, generic-enforce) and OpenAPI validation. Added `lint-all` and individual lint targets to Makefile.
- **Scripts:** `lint-file-size.sh`, `lint-func-size.sh`, `lint-negative.sh`, `lint-imports.sh`, `lint-ge.sh`
- **CI triggers:** Push/PR to `main` when `backend/` or `scripts/` change

---

## Phase D: New Features

### D1. Licensing System Architecture (Phase 5 from active.md)
- **Objective:** License server + WP plugin client for commercial distribution
- **Dependencies:** Decision needed: Build custom (Go) vs Keygen.sh vs LemonSqueezy vs EDD
- **Expected Outputs:** License validation, activation/deactivation, feature gating
- **Acceptance Criteria:** License can be issued, validated, and revoked; WP plugin checks license on activation
- **Estimated Effort:** 8–10 tasks

### D2. E2 Activity Feed (Fleet-Wide Audit Log)
- **Objective:** Implement the fleet-wide activity audit log per `spec/11-e2-activity-feed/`
- **Dependencies:** Go endpoint spec exists (e2.1)
- **Expected Outputs:** Go endpoint + React UI for activity feed
- **Acceptance Criteria:** Per spec acceptance criteria

### ~~D3. Batch G — Snake_case Log Context Keys to camelCase~~ ✅ COMPLETE
- **Completed:** 2026-02-24 — Fixed 1 true log context key (`plugin_slug` → `pluginSlug` in `PluginLifecycleHelpersTrait.php`). Remaining ~140 snake_case keys are in WP settings (`wp_options`), transient data, database columns, or API response contracts — changing them would be breaking changes requiring data migrations. Not log context keys.

---

## Phase E: Architecture Decisions ✅ ALL DECIDED

| # | Question | Decision | Notes |
|---|----------|----------|-------|
| 1 | Remote Plugin Backups | **WP Site Only** | Store backups on the WordPress site via companion plugin. No local download needed. Simpler architecture, fewer storage concerns. |
| 2 | Bulk Quick Publish | **Yes** | Add "Quick Publish Selected" for multiple plugins. Sequential publish to mapped sites. Needs UI (multi-select + action) and pipeline changes (queue/batch). |
| 3 | True Diff Comparison | **Yes** | Compare local files against remote file hashes for accurate added/modified/deleted counts. Requires companion plugin endpoint for remote file listing with hashes. |
| 4 | Licensing | **Custom Go Server** | Build in-house license server in Go. Full control, no vendor fees. Includes key generation, validation, activation/deactivation, feature gating. Estimated 8–10 tasks. |

---

## Phase F: Newly Unblocked Work

### F1. Backup Service — WP Site Storage
- **Objective:** Implement backup creation/restore via WordPress companion plugin
- **Decision:** Store on WP site only (no local download)
- **Dependencies:** Companion plugin backup endpoints
- **Expected Outputs:** Backup CRUD in Go, pre-publish backup integration, backup listing UI
- **Estimated Effort:** 4–5 tasks

### F2. Bulk Quick Publish
- **Objective:** Add multi-select "Quick Publish Selected" for batch plugin publishing
- **Dependencies:** Existing publish pipeline
- **Expected Outputs:** Multi-select UI, sequential publish queue, aggregate result reporting
- **Estimated Effort:** 3–4 tasks

### F3. True Diff Comparison
- **Objective:** Fetch remote file hashes and compare with local for accurate change counts
- **Dependencies:** Companion plugin file-listing endpoint (already partially implemented via `GetPluginFilesViaRiseup`)
- **Expected Outputs:** Enhanced sync service, accurate added/modified/deleted counts in preview
- **Note:** PreviewPublish already has remote comparison logic — this task is to ensure reliability and fill gaps
- **Estimated Effort:** 2–3 tasks

### F4. Custom Licensing Server (Go)
- **Objective:** License server + WP plugin client for commercial distribution
- **Dependencies:** None (greenfield)
- **Expected Outputs:** License key generation, validation endpoint, activation/deactivation, feature gating, WP plugin client
- **Acceptance Criteria:** License can be issued, validated, activated, deactivated, and revoked; WP plugin checks license on activation
- **Estimated Effort:** 8–10 tasks

---

## Next Task Selection

**Ready to implement now (no blockers):**

1. **F3 — True Diff Comparison** — Smallest unblocked task; enhances existing PreviewPublish logic (2–3 tasks)
2. **F2 — Bulk Quick Publish** — Medium scope; UI + pipeline changes (3–4 tasks)
3. **F1 — Backup Service** — Medium scope; requires companion plugin coordination (4–5 tasks)
4. **D2 — Activity Feed** — Go endpoint + React UI for fleet-wide audit log
5. **F4 — Licensing Server** — Largest scope; greenfield Go service (8–10 tasks)

---

*Ask the user which task to implement next.*
