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

Insert one blank line before `return` ONLY when there are preceding statements in the same block. When `return` is the **sole statement** inside an `if` (immediately after the opening `{`), do NOT add a blank line.

```go
// ✅ CORRECT — return is sole statement, no blank line
if isMissing {
	return nil
}

// ✅ CORRECT — return has preceding statements, blank line required
result := compute(x)
log.Info("computed", "result", result)

return result

// ❌ WRONG — unnecessary blank line when return is sole statement
if isMissing {

	return nil
}
```
