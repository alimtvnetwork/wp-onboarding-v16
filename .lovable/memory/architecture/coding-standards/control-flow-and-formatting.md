# Memory: architecture/coding-standards/control-flow-and-formatting
Updated: 2026-02-26

Code style across all languages (PHP, TypeScript, Go) is governed by a canonical spec at `spec/03-coding-guidelines/code-style.md` (v3.3.0) with fifteen rules plus sub-rules: (1) mandatory curly braces `{}` for all control structures, (2) no nested `if` blocks — flatten via combined conditions or early returns, (3) extract any `if` condition with 2+ operators into a named boolean variable, method, or constant, (4) blank line before `return` or `throw` unless it's the sole statement in its block, (5) blank line after `}` when more code follows (exception: consecutive `}` or `else`/`catch`), (6) max 15 lines per function body — **(6a) error wrapping chains must use one `.WithX()` per line and are never compressed; if this pushes past 15 lines, extract other logic into helpers**, (7) reinforced zero-tolerance nested-if ban, (8) no leading backslash on global types, (9) **multi-line arguments for signatures, calls, AND arrays when >2 items** — each argument/item on its own line with trailing comma; applies to function signatures (9a), function/method calls (9b), and PHP array literals (9c), (10) blank line before control structures (`if`/`for`/`foreach`/`while`) when preceded by one or more non-brace statements, (11) **no inline `if init; cond {` in Go — ALL forms are prohibited, including `if err := ...; err != nil`**. Every variable assignment (including error checks) must be on its own line, then the condition on the next line. No exceptions. (12) **no raw `os.Stat`** — always use `pathutil` helpers (`StatFile`, `StatDir`, `IsFileExists`, `IsFileMissing`, `FileSize`). (12a) **no raw `os.Remove` / `os.RemoveAll`** — always use `pathutil.RemoveFile`, `pathutil.RemoveDir`, `pathutil.RemoveEntry`, or `pathutil.RemoveFileUnchecked`. These handle not-found silently and include path + variable name in error context. (13) **no magic strings**, (14) **camelCase for all structured log keys**.

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

// ❌ WRONG — inline init for error (NO LONGER EXEMPT)
if err := validate(input); err != nil {
    return err
}

// ✅ CORRECT — error on separate line
err := validate(input)
if err != nil {
    return err
}
```

### Rule 12a Examples (Raw os.Remove Prohibited)

```go
// ❌ WRONG — raw os.Remove with os.IsNotExist check
if err := os.Remove(legacyPath); err != nil {
    isRealError := !os.IsNotExist(err)
    if isRealError {
        return apperror.Wrap(err, apperror.ErrSessionDelete, "delete session log")
    }
}

// ✅ CORRECT — pathutil handles not-found silently, includes var name
appErr := pathutil.RemoveFile(legacyPath, "legacyPath")
if appErr != nil {
    return appErr
}

// ✅ CORRECT — cleanup/defer (no error needed)
defer pathutil.RemoveFileUnchecked(tempPath)
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
