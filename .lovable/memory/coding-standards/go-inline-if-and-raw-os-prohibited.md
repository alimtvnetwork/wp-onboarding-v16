# Memory: coding-standards/go-inline-if-and-raw-os-prohibited
Updated: 2026-02-28

## Rule: ALL Inline `if err := ...; err != nil` is PROHIBITED

No exceptions. Every error assignment must be on its own line, then checked on the next line:

```go
// ❌ PROHIBITED — inline if-init even for error
if err := os.Remove(path); err != nil {
    return err
}

// ✅ REQUIRED — separate assignment, then check
err := os.Remove(path)
if err != nil {
    return err
}
```

This replaces the previous exception that allowed `if err := ...; err != nil`. That exception is REVOKED.

## Rule: No Raw `os.Remove` / `os.RemoveAll`

All file/directory removal MUST use `pathutil.RemoveFile`, `pathutil.RemoveDir`, `pathutil.RemoveEntry`, or `pathutil.RemoveFileUnchecked`.

These wrappers:
1. Resolve to absolute path automatically
2. Silently ignore "not found" errors (no need for `os.IsNotExist` checks)
3. Include path AND variable name in error context
4. Return `*apperror.AppError` for proper error chain

```go
// ❌ PROHIBITED
os.Remove(legacyPath)
os.RemoveAll(sessionDir)

// ✅ REQUIRED
pathutil.RemoveFileUnchecked(legacyPath)       // cleanup/defer (no error needed)
appErr := pathutil.RemoveFile(legacyPath, "legacyPath")  // when error matters
appErr := pathutil.RemoveDir(sessionDir, "sessionDir")   // directory removal
```

## Rule: No `os.IsNotExist` Checks

Since `pathutil.RemoveFile`/`RemoveDir` already handle not-found silently, there is NEVER a reason to check `os.IsNotExist` in removal code. Any remaining `os.IsNotExist` usage outside of `pathutil` itself is a violation.

## Rule: No Nested If Blocks

Two levels of `if` nesting are strictly prohibited. Flatten using early returns or extract to helper functions.
