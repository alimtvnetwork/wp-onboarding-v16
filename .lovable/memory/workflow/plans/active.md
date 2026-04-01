# Active Development Plan

> **Updated:** 2026-03-16

---

## Current Focus Areas

- **PowerShell Automation:** CLI suite complete with `-check`, `-am` preflight, full site targeting. Ready for deployment verification.
- **QUpload Stability:** EnvelopeBuilder crash fix applied. Self-update resilience via `class_exists()` fallback. Log rotation confirmed working.
- **Cloud Storage Integration:** Frontend complete (3 providers + dashboard + publish integration). Backend Go pipeline integration pending.
- **Code Quality & Standards:** Ongoing enforcement of formatting rules and code consistency.
- **Issue Documentation:** Every fix requires a write-up under `/spec/02-app-issues/` per the post-fix workflow.

---

## Recently Completed (2026-03-16 — This Session)

### PowerShell & QUpload Improvements

| Item | Description | Files |
|------|-------------|-------|
| `-am` preflight check | Version-aware readiness check before machine approval | `mode-approve-machine.ps1` |
| EnvelopeBuilder crash fix | `class_exists()` + inline fallback in ResponseTrait | `ResponseTrait.php`, `UploadHandlerTrait.php` |
| Root `README.md` | Comprehensive CLI reference with all flags and examples | `README.md` |
| `-check` command | Read-only site/plugin readiness diagnostics | `mode-check.ps1`, `run.ps1` |
| Log rotation confirmed | Already implemented — size-based rotation + pruning in `FileLogger.php` | `FileLogger.php` |
| Memory & docs update | Issues, suggestions, plans, CLI reference all updated | Multiple `.lovable/memory/` files |

### Previously Completed (2026-03-15 — Cloud Storage)

- **CS-001–CS-006**: Cloud Storage Settings Page, Google Drive OAuth2, BackupSelector, publish integration
- **S-041–S-044**: Cloud upload progress stage, OAuth settings, version bump

### Previously Completed (2026-02-23 — Code Quality)

- **S-033–S-040**: DateHelper, camelCase, ResponseKeyType, formatting sweeps
- **Phase 8**: Plugin identity PluginConfigType enum
- **Go Phases 4–6**: Boolean standards, code organization, CI lint

---

## Pending Tasks

| # | Task | Priority | Status | Suggestion |
|---|------|----------|--------|------------|
| 1 | **Deploy v2.17.0+ to all sites** | 🔴 High | Blocked (user action) | — |
| 2 | **ORM PDO Fix — Redeploy** | 🔴 High | Blocked (deployment) | — |
| 3 | Go `interface{}` → `any` migration | 🟡 Medium | ✅ Done — 7 instances across 4 files | — |
| 4 | Cloud Storage Go pipeline `cloud_upload` stage | 🟡 Medium | ✅ Done — already implemented in ServicePublishCloudUpload.go + wired in pipeline | S-044 |
| 5 | Backup History Visualization (Phase 5E) | 🟡 Medium | ✅ Done — CloudStorageBackupTimeline component wired into account cards | S-047 |
| 5b | Cloud Storage PascalCase → camelCase type fix | 🟡 Medium | ✅ Done — all types, components, hooks, methods fixed | — |
| 6 | Go Backend UserClient | 🟡 Medium | Open | — |
| 7 | Progress Bar Normalization | 🟡 Medium | Open | — |
| 8 | Chunk reassembly manifest validation | 🟡 Medium | ✅ Done | S-048 |
| 9 | Auto-invalidate cached ZIP on source change | 🟡 Medium | ✅ Done | S-052 |
| 10 | Google Drive Rotation (Phase 5F) | 🟢 Low | Open | S-046 |
| 11 | Diagnostic Reporting (Spec #08) | 🟢 Low | Open | — |
| 12 | QUpload API method migration (activate/deactivate → PUT) | 🟢 Low | Open | — |
| 13 | Create `settings.json` for QUpload | 🟢 Low | Open | S-049 |
| 14 | `/logs/rotation-status` endpoint | 🟢 Low | Open | S-050 |
| 15 | Verbose `-check` mode (HEAD probing) | 🟢 Low | ✅ Done — HEAD probes per endpoint in verbose mode | S-051 |
| 16 | Licensing admin dashboard | 🟢 Low | Open | S-053 |
| 17 | Publish analytics dashboard | 🟢 Low | Open | S-054 |

---

## Key Cross-References

- **Suggestions:** `.lovable/memory/suggestions/01-suggestions-tracker.md`
- **Pending tasks:** `.lovable/memory/workflow/pending-tasks.md`
- **Issues:** `.lovable/memory/issues/` and `.lovable/memory/issues-fixed/00-index.md`
- **CLI reference:** `.lovable/memory/workflow/powershell-automation/cli-reference.md`
- **QUpload features:** `.lovable/memory/features/qupload-plugin.md`

---

*Update this file when plans are started, completed, or deprioritized.*
