# Memory: architecture/coding-standards/go-enum-methods
Updated: 2026-02-25

---

Every Go enum `Variant` type under `backend/internal/enums/` MUST implement these standard methods:

### Required instance methods
- `String() string` — human-readable label
- `Label() string` — alias for String()
- `IsValid() bool` — `v > Invalid && v < Variant(len(variantLabels))`
- `IsInvalid() bool` — `v == Invalid`
- `IsDefined() bool` — `v != Invalid` (positive counterpart of IsInvalid; prefer this over `!IsInvalid()`)
- `IsDefinedAndValid() bool` — `v.IsDefined() && v.IsValid()` (combines existence + range check)
- `IsOther(other Variant) bool` — `v != other`
- `IsAnyOf(others ...Variant) bool` — membership check
- `Is<CaseName>() bool` — one per non-Invalid case
- `MarshalJSON() ([]byte, error)`
- `UnmarshalJSON(data []byte) error`

### Required package-level functions
- `All() []Variant` — all valid variants (excluding Invalid)
- `ByIndex(i int) Variant` — safe index lookup
- `Parse(s string) (Variant, error)` — string-to-variant conversion
- `Values() []string` — string labels of all valid variants

### Usage guidance
- **Always prefer `IsDefined()`** over `!IsInvalid()` — aligns with the boolean-principles P2/P3 (no negation at call sites).
- **Use `IsDefinedAndValid()`** when you need to confirm both that the value was explicitly set AND falls within the known range.
