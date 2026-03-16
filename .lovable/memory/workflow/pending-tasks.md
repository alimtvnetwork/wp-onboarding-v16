# Pending Tasks for Future AI Sessions

> **Updated:** 2026-03-16

---

## High Priority

### 1. Deploy v2.17.0+ to All Remote Sites
- **Status:** Blocked — needs user to run `.\run.ps1 -uas` from their local machine
- **Context:** Remote sites are running v2.11.0–v2.14.0. The latest code (v2.17.0) includes machine authorization, preflight checks, EnvelopeBuilder crash fix, and the `-check` command
- **After deploy:** Run `.\run.ps1 -am` then `.\run.ps1 -cla` then `.\run.ps1 -check` to verify

### 2. Verify EnvelopeBuilder Fallback Fix
- **Status:** Code fix applied, not yet deployed
- **File:** `wp-plugins/qupload/includes/Traits/Core/ResponseTrait.php`
- **Test:** After deploying, upload any plugin via QUpload and confirm no 500 errors

---

## Medium Priority

### 3. Go Backend `interface{}` Type-Safety Migration
- **Status:** ~2,680 instances across 58 files
- **Context:** Replace `interface{}` with `any` or typed generics where possible

### 4. ORM PDO Fix
- **Status:** Open
- **Context:** Resolving database connectivity issues for multi-site deployments
- **Reference:** `.lovable/memory/issues/003-orm-pdo-class-not-found.md`

### 5. Backup History Visualization (Phase 5E)
- **Status:** Open (S-047)
- **Context:** Timeline UI integration in Cloud Storage dashboard

### 6. Go Backend UserClient
- **Status:** Open
- **Context:** Bridge backend with PHP user management endpoints

---

## Low Priority

### 7. Auto-Invalidate Cached ZIP on Source Change
- **Status:** Suggested (S-052), not implemented
- **Context:** Upload script reuses cached ZIP files even when source files changed. Add hash-based invalidation

### 8. Create `settings.json` for QUpload
- **Status:** Suggested (S-049), not implemented
- **Context:** Make logging defaults (maxLogSizeBytes, maxRotations) explicit and easy to tune

### 9. Add `/logs/rotation-status` Endpoint
- **Status:** Suggested (S-050), not implemented
- **Context:** Remote monitoring of rotation config, archive count, per-file sizes

### 10. Verbose `-check` Mode
- **Status:** Suggested (S-051), not implemented
- **Context:** HEAD requests per endpoint instead of inferring from version

---

## Recently Completed (2026-03-16)

| Task | Date | Reference |
|------|------|-----------|
| Preflight check in `-am` | 2026-03-16 | `mode-approve-machine.ps1` |
| EnvelopeBuilder fallback in ResponseTrait | 2026-03-16 | Issue #006 |
| Root README.md with full CLI reference | 2026-03-16 | `README.md` |
| `-check` diagnostic command | 2026-03-16 | `mode-check.ps1` |
| Log rotation confirmed already implemented | 2026-03-16 | `FileLogger.php` (size-based + pruning) |
| Memory & documentation comprehensive update | 2026-03-16 | This session |
