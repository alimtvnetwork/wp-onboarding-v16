# Memory: architecture/coding-standards/control-flow-and-formatting
Updated: 2026-02-26

Code style across all languages (PHP, TypeScript, Go) is governed by a canonical spec at `spec/03-coding-guidelines/code-style.md` (v3.2.0) with fifteen rules plus sub-rules: (1) mandatory curly braces `{}` for all control structures, (2) no nested `if` blocks — flatten via combined conditions or early returns, (3) extract any `if` condition with 2+ operators into a named boolean variable, method, or constant, (4) blank line before `return` or `throw` unless it's the sole statement in its block, (5) blank line after `}` when more code follows (exception: consecutive `}` or `else`/`catch`), (6) max 15 lines per function body — **(6a) error wrapping chains must use one `.WithX()` per line and are never compressed; if this pushes past 15 lines, extract other logic into helpers**, (7) reinforced zero-tolerance nested-if ban, (8) no leading backslash on global types, (9) **multi-line arguments for signatures, calls, AND arrays when >2 items** — each argument/item on its own line with trailing comma; applies to function signatures (9a), function/method calls (9b), and PHP array literals (9c), (10) blank line before control structures (`if`/`for`/`foreach`/`while`) when preceded by one or more non-brace statements, (11) **no inline `if init; cond {` for ANY variable assignment in Go** — always declare the variable on a separate line, then check the condition on the next line. The only permitted exception is `if err := ...; err != nil` for error checks, which is idiomatic Go. All other inline init patterns are banned. Stat calls must use `pathutil.StatFile` / `pathutil.StatDir`, (12) **no raw `os.Stat`** — always use `pathutil` helpers (`StatFile`, `StatDir`, `IsFileExists`, `IsFileMissing`, `FileSize`) which resolve to absolute paths and return `*apperror.AppError`. Raw `os.Stat` is only permitted inside `pathutil` itself and test files, (13) **no magic strings** — all string literals used as keys, step identifiers, status values, or category labels must be defined as typed enum constants or named constants. Never pass raw string literals like `"fetch_site"` or `"api_test"` directly; use the corresponding enum's `.String()` or `.Value()` method, (14) **camelCase for all structured log keys** — logger key arguments must use camelCase (`"durationMs"`, `"filesCount"`, `"totalBytes"`, `"dbId"`), never snake_case (`"duration_ms"`, `"files_count"`). This applies to all `s.log.Info/Debug/Warn/Error` calls.

### Rule 11 Examples

```go
// ❌ WRONG — inline init for non-error variable
if userId := GetUserId(ctx); userId != "" {
    args = append(args, "user_id", userId)
}

// ✅ CORRECT — separate declaration, then condition
userId := GetUserId(ctx)
if userId != "" {
    args = append(args, "userId", userId)
}

// ❌ WRONG — inline init for function result
if resolved := resolveScriptPath(path); resolved != "" {
    return resolved
}

// ✅ CORRECT
resolved := resolveScriptPath(path)
if resolved != "" {
    return resolved
}

// ✅ OK — error check inline is the sole exception
if err := validate(input); err != nil {
    return err
}
```

### Rule 13 Examples

```go
// ❌ WRONG — magic string for step identifier
Step: "fetch_site",

// ✅ CORRECT — use typed enum constant
Step: connectionstep.FetchSite.String(),

// ❌ WRONG — magic string for log key with snake_case
s.log.Info("done", "user_id", userId)

// ✅ CORRECT — camelCase log key
s.log.Info("done", "userId", userId)
```

### Rule 14 Examples

```go
// ❌ WRONG — snake_case logger keys
s.log.Info("Export complete",
    "files_count", filesCount,
    "total_bytes", totalBytes,
    "duration_ms", duration.Milliseconds(),
)

// ✅ CORRECT — camelCase logger keys
s.log.Info("Export complete",
    "filesCount", filesCount,
    "totalBytes", totalBytes,
    "durationMs", duration.Milliseconds(),
)
```
