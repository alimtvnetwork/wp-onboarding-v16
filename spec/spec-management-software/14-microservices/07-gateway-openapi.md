# 07. Gateway OpenAPI Specification

## Overview
OpenAPI 3.1 specification for the Main Gateway service, documenting all REST endpoints across microservices with unified authentication, error handling, and rate limiting.

**Base URL**: `http://localhost:8080/api/v1`  
**Error Code Range**: 2xxx (Gateway-specific errors)

---

## 7.1 OpenAPI Document

```yaml
openapi: 3.1.0
info:
  title: SpecBuilder Pro API
  description: |
    Unified API gateway for SpecBuilder Pro microservices architecture.
    All requests are routed through this gateway with consistent authentication,
    error handling, and rate limiting.
  version: 1.0.0
  contact:
    name: API Support
  license:
    name: MIT

servers:
  - url: http://localhost:8080/api/v1
    description: Local development
  - url: http://localhost:8080/api/v1
    description: Production

tags:
  - name: Projects
    description: Project management (SpecManager)
  - name: Specs
    description: Specification CRUD (SpecManager)
  - name: Folders
    description: Folder organization (SpecManager)
  - name: History
    description: Version control (Chronicle)
  - name: Search
    description: Full-text and vector search (Scout)
  - name: Conversations
    description: AI conversation management (AI-Bridge)
  - name: System
    description: Gateway health and diagnostics
```

---

## 7.2 Authentication

```yaml
components:
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: JWT token for authenticated requests
    
    ApiKeyAuth:
      type: apiKey
      in: header
      name: X-API-Key
      description: API key for service-to-service communication

security:
  - BearerAuth: []
  - ApiKeyAuth: []
```

---

## 7.3 Common Components

```yaml
components:
  schemas:
    # Standard error response
    Error:
      type: object
      required: [code, message]
      properties:
        code:
          type: integer
          description: Numeric error code (service-specific range)
          example: 3001
        message:
          type: string
          description: Human-readable error message
          example: "Project not found"
        details:
          type: object
          description: Additional error context
        requestId:
          type: string
          format: uuid
          description: Request tracking ID
        timestamp:
          type: string
          format: date-time

    # Pagination wrapper
    PaginatedResponse:
      type: object
      required: [data, pagination]
      properties:
        data:
          type: array
          items: {}
        pagination:
          $ref: '#/components/schemas/Pagination'

    Pagination:
      type: object
      required: [page, pageSize, total, totalPages]
      properties:
        page:
          type: integer
          minimum: 1
          example: 1
        pageSize:
          type: integer
          minimum: 1
          maximum: 100
          example: 20
        total:
          type: integer
          example: 150
        totalPages:
          type: integer
          example: 8

    # Common timestamp fields
    Timestamps:
      type: object
      properties:
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time

  parameters:
    ProjectId:
      name: projectId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Project unique identifier

    SpecId:
      name: specId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Specification unique identifier

    Page:
      name: page
      in: query
      schema:
        type: integer
        minimum: 1
        default: 1
      description: Page number

    PageSize:
      name: pageSize
      in: query
      schema:
        type: integer
        minimum: 1
        maximum: 100
        default: 20
      description: Items per page

  responses:
    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: 3001
            message: "Project not found"
            requestId: "550e8400-e29b-41d4-a716-446655440000"

    BadRequest:
      description: Invalid request parameters
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'

    Unauthorized:
      description: Authentication required
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: 2001
            message: "Authentication required"

    RateLimited:
      description: Rate limit exceeded
      headers:
        X-RateLimit-Limit:
          schema:
            type: integer
          description: Request limit per window
        X-RateLimit-Remaining:
          schema:
            type: integer
          description: Remaining requests in window
        X-RateLimit-Reset:
          schema:
            type: integer
          description: Unix timestamp when limit resets
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: 2010
            message: "Rate limit exceeded"
```

---

## 7.4 System Endpoints

```yaml
paths:
  /health:
    get:
      tags: [System]
      summary: Health check
      description: Returns gateway and downstream service health status
      security: []
      responses:
        '200':
          description: System healthy
          content:
            application/json:
              schema:
                type: object
                properties:
                  status:
                    type: string
                    enum: [healthy, degraded, unhealthy]
                  version:
                    type: string
                  uptime:
                    type: integer
                    description: Uptime in seconds
                  services:
                    type: object
                    additionalProperties:
                      type: object
                      properties:
                        status:
                          type: string
                          enum: [up, down, degraded]
                        latencyMs:
                          type: integer
              example:
                status: healthy
                version: "1.0.0"
                uptime: 86400
                services:
                  specmanager:
                    status: up
                    latencyMs: 5
                  chronicle:
                    status: up
                    latencyMs: 3
                  scout:
                    status: up
                    latencyMs: 8
                  ai-bridge:
                    status: up
                    latencyMs: 12

  /metrics:
    get:
      tags: [System]
      summary: Prometheus metrics
      description: Exposes metrics in Prometheus format
      security:
        - ApiKeyAuth: []
      responses:
        '200':
          description: Metrics in Prometheus format
          content:
            text/plain:
              schema:
                type: string
```

---

## 7.5 Project Endpoints (SpecManager)

```yaml
paths:
  /projects:
    get:
      tags: [Projects]
      summary: List all projects
      parameters:
        - $ref: '#/components/parameters/Page'
        - $ref: '#/components/parameters/PageSize'
        - name: status
          in: query
          schema:
            type: string
            enum: [active, archived, deleted]
            default: active
        - name: search
          in: query
          schema:
            type: string
          description: Search by name or description
      responses:
        '200':
          description: Projects list
          content:
            application/json:
              schema:
                allOf:
                  - $ref: '#/components/schemas/PaginatedResponse'
                  - type: object
                    properties:
                      data:
                        type: array
                        items:
                          $ref: '#/components/schemas/ProjectSummary'

    post:
      tags: [Projects]
      summary: Create new project
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateProjectRequest'
      responses:
        '201':
          description: Project created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Project'
        '400':
          $ref: '#/components/responses/BadRequest'

  /projects/{projectId}:
    get:
      tags: [Projects]
      summary: Get project details
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Project details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Project'
        '404':
          $ref: '#/components/responses/NotFound'

    patch:
      tags: [Projects]
      summary: Update project
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateProjectRequest'
      responses:
        '200':
          description: Project updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Project'

    delete:
      tags: [Projects]
      summary: Delete project (soft delete)
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '204':
          description: Project deleted

  /projects/{projectId}/sync:
    post:
      tags: [Projects]
      summary: Sync project from filesystem
      description: Reconcile database with filesystem changes
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Sync completed
          content:
            application/json:
              schema:
                type: object
                properties:
                  added:
                    type: integer
                  modified:
                    type: integer
                  deleted:
                    type: integer
                  errors:
                    type: array
                    items:
                      type: string

components:
  schemas:
    ProjectSummary:
      type: object
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
        slug:
          type: string
        description:
          type: string
        status:
          type: string
          enum: [active, archived, deleted]
        specCount:
          type: integer
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time

    Project:
      allOf:
        - $ref: '#/components/schemas/ProjectSummary'
        - type: object
          properties:
            rootPath:
              type: string
            folders:
              type: array
              items:
                $ref: '#/components/schemas/FolderSummary'
            tags:
              type: array
              items:
                $ref: '#/components/schemas/Tag'

    CreateProjectRequest:
      type: object
      required: [name]
      properties:
        name:
          type: string
          minLength: 1
          maxLength: 100
        description:
          type: string
          maxLength: 500
        rootPath:
          type: string
          description: Optional custom root path

    UpdateProjectRequest:
      type: object
      properties:
        name:
          type: string
        description:
          type: string
        status:
          type: string
          enum: [active, archived]
```

---

## 7.6 Specification Endpoints (SpecManager)

```yaml
paths:
  /projects/{projectId}/specs:
    get:
      tags: [Specs]
      summary: List specifications
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/Page'
        - $ref: '#/components/parameters/PageSize'
        - name: folderId
          in: query
          schema:
            type: string
            format: uuid
          description: Filter by folder
        - name: status
          in: query
          schema:
            type: string
            enum: [draft, review, approved, archived]
        - name: tag
          in: query
          schema:
            type: string
          description: Filter by tag name
      responses:
        '200':
          description: Specifications list
          content:
            application/json:
              schema:
                allOf:
                  - $ref: '#/components/schemas/PaginatedResponse'
                  - type: object
                    properties:
                      data:
                        type: array
                        items:
                          $ref: '#/components/schemas/SpecSummary'

    post:
      tags: [Specs]
      summary: Create specification
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateSpecRequest'
      responses:
        '201':
          description: Specification created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Spec'

  /projects/{projectId}/specs/{specId}:
    get:
      tags: [Specs]
      summary: Get specification
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
        - name: includeContent
          in: query
          schema:
            type: boolean
            default: false
          description: Include file content in response
      responses:
        '200':
          description: Specification details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Spec'

    put:
      tags: [Specs]
      summary: Update specification
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateSpecRequest'
      responses:
        '200':
          description: Specification updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Spec'

    delete:
      tags: [Specs]
      summary: Delete specification
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
        - name: permanent
          in: query
          schema:
            type: boolean
            default: false
          description: Permanently delete (bypass trash)
      responses:
        '204':
          description: Specification deleted

  /projects/{projectId}/specs/{specId}/content:
    get:
      tags: [Specs]
      summary: Get specification content
      description: Returns raw markdown content
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      responses:
        '200':
          description: Markdown content
          content:
            text/markdown:
              schema:
                type: string

    put:
      tags: [Specs]
      summary: Update specification content
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
        - name: commitMessage
          in: query
          schema:
            type: string
          description: Optional commit message
      requestBody:
        required: true
        content:
          text/markdown:
            schema:
              type: string
      responses:
        '200':
          description: Content updated
          content:
            application/json:
              schema:
                type: object
                properties:
                  fileHash:
                    type: string
                  commitId:
                    type: string
                    format: uuid

  /projects/{projectId}/specs/{specId}/references:
    get:
      tags: [Specs]
      summary: Get cross-references
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
      responses:
        '200':
          description: Cross-references
          content:
            application/json:
              schema:
                type: object
                properties:
                  outgoing:
                    type: array
                    items:
                      $ref: '#/components/schemas/CrossReference'
                  incoming:
                    type: array
                    items:
                      $ref: '#/components/schemas/CrossReference'

components:
  schemas:
    SpecSummary:
      type: object
      properties:
        id:
          type: string
          format: uuid
        title:
          type: string
        filePath:
          type: string
        status:
          type: string
          enum: [draft, review, approved, archived]
        folderId:
          type: string
          format: uuid
        tags:
          type: array
          items:
            type: string
        updatedAt:
          type: string
          format: date-time

    Spec:
      allOf:
        - $ref: '#/components/schemas/SpecSummary'
        - type: object
          properties:
            fileHash:
              type: string
            fileSize:
              type: integer
            content:
              type: string
              description: Present only if includeContent=true
            metadata:
              type: object
              description: Parsed frontmatter
            createdAt:
              type: string
              format: date-time

    CreateSpecRequest:
      type: object
      required: [title]
      properties:
        title:
          type: string
        folderId:
          type: string
          format: uuid
        content:
          type: string
        tags:
          type: array
          items:
            type: string

    UpdateSpecRequest:
      type: object
      properties:
        title:
          type: string
        folderId:
          type: string
          format: uuid
        status:
          type: string
          enum: [draft, review, approved, archived]
        tags:
          type: array
          items:
            type: string

    CrossReference:
      type: object
      properties:
        specId:
          type: string
          format: uuid
        specTitle:
          type: string
        linkText:
          type: string
        lineNumber:
          type: integer
        isValid:
          type: boolean
```

---

## 7.7 Folder Endpoints (SpecManager)

```yaml
paths:
  /projects/{projectId}/folders:
    get:
      tags: [Folders]
      summary: List folders (tree structure)
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Folder tree
          content:
            application/json:
              schema:
                type: array
                items:
                  $ref: '#/components/schemas/FolderTree'

    post:
      tags: [Folders]
      summary: Create folder
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateFolderRequest'
      responses:
        '201':
          description: Folder created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Folder'

  /projects/{projectId}/folders/{folderId}:
    patch:
      tags: [Folders]
      summary: Update folder
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: folderId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateFolderRequest'
      responses:
        '200':
          description: Folder updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Folder'

    delete:
      tags: [Folders]
      summary: Delete folder
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: folderId
          in: path
          required: true
          schema:
            type: string
            format: uuid
        - name: cascade
          in: query
          schema:
            type: boolean
            default: false
          description: Delete contained specs
      responses:
        '204':
          description: Folder deleted

components:
  schemas:
    FolderSummary:
      type: object
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
        specCount:
          type: integer

    Folder:
      allOf:
        - $ref: '#/components/schemas/FolderSummary'
        - type: object
          properties:
            parentId:
              type: string
              format: uuid
            sortOrder:
              type: integer
            createdAt:
              type: string
              format: date-time
            updatedAt:
              type: string
              format: date-time

    FolderTree:
      allOf:
        - $ref: '#/components/schemas/Folder'
        - type: object
          properties:
            children:
              type: array
              items:
                $ref: '#/components/schemas/FolderTree'

    CreateFolderRequest:
      type: object
      required: [name]
      properties:
        name:
          type: string
        parentId:
          type: string
          format: uuid

    UpdateFolderRequest:
      type: object
      properties:
        name:
          type: string
        parentId:
          type: string
          format: uuid
        sortOrder:
          type: integer
```

---

## 7.8 History Endpoints (Chronicle)

```yaml
paths:
  /projects/{projectId}/history:
    get:
      tags: [History]
      summary: Get commit history
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/Page'
        - $ref: '#/components/parameters/PageSize'
        - name: specId
          in: query
          schema:
            type: string
            format: uuid
          description: Filter by specification
        - name: author
          in: query
          schema:
            type: string
        - name: since
          in: query
          schema:
            type: string
            format: date-time
        - name: until
          in: query
          schema:
            type: string
            format: date-time
      responses:
        '200':
          description: Commit history
          content:
            application/json:
              schema:
                allOf:
                  - $ref: '#/components/schemas/PaginatedResponse'
                  - type: object
                    properties:
                      data:
                        type: array
                        items:
                          $ref: '#/components/schemas/Commit'

  /projects/{projectId}/history/{commitId}:
    get:
      tags: [History]
      summary: Get commit details
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: commitId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Commit details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CommitDetails'

  /projects/{projectId}/history/{commitId}/diff:
    get:
      tags: [History]
      summary: Get commit diff
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: commitId
          in: path
          required: true
          schema:
            type: string
            format: uuid
        - name: filePath
          in: query
          schema:
            type: string
          description: Filter to specific file
      responses:
        '200':
          description: Unified diff output
          content:
            text/plain:
              schema:
                type: string
            application/json:
              schema:
                type: array
                items:
                  $ref: '#/components/schemas/FileDiff'

  /projects/{projectId}/history/rollback:
    post:
      tags: [History]
      summary: Rollback to commit
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [commitId]
              properties:
                commitId:
                  type: string
                  format: uuid
                reason:
                  type: string
      responses:
        '200':
          description: Rollback completed
          content:
            application/json:
              schema:
                type: object
                properties:
                  rollbackId:
                    type: string
                    format: uuid
                  restoredFiles:
                    type: array
                    items:
                      type: string
                  newCommitId:
                    type: string
                    format: uuid

  /projects/{projectId}/specs/{specId}/history:
    get:
      tags: [History]
      summary: Get file version history
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
        - $ref: '#/components/parameters/Page'
        - $ref: '#/components/parameters/PageSize'
      responses:
        '200':
          description: File versions
          content:
            application/json:
              schema:
                allOf:
                  - $ref: '#/components/schemas/PaginatedResponse'
                  - type: object
                    properties:
                      data:
                        type: array
                        items:
                          $ref: '#/components/schemas/FileVersion'

  /projects/{projectId}/specs/{specId}/history/{versionId}:
    get:
      tags: [History]
      summary: Get file version content
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/SpecId'
        - name: versionId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Version content
          content:
            text/markdown:
              schema:
                type: string

components:
  schemas:
    Commit:
      type: object
      properties:
        id:
          type: string
          format: uuid
        parentId:
          type: string
          format: uuid
        message:
          type: string
        author:
          type: string
        timestamp:
          type: string
          format: date-time
        isAutoCommit:
          type: boolean
        fileCount:
          type: integer

    CommitDetails:
      allOf:
        - $ref: '#/components/schemas/Commit'
        - type: object
          properties:
            files:
              type: array
              items:
                $ref: '#/components/schemas/FileVersion'
            metadata:
              type: object

    FileVersion:
      type: object
      properties:
        id:
          type: string
          format: uuid
        filePath:
          type: string
        operation:
          type: string
          enum: [create, modify, delete, rename]
        oldPath:
          type: string
          description: For rename operations
        contentHash:
          type: string
        addedLines:
          type: integer
        removedLines:
          type: integer
        createdAt:
          type: string
          format: date-time

    FileDiff:
      type: object
      properties:
        filePath:
          type: string
        operation:
          type: string
        hunks:
          type: array
          items:
            type: object
            properties:
              oldStart:
                type: integer
              oldCount:
                type: integer
              newStart:
                type: integer
              newCount:
                type: integer
              lines:
                type: array
                items:
                  type: string
```

---

## 7.9 Search Endpoints (Scout)

```yaml
paths:
  /projects/{projectId}/search:
    get:
      tags: [Search]
      summary: Hybrid search
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: q
          in: query
          required: true
          schema:
            type: string
          description: Search query
        - name: type
          in: query
          schema:
            type: string
            enum: [fts, vector, hybrid]
            default: hybrid
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 50
            default: 10
        - name: ftsWeight
          in: query
          schema:
            type: number
            minimum: 0
            maximum: 1
            default: 0.3
          description: FTS weight (hybrid mode)
        - name: mmrLambda
          in: query
          schema:
            type: number
            minimum: 0
            maximum: 1
            default: 0.5
          description: MMR diversity factor
      responses:
        '200':
          description: Search results
          content:
            application/json:
              schema:
                type: object
                properties:
                  query:
                    type: string
                  searchType:
                    type: string
                  durationMs:
                    type: integer
                  results:
                    type: array
                    items:
                      $ref: '#/components/schemas/SearchResult'

  /projects/{projectId}/search/index:
    post:
      tags: [Search]
      summary: Reindex project
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: force
          in: query
          schema:
            type: boolean
            default: false
          description: Force full reindex
      responses:
        '202':
          description: Indexing started
          content:
            application/json:
              schema:
                type: object
                properties:
                  jobId:
                    type: string
                    format: uuid
                  status:
                    type: string
                    enum: [queued, indexing]

    get:
      tags: [Search]
      summary: Get index status
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      responses:
        '200':
          description: Index status
          content:
            application/json:
              schema:
                type: object
                properties:
                  status:
                    type: string
                    enum: [pending, indexing, ready, error]
                  chunkCount:
                    type: integer
                  lastIndexedAt:
                    type: string
                    format: date-time
                  embeddingModel:
                    type: string
                  progress:
                    type: number
                    description: 0-100 if indexing

  /search/embed:
    post:
      tags: [Search]
      summary: Generate embeddings
      description: Generate vector embeddings for text
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [text]
              properties:
                text:
                  type: string
                model:
                  type: string
                  description: Embedding model ID
      responses:
        '200':
          description: Embeddings generated
          content:
            application/json:
              schema:
                type: object
                properties:
                  embedding:
                    type: array
                    items:
                      type: number
                  dimensions:
                    type: integer
                  model:
                    type: string
                  tokenCount:
                    type: integer

components:
  schemas:
    SearchResult:
      type: object
      properties:
        chunkId:
          type: string
        specId:
          type: string
          format: uuid
        specTitle:
          type: string
        filePath:
          type: string
        content:
          type: string
        headings:
          type: array
          items:
            type: string
        score:
          type: number
        ftsScore:
          type: number
        vectorScore:
          type: number
        startLine:
          type: integer
        endLine:
          type: integer
        highlights:
          type: array
          items:
            type: string
          description: Matched text fragments
```

---

## 7.10 Conversation Endpoints (AI-Bridge)

```yaml
paths:
  /projects/{projectId}/conversations:
    get:
      tags: [Conversations]
      summary: List conversations
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - $ref: '#/components/parameters/Page'
        - $ref: '#/components/parameters/PageSize'
      responses:
        '200':
          description: Conversations list
          content:
            application/json:
              schema:
                allOf:
                  - $ref: '#/components/schemas/PaginatedResponse'
                  - type: object
                    properties:
                      data:
                        type: array
                        items:
                          $ref: '#/components/schemas/ConversationSummary'

    post:
      tags: [Conversations]
      summary: Create conversation
      parameters:
        - $ref: '#/components/parameters/ProjectId'
      requestBody:
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateConversationRequest'
      responses:
        '201':
          description: Conversation created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Conversation'

  /projects/{projectId}/conversations/{conversationId}:
    get:
      tags: [Conversations]
      summary: Get conversation
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: conversationId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Conversation details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Conversation'

    delete:
      tags: [Conversations]
      summary: Delete conversation
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: conversationId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '204':
          description: Conversation deleted

  /projects/{projectId}/conversations/{conversationId}/messages:
    get:
      tags: [Conversations]
      summary: Get messages
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: conversationId
          in: path
          required: true
          schema:
            type: string
            format: uuid
        - $ref: '#/components/parameters/Page'
        - $ref: '#/components/parameters/PageSize'
      responses:
        '200':
          description: Messages list
          content:
            application/json:
              schema:
                allOf:
                  - $ref: '#/components/schemas/PaginatedResponse'
                  - type: object
                    properties:
                      data:
                        type: array
                        items:
                          $ref: '#/components/schemas/Message'

    post:
      tags: [Conversations]
      summary: Send message
      description: Send user message and receive AI response via SSE
      parameters:
        - $ref: '#/components/parameters/ProjectId'
        - name: conversationId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/SendMessageRequest'
      responses:
        '200':
          description: SSE stream of AI response
          content:
            text/event-stream:
              schema:
                type: string
                description: |
                  Server-sent events with message chunks:
                  - event: token, data: {"content": "..."}
                  - event: context, data: {"chunks": [...]}
                  - event: done, data: {"messageId": "...", "tokenCount": 123}
                  - event: error, data: {"code": 6001, "message": "..."}

components:
  schemas:
    ConversationSummary:
      type: object
      properties:
        id:
          type: string
          format: uuid
        title:
          type: string
        messageCount:
          type: integer
        model:
          type: string
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time

    Conversation:
      allOf:
        - $ref: '#/components/schemas/ConversationSummary'
        - type: object
          properties:
            systemPrompt:
              type: string
            temperature:
              type: number
            maxTokens:
              type: integer

    CreateConversationRequest:
      type: object
      properties:
        title:
          type: string
        model:
          type: string
        systemPrompt:
          type: string
        temperature:
          type: number
          minimum: 0
          maximum: 2
        maxTokens:
          type: integer

    Message:
      type: object
      properties:
        id:
          type: string
          format: uuid
        role:
          type: string
          enum: [user, assistant, system]
        content:
          type: string
        tokenCount:
          type: integer
        ragContext:
          type: array
          items:
            $ref: '#/components/schemas/RAGContext'
        createdAt:
          type: string
          format: date-time

    SendMessageRequest:
      type: object
      required: [content]
      properties:
        content:
          type: string
        useRAG:
          type: boolean
          default: true
        ragLimit:
          type: integer
          minimum: 1
          maximum: 20
          default: 5

    RAGContext:
      type: object
      properties:
        chunkId:
          type: string
        filePath:
          type: string
        content:
          type: string
        score:
          type: number
        searchType:
          type: string
          enum: [fts, vector, hybrid]

    Tag:
      type: object
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
        color:
          type: string
          pattern: '^#[0-9a-fA-F]{6}$'
```

---

## 7.11 Rate Limiting

```yaml
x-rate-limits:
  default:
    requests: 100
    window: 60
    description: 100 requests per minute
  
  search:
    requests: 30
    window: 60
    description: 30 search requests per minute
  
  ai:
    requests: 20
    window: 60
    description: 20 AI requests per minute
  
  indexing:
    requests: 5
    window: 300
    description: 5 reindex requests per 5 minutes
```

---

## 7.12 Error Codes Summary

| Range | Service | Examples |
|-------|---------|----------|
| 2xxx | Gateway | 2001 Auth required, 2010 Rate limited |
| 3xxx | SpecManager | 3001 Project not found, 3010 Path validation failed |
| 4xxx | Chronicle | 4001 Commit not found, 4010 Rollback failed |
| 5xxx | Scout | 5001 Index not ready, 5010 Embedding failed |
| 6xxx | AI-Bridge | 6001 Model unavailable, 6010 Context limit exceeded |

---

## 7.13 Acceptance Criteria

- [ ] All endpoints documented with request/response schemas
- [ ] Authentication schemes defined (JWT + API Key)
- [ ] Pagination standardized across all list endpoints
- [ ] Error responses follow consistent format
- [ ] Rate limits documented per endpoint category
- [ ] SSE streaming documented for AI responses
- [ ] OpenAPI spec validates against 3.1 schema
