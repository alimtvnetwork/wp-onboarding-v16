# 33. Knowledge Memory Validator Tests

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28

---

## 33.1 Overview

This specification defines comprehensive unit tests for the Knowledge Memory System validators: URL Validator, Pattern Validator, and Path Validator. These validators ensure security and data integrity for knowledge sources.

### 33.1.1 Test Coverage Goals

| Validator | Target Coverage | Critical Paths |
|-----------|----------------|----------------|
| **URL Validator** | ≥ 95% | Private networks, schemes, encoding |
| **Pattern Validator** | ≥ 95% | Catastrophic backtracking, timeouts |
| **Path Validator** | ≥ 98% | Traversal, symlinks, injection |

---

## 33.2 URL Validator Tests

### 33.2.1 Scheme Validation Tests (URL-SCH)

```go
func TestUrlValidator_Scheme(t *testing.T) {
    tests := []struct {
        name    string
        url     string
        wantErr bool
        errCode string
    }{
        // URL-SCH-001: Valid HTTP scheme
        {
            name:    "URL-SCH-001: valid http scheme",
            url:     "http://example.com/path",
            wantErr: false,
        },
        // URL-SCH-002: Valid HTTPS scheme
        {
            name:    "URL-SCH-002: valid https scheme",
            url:     "https://example.com/path",
            wantErr: false,
        },
        // URL-SCH-003: Reject FTP scheme
        {
            name:    "URL-SCH-003: reject ftp scheme",
            url:     "ftp://example.com/file.txt",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-SCH-004: Reject file scheme
        {
            name:    "URL-SCH-004: reject file scheme",
            url:     "file:///etc/passwd",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-SCH-005: Reject javascript scheme
        {
            name:    "URL-SCH-005: reject javascript scheme",
            url:     "javascript:alert('xss')",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-SCH-006: Reject data URI
        {
            name:    "URL-SCH-006: reject data URI",
            url:     "data:text/html,<script>alert(1)</script>",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-SCH-007: Reject mailto scheme
        {
            name:    "URL-SCH-007: reject mailto scheme",
            url:     "mailto:admin@example.com",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-SCH-008: Reject tel scheme
        {
            name:    "URL-SCH-008: reject tel scheme",
            url:     "tel:+1234567890",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-SCH-009: Missing scheme rejected
        {
            name:    "URL-SCH-009: missing scheme rejected",
            url:     "example.com/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-SCH-010: Protocol-relative URL rejected
        {
            name:    "URL-SCH-010: protocol-relative rejected",
            url:     "//example.com/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
    }

    validator := NewUrlValidator(false) // allowPrivateNetworks = false
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.Validate(tt.url)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.2.2 Private Network Validation Tests (URL-PVT)

```go
func TestUrlValidator_PrivateNetworks(t *testing.T) {
    tests := []struct {
        name             string
        url              string
        allowPrivate     bool
        wantErr          bool
        errCode          string
    }{
        // URL-PVT-001: Localhost rejected
        {
            name:         "URL-PVT-001: localhost rejected",
            url:          "http://localhost/admin",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-002: 127.0.0.1 rejected
        {
            name:         "URL-PVT-002: 127.0.0.1 rejected",
            url:          "http://127.0.0.1/admin",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-003: 127.x.x.x range rejected
        {
            name:         "URL-PVT-003: 127.0.0.2 rejected",
            url:          "http://127.0.0.2:8080/api",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-004: 10.x.x.x range rejected
        {
            name:         "URL-PVT-004: 10.0.0.1 rejected",
            url:          "http://10.0.0.1/internal",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-005: 10.255.255.255 rejected
        {
            name:         "URL-PVT-005: 10.255.255.255 rejected",
            url:          "http://10.255.255.255/api",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-006: 172.16.x.x rejected
        {
            name:         "URL-PVT-006: 172.16.0.1 rejected",
            url:          "http://172.16.0.1/app",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-007: 172.31.x.x rejected
        {
            name:         "URL-PVT-007: 172.31.255.255 rejected",
            url:          "http://172.31.255.255/app",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-008: 172.15.x.x allowed (not private)
        {
            name:         "URL-PVT-008: 172.15.0.1 allowed",
            url:          "http://172.15.0.1/public",
            allowPrivate: false,
            wantErr:      false,
        },
        // URL-PVT-009: 172.32.x.x allowed (not private)
        {
            name:         "URL-PVT-009: 172.32.0.1 allowed",
            url:          "http://172.32.0.1/public",
            allowPrivate: false,
            wantErr:      false,
        },
        // URL-PVT-010: 192.168.x.x rejected
        {
            name:         "URL-PVT-010: 192.168.1.1 rejected",
            url:          "http://192.168.1.1/router",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-011: 192.168.255.255 rejected
        {
            name:         "URL-PVT-011: 192.168.255.255 rejected",
            url:          "http://192.168.255.255/api",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-012: 192.167.x.x allowed (not private)
        {
            name:         "URL-PVT-012: 192.167.1.1 allowed",
            url:          "http://192.167.1.1/public",
            allowPrivate: false,
            wantErr:      false,
        },
        // URL-PVT-013: Link-local 169.254.x.x rejected
        {
            name:         "URL-PVT-013: 169.254.1.1 rejected",
            url:          "http://169.254.1.1/metadata",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-014: IPv6 localhost rejected
        {
            name:         "URL-PVT-014: IPv6 ::1 rejected",
            url:          "http://[::1]/admin",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-015: IPv6 private fc00::/7 rejected
        {
            name:         "URL-PVT-015: IPv6 fc00:: rejected",
            url:          "http://[fc00::1]/internal",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-016: IPv6 private fd00:: rejected
        {
            name:         "URL-PVT-016: IPv6 fd00:: rejected",
            url:          "http://[fd12:3456:789a::1]/api",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-017: Localhost allowed when flag enabled
        {
            name:         "URL-PVT-017: localhost allowed when enabled",
            url:          "http://localhost/admin",
            allowPrivate: true,
            wantErr:      false,
        },
        // URL-PVT-018: Private IP allowed when flag enabled
        {
            name:         "URL-PVT-018: private IP allowed when enabled",
            url:          "http://192.168.1.1/router",
            allowPrivate: true,
            wantErr:      false,
        },
        // URL-PVT-019: AWS metadata endpoint rejected
        {
            name:         "URL-PVT-019: AWS metadata 169.254.169.254 rejected",
            url:          "http://169.254.169.254/latest/meta-data",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-020: GCP metadata endpoint rejected
        {
            name:         "URL-PVT-020: GCP metadata endpoint rejected",
            url:          "http://metadata.google.internal/computeMetadata",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-021: 0.0.0.0 rejected
        {
            name:         "URL-PVT-021: 0.0.0.0 rejected",
            url:          "http://0.0.0.0/path",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-022: Localhost with different case
        {
            name:         "URL-PVT-022: LOCALHOST rejected",
            url:          "http://LOCALHOST/admin",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-023: localhost.localdomain rejected
        {
            name:         "URL-PVT-023: localhost.localdomain rejected",
            url:          "http://localhost.localdomain/path",
            allowPrivate: false,
            wantErr:      true,
            errCode:      "ERR_KNOWLEDGE_PRIVATE_URL",
        },
        // URL-PVT-024: Public IP allowed
        {
            name:         "URL-PVT-024: public IP 8.8.8.8 allowed",
            url:          "http://8.8.8.8/dns",
            allowPrivate: false,
            wantErr:      false,
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            validator := NewUrlValidator(tt.allowPrivate)
            err := validator.Validate(tt.url)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.2.3 Host Validation Tests (URL-HST)

```go
func TestUrlValidator_Host(t *testing.T) {
    tests := []struct {
        name    string
        url     string
        wantErr bool
        errCode string
    }{
        // URL-HST-001: Valid domain
        {
            name:    "URL-HST-001: valid domain",
            url:     "https://example.com/path",
            wantErr: false,
        },
        // URL-HST-002: Valid subdomain
        {
            name:    "URL-HST-002: valid subdomain",
            url:     "https://api.example.com/v1",
            wantErr: false,
        },
        // URL-HST-003: Empty host rejected
        {
            name:    "URL-HST-003: empty host rejected",
            url:     "https:///path/only",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-HST-004: User credentials rejected
        {
            name:    "URL-HST-004: credentials in URL rejected",
            url:     "https://user:pass@example.com/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-HST-005: Username only rejected
        {
            name:    "URL-HST-005: username only rejected",
            url:     "https://admin@example.com/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-HST-006: Valid public IPv4
        {
            name:    "URL-HST-006: valid public IPv4",
            url:     "https://203.0.113.50/api",
            wantErr: false,
        },
        // URL-HST-007: Valid public IPv6
        {
            name:    "URL-HST-007: valid public IPv6",
            url:     "https://[2001:db8::1]/api",
            wantErr: false,
        },
        // URL-HST-008: Invalid IPv6 format
        {
            name:    "URL-HST-008: invalid IPv6 format",
            url:     "https://[invalid]/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-HST-009: Host exceeds max length
        {
            name:    "URL-HST-009: host too long",
            url:     "https://" + strings.Repeat("a", 254) + ".com/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-HST-010: Single label host (no TLD)
        {
            name:    "URL-HST-010: single label host rejected",
            url:     "https://localhost-not-really/path",
            wantErr: false, // Valid hostname format, just unusual
        },
    }

    validator := NewUrlValidator(false)
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.Validate(tt.url)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.2.4 Port Validation Tests (URL-PRT)

```go
func TestUrlValidator_Port(t *testing.T) {
    tests := []struct {
        name    string
        url     string
        wantErr bool
        errCode string
    }{
        // URL-PRT-001: Valid port 80
        {
            name:    "URL-PRT-001: port 80 valid",
            url:     "http://example.com:80/path",
            wantErr: false,
        },
        // URL-PRT-002: Valid port 443
        {
            name:    "URL-PRT-002: port 443 valid",
            url:     "https://example.com:443/path",
            wantErr: false,
        },
        // URL-PRT-003: Valid port 8080
        {
            name:    "URL-PRT-003: port 8080 valid",
            url:     "http://example.com:8080/api",
            wantErr: false,
        },
        // URL-PRT-004: Valid port 65535 (max)
        {
            name:    "URL-PRT-004: port 65535 valid",
            url:     "http://example.com:65535/path",
            wantErr: false,
        },
        // URL-PRT-005: Port 0 rejected
        {
            name:    "URL-PRT-005: port 0 rejected",
            url:     "http://example.com:0/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-PRT-006: Port > 65535 rejected
        {
            name:    "URL-PRT-006: port 65536 rejected",
            url:     "http://example.com:65536/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-PRT-007: Negative port rejected
        {
            name:    "URL-PRT-007: negative port rejected",
            url:     "http://example.com:-1/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-PRT-008: Non-numeric port rejected
        {
            name:    "URL-PRT-008: non-numeric port rejected",
            url:     "http://example.com:abc/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-PRT-009: No port (default) valid
        {
            name:    "URL-PRT-009: no port valid",
            url:     "https://example.com/path",
            wantErr: false,
        },
        // URL-PRT-010: Port with leading zeros
        {
            name:    "URL-PRT-010: port with leading zeros",
            url:     "http://example.com:0080/path",
            wantErr: false, // Usually parsed as 80
        },
    }

    validator := NewUrlValidator(false)
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.Validate(tt.url)
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.2.5 URL Length and Encoding Tests (URL-LEN)

```go
func TestUrlValidator_LengthAndEncoding(t *testing.T) {
    tests := []struct {
        name    string
        url     string
        wantErr bool
        errCode string
    }{
        // URL-LEN-001: URL at max length (2048)
        {
            name:    "URL-LEN-001: URL at max length",
            url:     "https://example.com/" + strings.Repeat("a", 2048-len("https://example.com/")),
            wantErr: false,
        },
        // URL-LEN-002: URL exceeds max length
        {
            name:    "URL-LEN-002: URL exceeds max length",
            url:     "https://example.com/" + strings.Repeat("a", 2049),
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-LEN-003: Empty URL rejected
        {
            name:    "URL-LEN-003: empty URL rejected",
            url:     "",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-LEN-004: Whitespace only rejected
        {
            name:    "URL-LEN-004: whitespace only rejected",
            url:     "   \t\n  ",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-LEN-005: Valid UTF-8 encoding
        {
            name:    "URL-LEN-005: valid UTF-8 in path",
            url:     "https://example.com/日本語",
            wantErr: false,
        },
        // URL-LEN-006: Valid percent encoding
        {
            name:    "URL-LEN-006: valid percent encoding",
            url:     "https://example.com/path%20with%20spaces",
            wantErr: false,
        },
        // URL-LEN-007: Invalid percent encoding
        {
            name:    "URL-LEN-007: invalid percent encoding",
            url:     "https://example.com/path%GG",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-LEN-008: Incomplete percent encoding
        {
            name:    "URL-LEN-008: incomplete percent encoding",
            url:     "https://example.com/path%2",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-LEN-009: Null byte rejected
        {
            name:    "URL-LEN-009: null byte rejected",
            url:     "https://example.com/path\x00value",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-LEN-010: Newline in URL rejected
        {
            name:    "URL-LEN-010: newline rejected",
            url:     "https://example.com/path\ninjection",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-LEN-011: Carriage return rejected
        {
            name:    "URL-LEN-011: carriage return rejected",
            url:     "https://example.com/path\rinjection",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // URL-LEN-012: Tab character rejected
        {
            name:    "URL-LEN-012: tab character rejected",
            url:     "https://example.com/path\tvalue",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
    }

    validator := NewUrlValidator(false)
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.Validate(tt.url)
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

---

## 33.3 Pattern Validator Tests

### 33.3.1 Valid Pattern Tests (PAT-VAL)

```go
func TestPatternValidator_ValidPatterns(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        wantErr bool
    }{
        // PAT-VAL-001: Simple literal pattern
        {
            name:    "PAT-VAL-001: simple literal",
            pattern: "/docs/",
            wantErr: false,
        },
        // PAT-VAL-002: Wildcard pattern
        {
            name:    "PAT-VAL-002: wildcard pattern",
            pattern: "/api/.*",
            wantErr: false,
        },
        // PAT-VAL-003: Character class pattern
        {
            name:    "PAT-VAL-003: character class",
            pattern: "/v[0-9]+/",
            wantErr: false,
        },
        // PAT-VAL-004: Anchor pattern
        {
            name:    "PAT-VAL-004: anchored pattern",
            pattern: "^/docs/",
            wantErr: false,
        },
        // PAT-VAL-005: End anchor pattern
        {
            name:    "PAT-VAL-005: end anchor",
            pattern: "\\.html$",
            wantErr: false,
        },
        // PAT-VAL-006: Optional group
        {
            name:    "PAT-VAL-006: optional group",
            pattern: "/api/(v1|v2)?/users",
            wantErr: false,
        },
        // PAT-VAL-007: Quantifier with bounds
        {
            name:    "PAT-VAL-007: bounded quantifier",
            pattern: "/page/[0-9]{1,5}",
            wantErr: false,
        },
        // PAT-VAL-008: Escaped special chars
        {
            name:    "PAT-VAL-008: escaped special chars",
            pattern: "/path\\.with\\.dots",
            wantErr: false,
        },
        // PAT-VAL-009: Word boundary
        {
            name:    "PAT-VAL-009: word boundary",
            pattern: `\bdocs\b`,
            wantErr: false,
        },
        // PAT-VAL-010: Non-capturing group
        {
            name:    "PAT-VAL-010: non-capturing group",
            pattern: "(?:api|docs)/.*",
            wantErr: false,
        },
    }

    validator := NewPatternValidator()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidatePatterns([]string{tt.pattern})
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.3.2 Invalid Syntax Tests (PAT-SYN)

```go
func TestPatternValidator_InvalidSyntax(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        errCode string
    }{
        // PAT-SYN-001: Unmatched opening parenthesis
        {
            name:    "PAT-SYN-001: unmatched open paren",
            pattern: "(unclosed",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-002: Unmatched closing parenthesis
        {
            name:    "PAT-SYN-002: unmatched close paren",
            pattern: "unclosed)",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-003: Unmatched bracket
        {
            name:    "PAT-SYN-003: unmatched bracket",
            pattern: "[unclosed",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-004: Invalid escape sequence
        {
            name:    "PAT-SYN-004: invalid escape",
            pattern: "\\",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-005: Invalid quantifier range
        {
            name:    "PAT-SYN-005: invalid quantifier range",
            pattern: "a{5,3}",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-006: Empty alternation
        {
            name:    "PAT-SYN-006: empty alternation",
            pattern: "(|)",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-007: Dangling quantifier
        {
            name:    "PAT-SYN-007: dangling quantifier",
            pattern: "*invalid",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-008: Invalid character class range
        {
            name:    "PAT-SYN-008: invalid char class range",
            pattern: "[z-a]",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-009: Unescaped special char in class
        {
            name:    "PAT-SYN-009: nested bracket in class",
            pattern: "[[abc]",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-SYN-010: Invalid flag (Go doesn't support flags)
        {
            name:    "PAT-SYN-010: invalid inline flag",
            pattern: "(?z)pattern",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
    }

    validator := NewPatternValidator()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidatePatterns([]string{tt.pattern})
            assert.Error(t, err)
            assert.Contains(t, err.Error(), tt.errCode)
        })
    }
}
```

### 33.3.3 Catastrophic Backtracking Tests (PAT-CBT)

```go
func TestPatternValidator_CatastrophicBacktracking(t *testing.T) {
    tests := []struct {
        name    string
        pattern string
        errCode string
    }{
        // PAT-CBT-001: (a+)+ pattern
        {
            name:    "PAT-CBT-001: nested plus quantifiers",
            pattern: "(a+)+",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-002: (a*)* pattern
        {
            name:    "PAT-CBT-002: nested star quantifiers",
            pattern: "(a*)*",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-003: (a+)* pattern
        {
            name:    "PAT-CBT-003: plus then star",
            pattern: "(a+)*",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-004: (a*)+  pattern
        {
            name:    "PAT-CBT-004: star then plus",
            pattern: "(a*)+",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-005: Nested groups with quantifiers
        {
            name:    "PAT-CBT-005: deeply nested quantifiers",
            pattern: "((a+)+)+",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-006: Complex nested pattern
        {
            name:    "PAT-CBT-006: complex nested",
            pattern: "(([a-z]+)+)",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-007: Alternation with nested quantifiers
        {
            name:    "PAT-CBT-007: alternation nested quantifiers",
            pattern: "(a|b+)+",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-008: Overlapping quantifiers
        {
            name:    "PAT-CBT-008: overlapping patterns",
            pattern: "(.*)+",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-009: ReDoS classic pattern
        {
            name:    "PAT-CBT-009: ReDoS classic",
            pattern: "^(a+)+$",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-CBT-010: Email-like ReDoS
        {
            name:    "PAT-CBT-010: email-like ReDoS",
            pattern: "^([a-zA-Z0-9]+)+@",
            errCode: "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
    }

    validator := NewPatternValidator()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidatePatterns([]string{tt.pattern})
            assert.Error(t, err)
            assert.Contains(t, err.Error(), tt.errCode)
        })
    }
}
```

### 33.3.4 Length and Count Limits Tests (PAT-LIM)

```go
func TestPatternValidator_Limits(t *testing.T) {
    tests := []struct {
        name     string
        patterns []string
        wantErr  bool
        errCode  string
    }{
        // PAT-LIM-001: Pattern at max length (500)
        {
            name:     "PAT-LIM-001: pattern at max length",
            patterns: []string{strings.Repeat("a", 500)},
            wantErr:  false,
        },
        // PAT-LIM-002: Pattern exceeds max length
        {
            name:     "PAT-LIM-002: pattern exceeds max length",
            patterns: []string{strings.Repeat("a", 501)},
            wantErr:  true,
            errCode:  "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-LIM-003: Empty pattern rejected
        {
            name:     "PAT-LIM-003: empty pattern rejected",
            patterns: []string{""},
            wantErr:  true,
            errCode:  "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-LIM-004: Whitespace-only pattern rejected
        {
            name:     "PAT-LIM-004: whitespace pattern rejected",
            patterns: []string{"   "},
            wantErr:  true,
            errCode:  "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-LIM-005: Max patterns count (50)
        {
            name:     "PAT-LIM-005: max patterns count",
            patterns: generatePatterns(50),
            wantErr:  false,
        },
        // PAT-LIM-006: Exceeds max patterns count
        {
            name:     "PAT-LIM-006: exceeds max patterns",
            patterns: generatePatterns(51),
            wantErr:  true,
            errCode:  "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-LIM-007: Empty patterns array allowed
        {
            name:     "PAT-LIM-007: empty array allowed",
            patterns: []string{},
            wantErr:  false,
        },
        // PAT-LIM-008: Mixed valid patterns
        {
            name:     "PAT-LIM-008: mixed valid patterns",
            patterns: []string{"/api/.*", "/docs/", "^/v[0-9]+/"},
            wantErr:  false,
        },
        // PAT-LIM-009: One invalid in list fails all
        {
            name:     "PAT-LIM-009: one invalid fails all",
            patterns: []string{"/valid/", "(invalid", "/also-valid/"},
            wantErr:  true,
            errCode:  "ERR_KNOWLEDGE_PATTERN_INVALID",
        },
        // PAT-LIM-010: Nil patterns array
        {
            name:     "PAT-LIM-010: nil array allowed",
            patterns: nil,
            wantErr:  false,
        },
    }

    validator := NewPatternValidator()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidatePatterns(tt.patterns)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}

func generatePatterns(count int) []string {
    patterns := make([]string, count)
    for i := 0; i < count; i++ {
        patterns[i] = fmt.Sprintf("/path%d/", i)
    }
    return patterns
}
```

### 33.3.5 Compilation Timeout Tests (PAT-TMO)

```go
func TestPatternValidator_Timeout(t *testing.T) {
    validator := NewPatternValidator()
    
    // PAT-TMO-001: Pattern that compiles quickly
    t.Run("PAT-TMO-001: fast compilation", func(t *testing.T) {
        start := time.Now()
        err := validator.ValidatePatterns([]string{"/simple/pattern"})
        elapsed := time.Since(start)
        
        assert.NoError(t, err)
        assert.Less(t, elapsed, 10*time.Millisecond)
    })
    
    // PAT-TMO-002: Pattern with many alternations (still fast in Go)
    t.Run("PAT-TMO-002: many alternations", func(t *testing.T) {
        pattern := strings.Join(generateAlternations(100), "|")
        err := validator.ValidatePatterns([]string{pattern})
        
        assert.NoError(t, err)
    })
    
    // PAT-TMO-003: Deeply nested groups
    t.Run("PAT-TMO-003: deeply nested groups", func(t *testing.T) {
        pattern := strings.Repeat("(", 50) + "a" + strings.Repeat(")", 50)
        err := validator.ValidatePatterns([]string{pattern})
        
        // Should either succeed or fail with appropriate error
        // Go's regex is generally resilient to deep nesting
        if err != nil {
            assert.Contains(t, err.Error(), "ERR_KNOWLEDGE_PATTERN_INVALID")
        }
    })
}

func generateAlternations(count int) []string {
    alts := make([]string, count)
    for i := 0; i < count; i++ {
        alts[i] = fmt.Sprintf("option%d", i)
    }
    return alts
}
```

---

## 33.4 Path Validator Tests

### 33.4.1 Basic Path Validation Tests (PATH-BSC)

```go
func TestPathValidator_Basic(t *testing.T) {
    // Create temp directories for testing
    tmpDir := t.TempDir()
    validDir := filepath.Join(tmpDir, "valid")
    os.MkdirAll(validDir, 0755)
    
    validator := NewPathValidator(tmpDir, []string{tmpDir}, false)
    
    tests := []struct {
        name    string
        path    string
        wantErr bool
        errCode string
    }{
        // PATH-BSC-001: Valid absolute path
        {
            name:    "PATH-BSC-001: valid absolute path",
            path:    validDir,
            wantErr: false,
        },
        // PATH-BSC-002: Valid relative path
        {
            name:    "PATH-BSC-002: valid relative path",
            path:    "valid",
            wantErr: false,
        },
        // PATH-BSC-003: Non-existent path rejected
        {
            name:    "PATH-BSC-003: non-existent rejected",
            path:    filepath.Join(tmpDir, "nonexistent"),
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_PATH_NOT_FOUND",
        },
        // PATH-BSC-004: Empty path rejected
        {
            name:    "PATH-BSC-004: empty path rejected",
            path:    "",
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-BSC-005: Whitespace path rejected
        {
            name:    "PATH-BSC-005: whitespace rejected",
            path:    "   ",
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidateSpecPath(tt.path)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.4.2 Path Traversal Prevention Tests (PATH-TRV)

```go
func TestPathValidator_TraversalPrevention(t *testing.T) {
    tmpDir := t.TempDir()
    validDir := filepath.Join(tmpDir, "valid")
    os.MkdirAll(validDir, 0755)
    
    validator := NewPathValidator(tmpDir, []string{tmpDir}, false)
    
    tests := []struct {
        name    string
        path    string
        errCode string
    }{
        // PATH-TRV-001: Single parent reference
        {
            name:    "PATH-TRV-001: single parent ref",
            path:    "../outside",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-002: Multiple parent references
        {
            name:    "PATH-TRV-002: multiple parent refs",
            path:    "../../../etc/passwd",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-003: Hidden parent reference
        {
            name:    "PATH-TRV-003: hidden in path",
            path:    "valid/../../../outside",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-004: URL-encoded traversal
        {
            name:    "PATH-TRV-004: URL-encoded traversal",
            path:    "..%2F..%2Fetc%2Fpasswd",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-005: Double-encoded traversal
        {
            name:    "PATH-TRV-005: double-encoded",
            path:    "..%252F..%252Fetc",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-006: Backslash traversal (Windows)
        {
            name:    "PATH-TRV-006: backslash traversal",
            path:    "..\\..\\etc\\passwd",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-007: Mixed separator traversal
        {
            name:    "PATH-TRV-007: mixed separators",
            path:    "..\\/..\\/../etc",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-008: Null byte injection
        {
            name:    "PATH-TRV-008: null byte injection",
            path:    "valid\x00/../etc/passwd",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-009: Unicode normalization bypass
        {
            name:    "PATH-TRV-009: unicode bypass attempt",
            path:    "valid/\u002e\u002e/outside",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-TRV-010: Overlong UTF-8 sequence
        {
            name:    "PATH-TRV-010: overlong UTF-8",
            path:    "valid/\xc0\xae\xc0\xae/outside",
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidateSpecPath(tt.path)
            assert.Error(t, err)
            assert.Contains(t, err.Error(), tt.errCode)
        })
    }
}
```

### 33.4.3 Path Length and Character Tests (PATH-CHR)

```go
func TestPathValidator_LengthAndCharacters(t *testing.T) {
    tmpDir := t.TempDir()
    validator := NewPathValidator(tmpDir, []string{tmpDir}, false)
    
    tests := []struct {
        name    string
        path    string
        wantErr bool
        errCode string
    }{
        // PATH-CHR-001: Path at max length (4096)
        {
            name:    "PATH-CHR-001: path at max length",
            path:    tmpDir + "/" + strings.Repeat("a", 4096-len(tmpDir)-1),
            wantErr: true, // Will fail because path doesn't exist, but length OK
            errCode: "ERR_KNOWLEDGE_PATH_NOT_FOUND",
        },
        // PATH-CHR-002: Path exceeds max length
        {
            name:    "PATH-CHR-002: path exceeds max length",
            path:    strings.Repeat("a", 4097),
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-CHR-003: Null byte in path
        {
            name:    "PATH-CHR-003: null byte rejected",
            path:    tmpDir + "/path\x00value",
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-CHR-004: Newline in path
        {
            name:    "PATH-CHR-004: newline rejected",
            path:    tmpDir + "/path\nvalue",
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-CHR-005: Tab in path
        {
            name:    "PATH-CHR-005: tab rejected",
            path:    tmpDir + "/path\tvalue",
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-CHR-006: Carriage return in path
        {
            name:    "PATH-CHR-006: carriage return rejected",
            path:    tmpDir + "/path\rvalue",
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-CHR-007: Bell character in path
        {
            name:    "PATH-CHR-007: bell char rejected",
            path:    tmpDir + "/path\x07value",
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-CHR-008: Valid Unicode in path
        {
            name:    "PATH-CHR-008: unicode allowed",
            path:    tmpDir + "/日本語/ファイル",
            wantErr: true, // Doesn't exist but valid format
            errCode: "ERR_KNOWLEDGE_PATH_NOT_FOUND",
        },
        // PATH-CHR-009: Spaces in path allowed
        {
            name:    "PATH-CHR-009: spaces allowed",
            path:    tmpDir + "/path with spaces",
            wantErr: true, // Doesn't exist but valid format
            errCode: "ERR_KNOWLEDGE_PATH_NOT_FOUND",
        },
        // PATH-CHR-010: Dots in filename allowed
        {
            name:    "PATH-CHR-010: dots in filename allowed",
            path:    tmpDir + "/file.name.with.dots",
            wantErr: true, // Doesn't exist but valid format
            errCode: "ERR_KNOWLEDGE_PATH_NOT_FOUND",
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidateSpecPath(tt.path)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.4.4 Allowed Roots Tests (PATH-ROOT)

```go
func TestPathValidator_AllowedRoots(t *testing.T) {
    tmpDir := t.TempDir()
    allowedDir := filepath.Join(tmpDir, "allowed")
    forbiddenDir := filepath.Join(tmpDir, "forbidden")
    nestedAllowed := filepath.Join(allowedDir, "nested")
    
    os.MkdirAll(allowedDir, 0755)
    os.MkdirAll(forbiddenDir, 0755)
    os.MkdirAll(nestedAllowed, 0755)
    
    validator := NewPathValidator(tmpDir, []string{allowedDir}, false)
    
    tests := []struct {
        name    string
        path    string
        wantErr bool
        errCode string
    }{
        // PATH-ROOT-001: Path within allowed root
        {
            name:    "PATH-ROOT-001: within allowed root",
            path:    allowedDir,
            wantErr: false,
        },
        // PATH-ROOT-002: Nested path within allowed root
        {
            name:    "PATH-ROOT-002: nested within allowed",
            path:    nestedAllowed,
            wantErr: false,
        },
        // PATH-ROOT-003: Path outside allowed roots
        {
            name:    "PATH-ROOT-003: outside allowed roots",
            path:    forbiddenDir,
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-ROOT-004: Parent of allowed root
        {
            name:    "PATH-ROOT-004: parent of allowed",
            path:    tmpDir,
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-ROOT-005: Sibling of allowed root
        {
            name:    "PATH-ROOT-005: sibling of allowed",
            path:    forbiddenDir,
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidateSpecPath(tt.path)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.4.5 Symlink Resolution Tests (PATH-SYM)

```go
func TestPathValidator_Symlinks(t *testing.T) {
    if runtime.GOOS == "windows" {
        t.Skip("Symlink tests not reliable on Windows")
    }
    
    tmpDir := t.TempDir()
    allowedDir := filepath.Join(tmpDir, "allowed")
    outsideDir := filepath.Join(tmpDir, "outside")
    
    os.MkdirAll(allowedDir, 0755)
    os.MkdirAll(outsideDir, 0755)
    
    // Create symlinks
    safeLink := filepath.Join(allowedDir, "safe-link")
    escapeLink := filepath.Join(allowedDir, "escape-link")
    brokenLink := filepath.Join(allowedDir, "broken-link")
    
    os.Symlink(allowedDir, safeLink)
    os.Symlink(outsideDir, escapeLink)
    os.Symlink(filepath.Join(tmpDir, "nonexistent"), brokenLink)
    
    // Validator with symlink following enabled
    validator := NewPathValidator(tmpDir, []string{allowedDir}, true)
    
    tests := []struct {
        name    string
        path    string
        wantErr bool
        errCode string
    }{
        // PATH-SYM-001: Symlink within allowed directory
        {
            name:    "PATH-SYM-001: symlink within allowed",
            path:    safeLink,
            wantErr: false,
        },
        // PATH-SYM-002: Symlink escaping to outside
        {
            name:    "PATH-SYM-002: symlink escaping allowed",
            path:    escapeLink,
            wantErr: true,
            errCode: "ERR_CONFIG_PATH_INVALID",
        },
        // PATH-SYM-003: Broken symlink
        {
            name:    "PATH-SYM-003: broken symlink",
            path:    brokenLink,
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_PATH_NOT_FOUND",
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := validator.ValidateSpecPath(tt.path)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

### 33.4.6 Directory Validation Tests (PATH-DIR)

```go
func TestPathValidator_DirectoryRequirements(t *testing.T) {
    tmpDir := t.TempDir()
    validDir := filepath.Join(tmpDir, "valid-dir")
    os.MkdirAll(validDir, 0755)
    
    // Create a file (not directory)
    filePath := filepath.Join(tmpDir, "file.txt")
    os.WriteFile(filePath, []byte("content"), 0644)
    
    // Create unreadable directory
    unreadableDir := filepath.Join(tmpDir, "unreadable")
    os.MkdirAll(unreadableDir, 0000)
    defer os.Chmod(unreadableDir, 0755) // Cleanup
    
    validator := NewPathValidator(tmpDir, []string{tmpDir}, false)
    
    tests := []struct {
        name    string
        path    string
        wantErr bool
        errCode string
    }{
        // PATH-DIR-001: Valid directory
        {
            name:    "PATH-DIR-001: valid directory",
            path:    validDir,
            wantErr: false,
        },
        // PATH-DIR-002: File instead of directory
        {
            name:    "PATH-DIR-002: file rejected",
            path:    filePath,
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_PATH_NOT_FOUND",
        },
        // PATH-DIR-003: Unreadable directory
        {
            name:    "PATH-DIR-003: unreadable directory",
            path:    unreadableDir,
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_PATH_NOT_FOUND",
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            // Skip unreadable test if running as root
            if tt.name == "PATH-DIR-003: unreadable directory" && os.Getuid() == 0 {
                t.Skip("Running as root, cannot test permission denied")
            }
            
            err := validator.ValidateSpecPath(tt.path)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
            }
        })
    }
}
```

---

## 33.5 Integration Tests

### 33.5.1 Combined Validator Tests

```go
func TestKnowledgeValidators_Integration(t *testing.T) {
    tmpDir := t.TempDir()
    allowedDir := filepath.Join(tmpDir, "specs")
    os.MkdirAll(allowedDir, 0755)
    
    urlValidator := NewUrlValidator(false)
    patternValidator := NewPatternValidator()
    pathValidator := NewPathValidator(tmpDir, []string{allowedDir}, false)
    
    // INT-001: Valid spec source configuration
    t.Run("INT-001: valid spec source config", func(t *testing.T) {
        err := pathValidator.ValidateSpecPath(allowedDir)
        assert.NoError(t, err)
        
        err = patternValidator.ValidatePatterns([]string{"/docs/.*", "/api/"})
        assert.NoError(t, err)
    })
    
    // INT-002: Valid URL source configuration
    t.Run("INT-002: valid URL source config", func(t *testing.T) {
        err := urlValidator.Validate("https://docs.example.com/guide")
        assert.NoError(t, err)
        
        err = patternValidator.ValidatePatterns([]string{"^/guide/", "^/tutorial/"})
        assert.NoError(t, err)
    })
    
    // INT-003: Invalid combined configuration
    t.Run("INT-003: invalid combined config", func(t *testing.T) {
        // Valid URL but invalid pattern
        err := urlValidator.Validate("https://example.com")
        assert.NoError(t, err)
        
        err = patternValidator.ValidatePatterns([]string{"(a+)+"})
        assert.Error(t, err)
    })
    
    // INT-004: All validators reject malicious input
    t.Run("INT-004: security validation", func(t *testing.T) {
        // URL with private network
        err := urlValidator.Validate("http://192.168.1.1/admin")
        assert.Error(t, err)
        
        // ReDoS pattern
        err = patternValidator.ValidatePatterns([]string{"(.*)*"})
        assert.Error(t, err)
        
        // Path traversal
        err = pathValidator.ValidateSpecPath("../../../etc/passwd")
        assert.Error(t, err)
    })
}
```

---

## 33.6 Benchmark Tests

### 33.6.1 Performance Benchmarks

```go
func BenchmarkUrlValidator(b *testing.B) {
    validator := NewUrlValidator(false)
    urls := []string{
        "https://example.com/path",
        "https://api.example.com:8080/v1/users?id=123",
        "https://192.168.1.1/admin",
        "http://localhost/api",
    }
    
    b.Run("valid_url", func(b *testing.B) {
        for i := 0; i < b.N; i++ {
            validator.Validate(urls[0])
        }
    })
    
    b.Run("complex_url", func(b *testing.B) {
        for i := 0; i < b.N; i++ {
            validator.Validate(urls[1])
        }
    })
    
    b.Run("private_network_check", func(b *testing.B) {
        for i := 0; i < b.N; i++ {
            validator.Validate(urls[2])
        }
    })
}

func BenchmarkPatternValidator(b *testing.B) {
    validator := NewPatternValidator()
    
    b.Run("simple_pattern", func(b *testing.B) {
        for i := 0; i < b.N; i++ {
            validator.ValidatePatterns([]string{"/docs/"})
        }
    })
    
    b.Run("complex_pattern", func(b *testing.B) {
        for i := 0; i < b.N; i++ {
            validator.ValidatePatterns([]string{"^/api/v[0-9]+/users/[a-z]+$"})
        }
    })
    
    b.Run("multiple_patterns", func(b *testing.B) {
        patterns := generatePatterns(20)
        b.ResetTimer()
        for i := 0; i < b.N; i++ {
            validator.ValidatePatterns(patterns)
        }
    })
    
    b.Run("catastrophic_detection", func(b *testing.B) {
        for i := 0; i < b.N; i++ {
            validator.ValidatePatterns([]string{"(a+)+"})
        }
    })
}

func BenchmarkPathValidator(b *testing.B) {
    tmpDir := b.TempDir()
    validDir := filepath.Join(tmpDir, "valid")
    os.MkdirAll(validDir, 0755)
    
    validator := NewPathValidator(tmpDir, []string{tmpDir}, false)
    
    b.Run("valid_path", func(b *testing.B) {
        for i := 0; i < b.N; i++ {
            validator.ValidateSpecPath(validDir)
        }
    })
    
    b.Run("traversal_check", func(b *testing.B) {
        for i := 0; i < b.N; i++ {
            validator.ValidateSpecPath("../../../etc/passwd")
        }
    })
    
    b.Run("long_path", func(b *testing.B) {
        longPath := tmpDir + "/" + strings.Repeat("a/", 100)
        b.ResetTimer()
        for i := 0; i < b.N; i++ {
            validator.ValidateSpecPath(longPath)
        }
    })
}
```

---

## 33.7 Test Data Factories

```go
package testutil

import (
    "fmt"
    "net"
    "strings"
)

// PrivateIPGenerator generates various private IP addresses for testing
type PrivateIPGenerator struct{}

func (g *PrivateIPGenerator) Generate() []string {
    return []string{
        // Loopback
        "127.0.0.1", "127.0.0.2", "127.255.255.255",
        // Class A private
        "10.0.0.1", "10.255.255.255", "10.100.50.25",
        // Class B private
        "172.16.0.1", "172.31.255.255", "172.20.100.50",
        // Class C private
        "192.168.0.1", "192.168.255.255", "192.168.1.100",
        // Link-local
        "169.254.0.1", "169.254.169.254", "169.254.255.255",
        // Special
        "0.0.0.0", "255.255.255.255",
    }
}

// PublicIPGenerator generates public IP addresses for testing
type PublicIPGenerator struct{}

func (g *PublicIPGenerator) Generate() []string {
    return []string{
        "8.8.8.8", "1.1.1.1", "208.67.222.222",
        "172.15.0.1", "172.32.0.1", // Just outside private range
        "192.167.1.1", "192.169.1.1", // Just outside private range
    }
}

// DangerousPatternGenerator generates patterns known to cause issues
type DangerousPatternGenerator struct{}

func (g *DangerousPatternGenerator) ReDoSPatterns() []string {
    return []string{
        "(a+)+", "(a*)*", "(a+)*", "(a*)+",
        "((a+)+)+", "(([a-z]+)+)",
        "(a|b+)+", "(.*)+", "^(a+)+$",
        "([a-zA-Z0-9]+)+@",
    }
}

func (g *DangerousPatternGenerator) InvalidSyntaxPatterns() []string {
    return []string{
        "(unclosed", "unclosed)", "[unclosed",
        "\\", "a{5,3}", "(|)", "*invalid",
        "[z-a]", "[[abc]", "(?z)pattern",
    }
}

// TraversalPathGenerator generates path traversal attempts
type TraversalPathGenerator struct{}

func (g *TraversalPathGenerator) Generate() []string {
    return []string{
        "../", "../../", "../../../",
        "..\\", "..\\..\\",
        "..%2F", "..%5C",
        "..%252F", "..%255C",
        "..\\/", "..\\../",
        "\x00../", "../\x00",
        "\u002e\u002e/",
    }
}
```

---

## 33.8 Coverage Requirements

| Category | Minimum Coverage | Critical Branches |
|----------|-----------------|-------------------|
| URL Validator | 95% | Private network detection, scheme validation |
| Pattern Validator | 95% | ReDoS detection, timeout handling |
| Path Validator | 98% | Traversal prevention, symlink resolution |
| Integration | 90% | Combined validation flows |

---

## 33.9 Cross-References

| Specification | Relationship |
|---------------|--------------|
| 31-knowledge-memory-system.md | Validator implementations |
| 09-seeding-configuration.md | Validation rules definition |
| 32-url-normalizer-tests.md | URL test patterns |
| general-spec/03-quality/01-testing-standards-quality.md | Test standards |

---

## 33.10 Summary

This test specification provides:

1. **120+ test cases** across URL, Pattern, and Path validators
2. **Security-focused tests** for private networks, ReDoS, and traversal
3. **Edge case coverage** for encoding, length limits, and special characters
4. **Performance benchmarks** for validation operations
5. **Test factories** for generating varied test data
6. **Integration tests** for combined validation scenarios
