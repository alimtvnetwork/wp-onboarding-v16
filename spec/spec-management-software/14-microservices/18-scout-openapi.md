# Scout Service OpenAPI Specification

> **Version:** 1.0.0  
> **Status:** Draft  
> **Last Updated:** 2026-01-30  
> **Service Port:** 8093  
> **Error Code Range:** 5xxx

---

## Cross-References

- [Scout Service](./05-scout-service.md) — Core service specification
- [Gateway OpenAPI](./07-gateway-openapi.md)
- [AI-Bridge OpenAPI](./13-ai-bridge-openapi.md) — Embedding generation
- [SpecManager Service](./03-spec-manager-service.md)

---

## 1. Overview

This document defines the REST API for the Scout search and indexing service. The API enables hybrid search (FTS5 + vector similarity), document indexing, embedding management, and RAG query operations.

### Base URL

```
Production:  http://localhost:8093/api/v1
Via Gateway: http://localhost:8080/api/v1/scout
```

### Authentication

```yaml
securitySchemes:
  BearerAuth:
    type: http
    scheme: bearer
    bearerFormat: JWT
  ApiKeyAuth:
    type: apiKey
    in: header
    name: X-API-Key
```

---

## 2. OpenAPI 3.1.0 Specification

```yaml
openapi: 3.1.0
info:
  title: Scout Search & Indexing API
  description: |
    REST API for full-text search, vector similarity search, and RAG operations.
    Implements hybrid retrieval with FTS5 and vector similarity using weighted
    scoring (0.3 FTS / 0.7 VSS) and MMR reranking.
  version: 1.0.0
  contact:
    name: Scout Team
  license:
    name: Proprietary

servers:
  - url: http://localhost:8093/api/v1
    description: Local development
  - url: http://localhost:8080/api/v1/scout
    description: Via Gateway

tags:
  - name: Search
    description: Search and retrieval operations
  - name: Index
    description: Document indexing operations
  - name: Chunks
    description: Chunk management
  - name: Embeddings
    description: Embedding operations
  - name: Files
    description: File registry management
  - name: Projects
    description: Project search index management
  - name: Models
    description: Embedding model configuration
  - name: Analytics
    description: Search analytics and feedback
  - name: Health
    description: Service health endpoints

paths:
  # ============================================================
  # HEALTH ENDPOINTS
  # ============================================================
  /health:
    get:
      tags: [Health]
      summary: Basic health check
      operationId: getHealth
      responses:
        '200':
          description: Service is healthy
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/HealthResponse'

  /ready:
    get:
      tags: [Health]
      summary: Readiness probe
      operationId: getReadiness
      responses:
        '200':
          description: Service is ready
        '503':
          description: Service not ready (embedding service unavailable)

  /live:
    get:
      tags: [Health]
      summary: Liveness probe
      operationId: getLiveness
      responses:
        '200':
          description: Service is alive

  # ============================================================
  # SEARCH ENDPOINTS
  # ============================================================
  /projects/{projectId}/search:
    post:
      tags: [Search]
      summary: Hybrid search
      description: |
        Performs hybrid search combining FTS5 and vector similarity.
        Uses weighted scoring (configurable, default 0.3 FTS / 0.7 vector)
        with optional MMR reranking for diversity.
      operationId: hybridSearch
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/SearchRequest'
      responses:
        '200':
          description: Search results
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SearchResponse'
        '400':
          $ref: '#/components/responses/BadRequest'
        '404':
          $ref: '#/components/responses/NotFound'

  /projects/{projectId}/search/fts:
    post:
      tags: [Search]
      summary: Full-text search only
      description: Performs FTS5 search without vector similarity
      operationId: ftsSearch
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/FTSSearchRequest'
      responses:
        '200':
          description: Search results
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SearchResponse'

  /projects/{projectId}/search/vector:
    post:
      tags: [Search]
      summary: Vector similarity search only
      description: Performs vector similarity search using embeddings
      operationId: vectorSearch
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/VectorSearchRequest'
      responses:
        '200':
          description: Search results
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SearchResponse'

  /projects/{projectId}/search/similar:
    post:
      tags: [Search]
      summary: Find similar chunks
      description: Find chunks similar to a given chunk ID
      operationId: findSimilar
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [chunkId]
              properties:
                chunkId:
                  type: string
                  format: uuid
                limit:
                  type: integer
                  default: 10
                threshold:
                  type: number
                  format: float
                  minimum: 0
                  maximum: 1
                  default: 0.7
                excludeFileId:
                  type: string
                  format: uuid
                  description: Exclude chunks from this file
      responses:
        '200':
          description: Similar chunks
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SearchResponse'

  /projects/{projectId}/search/rag:
    post:
      tags: [Search]
      summary: RAG query
      description: |
        Retrieval-Augmented Generation query. Retrieves relevant chunks
        and formats them as context for LLM consumption.
      operationId: ragQuery
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RAGRequest'
      responses:
        '200':
          description: RAG context
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RAGResponse'

  /projects/{projectId}/search/multi:
    post:
      tags: [Search]
      summary: Multi-query search
      description: |
        Executes multiple queries and merges results with deduplication.
        Useful for query expansion and HyDE approaches.
      operationId: multiSearch
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [queries]
              properties:
                queries:
                  type: array
                  items:
                    type: string
                  minItems: 1
                  maxItems: 5
                limit:
                  type: integer
                  default: 10
                mergeStrategy:
                  type: string
                  enum: [union, intersection, rrf]
                  default: rrf
                  description: |
                    - union: combine all results
                    - intersection: only results in all queries
                    - rrf: Reciprocal Rank Fusion
      responses:
        '200':
          description: Merged search results
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SearchResponse'

  # ============================================================
  # INDEX ENDPOINTS
  # ============================================================
  /projects/{projectId}/index:
    post:
      tags: [Index]
      summary: Index documents
      description: |
        Index one or more documents. Supports incremental indexing
        with hash-based change detection.
      operationId: indexDocuments
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/IndexRequest'
      responses:
        '202':
          description: Indexing started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/IndexJobResponse'

    get:
      tags: [Index]
      summary: Get index status
      description: Get indexing status and statistics for project
      operationId: getIndexStatus
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      responses:
        '200':
          description: Index status
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/IndexStatus'

    delete:
      tags: [Index]
      summary: Clear index
      description: Remove all indexed content for project
      operationId: clearIndex
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - name: confirm
          in: query
          required: true
          schema:
            type: boolean
          description: Must be true to confirm deletion
      responses:
        '204':
          description: Index cleared

  /projects/{projectId}/index/file:
    post:
      tags: [Index]
      summary: Index single file
      description: Index a single file by path
      operationId: indexFile
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [filePath]
              properties:
                filePath:
                  type: string
                  description: Path to file (relative or absolute)
                forceReindex:
                  type: boolean
                  default: false
                  description: Re-index even if unchanged
                title:
                  type: string
                  description: Optional title override
                metadata:
                  type: object
                  additionalProperties: true
      responses:
        '202':
          description: Indexing started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/IndexJobResponse'

  /projects/{projectId}/index/directory:
    post:
      tags: [Index]
      summary: Index directory
      description: Recursively index all supported files in directory
      operationId: indexDirectory
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [directoryPath]
              properties:
                directoryPath:
                  type: string
                pattern:
                  type: string
                  description: Glob pattern for file matching
                  default: "**/*.md"
                excludePatterns:
                  type: array
                  items:
                    type: string
                  default: ["**/node_modules/**", "**/.git/**"]
                maxDepth:
                  type: integer
                  minimum: 1
                  maximum: 100
                  default: 10
                forceReindex:
                  type: boolean
                  default: false
      responses:
        '202':
          description: Indexing started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/IndexJobResponse'

  /projects/{projectId}/index/jobs/{jobId}:
    get:
      tags: [Index]
      summary: Get job status
      description: Get status of an indexing job
      operationId: getIndexJob
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - name: jobId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Job status
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/IndexJobStatus'
        '404':
          $ref: '#/components/responses/NotFound'

    delete:
      tags: [Index]
      summary: Cancel indexing job
      operationId: cancelIndexJob
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - name: jobId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Job cancelled
        '404':
          $ref: '#/components/responses/NotFound'
        '409':
          description: Job already completed

  /projects/{projectId}/index/sync:
    post:
      tags: [Index]
      summary: Sync index with filesystem
      description: |
        Detect and process file changes:
        - Remove deleted files from index
        - Update modified files
        - Add new files
      operationId: syncIndex
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                dryRun:
                  type: boolean
                  default: false
                  description: Preview changes without applying
      responses:
        '200':
          description: Sync result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SyncResult'

  # ============================================================
  # CHUNK ENDPOINTS
  # ============================================================
  /projects/{projectId}/chunks:
    get:
      tags: [Chunks]
      summary: List chunks
      operationId: listChunks
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
        - name: fileId
          in: query
          schema:
            type: string
            format: uuid
        - name: hasEmbedding
          in: query
          schema:
            type: boolean
      responses:
        '200':
          description: Chunk list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ChunkListResponse'

  /projects/{projectId}/chunks/{chunkId}:
    get:
      tags: [Chunks]
      summary: Get chunk by ID
      operationId: getChunk
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/ChunkIdParam'
        - name: include
          in: query
          schema:
            type: array
            items:
              type: string
              enum: [embedding, context]
          description: Include embedding vector and/or surrounding context
      responses:
        '200':
          description: Chunk details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ChunkDetail'
        '404':
          $ref: '#/components/responses/NotFound'

    delete:
      tags: [Chunks]
      summary: Delete chunk
      operationId: deleteChunk
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/ChunkIdParam'
      responses:
        '204':
          description: Chunk deleted

  /projects/{projectId}/chunks/{chunkId}/context:
    get:
      tags: [Chunks]
      summary: Get chunk with context
      description: Get chunk with surrounding chunks for expanded context
      operationId: getChunkContext
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/ChunkIdParam'
        - name: before
          in: query
          schema:
            type: integer
            minimum: 0
            maximum: 5
            default: 1
          description: Number of chunks before
        - name: after
          in: query
          schema:
            type: integer
            minimum: 0
            maximum: 5
            default: 1
          description: Number of chunks after
      responses:
        '200':
          description: Chunk with context
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ChunkContext'

  # ============================================================
  # EMBEDDING ENDPOINTS
  # ============================================================
  /projects/{projectId}/embeddings:
    post:
      tags: [Embeddings]
      summary: Generate embeddings
      description: Generate embeddings for specified chunks or text
      operationId: generateEmbeddings
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/EmbeddingRequest'
      responses:
        '202':
          description: Embedding generation started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/EmbeddingJobResponse'

    get:
      tags: [Embeddings]
      summary: Get embedding stats
      operationId: getEmbeddingStats
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      responses:
        '200':
          description: Embedding statistics
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/EmbeddingStats'

  /projects/{projectId}/embeddings/batch:
    post:
      tags: [Embeddings]
      summary: Batch embed text
      description: Generate embeddings for multiple text inputs
      operationId: batchEmbed
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [texts]
              properties:
                texts:
                  type: array
                  items:
                    type: string
                  maxItems: 100
                modelId:
                  type: string
      responses:
        '200':
          description: Embeddings generated
          content:
            application/json:
              schema:
                type: object
                properties:
                  embeddings:
                    type: array
                    items:
                      type: array
                      items:
                        type: number
                  model:
                    type: string
                  dimensions:
                    type: integer

  /projects/{projectId}/embeddings/missing:
    get:
      tags: [Embeddings]
      summary: Get chunks without embeddings
      operationId: getMissingEmbeddings
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/LimitParam'
      responses:
        '200':
          description: Chunks without embeddings
          content:
            application/json:
              schema:
                type: object
                properties:
                  count:
                    type: integer
                  chunkIds:
                    type: array
                    items:
                      type: string
                      format: uuid

    post:
      tags: [Embeddings]
      summary: Generate missing embeddings
      description: Generate embeddings for all chunks that don't have them
      operationId: generateMissingEmbeddings
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                batchSize:
                  type: integer
                  default: 50
                  maximum: 100
                modelId:
                  type: string
      responses:
        '202':
          description: Generation started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/EmbeddingJobResponse'

  # ============================================================
  # FILE ENDPOINTS
  # ============================================================
  /projects/{projectId}/files:
    get:
      tags: [Files]
      summary: List indexed files
      operationId: listFiles
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
        - name: status
          in: query
          schema:
            type: string
            enum: [pending, indexing, indexed, error]
        - name: search
          in: query
          schema:
            type: string
          description: Search in file paths
      responses:
        '200':
          description: File list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileListResponse'

  /projects/{projectId}/files/{fileId}:
    get:
      tags: [Files]
      summary: Get file details
      operationId: getFile
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FileIdParam'
      responses:
        '200':
          description: File details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FileDetail'

    delete:
      tags: [Files]
      summary: Remove file from index
      operationId: deleteFile
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FileIdParam'
      responses:
        '204':
          description: File removed from index

  /projects/{projectId}/files/{fileId}/reindex:
    post:
      tags: [Files]
      summary: Reindex file
      operationId: reindexFile
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FileIdParam'
      responses:
        '202':
          description: Reindexing started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/IndexJobResponse'

  # ============================================================
  # PROJECT ENDPOINTS
  # ============================================================
  /projects:
    get:
      tags: [Projects]
      summary: List indexed projects
      operationId: listProjects
      parameters:
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
        - name: status
          in: query
          schema:
            type: string
            enum: [active, indexing, error, disabled]
      responses:
        '200':
          description: Project list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProjectListResponse'

    post:
      tags: [Projects]
      summary: Register project for indexing
      operationId: registerProject
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RegisterProjectRequest'
      responses:
        '201':
          description: Project registered
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Project'
        '409':
          description: Project already registered

  /projects/{projectId}:
    get:
      tags: [Projects]
      summary: Get project details
      operationId: getProject
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      responses:
        '200':
          description: Project details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProjectDetail'

    patch:
      tags: [Projects]
      summary: Update project settings
      operationId: updateProject
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
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
      summary: Unregister project
      description: Remove project from Scout (does not delete source files)
      operationId: unregisterProject
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      responses:
        '204':
          description: Project unregistered

  # ============================================================
  # MODEL ENDPOINTS
  # ============================================================
  /models:
    get:
      tags: [Models]
      summary: List embedding models
      operationId: listModels
      parameters:
        - name: enabled
          in: query
          schema:
            type: boolean
      responses:
        '200':
          description: Model list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ModelListResponse'

    post:
      tags: [Models]
      summary: Register embedding model
      operationId: registerModel
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RegisterModelRequest'
      responses:
        '201':
          description: Model registered
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/EmbeddingModel'

  /models/{modelId}:
    get:
      tags: [Models]
      summary: Get model details
      operationId: getModel
      parameters:
        - name: modelId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Model details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/EmbeddingModel'

    patch:
      tags: [Models]
      summary: Update model
      operationId: updateModel
      parameters:
        - name: modelId
          in: path
          required: true
          schema:
            type: string
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                isDefault:
                  type: boolean
                isEnabled:
                  type: boolean
                config:
                  type: object
      responses:
        '200':
          description: Model updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/EmbeddingModel'

    delete:
      tags: [Models]
      summary: Remove model
      operationId: deleteModel
      parameters:
        - name: modelId
          in: path
          required: true
          schema:
            type: string
      responses:
        '204':
          description: Model removed
        '409':
          description: Cannot delete model with existing embeddings

  /models/{modelId}/test:
    post:
      tags: [Models]
      summary: Test model
      description: Generate a test embedding to verify model connectivity
      operationId: testModel
      parameters:
        - name: modelId
          in: path
          required: true
          schema:
            type: string
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                text:
                  type: string
                  default: "This is a test sentence."
      responses:
        '200':
          description: Test result
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  dimensions:
                    type: integer
                  latencyMs:
                    type: integer
                  error:
                    type: string

  # ============================================================
  # ANALYTICS ENDPOINTS
  # ============================================================
  /projects/{projectId}/analytics/searches:
    get:
      tags: [Analytics]
      summary: Get search history
      operationId: getSearchHistory
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
        - name: startDate
          in: query
          schema:
            type: string
            format: date
        - name: endDate
          in: query
          schema:
            type: string
            format: date
      responses:
        '200':
          description: Search history
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SearchHistoryResponse'

  /projects/{projectId}/analytics/popular:
    get:
      tags: [Analytics]
      summary: Get popular queries
      operationId: getPopularQueries
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - name: days
          in: query
          schema:
            type: integer
            default: 7
      responses:
        '200':
          description: Popular queries
          content:
            application/json:
              schema:
                type: object
                properties:
                  queries:
                    type: array
                    items:
                      type: object
                      properties:
                        query:
                          type: string
                        count:
                          type: integer
                        avgLatencyMs:
                          type: number

  /projects/{projectId}/analytics/feedback:
    post:
      tags: [Analytics]
      summary: Submit relevance feedback
      description: Submit user feedback on search result relevance
      operationId: submitFeedback
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/FeedbackRequest'
      responses:
        '201':
          description: Feedback recorded

components:
  # ============================================================
  # PARAMETERS
  # ============================================================
  parameters:
    ProjectIdParam:
      name: projectId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Project UUID

    ChunkIdParam:
      name: chunkId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Chunk UUID

    FileIdParam:
      name: fileId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: File UUID

    LimitParam:
      name: limit
      in: query
      schema:
        type: integer
        minimum: 1
        maximum: 100
        default: 20

    OffsetParam:
      name: offset
      in: query
      schema:
        type: integer
        minimum: 0
        default: 0

  # ============================================================
  # RESPONSES
  # ============================================================
  responses:
    BadRequest:
      description: Invalid request
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'

    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'

  # ============================================================
  # SCHEMAS
  # ============================================================
  schemas:
    # --- Health ---
    HealthResponse:
      type: object
      properties:
        status:
          type: string
          enum: [healthy, degraded, unhealthy]
        version:
          type: string
        uptime:
          type: integer
        components:
          type: object
          properties:
            database:
              type: string
              enum: [connected, disconnected]
            embeddingService:
              type: string
              enum: [available, unavailable]
            fts:
              type: string
              enum: [enabled, disabled]

    # --- Error ---
    ErrorResponse:
      type: object
      required: [code, message]
      properties:
        code:
          type: integer
          description: Error code (5xxx range)
        message:
          type: string
        details:
          type: object
        traceId:
          type: string
          format: uuid

    # --- Search ---
    SearchRequest:
      type: object
      required: [query]
      properties:
        query:
          type: string
          minLength: 1
          maxLength: 1000
        limit:
          type: integer
          minimum: 1
          maximum: 100
          default: 10
        offset:
          type: integer
          minimum: 0
          default: 0
        threshold:
          type: number
          format: float
          minimum: 0
          maximum: 1
          default: 0.5
          description: Minimum similarity score
        ftsWeight:
          type: number
          format: float
          minimum: 0
          maximum: 1
          default: 0.3
          description: Weight for FTS score (vector weight = 1 - ftsWeight)
        mmr:
          type: object
          description: MMR reranking settings
          properties:
            enabled:
              type: boolean
              default: true
            lambda:
              type: number
              format: float
              minimum: 0
              maximum: 1
              default: 0.5
              description: Diversity vs relevance tradeoff
            fetchK:
              type: integer
              default: 20
              description: Candidates to fetch before reranking
        filters:
          $ref: '#/components/schemas/SearchFilters'
        includeContent:
          type: boolean
          default: true
        includeMetadata:
          type: boolean
          default: true
        highlightMatches:
          type: boolean
          default: true

    FTSSearchRequest:
      type: object
      required: [query]
      properties:
        query:
          type: string
        limit:
          type: integer
          default: 10
        filters:
          $ref: '#/components/schemas/SearchFilters'
        highlightMatches:
          type: boolean
          default: true

    VectorSearchRequest:
      type: object
      properties:
        query:
          type: string
          description: Text to embed and search
        embedding:
          type: array
          items:
            type: number
          description: Pre-computed embedding vector
        limit:
          type: integer
          default: 10
        threshold:
          type: number
          default: 0.5
        filters:
          $ref: '#/components/schemas/SearchFilters'

    SearchFilters:
      type: object
      properties:
        fileIds:
          type: array
          items:
            type: string
            format: uuid
        filePaths:
          type: array
          items:
            type: string
        fileTypes:
          type: array
          items:
            type: string
          description: File extensions (e.g., ["md", "txt"])
        headings:
          type: array
          items:
            type: string
        excludeFileIds:
          type: array
          items:
            type: string
            format: uuid

    SearchResponse:
      type: object
      properties:
        results:
          type: array
          items:
            $ref: '#/components/schemas/SearchResult'
        total:
          type: integer
        query:
          type: string
        method:
          type: string
          enum: [fts, vector, hybrid]
        latencyMs:
          type: integer
        searchId:
          type: string
          format: uuid
          description: ID for feedback tracking

    SearchResult:
      type: object
      properties:
        chunkId:
          type: string
          format: uuid
        fileId:
          type: string
          format: uuid
        filePath:
          type: string
        title:
          type: string
        heading:
          type: string
        content:
          type: string
        contentPreview:
          type: string
        highlights:
          type: array
          items:
            type: string
          description: Highlighted excerpts with <mark> tags
        score:
          type: number
          format: float
        ftsScore:
          type: number
          format: float
        vectorScore:
          type: number
          format: float
        chunkIndex:
          type: integer
        tokenCount:
          type: integer
        startLine:
          type: integer
        endLine:
          type: integer

    # --- RAG ---
    RAGRequest:
      type: object
      required: [query]
      properties:
        query:
          type: string
        limit:
          type: integer
          default: 5
        maxTokens:
          type: integer
          default: 4000
          description: Maximum total tokens in context
        contextFormat:
          type: string
          enum: [markdown, xml, json]
          default: markdown
        includeMetadata:
          type: boolean
          default: true
        expandContext:
          type: boolean
          default: false
          description: Include surrounding chunks
        filters:
          $ref: '#/components/schemas/SearchFilters'

    RAGResponse:
      type: object
      properties:
        context:
          type: string
          description: Formatted context for LLM
        chunks:
          type: array
          items:
            $ref: '#/components/schemas/SearchResult'
        totalTokens:
          type: integer
        query:
          type: string
        searchId:
          type: string
          format: uuid

    # --- Index ---
    IndexRequest:
      type: object
      properties:
        documents:
          type: array
          items:
            type: object
            required: [content]
            properties:
              filePath:
                type: string
              content:
                type: string
              title:
                type: string
              metadata:
                type: object
        options:
          $ref: '#/components/schemas/ChunkOptions'

    ChunkOptions:
      type: object
      properties:
        maxTokens:
          type: integer
          default: 512
        overlapTokens:
          type: integer
          default: 50
        minTokens:
          type: integer
          default: 100
        splitOnHeading:
          type: boolean
          default: true
        preserveCode:
          type: boolean
          default: true

    IndexJobResponse:
      type: object
      properties:
        jobId:
          type: string
          format: uuid
        status:
          type: string
          enum: [queued, processing, completed, failed]
        filesQueued:
          type: integer
        createdAt:
          type: string
          format: date-time

    IndexJobStatus:
      type: object
      properties:
        jobId:
          type: string
          format: uuid
        status:
          type: string
          enum: [queued, processing, completed, failed, cancelled]
        progress:
          type: object
          properties:
            filesTotal:
              type: integer
            filesProcessed:
              type: integer
            chunksCreated:
              type: integer
            embeddingsGenerated:
              type: integer
            errors:
              type: integer
        errors:
          type: array
          items:
            type: object
            properties:
              filePath:
                type: string
              error:
                type: string
        startedAt:
          type: string
          format: date-time
        completedAt:
          type: string
          format: date-time
        durationMs:
          type: integer

    IndexStatus:
      type: object
      properties:
        projectId:
          type: string
          format: uuid
        status:
          type: string
          enum: [active, indexing, error, disabled]
        totalFiles:
          type: integer
        totalChunks:
          type: integer
        totalTokens:
          type: integer
        chunksWithEmbeddings:
          type: integer
        embeddingModel:
          type: string
        embeddingDimensions:
          type: integer
        lastIndexedAt:
          type: string
          format: date-time
        lastSearchAt:
          type: string
          format: date-time

    SyncResult:
      type: object
      properties:
        added:
          type: array
          items:
            type: string
        modified:
          type: array
          items:
            type: string
        deleted:
          type: array
          items:
            type: string
        errors:
          type: array
          items:
            type: object
            properties:
              path:
                type: string
              error:
                type: string
        dryRun:
          type: boolean

    # --- Chunk ---
    Chunk:
      type: object
      properties:
        id:
          type: string
          format: uuid
        fileId:
          type: string
          format: uuid
        chunkIndex:
          type: integer
        content:
          type: string
        contentPreview:
          type: string
        heading:
          type: string
        tokenCount:
          type: integer
        charCount:
          type: integer
        startLine:
          type: integer
        endLine:
          type: integer
        hasEmbedding:
          type: boolean
        createdAt:
          type: string
          format: date-time

    ChunkDetail:
      allOf:
        - $ref: '#/components/schemas/Chunk'
        - type: object
          properties:
            filePath:
              type: string
            title:
              type: string
            embedding:
              type: array
              items:
                type: number
            startOffset:
              type: integer
            endOffset:
              type: integer
            overlapPrev:
              type: integer
            overlapNext:
              type: integer

    ChunkListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Chunk'
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer

    ChunkContext:
      type: object
      properties:
        chunk:
          $ref: '#/components/schemas/ChunkDetail'
        before:
          type: array
          items:
            $ref: '#/components/schemas/Chunk'
        after:
          type: array
          items:
            $ref: '#/components/schemas/Chunk'
        combinedContent:
          type: string

    # --- Embedding ---
    EmbeddingRequest:
      type: object
      properties:
        chunkIds:
          type: array
          items:
            type: string
            format: uuid
          description: Specific chunks to embed
        all:
          type: boolean
          default: false
          description: Embed all chunks without embeddings
        modelId:
          type: string
        batchSize:
          type: integer
          default: 50
          maximum: 100

    EmbeddingJobResponse:
      type: object
      properties:
        jobId:
          type: string
          format: uuid
        status:
          type: string
          enum: [queued, processing, completed, failed]
        chunksQueued:
          type: integer

    EmbeddingStats:
      type: object
      properties:
        totalChunks:
          type: integer
        chunksWithEmbeddings:
          type: integer
        chunksWithoutEmbeddings:
          type: integer
        modelId:
          type: string
        dimensions:
          type: integer
        storageBytes:
          type: integer

    # --- File ---
    File:
      type: object
      properties:
        id:
          type: string
          format: uuid
        filePath:
          type: string
        title:
          type: string
        fileHash:
          type: string
        fileSize:
          type: integer
        mimeType:
          type: string
        chunkCount:
          type: integer
        tokenCount:
          type: integer
        status:
          type: string
          enum: [pending, indexing, indexed, error]
        errorMessage:
          type: string
        indexedAt:
          type: string
          format: date-time

    FileDetail:
      allOf:
        - $ref: '#/components/schemas/File'
        - type: object
          properties:
            chunks:
              type: array
              items:
                $ref: '#/components/schemas/Chunk'
            metadata:
              type: object

    FileListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/File'
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer

    # --- Project ---
    Project:
      type: object
      properties:
        id:
          type: string
          format: uuid
        projectId:
          type: string
          format: uuid
        projectName:
          type: string
        status:
          type: string
          enum: [active, indexing, error, disabled]
        totalChunks:
          type: integer
        totalDocuments:
          type: integer
        totalTokens:
          type: integer
        embeddingModel:
          type: string
        lastIndexedAt:
          type: string
          format: date-time
        lastSearchAt:
          type: string
          format: date-time
        createdAt:
          type: string
          format: date-time

    ProjectDetail:
      allOf:
        - $ref: '#/components/schemas/Project'
        - type: object
          properties:
            indexPath:
              type: string
            embeddingDimensions:
              type: integer
            recentSearches:
              type: integer
            averageLatencyMs:
              type: number

    ProjectListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Project'
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer

    RegisterProjectRequest:
      type: object
      required: [projectId, projectName]
      properties:
        projectId:
          type: string
          format: uuid
        projectName:
          type: string
        indexPath:
          type: string
          description: Custom path for search.db (optional)
        embeddingModelId:
          type: string
          description: Override default embedding model

    UpdateProjectRequest:
      type: object
      properties:
        status:
          type: string
          enum: [active, disabled]
        embeddingModelId:
          type: string

    # --- Model ---
    EmbeddingModel:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
        provider:
          type: string
          enum: [openai, ollama, local, custom]
        modelId:
          type: string
        dimensions:
          type: integer
        maxTokens:
          type: integer
        isDefault:
          type: boolean
        isEnabled:
          type: boolean
        config:
          type: object
        createdAt:
          type: string
          format: date-time

    ModelListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/EmbeddingModel'

    RegisterModelRequest:
      type: object
      required: [name, provider, modelId, dimensions]
      properties:
        name:
          type: string
        provider:
          type: string
          enum: [openai, ollama, local, custom]
        modelId:
          type: string
        dimensions:
          type: integer
        maxTokens:
          type: integer
        isDefault:
          type: boolean
          default: false
        config:
          type: object
          description: Provider-specific configuration

    # --- Analytics ---
    SearchHistoryResponse:
      type: object
      properties:
        items:
          type: array
          items:
            type: object
            properties:
              id:
                type: string
                format: uuid
              query:
                type: string
              method:
                type: string
              resultCount:
                type: integer
              latencyMs:
                type: integer
              createdAt:
                type: string
                format: date-time
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer

    FeedbackRequest:
      type: object
      required: [searchId, chunkId, relevance]
      properties:
        searchId:
          type: string
          format: uuid
        chunkId:
          type: string
          format: uuid
        relevance:
          type: integer
          enum: [-1, 0, 1]
          description: "-1: not relevant, 0: neutral, 1: relevant"
        position:
          type: integer
          description: Position in result list
        dwellTimeMs:
          type: integer
          description: Time spent viewing result
```

---

## 3. Error Codes

| Code | Name | Description |
|------|------|-------------|
| **5000** | SCOUT_UNKNOWN | Unknown Scout error |
| **5001** | PROJECT_NOT_FOUND | Project not registered in Scout |
| **5002** | PROJECT_ALREADY_EXISTS | Project already registered |
| **5003** | PROJECT_DISABLED | Project indexing is disabled |
| **5010** | FILE_NOT_FOUND | File not in index |
| **5011** | FILE_READ_ERROR | Cannot read file |
| **5012** | FILE_PARSE_ERROR | Cannot parse file content |
| **5013** | FILE_TOO_LARGE | File exceeds size limit |
| **5020** | CHUNK_NOT_FOUND | Chunk does not exist |
| **5021** | CHUNK_CREATE_ERROR | Failed to create chunk |
| **5030** | EMBEDDING_NOT_FOUND | Embedding not found for chunk |
| **5031** | EMBEDDING_GENERATION_FAILED | Failed to generate embedding |
| **5032** | EMBEDDING_MODEL_UNAVAILABLE | Embedding model not available |
| **5033** | EMBEDDING_DIMENSION_MISMATCH | Embedding dimensions don't match |
| **5040** | SEARCH_QUERY_EMPTY | Search query is empty |
| **5041** | SEARCH_QUERY_TOO_LONG | Search query exceeds limit |
| **5042** | SEARCH_TIMEOUT | Search operation timed out |
| **5043** | FTS_ERROR | FTS5 query error |
| **5044** | VECTOR_SEARCH_ERROR | Vector search error |
| **5050** | INDEX_JOB_NOT_FOUND | Indexing job not found |
| **5051** | INDEX_JOB_FAILED | Indexing job failed |
| **5052** | INDEX_ALREADY_RUNNING | Indexing already in progress |
| **5060** | MODEL_NOT_FOUND | Embedding model not found |
| **5061** | MODEL_ALREADY_EXISTS | Model already registered |
| **5062** | MODEL_CONFIG_INVALID | Invalid model configuration |
| **5063** | MODEL_TEST_FAILED | Model connectivity test failed |
| **5070** | DATABASE_ERROR | Database operation failed |
| **5071** | DATABASE_LOCKED | Database is locked |

---

## 4. Rate Limits

| Endpoint Category | Rate Limit | Window |
|-------------------|------------|--------|
| Search operations | 500 req | 1 minute |
| Index operations | 50 req | 1 minute |
| Embedding generation | 100 req | 1 minute |
| Read operations | 1000 req | 1 minute |

---

## 5. Scoring Formula

### Hybrid Score Calculation

```
hybrid_score = (fts_weight × fts_score) + ((1 - fts_weight) × vector_score)

Default: fts_weight = 0.3
Result:  hybrid_score = (0.3 × fts_score) + (0.7 × vector_score)
```

### MMR Reranking

```
MMR(d) = λ × similarity(d, q) - (1 - λ) × max(similarity(d, d_selected))

Where:
- d = candidate document
- q = query
- d_selected = already selected documents
- λ = diversity parameter (default 0.5)
```

---

## Appendix A: Supported File Types

| Extension | MIME Type | Notes |
|-----------|-----------|-------|
| `.md` | text/markdown | Markdown with heading detection |
| `.txt` | text/plain | Plain text |
| `.json` | application/json | Structured data |
| `.yaml`, `.yml` | application/yaml | Configuration files |
| `.html` | text/html | HTML with tag stripping |
| `.rst` | text/x-rst | reStructuredText |
| `.adoc` | text/asciidoc | AsciiDoc |

---

## Appendix B: FTS5 Query Syntax

Scout supports FTS5 query syntax:

```
# Simple term
query: "typescript"

# Phrase
query: "\"react hooks\""

# OR
query: "react OR vue"

# NOT
query: "javascript NOT typescript"

# Prefix
query: "type*"

# Column filter
query: "title:typescript"

# Proximity
query: "NEAR(react hooks, 5)"
```
