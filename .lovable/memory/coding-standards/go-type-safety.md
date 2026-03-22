# Memory: Go Type Safety Standards

> **Updated:** 2026-03-22

---

## Rule: No `any` or `interface{}` in Production Go Code

**All production Go code must use specific types or bounded generics.** The use of `any` (alias for `interface{}`) is strictly prohibited except:

1. **File I/O / JSON unmarshalling** — initial decode target only, must be converted to typed struct immediately
2. **Test files** (`_test.go`) — unrestricted
3. **Third-party library boundaries** — matching external signatures

### Key Patterns

- Handler returns: Use typed structs (e.g., `*SiteSettings`) instead of `any`
- Maps: Use typed structs instead of `map[string]any`
- Slices: Use typed slices (e.g., `[]PluginStatus`) instead of `[]any`
- Generics: Use `Result[T]` pattern for generic containers
- Service getters: Use typed interface (e.g., `SiteServiceInterface`) instead of `func() any`

### Enforcement

- Spec: `spec/05-golang-standards/04-type-safety-no-any.md`
- CI lint rule planned: `go-no-any` in `tools/consistency-checker/rules.json`
- Current violations: ~259 across 88 files (refactoring plan in `.lovable/plan.md`)

### Refactoring Order

1. `pkg/apperror` + `pkg/dbutil` — generic Result[T] foundations
2. `internal/api/handlers/Response.go` + `ResponseTypes.go` — typed response envelope
3. `internal/api/handlers/HandlerFactory.go` — generic handler factories
4. `internal/api/handlers/AdapterSite.go` — typed adapter interface
5. All service files — replace `map[string]any` with typed structs
6. WordPress client + WebSocket — final cleanup
