# Plan: Handler Factory Generic Refactoring

> **Created:** 2026-03-22
> **Completed:** 2026-03-22
> **Goal:** Eliminate all unjustified `any` from the Handler Factory pattern
> **Scope:** `backend/internal/api/handlers/`
> **Status:** ✅ Complete

---

## Summary of Changes

### Phase HF-1: Config structs + isServiceNotReady ✅
- Replaced `GetService func() any` → `IsReady func() bool` in `handlerIdConfig`, `noArgsConfig`, `twoIdConfig`
- Added `isServiceNotReady(w, func() bool, name)` helper in Response.go
- Kept `isServiceMissing(w, any, name)` for manual handler usage (justified — nil-check utility)

### Phase HF-2: Generic factory functions ✅
- Added `[T any]` type parameter to all 6 factory functions:
  - `handleActionById[T any]`
  - `handleListNilSafe[T any]`
  - `handleNoArgs[T any]`
  - `handleSiteActionById[T any]`
  - `handleSiteActionByIdWithQuery[T any]`
  - `handleTwoIds[T any]`
- Callback signatures: `func(ctx, id) (T, *AppError)` instead of `(any, *AppError)`
- `handleDeleteById` unchanged — no data return, no `any` in callback

### Phase HF-3: Updated all handler call sites ✅
- 15 handler files updated: config literals use `IsReady` instead of `GetService`
- Where service methods return typed values (e.g., `*database.PluginVersionRow`), `T` is inferred as the typed return
- Where service methods still return `any` (PHP-proxied endpoints), `T = any` — the `any` comes from the service layer, not the factory
- `handleListNilSafe` nil case uses `[]struct{}{}` instead of `[]any{}`

### Phase HF-4: Cleaned up getters ✅
- Removed all `func() any` legacy wrappers from `HandlerFactoryGetters.go`
- Added 10 `func() bool` readiness checks: `isSiteServiceReady`, `isPluginServiceReady`, etc.
- Typed service getters retained for manual handler usage

---

## Impact

| Metric | Before | After |
|--------|--------|-------|
| `any` in HandlerFactory.go (config + callbacks) | 12 | 0 |
| `any` in HandlerFactoryGetters.go | 12 | 0 |
| `any` in handler callbacks (factory call sites) | ~30 | ~22* |
| `any` in Response.go (isServiceMissing) | 1 | 1 (justified) |
| **Factory infrastructure `any`** | **~25** | **0** |

*~22 remaining `any` in callbacks are from service methods that return `any` (PHP-proxied endpoints). These are Phase G-4 scope (adapter interface typed returns), not factory scope.

## Remaining `any` (Phase G-4 scope)

Service methods like `GetRemotePlugins`, `GetRemoteSiteSettings`, `GetRemoteLogsStatus` return `(any, *AppError)` because they proxy dynamic PHP JSON. When these service methods get typed return structs in Phase G-4, the `T` parameter will automatically infer the typed return — no further factory changes needed.
