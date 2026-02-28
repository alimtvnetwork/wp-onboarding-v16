# Memory: architecture/coding-standards/go-enum-methods
Updated: 2026-02-28

---

## Package Naming Convention

Go enum packages live under `backend/internal/enums/` and MUST use a **lowercase `type` suffix** for the directory name:

| Package Directory | Import Alias (if needed) | Example Constants |
|---|---|---|
| `actiontype` | `action` | `action.Upload` |
| `endpointtype` | `ep` | `ep.Upload` |
| `httpmethodtype` | `httpmethod` | `httpmethod.Get` |
| `stagestatustype` | `stagestatus` | `stagestatus.Running` |
| `logleveltype` | `loglevel` | `loglevel.Info` |
| `publishsteptype` | `publishstep` | `publishstep.Upload` |
| `uploadsourcetype` | `uploadsource` | `uploadsource.Script` |
| `statustype` | `enumstatus` | `enumstatus.Success` |
| `connectionsteptype` | `connectionstep` | `connectionstep.Validate` |
| `healthstatustype` | `healthstatus` | `healthstatus.Healthy` |
| `responsemessagetype` | `responsemessage` | `responsemessage.Ok` |
| `publishtype` | (same) | `publishtype.Full` |
| `backuptype` | (same) | `backuptype.Full` |

**Rules:**
- Directory name = all-lowercase, no underscores, ends with `type` (e.g., `httpmethodtype`)
- Each package contains two files: `{packagename}.go` (type + methods) and `Variant.go` (constants)
- Use import aliases to keep call sites readable when the full package name is verbose
- PHP counterpart classes use PascalCase `Type` suffix (e.g., `HttpMethodType`)

---

Every Go enum `Variant` type under `backend/internal/enums/` MUST implement these standard methods:

### Required instance methods
- `String() string` — for non-protocol enums returns PascalCase label; for protocol enums delegates to `Value()`
- `Label() string` — always returns PascalCase identity from `variantLabels`
- `Value() string` — (protocol enums only) returns the wire/technical value from `variantValues`
- `IsValid() bool` — `v > Invalid && v < Variant(len(variantLabels))`
- `IsInvalid() bool` — `v == Invalid`
- `IsDefined() bool` — `v != Invalid` (positive counterpart of IsInvalid; prefer this over `!IsInvalid()`)
- `IsDefinedAndValid() bool` — `v.IsDefined() && v.IsValid()` (combines existence + range check)
- `IsOther(other Variant) bool` — `v != other`
- `IsAnyOf(others ...Variant) bool` — membership check
- `Is<CaseName>() bool` — one per non-Invalid case
- `MarshalJSON() ([]byte, error)` — for protocol enums uses `Value()`, otherwise `String()`
- `UnmarshalJSON(data []byte) error`

### Required package-level functions
- `All() []Variant` — all valid variants (excluding Invalid)
- `ByIndex(i int) Variant` — safe index lookup
- `Parse(s string) (Variant, error)` — checks both `variantLabels` (EqualFold) and `variantValues` (exact match)
- `Values() []string` — string labels of all valid variants

### PascalCase Label Rule (v4.2.0)

**All** `variantLabels` entries MUST use PascalCase matching the Go constant name. No exceptions.

For enums whose variants carry a technical wire value (URL paths, MIME types, HTTP headers, JSON keys, display messages), a separate `variantValues` array stores the wire string. These are called **protocol enums** and include: `endpointtype`, `contenttype`, `headertype`, `connectionsteptype`, `responsekeytype`, `responsemessagetype`.

- `String()` delegates to `Value()` for backwards compatibility with callers using Stringer
- `Label()` returns PascalCase from `variantLabels`
- `Value()` returns the wire format from `variantValues`
- `Parse()` checks both arrays

### Usage guidance
- **Always prefer `IsDefined()`** over `!IsInvalid()` — aligns with boolean-principles P2/P3
- **Use `IsDefinedAndValid()`** when confirming both set + in range
- **Use `Label()`** for logging/display identity
- **Use `Value()`** (or `String()`) for wire/protocol output
