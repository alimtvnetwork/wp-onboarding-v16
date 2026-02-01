# AI Bridge CLI - OpenAPI Specification

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-30  

---

## Overview

Complete OpenAPI 3.0.3 specification for the AI Bridge CLI HTTP API, covering all endpoints for application management, inference, memory operations, and model management.

**Cross-References:**
- [AI Bridge CLI](./12-ai-bridge-cli.md)
- [Voice CLI OpenAPI](./11-voice-cli-openapi.md)
- [Error Management](../../06-error-management/00-overview.md)

---

## OpenAPI Specification

```yaml
openapi: 3.0.3
info:
  title: AI Bridge CLI API
  description: |
    Centralized AI/LLM management service for the Spec Management ecosystem.
    Provides unified interface for model management, inference, and orchestration.
  version: 1.0.0
  contact:
    name: AI Bridge Support
  license:
    name: MIT

servers:
  - url: http://localhost:8090/api/v1
    description: Local development server
  - url: http://{host}:{port}/api/v1
    description: Custom server
    variables:
      host:
        default: localhost
      port:
        default: "8090"

tags:
  - name: Health
    description: Service health and status
  - name: Applications
    description: Client application management
  - name: Projects
    description: Project management within applications
  - name: Conversations
    description: Chat conversation management
  - name: Messages
    description: Message operations
  - name: Inference
    description: AI inference operations
  - name: Memory
    description: RAG memory management
  - name: Models
    description: Model management
  - name: Providers
    description: LLM provider management
  - name: System
    description: System configuration

security:
  - BearerAuth: []
  - ApiKeyAuth: []

paths:
  # ============================================================================
  # Health Endpoints
  # ============================================================================
  /health:
    get:
      tags: [Health]
      summary: Full health check
      operationId: getHealth
      security: []
      responses:
        "200":
          description: Service health status
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/HealthResponse"

  /health/ready:
    get:
      tags: [Health]
      summary: Readiness probe
      operationId: getReadiness
      security: []
      responses:
        "200":
          description: Service is ready
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ReadinessResponse"
        "503":
          description: Service not ready
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ReadinessResponse"

  /health/live:
    get:
      tags: [Health]
      summary: Liveness probe
      operationId: getLiveness
      security: []
      responses:
        "200":
          description: Service is alive
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/LivenessResponse"

  # ============================================================================
  # Application Endpoints
  # ============================================================================
  /apps:
    post:
      tags: [Applications]
      summary: Register new application
      operationId: registerApplication
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/RegisterAppRequest"
      responses:
        "201":
          description: Application registered
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/AppResponse"
        "400":
          $ref: "#/components/responses/BadRequest"
        "409":
          description: Application already exists
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ErrorResponse"

    get:
      tags: [Applications]
      summary: List registered applications
      operationId: listApplications
      parameters:
        - name: activeOnly
          in: query
          schema:
            type: boolean
            default: true
        - name: limit
          in: query
          schema:
            type: integer
            default: 50
            maximum: 100
        - name: offset
          in: query
          schema:
            type: integer
            default: 0
      responses:
        "200":
          description: List of applications
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/AppListResponse"

  /apps/{appId}:
    parameters:
      - $ref: "#/components/parameters/AppId"

    get:
      tags: [Applications]
      summary: Get application details
      operationId: getApplication
      responses:
        "200":
          description: Application details
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/AppResponse"
        "404":
          $ref: "#/components/responses/NotFound"

    patch:
      tags: [Applications]
      summary: Update application
      operationId: updateApplication
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/UpdateAppRequest"
      responses:
        "200":
          description: Application updated
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/AppResponse"
        "404":
          $ref: "#/components/responses/NotFound"

    delete:
      tags: [Applications]
      summary: Remove application
      operationId: deleteApplication
      parameters:
        - name: force
          in: query
          description: Force deletion including all projects
          schema:
            type: boolean
            default: false
      responses:
        "204":
          description: Application deleted
        "404":
          $ref: "#/components/responses/NotFound"
        "409":
          description: Application has active projects

  /apps/{appId}/stats:
    parameters:
      - $ref: "#/components/parameters/AppId"
    get:
      tags: [Applications]
      summary: Get application usage statistics
      operationId: getAppStats
      parameters:
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
        "200":
          description: Usage statistics
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/AppStatsResponse"

  # ============================================================================
  # Project Endpoints
  # ============================================================================
  /apps/{appId}/projects:
    parameters:
      - $ref: "#/components/parameters/AppId"

    post:
      tags: [Projects]
      summary: Create project
      operationId: createProject
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/CreateProjectRequest"
      responses:
        "201":
          description: Project created
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProjectResponse"
        "400":
          $ref: "#/components/responses/BadRequest"
        "404":
          $ref: "#/components/responses/NotFound"

    get:
      tags: [Projects]
      summary: List projects
      operationId: listProjects
      parameters:
        - name: activeOnly
          in: query
          schema:
            type: boolean
            default: true
        - name: limit
          in: query
          schema:
            type: integer
            default: 50
        - name: offset
          in: query
          schema:
            type: integer
            default: 0
      responses:
        "200":
          description: List of projects
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProjectListResponse"

  /apps/{appId}/projects/{projectId}:
    parameters:
      - $ref: "#/components/parameters/AppId"
      - $ref: "#/components/parameters/ProjectId"

    get:
      tags: [Projects]
      summary: Get project details
      operationId: getProject
      responses:
        "200":
          description: Project details
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProjectResponse"
        "404":
          $ref: "#/components/responses/NotFound"

    patch:
      tags: [Projects]
      summary: Update project
      operationId: updateProject
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/UpdateProjectRequest"
      responses:
        "200":
          description: Project updated
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProjectResponse"

    delete:
      tags: [Projects]
      summary: Delete project
      operationId: deleteProject
      parameters:
        - name: force
          in: query
          schema:
            type: boolean
            default: false
      responses:
        "204":
          description: Project deleted

  # ============================================================================
  # Conversation Endpoints
  # ============================================================================
  /apps/{appId}/projects/{projectId}/conversations:
    parameters:
      - $ref: "#/components/parameters/AppId"
      - $ref: "#/components/parameters/ProjectId"

    post:
      tags: [Conversations]
      summary: Create conversation
      operationId: createConversation
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/CreateConversationRequest"
      responses:
        "201":
          description: Conversation created
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ConversationResponse"

    get:
      tags: [Conversations]
      summary: List conversations
      operationId: listConversations
      parameters:
        - name: status
          in: query
          schema:
            type: string
            enum: [active, archived, deleted]
        - name: limit
          in: query
          schema:
            type: integer
            default: 50
        - name: offset
          in: query
          schema:
            type: integer
            default: 0
        - name: sortBy
          in: query
          schema:
            type: string
            enum: [createdAt, updatedAt, lastMessageAt]
            default: updatedAt
        - name: sortOrder
          in: query
          schema:
            type: string
            enum: [asc, desc]
            default: desc
      responses:
        "200":
          description: List of conversations
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ConversationListResponse"

  /apps/{appId}/projects/{projectId}/conversations/{conversationId}:
    parameters:
      - $ref: "#/components/parameters/AppId"
      - $ref: "#/components/parameters/ProjectId"
      - $ref: "#/components/parameters/ConversationId"

    get:
      tags: [Conversations]
      summary: Get conversation
      operationId: getConversation
      parameters:
        - name: includeMessages
          in: query
          schema:
            type: boolean
            default: false
        - name: messageLimit
          in: query
          schema:
            type: integer
            default: 50
      responses:
        "200":
          description: Conversation details
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ConversationDetailResponse"
        "404":
          $ref: "#/components/responses/NotFound"

    patch:
      tags: [Conversations]
      summary: Update conversation
      operationId: updateConversation
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/UpdateConversationRequest"
      responses:
        "200":
          description: Conversation updated
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ConversationResponse"

    delete:
      tags: [Conversations]
      summary: Delete conversation
      operationId: deleteConversation
      parameters:
        - name: permanent
          in: query
          description: Permanently delete (skip archive)
          schema:
            type: boolean
            default: false
      responses:
        "204":
          description: Conversation deleted

  # ============================================================================
  # Message Endpoints
  # ============================================================================
  /apps/{appId}/projects/{projectId}/conversations/{conversationId}/messages:
    parameters:
      - $ref: "#/components/parameters/AppId"
      - $ref: "#/components/parameters/ProjectId"
      - $ref: "#/components/parameters/ConversationId"

    post:
      tags: [Messages]
      summary: Send message
      operationId: sendMessage
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/SendMessageRequest"
      responses:
        "200":
          description: Message response (non-streaming)
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/MessageResponse"
        "202":
          description: Streaming started
          headers:
            X-Message-Id:
              schema:
                type: string
          content:
            text/event-stream:
              schema:
                type: string
                description: SSE stream

    get:
      tags: [Messages]
      summary: Get messages
      operationId: getMessages
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
            default: 50
            maximum: 200
        - name: before
          in: query
          description: Get messages before this ID
          schema:
            type: string
        - name: after
          in: query
          description: Get messages after this ID
          schema:
            type: string
        - name: role
          in: query
          schema:
            type: string
            enum: [user, assistant, system, tool]
      responses:
        "200":
          description: List of messages
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/MessageListResponse"

  /apps/{appId}/projects/{projectId}/conversations/{conversationId}/messages/{messageId}:
    parameters:
      - $ref: "#/components/parameters/AppId"
      - $ref: "#/components/parameters/ProjectId"
      - $ref: "#/components/parameters/ConversationId"
      - $ref: "#/components/parameters/MessageId"

    get:
      tags: [Messages]
      summary: Get message
      operationId: getMessage
      responses:
        "200":
          description: Message details
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/MessageResponse"

    delete:
      tags: [Messages]
      summary: Delete message
      operationId: deleteMessage
      responses:
        "204":
          description: Message deleted

  # ============================================================================
  # Inference Endpoints
  # ============================================================================
  /inference/complete:
    post:
      tags: [Inference]
      summary: Single completion
      operationId: complete
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/CompletionRequest"
      responses:
        "200":
          description: Completion result
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/CompletionResponse"
            text/event-stream:
              schema:
                type: string

  /inference/chat:
    post:
      tags: [Inference]
      summary: Chat completion (stateless)
      operationId: chatComplete
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/ChatCompletionRequest"
      responses:
        "200":
          description: Chat response
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ChatCompletionResponse"
            text/event-stream:
              schema:
                type: string

  /inference/embed:
    post:
      tags: [Inference]
      summary: Generate embeddings
      operationId: embed
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/EmbeddingRequest"
      responses:
        "200":
          description: Embeddings
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/EmbeddingResponse"

  /inference/vision:
    post:
      tags: [Inference]
      summary: Vision analysis
      operationId: vision
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/VisionRequest"
          multipart/form-data:
            schema:
              type: object
              properties:
                image:
                  type: string
                  format: binary
                prompt:
                  type: string
                model:
                  type: string
      responses:
        "200":
          description: Vision analysis result
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/VisionResponse"

  # ============================================================================
  # Memory Endpoints
  # ============================================================================
  /apps/{appId}/projects/{projectId}/memory:
    parameters:
      - $ref: "#/components/parameters/AppId"
      - $ref: "#/components/parameters/ProjectId"

    post:
      tags: [Memory]
      summary: Add to memory
      operationId: addMemory
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/AddMemoryRequest"
      responses:
        "201":
          description: Memory added
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/MemoryResponse"

    get:
      tags: [Memory]
      summary: List memory entries
      operationId: listMemory
      parameters:
        - name: sourceType
          in: query
          schema:
            type: string
            enum: [message, file, external, summary]
        - name: conversationId
          in: query
          schema:
            type: string
        - name: limit
          in: query
          schema:
            type: integer
            default: 50
      responses:
        "200":
          description: Memory entries
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/MemoryListResponse"

  /apps/{appId}/projects/{projectId}/memory/search:
    parameters:
      - $ref: "#/components/parameters/AppId"
      - $ref: "#/components/parameters/ProjectId"

    post:
      tags: [Memory]
      summary: Search memory
      operationId: searchMemory
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/MemorySearchRequest"
      responses:
        "200":
          description: Search results
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/MemorySearchResponse"

  /apps/{appId}/projects/{projectId}/memory/{memoryId}:
    parameters:
      - $ref: "#/components/parameters/AppId"
      - $ref: "#/components/parameters/ProjectId"
      - $ref: "#/components/parameters/MemoryId"

    get:
      tags: [Memory]
      summary: Get memory entry
      operationId: getMemory
      responses:
        "200":
          description: Memory entry
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/MemoryResponse"

    delete:
      tags: [Memory]
      summary: Delete memory entry
      operationId: deleteMemory
      responses:
        "204":
          description: Memory deleted

  # ============================================================================
  # Model Endpoints
  # ============================================================================
  /models:
    get:
      tags: [Models]
      summary: List models
      operationId: listModels
      parameters:
        - name: category
          in: query
          schema:
            $ref: "#/components/schemas/ModelCategory"
        - name: provider
          in: query
          schema:
            type: string
        - name: downloaded
          in: query
          schema:
            type: boolean
      responses:
        "200":
          description: List of models
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ModelListResponse"

  /models/{modelId}:
    parameters:
      - $ref: "#/components/parameters/ModelId"

    get:
      tags: [Models]
      summary: Get model info
      operationId: getModel
      responses:
        "200":
          description: Model details
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ModelResponse"

    delete:
      tags: [Models]
      summary: Remove model
      operationId: deleteModel
      parameters:
        - name: force
          in: query
          schema:
            type: boolean
            default: false
      responses:
        "204":
          description: Model removed

  /models/pull:
    post:
      tags: [Models]
      summary: Pull/download model
      operationId: pullModel
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/PullModelRequest"
      responses:
        "202":
          description: Download started
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/PullModelResponse"

  /models/defaults:
    get:
      tags: [Models]
      summary: Get default models per category
      operationId: getDefaultModels
      responses:
        "200":
          description: Default models
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/DefaultModelsResponse"

    put:
      tags: [Models]
      summary: Set default model for category
      operationId: setDefaultModel
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/SetDefaultModelRequest"
      responses:
        "200":
          description: Default updated
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/DefaultModelsResponse"

  # ============================================================================
  # Provider Endpoints
  # ============================================================================
  /providers:
    get:
      tags: [Providers]
      summary: List providers
      operationId: listProviders
      responses:
        "200":
          description: List of providers
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProviderListResponse"

    post:
      tags: [Providers]
      summary: Add provider
      operationId: addProvider
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/AddProviderRequest"
      responses:
        "201":
          description: Provider added
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProviderResponse"

  /providers/{providerId}:
    parameters:
      - $ref: "#/components/parameters/ProviderId"

    get:
      tags: [Providers]
      summary: Get provider
      operationId: getProvider
      responses:
        "200":
          description: Provider details
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProviderResponse"

    patch:
      tags: [Providers]
      summary: Update provider
      operationId: updateProvider
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/UpdateProviderRequest"
      responses:
        "200":
          description: Provider updated
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProviderResponse"

    delete:
      tags: [Providers]
      summary: Remove provider
      operationId: deleteProvider
      responses:
        "204":
          description: Provider removed

  /providers/{providerId}/health:
    parameters:
      - $ref: "#/components/parameters/ProviderId"

    get:
      tags: [Providers]
      summary: Check provider health
      operationId: checkProviderHealth
      responses:
        "200":
          description: Provider health status
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ProviderHealthResponse"

  /providers/{providerId}/models:
    parameters:
      - $ref: "#/components/parameters/ProviderId"

    get:
      tags: [Providers]
      summary: List provider models
      operationId: listProviderModels
      responses:
        "200":
          description: Models from provider
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/ModelListResponse"

  # ============================================================================
  # System Endpoints
  # ============================================================================
  /system/settings:
    get:
      tags: [System]
      summary: Get system settings
      operationId: getSettings
      responses:
        "200":
          description: System settings
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/SettingsResponse"

    patch:
      tags: [System]
      summary: Update settings
      operationId: updateSettings
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/UpdateSettingsRequest"
      responses:
        "200":
          description: Settings updated
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/SettingsResponse"

  /system/ports:
    get:
      tags: [System]
      summary: List managed ports
      operationId: listPorts
      responses:
        "200":
          description: Port configurations
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/PortListResponse"

  /system/ports/{port}/firewall:
    parameters:
      - name: port
        in: path
        required: true
        schema:
          type: integer

    post:
      tags: [System]
      summary: Add firewall exception
      operationId: addFirewallException
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  description: Rule name
      responses:
        "201":
          description: Firewall rule added

    delete:
      tags: [System]
      summary: Remove firewall exception
      operationId: removeFirewallException
      responses:
        "204":
          description: Firewall rule removed

  /system/database/backup:
    post:
      tags: [System]
      summary: Create database backup
      operationId: createBackup
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                appId:
                  type: string
                  description: Specific app to backup (optional)
      responses:
        "200":
          description: Backup created
          content:
            application/json:
              schema:
                type: object
                properties:
                  backupPath:
                    type: string
                  size:
                    type: integer
                  createdAt:
                    type: string
                    format: date-time

components:
  # ============================================================================
  # Security Schemes
  # ============================================================================
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: JWT token authentication

    ApiKeyAuth:
      type: apiKey
      in: header
      name: X-API-Key
      description: Application API key

  # ============================================================================
  # Parameters
  # ============================================================================
  parameters:
    AppId:
      name: appId
      in: path
      required: true
      description: Application ID
      schema:
        type: string
        pattern: "^app_[a-zA-Z0-9]{20,}$"

    ProjectId:
      name: projectId
      in: path
      required: true
      description: Project ID
      schema:
        type: string
        pattern: "^proj_[a-zA-Z0-9]{20,}$"

    ConversationId:
      name: conversationId
      in: path
      required: true
      description: Conversation ID
      schema:
        type: string
        pattern: "^conv_[a-zA-Z0-9]{20,}$"

    MessageId:
      name: messageId
      in: path
      required: true
      description: Message ID
      schema:
        type: string
        pattern: "^msg_[a-zA-Z0-9]{20,}$"

    MemoryId:
      name: memoryId
      in: path
      required: true
      description: Memory entry ID
      schema:
        type: string
        pattern: "^mem_[a-zA-Z0-9]{20,}$"

    ModelId:
      name: modelId
      in: path
      required: true
      description: Model ID
      schema:
        type: string

    ProviderId:
      name: providerId
      in: path
      required: true
      description: Provider ID
      schema:
        type: string

  # ============================================================================
  # Common Responses
  # ============================================================================
  responses:
    BadRequest:
      description: Invalid request
      content:
        application/json:
          schema:
            $ref: "#/components/schemas/ErrorResponse"

    Unauthorized:
      description: Authentication required
      content:
        application/json:
          schema:
            $ref: "#/components/schemas/ErrorResponse"

    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            $ref: "#/components/schemas/ErrorResponse"

    RateLimited:
      description: Rate limit exceeded
      headers:
        X-RateLimit-Limit:
          schema:
            type: integer
        X-RateLimit-Remaining:
          schema:
            type: integer
        X-RateLimit-Reset:
          schema:
            type: integer
      content:
        application/json:
          schema:
            $ref: "#/components/schemas/ErrorResponse"

  # ============================================================================
  # Schemas
  # ============================================================================
  schemas:
    # --------------------------------------------------------------------------
    # Common Types
    # --------------------------------------------------------------------------
    ModelCategory:
      type: string
      enum:
        - thinking
        - writing
        - coding
        - voice
        - vision
        - image-gen
        - video-gen
        - embedding

    # --------------------------------------------------------------------------
    # Error Schemas
    # --------------------------------------------------------------------------
    ErrorResponse:
      type: object
      required: [success, error]
      properties:
        success:
          type: boolean
          enum: [false]
        error:
          $ref: "#/components/schemas/ErrorDetail"

    ErrorDetail:
      type: object
      required: [code, constant, message]
      properties:
        code:
          type: integer
          description: Numeric error code (6xxx range)
          example: 6001
        constant:
          type: string
          description: Error constant
          example: ERR_AI_PROVIDER_UNAVAILABLE
        message:
          type: string
          description: Human-readable message
        details:
          type: object
          additionalProperties: true
        retryable:
          type: boolean
        stack:
          type: array
          items:
            type: string
          description: Stack trace (debug mode only)

    # --------------------------------------------------------------------------
    # Health Schemas
    # --------------------------------------------------------------------------
    HealthResponse:
      type: object
      properties:
        status:
          type: string
          enum: [healthy, degraded, unhealthy]
        version:
          type: string
        uptime:
          type: string
        checks:
          type: object
          properties:
            database:
              type: string
            providers:
              type: object
              additionalProperties:
                type: string
            memory:
              type: object
              properties:
                used:
                  type: string
                available:
                  type: string
            gpu:
              type: object
              properties:
                available:
                  type: boolean
                name:
                  type: string
                memory:
                  type: string

    ReadinessResponse:
      type: object
      properties:
        ready:
          type: boolean
        database:
          type: boolean
        atLeastOneProvider:
          type: boolean

    LivenessResponse:
      type: object
      properties:
        alive:
          type: boolean

    # --------------------------------------------------------------------------
    # Application Schemas
    # --------------------------------------------------------------------------
    RegisterAppRequest:
      type: object
      required: [name, displayName]
      properties:
        name:
          type: string
          pattern: "^[a-z][a-z0-9-]*$"
          minLength: 3
          maxLength: 64
        displayName:
          type: string
          maxLength: 128
        description:
          type: string
          maxLength: 500
        apiKey:
          type: string
          description: Optional API key for this app

    UpdateAppRequest:
      type: object
      properties:
        displayName:
          type: string
        description:
          type: string
        apiKey:
          type: string
        isActive:
          type: boolean

    AppResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: "#/components/schemas/Application"

    Application:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
        displayName:
          type: string
        description:
          type: string
        dataPath:
          type: string
        isActive:
          type: boolean
        projectCount:
          type: integer
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time
        lastAccessAt:
          type: string
          format: date-time

    AppListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: "#/components/schemas/Application"
        pagination:
          $ref: "#/components/schemas/Pagination"

    AppStatsResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            totalConversations:
              type: integer
            totalMessages:
              type: integer
            totalTokensInput:
              type: integer
            totalTokensOutput:
              type: integer
            averageLatencyMs:
              type: number
            modelUsage:
              type: object
              additionalProperties:
                type: integer

    # --------------------------------------------------------------------------
    # Project Schemas
    # --------------------------------------------------------------------------
    CreateProjectRequest:
      type: object
      required: [name]
      properties:
        name:
          type: string
          pattern: "^[a-z][a-z0-9-]*$"
        displayName:
          type: string
        description:
          type: string
        defaultModelThinking:
          type: string
        defaultModelWriting:
          type: string
        defaultModelCoding:
          type: string

    UpdateProjectRequest:
      type: object
      properties:
        displayName:
          type: string
        description:
          type: string
        defaultModelThinking:
          type: string
        defaultModelWriting:
          type: string
        defaultModelCoding:
          type: string
        isActive:
          type: boolean

    ProjectResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: "#/components/schemas/Project"

    Project:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
        displayName:
          type: string
        description:
          type: string
        dataPath:
          type: string
        defaultModelThinking:
          type: string
        defaultModelWriting:
          type: string
        defaultModelCoding:
          type: string
        isActive:
          type: boolean
        conversationCount:
          type: integer
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time

    ProjectListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: "#/components/schemas/Project"
        pagination:
          $ref: "#/components/schemas/Pagination"

    # --------------------------------------------------------------------------
    # Conversation Schemas
    # --------------------------------------------------------------------------
    CreateConversationRequest:
      type: object
      properties:
        title:
          type: string
        systemPrompt:
          type: string
        modelThinking:
          type: string
        modelWriting:
          type: string
        modelCoding:
          type: string

    UpdateConversationRequest:
      type: object
      properties:
        title:
          type: string
        systemPrompt:
          type: string
        status:
          type: string
          enum: [active, archived]
        modelThinking:
          type: string
        modelWriting:
          type: string
        modelCoding:
          type: string

    ConversationResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: "#/components/schemas/Conversation"

    Conversation:
      type: object
      properties:
        id:
          type: string
        title:
          type: string
        systemPrompt:
          type: string
        modelThinking:
          type: string
        modelWriting:
          type: string
        modelCoding:
          type: string
        status:
          type: string
        messageCount:
          type: integer
        tokensUsed:
          type: integer
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time
        lastMessageAt:
          type: string
          format: date-time

    ConversationDetailResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          allOf:
            - $ref: "#/components/schemas/Conversation"
            - type: object
              properties:
                messages:
                  type: array
                  items:
                    $ref: "#/components/schemas/Message"

    ConversationListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: "#/components/schemas/Conversation"
        pagination:
          $ref: "#/components/schemas/Pagination"

    # --------------------------------------------------------------------------
    # Message Schemas
    # --------------------------------------------------------------------------
    SendMessageRequest:
      type: object
      required: [role, content]
      properties:
        role:
          type: string
          enum: [user]
        content:
          type: string
        contentType:
          type: string
          enum: [text, markdown, code]
          default: text
        modelCategory:
          $ref: "#/components/schemas/ModelCategory"
        modelOverride:
          type: string
          description: Specific model to use
        stream:
          type: boolean
          default: true
        includeContext:
          type: boolean
          default: true
          description: Include conversation history
        contextLimit:
          type: integer
          default: 20
          description: Max previous messages
        includeMemory:
          type: boolean
          default: false
          description: Include RAG memory
        memoryLimit:
          type: integer
          default: 5
        temperature:
          type: number
          minimum: 0
          maximum: 2
        maxTokens:
          type: integer
        toolCalls:
          type: array
          items:
            $ref: "#/components/schemas/ToolDefinition"

    MessageResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: "#/components/schemas/Message"

    Message:
      type: object
      properties:
        id:
          type: string
        conversationId:
          type: string
        role:
          type: string
          enum: [system, user, assistant, tool]
        content:
          type: string
        contentType:
          type: string
        modelUsed:
          type: string
        providerUsed:
          type: string
        tokensInput:
          type: integer
        tokensOutput:
          type: integer
        latencyMs:
          type: integer
        toolCalls:
          type: array
          items:
            $ref: "#/components/schemas/ToolCall"
        toolResults:
          type: array
          items:
            $ref: "#/components/schemas/ToolResult"
        createdAt:
          type: string
          format: date-time
        sequenceNum:
          type: integer

    MessageListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: "#/components/schemas/Message"
        pagination:
          $ref: "#/components/schemas/Pagination"

    ToolDefinition:
      type: object
      properties:
        type:
          type: string
          enum: [function]
        function:
          type: object
          properties:
            name:
              type: string
            description:
              type: string
            parameters:
              type: object

    ToolCall:
      type: object
      properties:
        id:
          type: string
        type:
          type: string
        function:
          type: object
          properties:
            name:
              type: string
            arguments:
              type: string

    ToolResult:
      type: object
      properties:
        toolCallId:
          type: string
        output:
          type: string

    # --------------------------------------------------------------------------
    # Inference Schemas
    # --------------------------------------------------------------------------
    CompletionRequest:
      type: object
      required: [prompt]
      properties:
        prompt:
          type: string
        model:
          type: string
        modelCategory:
          $ref: "#/components/schemas/ModelCategory"
        systemPrompt:
          type: string
        stream:
          type: boolean
          default: false
        temperature:
          type: number
        maxTokens:
          type: integer
        stopSequences:
          type: array
          items:
            type: string

    CompletionResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            text:
              type: string
            model:
              type: string
            provider:
              type: string
            tokensInput:
              type: integer
            tokensOutput:
              type: integer
            latencyMs:
              type: integer
            finishReason:
              type: string

    ChatCompletionRequest:
      type: object
      required: [messages]
      properties:
        messages:
          type: array
          items:
            type: object
            properties:
              role:
                type: string
                enum: [system, user, assistant]
              content:
                type: string
        model:
          type: string
        modelCategory:
          $ref: "#/components/schemas/ModelCategory"
        stream:
          type: boolean
          default: false
        temperature:
          type: number
        maxTokens:
          type: integer
        tools:
          type: array
          items:
            $ref: "#/components/schemas/ToolDefinition"

    ChatCompletionResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            message:
              $ref: "#/components/schemas/Message"
            model:
              type: string
            provider:
              type: string
            usage:
              type: object
              properties:
                promptTokens:
                  type: integer
                completionTokens:
                  type: integer
                totalTokens:
                  type: integer
            latencyMs:
              type: integer

    EmbeddingRequest:
      type: object
      required: [input]
      properties:
        input:
          oneOf:
            - type: string
            - type: array
              items:
                type: string
        model:
          type: string
          default: text-embedding-3-small

    EmbeddingResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            embeddings:
              type: array
              items:
                type: object
                properties:
                  index:
                    type: integer
                  embedding:
                    type: array
                    items:
                      type: number
            model:
              type: string
            dimensions:
              type: integer
            usage:
              type: object
              properties:
                promptTokens:
                  type: integer

    VisionRequest:
      type: object
      required: [prompt]
      properties:
        prompt:
          type: string
        imageUrl:
          type: string
          format: uri
        imageBase64:
          type: string
        model:
          type: string

    VisionResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            analysis:
              type: string
            model:
              type: string
            tokensInput:
              type: integer
            tokensOutput:
              type: integer

    # --------------------------------------------------------------------------
    # Memory Schemas
    # --------------------------------------------------------------------------
    AddMemoryRequest:
      type: object
      required: [content, sourceType]
      properties:
        content:
          type: string
        sourceType:
          type: string
          enum: [message, file, external, summary]
        sourceId:
          type: string
        conversationId:
          type: string
        metadata:
          type: object
        generateEmbedding:
          type: boolean
          default: true
        expiresAt:
          type: string
          format: date-time

    MemoryResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: "#/components/schemas/Memory"

    Memory:
      type: object
      properties:
        id:
          type: string
        conversationId:
          type: string
        sourceType:
          type: string
        sourceId:
          type: string
        content:
          type: string
        contentHash:
          type: string
        chunkIndex:
          type: integer
        chunkTotal:
          type: integer
        hasEmbedding:
          type: boolean
        metadata:
          type: object
        createdAt:
          type: string
          format: date-time
        expiresAt:
          type: string
          format: date-time

    MemoryListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: "#/components/schemas/Memory"
        pagination:
          $ref: "#/components/schemas/Pagination"

    MemorySearchRequest:
      type: object
      required: [query]
      properties:
        query:
          type: string
        method:
          type: string
          enum: [hybrid, vector, fts]
          default: hybrid
        limit:
          type: integer
          default: 5
          maximum: 50
        threshold:
          type: number
          minimum: 0
          maximum: 1
          default: 0.7
        conversationId:
          type: string
          description: Scope to conversation
        sourceTypes:
          type: array
          items:
            type: string

    MemorySearchResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            type: object
            properties:
              memory:
                $ref: "#/components/schemas/Memory"
              score:
                type: number
              matchType:
                type: string
                enum: [vector, fts, hybrid]

    # --------------------------------------------------------------------------
    # Model Schemas
    # --------------------------------------------------------------------------
    ModelResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: "#/components/schemas/Model"

    Model:
      type: object
      properties:
        id:
          type: string
        providerId:
          type: string
        providerName:
          type: string
        name:
          type: string
        displayName:
          type: string
        category:
          $ref: "#/components/schemas/ModelCategory"
        size:
          type: string
        quantization:
          type: string
        filePath:
          type: string
        fileSize:
          type: integer
        isDownloaded:
          type: boolean
        isDefault:
          type: boolean
        parameters:
          type: object
          properties:
            contextLength:
              type: integer
            tokenizer:
              type: string
        createdAt:
          type: string
          format: date-time

    ModelListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: "#/components/schemas/Model"

    PullModelRequest:
      type: object
      required: [name]
      properties:
        name:
          type: string
        provider:
          type: string
          default: ollama

    PullModelResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            taskId:
              type: string
            status:
              type: string
            progress:
              type: number

    DefaultModelsResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          additionalProperties:
            type: string
          example:
            thinking: deepseek-r1:14b
            writing: llama3:8b
            coding: codellama:13b

    SetDefaultModelRequest:
      type: object
      required: [category, model]
      properties:
        category:
          $ref: "#/components/schemas/ModelCategory"
        model:
          type: string

    # --------------------------------------------------------------------------
    # Provider Schemas
    # --------------------------------------------------------------------------
    AddProviderRequest:
      type: object
      required: [name, type]
      properties:
        name:
          type: string
        type:
          type: string
          enum: [local, remote, hybrid]
        baseUrl:
          type: string
          format: uri
        priority:
          type: integer
          default: 100
        capabilities:
          type: array
          items:
            type: string
            enum: [chat, completion, embedding, vision]
        config:
          type: object

    UpdateProviderRequest:
      type: object
      properties:
        baseUrl:
          type: string
        priority:
          type: integer
        config:
          type: object

    ProviderResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          $ref: "#/components/schemas/Provider"

    Provider:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
        type:
          type: string
        baseUrl:
          type: string
        status:
          type: string
          enum: [online, offline, error, unknown]
        capabilities:
          type: array
          items:
            type: string
        priority:
          type: integer
        modelCount:
          type: integer
        lastHealthCheck:
          type: string
          format: date-time
        createdAt:
          type: string
          format: date-time

    ProviderListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: "#/components/schemas/Provider"

    ProviderHealthResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            status:
              type: string
            responseTimeMs:
              type: integer
            lastCheck:
              type: string
              format: date-time
            errorCount:
              type: integer
            availableModels:
              type: integer

    # --------------------------------------------------------------------------
    # System Schemas
    # --------------------------------------------------------------------------
    SettingsResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: object
          additionalProperties:
            type: object
            properties:
              value:
                type: string
              type:
                type: string
              category:
                type: string
              description:
                type: string

    UpdateSettingsRequest:
      type: object
      additionalProperties:
        type: string

    PortListResponse:
      type: object
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            type: object
            properties:
              port:
                type: integer
              serviceName:
                type: string
              applicationId:
                type: string
              protocol:
                type: string
              firewallStatus:
                type: string
              isActive:
                type: boolean

    # --------------------------------------------------------------------------
    # Common Schemas
    # --------------------------------------------------------------------------
    Pagination:
      type: object
      properties:
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer
        hasMore:
          type: boolean
```

---

## SSE Event Types

For streaming responses (`Accept: text/event-stream`):

| Event | Description |
|-------|-------------|
| `message_start` | Generation started |
| `content_delta` | Token chunk |
| `thinking_start` | Reasoning started (thinking models) |
| `thinking_delta` | Reasoning token |
| `thinking_done` | Reasoning complete |
| `tool_call` | Tool invocation |
| `tool_result` | Tool response |
| `message_done` | Generation complete |
| `error` | Error occurred |

### SSE Format

```
event: message_start
data: {"messageId":"msg_01ABC","model":"llama3:8b","provider":"ollama"}

event: content_delta
data: {"delta":"Hello"}

event: content_delta
data: {"delta":" there!"}

event: message_done
data: {"tokensInput":15,"tokensOutput":2,"latencyMs":234}
```

---

## Rate Limits

| Endpoint Category | Limit | Window |
|-------------------|-------|--------|
| Health checks | Unlimited | - |
| Read operations | 200/min | Per API key |
| Inference | 30/min | Per API key |
| Memory search | 60/min | Per API key |
| Model pull | 5/min | Global |

---

## Related Specifications

- [AI Bridge CLI](./12-ai-bridge-cli.md)
- [Voice CLI OpenAPI](./11-voice-cli-openapi.md)
- [Gateway Service](./01-gateway-service.md)
