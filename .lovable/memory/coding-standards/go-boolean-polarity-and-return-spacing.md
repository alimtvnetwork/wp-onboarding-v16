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

## Rule: Blank Line Before `return`

Insert one blank line before each `return`, including returns inside `if` blocks.
