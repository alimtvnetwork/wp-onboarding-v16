# Memory: architecture/coding-standards/go-enum-methods
Updated: 2026-02-25

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

For enums whose variants carry a technical wire value (URL paths, MIME types, HTTP headers, JSON keys, display messages), a separate `variantValues` array stores the wire string. These are called **protocol enums** and include: `endpoint`, `content_type`, `header`, `connection_step`, `response_key`, `response_message`.

- `String()` delegates to `Value()` for backwards compatibility with callers using Stringer
- `Label()` returns PascalCase from `variantLabels`
- `Value()` returns the wire format from `variantValues`
- `Parse()` checks both arrays

### Usage guidance
- **Always prefer `IsDefined()`** over `!IsInvalid()` — aligns with boolean-principles P2/P3
- **Use `IsDefinedAndValid()`** when confirming both set + in range
- **Use `Label()`** for logging/display identity
- **Use `Value()`** (or `String()`) for wire/protocol output
