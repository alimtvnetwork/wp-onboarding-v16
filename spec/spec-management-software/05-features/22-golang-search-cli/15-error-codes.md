# Golang Search CLI - Error Code Registry

**Version:** 1.2.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Comprehensive error code registry for the Golang Search CLI application. All error codes are organized by domain and include constant identifiers, descriptions, and retryability flags for programmatic handling.

**Cross-References:**
- [CLI Framework](./01-cli-framework.md)
- [Configuration](./02-configuration.md)
- [Method Switching](./08-method-switching.md)
- [Error Management Overview](../../06-error-management/00-overview.md)

---

## Exit Codes (CLI Process)

Exit codes returned when the CLI process terminates.

| Code | Constant | Description | Recovery Action |
|------|----------|-------------|-----------------|
| 0 | `EXIT_SUCCESS` | Operation completed successfully | None |
| 1 | `EXIT_GENERAL` | General/unspecified error | Check logs |
| 2 | `EXIT_CONFIG` | Configuration error | Validate config file |
| 3 | `EXIT_DATABASE` | Database connection/query error | Check database path |
| 4 | `EXIT_NETWORK` | Network/HTTP error | Check connectivity |
| 5 | `EXIT_ALL_BLOCKED` | All search methods blocked | Wait for cooldown |
| 6 | `EXIT_QUOTA` | API quota exhausted | Wait or use different method |
| 7 | `EXIT_AUTH` | Authentication/authorization error | Check API credentials |
| 8 | `EXIT_TIMEOUT` | Operation timeout | Retry or increase timeout |
| 9 | `EXIT_INVALID_INPUT` | Invalid command arguments | Check CLI usage |
| 10 | `EXIT_SHUTDOWN` | Graceful shutdown completed | None |

---

## Application Error Codes

### Error Code Ranges

| Range | Domain | Description |
|-------|--------|-------------|
| 1xxx | Validation | Input validation and format errors |
| 2xxx | Authentication | API keys, OAuth tokens, permissions |
| 3xxx | Database | SQLite operations and migrations |
| 4xxx | Network | HTTP requests, DNS, TLS, rate limits |
| 5xxx | Blocking | CAPTCHA, IP blocks, method cooldowns |
| 6xxx | Quota | API usage limits and thresholds |
| 7xxx | Parser | HTML parsing and selector errors |
| 8xxx | Cache | Caching operations and expiry |
| 9xxx | Export | RAG export and file operations |
| 10xxx | Resource | Memory, goroutines, system resources |
| 11xxx | Config | Runtime configuration issues |
| 12xxx | Encryption | Token encryption/decryption |

---

### 1xxx - Validation Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 1001 | `ERR_INVALID_QUERY` | Empty or malformed search query | No | 9 |
| 1002 | `ERR_INVALID_CONFIG_PATH` | Config file path not found | No | 2 |
| 1003 | `ERR_INVALID_CONFIG_FORMAT` | Config file is not valid JSON | No | 2 |
| 1004 | `ERR_INVALID_CONFIG_SCHEMA` | Config fails JSON schema validation | No | 2 |
| 1005 | `ERR_INVALID_OUTPUT_FORMAT` | Unknown output format specified | No | 9 |
| 1006 | `ERR_INVALID_ENGINE` | Unknown search engine specified | No | 9 |
| 1007 | `ERR_INVALID_METHOD` | Unknown retrieval method specified | No | 9 |
| 1008 | `ERR_INVALID_WEIGHT_RANGE` | Weight value outside 0.0-1.0 range | No | 2 |
| 1009 | `ERR_INVALID_WEIGHT_SUM` | Weights do not sum to 1.0 | No | 2 |
| 1010 | `ERR_INVALID_DURATION` | Invalid duration format | No | 2 |
| 1011 | `ERR_INVALID_DEPTH` | Nested search depth out of range | No | 9 |
| 1012 | `ERR_INVALID_LIMIT` | Results limit out of range | No | 9 |

---

### 2xxx - Authentication Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 2001 | `ERR_AUTH_KEY_MISSING` | Required API key not configured | No | 7 |
| 2002 | `ERR_AUTH_KEY_INVALID` | API key rejected by service | No | 7 |
| 2003 | `ERR_AUTH_TOKEN_MISSING` | OAuth access token not found | No | 7 |
| 2004 | `ERR_AUTH_TOKEN_EXPIRED` | OAuth token expired, refresh required | Yes | 7 |
| 2005 | `ERR_AUTH_TOKEN_REVOKED` | OAuth token revoked by user/admin | No | 7 |
| 2006 | `ERR_AUTH_REFRESH_FAILED` | OAuth token refresh failed | Yes | 7 |
| 2007 | `ERR_AUTH_SCOPE_INSUFFICIENT` | OAuth token lacks required scopes | No | 7 |
| 2008 | `ERR_AUTH_CSE_ID_MISSING` | Google Custom Search Engine ID missing | No | 7 |
| 2009 | `ERR_AUTH_CSE_ID_INVALID` | Google CSE ID rejected | No | 7 |

---

### 3xxx - Database Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 3001 | `ERR_DB_OPEN` | Cannot open SQLite database file | Yes | 3 |
| 3002 | `ERR_DB_CREATE` | Cannot create database file | No | 3 |
| 3003 | `ERR_DB_PERMISSION` | Insufficient permissions on database | No | 3 |
| 3004 | `ERR_DB_MIGRATION_FAILED` | Database migration failed | No | 3 |
| 3005 | `ERR_DB_MIGRATION_DIRTY` | Migration in dirty state | No | 3 |
| 3006 | `ERR_DB_QUERY_FAILED` | SQL query execution failed | Yes | 3 |
| 3007 | `ERR_DB_RECORD_NOT_FOUND` | Requested record does not exist | No | 3 |
| 3008 | `ERR_DB_CONSTRAINT` | Unique/foreign key constraint violation | No | 3 |
| 3009 | `ERR_DB_LOCKED` | Database locked by another process | Yes | 3 |
| 3010 | `ERR_DB_CORRUPTION` | Database file corrupted | No | 3 |
| 3011 | `ERR_DB_TRANSACTION` | Transaction commit/rollback failed | Yes | 3 |

---

### 4xxx - Network Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 4001 | `ERR_HTTP_TIMEOUT` | HTTP request timed out | Yes | 8 |
| 4002 | `ERR_HTTP_DNS` | DNS resolution failed | Yes | 4 |
| 4003 | `ERR_HTTP_TLS` | TLS handshake failed | Yes | 4 |
| 4004 | `ERR_HTTP_CONNECTION` | TCP connection failed | Yes | 4 |
| 4005 | `ERR_HTTP_RESET` | Connection reset by peer | Yes | 4 |
| 4006 | `ERR_HTTP_STATUS_4XX` | HTTP 4xx client error | No | 4 |
| 4007 | `ERR_HTTP_STATUS_5XX` | HTTP 5xx server error | Yes | 4 |
| 4008 | `ERR_RATE_LIMITED` | Rate limited (HTTP 429) | Yes | 4 |
| 4009 | `ERR_PROXY_AUTH` | Proxy authentication failed | No | 4 |
| 4010 | `ERR_PROXY_CONNECTION` | Proxy connection failed | Yes | 4 |
| 4011 | `ERR_PROXY_TIMEOUT` | Proxy request timed out | Yes | 8 |
| 4012 | `ERR_REDIRECT_LOOP` | Too many HTTP redirects | No | 4 |
| 4013 | `ERR_RESPONSE_TOO_LARGE` | Response body exceeds limit | No | 4 |

---

### 5xxx - Blocking Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 5001 | `ERR_BLOCKED_CAPTCHA` | CAPTCHA challenge detected | No | 5 |
| 5002 | `ERR_BLOCKED_IP` | IP address blocked by service | No | 5 |
| 5003 | `ERR_BLOCKED_USER_AGENT` | User-Agent rejected | No | 5 |
| 5004 | `ERR_BLOCKED_REGION` | Request blocked for region | No | 5 |
| 5005 | `ERR_METHOD_COOLDOWN` | Search method in cooldown period | Yes | 5 |
| 5006 | `ERR_ALL_METHODS_BLOCKED` | All search methods blocked | Yes | 5 |
| 5007 | `ERR_ENGINE_UNAVAILABLE` | Search engine temporarily unavailable | Yes | 5 |
| 5008 | `ERR_BOT_DETECTION` | Bot/automation detection triggered | No | 5 |

---

### 6xxx - Quota Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 6001 | `ERR_QUOTA_DAILY` | Daily API quota exceeded | No | 6 |
| 6002 | `ERR_QUOTA_MONTHLY` | Monthly API quota exceeded | No | 6 |
| 6003 | `ERR_QUOTA_PER_SECOND` | Requests per second limit hit | Yes | 6 |
| 6004 | `ERR_QUOTA_PER_MINUTE` | Requests per minute limit hit | Yes | 6 |
| 6005 | `ERR_QUOTA_BILLING` | Billing quota exceeded | No | 6 |
| 6006 | `ERR_QUOTA_USER` | Per-user quota exceeded | No | 6 |

---

### 7xxx - Parser Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 7001 | `ERR_PARSE_HTML` | HTML document parsing failed | Yes | 1 |
| 7002 | `ERR_PARSE_JSON` | JSON response parsing failed | Yes | 1 |
| 7003 | `ERR_SELECTOR_NOT_FOUND` | CSS selector matched nothing | Yes | 1 |
| 7004 | `ERR_SELECTOR_INVALID` | Invalid CSS selector syntax | No | 1 |
| 7005 | `ERR_SELECTOR_VERSION` | Selector version mismatch | Yes | 1 |
| 7006 | `ERR_EMPTY_RESULTS` | No results found (informational) | No | 0 |
| 7007 | `ERR_MALFORMED_URL` | Cannot parse extracted URL | No | 1 |
| 7008 | `ERR_ENCODING` | Character encoding error | Yes | 1 |

---

### 8xxx - Cache Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 8001 | `ERR_CACHE_MISS` | Cache entry not found | No | 0 |
| 8002 | `ERR_CACHE_EXPIRED` | Cache entry expired | No | 0 |
| 8003 | `ERR_CACHE_CORRUPT` | Cache entry corrupted | No | 1 |
| 8004 | `ERR_CACHE_WRITE` | Failed to write cache entry | Yes | 1 |
| 8005 | `ERR_CACHE_CLEANUP` | Cache cleanup failed | Yes | 1 |

---

### 9xxx - Export Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 9001 | `ERR_EXPORT_PATH` | Export path not accessible | No | 1 |
| 9002 | `ERR_EXPORT_PERMISSION` | Insufficient write permissions | No | 1 |
| 9003 | `ERR_EXPORT_FORMAT` | Invalid export format specified | No | 9 |
| 9004 | `ERR_EXPORT_SERIALIZE` | Failed to serialize export data | No | 1 |
| 9005 | `ERR_EXPORT_DISK_FULL` | Insufficient disk space | No | 1 |

---

### 10xxx - Resource Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 10001 | `ERR_RESOURCE_TIMEOUT` | Resource acquisition timeout | Yes | 8 |
| 10002 | `ERR_RESOURCE_GOROUTINE_LIMIT` | Maximum goroutines reached | Yes | 1 |
| 10003 | `ERR_RESOURCE_MEMORY` | Memory limit exceeded | Yes | 1 |
| 10004 | `ERR_RESOURCE_FILE_DESCRIPTORS` | File descriptor limit reached | Yes | 1 |
| 10005 | `ERR_SHUTDOWN_TIMEOUT` | Graceful shutdown timed out | No | 10 |

---

### 11xxx - Configuration Runtime Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 11001 | `ERR_CONFIG_RELOAD` | Configuration reload failed | Yes | 2 |
| 11002 | `ERR_CONFIG_ENV_MISSING` | Required environment variable missing | No | 2 |
| 11003 | `ERR_CONFIG_ENV_INVALID` | Environment variable invalid format | No | 2 |
| 11004 | `ERR_SELECTOR_FILE_MISSING` | Selector registry file not found | No | 2 |
| 11005 | `ERR_SELECTOR_FILE_INVALID` | Selector registry validation failed | No | 2 |

---

### 12xxx - Encryption Errors

| Code | Constant | Description | Retryable | Exit Code |
|------|----------|-------------|-----------|-----------|
| 12001 | `ERR_ENCRYPT_KEY_MISSING` | Encryption key not configured | No | 7 |
| 12002 | `ERR_ENCRYPT_KEY_INVALID` | Encryption key invalid format | No | 7 |
| 12003 | `ERR_ENCRYPT_FAILED` | Encryption operation failed | No | 1 |
| 12004 | `ERR_DECRYPT_FAILED` | Decryption operation failed | No | 1 |
| 12005 | `ERR_DECRYPT_CORRUPTED` | Ciphertext appears corrupted | No | 1 |

---

## Go Implementation

### Error Code Constants

```go
// pkg/errors/codes.go

package errors

// ErrorCode represents a structured application error code
type ErrorCode int

// Exit codes for CLI process termination
const (
    ExitSuccess     = 0
    ExitGeneral     = 1
    ExitConfig      = 2
    ExitDatabase    = 3
    ExitNetwork     = 4
    ExitAllBlocked  = 5
    ExitQuota       = 6
    ExitAuth        = 7
    ExitTimeout     = 8
    ExitInvalidInput = 9
    ExitShutdown    = 10
)

// Validation errors (1xxx)
const (
    ErrInvalidQuery        ErrorCode = 1001
    ErrInvalidConfigPath   ErrorCode = 1002
    ErrInvalidConfigFormat ErrorCode = 1003
    ErrInvalidConfigSchema ErrorCode = 1004
    ErrInvalidOutputFormat ErrorCode = 1005
    ErrInvalidEngine       ErrorCode = 1006
    ErrInvalidMethod       ErrorCode = 1007
    ErrInvalidWeightRange  ErrorCode = 1008
    ErrInvalidWeightSum    ErrorCode = 1009
    ErrInvalidDuration     ErrorCode = 1010
    ErrInvalidDepth        ErrorCode = 1011
    ErrInvalidLimit        ErrorCode = 1012
)

// Authentication errors (2xxx)
const (
    ErrAuthKeyMissing       ErrorCode = 2001
    ErrAuthKeyInvalid       ErrorCode = 2002
    ErrAuthTokenMissing     ErrorCode = 2003
    ErrAuthTokenExpired     ErrorCode = 2004
    ErrAuthTokenRevoked     ErrorCode = 2005
    ErrAuthRefreshFailed    ErrorCode = 2006
    ErrAuthScopeInsufficient ErrorCode = 2007
    ErrAuthCSEIdMissing     ErrorCode = 2008
    ErrAuthCSEIdInvalid     ErrorCode = 2009
)

// Database errors (3xxx)
const (
    ErrDBOpen            ErrorCode = 3001
    ErrDBCreate          ErrorCode = 3002
    ErrDBPermission      ErrorCode = 3003
    ErrDBMigrationFailed ErrorCode = 3004
    ErrDBMigrationDirty  ErrorCode = 3005
    ErrDBQueryFailed     ErrorCode = 3006
    ErrDBRecordNotFound  ErrorCode = 3007
    ErrDBConstraint      ErrorCode = 3008
    ErrDBLocked          ErrorCode = 3009
    ErrDBCorruption      ErrorCode = 3010
    ErrDBTransaction     ErrorCode = 3011
)

// Network errors (4xxx)
const (
    ErrHTTPTimeout       ErrorCode = 4001
    ErrHTTPDNS           ErrorCode = 4002
    ErrHTTPTLS           ErrorCode = 4003
    ErrHTTPConnection    ErrorCode = 4004
    ErrHTTPReset         ErrorCode = 4005
    ErrHTTPStatus4XX     ErrorCode = 4006
    ErrHTTPStatus5XX     ErrorCode = 4007
    ErrRateLimited       ErrorCode = 4008
    ErrProxyAuth         ErrorCode = 4009
    ErrProxyConnection   ErrorCode = 4010
    ErrProxyTimeout      ErrorCode = 4011
    ErrRedirectLoop      ErrorCode = 4012
    ErrResponseTooLarge  ErrorCode = 4013
)

// Blocking errors (5xxx)
const (
    ErrBlockedCaptcha     ErrorCode = 5001
    ErrBlockedIP          ErrorCode = 5002
    ErrBlockedUserAgent   ErrorCode = 5003
    ErrBlockedRegion      ErrorCode = 5004
    ErrMethodCooldown     ErrorCode = 5005
    ErrAllMethodsBlocked  ErrorCode = 5006
    ErrEngineUnavailable  ErrorCode = 5007
    ErrBotDetection       ErrorCode = 5008
)

// Quota errors (6xxx)
const (
    ErrQuotaDaily     ErrorCode = 6001
    ErrQuotaMonthly   ErrorCode = 6002
    ErrQuotaPerSecond ErrorCode = 6003
    ErrQuotaPerMinute ErrorCode = 6004
    ErrQuotaBilling   ErrorCode = 6005
    ErrQuotaUser      ErrorCode = 6006
)

// Parser errors (7xxx)
const (
    ErrParseHTML         ErrorCode = 7001
    ErrParseJSON         ErrorCode = 7002
    ErrSelectorNotFound  ErrorCode = 7003
    ErrSelectorInvalid   ErrorCode = 7004
    ErrSelectorVersion   ErrorCode = 7005
    ErrEmptyResults      ErrorCode = 7006
    ErrMalformedURL      ErrorCode = 7007
    ErrEncoding          ErrorCode = 7008
)

// Cache errors (8xxx)
const (
    ErrCacheMiss    ErrorCode = 8001
    ErrCacheExpired ErrorCode = 8002
    ErrCacheCorrupt ErrorCode = 8003
    ErrCacheWrite   ErrorCode = 8004
    ErrCacheCleanup ErrorCode = 8005
)

// Export errors (9xxx)
const (
    ErrExportPath       ErrorCode = 9001
    ErrExportPermission ErrorCode = 9002
    ErrExportFormat     ErrorCode = 9003
    ErrExportSerialize  ErrorCode = 9004
    ErrExportDiskFull   ErrorCode = 9005
)

// Resource errors (10xxx)
const (
    ErrResourceTimeout         ErrorCode = 10001
    ErrResourceGoroutineLimit  ErrorCode = 10002
    ErrResourceMemory          ErrorCode = 10003
    ErrResourceFileDescriptors ErrorCode = 10004
    ErrShutdownTimeout         ErrorCode = 10005
)

// Configuration runtime errors (11xxx)
const (
    ErrConfigReload         ErrorCode = 11001
    ErrConfigEnvMissing     ErrorCode = 11002
    ErrConfigEnvInvalid     ErrorCode = 11003
    ErrSelectorFileMissing  ErrorCode = 11004
    ErrSelectorFileInvalid  ErrorCode = 11005
)

// Encryption errors (12xxx)
const (
    ErrEncryptKeyMissing  ErrorCode = 12001
    ErrEncryptKeyInvalid  ErrorCode = 12002
    ErrEncryptFailed      ErrorCode = 12003
    ErrDecryptFailed      ErrorCode = 12004
    ErrDecryptCorrupted   ErrorCode = 12005
)
```

---

### Error Metadata Registry

```go
// pkg/errors/metadata.go

package errors

// ErrorMetadata contains static information about an error code
type ErrorMetadata struct {
    Code      ErrorCode
    Constant  string
    Domain    string
    Message   string
    Retryable bool
    ExitCode  int
}

// Registry maps error codes to their metadata
var Registry = map[ErrorCode]ErrorMetadata{
    // Validation (1xxx)
    ErrInvalidQuery:        {1001, "ERR_INVALID_QUERY", "validation", "Empty or malformed search query", false, ExitInvalidInput},
    ErrInvalidConfigPath:   {1002, "ERR_INVALID_CONFIG_PATH", "validation", "Config file path not found", false, ExitConfig},
    ErrInvalidConfigFormat: {1003, "ERR_INVALID_CONFIG_FORMAT", "validation", "Config file is not valid JSON", false, ExitConfig},
    ErrInvalidConfigSchema: {1004, "ERR_INVALID_CONFIG_SCHEMA", "validation", "Config fails JSON schema validation", false, ExitConfig},
    ErrInvalidOutputFormat: {1005, "ERR_INVALID_OUTPUT_FORMAT", "validation", "Unknown output format specified", false, ExitInvalidInput},
    ErrInvalidEngine:       {1006, "ERR_INVALID_ENGINE", "validation", "Unknown search engine specified", false, ExitInvalidInput},
    ErrInvalidMethod:       {1007, "ERR_INVALID_METHOD", "validation", "Unknown retrieval method specified", false, ExitInvalidInput},
    ErrInvalidWeightRange:  {1008, "ERR_INVALID_WEIGHT_RANGE", "validation", "Weight value outside 0.0-1.0 range", false, ExitConfig},
    ErrInvalidWeightSum:    {1009, "ERR_INVALID_WEIGHT_SUM", "validation", "Weights do not sum to 1.0", false, ExitConfig},
    ErrInvalidDuration:     {1010, "ERR_INVALID_DURATION", "validation", "Invalid duration format", false, ExitConfig},
    ErrInvalidDepth:        {1011, "ERR_INVALID_DEPTH", "validation", "Nested search depth out of range", false, ExitInvalidInput},
    ErrInvalidLimit:        {1012, "ERR_INVALID_LIMIT", "validation", "Results limit out of range", false, ExitInvalidInput},
    
    // Authentication (2xxx)
    ErrAuthKeyMissing:        {2001, "ERR_AUTH_KEY_MISSING", "auth", "Required API key not configured", false, ExitAuth},
    ErrAuthKeyInvalid:        {2002, "ERR_AUTH_KEY_INVALID", "auth", "API key rejected by service", false, ExitAuth},
    ErrAuthTokenMissing:      {2003, "ERR_AUTH_TOKEN_MISSING", "auth", "OAuth access token not found", false, ExitAuth},
    ErrAuthTokenExpired:      {2004, "ERR_AUTH_TOKEN_EXPIRED", "auth", "OAuth token expired, refresh required", true, ExitAuth},
    ErrAuthTokenRevoked:      {2005, "ERR_AUTH_TOKEN_REVOKED", "auth", "OAuth token revoked by user/admin", false, ExitAuth},
    ErrAuthRefreshFailed:     {2006, "ERR_AUTH_REFRESH_FAILED", "auth", "OAuth token refresh failed", true, ExitAuth},
    ErrAuthScopeInsufficient: {2007, "ERR_AUTH_SCOPE_INSUFFICIENT", "auth", "OAuth token lacks required scopes", false, ExitAuth},
    ErrAuthCSEIdMissing:      {2008, "ERR_AUTH_CSE_ID_MISSING", "auth", "Google Custom Search Engine ID missing", false, ExitAuth},
    ErrAuthCSEIdInvalid:      {2009, "ERR_AUTH_CSE_ID_INVALID", "auth", "Google CSE ID rejected", false, ExitAuth},
    
    // Database (3xxx)
    ErrDBOpen:            {3001, "ERR_DB_OPEN", "database", "Cannot open SQLite database file", true, ExitDatabase},
    ErrDBCreate:          {3002, "ERR_DB_CREATE", "database", "Cannot create database file", false, ExitDatabase},
    ErrDBPermission:      {3003, "ERR_DB_PERMISSION", "database", "Insufficient permissions on database", false, ExitDatabase},
    ErrDBMigrationFailed: {3004, "ERR_DB_MIGRATION_FAILED", "database", "Database migration failed", false, ExitDatabase},
    ErrDBMigrationDirty:  {3005, "ERR_DB_MIGRATION_DIRTY", "database", "Migration in dirty state", false, ExitDatabase},
    ErrDBQueryFailed:     {3006, "ERR_DB_QUERY_FAILED", "database", "SQL query execution failed", true, ExitDatabase},
    ErrDBRecordNotFound:  {3007, "ERR_DB_RECORD_NOT_FOUND", "database", "Requested record does not exist", false, ExitDatabase},
    ErrDBConstraint:      {3008, "ERR_DB_CONSTRAINT", "database", "Unique/foreign key constraint violation", false, ExitDatabase},
    ErrDBLocked:          {3009, "ERR_DB_LOCKED", "database", "Database locked by another process", true, ExitDatabase},
    ErrDBCorruption:      {3010, "ERR_DB_CORRUPTION", "database", "Database file corrupted", false, ExitDatabase},
    ErrDBTransaction:     {3011, "ERR_DB_TRANSACTION", "database", "Transaction commit/rollback failed", true, ExitDatabase},
    
    // Network (4xxx)
    ErrHTTPTimeout:      {4001, "ERR_HTTP_TIMEOUT", "network", "HTTP request timed out", true, ExitTimeout},
    ErrHTTPDNS:          {4002, "ERR_HTTP_DNS", "network", "DNS resolution failed", true, ExitNetwork},
    ErrHTTPTLS:          {4003, "ERR_HTTP_TLS", "network", "TLS handshake failed", true, ExitNetwork},
    ErrHTTPConnection:   {4004, "ERR_HTTP_CONNECTION", "network", "TCP connection failed", true, ExitNetwork},
    ErrHTTPReset:        {4005, "ERR_HTTP_RESET", "network", "Connection reset by peer", true, ExitNetwork},
    ErrHTTPStatus4XX:    {4006, "ERR_HTTP_STATUS_4XX", "network", "HTTP 4xx client error", false, ExitNetwork},
    ErrHTTPStatus5XX:    {4007, "ERR_HTTP_STATUS_5XX", "network", "HTTP 5xx server error", true, ExitNetwork},
    ErrRateLimited:      {4008, "ERR_RATE_LIMITED", "network", "Rate limited (HTTP 429)", true, ExitNetwork},
    ErrProxyAuth:        {4009, "ERR_PROXY_AUTH", "network", "Proxy authentication failed", false, ExitNetwork},
    ErrProxyConnection:  {4010, "ERR_PROXY_CONNECTION", "network", "Proxy connection failed", true, ExitNetwork},
    ErrProxyTimeout:     {4011, "ERR_PROXY_TIMEOUT", "network", "Proxy request timed out", true, ExitTimeout},
    ErrRedirectLoop:     {4012, "ERR_REDIRECT_LOOP", "network", "Too many HTTP redirects", false, ExitNetwork},
    ErrResponseTooLarge: {4013, "ERR_RESPONSE_TOO_LARGE", "network", "Response body exceeds limit", false, ExitNetwork},
    
    // Blocking (5xxx)
    ErrBlockedCaptcha:    {5001, "ERR_BLOCKED_CAPTCHA", "blocking", "CAPTCHA challenge detected", false, ExitAllBlocked},
    ErrBlockedIP:         {5002, "ERR_BLOCKED_IP", "blocking", "IP address blocked by service", false, ExitAllBlocked},
    ErrBlockedUserAgent:  {5003, "ERR_BLOCKED_USER_AGENT", "blocking", "User-Agent rejected", false, ExitAllBlocked},
    ErrBlockedRegion:     {5004, "ERR_BLOCKED_REGION", "blocking", "Request blocked for region", false, ExitAllBlocked},
    ErrMethodCooldown:    {5005, "ERR_METHOD_COOLDOWN", "blocking", "Search method in cooldown period", true, ExitAllBlocked},
    ErrAllMethodsBlocked: {5006, "ERR_ALL_METHODS_BLOCKED", "blocking", "All search methods blocked", true, ExitAllBlocked},
    ErrEngineUnavailable: {5007, "ERR_ENGINE_UNAVAILABLE", "blocking", "Search engine temporarily unavailable", true, ExitAllBlocked},
    ErrBotDetection:      {5008, "ERR_BOT_DETECTION", "blocking", "Bot/automation detection triggered", false, ExitAllBlocked},
    
    // Quota (6xxx)
    ErrQuotaDaily:     {6001, "ERR_QUOTA_DAILY", "quota", "Daily API quota exceeded", false, ExitQuota},
    ErrQuotaMonthly:   {6002, "ERR_QUOTA_MONTHLY", "quota", "Monthly API quota exceeded", false, ExitQuota},
    ErrQuotaPerSecond: {6003, "ERR_QUOTA_PER_SECOND", "quota", "Requests per second limit hit", true, ExitQuota},
    ErrQuotaPerMinute: {6004, "ERR_QUOTA_PER_MINUTE", "quota", "Requests per minute limit hit", true, ExitQuota},
    ErrQuotaBilling:   {6005, "ERR_QUOTA_BILLING", "quota", "Billing quota exceeded", false, ExitQuota},
    ErrQuotaUser:      {6006, "ERR_QUOTA_USER", "quota", "Per-user quota exceeded", false, ExitQuota},
    
    // Parser (7xxx)
    ErrParseHTML:        {7001, "ERR_PARSE_HTML", "parser", "HTML document parsing failed", true, ExitGeneral},
    ErrParseJSON:        {7002, "ERR_PARSE_JSON", "parser", "JSON response parsing failed", true, ExitGeneral},
    ErrSelectorNotFound: {7003, "ERR_SELECTOR_NOT_FOUND", "parser", "CSS selector matched nothing", true, ExitGeneral},
    ErrSelectorInvalid:  {7004, "ERR_SELECTOR_INVALID", "parser", "Invalid CSS selector syntax", false, ExitGeneral},
    ErrSelectorVersion:  {7005, "ERR_SELECTOR_VERSION", "parser", "Selector version mismatch", true, ExitGeneral},
    ErrEmptyResults:     {7006, "ERR_EMPTY_RESULTS", "parser", "No results found (informational)", false, ExitSuccess},
    ErrMalformedURL:     {7007, "ERR_MALFORMED_URL", "parser", "Cannot parse extracted URL", false, ExitGeneral},
    ErrEncoding:         {7008, "ERR_ENCODING", "parser", "Character encoding error", true, ExitGeneral},
    
    // Cache (8xxx)
    ErrCacheMiss:    {8001, "ERR_CACHE_MISS", "cache", "Cache entry not found", false, ExitSuccess},
    ErrCacheExpired: {8002, "ERR_CACHE_EXPIRED", "cache", "Cache entry expired", false, ExitSuccess},
    ErrCacheCorrupt: {8003, "ERR_CACHE_CORRUPT", "cache", "Cache entry corrupted", false, ExitGeneral},
    ErrCacheWrite:   {8004, "ERR_CACHE_WRITE", "cache", "Failed to write cache entry", true, ExitGeneral},
    ErrCacheCleanup: {8005, "ERR_CACHE_CLEANUP", "cache", "Cache cleanup failed", true, ExitGeneral},
    
    // Export (9xxx)
    ErrExportPath:       {9001, "ERR_EXPORT_PATH", "export", "Export path not accessible", false, ExitGeneral},
    ErrExportPermission: {9002, "ERR_EXPORT_PERMISSION", "export", "Insufficient write permissions", false, ExitGeneral},
    ErrExportFormat:     {9003, "ERR_EXPORT_FORMAT", "export", "Invalid export format specified", false, ExitInvalidInput},
    ErrExportSerialize:  {9004, "ERR_EXPORT_SERIALIZE", "export", "Failed to serialize export data", false, ExitGeneral},
    ErrExportDiskFull:   {9005, "ERR_EXPORT_DISK_FULL", "export", "Insufficient disk space", false, ExitGeneral},
    
    // Resource (10xxx)
    ErrResourceTimeout:         {10001, "ERR_RESOURCE_TIMEOUT", "resource", "Resource acquisition timeout", true, ExitTimeout},
    ErrResourceGoroutineLimit:  {10002, "ERR_RESOURCE_GOROUTINE_LIMIT", "resource", "Maximum goroutines reached", true, ExitGeneral},
    ErrResourceMemory:          {10003, "ERR_RESOURCE_MEMORY", "resource", "Memory limit exceeded", true, ExitGeneral},
    ErrResourceFileDescriptors: {10004, "ERR_RESOURCE_FILE_DESCRIPTORS", "resource", "File descriptor limit reached", true, ExitGeneral},
    ErrShutdownTimeout:         {10005, "ERR_SHUTDOWN_TIMEOUT", "resource", "Graceful shutdown timed out", false, ExitShutdown},
    
    // Configuration Runtime (11xxx)
    ErrConfigReload:        {11001, "ERR_CONFIG_RELOAD", "config", "Configuration reload failed", true, ExitConfig},
    ErrConfigEnvMissing:    {11002, "ERR_CONFIG_ENV_MISSING", "config", "Required environment variable missing", false, ExitConfig},
    ErrConfigEnvInvalid:    {11003, "ERR_CONFIG_ENV_INVALID", "config", "Environment variable invalid format", false, ExitConfig},
    ErrSelectorFileMissing: {11004, "ERR_SELECTOR_FILE_MISSING", "config", "Selector registry file not found", false, ExitConfig},
    ErrSelectorFileInvalid: {11005, "ERR_SELECTOR_FILE_INVALID", "config", "Selector registry validation failed", false, ExitConfig},
    
    // Encryption (12xxx)
    ErrEncryptKeyMissing: {12001, "ERR_ENCRYPT_KEY_MISSING", "encryption", "Encryption key not configured", false, ExitAuth},
    ErrEncryptKeyInvalid: {12002, "ERR_ENCRYPT_KEY_INVALID", "encryption", "Encryption key invalid format", false, ExitAuth},
    ErrEncryptFailed:     {12003, "ERR_ENCRYPT_FAILED", "encryption", "Encryption operation failed", false, ExitGeneral},
    ErrDecryptFailed:     {12004, "ERR_DECRYPT_FAILED", "encryption", "Decryption operation failed", false, ExitGeneral},
    ErrDecryptCorrupted:  {12005, "ERR_DECRYPT_CORRUPTED", "encryption", "Ciphertext appears corrupted", false, ExitGeneral},
}
```

---

### AppError Implementation

```go
// pkg/errors/app_error.go

package errors

import (
    "encoding/json"
    "fmt"
)

// AppError represents a structured application error
type AppError struct {
    Code      ErrorCode `json:"code"`
    Constant  string    `json:"constant"`
    Domain    string    `json:"domain"`
    Message   string    `json:"message"`
    Details   string    `json:"details,omitempty"`
    Retryable bool      `json:"retryable"`
    ExitCode  int       `json:"exitCode"`
    Wrapped   error     `json:"-"`
}

// Error implements the error interface
func (e *AppError) Error() string {
    if e.Details != "" {
        return fmt.Sprintf("[%d] %s: %s - %s", e.Code, e.Constant, e.Message, e.Details)
    }
    return fmt.Sprintf("[%d] %s: %s", e.Code, e.Constant, e.Message)
}

// Unwrap returns the wrapped error for errors.Is/As support
func (e *AppError) Unwrap() error {
    return e.Wrapped
}

// JSON returns the error as a JSON string
func (e *AppError) JSON() string {
    data, _ := json.Marshal(e)
    return string(data)
}

// NewError creates a new AppError from an error code
func NewError(code ErrorCode, details string) *AppError {
    meta, ok := Registry[code]
    if !ok {
        meta = ErrorMetadata{
            Code:      code,
            Constant:  "ERR_UNKNOWN",
            Domain:    "unknown",
            Message:   "Unknown error",
            Retryable: false,
            ExitCode:  ExitGeneral,
        }
    }
    
    return &AppError{
        Code:      code,
        Constant:  meta.Constant,
        Domain:    meta.Domain,
        Message:   meta.Message,
        Details:   details,
        Retryable: meta.Retryable,
        ExitCode:  meta.ExitCode,
    }
}

// WrapError wraps an existing error with an AppError
func WrapError(code ErrorCode, details string, wrapped error) *AppError {
    appErr := NewError(code, details)
    appErr.Wrapped = wrapped
    return appErr
}

// IsRetryable checks if an error is retryable
func IsRetryable(err error) bool {
    if appErr, ok := err.(*AppError); ok {
        return appErr.Retryable
    }
    return false
}

// GetExitCode extracts the exit code from an error
func GetExitCode(err error) int {
    if appErr, ok := err.(*AppError); ok {
        return appErr.ExitCode
    }
    return ExitGeneral
}

// GetDomain extracts the domain from an error
func GetDomain(err error) string {
    if appErr, ok := err.(*AppError); ok {
        return appErr.Domain
    }
    return "unknown"
}
```

---

### Usage Examples

#### Creating Errors

```go
package main

import (
    "fmt"
    "gsearch/pkg/errors"
)

func validateQuery(query string) error {
    if query == "" {
        return errors.NewError(errors.ErrInvalidQuery, "query parameter is empty")
    }
    return nil
}

func executeSearch(query string) error {
    // Simulate a rate limit
    return errors.WrapError(
        errors.ErrRateLimited,
        "Google API returned 429",
        fmt.Errorf("http: 429 Too Many Requests"),
    )
}

func main() {
    err := validateQuery("")
    if err != nil {
        appErr := err.(*errors.AppError)
        fmt.Printf("Error: %s\n", appErr.Error())
        fmt.Printf("Retryable: %t\n", appErr.Retryable)
        fmt.Printf("Exit Code: %d\n", appErr.ExitCode)
    }
}
```

#### CLI Exit Code Handling

```go
func main() {
    if err := run(); err != nil {
        exitCode := errors.GetExitCode(err)
        
        // Log error
        log.Error().
            Int("code", int(err.(*errors.AppError).Code)).
            Str("constant", err.(*errors.AppError).Constant).
            Str("domain", err.(*errors.AppError).Domain).
            Msg(err.Error())
        
        os.Exit(exitCode)
    }
    os.Exit(errors.ExitSuccess)
}
```

#### Retry Logic

```go
func executeWithRetry(ctx context.Context, fn func() error, maxAttempts int) error {
    var lastErr error
    
    for attempt := 1; attempt <= maxAttempts; attempt++ {
        lastErr = fn()
        if lastErr == nil {
            return nil
        }
        
        if !errors.IsRetryable(lastErr) {
            return lastErr
        }
        
        // Calculate backoff delay
        delay := calculateBackoff(attempt)
        
        select {
        case <-ctx.Done():
            return ctx.Err()
        case <-time.After(delay):
            continue
        }
    }
    
    return lastErr
}
```

---

## Error Handling Best Practices

### 1. Always Use Constants

```go
// ✓ Correct - uses constant
return errors.NewError(errors.ErrDBQueryFailed, "SELECT failed")

// ✗ Incorrect - magic number
return &AppError{Code: 3006, Message: "query failed"}
```

### 2. Include Contextual Details

```go
// ✓ Correct - includes context
return errors.NewError(errors.ErrInvalidEngine, 
    fmt.Sprintf("engine '%s' not recognized, valid: google, bing, duckduckgo", engine))

// ✗ Incorrect - no context
return errors.NewError(errors.ErrInvalidEngine, "invalid engine")
```

### 3. Wrap Underlying Errors

```go
// ✓ Correct - preserves stack
result, err := db.Query(sql)
if err != nil {
    return errors.WrapError(errors.ErrDBQueryFailed, sql, err)
}

// ✗ Incorrect - loses original error
if err != nil {
    return errors.NewError(errors.ErrDBQueryFailed, "query failed")
}
```

### 4. Check Retryability

```go
// ✓ Correct - respects retryability
if errors.IsRetryable(err) {
    return retry(fn)
}
return err

// ✗ Incorrect - retries non-retryable errors
return retry(fn) // might retry forever on validation errors
```

---

## Error Code Statistics

| Domain | Total Codes | Retryable | Non-Retryable |
|--------|-------------|-----------|---------------|
| Validation | 12 | 0 | 12 |
| Authentication | 9 | 2 | 7 |
| Database | 11 | 5 | 6 |
| Network | 13 | 10 | 3 |
| Blocking | 8 | 3 | 5 |
| Quota | 6 | 2 | 4 |
| Parser | 8 | 5 | 3 |
| Cache | 5 | 2 | 3 |
| Export | 5 | 0 | 5 |
| Resource | 5 | 4 | 1 |
| Configuration | 5 | 1 | 4 |
| Encryption | 5 | 0 | 5 |
| **Total** | **92** | **34** | **58** |

---

## Related Specifications

- [CLI Framework](./01-cli-framework.md) - Exit code usage
- [Configuration](./02-configuration.md) - Configuration validation errors
- [Database Schema](./03-database-schema.md) - Database error handling
- [Method Switching](./08-method-switching.md) - Blocking and retry logic
- [Remediation Plan](./14-remediation-plan.md) - Phase 5 implementation

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-28 | Initial error code registry with 92 codes |
