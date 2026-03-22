# Memory: Go Type Safety Standards

> **Updated:** 2026-03-22

---

## Rule: No `any` or `interface{}` in Production Go Code

**All production Go code must use specific types or bounded generics.** The use of `any` (alias for `interface{}`) is strictly prohibited except:

1. **File I/O / JSON unmarshalling** — initial decode target only, must be converted to typed struct immediately
2. **Test files** (`_test.go`) — unrestricted
3. **Third-party library boundaries** — matching external signatures
4. **Generic type constraints** — `[T any]` in generic functions/structs is correct Go
5. **Logger variadic keyvals** — `keyvals ...any` follows the standard `log/slog` pattern
6. **Runtime JSON containers** — `Message.Data any` in ws.Hub where typed generics feed into a single channel
7. **PHP envelope unwrap** — `UnwrapPhpEnvelope(map[string]any) any` handles dynamic PHP JSON structures

### Key Patterns

- Handler returns: Use typed structs (e.g., `*SiteSettings`) instead of `any`
- Maps: Use typed structs instead of `map[string]any`
- Slices: Use typed slices (e.g., `[]PluginStatus`) instead of `[]any`
- Generics: Use `Result[T]` pattern for generic containers
- Service getters: Use typed interface (e.g., `SiteServiceInterface`) instead of `func() any`
- WebSocket broadcasts: Use typed methods (e.g., `BroadcastRemoteActionStarted`) instead of `BroadcastWithSession(data any)`
- Request bodies: `Body any` in `ApiCallInput` is justified — mirrors `json.Marshal` signature

### Status: ✅ Complete

All 6 phases of the type safety refactoring are complete (G-1 through G-6). All remaining `any` usage has been audited and is justified per the exceptions above.

### Enforcement

- Spec: `spec/05-golang-standards/04-type-safety-no-any.md`
- CI lint rule planned: `go-no-any` in `tools/consistency-checker/rules.json`
- Issue: `spec/02-app-issues/38-go-type-safety-any-elimination.md` — **Resolved**
