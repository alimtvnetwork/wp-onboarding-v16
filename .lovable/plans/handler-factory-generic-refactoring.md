# Plan: Handler Factory Generic Refactoring

> **Created:** 2026-03-22
> **Goal:** Eliminate all unjustified `any` from the Handler Factory pattern
> **Scope:** `backend/internal/api/handlers/`
> **Estimated `any` removals:** ~40 locations across 15+ handler files

---

## Problem Analysis

The Handler Factory pattern (`HandlerFactory.go`) uses `any` in three places:

1. **Config structs** — `GetService func() any` in `handlerIdConfig`, `noArgsConfig`, `twoIdConfig`
2. **Callback signatures** — `fn func(ctx, id) (any, *AppError)` passed to every factory
3. **Service getters** — Legacy `func() any` wrappers in `HandlerFactoryGetters.go`

This forces every handler callback to return `(any, *AppError)` even though the underlying service methods return typed values. The `any` return type propagates to `respondSuccess(w, result)` which then passes untyped data to `envelope.Write`.

## Root Cause

Go does not allow type parameters on package-level `var` declarations. The handler vars like:
```go
var GetSite = handleActionById(cfg, func(ctx, id) (any, *AppError) { ... })
```
cannot use generics because `var` doesn't support `[T any]` syntax. The factory functions CAN be generic, but the callback return type determines what `respondSuccess` receives.

## Solution Strategy

### Approach: Generic Factory Functions + Generic Callbacks

Make factory functions generic so the callback returns `T` instead of `any`:

```go
// Before:
func handleActionById(cfg handlerIdConfig, fn func(context.Context, int64) (any, *AppError)) http.HandlerFunc

// After:
func handleActionById[T any](cfg handlerIdConfig[T], fn func(context.Context, int64) (T, *AppError)) http.HandlerFunc
```

The key insight: `http.HandlerFunc` is NOT generic — it's the final output. So the generic type parameter `T` is only used internally within the factory closure. Go CAN infer `T` from the callback argument at each call site.

### Config Struct Changes

Replace `GetService func() any` with a generic nil-checker:

```go
// Before:
type handlerIdConfig struct {
    GetService  func() any
    ServiceName string
    ...
}

// After — Option A: Generic config
type handlerIdConfig[S any] struct {
    GetService  func() S
    ServiceName string
    ...
}

// After — Option B: Separate nil-check (simpler)
type handlerIdConfig struct {
    IsReady     func() bool    // replaces func() any nil check
    ServiceName string
    ...
}
```

**Decision: Option B (IsReady pattern)** — Simpler, avoids double generic parameter `[T, S any]` on every factory. The typed service getters already exist; we just need a boolean readiness check.

### isServiceMissing Refactoring

```go
// Before:
func isServiceMissing(w http.ResponseWriter, service any, name string) bool

// After:
func isServiceNotReady(w http.ResponseWriter, isReady func() bool, name string) bool
```

---

## Phase Breakdown

### Phase HF-1: Refactor config structs and isServiceMissing
**Files:** `HandlerFactory.go`, `HandlerFactoryGetters.go`, `Response.go`

1. Replace `GetService func() any` → `IsReady func() bool` in all config structs
2. Replace `isServiceMissing(w, any, name)` → `isServiceNotReady(w, func() bool, name)`
3. Update `HandlerFactoryGetters.go`: replace `func() any` wrappers with `func() bool` readiness checks
4. Update `isSiteServiceMissing` to use the same pattern

### Phase HF-2: Make factory functions generic
**Files:** `HandlerFactory.go`

1. Add type parameter `[T any]` to `handleActionById`, `handleNoArgs`, `handleListNilSafe`, `handleSiteActionById`, `handleSiteActionByIdWithQuery`, `handleTwoIds`
2. Change callback signatures from `func(...) (any, *AppError)` → `func(...) (T, *AppError)`
3. `respondSuccess(w, result)` now receives typed `T` — already generic in Response.go

### Phase HF-3: Update all handler call sites
**Files:** 15 handler files (SiteHandlers, PluginHandlers, SyncGitHandlers, etc.)

1. Remove explicit `(any, *AppError)` return type annotations from callbacks
2. Go compiler infers `T` from service method return types
3. Update config literals to use `IsReady` instead of `GetService`
4. Each `var Handler = handleActionById(...)` call site gets type-safe callbacks

### Phase HF-4: Clean up and verify
**Files:** `HandlerFactoryGetters.go`, memory/spec docs

1. Remove legacy `func() any` wrappers from `HandlerFactoryGetters.go`
2. Keep typed service getters (already exist)
3. Add `func() bool` readiness getters
4. Update memory and spec docs
5. Verify zero `any` in handler factory pattern

---

## Impact Assessment

| Metric | Before | After |
|--------|--------|-------|
| `any` in HandlerFactory.go | 12 | 0 |
| `any` in HandlerFactoryGetters.go | 12 | 0 |
| `any` in handler callbacks (15 files) | ~30 | 0 |
| `any` in Response.go (isServiceMissing) | 2 | 0 |
| **Total `any` eliminated** | **~56** | **0** |

## Risks

1. **Go var initialization** — `var X = handleActionById[T](...)` requires Go to infer `T` at package init. This works because the callback literal provides the concrete type.
2. **Compilation cascade** — All 15 handler files change. Must be done atomically.
3. **handleListNilSafe special case** — Currently returns `[]any{}` on nil service. With generics, needs `[]T{}` or `*new(T)` — may need a zero-value helper.

## handleListNilSafe Challenge

```go
// Current (returns []any{} on nil):
if getService() == nil {
    respondSuccess(w, []any{})
    return
}

// Generic version needs empty typed slice:
if !cfg.IsReady() {
    var empty []T
    respondSuccess(w, empty)
    return
}
```

This works because `var empty []T` gives `nil` which JSON-encodes as `null`. To get `[]` in JSON, use `make([]T, 0)` or change to `respondSuccess(w, make([]T, 0))`.
