# Pending Tasks for Future AI Sessions

> **Updated:** 2026-03-16

---

## High Priority

### 1. Deploy v2.17.0+ to All Remote Sites
- **Status:** Blocked — needs user to run `.\run.ps1 -uas` from their local machine
- **Context:** Remote sites are running v2.11.0–v2.14.0. The latest code (v2.17.0) includes machine authorization, preflight checks, and EnvelopeBuilder crash fix
- **After deploy:** Run `.\run.ps1 -am` then `.\run.ps1 -cla` to verify

### 2. Verify EnvelopeBuilder Fallback Fix
- **Status:** Code fix applied, not yet deployed
- **File:** `wp-plugins/qupload/includes/Traits/Core/ResponseTrait.php`
- **Test:** After deploying, upload any plugin via QUpload and confirm no 500 errors

---

## Medium Priority

### 3. Go Backend `interface{}` Type-Safety Migration
- **Status:** ~2,680 instances across 58 files
- **Context:** Replace `interface{}` with `any` or typed generics where possible
- **Reference:** `.lovable/plan/README.md` item #3

### 4. Log Rotation for QUpload
- **Status:** Not implemented
- **Context:** Log files in `wp-content/uploads/qupload/logs/` grow unbounded
- **Reference:** `.lovable/memory/issues/005-log-rotation-missing.md`

---

## Low Priority

### 5. Add `-check` Diagnostic Command
- **Status:** Suggested, not implemented
- **Context:** A `.\run.ps1 -check` command that runs the preflight readiness check across all sites without performing any action — useful for quick diagnostics

### 6. Force ZIP Rebuild on Source Change
- **Status:** Suggested, not implemented
- **Context:** Upload script reuses cached ZIP files even when source files have changed. Add automatic hash-based invalidation

---

## Recently Completed

| Task | Date | Reference |
|------|------|-----------|
| Preflight check in `-am` | 2026-03-16 | `mode-approve-machine.ps1` |
| EnvelopeBuilder fallback in ResponseTrait | 2026-03-16 | Issue #006 |
| Root README.md with full CLI reference | 2026-03-16 | `README.md` |
