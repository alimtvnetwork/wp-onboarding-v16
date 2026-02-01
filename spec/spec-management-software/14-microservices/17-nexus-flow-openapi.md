# Nexus-Flow Service OpenAPI Specification

> **Version:** 1.0.0  
> **Status:** Draft  
> **Last Updated:** 2026-01-30  
> **Service Port:** 8092  
> **Error Code Range:** 10xxx

---

## Cross-References

- [Nexus-Flow Service](./14-nexus-flow-service.md) — Core service specification
- [Nexus-Flow Standalone Architecture](./09-nexus-flow-standalone-architecture.md)
- [Gateway OpenAPI](./07-gateway-openapi.md)
- [AI-Bridge OpenAPI](./13-ai-bridge-openapi.md)

---

## 1. Overview

This document defines the REST API and WebSocket protocol for the Nexus-Flow orchestration service. The API enables flow management, stage configuration, execution control, and real-time monitoring.

### Base URL

```
Production:  http://localhost:8092/api/v1
Via Gateway: http://localhost:8080/api/v1/nexus-flow
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
  title: Nexus-Flow Orchestration API
  description: |
    REST API and WebSocket protocol for visual pipeline orchestration.
    Supports flow design, stage execution, variable management, and
    real-time execution monitoring.
  version: 1.0.0
  contact:
    name: Nexus-Flow Team
  license:
    name: Proprietary

servers:
  - url: http://localhost:8092/api/v1
    description: Local development
  - url: http://localhost:8080/api/v1/nexus-flow
    description: Via Gateway

tags:
  - name: Projects
    description: Project management
  - name: Flows
    description: Flow CRUD operations
  - name: Stages
    description: Stage/node management
  - name: Connections
    description: Stage connection management
  - name: Variables
    description: Variable registry
  - name: Executions
    description: Execution control and monitoring
  - name: Templates
    description: Stage templates
  - name: Runtimes
    description: Script runtime management
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
          description: Service not ready

  /live:
    get:
      tags: [Health]
      summary: Liveness probe
      operationId: getLiveness
      responses:
        '200':
          description: Service is alive

  # ============================================================
  # PROJECT ENDPOINTS
  # ============================================================
  /projects:
    get:
      tags: [Projects]
      summary: List all projects
      operationId: listProjects
      parameters:
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
        - name: sort
          in: query
          schema:
            type: string
            enum: [name, created_at, last_accessed]
            default: last_accessed
      responses:
        '200':
          description: Project list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProjectListResponse'

    post:
      tags: [Projects]
      summary: Create new project
      operationId: createProject
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
      summary: Get project by ID
      operationId: getProject
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
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
      summary: Delete project
      operationId: deleteProject
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      responses:
        '204':
          description: Project deleted
        '404':
          $ref: '#/components/responses/NotFound'

  # ============================================================
  # FLOW ENDPOINTS
  # ============================================================
  /projects/{projectId}/flows:
    get:
      tags: [Flows]
      summary: List flows in project
      operationId: listFlows
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
        - name: status
          in: query
          schema:
            type: string
            enum: [draft, active, archived]
      responses:
        '200':
          description: Flow list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FlowListResponse'

    post:
      tags: [Flows]
      summary: Create new flow
      operationId: createFlow
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateFlowRequest'
      responses:
        '201':
          description: Flow created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Flow'

  /projects/{projectId}/flows/{flowId}:
    get:
      tags: [Flows]
      summary: Get flow by ID
      operationId: getFlow
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - name: include
          in: query
          description: Include related data
          schema:
            type: array
            items:
              type: string
              enum: [stages, connections, variables, executions]
      responses:
        '200':
          description: Flow details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FlowDetail'

    patch:
      tags: [Flows]
      summary: Update flow
      operationId: updateFlow
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateFlowRequest'
      responses:
        '200':
          description: Flow updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Flow'

    delete:
      tags: [Flows]
      summary: Delete flow
      operationId: deleteFlow
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      responses:
        '204':
          description: Flow deleted

  /projects/{projectId}/flows/{flowId}/duplicate:
    post:
      tags: [Flows]
      summary: Duplicate flow
      operationId: duplicateFlow
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  description: Name for duplicated flow
      responses:
        '201':
          description: Flow duplicated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Flow'

  /projects/{projectId}/flows/{flowId}/export:
    get:
      tags: [Flows]
      summary: Export flow
      operationId: exportFlow
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - name: format
          in: query
          schema:
            type: string
            enum: [json, nfx, zip]
            default: json
        - name: includeExecutions
          in: query
          schema:
            type: boolean
            default: false
      responses:
        '200':
          description: Exported flow
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/FlowExport'
            application/octet-stream:
              schema:
                type: string
                format: binary

  /projects/{projectId}/flows/import:
    post:
      tags: [Flows]
      summary: Import flow
      operationId: importFlow
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              properties:
                file:
                  type: string
                  format: binary
                overwrite:
                  type: boolean
                  default: false
          application/json:
            schema:
              $ref: '#/components/schemas/FlowExport'
      responses:
        '201':
          description: Flow imported
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Flow'

  # ============================================================
  # STAGE ENDPOINTS
  # ============================================================
  /projects/{projectId}/flows/{flowId}/stages:
    get:
      tags: [Stages]
      summary: List stages in flow
      operationId: listStages
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - name: type
          in: query
          schema:
            $ref: '#/components/schemas/StageType'
      responses:
        '200':
          description: Stage list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/StageListResponse'

    post:
      tags: [Stages]
      summary: Create stage
      operationId: createStage
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateStageRequest'
      responses:
        '201':
          description: Stage created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Stage'

    patch:
      tags: [Stages]
      summary: Batch update stages
      description: Update multiple stages at once (positions, connections)
      operationId: batchUpdateStages
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/BatchStageUpdateRequest'
      responses:
        '200':
          description: Stages updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/StageListResponse'

  /projects/{projectId}/flows/{flowId}/stages/{stageId}:
    get:
      tags: [Stages]
      summary: Get stage by ID
      operationId: getStage
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/StageIdParam'
      responses:
        '200':
          description: Stage details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Stage'

    patch:
      tags: [Stages]
      summary: Update stage
      operationId: updateStage
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/StageIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateStageRequest'
      responses:
        '200':
          description: Stage updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Stage'

    delete:
      tags: [Stages]
      summary: Delete stage
      operationId: deleteStage
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/StageIdParam'
      responses:
        '204':
          description: Stage deleted

  /projects/{projectId}/flows/{flowId}/stages/{stageId}/validate:
    post:
      tags: [Stages]
      summary: Validate stage configuration
      operationId: validateStage
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/StageIdParam'
      responses:
        '200':
          description: Validation result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ValidationResult'

  # ============================================================
  # CONNECTION ENDPOINTS
  # ============================================================
  /projects/{projectId}/flows/{flowId}/connections:
    get:
      tags: [Connections]
      summary: List connections in flow
      operationId: listConnections
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      responses:
        '200':
          description: Connection list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ConnectionListResponse'

    post:
      tags: [Connections]
      summary: Create connection
      operationId: createConnection
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateConnectionRequest'
      responses:
        '201':
          description: Connection created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Connection'
        '400':
          description: Invalid connection (would create cycle)
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'

  /projects/{projectId}/flows/{flowId}/connections/{connectionId}:
    patch:
      tags: [Connections]
      summary: Update connection
      operationId: updateConnection
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ConnectionIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateConnectionRequest'
      responses:
        '200':
          description: Connection updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Connection'

    delete:
      tags: [Connections]
      summary: Delete connection
      operationId: deleteConnection
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ConnectionIdParam'
      responses:
        '204':
          description: Connection deleted

  # ============================================================
  # VARIABLE ENDPOINTS
  # ============================================================
  /projects/{projectId}/flows/{flowId}/variables:
    get:
      tags: [Variables]
      summary: List variables in flow
      operationId: listVariables
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - name: scope
          in: query
          schema:
            type: string
            enum: [input, output, internal, all]
            default: all
      responses:
        '200':
          description: Variable list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/VariableListResponse'

    post:
      tags: [Variables]
      summary: Create variable
      operationId: createVariable
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateVariableRequest'
      responses:
        '201':
          description: Variable created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Variable'

  /projects/{projectId}/flows/{flowId}/variables/{variableId}:
    patch:
      tags: [Variables]
      summary: Update variable
      operationId: updateVariable
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/VariableIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateVariableRequest'
      responses:
        '200':
          description: Variable updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Variable'

    delete:
      tags: [Variables]
      summary: Delete variable
      operationId: deleteVariable
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/VariableIdParam'
      responses:
        '204':
          description: Variable deleted

  # ============================================================
  # EXECUTION ENDPOINTS
  # ============================================================
  /projects/{projectId}/flows/{flowId}/executions:
    get:
      tags: [Executions]
      summary: List executions
      operationId: listExecutions
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
        - name: status
          in: query
          schema:
            $ref: '#/components/schemas/ExecutionStatus'
      responses:
        '200':
          description: Execution list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ExecutionListResponse'

    post:
      tags: [Executions]
      summary: Start new execution
      operationId: startExecution
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
      requestBody:
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/StartExecutionRequest'
      responses:
        '202':
          description: Execution started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Execution'
          headers:
            X-Execution-ID:
              schema:
                type: string
              description: Execution ID for tracking

  /projects/{projectId}/flows/{flowId}/executions/{executionId}:
    get:
      tags: [Executions]
      summary: Get execution details
      operationId: getExecution
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ExecutionIdParam'
        - name: include
          in: query
          schema:
            type: array
            items:
              type: string
              enum: [stages, logs, checkpoints, resilience]
      responses:
        '200':
          description: Execution details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ExecutionDetail'

  /projects/{projectId}/flows/{flowId}/executions/{executionId}/pause:
    post:
      tags: [Executions]
      summary: Pause execution
      operationId: pauseExecution
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ExecutionIdParam'
      responses:
        '200':
          description: Execution paused
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Execution'
        '409':
          description: Cannot pause (not running)
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'

  /projects/{projectId}/flows/{flowId}/executions/{executionId}/resume:
    post:
      tags: [Executions]
      summary: Resume execution
      operationId: resumeExecution
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ExecutionIdParam'
      responses:
        '200':
          description: Execution resumed
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Execution'

  /projects/{projectId}/flows/{flowId}/executions/{executionId}/cancel:
    post:
      tags: [Executions]
      summary: Cancel execution
      operationId: cancelExecution
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ExecutionIdParam'
      responses:
        '200':
          description: Execution cancelled
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Execution'

  /projects/{projectId}/flows/{flowId}/executions/{executionId}/retry:
    post:
      tags: [Executions]
      summary: Retry failed execution
      operationId: retryExecution
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ExecutionIdParam'
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                fromStageId:
                  type: string
                  description: Stage ID to retry from (defaults to failed stage)
                useCheckpoint:
                  type: boolean
                  default: true
      responses:
        '202':
          description: Retry started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Execution'

  /projects/{projectId}/flows/{flowId}/executions/{executionId}/rollback:
    post:
      tags: [Executions]
      summary: Rollback to checkpoint
      operationId: rollbackExecution
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ExecutionIdParam'
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [checkpointId]
              properties:
                checkpointId:
                  type: string
                continueExecution:
                  type: boolean
                  default: false
      responses:
        '200':
          description: Rollback complete
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Execution'

  /projects/{projectId}/flows/{flowId}/executions/{executionId}/logs:
    get:
      tags: [Executions]
      summary: Get execution logs
      operationId: getExecutionLogs
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ExecutionIdParam'
        - name: level
          in: query
          schema:
            type: string
            enum: [debug, info, warn, error]
        - name: stageId
          in: query
          schema:
            type: string
        - $ref: '#/components/parameters/LimitParam'
        - $ref: '#/components/parameters/OffsetParam'
      responses:
        '200':
          description: Execution logs
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/LogListResponse'

  /projects/{projectId}/flows/{flowId}/executions/{executionId}/checkpoints:
    get:
      tags: [Executions]
      summary: List execution checkpoints
      operationId: listCheckpoints
      parameters:
        - $ref: '#/components/parameters/ProjectIdParam'
        - $ref: '#/components/parameters/FlowIdParam'
        - $ref: '#/components/parameters/ExecutionIdParam'
      responses:
        '200':
          description: Checkpoint list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CheckpointListResponse'

  # ============================================================
  # TEMPLATE ENDPOINTS
  # ============================================================
  /templates:
    get:
      tags: [Templates]
      summary: List stage templates
      operationId: listTemplates
      parameters:
        - name: type
          in: query
          schema:
            $ref: '#/components/schemas/StageType'
        - name: builtIn
          in: query
          schema:
            type: boolean
      responses:
        '200':
          description: Template list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TemplateListResponse'

    post:
      tags: [Templates]
      summary: Create template from stage
      operationId: createTemplate
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateTemplateRequest'
      responses:
        '201':
          description: Template created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Template'

  /templates/{templateId}:
    delete:
      tags: [Templates]
      summary: Delete template
      operationId: deleteTemplate
      parameters:
        - name: templateId
          in: path
          required: true
          schema:
            type: string
      responses:
        '204':
          description: Template deleted

  # ============================================================
  # RUNTIME ENDPOINTS
  # ============================================================
  /runtimes:
    get:
      tags: [Runtimes]
      summary: List available runtimes
      operationId: listRuntimes
      responses:
        '200':
          description: Runtime list
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RuntimeListResponse'

  /runtimes/{runtimeId}/check:
    post:
      tags: [Runtimes]
      summary: Check runtime availability
      operationId: checkRuntime
      parameters:
        - name: runtimeId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Runtime check result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RuntimeCheckResult'

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

    FlowIdParam:
      name: flowId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Flow UUID

    StageIdParam:
      name: stageId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Stage UUID

    ConnectionIdParam:
      name: connectionId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Connection UUID

    VariableIdParam:
      name: variableId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Variable UUID

    ExecutionIdParam:
      name: executionId
      in: path
      required: true
      schema:
        type: string
        format: uuid
      description: Execution UUID

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

    Conflict:
      description: Resource conflict
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
          description: Uptime in seconds
        databases:
          type: object
          additionalProperties:
            type: string
            enum: [connected, disconnected]
        runtimes:
          type: object
          additionalProperties:
            type: boolean

    # --- Error ---
    ErrorResponse:
      type: object
      required: [code, message]
      properties:
        code:
          type: integer
          description: Error code (10xxx range)
        message:
          type: string
        details:
          type: object
        traceId:
          type: string
          format: uuid

    # --- Project ---
    Project:
      type: object
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
        description:
          type: string
        rootPath:
          type: string
        flowCount:
          type: integer
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time
        lastAccessedAt:
          type: string
          format: date-time

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

    CreateProjectRequest:
      type: object
      required: [name, rootPath]
      properties:
        name:
          type: string
          minLength: 1
          maxLength: 100
        description:
          type: string
        rootPath:
          type: string

    UpdateProjectRequest:
      type: object
      properties:
        name:
          type: string
        description:
          type: string

    # --- Flow ---
    Flow:
      type: object
      properties:
        id:
          type: string
          format: uuid
        projectId:
          type: string
          format: uuid
        name:
          type: string
        description:
          type: string
        version:
          type: string
        status:
          type: string
          enum: [draft, active, archived]
        stageCount:
          type: integer
        executionCount:
          type: integer
        lastExecutedAt:
          type: string
          format: date-time
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time

    FlowDetail:
      allOf:
        - $ref: '#/components/schemas/Flow'
        - type: object
          properties:
            stages:
              type: array
              items:
                $ref: '#/components/schemas/Stage'
            connections:
              type: array
              items:
                $ref: '#/components/schemas/Connection'
            variables:
              type: array
              items:
                $ref: '#/components/schemas/Variable'
            canvasState:
              $ref: '#/components/schemas/CanvasState'

    FlowListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Flow'
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer

    CreateFlowRequest:
      type: object
      required: [name]
      properties:
        name:
          type: string
        description:
          type: string
        templateId:
          type: string
          format: uuid
          description: Create from template

    UpdateFlowRequest:
      type: object
      properties:
        name:
          type: string
        description:
          type: string
        status:
          type: string
          enum: [draft, active, archived]
        canvasState:
          $ref: '#/components/schemas/CanvasState'

    FlowExport:
      type: object
      properties:
        version:
          type: string
        exportedAt:
          type: string
          format: date-time
        flow:
          $ref: '#/components/schemas/FlowDetail'
        checksum:
          type: string

    CanvasState:
      type: object
      properties:
        viewport:
          type: object
          properties:
            x:
              type: number
            y:
              type: number
            zoom:
              type: number
        selection:
          type: array
          items:
            type: string

    # --- Stage ---
    StageType:
      type: string
      enum:
        - start
        - end
        - branch
        - loop
        - parallel
        - wait
        - llm_prompt
        - embedding
        - image_gen
        - voice_transcribe
        - transform
        - filter
        - aggregate
        - validate
        - map
        - http_request
        - file_read
        - file_write
        - db_query
        - email
        - go_script
        - node_script
        - python_script
        - php_script
        - webhook
        - event
        - schedule
        - macro_recorder

    Stage:
      type: object
      properties:
        id:
          type: string
          format: uuid
        flowId:
          type: string
          format: uuid
        type:
          $ref: '#/components/schemas/StageType'
        name:
          type: string
        description:
          type: string
        position:
          $ref: '#/components/schemas/Position'
        dimensions:
          $ref: '#/components/schemas/Dimensions'
        configuration:
          type: object
          description: Type-specific configuration
        retryPolicy:
          $ref: '#/components/schemas/RetryPolicy'
        timeout:
          type: integer
          description: Timeout in milliseconds
        checkpointEnabled:
          type: boolean
        createdAt:
          type: string
          format: date-time

    StageListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Stage'
        total:
          type: integer

    CreateStageRequest:
      type: object
      required: [type, name]
      properties:
        type:
          $ref: '#/components/schemas/StageType'
        name:
          type: string
        description:
          type: string
        position:
          $ref: '#/components/schemas/Position'
        configuration:
          type: object
        templateId:
          type: string
          format: uuid

    UpdateStageRequest:
      type: object
      properties:
        name:
          type: string
        description:
          type: string
        position:
          $ref: '#/components/schemas/Position'
        dimensions:
          $ref: '#/components/schemas/Dimensions'
        configuration:
          type: object
        retryPolicy:
          $ref: '#/components/schemas/RetryPolicy'
        timeout:
          type: integer
        checkpointEnabled:
          type: boolean

    BatchStageUpdateRequest:
      type: object
      properties:
        updates:
          type: array
          items:
            type: object
            required: [id]
            properties:
              id:
                type: string
                format: uuid
              position:
                $ref: '#/components/schemas/Position'
              dimensions:
                $ref: '#/components/schemas/Dimensions'

    Position:
      type: object
      properties:
        x:
          type: number
        y:
          type: number

    Dimensions:
      type: object
      properties:
        width:
          type: number
        height:
          type: number

    RetryPolicy:
      type: object
      properties:
        maxAttempts:
          type: integer
          minimum: 1
          maximum: 10
        backoffMs:
          type: integer
        backoffMultiplier:
          type: number
        maxBackoffMs:
          type: integer
        retryableErrors:
          type: array
          items:
            type: integer

    ValidationResult:
      type: object
      properties:
        valid:
          type: boolean
        errors:
          type: array
          items:
            type: object
            properties:
              field:
                type: string
              message:
                type: string
              code:
                type: integer
        warnings:
          type: array
          items:
            type: object
            properties:
              field:
                type: string
              message:
                type: string

    # --- Connection ---
    Connection:
      type: object
      properties:
        id:
          type: string
          format: uuid
        flowId:
          type: string
          format: uuid
        sourceStageId:
          type: string
          format: uuid
        targetStageId:
          type: string
          format: uuid
        sourceHandle:
          type: string
        targetHandle:
          type: string
        condition:
          type: string
          description: CEL expression for conditional routing
        label:
          type: string
        createdAt:
          type: string
          format: date-time

    ConnectionListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Connection'
        total:
          type: integer

    CreateConnectionRequest:
      type: object
      required: [sourceStageId, targetStageId]
      properties:
        sourceStageId:
          type: string
          format: uuid
        targetStageId:
          type: string
          format: uuid
        sourceHandle:
          type: string
        targetHandle:
          type: string
        condition:
          type: string
        label:
          type: string

    UpdateConnectionRequest:
      type: object
      properties:
        condition:
          type: string
        label:
          type: string

    # --- Variable ---
    Variable:
      type: object
      properties:
        id:
          type: string
          format: uuid
        flowId:
          type: string
          format: uuid
        name:
          type: string
        type:
          type: string
          enum: [string, number, boolean, object, array]
        scope:
          type: string
          enum: [input, output, internal]
        defaultValue:
          type: string
        description:
          type: string
        createdAt:
          type: string
          format: date-time

    VariableListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Variable'
        total:
          type: integer

    CreateVariableRequest:
      type: object
      required: [name, type, scope]
      properties:
        name:
          type: string
        type:
          type: string
          enum: [string, number, boolean, object, array]
        scope:
          type: string
          enum: [input, output, internal]
        defaultValue:
          type: string
        description:
          type: string

    UpdateVariableRequest:
      type: object
      properties:
        name:
          type: string
        type:
          type: string
          enum: [string, number, boolean, object, array]
        defaultValue:
          type: string
        description:
          type: string

    # --- Execution ---
    ExecutionStatus:
      type: string
      enum: [pending, running, paused, completed, failed, cancelled]

    Execution:
      type: object
      properties:
        id:
          type: string
          format: uuid
        flowId:
          type: string
          format: uuid
        status:
          $ref: '#/components/schemas/ExecutionStatus'
        triggerType:
          type: string
          enum: [manual, scheduled, webhook, event, voice]
        currentStageId:
          type: string
          format: uuid
        progress:
          type: number
          description: Completion percentage 0-100
        inputVariables:
          type: object
        outputVariables:
          type: object
        startedAt:
          type: string
          format: date-time
        completedAt:
          type: string
          format: date-time
        durationMs:
          type: integer
        errorMessage:
          type: string
        errorCode:
          type: integer

    ExecutionDetail:
      allOf:
        - $ref: '#/components/schemas/Execution'
        - type: object
          properties:
            stageExecutions:
              type: array
              items:
                $ref: '#/components/schemas/StageExecution'
            logs:
              type: array
              items:
                $ref: '#/components/schemas/LogEntry'
            checkpoints:
              type: array
              items:
                $ref: '#/components/schemas/Checkpoint'
            resilienceLogs:
              type: array
              items:
                $ref: '#/components/schemas/ResilienceLog'

    ExecutionListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Execution'
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer

    StartExecutionRequest:
      type: object
      properties:
        inputVariables:
          type: object
          additionalProperties: true
        dryRun:
          type: boolean
          default: false
        startFromStageId:
          type: string
          format: uuid
        checkpointId:
          type: string
          format: uuid
          description: Resume from checkpoint

    StageExecution:
      type: object
      properties:
        id:
          type: string
          format: uuid
        stageId:
          type: string
          format: uuid
        stageName:
          type: string
        status:
          type: string
          enum: [pending, running, completed, failed, skipped]
        attempt:
          type: integer
        inputData:
          type: object
        outputData:
          type: object
        startedAt:
          type: string
          format: date-time
        completedAt:
          type: string
          format: date-time
        durationMs:
          type: integer
        errorCode:
          type: integer
        errorMessage:
          type: string

    LogEntry:
      type: object
      properties:
        id:
          type: string
          format: uuid
        stageId:
          type: string
          format: uuid
        level:
          type: string
          enum: [debug, info, warn, error]
        message:
          type: string
        metadata:
          type: object
        timestamp:
          type: string
          format: date-time

    LogListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/LogEntry'
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer

    Checkpoint:
      type: object
      properties:
        id:
          type: string
          format: uuid
        stageId:
          type: string
          format: uuid
        stageName:
          type: string
        variableSnapshot:
          type: object
        fileSnapshot:
          type: object
        createdAt:
          type: string
          format: date-time

    CheckpointListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Checkpoint'
        total:
          type: integer

    ResilienceLog:
      type: object
      properties:
        id:
          type: string
          format: uuid
        stageId:
          type: string
          format: uuid
        mechanism:
          type: string
          enum: [self_correction, multi_model, checkpoint, adaptive_retry, escalation]
        details:
          type: object
        outcome:
          type: string
          enum: [success, failure, escalated]
        createdAt:
          type: string
          format: date-time

    # --- Template ---
    Template:
      type: object
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
        type:
          $ref: '#/components/schemas/StageType'
        description:
          type: string
        configuration:
          type: object
        isBuiltIn:
          type: boolean
        createdAt:
          type: string
          format: date-time

    TemplateListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Template'
        total:
          type: integer

    CreateTemplateRequest:
      type: object
      required: [name, type, configuration]
      properties:
        name:
          type: string
        type:
          $ref: '#/components/schemas/StageType'
        description:
          type: string
        configuration:
          type: object

    # --- Runtime ---
    Runtime:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
          enum: [go, node, python, php]
        version:
          type: string
        executablePath:
          type: string
        isAvailable:
          type: boolean
        lastCheckedAt:
          type: string
          format: date-time

    RuntimeListResponse:
      type: object
      properties:
        items:
          type: array
          items:
            $ref: '#/components/schemas/Runtime'

    RuntimeCheckResult:
      type: object
      properties:
        id:
          type: string
        isAvailable:
          type: boolean
        version:
          type: string
        executablePath:
          type: string
        error:
          type: string
```

---

## 3. WebSocket Protocol

### 3.1 Connection

```
WebSocket URL: ws://localhost:8092/ws/executions/{executionId}
Via Gateway:   ws://localhost:8080/ws/nexus-flow/executions/{executionId}
```

### 3.2 Authentication

```
Authorization: Bearer <jwt_token>
```

Or via query parameter:
```
ws://localhost:8092/ws/executions/{executionId}?token=<jwt_token>
```

### 3.3 Message Types

#### Client → Server Messages

```typescript
// Subscribe to execution events
interface SubscribeMessage {
  type: 'subscribe';
  executionId: string;
  events?: ('stage' | 'log' | 'variable' | 'checkpoint' | 'resilience')[];
}

// Unsubscribe from execution
interface UnsubscribeMessage {
  type: 'unsubscribe';
  executionId: string;
}

// Send input to waiting stage (human-in-the-loop)
interface InputMessage {
  type: 'input';
  executionId: string;
  stageId: string;
  data: Record<string, unknown>;
}

// Ping for keepalive
interface PingMessage {
  type: 'ping';
  timestamp: number;
}
```

#### Server → Client Messages

```typescript
// Connection established
interface ConnectedMessage {
  type: 'connected';
  executionId: string;
  currentStatus: ExecutionStatus;
  timestamp: string;
}

// Execution status changed
interface StatusMessage {
  type: 'status';
  executionId: string;
  status: ExecutionStatus;
  previousStatus: ExecutionStatus;
  timestamp: string;
}

// Stage execution update
interface StageMessage {
  type: 'stage';
  executionId: string;
  stageId: string;
  stageName: string;
  event: 'started' | 'completed' | 'failed' | 'skipped' | 'waiting';
  attempt?: number;
  input?: Record<string, unknown>;
  output?: Record<string, unknown>;
  error?: { code: number; message: string };
  durationMs?: number;
  timestamp: string;
}

// Log entry
interface LogMessage {
  type: 'log';
  executionId: string;
  stageId?: string;
  level: 'debug' | 'info' | 'warn' | 'error';
  message: string;
  metadata?: Record<string, unknown>;
  timestamp: string;
}

// Variable change
interface VariableMessage {
  type: 'variable';
  executionId: string;
  stageId: string;
  name: string;
  path: string;
  value: unknown;
  previousValue?: unknown;
  timestamp: string;
}

// Checkpoint created
interface CheckpointMessage {
  type: 'checkpoint';
  executionId: string;
  checkpointId: string;
  stageId: string;
  stageName: string;
  timestamp: string;
}

// Resilience mechanism triggered
interface ResilienceMessage {
  type: 'resilience';
  executionId: string;
  stageId: string;
  mechanism: 'self_correction' | 'multi_model' | 'checkpoint' | 'adaptive_retry' | 'escalation';
  details: Record<string, unknown>;
  outcome: 'success' | 'failure' | 'escalated' | 'pending';
  timestamp: string;
}

// Progress update
interface ProgressMessage {
  type: 'progress';
  executionId: string;
  completedStages: number;
  totalStages: number;
  percentage: number;
  currentStageId: string;
  currentStageName: string;
  timestamp: string;
}

// Human input required
interface InputRequiredMessage {
  type: 'input_required';
  executionId: string;
  stageId: string;
  stageName: string;
  prompt: string;
  schema: Record<string, unknown>;  // JSON Schema for expected input
  timeout?: number;                  // Milliseconds before auto-timeout
  timestamp: string;
}

// Execution completed
interface CompletedMessage {
  type: 'completed';
  executionId: string;
  status: 'completed' | 'failed' | 'cancelled';
  outputVariables?: Record<string, unknown>;
  error?: { code: number; message: string };
  durationMs: number;
  timestamp: string;
}

// Pong response
interface PongMessage {
  type: 'pong';
  timestamp: number;
  serverTime: string;
}

// Error message
interface ErrorMessage {
  type: 'error';
  code: number;
  message: string;
  details?: Record<string, unknown>;
  timestamp: string;
}
```

### 3.4 Flow Diagram

```
Client                                          Server
   │                                               │
   │──── WebSocket Connect ────────────────────────►
   │                                               │
   │◄──── connected { status: 'running' } ─────────│
   │                                               │
   │──── subscribe { events: ['stage','log'] } ───►
   │                                               │
   │◄──── stage { event: 'started', stage: 'S1' } ─│
   │◄──── log { level: 'info', message: '...' } ───│
   │◄──── variable { name: 'result', value: ... } ─│
   │◄──── stage { event: 'completed', stage: 'S1' }│
   │                                               │
   │◄──── stage { event: 'started', stage: 'S2' } ─│
   │◄──── input_required { schema: {...} } ────────│
   │                                               │
   │──── input { stageId: 'S2', data: {...} } ────►
   │                                               │
   │◄──── stage { event: 'completed', stage: 'S2' }│
   │                                               │
   │◄──── resilience { mechanism: 'retry' } ───────│
   │◄──── checkpoint { stageId: 'S3' } ────────────│
   │                                               │
   │◄──── progress { percentage: 75 } ─────────────│
   │◄──── completed { status: 'completed' } ───────│
   │                                               │
   │──── WebSocket Close ──────────────────────────►
   │                                               │
```

---

## 4. Error Codes

| Code | Name | Description |
|------|------|-------------|
| **10000** | NEXUS_UNKNOWN | Unknown Nexus-Flow error |
| **10001** | PROJECT_NOT_FOUND | Project does not exist |
| **10002** | PROJECT_CREATE_FAILED | Failed to create project |
| **10003** | PROJECT_DELETE_FAILED | Cannot delete project with active executions |
| **10010** | FLOW_NOT_FOUND | Flow does not exist |
| **10011** | FLOW_CREATE_FAILED | Failed to create flow |
| **10012** | FLOW_INVALID | Flow validation failed |
| **10013** | FLOW_CYCLE_DETECTED | Circular dependency detected in flow |
| **10014** | FLOW_EXPORT_FAILED | Failed to export flow |
| **10015** | FLOW_IMPORT_FAILED | Failed to import flow |
| **10020** | STAGE_NOT_FOUND | Stage does not exist |
| **10021** | STAGE_CREATE_FAILED | Failed to create stage |
| **10022** | STAGE_CONFIG_INVALID | Stage configuration is invalid |
| **10023** | STAGE_TYPE_UNKNOWN | Unknown stage type |
| **10030** | CONNECTION_NOT_FOUND | Connection does not exist |
| **10031** | CONNECTION_INVALID | Invalid connection (would create cycle) |
| **10032** | CONNECTION_DUPLICATE | Duplicate connection |
| **10040** | VARIABLE_NOT_FOUND | Variable does not exist |
| **10041** | VARIABLE_TYPE_MISMATCH | Variable type mismatch |
| **10042** | VARIABLE_RESOLVE_FAILED | Failed to resolve variable path |
| **10050** | EXECUTION_NOT_FOUND | Execution does not exist |
| **10051** | EXECUTION_START_FAILED | Failed to start execution |
| **10052** | EXECUTION_ALREADY_RUNNING | Execution is already running |
| **10053** | EXECUTION_NOT_RUNNING | Execution is not running |
| **10054** | EXECUTION_CANCELLED | Execution was cancelled |
| **10055** | EXECUTION_TIMEOUT | Execution timed out |
| **10056** | EXECUTION_PAUSED | Execution is paused |
| **10060** | STAGE_EXECUTION_FAILED | Stage execution failed |
| **10061** | STAGE_TIMEOUT | Stage execution timed out |
| **10062** | STAGE_RETRY_EXHAUSTED | All retry attempts exhausted |
| **10063** | STAGE_INPUT_TIMEOUT | Human input timed out |
| **10070** | CHECKPOINT_NOT_FOUND | Checkpoint does not exist |
| **10071** | CHECKPOINT_CREATE_FAILED | Failed to create checkpoint |
| **10072** | ROLLBACK_FAILED | Rollback operation failed |
| **10080** | SCRIPT_RUNTIME_NOT_FOUND | Script runtime not available |
| **10081** | SCRIPT_EXECUTION_FAILED | Script execution failed |
| **10082** | SCRIPT_TIMEOUT | Script execution timed out |
| **10083** | SCRIPT_SYNTAX_ERROR | Script has syntax errors |
| **10090** | CEL_PARSE_ERROR | CEL expression parse error |
| **10091** | CEL_EVAL_ERROR | CEL expression evaluation error |
| **10100** | TEMPLATE_NOT_FOUND | Template does not exist |
| **10101** | TEMPLATE_CREATE_FAILED | Failed to create template |
| **10110** | DATABASE_ERROR | Database operation failed |
| **10111** | DATABASE_LOCKED | Database is locked |
| **10120** | WEBSOCKET_ERROR | WebSocket error |
| **10121** | WEBSOCKET_AUTH_FAILED | WebSocket authentication failed |

---

## 5. Rate Limits

| Endpoint Category | Rate Limit | Window |
|-------------------|------------|--------|
| Read operations | 1000 req | 1 minute |
| Write operations | 100 req | 1 minute |
| Execution start | 20 req | 1 minute |
| WebSocket connections | 10 | concurrent |

---

## Appendix A: Stage Configuration Schemas

See [Nexus-Flow Service Specification](./14-nexus-flow-service.md) §3 for detailed node type configurations.

---

## Appendix B: CEL Expression Examples

```cel
// Branch conditions
input.score > 80 && input.verified == true
stage.output.status == "success"
flow.retryCount < 3

// Loop iteration
items.filter(x, x.active).size() > 0

// Variable transformation
prev.data.map(x, x.name).join(", ")
```
