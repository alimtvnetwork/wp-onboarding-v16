# Error Codes

**Version:** 1.1.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

The Code Generation System uses the **12xxx** error code range. This document provides the complete registry with descriptions, HTTP mappings, and retryability flags.

**Cross-References:**
- [Error Management](../../06-error-management/00-overview.md)
- [Error Code Registry](../../06-error-management/error-code-registry.md)
- [Architecture](./01-architecture.md)

---

## Error Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| 12000-12099 | General | Core code generation errors |
| 12100-12199 | Guidelines | Guideline resolution errors |
| 12200-12299 | Planning | Plan generation errors |
| 12300-12399 | Execution | Parallel execution errors |
| 12400-12499 | Git | Git operation errors |
| 12500-12599 | Build | Build verification errors |
| 12600-12699 | Credits | Credit system errors |
| 12700-12799 | Repository | Repository structure errors |

---

## Complete Error Registry

### General Errors (12000-12099)

| Code | Constant | HTTP | Retryable | Description |
|------|----------|------|-----------|-------------|
| 12000 | ERR_CODEGEN_UNKNOWN | 500 | No | Unknown code generation error |
| 12001 | ERR_CODEGEN_NOT_ENABLED | 403 | No | Code generation not enabled for project |
| 12002 | ERR_CODEGEN_RUN_NOT_FOUND | 404 | No | Generation run not found |
| 12003 | ERR_CODEGEN_RUN_ALREADY_COMPLETED | 400 | No | Generation run already completed |
| 12004 | ERR_CODEGEN_RUN_CANCELLED | 400 | No | Generation run was cancelled |
| 12005 | ERR_CODEGEN_PROJECT_LOCKED | 423 | Yes | Project has another generation in progress |
| 12006 | ERR_CODEGEN_TIMEOUT | 504 | Yes | Generation timed out |
| 12007 | ERR_CODEGEN_CANCELLED_BY_USER | 400 | No | Generation cancelled by user |

### Guideline Errors (12100-12199)

| Code | Constant | HTTP | Retryable | Description |
|------|----------|------|-----------|-------------|
| 12100 | ERR_GUIDELINE_NOT_FOUND | 404 | No | Guideline not found |
| 12101 | ERR_GUIDELINE_INVALID_LEVEL | 400 | No | Invalid guideline level |
| 12102 | ERR_GUIDELINE_PARSE_FAILED | 500 | No | Failed to parse guideline sections |
| 12103 | ERR_GUIDELINE_RESOLUTION_FAILED | 500 | Yes | Failed to resolve guidelines |
| 12104 | ERR_GUIDELINE_CIRCULAR_REF | 400 | No | Circular reference in guidelines |
| 12105 | ERR_GUIDELINE_LANGUAGE_UNSUPPORTED | 400 | No | Language not supported |
| 12106 | ERR_GUIDELINE_DUPLICATE_NAME | 409 | No | Guideline name already exists |
| 12107 | ERR_GUIDELINE_CONTENT_EMPTY | 400 | No | Guideline content is empty |
| 12108 | ERR_GUIDELINE_VERSION_CONFLICT | 409 | Yes | Guideline version conflict |

### Planning Errors (12200-12299)

| Code | Constant | HTTP | Retryable | Description |
|------|----------|------|-----------|-------------|
| 12200 | ERR_PLAN_GENERATION_FAILED | 500 | Yes | Failed to generate plan |
| 12201 | ERR_PLAN_NO_SPECS | 400 | No | No specifications provided |
| 12202 | ERR_PLAN_SPEC_NOT_FOUND | 404 | No | Referenced specification not found |
| 12203 | ERR_PLAN_SPEC_PARSE_FAILED | 400 | No | Failed to parse specification |
| 12204 | ERR_PLAN_NO_FILES | 400 | No | No files to generate from specs |
| 12205 | ERR_PLAN_CIRCULAR_DEPENDENCY | 400 | No | Circular dependency in file plan |
| 12206 | ERR_PLAN_DEPENDENCY_RESOLUTION_FAILED | 500 | Yes | Failed to resolve dependencies |
| 12207 | ERR_PLAN_TOO_LARGE | 400 | No | Plan exceeds maximum file count |
| 12208 | ERR_PLAN_INVALID_LANGUAGE | 400 | No | Invalid target language specified |

### Execution Errors (12300-12399)

| Code | Constant | HTTP | Retryable | Description |
|------|----------|------|-----------|-------------|
| 12300 | ERR_EXEC_MODEL_SELECT_FAILED | 500 | Yes | Failed to select coding model |
| 12301 | ERR_EXEC_GENERATION_FAILED | 500 | Yes | Code generation failed |
| 12302 | ERR_EXEC_WRITE_FAILED | 500 | Yes | Failed to write generated file |
| 12303 | ERR_EXEC_BATCH_TIMEOUT | 504 | Yes | Batch execution timeout |
| 12304 | ERR_EXEC_CIRCULAR_DEPENDENCY | 400 | No | Circular dependency detected |
| 12305 | ERR_EXEC_NO_WORKERS | 503 | Yes | No workers available |
| 12306 | ERR_EXEC_CONTEXT_TOO_LARGE | 400 | No | Context exceeds model limit |
| 12307 | ERR_EXEC_MODEL_UNAVAILABLE | 503 | Yes | Coding model unavailable |
| 12308 | ERR_EXEC_MODEL_TIMEOUT | 504 | Yes | Model response timeout |
| 12309 | ERR_EXEC_INVALID_RESPONSE | 500 | Yes | Invalid model response format |
| 12310 | ERR_EXEC_CODE_EXTRACTION_FAILED | 500 | Yes | Failed to extract code from response |
| 12311 | ERR_EXEC_PATH_INVALID | 400 | No | Invalid file path in plan |
| 12312 | ERR_EXEC_PATH_TRAVERSAL | 403 | No | Path traversal attempt detected |

### Git Errors (12400-12499)

| Code | Constant | HTTP | Retryable | Description |
|------|----------|------|-----------|-------------|
| 12400 | ERR_CODEGEN_GIT_INIT_FAILED | 500 | Yes | Failed to initialize repository |
| 12401 | ERR_CODEGEN_GIT_COMMIT_FAILED | 500 | Yes | Failed to commit changes |
| 12402 | ERR_CODEGEN_GIT_PUSH_FAILED | 500 | Yes | Failed to push to remote |
| 12403 | ERR_CODEGEN_GIT_PULL_FAILED | 500 | Yes | Failed to pull from remote |
| 12404 | ERR_CODEGEN_GIT_CONFLICT | 409 | No | Merge conflict detected |
| 12405 | ERR_CODEGEN_GIT_NO_REMOTE | 400 | No | No remote configured |
| 12406 | ERR_CODEGEN_OAUTH_NOT_CONNECTED | 401 | No | OAuth not connected for provider |
| 12407 | ERR_CODEGEN_OAUTH_TOKEN_EXPIRED | 401 | Yes | OAuth token expired |
| 12408 | ERR_CODEGEN_OAUTH_REFRESH_FAILED | 500 | Yes | Failed to refresh OAuth token |
| 12409 | ERR_CODEGEN_GIT_REPO_CREATE_FAILED | 500 | Yes | Failed to create remote repository |
| 12410 | ERR_CODEGEN_GIT_REPO_NOT_FOUND | 404 | No | Remote repository not found |
| 12411 | ERR_CODEGEN_GIT_PERMISSION_DENIED | 403 | No | Git permission denied |
| 12412 | ERR_CODEGEN_GIT_STASH_FAILED | 500 | Yes | Failed to stash changes |
| 12413 | ERR_CODEGEN_GIT_STASH_POP_FAILED | 500 | Yes | Failed to apply stashed changes |
| 12414 | ERR_CODEGEN_OAUTH_STATE_MISMATCH | 400 | No | OAuth state mismatch |
| 12415 | ERR_CODEGEN_OAUTH_PROVIDER_ERROR | 502 | Yes | OAuth provider error |

### Build Errors (12500-12599)

| Code | Constant | HTTP | Retryable | Description |
|------|----------|------|-----------|-------------|
| 12500 | ERR_BUILD_VERIFICATION_FAILED | 500 | No | Build verification failed |
| 12501 | ERR_BUILD_BRUN_NOT_FOUND | 500 | No | brun CLI not found |
| 12502 | ERR_BUILD_BRUN_TIMEOUT | 504 | Yes | brun execution timeout |
| 12503 | ERR_BUILD_PARSE_FAILED | 500 | No | Failed to parse build output |
| 12504 | ERR_BUILD_FIX_FAILED | 500 | No | AI fix loop exhausted |
| 12505 | ERR_BUILD_LANGUAGE_UNSUPPORTED | 400 | No | Language not supported by brun |
| 12506 | ERR_BUILD_WORKSPACE_INVALID | 400 | No | Invalid build workspace |
| 12507 | ERR_BUILD_DEPENDENCIES_MISSING | 400 | No | Build dependencies missing |
| 12508 | ERR_BUILD_CONFIG_INVALID | 400 | No | Invalid build configuration |

### Credit Errors (12600-12699)

| Code | Constant | HTTP | Retryable | Description |
|------|----------|------|-----------|-------------|
| 12600 | ERR_CREDITS_INSUFFICIENT | 402 | No | Insufficient credits for operation |
| 12601 | ERR_CREDITS_ESTIMATION_FAILED | 500 | Yes | Failed to estimate credit cost |
| 12602 | ERR_CREDITS_TRANSACTION_FAILED | 500 | Yes | Failed to record transaction |
| 12603 | ERR_CREDITS_PLAN_NOT_FOUND | 404 | No | Credit plan not found |
| 12604 | ERR_CREDITS_PURCHASE_FAILED | 500 | Yes | Credit purchase failed |
| 12605 | ERR_CREDITS_NEGATIVE_BALANCE | 400 | No | Balance would be negative |
| 12606 | ERR_CREDITS_USER_NOT_FOUND | 404 | No | User credits not found |
| 12607 | ERR_CREDITS_ALREADY_REFUNDED | 400 | No | Transaction already refunded |

### Repository Structure Errors (12700-12799)

| Code | Constant | HTTP | Retryable | Description |
|------|----------|------|-----------|-------------|
| 12700 | ERR_REPO_CREATE_DIR_FAILED | 500 | Yes | Failed to create directory |
| 12701 | ERR_REPO_TEMPLATE_FAILED | 500 | Yes | Failed to generate template file |
| 12702 | ERR_REPO_INVALID_STRUCTURE | 400 | No | Invalid custom structure |
| 12703 | ERR_REPO_COPY_SPEC_FAILED | 500 | Yes | Failed to copy specification |
| 12704 | ERR_REPO_PATH_EXISTS | 409 | No | Repository path already exists |
| 12705 | ERR_REPO_ROOT_NOT_CONFIGURED | 500 | No | Repository root not configured |
| 12706 | ERR_REPO_PERMISSION_DENIED | 403 | No | Repository permission denied |

---

## Go Error Definitions

```go
package codegen

import "errors"

// General Errors
var (
    ErrCodegenUnknown           = errors.New("ERR_CODEGEN_UNKNOWN")
    ErrCodegenNotEnabled        = errors.New("ERR_CODEGEN_NOT_ENABLED")
    ErrCodegenRunNotFound       = errors.New("ERR_CODEGEN_RUN_NOT_FOUND")
    ErrCodegenRunCompleted      = errors.New("ERR_CODEGEN_RUN_ALREADY_COMPLETED")
    ErrCodegenRunCancelled      = errors.New("ERR_CODEGEN_RUN_CANCELLED")
    ErrCodegenProjectLocked     = errors.New("ERR_CODEGEN_PROJECT_LOCKED")
    ErrCodegenTimeout           = errors.New("ERR_CODEGEN_TIMEOUT")
    ErrCodegenCancelledByUser   = errors.New("ERR_CODEGEN_CANCELLED_BY_USER")
)

// Guideline Errors
var (
    ErrGuidelineNotFound        = errors.New("ERR_GUIDELINE_NOT_FOUND")
    ErrGuidelineInvalidLevel    = errors.New("ERR_GUIDELINE_INVALID_LEVEL")
    ErrGuidelineParseFailed     = errors.New("ERR_GUIDELINE_PARSE_FAILED")
    ErrGuidelineResolutionFailed = errors.New("ERR_GUIDELINE_RESOLUTION_FAILED")
    ErrGuidelineCircularRef     = errors.New("ERR_GUIDELINE_CIRCULAR_REF")
    ErrGuidelineLanguageUnsupported = errors.New("ERR_GUIDELINE_LANGUAGE_UNSUPPORTED")
)

// Execution Errors
var (
    ErrExecModelSelectFailed    = errors.New("ERR_EXEC_MODEL_SELECT_FAILED")
    ErrExecGenerationFailed     = errors.New("ERR_EXEC_GENERATION_FAILED")
    ErrExecWriteFailed          = errors.New("ERR_EXEC_WRITE_FAILED")
    ErrExecBatchTimeout         = errors.New("ERR_EXEC_BATCH_TIMEOUT")
    ErrExecNoWorkers            = errors.New("ERR_EXEC_NO_WORKERS")
    ErrExecContextTooLarge      = errors.New("ERR_EXEC_CONTEXT_TOO_LARGE")
)

// Credit Errors
var (
    ErrCreditsInsufficient      = errors.New("ERR_CREDITS_INSUFFICIENT")
    ErrCreditsEstimationFailed  = errors.New("ERR_CREDITS_ESTIMATION_FAILED")
    ErrCreditsTransactionFailed = errors.New("ERR_CREDITS_TRANSACTION_FAILED")
)

// ErrorCode mapping
var ErrorCodeMap = map[string]int{
    "ERR_CODEGEN_UNKNOWN":           12000,
    "ERR_CODEGEN_NOT_ENABLED":       12001,
    "ERR_CODEGEN_RUN_NOT_FOUND":     12002,
    // ... etc
}

func GetHTTPStatus(code int) int {
    switch {
    case code >= 12000 && code < 12100:
        return getGeneralHTTPStatus(code)
    case code >= 12100 && code < 12200:
        return getGuidelineHTTPStatus(code)
    case code >= 12200 && code < 12300:
        return getPlanHTTPStatus(code)
    case code >= 12300 && code < 12400:
        return getExecHTTPStatus(code)
    case code >= 12400 && code < 12500:
        return getGitHTTPStatus(code)
    case code >= 12500 && code < 12600:
        return getBuildHTTPStatus(code)
    case code >= 12600 && code < 12700:
        return getCreditHTTPStatus(code)
    case code >= 12700 && code < 12800:
        return getRepoHTTPStatus(code)
    default:
        return 500
    }
}

func IsRetryable(code int) bool {
    retryableCodes := map[int]bool{
        12005: true, 12006: true,           // General
        12103: true, 12108: true,           // Guidelines
        12200: true, 12206: true,           // Planning
        12300: true, 12301: true, 12302: true, 12303: true, 12305: true, 12307: true, 12308: true, 12309: true, 12310: true, // Execution
        12400: true, 12401: true, 12402: true, 12403: true, 12407: true, 12408: true, 12409: true, 12412: true, 12413: true, 12415: true, // Git
        12502: true,                        // Build
        12601: true, 12602: true, 12604: true, // Credits
        12700: true, 12701: true, 12703: true, // Repository
    }
    return retryableCodes[code]
}
```

---

## TypeScript Error Types

```typescript
export enum CodegenErrorCode {
    // General
    UNKNOWN = 12000,
    NOT_ENABLED = 12001,
    RUN_NOT_FOUND = 12002,
    RUN_ALREADY_COMPLETED = 12003,
    RUN_CANCELLED = 12004,
    PROJECT_LOCKED = 12005,
    TIMEOUT = 12006,
    CANCELLED_BY_USER = 12007,
    
    // Guidelines
    GUIDELINE_NOT_FOUND = 12100,
    GUIDELINE_INVALID_LEVEL = 12101,
    // ... etc
    
    // Credits
    CREDITS_INSUFFICIENT = 12600,
    // ... etc
}

export function isRetryable(code: CodegenErrorCode): boolean {
    const retryableCodes = new Set([
        CodegenErrorCode.PROJECT_LOCKED,
        CodegenErrorCode.TIMEOUT,
        // ... etc
    ]);
    return retryableCodes.has(code);
}

export function getErrorMessage(code: CodegenErrorCode): string {
    const messages: Record<CodegenErrorCode, string> = {
        [CodegenErrorCode.UNKNOWN]: 'An unknown error occurred',
        [CodegenErrorCode.CREDITS_INSUFFICIENT]: 'Insufficient credits. Please add more credits to continue.',
        // ... etc
    };
    return messages[code] ?? 'Unknown error';
}
```

---

## Related Specs

- [Error Management](../../06-error-management/00-overview.md)
- [Error Code Registry](../../06-error-management/error-code-registry.md)
- [API Endpoints](./13-api-endpoints.md)
