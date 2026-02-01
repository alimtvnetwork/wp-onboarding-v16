# 32. URL Normalizer Test Specification

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-28

---

## 32.1 Overview

This specification defines comprehensive unit tests for the URL Normalizer component of the Knowledge Memory System. The normalizer ensures consistent URL deduplication by transforming URLs into canonical form.

### 32.1.1 Test Coverage Goals

| Category | Target Coverage |
|----------|----------------|
| **Line Coverage** | ≥ 95% |
| **Branch Coverage** | ≥ 90% |
| **Edge Cases** | 100% documented cases |

---

## 32.2 Test Categories

### 32.2.1 Category Overview

| ID | Category | Test Count | Priority |
|----|----------|------------|----------|
| SCH | Scheme Normalization | 8 | Critical |
| HST | Host Normalization | 12 | Critical |
| PRT | Port Normalization | 10 | High |
| PTH | Path Normalization | 15 | Critical |
| QRY | Query Parameter Handling | 18 | High |
| FRG | Fragment Removal | 6 | Medium |
| TRK | Tracking Parameter Removal | 14 | High |
| WWW | WWW Prefix Handling | 8 | High |
| ENC | Encoding Normalization | 12 | Medium |
| ERR | Error Handling | 10 | Critical |
| IDM | Idempotency | 5 | High |

---

## 32.3 Scheme Normalization Tests (SCH)

### 32.3.1 Test Cases

```go
func TestNormalizeUrl_Scheme(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
        wantErr  bool
        errCode  string
    }{
        // SCH-001: Lowercase HTTP scheme
        {
            name:     "SCH-001: uppercase HTTP to lowercase",
            input:    "HTTP://example.com/path",
            expected: "http://example.com/path",
        },
        // SCH-002: Lowercase HTTPS scheme
        {
            name:     "SCH-002: uppercase HTTPS to lowercase",
            input:    "HTTPS://Example.COM/Path",
            expected: "https://example.com/Path",
        },
        // SCH-003: Mixed case scheme
        {
            name:     "SCH-003: mixed case scheme",
            input:    "HtTpS://example.com",
            expected: "https://example.com/",
        },
        // SCH-004: Already lowercase
        {
            name:     "SCH-004: already lowercase scheme",
            input:    "https://example.com",
            expected: "https://example.com/",
        },
        // SCH-005: Reject FTP scheme
        {
            name:    "SCH-005: reject ftp scheme",
            input:   "ftp://example.com/file.txt",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // SCH-006: Reject file scheme
        {
            name:    "SCH-006: reject file scheme",
            input:   "file:///etc/passwd",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // SCH-007: Reject javascript scheme
        {
            name:    "SCH-007: reject javascript scheme",
            input:   "javascript:alert(1)",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // SCH-008: Reject data URI scheme
        {
            name:    "SCH-008: reject data URI scheme",
            input:   "data:text/html,<script>alert(1)</script>",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            if tt.wantErr {
                assert.Error(t, err)
                if tt.errCode != "" {
                    assert.Contains(t, err.Error(), tt.errCode)
                }
            } else {
                assert.NoError(t, err)
                assert.Equal(t, tt.expected, result)
            }
        })
    }
}
```

---

## 32.4 Host Normalization Tests (HST)

### 32.4.1 Test Cases

```go
func TestNormalizeUrl_Host(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
        wantErr  bool
        errCode  string
    }{
        // HST-001: Lowercase host
        {
            name:     "HST-001: uppercase host to lowercase",
            input:    "https://EXAMPLE.COM/path",
            expected: "https://example.com/path",
        },
        // HST-002: Mixed case host
        {
            name:     "HST-002: mixed case host",
            input:    "https://ExAmPlE.CoM/path",
            expected: "https://example.com/path",
        },
        // HST-003: Subdomain handling
        {
            name:     "HST-003: subdomain preserved lowercase",
            input:    "https://API.Example.COM/v1",
            expected: "https://api.example.com/v1",
        },
        // HST-004: Multiple subdomains
        {
            name:     "HST-004: multiple subdomains",
            input:    "https://Dev.API.Example.COM/v1",
            expected: "https://dev.api.example.com/v1",
        },
        // HST-005: IPv4 address
        {
            name:     "HST-005: IPv4 address unchanged",
            input:    "http://192.168.1.1/admin",
            expected: "http://192.168.1.1/admin",
        },
        // HST-006: IPv6 address
        {
            name:     "HST-006: IPv6 address preserved",
            input:    "http://[2001:db8::1]/path",
            expected: "http://[2001:db8::1]/path",
        },
        // HST-007: IDN domain (punycode)
        {
            name:     "HST-007: IDN domain normalized",
            input:    "https://münchen.example.com/page",
            expected: "https://xn--mnchen-3ya.example.com/page",
        },
        // HST-008: Empty host rejected
        {
            name:    "HST-008: empty host rejected",
            input:   "https:///path/only",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // HST-009: Trailing dot removed
        {
            name:     "HST-009: trailing dot removed from host",
            input:    "https://example.com./path",
            expected: "https://example.com/path",
        },
        // HST-010: Host with credentials rejected
        {
            name:    "HST-010: user credentials in URL rejected",
            input:   "https://user:pass@example.com/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // HST-011: Very long host
        {
            name:    "HST-011: host exceeding 253 chars rejected",
            input:   "https://" + strings.Repeat("a", 254) + ".com/path",
            wantErr: true,
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // HST-012: Unicode normalization (NFC)
        {
            name:     "HST-012: unicode host normalized to NFC",
            input:    "https://café.example.com/menu",
            expected: "https://xn--caf-dma.example.com/menu",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
                assert.Equal(t, tt.expected, result)
            }
        })
    }
}
```

---

## 32.5 Port Normalization Tests (PRT)

### 32.5.1 Test Cases

```go
func TestNormalizeUrl_Port(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
        wantErr  bool
    }{
        // PRT-001: Remove default HTTP port 80
        {
            name:     "PRT-001: remove default HTTP port 80",
            input:    "http://example.com:80/path",
            expected: "http://example.com/path",
        },
        // PRT-002: Remove default HTTPS port 443
        {
            name:     "PRT-002: remove default HTTPS port 443",
            input:    "https://example.com:443/path",
            expected: "https://example.com/path",
        },
        // PRT-003: Preserve non-default HTTP port
        {
            name:     "PRT-003: preserve non-default HTTP port",
            input:    "http://example.com:8080/path",
            expected: "http://example.com:8080/path",
        },
        // PRT-004: Preserve non-default HTTPS port
        {
            name:     "PRT-004: preserve non-default HTTPS port",
            input:    "https://example.com:8443/path",
            expected: "https://example.com:8443/path",
        },
        // PRT-005: Port 443 on HTTP preserved (non-default)
        {
            name:     "PRT-005: port 443 on HTTP is non-default",
            input:    "http://example.com:443/path",
            expected: "http://example.com:443/path",
        },
        // PRT-006: Port 80 on HTTPS preserved (non-default)
        {
            name:     "PRT-006: port 80 on HTTPS is non-default",
            input:    "https://example.com:80/path",
            expected: "https://example.com:80/path",
        },
        // PRT-007: No port specified
        {
            name:     "PRT-007: no port uses default",
            input:    "https://example.com/path",
            expected: "https://example.com/path",
        },
        // PRT-008: Invalid port number
        {
            name:    "PRT-008: invalid port number rejected",
            input:   "https://example.com:99999/path",
            wantErr: true,
        },
        // PRT-009: Port zero rejected
        {
            name:    "PRT-009: port zero rejected",
            input:   "https://example.com:0/path",
            wantErr: true,
        },
        // PRT-010: Non-numeric port rejected
        {
            name:    "PRT-010: non-numeric port rejected",
            input:   "https://example.com:abc/path",
            wantErr: true,
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
                assert.Equal(t, tt.expected, result)
            }
        })
    }
}
```

---

## 32.6 Path Normalization Tests (PTH)

### 32.6.1 Trailing Slash Tests

```go
func TestNormalizeUrl_TrailingSlash(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
    }{
        // PTH-001: Remove trailing slash from path
        {
            name:     "PTH-001: remove trailing slash",
            input:    "https://example.com/path/",
            expected: "https://example.com/path",
        },
        // PTH-002: Multiple trailing slashes
        {
            name:     "PTH-002: remove multiple trailing slashes",
            input:    "https://example.com/path///",
            expected: "https://example.com/path",
        },
        // PTH-003: Root path keeps single slash
        {
            name:     "PTH-003: root path keeps slash",
            input:    "https://example.com/",
            expected: "https://example.com/",
        },
        // PTH-004: No path gets root slash
        {
            name:     "PTH-004: empty path becomes root",
            input:    "https://example.com",
            expected: "https://example.com/",
        },
        // PTH-005: Deep path trailing slash
        {
            name:     "PTH-005: deep path trailing slash removed",
            input:    "https://example.com/a/b/c/d/",
            expected: "https://example.com/a/b/c/d",
        },
        // PTH-006: Trailing slash with query preserved
        {
            name:     "PTH-006: trailing slash removed before query",
            input:    "https://example.com/path/?query=1",
            expected: "https://example.com/path?query=1",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            assert.NoError(t, err)
            assert.Equal(t, tt.expected, result)
        })
    }
}
```

### 32.6.2 Path Cleaning Tests

```go
func TestNormalizeUrl_PathCleaning(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
        wantErr  bool
    }{
        // PTH-007: Collapse double slashes
        {
            name:     "PTH-007: collapse double slashes in path",
            input:    "https://example.com/path//to///page",
            expected: "https://example.com/path/to/page",
        },
        // PTH-008: Remove dot segments
        {
            name:     "PTH-008: remove single dot segments",
            input:    "https://example.com/./path/./to/./page",
            expected: "https://example.com/path/to/page",
        },
        // PTH-009: Resolve parent references (safe)
        {
            name:     "PTH-009: resolve parent references",
            input:    "https://example.com/a/b/../c",
            expected: "https://example.com/a/c",
        },
        // PTH-010: Parent beyond root stays at root
        {
            name:     "PTH-010: parent beyond root clamped",
            input:    "https://example.com/../../../etc/passwd",
            expected: "https://example.com/etc/passwd",
        },
        // PTH-011: Case sensitivity preserved
        {
            name:     "PTH-011: path case preserved",
            input:    "https://example.com/Path/To/Page",
            expected: "https://example.com/Path/To/Page",
        },
        // PTH-012: Encoded slashes preserved
        {
            name:     "PTH-012: encoded slashes not decoded",
            input:    "https://example.com/path%2Fwith%2Fslashes",
            expected: "https://example.com/path%2Fwith%2Fslashes",
        },
        // PTH-013: Space encoding normalized
        {
            name:     "PTH-013: spaces encoded as %20",
            input:    "https://example.com/path with spaces",
            expected: "https://example.com/path%20with%20spaces",
        },
        // PTH-014: Plus in path preserved (not space)
        {
            name:     "PTH-014: plus in path preserved",
            input:    "https://example.com/c++/reference",
            expected: "https://example.com/c++/reference",
        },
        // PTH-015: Unicode path encoded
        {
            name:     "PTH-015: unicode path percent-encoded",
            input:    "https://example.com/路径/页面",
            expected: "https://example.com/%E8%B7%AF%E5%BE%84/%E9%A1%B5%E9%9D%A2",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
                assert.Equal(t, tt.expected, result)
            }
        })
    }
}
```

---

## 32.7 Query Parameter Tests (QRY)

### 32.7.1 Query Sorting Tests

```go
func TestNormalizeUrl_QuerySorting(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
    }{
        // QRY-001: Sort query params alphabetically
        {
            name:     "QRY-001: sort params alphabetically",
            input:    "https://example.com/search?z=1&a=2&m=3",
            expected: "https://example.com/search?a=2&m=3&z=1",
        },
        // QRY-002: Preserve param order with same key
        {
            name:     "QRY-002: multiple same keys preserve order",
            input:    "https://example.com/search?tag=b&tag=a&tag=c",
            expected: "https://example.com/search?tag=b&tag=a&tag=c",
        },
        // QRY-003: Mixed single and multiple params
        {
            name:     "QRY-003: mixed params sorted by key",
            input:    "https://example.com/search?b=1&a=2&b=3&c=4",
            expected: "https://example.com/search?a=2&b=1&b=3&c=4",
        },
        // QRY-004: Case sensitive key sorting
        {
            name:     "QRY-004: case sensitive sorting (A before a)",
            input:    "https://example.com/search?a=1&A=2&b=3",
            expected: "https://example.com/search?A=2&a=1&b=3",
        },
        // QRY-005: Empty query string removed
        {
            name:     "QRY-005: empty query string removed",
            input:    "https://example.com/path?",
            expected: "https://example.com/path",
        },
        // QRY-006: Query with only ampersands cleaned
        {
            name:     "QRY-006: empty params removed",
            input:    "https://example.com/path?&&&",
            expected: "https://example.com/path",
        },
        // QRY-007: Numeric keys sorted correctly
        {
            name:     "QRY-007: numeric keys sorted lexically",
            input:    "https://example.com/page?10=a&2=b&1=c",
            expected: "https://example.com/page?1=c&10=a&2=b",
        },
        // QRY-008: Empty value preserved
        {
            name:     "QRY-008: empty values preserved",
            input:    "https://example.com/page?flag=&name=test",
            expected: "https://example.com/page?flag=&name=test",
        },
        // QRY-009: Key without equals sign
        {
            name:     "QRY-009: key without value normalized",
            input:    "https://example.com/page?flag&name=test",
            expected: "https://example.com/page?flag=&name=test",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            assert.NoError(t, err)
            assert.Equal(t, tt.expected, result)
        })
    }
}
```

### 32.7.2 Query Encoding Tests

```go
func TestNormalizeUrl_QueryEncoding(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
    }{
        // QRY-010: Encode special characters in values
        {
            name:     "QRY-010: encode special chars in value",
            input:    "https://example.com/search?q=hello world",
            expected: "https://example.com/search?q=hello%20world",
        },
        // QRY-011: Decode then re-encode consistently
        {
            name:     "QRY-011: normalize existing encoding",
            input:    "https://example.com/search?q=hello%20world",
            expected: "https://example.com/search?q=hello%20world",
        },
        // QRY-012: Plus as space in query
        {
            name:     "QRY-012: plus converted to %20 in query",
            input:    "https://example.com/search?q=hello+world",
            expected: "https://example.com/search?q=hello%20world",
        },
        // QRY-013: Uppercase hex encoding normalized
        {
            name:     "QRY-013: lowercase hex normalized to uppercase",
            input:    "https://example.com/search?q=%2f%2F",
            expected: "https://example.com/search?q=%2F%2F",
        },
        // QRY-014: Unicode in query encoded
        {
            name:     "QRY-014: unicode query params encoded",
            input:    "https://example.com/search?q=日本語",
            expected: "https://example.com/search?q=%E6%97%A5%E6%9C%AC%E8%AA%9E",
        },
        // QRY-015: Ampersand in value encoded
        {
            name:     "QRY-015: ampersand in value encoded",
            input:    "https://example.com/page?company=A%26B",
            expected: "https://example.com/page?company=A%26B",
        },
        // QRY-016: Equals in value encoded
        {
            name:     "QRY-016: equals in value encoded",
            input:    "https://example.com/page?equation=1%3D1",
            expected: "https://example.com/page?equation=1%3D1",
        },
        // QRY-017: Safe chars not encoded
        {
            name:     "QRY-017: safe chars not encoded",
            input:    "https://example.com/page?chars=-_.~",
            expected: "https://example.com/page?chars=-_.~",
        },
        // QRY-018: Already encoded stays encoded
        {
            name:     "QRY-018: double encoding prevented",
            input:    "https://example.com/page?url=https%3A%2F%2Ftest.com",
            expected: "https://example.com/page?url=https%3A%2F%2Ftest.com",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            assert.NoError(t, err)
            assert.Equal(t, tt.expected, result)
        })
    }
}
```

---

## 32.8 Fragment Removal Tests (FRG)

### 32.8.1 Test Cases

```go
func TestNormalizeUrl_FragmentRemoval(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
    }{
        // FRG-001: Simple fragment removed
        {
            name:     "FRG-001: simple fragment removed",
            input:    "https://example.com/page#section",
            expected: "https://example.com/page",
        },
        // FRG-002: Fragment with query
        {
            name:     "FRG-002: fragment after query removed",
            input:    "https://example.com/page?a=1#section",
            expected: "https://example.com/page?a=1",
        },
        // FRG-003: Empty fragment removed
        {
            name:     "FRG-003: empty fragment removed",
            input:    "https://example.com/page#",
            expected: "https://example.com/page",
        },
        // FRG-004: Encoded fragment characters
        {
            name:     "FRG-004: encoded fragment removed",
            input:    "https://example.com/page#section%20name",
            expected: "https://example.com/page",
        },
        // FRG-005: Multiple hash signs (first is fragment)
        {
            name:     "FRG-005: multiple hashes only first is fragment",
            input:    "https://example.com/page#section#subsection",
            expected: "https://example.com/page",
        },
        // FRG-006: Hash in query preserved
        {
            name:     "FRG-006: encoded hash in query preserved",
            input:    "https://example.com/page?color=%23red",
            expected: "https://example.com/page?color=%23red",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            assert.NoError(t, err)
            assert.Equal(t, tt.expected, result)
        })
    }
}
```

---

## 32.9 Tracking Parameter Removal Tests (TRK)

### 32.9.1 Test Cases

```go
func TestNormalizeUrl_TrackingParameterRemoval(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
    }{
        // TRK-001: Remove utm_source
        {
            name:     "TRK-001: remove utm_source",
            input:    "https://example.com/page?utm_source=google",
            expected: "https://example.com/page",
        },
        // TRK-002: Remove utm_medium
        {
            name:     "TRK-002: remove utm_medium",
            input:    "https://example.com/page?utm_medium=cpc",
            expected: "https://example.com/page",
        },
        // TRK-003: Remove utm_campaign
        {
            name:     "TRK-003: remove utm_campaign",
            input:    "https://example.com/page?utm_campaign=spring_sale",
            expected: "https://example.com/page",
        },
        // TRK-004: Remove utm_term
        {
            name:     "TRK-004: remove utm_term",
            input:    "https://example.com/page?utm_term=shoes",
            expected: "https://example.com/page",
        },
        // TRK-005: Remove utm_content
        {
            name:     "TRK-005: remove utm_content",
            input:    "https://example.com/page?utm_content=banner",
            expected: "https://example.com/page",
        },
        // TRK-006: Remove fbclid (Facebook)
        {
            name:     "TRK-006: remove fbclid",
            input:    "https://example.com/page?fbclid=abc123",
            expected: "https://example.com/page",
        },
        // TRK-007: Remove gclid (Google Ads)
        {
            name:     "TRK-007: remove gclid",
            input:    "https://example.com/page?gclid=xyz789",
            expected: "https://example.com/page",
        },
        // TRK-008: Remove multiple tracking params
        {
            name:     "TRK-008: remove all tracking params together",
            input:    "https://example.com/page?utm_source=google&utm_medium=cpc&fbclid=abc",
            expected: "https://example.com/page",
        },
        // TRK-009: Preserve non-tracking params
        {
            name:     "TRK-009: preserve non-tracking params",
            input:    "https://example.com/page?id=123&utm_source=google&name=test",
            expected: "https://example.com/page?id=123&name=test",
        },
        // TRK-010: Remove ref parameter
        {
            name:     "TRK-010: remove ref parameter",
            input:    "https://example.com/page?ref=homepage",
            expected: "https://example.com/page",
        },
        // TRK-011: Remove source parameter
        {
            name:     "TRK-011: remove source parameter",
            input:    "https://example.com/page?source=email",
            expected: "https://example.com/page",
        },
        // TRK-012: Case insensitive tracking params
        {
            name:     "TRK-012: case insensitive tracking param removal",
            input:    "https://example.com/page?UTM_SOURCE=google&Fbclid=abc",
            expected: "https://example.com/page",
        },
        // TRK-013: Tracking param with empty value removed
        {
            name:     "TRK-013: tracking param with empty value removed",
            input:    "https://example.com/page?utm_source=&id=123",
            expected: "https://example.com/page?id=123",
        },
        // TRK-014: All params are tracking = empty query
        {
            name:     "TRK-014: all tracking params removed leaves clean URL",
            input:    "https://example.com/page?utm_source=a&utm_medium=b&utm_campaign=c&fbclid=d&gclid=e",
            expected: "https://example.com/page",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            assert.NoError(t, err)
            assert.Equal(t, tt.expected, result)
        })
    }
}
```

---

## 32.10 WWW Prefix Handling Tests (WWW)

### 32.10.1 Test Cases

```go
func TestNormalizeUrl_WwwPrefix(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
    }{
        // WWW-001: Remove www prefix
        {
            name:     "WWW-001: remove www prefix",
            input:    "https://www.example.com/path",
            expected: "https://example.com/path",
        },
        // WWW-002: WWW with subdomain preserved
        {
            name:     "WWW-002: www subdomain preserved when not at root",
            input:    "https://api.www.example.com/path",
            expected: "https://api.www.example.com/path",
        },
        // WWW-003: Case insensitive www removal
        {
            name:     "WWW-003: case insensitive www removal",
            input:    "https://WWW.example.com/path",
            expected: "https://example.com/path",
        },
        // WWW-004: Mixed case www removal
        {
            name:     "WWW-004: mixed case www removal",
            input:    "https://WwW.example.com/path",
            expected: "https://example.com/path",
        },
        // WWW-005: No www unchanged
        {
            name:     "WWW-005: no www stays unchanged",
            input:    "https://example.com/path",
            expected: "https://example.com/path",
        },
        // WWW-006: www only domain
        {
            name:     "WWW-006: www-only domain preserved",
            input:    "https://www.com/path",
            expected: "https://www.com/path",
        },
        // WWW-007: wwww (four w's) preserved
        {
            name:     "WWW-007: wwww not removed (not www)",
            input:    "https://wwww.example.com/path",
            expected: "https://wwww.example.com/path",
        },
        // WWW-008: www in path preserved
        {
            name:     "WWW-008: www in path preserved",
            input:    "https://example.com/www/path",
            expected: "https://example.com/www/path",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            assert.NoError(t, err)
            assert.Equal(t, tt.expected, result)
        })
    }
}
```

---

## 32.11 Encoding Normalization Tests (ENC)

### 32.11.1 Test Cases

```go
func TestNormalizeUrl_Encoding(t *testing.T) {
    tests := []struct {
        name     string
        input    string
        expected string
        wantErr  bool
    }{
        // ENC-001: Normalize percent encoding case
        {
            name:     "ENC-001: uppercase percent encoding",
            input:    "https://example.com/path%2fwith%2Fslash",
            expected: "https://example.com/path%2Fwith%2Fslash",
        },
        // ENC-002: Decode unreserved characters
        {
            name:     "ENC-002: decode unreserved chars",
            input:    "https://example.com/%61%62%63",
            expected: "https://example.com/abc",
        },
        // ENC-003: Keep reserved characters encoded
        {
            name:     "ENC-003: keep reserved chars encoded",
            input:    "https://example.com/path%3Fquery",
            expected: "https://example.com/path%3Fquery",
        },
        // ENC-004: Invalid percent encoding rejected
        {
            name:    "ENC-004: invalid percent encoding rejected",
            input:   "https://example.com/path%GG",
            wantErr: true,
        },
        // ENC-005: Incomplete percent encoding rejected
        {
            name:    "ENC-005: incomplete percent encoding rejected",
            input:   "https://example.com/path%2",
            wantErr: true,
        },
        // ENC-006: Double encoding prevention
        {
            name:     "ENC-006: prevent double encoding",
            input:    "https://example.com/path%252F",
            expected: "https://example.com/path%252F",
        },
        // ENC-007: Null byte rejected
        {
            name:    "ENC-007: null byte rejected",
            input:   "https://example.com/path%00value",
            wantErr: true,
        },
        // ENC-008: High ASCII encoded
        {
            name:     "ENC-008: high ASCII characters encoded",
            input:    "https://example.com/path©",
            expected: "https://example.com/path%C2%A9",
        },
        // ENC-009: Emoji in path encoded
        {
            name:     "ENC-009: emoji in path encoded",
            input:    "https://example.com/🎉/party",
            expected: "https://example.com/%F0%9F%8E%89/party",
        },
        // ENC-010: Valid UTF-8 sequences preserved
        {
            name:     "ENC-010: valid UTF-8 encoded correctly",
            input:    "https://example.com/日本",
            expected: "https://example.com/%E6%97%A5%E6%9C%AC",
        },
        // ENC-011: Invalid UTF-8 rejected
        {
            name:    "ENC-011: invalid UTF-8 sequence rejected",
            input:   "https://example.com/path\xff\xfe",
            wantErr: true,
        },
        // ENC-012: Normalize pre-encoded UTF-8
        {
            name:     "ENC-012: pre-encoded UTF-8 preserved",
            input:    "https://example.com/%E6%97%A5%E6%9C%AC",
            expected: "https://example.com/%E6%97%A5%E6%9C%AC",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            result, err := normalizer.Normalize(tt.input)
            if tt.wantErr {
                assert.Error(t, err)
            } else {
                assert.NoError(t, err)
                assert.Equal(t, tt.expected, result)
            }
        })
    }
}
```

---

## 32.12 Error Handling Tests (ERR)

### 32.12.1 Test Cases

```go
func TestNormalizeUrl_ErrorHandling(t *testing.T) {
    tests := []struct {
        name    string
        input   string
        errCode string
    }{
        // ERR-001: Empty URL
        {
            name:    "ERR-001: empty URL rejected",
            input:   "",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-002: Whitespace only URL
        {
            name:    "ERR-002: whitespace URL rejected",
            input:   "   \t\n  ",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-003: Missing scheme
        {
            name:    "ERR-003: missing scheme rejected",
            input:   "example.com/path",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-004: Relative URL rejected
        {
            name:    "ERR-004: relative URL rejected",
            input:   "/path/to/page",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-005: Protocol-relative URL rejected
        {
            name:    "ERR-005: protocol-relative URL rejected",
            input:   "//example.com/path",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-006: URL too long
        {
            name:    "ERR-006: URL exceeding max length rejected",
            input:   "https://example.com/" + strings.Repeat("a", 2048),
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-007: Malformed URL
        {
            name:    "ERR-007: malformed URL rejected",
            input:   "https://[invalid",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-008: Control characters rejected
        {
            name:    "ERR-008: control characters rejected",
            input:   "https://example.com/path\x00value",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-009: Newline in URL rejected
        {
            name:    "ERR-009: newline in URL rejected",
            input:   "https://example.com/path\ninjection",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
        // ERR-010: Tab in URL rejected
        {
            name:    "ERR-010: tab in URL rejected",
            input:   "https://example.com/path\tvalue",
            errCode: "ERR_KNOWLEDGE_INVALID_URL",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            _, err := normalizer.Normalize(tt.input)
            assert.Error(t, err)
            assert.Contains(t, err.Error(), tt.errCode)
        })
    }
}
```

---

## 32.13 Idempotency Tests (IDM)

### 32.13.1 Test Cases

```go
func TestNormalizeUrl_Idempotency(t *testing.T) {
    tests := []struct {
        name  string
        input string
    }{
        // IDM-001: Already normalized URL
        {
            name:  "IDM-001: already normalized URL unchanged",
            input: "https://example.com/path?a=1&b=2",
        },
        // IDM-002: Complex normalized URL
        {
            name:  "IDM-002: complex URL idempotent",
            input: "https://api.example.com:8080/v1/users?id=123&sort=name",
        },
        // IDM-003: URL with encoded characters
        {
            name:  "IDM-003: encoded URL idempotent",
            input: "https://example.com/path%20with%20spaces?q=hello%20world",
        },
        // IDM-004: Root URL
        {
            name:  "IDM-004: root URL idempotent",
            input: "https://example.com/",
        },
        // IDM-005: Unicode URL (pre-encoded)
        {
            name:  "IDM-005: unicode URL idempotent",
            input: "https://example.com/%E6%97%A5%E6%9C%AC",
        },
    }

    normalizer := NewUrlNormalizer()
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            // First normalization
            first, err := normalizer.Normalize(tt.input)
            assert.NoError(t, err)
            
            // Second normalization of result
            second, err := normalizer.Normalize(first)
            assert.NoError(t, err)
            
            // Third normalization
            third, err := normalizer.Normalize(second)
            assert.NoError(t, err)
            
            // All should be identical
            assert.Equal(t, first, second, "First and second normalization should match")
            assert.Equal(t, second, third, "Second and third normalization should match")
        })
    }
}
```

---

## 32.14 Benchmark Tests

### 32.14.1 Performance Benchmarks

```go
func BenchmarkNormalizeUrl(b *testing.B) {
    normalizer := NewUrlNormalizer()
    
    benchmarks := []struct {
        name  string
        input string
    }{
        {"simple", "https://example.com/path"},
        {"with_query", "https://example.com/path?a=1&b=2&c=3"},
        {"with_tracking", "https://example.com/path?utm_source=google&fbclid=abc&id=123"},
        {"complex", "https://WWW.Example.COM:443/Path/To/Page/?z=1&a=2&utm_source=test#section"},
        {"unicode", "https://example.com/日本語/ページ?query=値"},
        {"long_query", "https://example.com/path?" + strings.Repeat("key=value&", 50)},
    }
    
    for _, bm := range benchmarks {
        b.Run(bm.name, func(b *testing.B) {
            for i := 0; i < b.N; i++ {
                _, _ = normalizer.Normalize(bm.input)
            }
        })
    }
}

// Expected results:
// BenchmarkNormalizeUrl/simple-8          5000000    250 ns/op    128 B/op    2 allocs/op
// BenchmarkNormalizeUrl/with_query-8      2000000    600 ns/op    256 B/op    5 allocs/op
// BenchmarkNormalizeUrl/with_tracking-8   1500000    800 ns/op    320 B/op    7 allocs/op
// BenchmarkNormalizeUrl/complex-8         1000000   1200 ns/op    512 B/op   10 allocs/op
// BenchmarkNormalizeUrl/unicode-8          500000   2500 ns/op    768 B/op   12 allocs/op
// BenchmarkNormalizeUrl/long_query-8       200000   6000 ns/op   2048 B/op   20 allocs/op
```

---

## 32.15 Test Data Factories

### 32.15.1 URL Factory

```go
package testutil

import (
    "fmt"
    "strings"
    "github.com/brianvoe/gofakeit/v6"
)

// UrlFactory generates test URLs
type UrlFactory struct {
    faker *gofakeit.Faker
}

func NewUrlFactory(seed int64) *UrlFactory {
    return &UrlFactory{
        faker: gofakeit.New(seed),
    }
}

// ValidUrl generates a random valid URL
func (f *UrlFactory) ValidUrl() string {
    return fmt.Sprintf("https://%s.com/%s",
        f.faker.DomainName(),
        f.faker.LoremIpsumWord())
}

// UrlWithTracking generates URL with tracking params
func (f *UrlFactory) UrlWithTracking() string {
    tracking := []string{"utm_source", "utm_medium", "utm_campaign", "fbclid", "gclid"}
    params := make([]string, len(tracking))
    for i, t := range tracking {
        params[i] = fmt.Sprintf("%s=%s", t, f.faker.LetterN(8))
    }
    return fmt.Sprintf("https://%s.com/page?%s",
        f.faker.DomainName(),
        strings.Join(params, "&"))
}

// UrlWithWww generates URL with www prefix
func (f *UrlFactory) UrlWithWww() string {
    return fmt.Sprintf("https://www.%s.com/%s",
        f.faker.DomainName(),
        f.faker.LoremIpsumWord())
}

// UrlWithTrailingSlash generates URL with trailing slash
func (f *UrlFactory) UrlWithTrailingSlash() string {
    return fmt.Sprintf("https://%s.com/path/to/page/",
        f.faker.DomainName())
}

// UrlWithUnsortedQuery generates URL with unsorted query params
func (f *UrlFactory) UrlWithUnsortedQuery(paramCount int) string {
    params := make([]string, paramCount)
    for i := 0; i < paramCount; i++ {
        key := string(rune('z' - i%26))
        params[i] = fmt.Sprintf("%s=%d", key, i)
    }
    return fmt.Sprintf("https://%s.com/search?%s",
        f.faker.DomainName(),
        strings.Join(params, "&"))
}

// InvalidUrl generates various invalid URLs
func (f *UrlFactory) InvalidUrl() string {
    invalids := []string{
        "",
        "   ",
        "not-a-url",
        "ftp://example.com",
        "file:///etc/passwd",
        "javascript:alert(1)",
        "/relative/path",
        "//protocol-relative.com",
    }
    return invalids[f.faker.IntRange(0, len(invalids)-1)]
}
```

---

## 32.16 Test Coverage Requirements

### 32.16.1 Coverage Matrix

| Component | Target | Critical Paths |
|-----------|--------|----------------|
| Scheme validation | 100% | All branches |
| Host normalization | 95% | IDN, IPv6 |
| Port handling | 100% | Default removal |
| Path normalization | 95% | Traversal, encoding |
| Query sorting | 100% | All sort scenarios |
| Tracking removal | 100% | All known params |
| WWW removal | 100% | Edge cases |
| Error handling | 100% | All error codes |

### 32.16.2 CI Integration

```yaml
# .github/workflows/test-normalizer.yml
name: URL Normalizer Tests

on:
  push:
    paths:
      - 'internal/knowledge/normalizer/**'
      - 'internal/knowledge/normalizer_test.go'

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - uses: actions/setup-go@v5
        with:
          go-version: '1.22'
      
      - name: Run Tests
        run: |
          go test -v -race -coverprofile=coverage.out ./internal/knowledge/...
          go tool cover -func=coverage.out
      
      - name: Check Coverage
        run: |
          COVERAGE=$(go tool cover -func=coverage.out | grep total | awk '{print $3}' | sed 's/%//')
          if (( $(echo "$COVERAGE < 90" | bc -l) )); then
            echo "Coverage $COVERAGE% is below 90% threshold"
            exit 1
          fi
      
      - name: Run Benchmarks
        run: go test -bench=. -benchmem ./internal/knowledge/...
```

---

## 32.17 Cross-References

| Specification | Relationship |
|---------------|--------------|
| 31-knowledge-memory-system.md | URL normalizer implementation |
| 09-seeding-configuration.md | Validation rules |
| 30-config-validator-tests.md | Testing patterns |
| general-spec/03-quality/01-testing-standards-quality.md | Test standards |

---

## 32.18 Summary

This test specification provides:

1. **113 test cases** across 11 categories
2. **Comprehensive edge case coverage** for URL normalization
3. **Performance benchmarks** with expected thresholds
4. **Test factories** for generating varied test data
5. **CI integration** for automated quality gates
6. **90%+ coverage requirements** for production readiness
