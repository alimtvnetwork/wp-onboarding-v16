# Response Shape Consistency

**Version**: 1.0.0
**Status**: Active
**Applies to**: Go HTTP handlers calling `respondSuccess` (`backend/`, `licensing/`)
**Lint script**: `scripts/lint-response-shape.sh`
**CI jobs**: `.github/workflows/go-lint.yml` → `lint-backend`, `lint-licensing`, `lint-response-shape-advisory`
**Related**: [readme.md](./readme.md) (GE-2 Zero Loose Types), [golang.md](./golang.md), [changelog.md](./changelog.md)

---

## 1. Why This Rule Exists

Go HTTP handlers in this project funnel every successful JSON response through a single helper:

```go
respondSuccess(w, payload)
```

`payload` is `any`, so the compiler accepts **anything**: a typed struct, a bare map literal, an anonymous struct literal, a slice, `nil`. That flexibility is also the problem — without discipline, a single endpoint can return three different shapes across three branches of the same handler, and the only thing that catches it is a frontend runtime crash or a manual audit.

This document codifies the rule, the automated checks that enforce it, and the escape hatches.

---

## 2. The Hard Rule (Blocking)

> **Within a single handler function, every `respondSuccess(w, X)` call MUST pass a value of the same concrete top-level type.**

### What's checked

`scripts/lint-response-shape.sh` parses each Go file in `backend/` and `licensing/`, walks every function that calls `respondSuccess`, and categorizes each payload expression into one of:

| Category | Example |
|---|---|
| `map_literal` | `map[string]any{"ok": true}` |
| `struct_literal\|TypeName` | `ActionResponse{IsDeleted: true}`, `&site.PreflightSiteResult{...}`, `pkg.Foo{...}` |
| `nil` | `respondSuccess(w, nil)` |
| `slice` | `[]Plugin{...}`, `make([]X, 0)` |
| `empty_slice` | `[]struct{}{}`, `[]any{}`, `[]T{}` (nil-safe placeholder) |
| `identifier` | bare variable — type resolved via local declaration heuristics |

If a function's branches mix two **different** categories (e.g. `map_literal` + `struct_literal`, or `struct_literal|FlatScanResponse` + `struct_literal|ScanEnvelope`), the lint **fails the build**.

### Exempted combinations

These are *not* treated as a mixed shape:

- Anything **+ `nil`** — early `respondSuccess(w, nil)` for "no body" success.
- Anything **+ `empty_slice`** — `[]T{}` is the JSON-equivalent fallback for a populated `slice` of the same element type.
- Two `struct_literal|TypeName` entries with the **same** `TypeName` — they're the same shape by construction.

### Enforced in

- `backend/Makefile` → `make lint-all`, `make lint-response-shape`
- `scripts/pre-commit.sh`
- `.github/workflows/go-lint.yml` → `lint-backend` and `lint-licensing` jobs (blocking)

### Historical motivation

This check was added in **v2.39.0** after the `ScanDirectoryPath` regression (v2.37.0): one branch returned a flat object `{path, isValid, pluginName, ...}`, the other returned a `{Scan, IsDetectionCreated}` envelope. The frontend crashed on the second shape because it could not unwrap `Scan`. The lint catches this entire regression class at commit time.

---

## 3. The Soft Warnings (Advisory)

Even when a handler is *internally consistent*, it can still be sloppy: a bare `map[string]any` literal or an anonymous `struct{...}{...}` literal compiles fine, ships to clients, and silently drifts when fields are added or renamed. There is no compile-time guarantee, no shared type to reference in tests, and no way to generate a clean OpenAPI schema.

Two soft warnings flag these patterns. They **print a yellow advisory** but exit `0` by default.

### 3.1 Bare map literal in `respondSuccess` *(added v2.42.0)*

```go
// ⚠️ Triggers a soft warning
respondSuccess(w, map[string]any{
    "isOk":   true,
    "count":  n,
})

// ⚠️ Also triggers — same problem with map[string]bool / map[string]interface{}
respondSuccess(w, map[string]bool{"deleted": true})
```

**Detection regex** (single + multi-line):
```
respondSuccess\([^,]+,[[:space:]]*(&)?map\[string\](any|bool|interface\{\})\{
```

### 3.2 Anonymous struct literal in `respondSuccess` *(added v2.43.0)*

```go
// ⚠️ Triggers a soft warning
respondSuccess(w, struct {
    IsOk  bool `json:"isOk"`
    Count int  `json:"count"`
}{IsOk: true, Count: n})

// ⚠️ Pointer variant also triggers
respondSuccess(w, &struct{ X int }{X: 1})
```

**Detection regex**:
```
respondSuccess\([^,]+,[[:space:]]*(&)?struct[[:space:]]*\{
```

### Why these are warnings, not errors

Both patterns are technically *consistent* within a function (the hard rule wouldn't trip). They become problems over time:

- **No compile-time key check across files** — typos in JSON keys aren't caught.
- **Not referenceable** — tests, OpenAPI, and frontend type-gen can't import the shape.
- **Hidden coupling** — adding a field requires editing every handler that built the literal.

The **fix** is always the same: promote to a named struct in `backend/internal/api/handlers/ResponseTypes.go` (or the equivalent file in `licensing/`).

```go
// ResponseTypes.go
type ActionResponse struct {
    IsOk      bool   `json:"isOk,omitempty"`
    IsDeleted bool   `json:"isDeleted,omitempty"`
    IsUpdated bool   `json:"isUpdated,omitempty"`
    Count     int    `json:"count,omitempty"`
    Message   string `json:"message,omitempty"`
    // ...
}

// Handler:
respondSuccess(w, ActionResponse{IsDeleted: true})
```

### Warning output format

The script groups hits by kind so authors know which anti-pattern they hit:

```
⚠ Response Shape Advisory
    • 2 bare map[string]any/bool/interface{} literal(s)
    • 1 anonymous struct{...}{...} literal(s)

  [map] backend/internal/api/handlers/Foo.go:42: respondSuccess(w, map[string]any{...})
  [map] backend/internal/api/handlers/Bar.go:88: respondSuccess(w, map[string]bool{"x": true})
  [struct] backend/internal/api/handlers/Baz.go:17: respondSuccess(w, struct{ X int }{X: 1})

  Recommendation: promote to a named struct in ResponseTypes.go.
  Why: named typed structs catch key typos at compile time, document the
       contract for the frontend, and let OpenAPI/test code reference the shape.
```

---

## 4. Strict Mode: `RESPONSE_SHAPE_STRICT=1`

Set this environment variable to escalate **both soft warnings** to fatal errors:

```bash
RESPONSE_SHAPE_STRICT=1 bash scripts/lint-response-shape.sh
# ✗ RESPONSE_SHAPE_STRICT=1 — escalating warnings to errors
# exit code: 1
```

Use cases:
- **Local hardening pass** — run before a refactor that promotes literals to structs, then keep the var set in your shell to prevent backsliding mid-refactor.
- **Future global flip** — once the codebase reports zero advisory warnings on `main` for several weeks, the toggle can be promoted from the advisory job to the blocking jobs by removing `continue-on-error: true` and exporting `RESPONSE_SHAPE_STRICT=1` in the `lint-backend` / `lint-licensing` steps.

The toggle does **not** affect the hard "mixed shape" check — that always exits non-zero on violation.

---

## 5. The Advisory CI Job *(added v2.44.0)*

`.github/workflows/go-lint.yml` defines a third, **non-blocking** job:

```yaml
lint-response-shape-advisory:
  name: "Advisory: Response Shape (strict)"
  continue-on-error: true              # never blocks the pipeline
  strategy:
    fail-fast: false                   # backend & licensing report independently
    matrix:
      target:
        - { name: backend,   dir: backend }
        - { name: licensing, dir: licensing }
  steps:
    - uses: actions/checkout@v4
    - name: Lint — Response Shape (strict, advisory) — ${{ matrix.target.name }}
      env:
        RESPONSE_SHAPE_STRICT: '1'
      run: bash scripts/lint-response-shape.sh --dir ${{ matrix.target.dir }}
```

### Behavior on a PR

| Scenario | `lint-backend` | `lint-response-shape-advisory` | PR status |
|---|---|---|---|
| Clean code | ✅ green | ✅ green | mergeable |
| Soft warning only (bare map literal added) | ✅ green | 🟡 failed (advisory) | **mergeable** — yellow check is informational |
| Hard violation (mixed shapes in one handler) | ❌ red | 🟡 failed (advisory) | **blocked** by the blocking job |

The advisory job exists to **surface drift early** without forcing an emergency fix. When it goes yellow, the team has a normal review cycle to either promote the literal to a typed struct or accept the warning for that PR.

---

## 6. Workflow: Adding a New Handler

1. Define the response shape as a named struct in `ResponseTypes.go`:
   ```go
   type MyEndpointResponse struct {
       Items []Item `json:"items"`
       Total int    `json:"total"`
   }
   ```
2. Return it consistently from every branch:
   ```go
   func MyEndpoint(w http.ResponseWriter, r *http.Request) {
       if err := ...; err != nil {
           respondError(w, err, "E1234")
           return
       }
       respondSuccess(w, MyEndpointResponse{Items: items, Total: len(items)})
   }
   ```
3. Run `bash scripts/lint-response-shape.sh` locally — both the hard check and the advisory should report zero issues.

### What NOT to do

```go
// ❌ Hard fail: mixed shapes
if flat {
    respondSuccess(w, FlatScanResponse{...})
} else {
    respondSuccess(w, ScanEnvelope{...})   // different concrete type
}

// ⚠️ Soft warning: bare map literal
respondSuccess(w, map[string]any{"items": items})

// ⚠️ Soft warning: anonymous struct literal
respondSuccess(w, struct{ Items []Item }{Items: items})
```

---

## 7. Exemptions

The lint intentionally tolerates a few patterns:

| Pattern | Why it's OK |
|---|---|
| `respondSuccess(w, nil)` | "No body" success; can pair with any populated branch. |
| `respondSuccess(w, []T{})` / `[]any{}` / `[]struct{}{}` | Nil-safe empty-list fallback; JSON-equivalent to a populated slice. |
| Two branches returning the **same** named struct | Same `TypeName` ⇒ same shape. |
| `map[string]any` **outside** `respondSuccess` | E.g. dynamic PHP request body decode targets. Not in scope — only response-side shape is enforced. |

If you need a genuine exemption (e.g. a webhook proxy that forwards an opaque upstream JSON body), document it inline:

```go
// lint:ignore response-shape — opaque upstream payload, no internal contract
respondSuccess(w, upstreamBody)
```

The script does not currently parse `lint:ignore` directives; the comment is for human reviewers. If exemptions become common, the script can be extended to honor a `// lint:ignore response-shape` marker on the same or preceding line.

---

## 8. Version History

| Version | Change |
|---|---|
| **v2.37.0** | Bug: `ScanDirectoryPath` returned mixed shapes — frontend crash. |
| **v2.38.0** | Manual audit + fix: every handler returns one stable shape per endpoint. |
| **v2.39.0** | Hard lint added: `scripts/lint-response-shape.sh` + Makefile + CI + pre-commit. |
| **v2.40.0** | `buildFlatScanResponse` promoted to typed `FlatScanResponse` struct. |
| **v2.41.0** | Audit: zero `map[string]any` / `map[string]bool` literals remain in any handler. |
| **v2.42.0** | Soft warning: bare map literals in `respondSuccess`. |
| **v2.43.0** | Soft warning extended: anonymous `struct{...}{...}` literals. |
| **v2.44.0** | Advisory CI job (`lint-response-shape-advisory`, `continue-on-error: true`, `RESPONSE_SHAPE_STRICT=1`). |
| **v2.45.0** | This spec document. |

---

## 9. Quick Reference

```bash
# Default: hard check blocks, soft warnings print
bash scripts/lint-response-shape.sh

# Strict: soft warnings also block
RESPONSE_SHAPE_STRICT=1 bash scripts/lint-response-shape.sh

# Scope to a single tree
bash scripts/lint-response-shape.sh --dir backend
bash scripts/lint-response-shape.sh --dir licensing

# Via Makefile
cd backend && make lint-response-shape
cd backend && make lint-all
```

| Signal | Meaning | Blocks? |
|---|---|---|
| ✗ mixed shape | Two concrete types in one handler | ✅ blocks (always) |
| ⚠ map literal | Bare `map[string]any/bool/interface{}` in `respondSuccess` | ⛔ no (✅ blocks under `RESPONSE_SHAPE_STRICT=1`) |
| ⚠ struct literal | Anonymous `struct{...}{...}` in `respondSuccess` | ⛔ no (✅ blocks under `RESPONSE_SHAPE_STRICT=1`) |
| ✓ all consistent | Every handler returns one named struct (or exempted combo) | — |
