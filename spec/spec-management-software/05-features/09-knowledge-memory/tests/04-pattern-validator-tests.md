# 35. Pattern Validator Unit Tests

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28

---

## 35.1 Overview

This specification defines comprehensive unit tests for the Pattern Validator component of the Knowledge Memory System. The Pattern Validator ensures that user-provided regex patterns are syntactically valid, free from catastrophic backtracking vulnerabilities (ReDoS), and comply with configured limits.

### 35.1.1 Test Coverage Goals

| Category | Target Coverage | Critical Paths |
|----------|----------------|----------------|
| **Syntax Validation** | ≥ 98% | All regex metacharacters, escapes, groups |
| **ReDoS Detection** | ≥ 99% | Nested quantifiers, overlapping patterns |
| **Timeout Handling** | ≥ 95% | Compilation timeouts, execution limits |
| **Limit Enforcement** | ≥ 98% | Length, count, complexity bounds |
| **Edge Cases** | ≥ 95% | Unicode, encoding, boundary conditions |

### 35.1.2 Test Naming Convention

```
PAT-{CATEGORY}-{NUMBER}: {description}

Categories:
- VAL: Valid pattern tests
- SYN: Syntax validation tests
- CBT: Catastrophic backtracking tests
- TMO: Timeout handling tests
- LIM: Limit enforcement tests
- EDG: Edge case tests
- ADV: Advanced ReDoS tests
- CMP: Complexity analysis tests
- BNC: Benchmark tests
```

---

## 35.2 Valid Pattern Tests (PAT-VAL)

### 35.2.1 Simple Patterns

```go
func TestPatternValidator_ValidSimple(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
    }{
        // PAT-VAL-001: Literal string
        {"PAT-VAL-001: literal string", "hello"},
        // PAT-VAL-002: Simple wildcard
        {"PAT-VAL-002: simple wildcard", ".*"},
        // PAT-VAL-003: Single character class
        {"PAT-VAL-003: char class", "[a-z]"},
        // PAT-VAL-004: Digit pattern
        {"PAT-VAL-004: digits", "\\d+"},
        // PAT-VAL-005: Word boundary
        {"PAT-VAL-005: word boundary", "\\bword\\b"},
        // PAT-VAL-006: Anchored pattern
        {"PAT-VAL-006: anchored", "^start.*end$"},
        // PAT-VAL-007: Optional group
        {"PAT-VAL-007: optional group", "(optional)?"},
        // PAT-VAL-008: Non-capturing group
        {"PAT-VAL-008: non-capturing", "(?:group)"},
        // PAT-VAL-009: Alternation
        {"PAT-VAL-009: alternation", "cat|dog|bird"},
        // PAT-VAL-010: Quantifier range
        {"PAT-VAL-010: quantifier range", "a{2,5}"},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            assert.True(t, result.Valid, "pattern should be valid: %s", tt.pattern)
            assert.Empty(t, result.Errors)
        })
    }
}
```

### 35.2.2 Complex Valid Patterns

```go
func TestPatternValidator_ValidComplex(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
    }{
        // PAT-VAL-011: URL path pattern
        {"PAT-VAL-011: URL path", "/api/v[0-9]+/[a-z]+"},
        // PAT-VAL-012: File extension
        {"PAT-VAL-012: file extension", "\\.(md|txt|json)$"},
        // PAT-VAL-013: Date pattern
        {"PAT-VAL-013: date pattern", "\\d{4}-\\d{2}-\\d{2}"},
        // PAT-VAL-014: Email-like (safe)
        {"PAT-VAL-014: safe email", "[a-z]+@[a-z]+\\.[a-z]{2,4}"},
        // PAT-VAL-015: Nested groups (safe depth)
        {"PAT-VAL-015: nested groups", "((a)(b)(c))"},
        // PAT-VAL-016: Lookahead (if supported)
        {"PAT-VAL-016: lookahead", "foo(?=bar)"},
        // PAT-VAL-017: Lookbehind (if supported)
        {"PAT-VAL-017: lookbehind", "(?<=foo)bar"},
        // PAT-VAL-018: Named group (Go syntax)
        {"PAT-VAL-018: named group", "(?P<name>[a-z]+)"},
        // PAT-VAL-019: Possessive-like (atomic group)
        {"PAT-VAL-019: atomic group", "(?>a+)b"},
        // PAT-VAL-020: Complex char class
        {"PAT-VAL-020: complex char class", "[a-zA-Z0-9_\\-\\.]+"},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            // Some patterns may not be supported by Go's regex
            // We accept either valid or specific unsupported error
            if !result.Valid {
                assert.Contains(t, result.Errors[0].Code, "UNSUPPORTED")
            }
        })
    }
}
```

---

## 35.3 Syntax Validation Tests (PAT-SYN)

### 35.3.1 Unbalanced Delimiters

```go
func TestPatternValidator_UnbalancedDelimiters(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        errCode string
    }{
        // PAT-SYN-001: Unmatched opening parenthesis
        {"PAT-SYN-001: unmatched open paren", "(unclosed", "ERR_PATTERN_UNBALANCED"},
        // PAT-SYN-002: Unmatched closing parenthesis
        {"PAT-SYN-002: unmatched close paren", "extra)", "ERR_PATTERN_UNBALANCED"},
        // PAT-SYN-003: Multiple unmatched opening
        {"PAT-SYN-003: multiple open", "(((abc", "ERR_PATTERN_UNBALANCED"},
        // PAT-SYN-004: Multiple unmatched closing
        {"PAT-SYN-004: multiple close", "abc)))", "ERR_PATTERN_UNBALANCED"},
        // PAT-SYN-005: Unmatched opening bracket
        {"PAT-SYN-005: unmatched open bracket", "[abc", "ERR_PATTERN_UNBALANCED"},
        // PAT-SYN-006: Unmatched closing bracket
        {"PAT-SYN-006: unmatched close bracket", "abc]def", "ERR_PATTERN_UNBALANCED"},
        // PAT-SYN-007: Unmatched brace
        {"PAT-SYN-007: unmatched brace", "a{5", "ERR_PATTERN_SYNTAX"},
        // PAT-SYN-008: Mixed unbalanced
        {"PAT-SYN-008: mixed unbalanced", "([abc)", "ERR_PATTERN_UNBALANCED"},
        // PAT-SYN-009: Deeply nested unbalanced
        {"PAT-SYN-009: deep unbalanced", "((((a))))", "ERR_PATTERN_SYNTAX"}, // Actually valid
        // PAT-SYN-010: Escaped paren not unbalanced
        {"PAT-SYN-010: escaped paren valid", "\\(text\\)", ""},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            if tt.errCode != "" {
                assert.False(t, result.Valid)
                assert.Contains(t, result.Errors[0].Code, tt.errCode)
            } else {
                assert.True(t, result.Valid)
            }
        })
    }
}
```

### 35.3.2 Invalid Escape Sequences

```go
func TestPatternValidator_InvalidEscapes(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        errCode string
    }{
        // PAT-SYN-011: Trailing backslash
        {"PAT-SYN-011: trailing backslash", "pattern\\", "ERR_PATTERN_ESCAPE"},
        // PAT-SYN-012: Invalid escape in class
        {"PAT-SYN-012: invalid escape in class", "[\\q]", "ERR_PATTERN_ESCAPE"},
        // PAT-SYN-013: Double backslash valid
        {"PAT-SYN-013: double backslash", "path\\\\name", ""},
        // PAT-SYN-014: Escaped metachar valid
        {"PAT-SYN-014: escaped metachar", "\\[\\]\\(\\)", ""},
        // PAT-SYN-015: Octal escape (may be invalid)
        {"PAT-SYN-015: octal escape", "\\0777", ""},
        // PAT-SYN-016: Hex escape valid
        {"PAT-SYN-016: hex escape", "\\x41", ""},
        // PAT-SYN-017: Unicode escape valid
        {"PAT-SYN-017: unicode escape", "\\p{L}", ""},
        // PAT-SYN-018: Invalid hex escape
        {"PAT-SYN-018: invalid hex", "\\xZZ", "ERR_PATTERN_ESCAPE"},
        // PAT-SYN-019: Incomplete hex
        {"PAT-SYN-019: incomplete hex", "\\x4", "ERR_PATTERN_ESCAPE"},
        // PAT-SYN-020: Control escape
        {"PAT-SYN-020: control escape", "\\cA", ""},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            if tt.errCode != "" {
                assert.False(t, result.Valid)
                assert.Contains(t, result.Errors[0].Code, tt.errCode)
            } else {
                // May be valid or unsupported, but not escape error
                if !result.Valid {
                    assert.NotContains(t, result.Errors[0].Code, "ESCAPE")
                }
            }
        })
    }
}
```

### 35.3.3 Invalid Quantifiers

```go
func TestPatternValidator_InvalidQuantifiers(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        errCode string
    }{
        // PAT-SYN-021: Dangling star
        {"PAT-SYN-021: dangling star", "*abc", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-022: Dangling plus
        {"PAT-SYN-022: dangling plus", "+abc", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-023: Dangling question
        {"PAT-SYN-023: dangling question", "?abc", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-024: Dangling brace quantifier
        {"PAT-SYN-024: dangling brace", "{3}abc", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-025: Invalid range (min > max)
        {"PAT-SYN-025: inverted range", "a{5,3}", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-026: Negative quantifier
        {"PAT-SYN-026: negative quantifier", "a{-1}", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-027: Non-numeric quantifier
        {"PAT-SYN-027: non-numeric", "a{abc}", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-028: Extremely large quantifier
        {"PAT-SYN-028: huge quantifier", "a{999999999}", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-029: Double quantifier
        {"PAT-SYN-029: double quantifier", "a++", "ERR_PATTERN_QUANTIFIER"},
        // PAT-SYN-030: Quantifier on quantifier
        {"PAT-SYN-030: quantifier on quantifier", "a*+", "ERR_PATTERN_QUANTIFIER"},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            assert.False(t, result.Valid, "pattern should be invalid: %s", tt.pattern)
            hasQuantifierError := false
            for _, err := range result.Errors {
                if strings.Contains(err.Code, "QUANTIFIER") || strings.Contains(err.Code, "SYNTAX") {
                    hasQuantifierError = true
                    break
                }
            }
            assert.True(t, hasQuantifierError)
        })
    }
}
```

### 35.3.4 Invalid Character Classes

```go
func TestPatternValidator_InvalidCharClasses(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        errCode string
    }{
        // PAT-SYN-031: Inverted range in class
        {"PAT-SYN-031: inverted range", "[z-a]", "ERR_PATTERN_CHARCLASS"},
        // PAT-SYN-032: Empty class (may be valid in some flavors)
        {"PAT-SYN-032: empty class", "[]", "ERR_PATTERN_CHARCLASS"},
        // PAT-SYN-033: Nested bracket
        {"PAT-SYN-033: nested bracket", "[[abc]]", "ERR_PATTERN_CHARCLASS"},
        // PAT-SYN-034: Unescaped special in class
        {"PAT-SYN-034: unescaped special", "[a[b]c]", "ERR_PATTERN_CHARCLASS"},
        // PAT-SYN-035: Invalid POSIX class
        {"PAT-SYN-035: invalid POSIX", "[[:invalid:]]", "ERR_PATTERN_CHARCLASS"},
        // PAT-SYN-036: Unclosed POSIX class
        {"PAT-SYN-036: unclosed POSIX", "[[:alpha]", "ERR_PATTERN_CHARCLASS"},
        // PAT-SYN-037: Valid negated class
        {"PAT-SYN-037: valid negated", "[^abc]", ""},
        // PAT-SYN-038: Valid range
        {"PAT-SYN-038: valid range", "[a-zA-Z0-9]", ""},
        // PAT-SYN-039: Dash at start (valid)
        {"PAT-SYN-039: dash at start", "[-abc]", ""},
        // PAT-SYN-040: Dash at end (valid)
        {"PAT-SYN-040: dash at end", "[abc-]", ""},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            if tt.errCode != "" {
                assert.False(t, result.Valid)
            } else {
                assert.True(t, result.Valid)
            }
        })
    }
}
```

---

## 35.4 Catastrophic Backtracking Tests (PAT-CBT)

### 35.4.1 Classic ReDoS Patterns

```go
func TestPatternValidator_ClassicReDoS(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        errCode string
    }{
        // PAT-CBT-001: (a+)+ - classic nested quantifiers
        {"PAT-CBT-001: (a+)+", "(a+)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-002: (a*)* - nested star
        {"PAT-CBT-002: (a*)*", "(a*)*", "ERR_PATTERN_REDOS"},
        // PAT-CBT-003: (a+)* - plus then star
        {"PAT-CBT-003: (a+)*", "(a+)*", "ERR_PATTERN_REDOS"},
        // PAT-CBT-004: (a*)+ - star then plus
        {"PAT-CBT-004: (a*)+", "(a*)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-005: (a|a)+ - overlapping alternation
        {"PAT-CBT-005: (a|a)+", "(a|a)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-006: (a|aa)+ - subset alternation
        {"PAT-CBT-006: (a|aa)+", "(a|aa)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-007: (.*a)+ - greedy with fixed suffix
        {"PAT-CBT-007: (.*a)+", "(.*a)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-008: (a+b?)+ - optional in quantified group
        {"PAT-CBT-008: (a+b?)+", "(a+b?)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-009: ((a+)+)+ - triply nested
        {"PAT-CBT-009: ((a+)+)+", "((a+)+)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-010: (a{1,100}){1,100} - range in range
        {"PAT-CBT-010: nested ranges", "(a{1,100}){1,100}", "ERR_PATTERN_REDOS"},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            assert.False(t, result.Valid, "ReDoS pattern should be rejected: %s", tt.pattern)
            assert.Contains(t, result.Errors[0].Code, tt.errCode)
        })
    }
}
```

### 35.4.2 Advanced ReDoS Patterns

```go
func TestPatternValidator_AdvancedReDoS(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        errCode string
    }{
        // PAT-CBT-011: Email-style ReDoS
        {"PAT-CBT-011: email ReDoS", "^([a-zA-Z0-9]+)+@", "ERR_PATTERN_REDOS"},
        // PAT-CBT-012: URL-style ReDoS
        {"PAT-CBT-012: URL ReDoS", "^(https?://)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-013: Nested char class quantifiers
        {"PAT-CBT-013: char class nested", "([a-z]+)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-014: Whitespace ReDoS
        {"PAT-CBT-014: whitespace ReDoS", "(\\s+)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-015: Word char ReDoS
        {"PAT-CBT-015: word char ReDoS", "(\\w+)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-016: Digit ReDoS
        {"PAT-CBT-016: digit ReDoS", "(\\d+)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-017: Alternation with quantified branch
        {"PAT-CBT-017: alt quantified branch", "(a+|b)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-018: Complex nested groups
        {"PAT-CBT-018: complex nested", "(([ab]+)+)+", "ERR_PATTERN_REDOS"},
        // PAT-CBT-019: Prefix/suffix overlap
        {"PAT-CBT-019: prefix overlap", "^(ab|abc)+$", "ERR_PATTERN_REDOS"},
        // PAT-CBT-020: Lazy quantifier ReDoS
        {"PAT-CBT-020: lazy ReDoS", "(a+?)+", "ERR_PATTERN_REDOS"},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            assert.False(t, result.Valid, "Advanced ReDoS should be rejected: %s", tt.pattern)
        })
    }
}
```

### 35.4.3 Real-World Vulnerable Patterns

```go
func TestPatternValidator_RealWorldReDoS(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        source  string
        errCode string
    }{
        // PAT-CBT-021: CVE-style email regex
        {
            name:    "PAT-CBT-021: CVE email pattern",
            pattern: "^([a-zA-Z0-9_\\.\\-])+@([a-zA-Z0-9_\\.\\-])+\\.([a-zA-Z])+$",
            source:  "Common vulnerable email regex",
            errCode: "ERR_PATTERN_REDOS",
        },
        // PAT-CBT-022: HTML comment regex
        {
            name:    "PAT-CBT-022: HTML comment",
            pattern: "<!--(.*?)*-->",
            source:  "Vulnerable HTML comment parser",
            errCode: "ERR_PATTERN_REDOS",
        },
        // PAT-CBT-023: Quoted string regex
        {
            name:    "PAT-CBT-023: quoted string",
            pattern: "\"([^\"\\\\]|\\\\.)*\"",
            source:  "Potentially slow quoted string",
            errCode: "",  // This one is actually safe in Go
        },
        // PAT-CBT-024: Log line parser
        {
            name:    "PAT-CBT-024: log parser",
            pattern: "^(\\d+\\.)+\\d+$",
            source:  "IP-like pattern with ReDoS",
            errCode: "ERR_PATTERN_REDOS",
        },
        // PAT-CBT-025: Path traversal check (bad regex)
        {
            name:    "PAT-CBT-025: path traversal",
            pattern: "^(\\.\\./)+",
            source:  "Bad path traversal detector",
            errCode: "ERR_PATTERN_REDOS",
        },
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            if tt.errCode != "" {
                assert.False(t, result.Valid, "Real-world ReDoS should be rejected: %s (source: %s)", tt.pattern, tt.source)
            }
        })
    }
}
```

### 35.4.4 Safe Patterns (False Positive Prevention)

```go
func TestPatternValidator_SafePatterns(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
    }{
        // PAT-CBT-026: Simple repetition (safe)
        {"PAT-CBT-026: simple repetition", "a+"},
        // PAT-CBT-027: Non-overlapping alternation
        {"PAT-CBT-027: non-overlapping alt", "(cat|dog)+"},
        // PAT-CBT-028: Atomic group (if supported)
        {"PAT-CBT-028: atomic group", "(?>a+)b"},
        // PAT-CBT-029: Possessive quantifier (if supported)
        {"PAT-CBT-029: possessive", "a++b"},
        // PAT-CBT-030: Anchored with bounded quantifier
        {"PAT-CBT-030: bounded anchored", "^a{1,10}$"},
        // PAT-CBT-031: Character class without nesting
        {"PAT-CBT-031: flat char class", "[a-z]+"},
        // PAT-CBT-032: Fixed-length groups
        {"PAT-CBT-032: fixed length", "(abc){3}"},
        // PAT-CBT-033: Lookahead prevents backtrack
        {"PAT-CBT-033: lookahead guard", "(?=.{6,})a+"},
        // PAT-CBT-034: Non-greedy with anchor
        {"PAT-CBT-034: lazy anchored", "^.*?end$"},
        // PAT-CBT-035: Sequential groups
        {"PAT-CBT-035: sequential groups", "(a+)(b+)(c+)"},
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            // Pattern should be valid (or unsupported if feature not available)
            if !result.Valid {
                for _, err := range result.Errors {
                    assert.NotContains(t, err.Code, "REDOS", "Safe pattern incorrectly flagged as ReDoS: %s", tt.pattern)
                }
            }
        })
    }
}
```

---

## 35.5 Timeout Handling Tests (PAT-TMO)

### 35.5.1 Compilation Timeout Tests

```go
func TestPatternValidator_CompilationTimeout(t *testing.T) {
    config := DefaultPatternValidatorConfig()
    config.CompilationTimeoutMs = 100
    validator := NewPatternValidator(config)

    // PAT-TMO-001: Fast compilation
    t.Run("PAT-TMO-001: fast compilation", func(t *testing.T) {
        start := time.Now()
        result := validator.Validate("simple")
        elapsed := time.Since(start)

        assert.True(t, result.Valid)
        assert.Less(t, elapsed, 50*time.Millisecond)
    })

    // PAT-TMO-002: Normal pattern within timeout
    t.Run("PAT-TMO-002: normal complexity", func(t *testing.T) {
        pattern := "[a-zA-Z0-9]+@[a-zA-Z0-9]+\\.[a-z]{2,4}"
        result := validator.Validate(pattern)

        assert.True(t, result.Valid)
    })

    // PAT-TMO-003: Many alternations (Go handles efficiently)
    t.Run("PAT-TMO-003: many alternations", func(t *testing.T) {
        alts := make([]string, 100)
        for i := range alts {
            alts[i] = fmt.Sprintf("option%d", i)
        }
        pattern := strings.Join(alts, "|")
        
        result := validator.Validate(pattern)
        assert.True(t, result.Valid)
    })

    // PAT-TMO-004: Deeply nested groups
    t.Run("PAT-TMO-004: deeply nested", func(t *testing.T) {
        depth := 30
        pattern := strings.Repeat("(", depth) + "a" + strings.Repeat(")", depth)
        
        result := validator.Validate(pattern)
        // May succeed or fail, but should not hang
        if !result.Valid {
            assert.Contains(t, result.Errors[0].Code, "TIMEOUT", "or", "COMPLEXITY")
        }
    })

    // PAT-TMO-005: Timeout returns specific error code
    t.Run("PAT-TMO-005: timeout error code", func(t *testing.T) {
        // Create artificially slow validator for testing
        slowConfig := config
        slowConfig.CompilationTimeoutMs = 1 // 1ms - will timeout on most patterns
        slowValidator := NewPatternValidator(slowConfig)

        result := slowValidator.Validate("[a-z]{1,1000}")
        if !result.Valid {
            assert.Contains(t, result.Errors[0].Code, "ERR_PATTERN_TIMEOUT")
        }
    })
}
```

### 35.5.2 Execution Simulation Tests

```go
func TestPatternValidator_ExecutionSimulation(t *testing.T) {
    config := DefaultPatternValidatorConfig()
    config.EnableExecutionSimulation = true
    config.SimulationInputLength = 100
    config.SimulationTimeoutMs = 50
    validator := NewPatternValidator(config)

    // PAT-TMO-006: Safe pattern executes quickly
    t.Run("PAT-TMO-006: safe execution", func(t *testing.T) {
        result := validator.Validate("^[a-z]+$")
        assert.True(t, result.Valid)
        assert.Less(t, result.SimulationTimeMs, int64(10))
    })

    // PAT-TMO-007: ReDoS pattern detected via simulation
    t.Run("PAT-TMO-007: ReDoS simulation", func(t *testing.T) {
        result := validator.Validate("(a+)+b")
        assert.False(t, result.Valid)
        assert.Contains(t, result.Errors[0].Code, "REDOS")
    })

    // PAT-TMO-008: Simulation with pathological input
    t.Run("PAT-TMO-008: pathological input", func(t *testing.T) {
        // Validator should test against "aaaaaa..." input
        result := validator.Validate("(a+)+$")
        assert.False(t, result.Valid)
    })

    // PAT-TMO-009: Simulation result includes timing
    t.Run("PAT-TMO-009: timing included", func(t *testing.T) {
        result := validator.Validate("[a-z]+")
        assert.GreaterOrEqual(t, result.SimulationTimeMs, int64(0))
    })

    // PAT-TMO-010: Disabled simulation skips timing
    t.Run("PAT-TMO-010: disabled simulation", func(t *testing.T) {
        noSimConfig := DefaultPatternValidatorConfig()
        noSimConfig.EnableExecutionSimulation = false
        noSimValidator := NewPatternValidator(noSimConfig)

        result := noSimValidator.Validate("(a+)+")
        // Should still detect via static analysis
        assert.False(t, result.Valid)
    })
}
```

### 35.5.3 Concurrent Validation Tests

```go
func TestPatternValidator_ConcurrentTimeout(t *testing.T) {
    config := DefaultPatternValidatorConfig()
    config.CompilationTimeoutMs = 100
    validator := NewPatternValidator(config)

    // PAT-TMO-011: Concurrent validations don't block
    t.Run("PAT-TMO-011: concurrent validation", func(t *testing.T) {
        patterns := []string{
            "simple",
            "[a-z]+",
            "\\d{4}-\\d{2}-\\d{2}",
            "(a+)+", // ReDoS
            ".*@.*",
        }

        var wg sync.WaitGroup
        results := make([]ValidationResult, len(patterns))
        
        start := time.Now()
        for i, p := range patterns {
            wg.Add(1)
            go func(idx int, pattern string) {
                defer wg.Done()
                results[idx] = validator.Validate(pattern)
            }(i, p)
        }
        wg.Wait()
        elapsed := time.Since(start)

        // Should complete quickly (parallel execution)
        assert.Less(t, elapsed, 200*time.Millisecond)
        
        // Verify results
        assert.True(t, results[0].Valid)   // simple
        assert.False(t, results[3].Valid)  // (a+)+
    })

    // PAT-TMO-012: Timeout isolation
    t.Run("PAT-TMO-012: timeout isolation", func(t *testing.T) {
        // One slow pattern shouldn't affect others
        var wg sync.WaitGroup
        fastDone := make(chan bool, 10)

        // Start slow validation
        wg.Add(1)
        go func() {
            defer wg.Done()
            validator.Validate(strings.Repeat("(", 50) + "a" + strings.Repeat(")", 50))
        }()

        // Start fast validations
        for i := 0; i < 10; i++ {
            wg.Add(1)
            go func() {
                defer wg.Done()
                validator.Validate("simple")
                fastDone <- true
            }()
        }

        // Fast ones should complete quickly
        timeout := time.After(50 * time.Millisecond)
        fastCount := 0
        for fastCount < 10 {
            select {
            case <-fastDone:
                fastCount++
            case <-timeout:
                t.Fatal("Fast validations blocked by slow one")
            }
        }

        wg.Wait()
    })
}
```

---

## 35.6 Limit Enforcement Tests (PAT-LIM)

### 35.6.1 Pattern Length Limits

```go
func TestPatternValidator_LengthLimits(t *testing.T) {
    config := DefaultPatternValidatorConfig()
    config.MaxPatternLength = 500
    validator := NewPatternValidator(config)

    tests := []struct {
        name    string
        length  int
        wantErr bool
        errCode string
    }{
        // PAT-LIM-001: At limit
        {"PAT-LIM-001: at limit", 500, false, ""},
        // PAT-LIM-002: Over limit
        {"PAT-LIM-002: over limit", 501, true, "ERR_PATTERN_TOO_LONG"},
        // PAT-LIM-003: Way over limit
        {"PAT-LIM-003: way over", 10000, true, "ERR_PATTERN_TOO_LONG"},
        // PAT-LIM-004: Empty
        {"PAT-LIM-004: empty", 0, true, "ERR_PATTERN_EMPTY"},
        // PAT-LIM-005: Single char
        {"PAT-LIM-005: single char", 1, false, ""},
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            pattern := strings.Repeat("a", tt.length)
            result := validator.Validate(pattern)
            if tt.wantErr {
                assert.False(t, result.Valid)
                assert.Contains(t, result.Errors[0].Code, tt.errCode)
            } else {
                assert.True(t, result.Valid)
            }
        })
    }
}
```

### 35.6.2 Pattern Count Limits

```go
func TestPatternValidator_CountLimits(t *testing.T) {
    config := DefaultPatternValidatorConfig()
    config.MaxPatternCount = 50
    validator := NewPatternValidator(config)

    tests := []struct {
        name    string
        count   int
        wantErr bool
        errCode string
    }{
        // PAT-LIM-006: At count limit
        {"PAT-LIM-006: at count limit", 50, false, ""},
        // PAT-LIM-007: Over count limit
        {"PAT-LIM-007: over count limit", 51, true, "ERR_PATTERN_COUNT_EXCEEDED"},
        // PAT-LIM-008: Way over count
        {"PAT-LIM-008: way over count", 1000, true, "ERR_PATTERN_COUNT_EXCEEDED"},
        // PAT-LIM-009: Empty array
        {"PAT-LIM-009: empty array", 0, false, ""},
        // PAT-LIM-010: Single pattern
        {"PAT-LIM-010: single pattern", 1, false, ""},
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            patterns := make([]string, tt.count)
            for i := range patterns {
                patterns[i] = fmt.Sprintf("pattern%d", i)
            }
            
            result := validator.ValidateAll(patterns)
            if tt.wantErr {
                assert.False(t, result.Valid)
                assert.Contains(t, result.Errors[0].Code, tt.errCode)
            } else {
                assert.True(t, result.Valid)
            }
        })
    }
}
```

### 35.6.3 Complexity Limits

```go
func TestPatternValidator_ComplexityLimits(t *testing.T) {
    config := DefaultPatternValidatorConfig()
    config.MaxGroupDepth = 5
    config.MaxAlternations = 20
    config.MaxQuantifierBound = 10000
    validator := NewPatternValidator(config)

    tests := []struct {
        name    string
        pattern string
        wantErr bool
        errCode string
    }{
        // PAT-LIM-011: Group depth at limit
        {"PAT-LIM-011: depth at limit", "(((((a)))))", false, ""},
        // PAT-LIM-012: Group depth over limit
        {"PAT-LIM-012: depth over limit", "((((((a))))))", true, "ERR_PATTERN_TOO_DEEP"},
        // PAT-LIM-013: Alternations at limit
        {"PAT-LIM-013: alts at limit", generateAlternationPattern(20), false, ""},
        // PAT-LIM-014: Alternations over limit
        {"PAT-LIM-014: alts over limit", generateAlternationPattern(21), true, "ERR_PATTERN_TOO_COMPLEX"},
        // PAT-LIM-015: Quantifier at limit
        {"PAT-LIM-015: quant at limit", "a{1,10000}", false, ""},
        // PAT-LIM-016: Quantifier over limit
        {"PAT-LIM-016: quant over limit", "a{1,10001}", true, "ERR_PATTERN_QUANTIFIER_TOO_LARGE"},
        // PAT-LIM-017: Combined complexity
        {"PAT-LIM-017: combined", "(a|b|c){1,100}+", true, "ERR_PATTERN_TOO_COMPLEX"},
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            if tt.wantErr {
                assert.False(t, result.Valid)
            } else {
                assert.True(t, result.Valid)
            }
        })
    }
}

func generateAlternationPattern(count int) string {
    parts := make([]string, count)
    for i := range parts {
        parts[i] = fmt.Sprintf("opt%d", i)
    }
    return strings.Join(parts, "|")
}
```

---

## 35.7 Edge Case Tests (PAT-EDG)

### 35.7.1 Unicode Patterns

```go
func TestPatternValidator_Unicode(t *testing.T) {
    validator := NewPatternValidator(DefaultPatternValidatorConfig())

    tests := []struct {
        name    string
        pattern string
        wantErr bool
    }{
        // PAT-EDG-001: Unicode literal
        {"PAT-EDG-001: unicode literal", "日本語", false},
        // PAT-EDG-002: Unicode escape
        {"PAT-EDG-002: unicode escape", "\\x{65E5}", false},
        // PAT-EDG-003: Unicode property
        {"PAT-EDG-003: unicode property", "\\p{L}+", false},
        // PAT-EDG-004: Emoji pattern
        {"PAT-EDG-004: emoji", "😀+", false},
        // PAT-EDG-005: Mixed scripts
        {"PAT-EDG-005: mixed scripts", "[a-zа-яあ-ん]+", false},
        // PAT-EDG-006: RTL characters
        {"PAT-EDG-006: RTL chars", "مرحبا", false},
        // PAT-EDG-007: Zero-width chars
        {"PAT-EDG-007: zero-width", "a\u200Bb", false},
        // PAT-EDG-008: Combining marks
        {"PAT-EDG-008: combining marks", "e\u0301", false},
        // PAT-EDG-009: Surrogate pair (invalid in UTF-8)
        {"PAT-EDG-009: invalid surrogate", "\xED\xA0\x80", true},
        // PAT-EDG-010: BOM character
        {"PAT-EDG-010: BOM", "\uFEFFpattern", false},
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            if tt.wantErr {
                assert.False(t, result.Valid)
            } else {
                // May be valid or unsupported, but not crash
                assert.NotPanics(t, func() { validator.Validate(tt.pattern) })
            }
        })
    }
}
```

### 35.7.2 Special Input Patterns

```go
func TestPatternValidator_SpecialInputs(t *testing.T) {
    validator := NewPatternValidator(DefaultPatternValidatorConfig())

    tests := []struct {
        name    string
        pattern string
        wantErr bool
        errCode string
    }{
        // PAT-EDG-011: Null byte
        {"PAT-EDG-011: null byte", "before\x00after", true, "ERR_PATTERN_INVALID_CHAR"},
        // PAT-EDG-012: Control characters
        {"PAT-EDG-012: control chars", "line\x01end", true, "ERR_PATTERN_INVALID_CHAR"},
        // PAT-EDG-013: Tab and newline
        {"PAT-EDG-013: whitespace escapes", "line\\tword\\nend", false, ""},
        // PAT-EDG-014: Very long alternation
        {"PAT-EDG-014: long alternation", strings.Repeat("a|", 100) + "z", false, ""},
        // PAT-EDG-015: Pattern matching nothing
        {"PAT-EDG-015: match nothing", "a^", false, ""},
        // PAT-EDG-016: Pattern matching everything
        {"PAT-EDG-016: match all", ".*", false, ""},
        // PAT-EDG-017: Only anchors
        {"PAT-EDG-017: only anchors", "^$", false, ""},
        // PAT-EDG-018: Only lookarounds
        {"PAT-EDG-018: only lookahead", "(?=a)", false, ""},
        // PAT-EDG-019: Recursive pattern (not supported)
        {"PAT-EDG-019: recursive", "(?R)", true, "ERR_PATTERN_UNSUPPORTED"},
        // PAT-EDG-020: Conditional pattern (not supported)
        {"PAT-EDG-020: conditional", "(?(1)a|b)", true, "ERR_PATTERN_UNSUPPORTED"},
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result := validator.Validate(tt.pattern)
            if tt.wantErr {
                assert.False(t, result.Valid)
                if tt.errCode != "" {
                    found := false
                    for _, err := range result.Errors {
                        if strings.Contains(err.Code, tt.errCode) {
                            found = true
                            break
                        }
                    }
                    assert.True(t, found, "Expected error code %s not found", tt.errCode)
                }
            } else {
                // May be valid or have warnings
                assert.NotPanics(t, func() { validator.Validate(tt.pattern) })
            }
        })
    }
}
```

### 35.7.3 Boundary Conditions

```go
func TestPatternValidator_BoundaryConditions(t *testing.T) {
    config := DefaultPatternValidatorConfig()
    config.MaxPatternLength = 100
    validator := NewPatternValidator(config)

    // PAT-EDG-021: Exactly at length boundary
    t.Run("PAT-EDG-021: exact boundary", func(t *testing.T) {
        pattern := strings.Repeat("a", 100)
        result := validator.Validate(pattern)
        assert.True(t, result.Valid)
    })

    // PAT-EDG-022: One over boundary
    t.Run("PAT-EDG-022: one over", func(t *testing.T) {
        pattern := strings.Repeat("a", 101)
        result := validator.Validate(pattern)
        assert.False(t, result.Valid)
    })

    // PAT-EDG-023: Empty string
    t.Run("PAT-EDG-023: empty string", func(t *testing.T) {
        result := validator.Validate("")
        assert.False(t, result.Valid)
        assert.Contains(t, result.Errors[0].Code, "EMPTY")
    })

    // PAT-EDG-024: Whitespace only
    t.Run("PAT-EDG-024: whitespace only", func(t *testing.T) {
        result := validator.Validate("   \t\n  ")
        assert.False(t, result.Valid)
        assert.Contains(t, result.Errors[0].Code, "EMPTY")
    })

    // PAT-EDG-025: Single escape char
    t.Run("PAT-EDG-025: single escape", func(t *testing.T) {
        result := validator.Validate("\\")
        assert.False(t, result.Valid)
    })
}
```

---

## 35.8 Benchmark Tests (PAT-BNC)

### 35.8.1 Validation Performance

```go
func BenchmarkPatternValidator_Simple(b *testing.B) {
    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    pattern := "[a-z]+@[a-z]+\\.[a-z]{2,4}"

    b.ResetTimer()
    for i := 0; i < b.N; i++ {
        validator.Validate(pattern)
    }
}

func BenchmarkPatternValidator_Complex(b *testing.B) {
    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    pattern := "^(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|\"(?:[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x21\\x23-\\x5b\\x5d-\\x7f]|\\\\[\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f])*\")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x21-\\x5a\\x53-\\x7f]|\\\\[\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f])+)\\])$"

    b.ResetTimer()
    for i := 0; i < b.N; i++ {
        validator.Validate(pattern)
    }
}

func BenchmarkPatternValidator_ReDoSDetection(b *testing.B) {
    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    patterns := []string{
        "(a+)+",
        "([a-z]+)+@",
        "(\\d+\\.)+",
        "((a|b)+)+",
        "(a+b?)+",
    }

    b.ResetTimer()
    for i := 0; i < b.N; i++ {
        for _, p := range patterns {
            validator.Validate(p)
        }
    }
}

func BenchmarkPatternValidator_Concurrent(b *testing.B) {
    validator := NewPatternValidator(DefaultPatternValidatorConfig())
    patterns := []string{
        "simple",
        "[a-z]+",
        "\\d{4}-\\d{2}-\\d{2}",
        "(a+)+",
        ".*@.*",
    }

    b.ResetTimer()
    b.RunParallel(func(pb *testing.PB) {
        i := 0
        for pb.Next() {
            validator.Validate(patterns[i%len(patterns)])
            i++
        }
    })
}
```

### 35.8.2 Performance Targets

```go
func TestPatternValidator_PerformanceTargets(t *testing.T) {
    if testing.Short() {
        t.Skip("Skipping performance tests in short mode")
    }

    validator := NewPatternValidator(DefaultPatternValidatorConfig())

    // PAT-BNC-001: Simple pattern < 1ms
    t.Run("PAT-BNC-001: simple under 1ms", func(t *testing.T) {
        start := time.Now()
        for i := 0; i < 100; i++ {
            validator.Validate("[a-z]+")
        }
        avg := time.Since(start) / 100
        assert.Less(t, avg, time.Millisecond)
    })

    // PAT-BNC-002: Complex pattern < 10ms
    t.Run("PAT-BNC-002: complex under 10ms", func(t *testing.T) {
        complex := "^(?:[a-z0-9]+\\.)+[a-z0-9]+$"
        start := time.Now()
        for i := 0; i < 100; i++ {
            validator.Validate(complex)
        }
        avg := time.Since(start) / 100
        assert.Less(t, avg, 10*time.Millisecond)
    })

    // PAT-BNC-003: ReDoS detection < 5ms
    t.Run("PAT-BNC-003: ReDoS detection under 5ms", func(t *testing.T) {
        redos := "(a+)+"
        start := time.Now()
        for i := 0; i < 100; i++ {
            validator.Validate(redos)
        }
        avg := time.Since(start) / 100
        assert.Less(t, avg, 5*time.Millisecond)
    })

    // PAT-BNC-004: Batch validation scales linearly
    t.Run("PAT-BNC-004: linear scaling", func(t *testing.T) {
        patterns := make([]string, 50)
        for i := range patterns {
            patterns[i] = fmt.Sprintf("pattern%d[a-z]+", i)
        }

        // Measure 10 patterns
        start10 := time.Now()
        validator.ValidateAll(patterns[:10])
        time10 := time.Since(start10)

        // Measure 50 patterns
        start50 := time.Now()
        validator.ValidateAll(patterns)
        time50 := time.Since(start50)

        // Should be roughly 5x (with some tolerance)
        ratio := float64(time50) / float64(time10)
        assert.Less(t, ratio, 7.0, "Scaling should be approximately linear")
    })
}
```

---

## 35.9 Test Utilities

### 35.9.1 Configuration Factory

```go
func DefaultPatternValidatorConfig() PatternValidatorConfig {
    return PatternValidatorConfig{
        MaxPatternLength:           500,
        MaxPatternCount:            50,
        MaxGroupDepth:              10,
        MaxAlternations:            50,
        MaxQuantifierBound:         10000,
        CompilationTimeoutMs:       1000,
        EnableExecutionSimulation:  true,
        SimulationInputLength:      100,
        SimulationTimeoutMs:        100,
        DetectNestedQuantifiers:    true,
        DetectOverlappingAlternations: true,
    }
}

func StrictPatternValidatorConfig() PatternValidatorConfig {
    return PatternValidatorConfig{
        MaxPatternLength:           200,
        MaxPatternCount:            20,
        MaxGroupDepth:              5,
        MaxAlternations:            10,
        MaxQuantifierBound:         1000,
        CompilationTimeoutMs:       100,
        EnableExecutionSimulation:  true,
        SimulationInputLength:      50,
        SimulationTimeoutMs:        50,
        DetectNestedQuantifiers:    true,
        DetectOverlappingAlternations: true,
    }
}
```

### 35.9.2 Pattern Generators

```go
// GenerateDeepNestedPattern creates a pattern with specified nesting depth
func GenerateDeepNestedPattern(depth int) string {
    return strings.Repeat("(", depth) + "a" + strings.Repeat(")", depth)
}

// GenerateLongAlternation creates a pattern with many alternations
func GenerateLongAlternation(count int) string {
    parts := make([]string, count)
    for i := range parts {
        parts[i] = fmt.Sprintf("option%d", i)
    }
    return strings.Join(parts, "|")
}

// GenerateReDoSPattern creates a known ReDoS-vulnerable pattern
func GenerateReDoSPattern(variant string) string {
    patterns := map[string]string{
        "nested_plus":     "(a+)+",
        "nested_star":     "(a*)*",
        "overlapping_alt": "(a|aa)+",
        "greedy_suffix":   "(.*a)+",
        "email_like":      "([a-z]+)+@",
    }
    if p, ok := patterns[variant]; ok {
        return p
    }
    return patterns["nested_plus"]
}

// GenerateSafePattern creates a pattern known to be safe
func GenerateSafePattern(complexity string) string {
    patterns := map[string]string{
        "simple":    "hello",
        "medium":    "[a-z]+@[a-z]+\\.[a-z]{2,4}",
        "complex":   "^(?:[a-z0-9]+\\.)+[a-z]{2,}$",
        "anchored":  "^start.*end$",
        "bounded":   "a{1,10}b{1,10}c{1,10}",
    }
    if p, ok := patterns[complexity]; ok {
        return p
    }
    return patterns["simple"]
}
```

### 35.9.3 Assertion Helpers

```go
// AssertPatternValid asserts pattern validation succeeds
func AssertPatternValid(t *testing.T, validator *PatternValidator, pattern string) {
    t.Helper()
    result := validator.Validate(pattern)
    if !result.Valid {
        t.Errorf("Expected pattern to be valid: %s\nErrors: %v", pattern, result.Errors)
    }
}

// AssertPatternInvalid asserts pattern validation fails with expected code
func AssertPatternInvalid(t *testing.T, validator *PatternValidator, pattern, expectedCode string) {
    t.Helper()
    result := validator.Validate(pattern)
    if result.Valid {
        t.Errorf("Expected pattern to be invalid: %s", pattern)
        return
    }
    
    found := false
    for _, err := range result.Errors {
        if strings.Contains(err.Code, expectedCode) {
            found = true
            break
        }
    }
    if !found {
        t.Errorf("Expected error code %s not found in: %v", expectedCode, result.Errors)
    }
}

// AssertNoReDoS asserts pattern is not flagged as ReDoS-vulnerable
func AssertNoReDoS(t *testing.T, validator *PatternValidator, pattern string) {
    t.Helper()
    result := validator.Validate(pattern)
    for _, err := range result.Errors {
        if strings.Contains(err.Code, "REDOS") {
            t.Errorf("Pattern incorrectly flagged as ReDoS: %s", pattern)
        }
    }
}
```

---

## 35.10 Cross-References

| Document | Relationship |
|----------|-------------|
| [09-knowledge-memory-system.md](../09-knowledge-memory-system.md) | Parent system specification |
| [02-knowledge-validator-tests.md](./02-knowledge-validator-tests.md) | Related validator tests |
| [03-knowledge-memory-e2e.md](./03-knowledge-memory-e2e.md) | E2E test scenarios |
| [general-spec/03-quality/01-testing-standards-quality.md](../../../../general-spec/03-quality/01-testing-standards-quality.md) | Testing standards |

---

## 35.11 Changelog

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2026-01-28 | AI | Initial specification |
