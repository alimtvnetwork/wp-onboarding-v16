# Memory: coding-standards/go-enum-external-switch-prohibition
Updated: 2026-02-28

## Rule: External Switch Over Enum Variants is FORBIDDEN (E1)

Never write `switch` statements over enum variants outside the enum package to derive string values, map variants, or compute derived properties. All such logic MUST be a method on the `Variant` type inside the enum package itself (e.g., `v.Stage()`, `v.Category()`, `v.Parent()`).

External switches duplicate enum knowledge and silently break when new variants are added. The enum is the single source of truth for all variant-dependent mappings.

### Correct Pattern
```go
// Inside enum package: use a map + method
var stageMap = map[Variant]Variant{
    Packaging: Package,
    Uploading: Upload,
}

func (v Variant) Stage() string {
    if base, isFound := stageMap[v]; isFound {
        return base.Value()
    }
    return v.Value()
}
```

### Forbidden Pattern
```go
// Outside enum package: NEVER do this
func mapStepToStage(step publishstep.Variant) string {
    switch step {
    case publishstep.Packaging:
        return publishstep.Package.Value()
    // ...
    }
}
```
