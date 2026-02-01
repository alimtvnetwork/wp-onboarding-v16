# Error Code Registry

**Version:** 1.2.0  
**Status:** Active  
**Last Updated:** 2026-01-30

---

## Overview

Master registry of all error codes used across the Spec Management Software. This document serves as the single source of truth for error code allocation and definitions.

**Cross-References:**
- [Error Management Overview](./00-overview.md)
- [Backend Error Codes](./backend/01-error-codes.md)
- [Frontend Error Codes](./frontend/01-error-codes.md)
- [API Contracts](../05-features/15-api-client/02-api-contracts.md)
- [brun CLI Error Handling](../05-features/23-build-runner-cli/06-error-handling.md) — Detailed 7100-7599 implementation
- [Code Generation Errors](../05-features/24-code-generation-system/16-error-codes.md) — 12xxx range
- [Project Editor Errors](../05-features/28-project-editor/05-error-codes.md) — 13xxx range

---

## Error Code Architecture

### Code Range Allocation

| Range | Category | Owner | Description |
|-------|----------|-------|-------------|
| 1xxx | Validation | Shared | Input validation, format errors |
| 2xxx | Authentication | Backend | Auth, tokens, sessions |
| 3xxx | Database | Backend | SQLite, queries, transactions |
| 4xxx | External Services | Backend | Network, HTTP, third-party APIs |
| 5xxx | Business Logic | Shared | Domain rules, state, processing |
| 6xxx | File System/Git | Backend | Files, paths, Git operations |
| 7xxx | LLM/Config/CLI | Backend | LLM server, models, config, brun CLI |
| 8xxx | RAG/Knowledge | Backend | RAG, embeddings, knowledge |
| 9xxx | System/Consistency | Backend | System errors, consistency checks |
| 10xxx | Context Window | Backend | Token budgeting, context assembly |
| 11xxx | Instructions | Backend | Instruction system, tasks |
| 12xxx | Code Generation | Backend | AI code generation, Git, credits |
| **13xxx** | **Project Editor** | **Frontend** | **Input persistence, drafts, sync** |

### 7xxx Sub-Range Allocation

| Sub-Range | Category | Description |
|-----------|----------|-------------|
| 7001-7049 | LLM Server | LLM model loading, execution, slots |
| 7050-7099 | Configuration | Main app config management |
| 7100-7199 | brun: CLI/Config | brun CLI args, config.json |
| 7200-7299 | brun: Runtime | PowerShell, Node.js, Go execution |
| 7300-7399 | brun: Ports | Port checking, firewall management |
| 7400-7499 | brun: Build | Compilation, assets, working dirs |
| 7500-7599 | brun: Health | Application health check monitoring |

### 12xxx Sub-Range Allocation (Code Generation)

| Sub-Range | Category | Description |
|-----------|----------|-------------|
| 12000-12099 | General | Core code generation errors |
| 12100-12199 | Guidelines | Guideline resolution errors |
| 12200-12299 | Planning | Plan generation errors |
| 12300-12399 | Execution | Parallel execution errors |
| 12400-12499 | Git | Git operation errors |
| 12500-12599 | Build | Build verification errors |
| 12600-12699 | Credits | Credit system errors |
| 12700-12799 | Repository | Repository structure errors |

### 13xxx Sub-Range Allocation (Project Editor)

| Sub-Range | Category | Description |
|-----------|----------|-------------|
| 13000-13099 | General | Module-level errors |
| 13100-13199 | Input Persistence | localStorage/IndexedDB errors |
| 13200-13299 | Draft Recovery | Recovery detection and restore errors |
| 13300-13399 | Sync API | Cross-device synchronization errors |
| 13400-13499 | Editor State | Cursor, scroll, undo/redo errors |
| 13500-13599 | Validation | Input validation errors |
| 13900-13999 | Internal | Internal/unexpected errors |

---

## 1xxx - Validation Errors

Input validation and format errors used by both frontend and backend.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 1001 | `ERR_VALIDATION_REQUIRED` | 400 | Required field is missing | No |
| 1002 | `ERR_VALIDATION_FORMAT` | 400 | Invalid format (email, URL, etc.) | No |
| 1003 | `ERR_VALIDATION_RANGE` | 400 | Value outside allowed range | No |
| 1004 | `ERR_VALIDATION_LENGTH` | 400 | String length violation (min/max) | No |
| 1005 | `ERR_VALIDATION_TYPE` | 400 | Type mismatch | No |
| 1006 | `ERR_VALIDATION_UNIQUE` | 409 | Uniqueness constraint violated | No |
| 1007 | `ERR_VALIDATION_REFERENCE` | 400 | Invalid reference/foreign key | No |
| 1008 | `ERR_VALIDATION_PATTERN` | 400 | Regex pattern mismatch | No |
| 1009 | `ERR_VALIDATION_ENUM` | 400 | Value not in allowed set | No |
| 1010 | `ERR_VALIDATION_BATCH` | 400 | Multiple validation failures | No |
| 1011 | `ERR_VALIDATION_URL_SCHEME` | 400 | Invalid URL scheme (not http/https) | No |
| 1012 | `ERR_VALIDATION_PATH_TRAVERSAL` | 400 | Path traversal attempt detected | No |
| 1013 | `ERR_VALIDATION_PATTERN_SYNTAX` | 400 | Invalid regex syntax | No |
| 1014 | `ERR_VALIDATION_PATTERN_REDOS` | 400 | Catastrophic backtracking detected | No |
| 1015 | `ERR_VALIDATION_FILE_TYPE` | 400 | Unsupported file type | No |
| 1016 | `ERR_VALIDATION_FILE_SIZE` | 400 | File exceeds size limit | No |
| 1017 | `ERR_VALIDATION_JSON` | 400 | Invalid JSON format | No |
| 1018 | `ERR_VALIDATION_MARKDOWN` | 400 | Invalid Markdown structure | No |

---

## 2xxx - Authentication/Authorization Errors

Authentication and permission errors.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 2001 | `ERR_AUTH_FAILED` | 401 | Authentication failed | No |
| 2002 | `ERR_AUTH_EXPIRED` | 401 | Token/session expired | Yes* |
| 2003 | `ERR_AUTH_INVALID_TOKEN` | 401 | Invalid or malformed token | No |
| 2004 | `ERR_AUTH_REVOKED` | 401 | Token has been revoked | No |
| 2005 | `ERR_AUTH_REFRESH_FAILED` | 401 | Token refresh failed | No |
| 2006 | `ERR_AUTH_MFA_REQUIRED` | 401 | Multi-factor auth required | No |
| 2007 | `ERR_AUTH_MFA_INVALID` | 401 | Invalid MFA code | No |
| 2010 | `ERR_AUTHZ_DENIED` | 403 | Permission denied | No |
| 2011 | `ERR_AUTHZ_ROLE` | 403 | Insufficient role/privilege | No |
| 2012 | `ERR_AUTHZ_RESOURCE` | 403 | No access to resource | No |
| 2013 | `ERR_AUTHZ_PROJECT` | 403 | No access to project | No |
| 2014 | `ERR_AUTHZ_READONLY` | 403 | Resource is read-only | No |
| 2020 | `ERR_SESSION_INVALID` | 401 | Session not found/invalid | No |
| 2021 | `ERR_SESSION_EXPIRED` | 401 | Session has expired | Yes* |
| 2022 | `ERR_SESSION_DEVICE` | 401 | Session device mismatch | No |

> *Retryable after token refresh

---

## 3xxx - Database Errors

SQLite database operations and queries.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 3001 | `ERR_DB_CONNECTION` | 503 | Database connection failed | Yes |
| 3002 | `ERR_DB_LOCKED` | 503 | Database is locked (SQLite busy) | Yes |
| 3003 | `ERR_DB_QUERY` | 500 | Query execution failed | No |
| 3004 | `ERR_DB_TRANSACTION` | 500 | Transaction failed/rollback | Yes |
| 3005 | `ERR_DB_NOT_FOUND` | 404 | Record not found | No |
| 3006 | `ERR_DB_DUPLICATE` | 409 | Duplicate key/constraint | No |
| 3007 | `ERR_DB_CONSTRAINT` | 400 | Constraint violation | No |
| 3008 | `ERR_DB_MIGRATION` | 500 | Migration failed | No |
| 3009 | `ERR_DB_SCHEMA` | 500 | Schema mismatch | No |
| 3010 | `ERR_DB_CHECKPOINT_SAVE` | 500 | Cannot save checkpoint | Yes |
| 3011 | `ERR_DB_CHECKPOINT_LOAD` | 500 | Cannot load checkpoint | No |
| 3012 | `ERR_DB_VACUUM` | 500 | VACUUM operation failed | Yes |
| 3013 | `ERR_DB_INTEGRITY` | 500 | Database integrity check failed | No |

---

## 4xxx - External Services/Network Errors

Network operations and third-party service errors.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 4001 | `ERR_NET_TIMEOUT` | 504 | Request timed out | Yes |
| 4002 | `ERR_NET_DNS` | 502 | DNS resolution failed | Yes |
| 4003 | `ERR_NET_CONNECTION` | 502 | Connection refused/reset | Yes |
| 4004 | `ERR_NET_TLS` | 502 | TLS/SSL handshake failed | No |
| 4005 | `ERR_NET_HTTP` | 502 | HTTP error (4xx/5xx response) | Yes* |
| 4006 | `ERR_NET_REDIRECT_LOOP` | 502 | Too many redirects | No |
| 4007 | `ERR_NET_ROBOTS_TXT` | 403 | Blocked by robots.txt | No |
| 4008 | `ERR_NET_CONTENT_TYPE` | 502 | Unexpected content type | No |
| 4010 | `ERR_EXT_UNAVAILABLE` | 503 | External service unavailable | Yes |
| 4011 | `ERR_EXT_RESPONSE` | 502 | Invalid response from service | Yes |
| 4012 | `ERR_EXT_RATE_LIMITED` | 429 | Rate limited by external service | Yes |
| 4020 | `ERR_EMBEDDING_SERVICE` | 503 | Embedding service unavailable | Yes |
| 4021 | `ERR_EMBEDDING_TIMEOUT` | 504 | Embedding request timed out | Yes |
| 4022 | `ERR_EMBEDDING_DIMENSION` | 500 | Embedding dimension mismatch | No |

> *Depends on HTTP status code

---

## 5xxx - Business Logic/Processing Errors

Domain-specific rules, state transitions, and content processing.

### 5001-5049: Core Business Logic

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 5001 | `ERR_LOGIC_STATE` | 400 | Invalid state transition | No |
| 5002 | `ERR_LOGIC_LIMIT` | 400 | Limit exceeded | No |
| 5003 | `ERR_LOGIC_CONFLICT` | 409 | Business rule conflict | No |
| 5004 | `ERR_LOGIC_DEPENDENCY` | 400 | Dependency not met | No |
| 5005 | `ERR_LOGIC_PRECONDITION` | 400 | Precondition failed | No |
| 5006 | `ERR_LOGIC_POSTCONDITION` | 500 | Postcondition failed | No |

### 5010-5029: Specification Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 5010 | `ERR_SPEC_INVALID` | 400 | Invalid specification format | No |
| 5011 | `ERR_SPEC_CIRCULAR` | 400 | Circular reference detected | No |
| 5012 | `ERR_SPEC_MISSING` | 404 | Referenced spec not found | No |
| 5013 | `ERR_SPEC_VERSION` | 400 | Spec version mismatch | No |
| 5014 | `ERR_SPEC_SCHEMA` | 400 | Spec schema validation failed | No |
| 5015 | `ERR_SPEC_INCOMPLETE` | 400 | Required sections missing | No |

### 5030-5049: Content Processing

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 5030 | `ERR_PROC_PARSE_HTML` | 500 | HTML parsing failed | No |
| 5031 | `ERR_PROC_PARSE_MD` | 500 | Markdown parsing failed | No |
| 5032 | `ERR_PROC_PARSE_JSON` | 500 | JSON parsing failed | No |
| 5033 | `ERR_PROC_CHUNK` | 500 | Chunking algorithm failed | No |
| 5034 | `ERR_PROC_EXTRACT` | 500 | Content extraction failed | No |
| 5035 | `ERR_PROC_ENCODING` | 500 | Character encoding error | No |
| 5036 | `ERR_PROC_SANITIZE` | 500 | Content sanitization failed | No |

### 5050-5069: Project Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 5050 | `ERR_PROJECT_NOT_FOUND` | 404 | Project does not exist | No |
| 5051 | `ERR_PROJECT_ARCHIVED` | 400 | Project is archived | No |
| 5052 | `ERR_PROJECT_LOCKED` | 423 | Project is locked for editing | Yes |
| 5053 | `ERR_PROJECT_QUOTA` | 400 | Project quota exceeded | No |

### 5070-5089: History/Rollback Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 5070 | `ERR_HISTORY_NOT_FOUND` | 404 | History record not found | No |
| 5071 | `ERR_HISTORY_NO_CHANGES` | 400 | No changes recorded | No |
| 5072 | `ERR_HISTORY_ROLLBACK_CONFLICT` | 409 | File modified since snapshot | No |
| 5073 | `ERR_HISTORY_SNAPSHOT_FAILED` | 500 | Snapshot creation failed | Yes |
| 5074 | `ERR_HISTORY_RESTORE_FAILED` | 500 | Restore operation failed | Yes |

### 5100-5119: UI State Errors (Frontend)

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 5100 | `ERR_UI_STATE` | 400 | Invalid UI state transition | No |
| 5101 | `ERR_UI_NAVIGATION` | 400 | Navigation blocked (unsaved) | No |
| 5102 | `ERR_UI_SELECTION` | 400 | Invalid selection state | No |
| 5103 | `ERR_UI_CLIPBOARD` | 500 | Clipboard operation failed | No |
| 5104 | `ERR_UI_STORAGE` | 500 | Local storage error | No |

---

## 6xxx - File System/Git Errors

File operations, path validation, and Git integration.

### 6001-6049: File System Operations

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 6001 | `ERR_FS_NOT_FOUND` | 404 | File not found | No |
| 6002 | `ERR_FS_PERMISSION` | 403 | Permission denied | No |
| 6003 | `ERR_FS_EXISTS` | 409 | File already exists | No |
| 6004 | `ERR_FS_INVALID_PATH` | 400 | Invalid file path | No |
| 6005 | `ERR_FS_TRAVERSAL` | 400 | Path traversal attempt | No |
| 6006 | `ERR_FS_READ` | 500 | Read operation failed | Yes |
| 6007 | `ERR_FS_WRITE` | 500 | Write operation failed | Yes |
| 6008 | `ERR_FS_DELETE` | 500 | Delete operation failed | Yes |
| 6009 | `ERR_FS_HASH_MISMATCH` | 409 | Optimistic lock failure (hash) | No |
| 6010 | `ERR_FS_RENAME` | 500 | Rename operation failed | Yes |
| 6011 | `ERR_FS_COPY` | 500 | Copy operation failed | Yes |
| 6012 | `ERR_FS_MKDIR` | 500 | Directory creation failed | Yes |
| 6013 | `ERR_FS_RMDIR` | 500 | Directory removal failed | Yes |
| 6014 | `ERR_FS_SYMLINK` | 400 | Symlink outside allowed path | No |
| 6015 | `ERR_FS_RESERVED_PATH` | 400 | Path is system-reserved | No |
| 6016 | `ERR_FS_NAME_INVALID` | 400 | Invalid filename format | No |
| 6017 | `ERR_FS_SIZE_LIMIT` | 400 | File size exceeds limit | No |
| 6018 | `ERR_FS_DISK_FULL` | 503 | Insufficient disk space | No |

### 6050-6099: Git Integration

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 6050 | `ERR_GIT_INIT` | 500 | Git init failed | Yes |
| 6051 | `ERR_GIT_CLONE` | 500 | Git clone failed | Yes |
| 6052 | `ERR_GIT_COMMIT` | 500 | Git commit failed | Yes |
| 6053 | `ERR_GIT_PUSH` | 500 | Git push failed | Yes |
| 6054 | `ERR_GIT_PULL` | 500 | Git pull failed | Yes |
| 6055 | `ERR_GIT_MERGE_CONFLICT` | 409 | Merge conflict detected | No |
| 6056 | `ERR_GIT_REPO_NOT_FOUND` | 404 | Repository not found | No |
| 6057 | `ERR_GIT_AUTH` | 401 | Git authentication failed | No |
| 6058 | `ERR_GIT_REMOTE` | 500 | Remote operation failed | Yes |
| 6059 | `ERR_GIT_BRANCH` | 500 | Branch operation failed | Yes |
| 6060 | `ERR_GIT_STASH` | 500 | Stash operation failed | Yes |
| 6061 | `ERR_GIT_CHECKOUT` | 500 | Checkout failed | Yes |
| 6062 | `ERR_GIT_RESET` | 500 | Reset operation failed | Yes |

---

## 7xxx - LLM/Configuration/CLI Errors

LLM server management, model operations, configuration, and brun CLI.

### 7001-7049: LLM Server Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 7001 | `ERR_LLM_SERVER_OFFLINE` | 503 | LLM server not running | Yes |
| 7002 | `ERR_LLM_MODEL_NOT_FOUND` | 404 | Model not available | No |
| 7003 | `ERR_LLM_MODEL_LOAD_FAILED` | 500 | Model failed to load | Yes |
| 7004 | `ERR_LLM_TIMEOUT` | 504 | LLM request timed out | Yes |
| 7005 | `ERR_LLM_CONNECTION` | 503 | LLM server connection failed | Yes |
| 7006 | `ERR_LLM_OOM` | 503 | Out of memory (model too large) | No |
| 7007 | `ERR_LLM_CANCELED` | 499 | LLM request canceled | No |
| 7008 | `ERR_LLM_RESPONSE_INVALID` | 502 | Invalid LLM response | Yes |
| 7009 | `ERR_LLM_GENERATION_FAILED` | 500 | Text generation failed | Yes |
| 7010 | `ERR_LLM_PORT_UNAVAILABLE` | 503 | No available port in range | Yes |
| 7011 | `ERR_LLM_BACKEND_MISMATCH` | 400 | Model incompatible with backend | No |
| 7012 | `ERR_LLM_CONTEXT_OVERFLOW` | 400 | Context exceeds model limit | No |
| 7013 | `ERR_LLM_STREAM_ERROR` | 500 | Streaming response error | Yes |
| 7020 | `ERR_LLM_EVICTION_FAILED` | 500 | Model eviction failed | Yes |
| 7021 | `ERR_LLM_SLOT_EXHAUSTED` | 503 | All model slots in use | Yes |

### 7050-7099: Configuration Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 7050 | `ERR_CONFIG_MISSING` | 500 | Required config missing | No |
| 7051 | `ERR_CONFIG_INVALID` | 500 | Invalid config value | No |
| 7052 | `ERR_CONFIG_PARSE` | 500 | Config file parse error | No |
| 7053 | `ERR_CONFIG_SCHEMA` | 500 | Config schema validation failed | No |
| 7054 | `ERR_CONFIG_RANGE` | 500 | Config value out of range | No |
| 7055 | `ERR_CONFIG_FORMAT` | 500 | Config format invalid (e.g., CIDR) | No |
| 7056 | `ERR_CONFIG_DEPENDENCY` | 500 | Config cross-field dependency | No |
| 7057 | `ERR_CONFIG_MISMATCH` | 500 | Config value doesn't match expected | No |
| 7058 | `ERR_CONFIG_FILE_READ` | 500 | Cannot read config file | Yes |
| 7059 | `ERR_CONFIG_WATCH` | 500 | Config file watch failed | Yes |

### 7100-7199: brun CLI & Configuration

Build Runner CLI command-line parsing and config.json management.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 7101 | `ERR_BRUN_INVALID_COMMAND` | 400 | Unknown or invalid CLI command | No |
| 7102 | `ERR_BRUN_INVALID_FLAG` | 400 | Unknown or invalid CLI flag | No |
| 7103 | `ERR_BRUN_MISSING_ARGUMENT` | 400 | Required argument not provided | No |
| 7104 | `ERR_BRUN_CONFLICTING_FLAGS` | 400 | Mutually exclusive flags specified | No |
| 7105 | `ERR_BRUN_BINARY_NOT_FOUND` | 500 | brun executable not in PATH | No |
| 7106 | `ERR_BRUN_VERSION_MISMATCH` | 400 | Config version incompatible with binary | No |
| 7110 | `ERR_BRUN_CONFIG_NOT_FOUND` | 404 | config.json not found at path | No |
| 7111 | `ERR_BRUN_CONFIG_PARSE_ERROR` | 400 | Invalid JSON in config file | No |
| 7112 | `ERR_BRUN_CONFIG_SCHEMA_INVALID` | 400 | Config does not match JSON schema | No |
| 7113 | `ERR_BRUN_CONFIG_PROFILE_NOT_FOUND` | 404 | Named profile not defined in config | No |
| 7114 | `ERR_BRUN_CONFIG_APP_NOT_FOUND` | 404 | Named application not defined in config | No |
| 7115 | `ERR_BRUN_CONFIG_RUNTIME_INVALID` | 400 | Invalid runtime type specified | No |
| 7116 | `ERR_BRUN_CONFIG_PATH_INVALID` | 400 | Invalid path in configuration | No |
| 7117 | `ERR_BRUN_CONFIG_WRITE_FAILED` | 500 | Failed to write config file | No |
| 7118 | `ERR_BRUN_CONFIG_PERMISSION` | 403 | Permission denied reading/writing config | No |

### 7200-7299: brun Runtime Execution

PowerShell, Node.js, and Go runtime execution errors.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 7201 | `ERR_BRUN_RUNTIME_NOT_FOUND` | 500 | Runtime executable not found (go, node, pwsh) | No |
| 7202 | `ERR_BRUN_RUNTIME_VERSION` | 500 | Runtime version not supported | No |
| 7203 | `ERR_BRUN_RUNTIME_CRASHED` | 500 | Runtime process crashed unexpectedly | Yes |
| 7204 | `ERR_BRUN_RUNTIME_TIMEOUT` | 408 | Runtime execution exceeded timeout | Yes |
| 7205 | `ERR_BRUN_RUNTIME_PERMISSION` | 403 | Permission denied executing runtime | No |
| 7206 | `ERR_BRUN_RUNTIME_SIGNALED` | 500 | Runtime killed by signal (SIGINT/SIGTERM) | No |
| 7210 | `ERR_BRUN_GO_BUILD_FAILED` | 422 | Go compilation failed | No |
| 7211 | `ERR_BRUN_GO_MOD_TIDY_FAILED` | 422 | go mod tidy failed | No |
| 7212 | `ERR_BRUN_GO_UNDEFINED_SYMBOL` | 422 | Undefined variable/function in Go code | No |
| 7213 | `ERR_BRUN_GO_IMPORT_ERROR` | 422 | Go import/package not found | No |
| 7220 | `ERR_BRUN_NODE_BUILD_FAILED` | 422 | Node.js/npm build failed | No |
| 7221 | `ERR_BRUN_NODE_PACKAGE_MISSING` | 422 | npm package not installed | No |
| 7222 | `ERR_BRUN_NODE_SCRIPT_NOT_FOUND` | 404 | npm script not defined in package.json | No |
| 7223 | `ERR_BRUN_TS_COMPILE_ERROR` | 422 | TypeScript compilation error | No |
| 7230 | `ERR_BRUN_PS_SCRIPT_ERROR` | 422 | PowerShell script execution error | No |
| 7231 | `ERR_BRUN_PS_SYNTAX_ERROR` | 422 | PowerShell syntax error | No |
| 7232 | `ERR_BRUN_PS_CMDLET_NOT_FOUND` | 422 | PowerShell cmdlet not found | No |

### 7300-7399: brun Port Management

Port checking, allocation, and firewall management.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 7301 | `ERR_BRUN_PORT_UNAVAILABLE` | 409 | Requested port in use, no fallback available | Yes |
| 7302 | `ERR_BRUN_PORT_PERMISSION` | 403 | Permission denied binding to port (<1024) | No |
| 7303 | `ERR_BRUN_PORT_INVALID` | 400 | Invalid port number (0, >65535) | No |
| 7304 | `ERR_BRUN_FIREWALL_FAILED` | 500 | Firewall rule creation/deletion failed | No |
| 7305 | `ERR_BRUN_FIREWALL_PERMISSION` | 403 | Insufficient privileges for firewall ops | No |
| 7306 | `ERR_BRUN_FIREWALL_NOT_FOUND` | 404 | Firewall rule not found for deletion | No |
| 7307 | `ERR_BRUN_NETWORK_UNREACHABLE` | 503 | Network interface not available | Yes |

### 7400-7499: brun Build Process

Compilation, asset operations, and working directory management.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 7401 | `ERR_BRUN_BUILD_FAILED` | 422 | General build failure | No |
| 7402 | `ERR_BRUN_SOURCE_NOT_FOUND` | 404 | Source path does not exist | No |
| 7403 | `ERR_BRUN_OUTPUT_DIR_FAILED` | 500 | Cannot create output directory | No |
| 7404 | `ERR_BRUN_ASSET_COPY_FAILED` | 500 | Asset copy operation failed | No |
| 7405 | `ERR_BRUN_ASSET_CLEAR_FAILED` | 500 | Asset clear operation failed | No |
| 7406 | `ERR_BRUN_ASSET_SOURCE_MISSING` | 404 | Asset source path not found | No |
| 7407 | `ERR_BRUN_WORKDIR_NOT_FOUND` | 404 | Working directory does not exist | No |
| 7408 | `ERR_BRUN_WORKDIR_PERMISSION` | 403 | Working directory not accessible | No |
| 7409 | `ERR_BRUN_EXTERNAL_DIR_BLOCKED` | 403 | External directory access denied (allowExternalDirs=false) | No |
| 7410 | `ERR_BRUN_PATH_TRAVERSAL` | 403 | Path traversal attempt blocked | No |

### 7500-7599: brun Health Check

Application health monitoring and verification.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 7501 | `ERR_BRUN_HEALTH_TIMEOUT` | 408 | Health check did not pass in time | Yes |
| 7502 | `ERR_BRUN_HEALTH_FAILED` | 503 | Health check endpoint returned error | Yes |
| 7503 | `ERR_BRUN_HEALTH_UNREACHABLE` | 503 | Health check endpoint unreachable | Yes |
| 7504 | `ERR_BRUN_HEALTH_STATUS_MISMATCH` | 422 | Unexpected HTTP status from health endpoint | No |
| 7505 | `ERR_BRUN_HEALTH_BODY_MISMATCH` | 422 | Health response body did not match expected | No |

---

## 8xxx - RAG/Knowledge/Security Errors

RAG system, knowledge management, and security violations.

### 8001-8049: RAG System Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 8001 | `ERR_RAG_INDEX_FAILED` | 500 | Failed to index artifact | Yes |
| 8002 | `ERR_RAG_EMBED_FAILED` | 500 | Embedding generation failed | Yes |
| 8003 | `ERR_RAG_QUERY_FAILED` | 500 | Retrieval query failed | Yes |
| 8004 | `ERR_RAG_NO_CONTEXT` | 404 | No relevant context found | No |
| 8005 | `ERR_RAG_CACHE_FULL` | 503 | RAG cache capacity exceeded | Yes |
| 8006 | `ERR_RAG_CHUNK_FAILED` | 500 | Chunking operation failed | Yes |
| 8007 | `ERR_RAG_RERANK_FAILED` | 500 | Re-ranking operation failed | Yes |
| 8008 | `ERR_RAG_VECTOR_SEARCH` | 500 | Vector search failed | Yes |
| 8009 | `ERR_RAG_FTS_SEARCH` | 500 | Full-text search failed | Yes |
| 8010 | `ERR_RAG_HYBRID_MERGE` | 500 | Hybrid result merge failed | Yes |

### 8020-8039: Idea/Artifact Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 8020 | `ERR_IDEA_NOT_FOUND` | 404 | Idea not found | No |
| 8021 | `ERR_IDEA_INVALID_STATUS` | 400 | Cannot perform action with current status | No |
| 8022 | `ERR_IDEA_ALREADY_PROMOTED` | 409 | Idea already promoted | No |
| 8023 | `ERR_IDEA_PROMOTION_FAILED` | 500 | Idea promotion failed | Yes |
| 8024 | `ERR_ARTIFACT_INVALID` | 400 | Artifact format invalid | No |
| 8025 | `ERR_ARTIFACT_FRONTMATTER` | 400 | Missing required frontmatter | No |

### 8050-8079: Knowledge System Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 8050 | `ERR_KNOWLEDGE_SOURCE_INVALID` | 400 | Invalid knowledge source | No |
| 8051 | `ERR_KNOWLEDGE_SYNC_FAILED` | 500 | Knowledge sync failed | Yes |
| 8052 | `ERR_KNOWLEDGE_CRAWL_FAILED` | 500 | Crawler operation failed | Yes |
| 8053 | `ERR_KNOWLEDGE_PARSE_FAILED` | 500 | Content parsing failed | Yes |
| 8054 | `ERR_KNOWLEDGE_DUPLICATE` | 409 | Duplicate knowledge source | No |

### 8080-8099: Security Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 8080 | `ERR_SEC_SSRF` | 400 | SSRF attempt - private network | No |
| 8081 | `ERR_SEC_BLOCKED_IP` | 400 | IP address is blocked | No |
| 8082 | `ERR_SEC_METADATA` | 400 | Cloud metadata endpoint blocked | No |
| 8083 | `ERR_SEC_LOCALHOST` | 400 | Localhost access blocked | No |
| 8084 | `ERR_SEC_XSS` | 400 | XSS attempt detected | No |
| 8085 | `ERR_SEC_INJECTION` | 400 | Injection attempt detected | No |
| 8086 | `ERR_SEC_RATE_LIMIT` | 429 | Rate limit exceeded | Yes |
| 8087 | `ERR_SEC_BRUTE_FORCE` | 429 | Brute force lockout | No |
| 8088 | `ERR_SEC_CSRF` | 400 | CSRF token invalid | No |

---

## 9xxx - System/Consistency Errors

System-level errors and consistency checker results.

### 9001-9049: System Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 9001 | `ERR_SYS_INTERNAL` | 500 | Internal server error | No |
| 9002 | `ERR_SYS_MEMORY` | 503 | Memory exhaustion | No |
| 9003 | `ERR_SYS_DISK` | 503 | Disk space exhaustion | No |
| 9004 | `ERR_SYS_TIMEOUT` | 503 | Operation timeout | Yes |
| 9005 | `ERR_SYS_PANIC` | 500 | Recovered panic | No |
| 9006 | `ERR_SYS_SIGNAL` | 500 | Unexpected signal received | No |
| 9007 | `ERR_SYS_RESOURCE` | 503 | Resource exhaustion (generic) | Yes |
| 9008 | `ERR_SYS_SHUTDOWN` | 503 | Server shutting down | No |
| 9009 | `ERR_SYS_MAINTENANCE` | 503 | System in maintenance mode | No |

### 9050-9099: Consistency Checker Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 9050 | `ERR_CONSISTENCY_SCAN_FAILED` | 500 | Consistency scan failed | Yes |
| 9051 | `ERR_CONSISTENCY_BROKEN_LINK` | 400 | Broken internal link detected | No |
| 9052 | `ERR_CONSISTENCY_ORPHAN_FILE` | 400 | Orphan file detected | No |
| 9053 | `ERR_CONSISTENCY_DUPLICATE` | 400 | Duplicate definition found | No |
| 9054 | `ERR_CONSISTENCY_NAMING` | 400 | Naming convention violation | No |
| 9055 | `ERR_CONSISTENCY_SECTION` | 400 | Missing required section | No |
| 9056 | `ERR_CONSISTENCY_SCHEMA` | 400 | Schema-API mismatch | No |
| 9057 | `ERR_CONSISTENCY_TERM` | 400 | Terminology inconsistency | No |
| 9058 | `ERR_CONSISTENCY_AUTOFIX_FAILED` | 500 | Auto-fix operation failed | Yes |
| 9059 | `ERR_CONSISTENCY_REPORT_FAILED` | 500 | Report generation failed | Yes |

---

## 10xxx - Context Window Errors

Token budgeting and context assembly errors.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 10001 | `ERR_CONTEXT_OVERFLOW` | 400 | Context exceeds token budget | No |
| 10002 | `ERR_CONTEXT_ASSEMBLY_FAILED` | 500 | Context assembly failed | Yes |
| 10003 | `ERR_CONTEXT_TOKENIZE_FAILED` | 500 | Tokenization failed | Yes |
| 10004 | `ERR_CONTEXT_TRUNCATE_FAILED` | 500 | Truncation strategy failed | Yes |
| 10005 | `ERR_CONTEXT_PRIORITY_INVALID` | 400 | Invalid priority configuration | No |
| 10006 | `ERR_CONTEXT_COMPRESSION_FAILED` | 500 | Memory compression failed | Yes |
| 10007 | `ERR_CONTEXT_CACHE_MISS` | 404 | Cached context not found | No |

---

## 11xxx - Instruction System Errors

Instruction lifecycle and task execution errors.

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 11001 | `ERR_INSTRUCTION_NOT_FOUND` | 404 | Instruction ID does not exist | No |
| 11002 | `ERR_INSTRUCTION_INVALID_SCOPE` | 400 | Invalid scope value | No |
| 11003 | `ERR_INSTRUCTION_FILE_REQUIRED` | 400 | File path required for file scope | No |
| 11004 | `ERR_INSTRUCTION_ALREADY_APPROVED` | 409 | Cannot modify approved instruction | No |
| 11005 | `ERR_INSTRUCTION_NOT_PLANNED` | 400 | Cannot approve before planning | No |
| 11006 | `ERR_INSTRUCTION_CANCELLED` | 400 | Instruction was cancelled | No |
| 11007 | `ERR_INSTRUCTION_EXECUTING` | 409 | Instruction currently executing | No |
| 11008 | `ERR_INSTRUCTION_COMPLETED` | 409 | Instruction already completed | No |
| 11010 | `ERR_TASK_NOT_FOUND` | 404 | Task ID does not exist | No |
| 11011 | `ERR_TASK_ALREADY_COMPLETED` | 409 | Cannot modify completed task | No |
| 11012 | `ERR_TASK_BLOCKED` | 400 | Task blocked by dependencies | No |
| 11013 | `ERR_TASK_EXECUTION_FAILED` | 500 | Task execution failed | Yes |
| 11014 | `ERR_TASK_CYCLE_DETECTED` | 400 | Circular task dependency | No |
| 11020 | `ERR_TRANSCRIPTION_FAILED` | 500 | Voice transcription failed | Yes |
| 11021 | `ERR_PROOFREADING_FAILED` | 500 | Proofreading step failed | Yes |
| 11022 | `ERR_PLANNING_FAILED` | 500 | Planning step failed | Yes |

---

## 12xxx - Code Generation System Errors

AI-powered code generation, Git integration, and credit management.

### 12000-12099: General Code Generation

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 12000 | `ERR_CODEGEN_UNKNOWN` | 500 | Unknown code generation error | No |
| 12001 | `ERR_CODEGEN_NOT_ENABLED` | 403 | Code generation not enabled for project | No |
| 12002 | `ERR_CODEGEN_RUN_NOT_FOUND` | 404 | Generation run not found | No |
| 12003 | `ERR_CODEGEN_RUN_ALREADY_COMPLETED` | 400 | Generation run already completed | No |
| 12004 | `ERR_CODEGEN_RUN_CANCELLED` | 400 | Generation run was cancelled | No |
| 12005 | `ERR_CODEGEN_PROJECT_LOCKED` | 423 | Project has another generation in progress | Yes |
| 12006 | `ERR_CODEGEN_TIMEOUT` | 504 | Generation timed out | Yes |
| 12007 | `ERR_CODEGEN_CANCELLED_BY_USER` | 400 | Generation cancelled by user | No |

### 12100-12199: Guideline Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 12100 | `ERR_GUIDELINE_NOT_FOUND` | 404 | Guideline not found | No |
| 12101 | `ERR_GUIDELINE_INVALID_LEVEL` | 400 | Invalid guideline level | No |
| 12102 | `ERR_GUIDELINE_PARSE_FAILED` | 500 | Failed to parse guideline sections | No |
| 12103 | `ERR_GUIDELINE_RESOLUTION_FAILED` | 500 | Failed to resolve guidelines | Yes |
| 12104 | `ERR_GUIDELINE_CIRCULAR_REF` | 400 | Circular reference in guidelines | No |
| 12105 | `ERR_GUIDELINE_LANGUAGE_UNSUPPORTED` | 400 | Language not supported | No |
| 12106 | `ERR_GUIDELINE_DUPLICATE_NAME` | 409 | Guideline name already exists | No |
| 12107 | `ERR_GUIDELINE_CONTENT_EMPTY` | 400 | Guideline content is empty | No |
| 12108 | `ERR_GUIDELINE_VERSION_CONFLICT` | 409 | Guideline version conflict | Yes |

### 12200-12299: Planning Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 12200 | `ERR_PLAN_GENERATION_FAILED` | 500 | Failed to generate plan | Yes |
| 12201 | `ERR_PLAN_NO_SPECS` | 400 | No specifications provided | No |
| 12202 | `ERR_PLAN_SPEC_NOT_FOUND` | 404 | Referenced specification not found | No |
| 12203 | `ERR_PLAN_SPEC_PARSE_FAILED` | 400 | Failed to parse specification | No |
| 12204 | `ERR_PLAN_NO_FILES` | 400 | No files to generate from specs | No |
| 12205 | `ERR_PLAN_CIRCULAR_DEPENDENCY` | 400 | Circular dependency in file plan | No |
| 12206 | `ERR_PLAN_DEPENDENCY_RESOLUTION_FAILED` | 500 | Failed to resolve dependencies | Yes |
| 12207 | `ERR_PLAN_TOO_LARGE` | 400 | Plan exceeds maximum file count | No |
| 12208 | `ERR_PLAN_INVALID_LANGUAGE` | 400 | Invalid target language specified | No |

### 12300-12399: Execution Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 12300 | `ERR_EXEC_MODEL_SELECT_FAILED` | 500 | Failed to select coding model | Yes |
| 12301 | `ERR_EXEC_GENERATION_FAILED` | 500 | Code generation failed | Yes |
| 12302 | `ERR_EXEC_WRITE_FAILED` | 500 | Failed to write generated file | Yes |
| 12303 | `ERR_EXEC_BATCH_TIMEOUT` | 504 | Batch execution timeout | Yes |
| 12304 | `ERR_EXEC_CIRCULAR_DEPENDENCY` | 400 | Circular dependency detected | No |
| 12305 | `ERR_EXEC_NO_WORKERS` | 503 | No workers available | Yes |
| 12306 | `ERR_EXEC_CONTEXT_TOO_LARGE` | 400 | Context exceeds model limit | No |
| 12307 | `ERR_EXEC_MODEL_UNAVAILABLE` | 503 | Coding model unavailable | Yes |
| 12308 | `ERR_EXEC_MODEL_TIMEOUT` | 504 | Model response timeout | Yes |
| 12309 | `ERR_EXEC_INVALID_RESPONSE` | 500 | Invalid model response format | Yes |
| 12310 | `ERR_EXEC_CODE_EXTRACTION_FAILED` | 500 | Failed to extract code from response | Yes |
| 12311 | `ERR_EXEC_PATH_INVALID` | 400 | Invalid file path in plan | No |
| 12312 | `ERR_EXEC_PATH_TRAVERSAL` | 403 | Path traversal attempt detected | No |

### 12400-12499: Git Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 12400 | `ERR_CODEGEN_GIT_INIT_FAILED` | 500 | Failed to initialize repository | Yes |
| 12401 | `ERR_CODEGEN_GIT_COMMIT_FAILED` | 500 | Failed to commit changes | Yes |
| 12402 | `ERR_CODEGEN_GIT_PUSH_FAILED` | 500 | Failed to push to remote | Yes |
| 12403 | `ERR_CODEGEN_GIT_PULL_FAILED` | 500 | Failed to pull from remote | Yes |
| 12404 | `ERR_CODEGEN_GIT_CONFLICT` | 409 | Merge conflict detected | No |
| 12405 | `ERR_CODEGEN_GIT_NO_REMOTE` | 400 | No remote configured | No |
| 12406 | `ERR_CODEGEN_OAUTH_NOT_CONNECTED` | 401 | OAuth not connected for provider | No |
| 12407 | `ERR_CODEGEN_OAUTH_TOKEN_EXPIRED` | 401 | OAuth token expired | Yes |
| 12408 | `ERR_CODEGEN_OAUTH_REFRESH_FAILED` | 500 | Failed to refresh OAuth token | Yes |
| 12409 | `ERR_CODEGEN_GIT_REPO_CREATE_FAILED` | 500 | Failed to create remote repository | Yes |
| 12410 | `ERR_CODEGEN_GIT_REPO_NOT_FOUND` | 404 | Remote repository not found | No |
| 12411 | `ERR_CODEGEN_GIT_PERMISSION_DENIED` | 403 | Git permission denied | No |
| 12412 | `ERR_CODEGEN_GIT_STASH_FAILED` | 500 | Failed to stash changes | Yes |
| 12413 | `ERR_CODEGEN_GIT_STASH_POP_FAILED` | 500 | Failed to apply stashed changes | Yes |
| 12414 | `ERR_CODEGEN_OAUTH_STATE_MISMATCH` | 400 | OAuth state mismatch | No |
| 12415 | `ERR_CODEGEN_OAUTH_PROVIDER_ERROR` | 502 | OAuth provider error | Yes |

### 12500-12599: Build Verification Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 12500 | `ERR_BUILD_VERIFICATION_FAILED` | 500 | Build verification failed | No |
| 12501 | `ERR_BUILD_BRUN_NOT_FOUND` | 500 | brun CLI not found | No |
| 12502 | `ERR_BUILD_BRUN_TIMEOUT` | 504 | brun execution timeout | Yes |
| 12503 | `ERR_BUILD_PARSE_FAILED` | 500 | Failed to parse build output | No |
| 12504 | `ERR_BUILD_FIX_FAILED` | 500 | AI fix loop exhausted | No |
| 12505 | `ERR_BUILD_LANGUAGE_UNSUPPORTED` | 400 | Language not supported by brun | No |
| 12506 | `ERR_BUILD_WORKSPACE_INVALID` | 400 | Invalid build workspace | No |
| 12507 | `ERR_BUILD_DEPENDENCIES_MISSING` | 400 | Build dependencies missing | No |
| 12508 | `ERR_BUILD_CONFIG_INVALID` | 400 | Invalid build configuration | No |

### 12600-12699: Credit System Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 12600 | `ERR_CREDITS_INSUFFICIENT` | 402 | Insufficient credits for operation | No |
| 12601 | `ERR_CREDITS_ESTIMATION_FAILED` | 500 | Failed to estimate credit cost | Yes |
| 12602 | `ERR_CREDITS_TRANSACTION_FAILED` | 500 | Failed to record transaction | Yes |
| 12603 | `ERR_CREDITS_PLAN_NOT_FOUND` | 404 | Credit plan not found | No |
| 12604 | `ERR_CREDITS_PURCHASE_FAILED` | 500 | Credit purchase failed | Yes |
| 12605 | `ERR_CREDITS_NEGATIVE_BALANCE` | 400 | Balance would be negative | No |
| 12606 | `ERR_CREDITS_USER_NOT_FOUND` | 404 | User credits not found | No |
| 12607 | `ERR_CREDITS_ALREADY_REFUNDED` | 400 | Transaction already refunded | No |

### 12700-12799: Repository Structure Errors

| Code | Constant | HTTP | Description | Retryable |
|------|----------|------|-------------|-----------|
| 12700 | `ERR_REPO_CREATE_DIR_FAILED` | 500 | Failed to create directory | Yes |
| 12701 | `ERR_REPO_TEMPLATE_FAILED` | 500 | Failed to generate template file | Yes |
| 12702 | `ERR_REPO_INVALID_STRUCTURE` | 400 | Invalid custom structure | No |
| 12703 | `ERR_REPO_COPY_SPEC_FAILED` | 500 | Failed to copy specification | Yes |
| 12704 | `ERR_REPO_PATH_EXISTS` | 409 | Repository path already exists | No |
| 12705 | `ERR_REPO_ROOT_NOT_CONFIGURED` | 500 | Repository root not configured | No |
| 12706 | `ERR_REPO_PERMISSION_DENIED` | 403 | Repository permission denied | No |

---

## HTTP Status Code Mapping

Standard mapping from error code ranges to HTTP status codes:

| Range | Primary HTTP | Secondary HTTP |
|-------|--------------|----------------|
| 1xxx | 400 Bad Request | 409 Conflict |
| 2xxx | 401 Unauthorized | 403 Forbidden |
| 3xxx | 500 Internal | 404 Not Found, 409 Conflict |
| 4xxx | 502/503/504 | 429 Too Many Requests |
| 5xxx | 400 Bad Request | 404 Not Found, 409 Conflict |
| 6xxx | 500 Internal | 400 Bad Request, 403/404/409 |
| 7xxx | 500/503/504 | 404 Not Found |
| 8xxx | 500 Internal | 400 Bad Request, 429 |
| 9xxx | 500/503 | - |
| 10xxx | 400/500 | - |
| 11xxx | 400/500 | 404 Not Found, 409 Conflict |
| **12xxx** | **500/503/504** | **400 Bad Request, 402 Payment Required, 404/409** |
| 9xxx | 500/503 | - |
| 10xxx | 400/500 | - |
| 11xxx | 400/500 | 404 Not Found, 409 Conflict |

---

## Implementation

### Go Constants

```go
// internal/errors/registry.go

package errors

// Validation (1xxx)
const (
    ERR_VALIDATION_REQUIRED    = 1001
    ERR_VALIDATION_FORMAT      = 1002
    // ... all 1xxx codes
)

// Authentication (2xxx)
const (
    ERR_AUTH_FAILED        = 2001
    ERR_AUTH_EXPIRED       = 2002
    // ... all 2xxx codes
)

// Database (3xxx)
const (
    ERR_DB_CONNECTION = 3001
    ERR_DB_LOCKED     = 3002
    // ... all 3xxx codes
)

// ... continue for all ranges
```

### TypeScript Constants

```typescript
// src/lib/errors/error-codes.ts

export const ERROR_CODES = {
  // Validation (1xxx)
  VALIDATION_REQUIRED: 1001,
  VALIDATION_FORMAT: 1002,
  // ... all 1xxx codes
  
  // Authentication (2xxx)
  AUTH_FAILED: 2001,
  AUTH_EXPIRED: 2002,
  // ... all 2xxx codes
  
  // ... continue for all ranges
} as const;

export type ErrorCode = typeof ERROR_CODES[keyof typeof ERROR_CODES];
```

---

## Maintenance Guidelines

### Adding New Error Codes

1. Choose appropriate range based on category
2. Use next available number in range
3. Update this registry document
4. Update Go constants in `internal/errors/registry.go`
5. Update TypeScript constants in `src/lib/errors/error-codes.ts`
6. Add to relevant spec documentation

### Deprecating Error Codes

1. Mark as deprecated in this registry (add `⚠️ DEPRECATED` suffix)
2. Keep code allocated to prevent reuse
3. Update implementation to use replacement code
4. Document migration path

### Code Range Expansion

If a range is exhausted, request allocation of next available range from architecture team. Never reuse codes from other ranges.

---

## Related Specs

- [Error Management Overview](./00-overview.md)
- [Backend Error Codes](./backend/01-error-codes.md)
- [Frontend Error Codes](./frontend/01-error-codes.md)
- [API Contracts](../05-features/15-api-client/02-api-contracts.md)
