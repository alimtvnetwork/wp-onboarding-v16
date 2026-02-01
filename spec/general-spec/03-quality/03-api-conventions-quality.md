# API Conventions

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines REST API design patterns, request/response formats, authentication standards, and error handling conventions across all backend services.

---

## 1. URL Structure

### 1.1 Base URL Pattern

```
https://api.example.com/v1/{resource}
https://example.com/wp-json/plugin/v1/{resource}  # WordPress
```

### 1.2 Resource Naming

| Rule | Example | Notes |
|------|---------|-------|
| Plural nouns | `/users`, `/exams` | Never singular |
| Lowercase | `/user-profiles` | Kebab-case for multi-word |
| No verbs | `/users` not `/getUsers` | HTTP methods define actions |
| Hierarchical | `/exams/{id}/sections` | Parent-child relationships |

### 1.3 URL Examples

```
GET    /api/v1/exams                    # List all exams
GET    /api/v1/exams/{id}               # Get single exam
POST   /api/v1/exams                    # Create exam
PUT    /api/v1/exams/{id}               # Update exam (full)
PATCH  /api/v1/exams/{id}               # Update exam (partial)
DELETE /api/v1/exams/{id}               # Delete exam

GET    /api/v1/exams/{id}/sections      # List exam sections
POST   /api/v1/exams/{id}/sections      # Add section to exam

GET    /api/v1/users/{id}/enrollments   # List user enrollments
POST   /api/v1/exams/{id}/enroll        # Action endpoint (verb allowed)
```

---

## 2. HTTP Methods

### 2.1 Method Semantics

| Method | Purpose | Idempotent | Request Body | Response Body |
|--------|---------|------------|--------------|---------------|
| GET | Retrieve resource(s) | Yes | No | Yes |
| POST | Create resource | No | Yes | Yes |
| PUT | Replace resource | Yes | Yes | Yes |
| PATCH | Partial update | Yes | Yes | Yes |
| DELETE | Remove resource | Yes | No | Optional |

### 2.2 Safe vs Unsafe

```
Safe (no side effects):     GET, HEAD, OPTIONS
Unsafe (modifies state):    POST, PUT, PATCH, DELETE
```

---

## 3. Request Format

### 3.1 Headers

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
X-Request-ID: {uuid}                    # For request tracing
X-API-Version: 2025-01-01               # Optional version header
```

### 3.2 Request Body

```json
// POST /api/v1/exams
{
  "title": "JavaScript Fundamentals",
  "slug": "js-fundamentals",
  "description": "Learn the basics of JavaScript",
  "settings": {
    "time_limit": 3600,
    "passing_score": 70,
    "allow_retakes": true
  }
}
```

### 3.3 Query Parameters

| Purpose | Pattern | Example |
|---------|---------|---------|
| Pagination | `page`, `per_page` | `?page=2&per_page=20` |
| Sorting | `sort`, `order` | `?sort=created_at&order=desc` |
| Filtering | Field names | `?status=active&role=admin` |
| Search | `q` or `search` | `?q=javascript` |
| Field selection | `fields` | `?fields=id,title,status` |
| Expansion | `include` | `?include=sections,author` |

---

## 4. Response Format

### 4.1 Standard Envelope

All responses use a consistent envelope:

```typescript
interface ApiResponse<T> {
  success: boolean;
  data: T | null;
  error: ApiError | null;
  meta?: ResponseMeta;
}

interface ApiError {
  code: string;           // Machine-readable: "ERR_1001"
  message: string;        // Human-readable: "Validation failed"
  details?: Record<string, string[]>;  // Field-specific errors
}

interface ResponseMeta {
  request_id: string;
  timestamp: string;
  version: string;
}
```

### 4.2 Success Responses

```json
// Single resource - GET /api/v1/exams/123
{
  "success": true,
  "data": {
    "id": 123,
    "title": "JavaScript Fundamentals",
    "slug": "js-fundamentals",
    "status": "published",
    "created_at": "2025-01-15T10:30:00Z",
    "updated_at": "2025-01-20T14:45:00Z"
  },
  "error": null,
  "meta": {
    "request_id": "req_abc123",
    "timestamp": "2025-01-26T12:00:00Z"
  }
}

// Collection - GET /api/v1/exams
{
  "success": true,
  "data": [
    { "id": 1, "title": "Exam 1", ... },
    { "id": 2, "title": "Exam 2", ... }
  ],
  "error": null,
  "meta": {
    "request_id": "req_xyz789",
    "timestamp": "2025-01-26T12:00:00Z",
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 150,
      "total_pages": 8
    }
  }
}

// Create response - POST /api/v1/exams
{
  "success": true,
  "data": {
    "id": 124,
    "title": "New Exam",
    ...
  },
  "error": null
}

// Delete response - DELETE /api/v1/exams/123
{
  "success": true,
  "data": null,
  "error": null
}
```

### 4.3 Error Responses

```json
// Validation error - 400
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERR_1001",
    "message": "Validation failed",
    "details": {
      "title": ["Title is required", "Title must be at least 3 characters"],
      "slug": ["Slug already exists"]
    }
  }
}

// Authentication error - 401
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERR_2001",
    "message": "Authentication required"
  }
}

// Authorization error - 403
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERR_2002",
    "message": "Insufficient permissions to access this resource"
  }
}

// Not found - 404
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERR_4001",
    "message": "Exam not found"
  }
}

// Server error - 500
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERR_5000",
    "message": "An unexpected error occurred"
  }
}
```

---

## 5. HTTP Status Codes

### 5.1 Success Codes

| Code | Meaning | When to Use |
|------|---------|-------------|
| 200 | OK | GET, PUT, PATCH, DELETE success |
| 201 | Created | POST success, resource created |
| 204 | No Content | DELETE success, no response body |

### 5.2 Client Error Codes

| Code | Meaning | When to Use |
|------|---------|-------------|
| 400 | Bad Request | Validation failed, malformed request |
| 401 | Unauthorized | Missing or invalid authentication |
| 403 | Forbidden | Valid auth, insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 409 | Conflict | Duplicate entry, state conflict |
| 422 | Unprocessable Entity | Semantic validation errors |
| 429 | Too Many Requests | Rate limit exceeded |

### 5.3 Server Error Codes

| Code | Meaning | When to Use |
|------|---------|-------------|
| 500 | Internal Server Error | Unexpected server error |
| 502 | Bad Gateway | Upstream service failure |
| 503 | Service Unavailable | Maintenance, overloaded |
| 504 | Gateway Timeout | Upstream timeout |

---

## 6. Authentication

### 6.1 Bearer Token

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

### 6.2 Token Response

```json
// POST /api/v1/auth/login
{
  "success": true,
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "dGhpcyBpcyBhIHJlZnJl...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "expires_at": "2025-01-26T13:00:00Z"
  }
}
```

### 6.3 Token Refresh

```json
// POST /api/v1/auth/refresh
// Request
{
  "refresh_token": "dGhpcyBpcyBhIHJlZnJl..."
}

// Response
{
  "success": true,
  "data": {
    "access_token": "eyJuZXcgdG9rZW4gaGVy...",
    "expires_in": 3600
  }
}
```

---

## 7. Pagination

### 7.1 Offset-Based Pagination

```http
GET /api/v1/exams?page=2&per_page=20
```

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "pagination": {
      "page": 2,
      "per_page": 20,
      "total": 150,
      "total_pages": 8,
      "has_next": true,
      "has_prev": true
    },
    "links": {
      "first": "/api/v1/exams?page=1&per_page=20",
      "prev": "/api/v1/exams?page=1&per_page=20",
      "next": "/api/v1/exams?page=3&per_page=20",
      "last": "/api/v1/exams?page=8&per_page=20"
    }
  }
}
```

### 7.2 Cursor-Based Pagination

For large datasets or real-time data:

```http
GET /api/v1/events?cursor=eyJpZCI6MTAwfQ&limit=50
```

```json
{
  "success": true,
  "data": [...],
  "meta": {
    "pagination": {
      "limit": 50,
      "has_more": true,
      "next_cursor": "eyJpZCI6MTUwfQ"
    }
  }
}
```

---

## 8. Filtering and Sorting

### 8.1 Filter Operators

```http
# Exact match
GET /api/v1/exams?status=published

# Multiple values (OR)
GET /api/v1/exams?status[]=draft&status[]=published

# Range
GET /api/v1/exams?created_at[gte]=2025-01-01&created_at[lt]=2025-02-01

# Search
GET /api/v1/exams?q=javascript

# Operators: eq, ne, gt, gte, lt, lte, like, in
```

### 8.2 Sorting

```http
# Single field
GET /api/v1/exams?sort=created_at&order=desc

# Multiple fields
GET /api/v1/exams?sort=status,-created_at
# Prefix with - for descending
```

---

## 9. Versioning

### 9.1 URL Path Versioning (Recommended)

```http
GET /api/v1/exams
GET /api/v2/exams
```

### 9.2 Version Support Policy

```
Current version (v1):     Full support
Previous version (v0):    Deprecated, 6-month sunset
Older versions:           Discontinued
```

### 9.3 Deprecation Headers

```http
HTTP/1.1 200 OK
Deprecation: true
Sunset: Sat, 01 Jul 2025 00:00:00 GMT
Link: </api/v2/exams>; rel="successor-version"
```

---

## 10. Rate Limiting

### 10.1 Rate Limit Headers

```http
HTTP/1.1 200 OK
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 998
X-RateLimit-Reset: 1706270400
```

### 10.2 Rate Limit Exceeded Response

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 60
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1706270400

{
  "success": false,
  "data": null,
  "error": {
    "code": "ERR_4290",
    "message": "Rate limit exceeded. Try again in 60 seconds."
  }
}
```

---

## 11. Caching

### 11.1 Cache Headers

```http
# Cacheable response
HTTP/1.1 200 OK
Cache-Control: public, max-age=3600
ETag: "abc123"
Last-Modified: Fri, 24 Jan 2025 10:00:00 GMT

# Non-cacheable
HTTP/1.1 200 OK
Cache-Control: no-store, no-cache, must-revalidate
```

### 11.2 Conditional Requests

```http
# Client request
GET /api/v1/exams/123
If-None-Match: "abc123"

# Server response (not modified)
HTTP/1.1 304 Not Modified
```

---

## 12. Implementation Examples

### 12.1 PHP Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ExamService;
use App\Http\Requests\CreateExamRequest;
use App\Http\Responses\ApiResponse;

final class ExamController
{
    public function __construct(
        private readonly ExamService $examService,
    ) {}
    
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);
        $filters = $request->only(['status', 'category']);
        
        $result = $this->examService->list($page, $perPage, $filters);
        
        return ApiResponse::success($result->items)
            ->withPagination($result->pagination)
            ->toJson();
    }
    
    public function show(int $id): JsonResponse
    {
        $exam = $this->examService->findById($id);
        
        return ApiResponse::success($exam)->toJson();
    }
    
    public function store(CreateExamRequest $request): JsonResponse
    {
        $exam = $this->examService->create($request->validated());
        
        return ApiResponse::success($exam)
            ->toJson(201);
    }
    
    public function update(int $id, UpdateExamRequest $request): JsonResponse
    {
        $exam = $this->examService->update($id, $request->validated());
        
        return ApiResponse::success($exam)->toJson();
    }
    
    public function destroy(int $id): JsonResponse
    {
        $this->examService->delete($id);
        
        return ApiResponse::success(null)->toJson();
    }
}
```

### 12.2 TypeScript API Client

```typescript
// services/api.ts
import type { ApiResponse, PaginatedResponse } from '@/types/api';
import type { Exam, CreateExamInput } from '@/types/exam';

const API_BASE = '/api/v1';

class ApiClient {
  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    const response = await fetch(`${API_BASE}${endpoint}`, {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getToken()}`,
        ...options.headers,
      },
      ...options,
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      throw new ApiError(data.error);
    }
    
    return data;
  }
  
  async get<T>(endpoint: string): Promise<T> {
    const response = await this.request<T>(endpoint);
    return response.data;
  }
  
  async post<T>(endpoint: string, body: unknown): Promise<T> {
    const response = await this.request<T>(endpoint, {
      method: 'POST',
      body: JSON.stringify(body),
    });
    return response.data;
  }
  
  async put<T>(endpoint: string, body: unknown): Promise<T> {
    const response = await this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(body),
    });
    return response.data;
  }
  
  async delete(endpoint: string): Promise<void> {
    await this.request(endpoint, { method: 'DELETE' });
  }
}

export const api = new ApiClient();

// Typed service layer
export const examService = {
  list: (params?: { page?: number; status?: string }) =>
    api.get<PaginatedResponse<Exam>>(`/exams?${new URLSearchParams(params)}`),
    
  get: (id: number) =>
    api.get<Exam>(`/exams/${id}`),
    
  create: (data: CreateExamInput) =>
    api.post<Exam>('/exams', data),
    
  update: (id: number, data: Partial<CreateExamInput>) =>
    api.put<Exam>(`/exams/${id}`, data),
    
  delete: (id: number) =>
    api.delete(`/exams/${id}`),
};
```

### 12.3 Python FastAPI

```python
# routes/exam_routes.py
from fastapi import APIRouter, Depends, Query, HTTPException, status
from typing import Optional
from app.services.exam_service import ExamService
from app.schemas.exam import ExamCreate, ExamUpdate, ExamResponse
from app.schemas.api import ApiResponse, PaginatedResponse
from app.dependencies import get_current_user

router = APIRouter(prefix="/exams", tags=["exams"])

@router.get("", response_model=ApiResponse[PaginatedResponse[ExamResponse]])
async def list_exams(
    page: int = Query(1, ge=1),
    per_page: int = Query(20, ge=1, le=100),
    status: Optional[str] = None,
    exam_service: ExamService = Depends(),
):
    result = exam_service.list(page, per_page, status=status)
    return ApiResponse(
        success=True,
        data=result,
    )

@router.get("/{exam_id}", response_model=ApiResponse[ExamResponse])
async def get_exam(
    exam_id: int,
    exam_service: ExamService = Depends(),
):
    exam = exam_service.find_by_id(exam_id)
    if not exam:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail={"code": "ERR_4001", "message": "Exam not found"}
        )
    return ApiResponse(success=True, data=exam)

@router.post("", response_model=ApiResponse[ExamResponse], status_code=201)
async def create_exam(
    data: ExamCreate,
    exam_service: ExamService = Depends(),
    current_user = Depends(get_current_user),
):
    exam = exam_service.create(data.dict(), current_user)
    return ApiResponse(success=True, data=exam)

@router.put("/{exam_id}", response_model=ApiResponse[ExamResponse])
async def update_exam(
    exam_id: int,
    data: ExamUpdate,
    exam_service: ExamService = Depends(),
    current_user = Depends(get_current_user),
):
    exam = exam_service.update(exam_id, data.dict(exclude_unset=True))
    return ApiResponse(success=True, data=exam)

@router.delete("/{exam_id}", status_code=204)
async def delete_exam(
    exam_id: int,
    exam_service: ExamService = Depends(),
    current_user = Depends(get_current_user),
):
    exam_service.delete(exam_id)
```

---

## 13. Anti-Patterns

### ❌ DON'T

```http
# Verbs in URLs
GET /api/v1/getExams
POST /api/v1/createExam

# Inconsistent response formats
GET /api/v1/exams → [...]           # Array
GET /api/v1/exams/1 → {...}         # Object
POST /api/v1/exams → "Created"      # String

# Leaking internal errors
{
  "error": "SQLSTATE[23000]: Integrity constraint violation..."
}

# Using 200 for errors
HTTP/1.1 200 OK
{ "error": true, "message": "Not found" }
```

### ✅ DO

```http
# Nouns in URLs
GET /api/v1/exams
POST /api/v1/exams

# Consistent envelope
GET /api/v1/exams → { "success": true, "data": [...] }
GET /api/v1/exams/1 → { "success": true, "data": {...} }
POST /api/v1/exams → { "success": true, "data": {...} }

# User-friendly errors
{
  "success": false,
  "error": {
    "code": "ERR_3001",
    "message": "This email is already registered"
  }
}

# Proper status codes
HTTP/1.1 404 Not Found
{ "success": false, "error": {...} }
```

---

## Quick Reference

| Aspect | Standard |
|--------|----------|
| Base URL | `/api/v{n}/{resource}` |
| Resources | Plural nouns, lowercase, kebab-case |
| Methods | GET (read), POST (create), PUT/PATCH (update), DELETE (remove) |
| Success codes | 200, 201, 204 |
| Error codes | 400, 401, 403, 404, 422, 429, 500 |
| Response format | `{ success, data, error, meta }` |
| Pagination | `?page=1&per_page=20` with meta.pagination |
| Auth | Bearer token in Authorization header |
| Versioning | URL path (`/v1/`, `/v2/`) |
