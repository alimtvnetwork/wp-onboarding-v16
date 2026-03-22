# Issue #38: Go Type Safety — Eliminate `any` from Production Code

> **Created:** 2026-03-22  
> **Severity:** Medium (code quality / maintainability)  
> **Status:** ✅ Resolved — all 6 phases complete

---

## Problem

The Go backend used `any` (empty interface) in **259 locations across 88 files**. This undermined type safety, made refactoring risky, hid bugs at compile time, and forced runtime type assertions.

## Root Cause

Historical `interface{}` usage was bulk-migrated to `any` keyword but never replaced with proper types. Handler factory pattern returned `any` to accommodate multiple service types, propagating untyped returns throughout the codebase.

## Resolution

All 6 phases completed. Remaining `any` usage audited and justified.

### Phase Results

| Phase | Scope | Result |
|-------|-------|--------|
| G-1 | `pkg/apperror` + `pkg/dbutil` generics | ✅ Already compliant |
| G-2 | Response & Envelope layer | ✅ Already compliant |
| G-3 | Handler Factory generics | ✅ Typed getters added; factory `any` justified (internal pattern) |
| G-4 | Adapter interfaces + Service returns | ✅ User mgmt fully typed; Logs/Settings/Health `any` justified (PHP JSON) |
| G-5 | Service layer structs | ✅ clearLogs typed; PHP-proxied `any` justified (envelope unwrap) |
| G-6 | WebSocket + Logger typing | ✅ Untyped `BroadcastWithSession(any)` eliminated; typed broadcast methods added |

### Justified Exceptions (remaining `any` usage)

| Location | Pattern | Justification |
|----------|---------|---------------|
| `ws/Hub.go` `Message.Data` | `any` field | Runtime JSON container — typed generics feed into single channel |
| `ws/*.go` | `[T any]` | Go generic type constraints — correct usage |
| `logger/*.go` | `keyvals ...any` | Standard structured logging pattern (matches `log/slog`) |
| `wordpress/EnvelopeUnwrap.go` | `map[string]any` → `any` | Dynamic PHP JSON with variable structure |
| `wordpress/Client.go` | `body any` | Mirrors `json.Marshal` — accepts any serializable struct |
| `wordpress/ClientApiCall.go` | `ApiCallInput.Body any` | Same as above |
| `*_test.go` | Various | Test files — unrestricted per coding standard |

## Prevention

- CI lint rule: `go-no-any` to block new unjustified `any` usage
- Code review checklist item
- Spec `04-type-safety-no-any.md` as reference
- Memory: `.lovable/memory/coding-standards/go-type-safety.md`
