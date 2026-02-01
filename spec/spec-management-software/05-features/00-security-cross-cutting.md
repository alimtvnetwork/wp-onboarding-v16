# Cross-Cutting Security Specification

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Status:** Active  
> **Purpose:** Centralized security requirements for all features

---

## Overview

This document defines security requirements that apply across ALL feature modules. Every implementation MUST follow these patterns.

---

## 1. Input Validation

### 1.1 Validation Rules

| Input Type | Validation | Library |
|------------|------------|---------|
| String fields | Length limits, character whitelist | `go-playground/validator` |
| Email | RFC 5322 format | `net/mail` |
| URLs | Protocol whitelist (http, https) | `net/url` |
| File paths | No path traversal (`..`), relative only | Custom |
| JSON payloads | Schema validation | `xeipuuv/gojsonschema` |
| HTML content | Sanitize all user HTML | `microcosm-cc/bluemonday` |

### 1.2 Server-Side Validation (REQUIRED)

```go
// NEVER trust client-side validation alone
func ValidateInput[T any](input T) error {
    validate := validator.New()
    if err := validate.Struct(input); err != nil {
        return NewError(ERR_VALIDATION, sanitizeError(err))
    }
    return nil
}
```

### 1.3 Path Traversal Prevention

```go
// ALWAYS validate file paths
func ValidatePath(basePath, requestedPath string) (string, error) {
    // Resolve to absolute path
    fullPath := filepath.Join(basePath, requestedPath)
    absPath, err := filepath.Abs(fullPath)
    if err != nil {
        return "", ErrInvalidPath
    }
    
    // Ensure path is within base directory
    absBase, _ := filepath.Abs(basePath)
    if !strings.HasPrefix(absPath, absBase) {
        return "", ErrPathTraversal
    }
    
    return absPath, nil
}
```

---

## 2. Authentication & Authorization

### 2.1 JWT Requirements

| Requirement | Value |
|-------------|-------|
| Algorithm | RS256 (asymmetric) or HS256 (symmetric) |
| Access token TTL | 15 minutes |
| Refresh token TTL | 7 days |
| Token storage | HttpOnly cookie (preferred) or secure storage |
| Refresh rotation | New refresh token on each use |

### 2.2 Authorization Checks

```go
// EVERY endpoint must verify authorization
func AuthMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        token := extractToken(r)
        claims, err := validateToken(token)
        if err != nil {
            http.Error(w, "Unauthorized", http.StatusUnauthorized)
            return
        }
        
        ctx := context.WithValue(r.Context(), "user", claims)
        next.ServeHTTP(w, r.WithContext(ctx))
    })
}

// Resource-level authorization
func canAccessResource(user *User, resource *Resource, action string) bool {
    // Owner check
    if resource.OwnerId == user.Id {
        return true
    }
    
    // Role-based check
    return user.HasPermission(resource.Type, action)
}
```

---

## 3. Cryptography

### 3.1 Password Hashing

| Algorithm | Use Case | Parameters |
|-----------|----------|------------|
| Argon2id | New passwords | m=64MB, t=3, p=4 |
| bcrypt | Legacy (read-only) | cost=12 |

### 3.2 Encryption at Rest

| Data Type | Algorithm | Key Management |
|-----------|-----------|----------------|
| API keys | AES-256-GCM | Environment variable |
| Sensitive fields | AES-256-GCM | Derived from master key |
| Database | SQLite encryption | SEE extension (optional) |

### 3.3 Secrets Management

```go
// NEVER log secrets
func loadSecret(name string) (string, error) {
    value := os.Getenv(name)
    if value == "" {
        return "", fmt.Errorf("missing secret: %s", name)
    }
    return value, nil
}

// NEVER commit secrets to code
// ❌ const apiKey = "sk-xxxxx"
// ✅ apiKey := os.Getenv("API_KEY")
```

---

## 4. API Security

### 4.1 Rate Limiting

| Endpoint Type | Rate Limit | Window |
|---------------|------------|--------|
| Login/Register | 5 requests | 1 minute |
| API endpoints | 100 requests | 1 minute |
| File uploads | 10 requests | 1 minute |
| AI generation | 20 requests | 1 minute |

### 4.2 CORS Configuration

```go
func corsMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        // Whitelist specific origins in production
        origin := r.Header.Get("Origin")
        if isAllowedOrigin(origin) {
            w.Header().Set("Access-Control-Allow-Origin", origin)
        }
        w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
        w.Header().Set("Access-Control-Allow-Headers", "Authorization, Content-Type")
        w.Header().Set("Access-Control-Allow-Credentials", "true")
        w.Header().Set("Access-Control-Max-Age", "86400")
        
        if r.Method == "OPTIONS" {
            w.WriteHeader(http.StatusNoContent)
            return
        }
        next.ServeHTTP(w, r)
    })
}
```

### 4.3 Security Headers

```go
func securityHeadersMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("X-Content-Type-Options", "nosniff")
        w.Header().Set("X-Frame-Options", "DENY")
        w.Header().Set("X-XSS-Protection", "1; mode=block")
        w.Header().Set("Strict-Transport-Security", "max-age=31536000; includeSubDomains")
        w.Header().Set("Content-Security-Policy", "default-src 'self'")
        w.Header().Set("Referrer-Policy", "strict-origin-when-cross-origin")
        next.ServeHTTP(w, r)
    })
}
```

---

## 5. Logging & Audit

### 5.1 PII Redaction

```go
// ALWAYS redact PII from logs
var sensitiveFields = []string{"password", "token", "secret", "apiKey", "email"}

func redactSensitive(data map[string]interface{}) map[string]interface{} {
    for _, field := range sensitiveFields {
        if _, exists := data[field]; exists {
            data[field] = "[REDACTED]"
        }
    }
    return data
}
```

### 5.2 Audit Events

| Event | Data Logged |
|-------|-------------|
| Login success | userId, timestamp, IP (hashed) |
| Login failure | identifier (hashed), timestamp, IP (hashed) |
| Password change | userId, timestamp |
| Permission change | userId, adminId, oldRole, newRole |
| Resource delete | userId, resourceId, resourceType |

---

## 6. File System Security

### 6.1 Upload Restrictions

| Restriction | Value |
|-------------|-------|
| Max file size | 10 MB |
| Allowed extensions | .md, .json, .yaml, .txt |
| Mime type validation | Required |
| Virus scanning | Optional (ClamAV) |

### 6.2 Storage Rules

```go
// ALWAYS store uploads outside web root
const uploadDir = "/var/data/uploads"

// ALWAYS generate random filenames
func generateSafeFilename(originalName string) string {
    ext := filepath.Ext(originalName)
    if !isAllowedExtension(ext) {
        return ""
    }
    return fmt.Sprintf("%s%s", uuid.New().String(), ext)
}
```

---

## 7. Error Handling Security

### 7.1 Error Response Rules

```go
// NEVER expose internal errors to clients
func handleError(w http.ResponseWriter, err error) {
    var appErr *AppError
    if errors.As(err, &appErr) {
        // Known application error - safe to expose
        writeJSON(w, appErr.StatusCode, ErrorResponse{
            Code:    appErr.Code,
            Message: appErr.Message,
        })
        return
    }
    
    // Unknown error - log internally, return generic message
    log.Error("internal error", "error", err)
    writeJSON(w, 500, ErrorResponse{
        Code:    "ERR_INTERNAL",
        Message: "An internal error occurred",
    })
}
```

---

## 8. Dependency Security

### 8.1 Vulnerability Scanning

```bash
# Run weekly
go install golang.org/x/vuln/cmd/govulncheck@latest
govulncheck ./...

# For npm dependencies
npm audit --audit-level=high
```

### 8.2 Dependency Update Policy

| Severity | Response Time |
|----------|---------------|
| Critical | 24 hours |
| High | 7 days |
| Medium | 30 days |
| Low | Next release |

---

## 9. Security Checklist (Per Feature)

Every feature spec MUST include:

- [ ] Input validation for all user inputs
- [ ] Authorization checks for all endpoints
- [ ] Rate limiting configuration
- [ ] Error handling without information leakage
- [ ] Audit logging for sensitive operations
- [ ] PII handling policy
- [ ] Dependency security review

---

## Cross-References

- [Authentication Spec](./01-authentication/01-authentication.md)
- [Error Management](../06-error-management/00-overview.md)
- [API Specification](../api/openapi.yaml)

---

*This document provides the security foundation for all feature implementations.*
