# Issues Fixed - Learning Repository

> **Purpose:** Document resolved issues with root causes and solutions for AI learning continuity.  
> **Updated:** 2026-03-16

---

## Quick Reference

| Issue | Category | File |
|-------|----------|------|
| pnpm PnP module resolution failures | Build/Dependencies | [01-pnpm-pnp-resolution.md](./01-pnpm-pnp-resolution.md) |
| SPA static file 404 errors | Backend/Routing | [02-spa-static-serving.md](./02-spa-static-serving.md) |
| WebSocket upgrade failures in middleware | Backend/WebSocket | [03-websocket-middleware.md](./03-websocket-middleware.md) |
| Global error modal not capturing API failures | Frontend/UX | [04-global-error-reporting.md](./04-global-error-reporting.md) |
| pnpm v10 ignored build scripts / PnP ESM loader | Build/Dependencies | [05-pnpm-v10-build-scripts-and-esm.md](./05-pnpm-v10-build-scripts-and-esm.md) |
| SQLite datetime scanning issues | Backend/Database | [06-sqlite-datetime-scanning.md](./06-sqlite-datetime-scanning.md) |
| Null-check, error source reporting, enhanced call chain | Frontend/UX | [07-null-check-error-source.md](./07-null-check-error-source.md) |
| Malformed version.json causes app crash | Tooling/Config | [08-version-json-malformed.md](./08-version-json-malformed.md) |
| NULL datetime crash in publish service | Backend/Publish | [09-null-datetime-publish-crash.md](./09-null-datetime-publish-crash.md) |
| ZIP finalization race condition | Backend/Publish | [10-zip-finalization-race.md](./10-zip-finalization-race.md) |
| Activation endpoint 404 mismatch | Backend/Publish | [11-activation-endpoint-mismatch.md](./11-activation-endpoint-mismatch.md) |
| PHP circular dependency during bootstrap | WordPress/PHP | [12-php-circular-dependency-bootstrap.md](./12-php-circular-dependency-bootstrap.md) |
| Go `buildWPClient` undefined method | Backend/Go | [13-go-build-wp-client-undefined.md](./13-go-build-wp-client-undefined.md) |
| Retry/debounce/dedup anti-patterns | Frontend/Reliability | [14-retry-debounce-dedup-anti-patterns.md](./14-retry-debounce-dedup-anti-patterns.md) |
| Deactivate plugin 404 | Backend/WordPress | [15-deactivate-plugin-404.md](./15-deactivate-plugin-404.md) |
| Health endpoint format mismatch | Frontend/Backend | [See spec/07-error-manage/03-error-resolution/01-health-endpoint-mismatch.md](../../../spec/07-error-manage/03-error-resolution/01-health-endpoint-mismatch.md) |
| Coverage report wrong package filtering | Tooling/Coverage | [See spec/02-app-issues/17-coverage-report-wrong-package-filtering.md](../../../spec/02-app-issues/17-coverage-report-wrong-package-filtering.md) |
| checkAuthenticatedOnly() return type fatal (WP_User instead of true) | WordPress/PHP | [See spec/02-app-issues/22-auth-return-type-fatal-error.md](../../../spec/02-app-issues/22-auth-return-type-fatal-error.md) |
| `-cla` fails with `rest_disabled` + `machine_not_approved` | PowerShell/WordPress REST | [See spec/02-app-issues/31-cla-failure-endpoint-gating-and-machine-approval.md](../../../spec/02-app-issues/31-cla-failure-endpoint-gating-and-machine-approval.md) |

---

## Structural / Debt Issues (`.lovable/memory/issues/`)

| # | Issue | Category | Status |
|---|-------|----------|--------|
| 001 | Missing stack traces in error log | Logging | Resolved |
| 002 | Raw comparisons in ternaries | Code Quality | Resolved |
| 003 | ORM PDO class not found | Database | Open (pending fix) |
| 004 | QUpload activate PUT not POST | API Design | Resolved |
| 005 | Log rotation missing | Logging | Resolved (already implemented in FileLogger) |
| 006 | EnvelopeBuilder class not found on self-update | Self-Update | Fixed (`class_exists()` + fallback) |
| 007 | Upload 404 rest_no_route | Deployment | Root cause: version mismatch. Fix: deploy v2.17.0+ |
| 008 | `-check` command implementation | Enhancement | ✅ Implemented |

---

## Key Learnings (Do NOT Repeat)

1. **Never hard-depend on helper classes in response paths** — use `class_exists()` guard for any class that could be missing during self-update (Issue #006)
2. **Boot-time logic must be wrapped in try/catch** — enum/class access during WordPress init can crash the entire REST namespace (Issue #006 memory)
3. **Always deploy latest versions before using new CLI features** — 404 errors from version mismatch waste debugging time (Issue #007)
4. **Deep-merge settings** — use `array_replace_recursive()` when evolving plugin settings to avoid breaking existing installs (Issue #037 / `-cla` failure)
5. **Preflight checks prevent blind failures** — the `-am` and `-check` commands validate endpoint availability before attempting operations

---

## Cross-References

- **App issues (03–28):** `spec/02-app-issues/` — see [spec/02-app-issues/README.md](../../../spec/02-app-issues/README.md) for the full index
- **Structural debt issues:** `.lovable/memory/issues/` — files 001–008
- **Suggestions tracker:** `.lovable/memory/suggestions/01-suggestions-tracker.md`

---

## How to Use This Folder

1. **Before implementing:** Check if a similar issue was previously fixed
2. **When debugging:** Search for error patterns or symptoms
3. **After fixing:** Document new issues following the template in each file

---

## Issue Categories

- **Build/Dependencies** - npm, pnpm, Go module issues
- **Backend/Routing** - API routes, static file serving, SPA fallback
- **Backend/WebSocket** - Connection upgrades, middleware interference
- **Backend/Go** - Compilation errors, undefined methods
- **Backend/Publish** - ZIP, activation, deployment pipeline
- **Frontend/UX** - Error handling, state management, UI bugs
- **Frontend/Build** - Vite, Rollup, TypeScript compilation
- **WordPress/PHP** - Plugin initialization, circular dependencies, enums
- **PowerShell/Automation** - CLI flags, upload scripts, diagnostics
- **Tooling/Config** - version.json, coverage reports, config files
