# Code Generation System - API Endpoints

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Overview

REST API specification for the Code Generation System. All endpoints follow the standard response envelope format and require JWT authentication.

**Cross-References:**
- [Overview](./00-overview.md)
- [Architecture](./01-architecture.md)
- [Data Models](./14-data-models.md)
- [WebSocket Events](./15-websocket-events.md)
- [OpenAPI Specification](../../api/openapi.yaml)

---

## Response Envelope

All responses use the standard JSON envelope:

```json
{
  "success": true,
  "data": { },
  "error": null,
  "meta": {
    "timestamp": "2026-01-29T10:30:00Z",
    "requestId": "req_abc123"
  }
}
```

### Error Response

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": 12201,
    "message": "Spec file not found",
    "details": "File 'spec/05-features/missing.md' does not exist"
  },
  "meta": {
    "timestamp": "2026-01-29T10:30:00Z",
    "requestId": "req_abc123"
  }
}
```

---

## Endpoint Summary

| Domain | Endpoints | Base Path |
|--------|-----------|-----------|
| [Guidelines](#1-guidelines-api) | 12 | `/api/v1/guidelines` |
| [Plans](#2-plans-api) | 6 | `/api/v1/codegen/plans` |
| [Sessions](#3-sessions-api) | 7 | `/api/v1/codegen/sessions` |
| [Git](#4-git-api) | 10 | `/api/v1/git` |
| [Build](#5-build-verification-api) | 4 | `/api/v1/codegen/build` |
| [Credits](#6-credits-api) | 7 | `/api/v1/credits` |

**Total:** 46 endpoints

---

## 1. Guidelines API

### GET /api/v1/guidelines/resolved

Get merged guidelines for a project and language.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| projectId | string | Yes | Project UUID |
| language | string | Yes | Target language (go, react, php) |

**Response:**

```json
{
  "success": true,
  "data": {
    "mergedPrompt": "You are an expert Go developer...",
    "layers": {
      "general": [
        { "id": 1, "name": "Error Handling", "category": "error_handling" }
      ],
      "language": [
        { "id": 5, "name": "Go Conventions", "language": "go" }
      ],
      "user": [],
      "project": [
        { "id": 12, "name": "Project Standards", "projectId": "uuid" }
      ]
    },
    "effectiveRules": {
      "error_handling": "Return early, wrap errors with context...",
      "naming": "Use camelCase for functions..."
    }
  }
}
```

**Errors:** 12100, 12101, 12102

---

### GET /api/v1/guidelines/general

List all general guidelines.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| category | string | No | Filter by category |
| isActive | boolean | No | Filter by active status |
| page | int | No | Page number (default: 1) |
| limit | int | No | Items per page (default: 20) |

**Response:**

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "name": "Error Handling",
        "category": "error_handling",
        "content": "## Error Handling\n\n...",
        "priority": 0,
        "isActive": true,
        "version": "1.0.0"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 15,
      "totalPages": 1
    }
  }
}
```

---

### POST /api/v1/guidelines/general

Create a general guideline.

**Request Body:**

```json
{
  "name": "Documentation Standards",
  "category": "documentation",
  "content": "## Documentation\n\nAll functions must have...",
  "priority": 10,
  "version": "1.0.0"
}
```

**Response:** Created guideline object with `id`.

**Errors:** 12100, 12103

---

### PUT /api/v1/guidelines/general/{id}

Update a general guideline.

---

### DELETE /api/v1/guidelines/general/{id}

Delete a general guideline.

---

### GET /api/v1/guidelines/language

List language-specific guidelines.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| language | string | No | Filter by language |
| category | string | No | Filter by category |

---

### POST /api/v1/guidelines/language

Create a language-specific guideline.

**Request Body:**

```json
{
  "language": "go",
  "name": "Go Error Wrapping",
  "category": "error_handling",
  "content": "Use fmt.Errorf with %w...",
  "extendsRule": "error_handling",
  "overrideKey": "error_messages"
}
```

---

### GET /api/v1/guidelines/user

List current user's preferences.

---

### POST /api/v1/guidelines/user

Create a user preference.

**Request Body:**

```json
{
  "language": "go",
  "name": "Personal Logging Style",
  "category": "logging",
  "content": "Always use structured logging...",
  "overrideKey": "logging_format"
}
```

---

### GET /api/v1/guidelines/project/{projectId}

List project-specific guidelines.

---

### POST /api/v1/guidelines/project/{projectId}

Create a project guideline.

**Request Body:**

```json
{
  "language": "go",
  "name": "API Response Format",
  "category": "api",
  "content": "All responses use envelope format...",
  "specFileRef": "spec/03-api-design/01-response-format.md"
}
```

---

### DELETE /api/v1/guidelines/project/{projectId}/{id}

Delete a project guideline.

---

## 2. Plans API

### POST /api/v1/codegen/plans

Generate a new execution plan from specifications.

**Request Body:**

```json
{
  "projectId": "uuid",
  "name": "Initial Backend Generation",
  "specPaths": [
    "spec/05-features/03-api-design/",
    "spec/05-features/07-database-design/"
  ],
  "languages": ["go", "react"],
  "options": {
    "includeTests": true,
    "includeMigrations": true,
    "includeDocumentation": false
  }
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "plan_uuid",
    "projectId": "project_uuid",
    "name": "Initial Backend Generation",
    "status": "ready",
    "totalFiles": 45,
    "totalBatches": 8,
    "estimatedTokens": 125000,
    "batches": [
      {
        "index": 0,
        "fileCount": 6,
        "files": ["internal/model/user.go", "internal/model/project.go"],
        "dependsOn": []
      },
      {
        "index": 1,
        "fileCount": 4,
        "files": ["internal/repository/user_repo.go"],
        "dependsOn": [0]
      }
    ],
    "createdAt": "2026-01-29T10:30:00Z"
  }
}
```

**Errors:** 12200, 12201, 12202, 12203, 12204

---

### GET /api/v1/codegen/plans

List plans for a project.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| projectId | string | Yes | Project UUID |
| status | string | No | Filter by status |
| page | int | No | Page number |
| limit | int | No | Items per page |

---

### GET /api/v1/codegen/plans/{planId}

Get plan details.

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "plan_uuid",
    "projectId": "project_uuid",
    "name": "Initial Backend Generation",
    "status": "ready",
    "totalFiles": 45,
    "totalBatches": 8,
    "estimatedTokens": 125000,
    "actualTokens": 0,
    "specSnapshot": { },
    "createdAt": "2026-01-29T10:30:00Z",
    "files": [
      {
        "id": 1,
        "filePath": "internal/model/user.go",
        "language": "go",
        "fileType": "source",
        "batchIndex": 0,
        "status": "pending",
        "dependencies": [],
        "estimatedTokens": 2500
      }
    ],
    "batches": [
      {
        "index": 0,
        "fileCount": 6,
        "status": "pending",
        "dependsOn": []
      }
    ]
  }
}
```

---

### GET /api/v1/codegen/plans/{planId}/files

Get all planned files with dependency details.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| batchIndex | int | No | Filter by batch |
| status | string | No | Filter by status |
| language | string | No | Filter by language |

---

### DELETE /api/v1/codegen/plans/{planId}

Cancel and delete a plan.

**Errors:** 12206 (Plan not found)

---

### POST /api/v1/codegen/plans/{planId}/duplicate

Duplicate a plan for re-execution.

---

## 3. Sessions API

### POST /api/v1/codegen/sessions

Start a new execution session from a plan.

**Request Body:**

```json
{
  "planId": "plan_uuid",
  "options": {
    "maxWorkers": 4,
    "autoCommit": true,
    "autoPush": false,
    "skipExisting": true
  }
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "session_uuid",
    "planId": 1,
    "status": "running",
    "totalFiles": 45,
    "completedFiles": 0,
    "failedFiles": 0,
    "currentBatch": 0,
    "workersActive": 4,
    "startedAt": "2026-01-29T10:30:00Z",
    "websocketUrl": "/ws/codegen/session_uuid"
  }
}
```

**Errors:** 12300, 12306 (Insufficient credits)

---

### GET /api/v1/codegen/sessions

List sessions for a project.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| projectId | string | Yes | Project UUID |
| status | string | No | Filter by status |
| page | int | No | Page number |
| limit | int | No | Items per page |

---

### GET /api/v1/codegen/sessions/{sessionId}

Get session status and progress.

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "session_uuid",
    "planId": 1,
    "status": "running",
    "totalFiles": 45,
    "completedFiles": 23,
    "failedFiles": 1,
    "skippedFiles": 0,
    "totalTokens": 58000,
    "totalCredits": 1.74,
    "currentBatch": 4,
    "workersActive": 4,
    "startedAt": "2026-01-29T10:30:00Z",
    "elapsedMs": 125000,
    "estimatedRemainingMs": 95000,
    "currentFiles": [
      "internal/service/user_service.go",
      "internal/service/project_service.go"
    ]
  }
}
```

---

### GET /api/v1/codegen/sessions/{sessionId}/files

Get generated files for a session.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| status | string | No | Filter by status |
| includeContent | boolean | No | Include file content (default: false) |

**Response:**

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "filePath": "internal/model/user.go",
        "status": "success",
        "modelUsed": "coding1",
        "promptTokens": 1200,
        "completionTokens": 800,
        "generationTimeMs": 3500,
        "content": "package model\n\n..."
      }
    ]
  }
}
```

---

### POST /api/v1/codegen/sessions/{sessionId}/pause

Pause a running session.

---

### POST /api/v1/codegen/sessions/{sessionId}/resume

Resume a paused session.

---

### POST /api/v1/codegen/sessions/{sessionId}/stop

Stop and cancel a session.

---

## 4. Git API

### POST /api/v1/git/repos/{projectId}/init

Initialize a local Git repository for a project.

**Request Body:**

```json
{
  "localPath": "/projects/code-output/my-project",
  "includeSpec": true,
  "createReadme": true
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "projectId": "project_uuid",
    "localPath": "/projects/code-output/my-project",
    "isInitialized": true,
    "remoteBranch": "main",
    "autoCommit": true,
    "autoPush": false,
    "createdAt": "2026-01-29T10:30:00Z"
  }
}
```

**Errors:** 12400, 12401

---

### GET /api/v1/git/repos/{projectId}

Get repository configuration.

---

### PUT /api/v1/git/repos/{projectId}

Update repository settings.

**Request Body:**

```json
{
  "autoCommit": true,
  "autoPush": true,
  "remoteBranch": "develop"
}
```

---

### POST /api/v1/git/repos/{projectId}/connect-remote

Connect repository to GitHub/GitLab.

**Request Body:**

```json
{
  "provider": "github",
  "remoteUrl": "https://github.com/user/repo.git",
  "branch": "main"
}
```

**Errors:** 12404, 12408, 12409

---

### POST /api/v1/git/repos/{projectId}/push

Push commits to remote.

**Errors:** 12405

---

### POST /api/v1/git/repos/{projectId}/pull

Pull from remote.

**Errors:** 12406, 12407 (Merge conflict)

---

### GET /api/v1/git/repos/{projectId}/commits

List commits.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| page | int | No | Page number |
| limit | int | No | Items per page |
| isPushed | boolean | No | Filter by pushed status |

**Response:**

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "commitHash": "abc123def456",
        "message": "[CodeGen] Generated 5 file(s)\n\nSpec References:\n- spec/03-api/...",
        "filesAdded": 5,
        "filesModified": 0,
        "filesDeleted": 0,
        "isPushed": true,
        "pushedAt": "2026-01-29T10:35:00Z",
        "createdAt": "2026-01-29T10:30:00Z"
      }
    ],
    "pagination": { }
  }
}
```

---

### GET /api/v1/git/oauth/{provider}/url

Get OAuth authorization URL.

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| provider | string | `github` or `gitlab` |

**Response:**

```json
{
  "success": true,
  "data": {
    "authUrl": "https://github.com/login/oauth/authorize?client_id=...",
    "state": "random_state_token"
  }
}
```

---

### POST /api/v1/git/oauth/{provider}/callback

Handle OAuth callback.

**Request Body:**

```json
{
  "code": "oauth_authorization_code",
  "state": "random_state_token"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "provider": "github",
    "username": "user123",
    "email": "user@example.com",
    "isActive": true,
    "connectedAt": "2026-01-29T10:30:00Z"
  }
}
```

**Errors:** 12402, 12403

---

### GET /api/v1/git/oauth/connections

List user's OAuth connections.

---

## 5. Build Verification API

### POST /api/v1/codegen/build/{sessionId}/verify

Start build verification for a session.

**Request Body:**

```json
{
  "languages": ["go", "react"],
  "maxFixAttempts": 3,
  "autoFix": true
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "verifications": [
      {
        "id": 1,
        "language": "go",
        "status": "running",
        "maxAttempts": 3
      },
      {
        "id": 2,
        "language": "react",
        "status": "pending",
        "maxAttempts": 3
      }
    ]
  }
}
```

**Errors:** 12500, 12501

---

### GET /api/v1/codegen/build/{sessionId}

Get verification status for a session.

**Response:**

```json
{
  "success": true,
  "data": {
    "verifications": [
      {
        "id": 1,
        "language": "go",
        "status": "success",
        "attemptCount": 1,
        "initialErrors": 3,
        "finalErrors": 0,
        "fixedErrors": 3,
        "duration": 45000
      },
      {
        "id": 2,
        "language": "react",
        "status": "failed",
        "attemptCount": 3,
        "initialErrors": 5,
        "finalErrors": 2,
        "fixedErrors": 3,
        "duration": 120000
      }
    ]
  }
}
```

---

### GET /api/v1/codegen/build/verification/{verificationId}

Get detailed verification results.

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "language": "go",
    "status": "success",
    "attemptCount": 2,
    "initialErrors": 5,
    "finalErrors": 0,
    "fixedErrors": 5,
    "errors": [
      {
        "id": 1,
        "filePath": "internal/handler/user.go",
        "line": 45,
        "column": 12,
        "errorCode": "undeclared",
        "errorMessage": "undefined: UserService",
        "severity": "error",
        "isFixed": true,
        "fixAttempt": 1
      }
    ],
    "fixAttempts": [
      {
        "attemptNumber": 1,
        "errorsAtStart": 5,
        "errorsAfterFix": 2,
        "filesModified": ["internal/handler/user.go"],
        "tokensUsed": 3500,
        "success": true
      },
      {
        "attemptNumber": 2,
        "errorsAtStart": 2,
        "errorsAfterFix": 0,
        "filesModified": ["internal/service/user.go"],
        "tokensUsed": 2800,
        "success": true
      }
    ]
  }
}
```

---

### POST /api/v1/codegen/build/verification/{verificationId}/retry

Retry a failed verification.

**Errors:** 12504 (Max retries exceeded)

---

## 6. Credits API

### GET /api/v1/credits/balance

Get current user's credit balance.

**Response:**

```json
{
  "success": true,
  "data": {
    "userId": "user_uuid",
    "totalCredits": 500.0000,
    "usedCredits": 125.5000,
    "balance": 374.5000,
    "freeCredits": 100.0000,
    "freeUsed": 100.0000,
    "lastTopupAt": "2026-01-15T10:00:00Z",
    "lastUsageAt": "2026-01-29T10:30:00Z"
  }
}
```

---

### GET /api/v1/credits/transactions

List credit transactions.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| type | string | No | Filter by type (usage, purchase, refund) |
| startDate | string | No | Filter from date (ISO8601) |
| endDate | string | No | Filter to date (ISO8601) |
| page | int | No | Page number |
| limit | int | No | Items per page |

**Response:**

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "uuid": "txn_uuid",
        "type": "usage",
        "amount": -1.2500,
        "description": "code_generation: 25000 input + 12000 output tokens",
        "modelId": "coding1",
        "tokensInput": 25000,
        "tokensOutput": 12000,
        "balanceBefore": 375.7500,
        "balanceAfter": 374.5000,
        "createdAt": "2026-01-29T10:30:00Z"
      }
    ],
    "pagination": { },
    "summary": {
      "totalUsage": 125.5000,
      "totalPurchased": 500.0000,
      "periodStart": "2026-01-01T00:00:00Z",
      "periodEnd": "2026-01-29T23:59:59Z"
    }
  }
}
```

---

### GET /api/v1/credits/usage

Get usage statistics.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| period | string | No | `day`, `week`, `month` (default: month) |
| groupBy | string | No | `model`, `project`, `requestType` |

**Response:**

```json
{
  "success": true,
  "data": {
    "period": "month",
    "totalCredits": 125.5000,
    "totalTokens": 2500000,
    "totalRequests": 850,
    "byModel": {
      "coding1": { "credits": 80.0000, "tokens": 1600000, "requests": 550 },
      "coding2": { "credits": 45.5000, "tokens": 900000, "requests": 300 }
    },
    "byProject": {
      "project_uuid": { "credits": 125.5000, "name": "My Project" }
    },
    "dailyUsage": [
      { "date": "2026-01-29", "credits": 15.2500 },
      { "date": "2026-01-28", "credits": 22.1000 }
    ]
  }
}
```

---

### GET /api/v1/credits/plans

List available credit plans.

**Response:**

```json
{
  "success": true,
  "data": {
    "plans": [
      {
        "id": 1,
        "name": "Starter",
        "description": "Perfect for trying out code generation",
        "credits": 100.0000,
        "price": 5.00,
        "currency": "USD",
        "bonusCredits": 0,
        "bonusPercent": 0,
        "isPopular": false
      },
      {
        "id": 2,
        "name": "Developer",
        "description": "Best value for active developers",
        "credits": 500.0000,
        "price": 20.00,
        "currency": "USD",
        "bonusCredits": 50.0000,
        "bonusPercent": 0,
        "isPopular": true
      }
    ]
  }
}
```

---

### POST /api/v1/credits/purchase

Initiate credit purchase.

**Request Body:**

```json
{
  "planId": 2
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "paymentIntentId": "pi_abc123",
    "clientSecret": "pi_abc123_secret_xyz",
    "amount": 20.00,
    "currency": "USD",
    "credits": 550.0000
  }
}
```

**Errors:** 12602, 12603

---

### POST /api/v1/credits/purchase/confirm

Confirm completed purchase.

**Request Body:**

```json
{
  "paymentIntentId": "pi_abc123"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "transaction": {
      "id": 10,
      "uuid": "txn_uuid",
      "type": "purchase",
      "amount": 550.0000,
      "description": "Purchased Developer plan",
      "balanceAfter": 924.5000
    },
    "newBalance": 924.5000
  }
}
```

---

### GET /api/v1/credits/estimate

Estimate credits for a plan.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| planId | string | Yes | Plan UUID to estimate |

**Response:**

```json
{
  "success": true,
  "data": {
    "planId": "plan_uuid",
    "estimatedTokens": 125000,
    "estimatedCredits": 3.75,
    "breakdown": {
      "codeGeneration": 3.25,
      "buildVerification": 0.50
    },
    "currentBalance": 374.5000,
    "sufficient": true
  }
}
```

---

## Authentication

All endpoints require JWT authentication via Bearer token:

```
Authorization: Bearer <jwt_token>
```

---

## Rate Limiting

| Endpoint Category | Rate Limit |
|-------------------|------------|
| Guidelines (read) | 100/minute |
| Guidelines (write) | 20/minute |
| Plans | 30/minute |
| Sessions | 10/minute |
| Git operations | 20/minute |
| Build verification | 10/minute |
| Credits | 50/minute |

---

## Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| 12100 | 500 | Guideline resolution failed |
| 12101 | 404 | Guideline not found |
| 12102 | 400 | Invalid language |
| 12103 | 400 | Validation error |
| 12200 | 500 | Plan generation failed |
| 12201 | 404 | Spec file not found |
| 12202 | 400 | Invalid spec format |
| 12203 | 400 | Circular dependency |
| 12204 | 400 | Unsupported language |
| 12206 | 404 | Plan not found |
| 12300 | 500 | Session failed |
| 12306 | 402 | Insufficient credits |
| 12400 | 500 | Git operation failed |
| 12401 | 400 | Repository not initialized |
| 12402 | 401 | OAuth connection failed |
| 12403 | 401 | Token refresh failed |
| 12404 | 400 | Remote not configured |
| 12405 | 500 | Push failed |
| 12406 | 500 | Pull failed |
| 12407 | 409 | Merge conflict |
| 12408 | 400 | Invalid remote URL |
| 12409 | 403 | Permission denied |
| 12500 | 500 | Build verification failed |
| 12501 | 404 | brun CLI not found |
| 12504 | 400 | Max retries exceeded |
| 12601 | 402 | Insufficient credits |
| 12602 | 404 | Invalid credit plan |
| 12603 | 402 | Payment failed |

---

## Related Specifications

- [WebSocket Events](./15-websocket-events.md)
- [Data Models](./14-data-models.md)
- [Error Codes](./16-error-codes.md)
