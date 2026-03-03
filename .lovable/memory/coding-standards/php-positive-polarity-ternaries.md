# Memory: coding-standards/php-positive-polarity-ternaries
Updated: 2026-03-03

## Rule: No Raw Comparisons in Ternary Conditions

Ternary conditions **MUST NOT** contain raw comparisons like `!== false`, `=== null`, `!== null`, `=== true`, etc. Extract them to a named boolean variable on the preceding line with positive polarity.

### ❌ FORBIDDEN

```php
// Raw comparison in ternary — unreadable, no semantic meaning
return ($val !== false) ? $val : $default;
return ($content !== false) ? json_decode($content, true) : null;
return ($result === null) ? $fallback : $result;
```

### ✅ REQUIRED

```php
// Named boolean with positive polarity, then clean ternary
$isFound = ($val !== false);
return $isFound ? $val : $default;

$isReadSuccess = ($content !== false);
return $isReadSuccess ? json_decode($content, true) : null;

$hasResult = ($result !== null);
return $hasResult ? $result : $fallback;
```

## Rules

1. **Every raw comparison** (`!== false`, `=== null`, `!== null`, `=== true`, `=== 0`, etc.) in a ternary condition must be extracted to a named boolean on the preceding line
2. **Positive polarity only** — use `$isFound`, `$isReadSuccess`, `$hasResult` — never `$isNotFound`, `$isMissing`
3. **The ternary itself uses only the boolean** — `return $isFound ? $val : $default;`
4. This applies to all PHP code across all plugins: `riseup-asia-uploader`, `qupload`, `plugins-onboard`
5. Aligns with Go standard: raw comparisons must be extracted to named variables (see `go-logic-and-formatting-standards`)
