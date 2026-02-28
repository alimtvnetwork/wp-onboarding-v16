# Memory: coding-standards/go-boolean-polarity-and-return-spacing
Updated: 2026-02-28

## Rule: No Mixed Polarity in Boolean Assignments

Never combine a positive condition and a negated condition in the same assignment expression.

```go
hasHttpPrefix := strings.HasPrefix(rawUrl, "http://")
hasHttpsPrefix := strings.HasPrefix(rawUrl, "https://")
hasScheme :=
	hasHttpPrefix ||
	hasHttpsPrefix
```

## Rule: Prefer Positive Branches

Use positive branch checks first, then fallback return.

```go
if hasScheme {
	return rawUrl
}

return "https://" + rawUrl
```

## Rule: Blank Line Before `return` (Conditional)

Insert one blank line before `return` ONLY when there are **preceding statements** (assignments, function calls, etc.) in the same block before the `return`. When `return` is the **sole statement** inside an `if`/`for`/`switch` (immediately after the opening `{`), do **NOT** add a blank line.

The same rule applies to `continue`, `break`, and `throw`.

```go
// ✅ CORRECT — return is sole statement, no blank line
if isMissing {
	return nil
}

// ✅ CORRECT — return has preceding statements, blank line required
result := compute(x)
log.Info("computed", "result", result)

return result

// ✅ CORRECT — multi-line sole return, no blank line
if err != nil {
	return nil, apperror.Wrap(err, apperror.ErrInternal, "failed").
		WithFilePath(path)
}

// ✅ CORRECT — continue is sole statement, no blank line
if err != nil {
	continue
}

// ✅ CORRECT — return after log call (2 statements), blank line before return
if err != nil {
	s.logError(0, "prepare", fmt.Sprintf("Failed: %v", err))

	return apperror.Wrap(err, apperror.ErrFSWrite, "failed")
}

// ❌ WRONG — unnecessary blank line when return is sole statement
if isMissing {

	return nil
}

// ❌ WRONG — unnecessary blank line when continue is sole statement
if err != nil {

	continue
}
```

### Quick Test

Count the statements between `{` and `return`:
- **0 statements** → NO blank line (return is sole)
- **1+ statements** → YES blank line before return
