# Issue #38: Go Type Safety — Eliminate `any` from Production Code

> **Created:** 2026-03-22  
> **Severity:** Medium (code quality / maintainability)  
> **Status:** Open — refactoring plan created

---

## Problem

The Go backend uses `any` (empty interface) in **259 locations across 88 files**. This undermines type safety, makes refactoring risky, hides bugs at compile time, and forces runtime type assertions.

## Root Cause

Historical `interface{}` usage was bulk-migrated to `any` keyword but never replaced with proper types. Handler factory pattern returns `any` to accommodate multiple service types, propagating untyped returns throughout the codebase.

## Impact

- **No compile-time checks** on handler return types
- **Runtime panics** from failed type assertions
- **Poor IDE support** — no autocomplete on `any` fields
- **Harder refactoring** — changing a type doesn't surface all callsites

## Fix Plan

See `spec/05-golang-standards/04-type-safety-no-any.md` for the 6-phase refactoring plan.

### Phase Summary

| Phase | Scope | Files | Est. Effort |
|-------|-------|-------|-------------|
| G-1 | `pkg/apperror` + `pkg/dbutil` generics | ~12 | Medium |
| G-2 | Response & Envelope layer | ~5 | Small |
| G-3 | Handler Factory generics | ~3 | Medium |
| G-4 | Adapter interfaces (typed returns) | ~5 | Large |
| G-5 | Service layer (`map[string]any` → structs) | ~40 | Large |
| G-6 | WordPress client + WebSocket | ~15 | Medium |

## Prevention

- CI lint rule: `go-no-any` to block new `any` usage
- Code review checklist item
- Spec `04-type-safety-no-any.md` as reference

## References

- Coding standard: `.lovable/memory/coding-standards/go-type-safety.md`
- Spec: `spec/05-golang-standards/04-type-safety-no-any.md`
