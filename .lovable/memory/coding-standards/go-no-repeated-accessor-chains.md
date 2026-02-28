# Memory: coding-standards/go-no-repeated-accessor-chains
Updated: 2026-02-28

## Rule: Extract Repeated Accessor Results to Variables

When an accessor method (e.g., `.Value()`, `.Error()`, `.Result()`) is called on the same object more than once, the result **must** be saved to a local variable first.

### ❌ Bad — repeated `.Value()` calls inline

```go
wpClient := s.wpClientFactory(siteInfoResult.Value().Url, siteInfoResult.Value().Username, password)
```

### ✅ Good — extract once, reuse variable

```go
siteInfo := siteInfoResult.Value()
wpClient := s.wpClientFactory(siteInfo.Url, siteInfo.Username, password)
```

### Why

- **Readability**: One clear assignment communicates "we have the value" — subsequent field accesses are short and scannable.
- **Safety**: Prevents accidental divergence if the accessor has side effects or if the underlying value changes.
- **Consistency**: Matches the existing pattern used throughout the codebase (e.g., `creds := credsResult.Value()`).

### Scope

Applies to **all** Go code in the backend. A single `.Value().Field` access is acceptable when the result is used only once; the rule triggers when the **same accessor** would be called **two or more times**.
