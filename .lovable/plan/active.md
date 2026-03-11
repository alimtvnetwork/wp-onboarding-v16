# Active & Future Phases

**Updated: 2026-02-23**

---

## Current Status: All Compliance Sweeps Complete ✅

All formatting, ABSPATH guard, dead code, magic string, DateHelper, and ResponseKeyType work is done. Remaining work is future architecture phases.

---

## Recently Completed (2026-02-23)

### S-033–S-038: Code Quality Improvements ✅

| ID | Description | Status |
|----|-------------|--------|
| S-033 | Expand DateHelper + replace all raw date()/gmdate() calls (21 files) | ✅ Done |
| S-034 | Rename snake_case vars in admin-logs.php to camelCase (19 vars) | ✅ Done |
| S-035 | Replace magic string keys with ResponseKeyType enum (8 Snapshot files) | ✅ Done |
| S-036 | Add SEPARATOR_WIDTH constant to AdminMailer | ✅ Done |
| S-037 | Replace gmdate() in test file (done with S-033) | ✅ Done |
| S-038 | Add DateHelper::relativeDayKey() helper | ✅ Done |

### Phase 8: Plugin Identity Strings ✅ (2026-02-23)

All 5 files fixed to replace hardcoded identity strings with `PluginConfigType` enum references. Full audit confirmed zero remaining violations.

### Formatting Sweep — All Directories ✅

| Directory | Status |
|-----------|--------|
| Snapshot/Traits/ | ✅ Done |
| Database/Traits/ | ✅ Done |
| Admin/Traits/ | ✅ Done |
| Logging/Traits/ | ✅ Done |
| Agent/Traits/ | ✅ Done |
| Helpers/Traits/ | ✅ Done |
| Traits/Route/ | ✅ Done |
| Go Handlers | ✅ Done |
| Go Services | ✅ Done |
| Database/*.php | ✅ Done |
| ErrorHandling/*.php | ✅ Done |
| Core/Plugin.php | ✅ Done (S-021: already compliant) |
| Admin/Admin.php | ✅ Done (S-021: already compliant) |
| Logging/FileLogger.php | ✅ Done (S-021: already compliant) |
| Activation/ActivationHandler.php | ✅ Done (S-031: already resolved) |
| Templates/*.php | ✅ Done (S-022: no violations) |
| Root files | ✅ Done (S-023: fully compliant) |

### ABSPATH Guard Sweep ✅

All 53 enum files and 13 Logging/ErrorHandling files confirmed to have guards (S-029, S-030).

### Dead Code Cleanup ✅

`loadDependencies()` and redundant `class_exists` already removed (S-032).

### PascalCase Enum Labels — Cross-System ✅

| Phase | Status |
|-------|--------|
| Go Backend (10 enums) | ✅ Done |
| PHP Plugin (24 enums) | ✅ Done |
| TypeScript Frontend (3 enums + 8 files) | ✅ Done (S-026) |
| PHP hardcoded string comparisons | ✅ Done (S-025: zero violations) |
| WP database stored values | ✅ Done (Phase 7G: V12 + V14 migrations) |
| Settings migration helper | ✅ Done (Phase 7G) |

### Template Magic String Elimination (Phase 7) ✅

All sub-phases 7A–7G complete.

---

## ✅ COMPLETED — PHP Plugin SQLite PascalCase Migration (Phase 3)

- Phase 3A: ✅ `TableType` enum values already PascalCase
- Phase 3B: ✅ Migration v13 — table renames via `ALTER TABLE`
- Phase 3C: ✅ Migration v13 — column renames via `ALTER TABLE ... RENAME COLUMN`
- Phase 3D: ✅ Updated all PHP code references (9 files fixed — Issue #07)
- Phase 3E: ✅ Batch F camelCase refactor — internal array keys in enhanced fields

### ✅ COMPLETED — PascalCase Spec Documentation Updates (Phase 4) (2026-02-23)

- ✅ Updated `02-required-methods.md` with PascalCase label convention (v4.1.0)
- ✅ Updated PHP `enums.md` with full `TableType`, `LogColumnType`, log context keys rule (v7.1.0)
- ✅ Updated `naming-conventions.md` with array key conventions section (v1.1.0)
- ✅ Created `php-go-consistency-audit.md` — 7-section cross-language audit

### Phase 5: Licensing System Architecture

- License server + WP plugin client (8–10 tasks)
- Decision needed: Build custom (Go) vs Keygen.sh vs LemonSqueezy vs EDD

### Go Phase 4: Positive Logic & Boolean Standards ✅ (2026-03-11)

- ✅ Positive boolean naming — renamed 12 negative variables across 10 files
- ✅ Removed `isDataMissing` method, replaced with positive `hasDataField` at call sites
- ✅ Lint script `lint-negative.sh` already in place (zero violations)
- ✅ `IsOtherThan` pattern already implemented as `IsOther` in enum variants

### Go Phase 5: Code Organization Standards

- Package restructuring
- File naming conventions
- Import organization
- **Estimated effort:** 3 tasks

### Go Phase 6: CI Lint Scripts & Integration

- Complete lint script suite (5 scripts)
- CI pipeline integration
- **Estimated effort:** 2 tasks

---

## Open Questions (with Decision Criteria)

### 1. Remote Plugin Backups — Store on WP site or download locally?

| Option | Pros | Cons | Best When |
|--------|------|------|-----------|
| **WP Site** | No local storage needed; accessible from any machine | Depends on WP site being up; storage limits | Managing many plugins across many sites |
| **Local** | Fast restore; offline access; full control | Disk usage; not portable across machines | Single developer, local workflow |
| **Both** (recommended) | Redundancy; best of both | Sync complexity | Production-critical plugins |

**Decision Criteria:** How many sites do you manage? Is offline access important? Is storage a concern on WP hosts?

### 2. Bulk Quick Publish — Add "Quick Publish Selected" for multiple plugins?

| Option | Pros | Cons | Best When |
|--------|------|------|-----------|
| **Yes** | Fast batch deployment; fewer clicks | Error recovery more complex; UI needs multi-select | Frequently deploying 3+ plugins together |
| **No** | Simpler UX; sequential publish is predictable | Slower for batch deploys | Deploying plugins individually most of the time |

**Decision Criteria:** How often do you deploy multiple plugins simultaneously? Is the current one-by-one publish workflow a bottleneck?

### 3. True Diff Comparison — Compare with remote files for accurate counts?

| Option | Pros | Cons | Best When |
|--------|------|------|-----------|
| **Yes** | Accurate modified/deleted counts; better sync confidence | Slower (needs to fetch remote file hashes); more API calls | Sync accuracy is critical; sites have reliable connections |
| **No** | Faster; less API traffic; current MD5 hash comparison works | May miss deleted files; counts are approximate | Speed matters more than precision |

**Decision Criteria:** Do you need exact file counts for audit purposes? Is sync accuracy currently causing issues?

### 4. Licensing — Build custom or use third-party?

| Option | Cost | Control | Effort | Best When |
|--------|------|---------|--------|-----------|
| **Custom Go backend** | Hosting costs only | Full control, custom features | 8–10 tasks | You want full ownership and custom license types |
| **Keygen.sh** | $49+/mo | Good API, managed | 3–4 tasks (integration) | You want a polished, managed solution |
| **LemonSqueezy** | % of revenue | Payments + licensing bundled | 2–3 tasks | You want payments and licensing together |
| **EDD Software Licensing** | $99/yr | WordPress-native | 4–5 tasks | You're already using EDD for sales |

**Decision Criteria:** Budget? How many license types needed? Do you need payment processing bundled? How important is full data ownership?

---

*Master plan details in `plan.md` (repo root). Suggestions tracked in `.lovable/memory/suggestions/01-suggestions-tracker.md`. Issues tracked in `/spec/02-app/issues/README.md`.*
