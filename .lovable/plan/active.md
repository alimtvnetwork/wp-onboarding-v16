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

## Pending (Not Yet Started)

### PHP Plugin SQLite PascalCase Migration (Phase 3)

- Phase 3A: Update `TableType` enum values to PascalCase
- Phase 3B: Migration v13 — table renames via `ALTER TABLE`
- Phase 3C: Migration v13 — column renames via `ALTER TABLE ... RENAME COLUMN`
- Phase 3D: Update all PHP code references (SQL queries, Orm, traits)
- **Estimated effort:** 3 sessions

### PascalCase Spec Documentation Updates (Phase 4)

- Update `02-required-methods.md` examples to PascalCase labels
- Update PHP enum spec to reflect PascalCase `->value` properties
- Update `php-go-consistency-audit.md`

### Phase 5: Licensing System Architecture

- License server + WP plugin client (8–10 tasks)
- Decision needed: Build custom (Go) vs Keygen.sh vs LemonSqueezy vs EDD

### Go Phase 4: Positive Logic & Boolean Standards

- Positive boolean naming (`IsValid` not `IsNotValid`)
- Negation elimination (`IsOtherThan` pattern)
- Lint script `lint-negative.sh`
- **Estimated effort:** 2 tasks

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

## Open Questions

1. **Remote Plugin Backups**: Store on WP site or download locally?
2. **Bulk Quick Publish**: Add "Quick Publish Selected" for multiple plugins?
3. **True Diff Comparison**: Compare with remote files for accurate modified/deleted counts?
4. **Licensing Build vs Buy**: Custom Go backend vs Keygen.sh vs LemonSqueezy?

---

*Master plan details in `plan.md`. Suggestions tracked in `.lovable/memory/suggestions/01-suggestions-tracker.md`. Issues tracked in `/spec/02-app/issues/README.md`.*
