# Microservices Integration Test Specifications

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  

---

## Overview

This specification defines end-to-end integration tests for validating communication between all microservices. Tests verify request routing, error propagation, data consistency, and resilience patterns across service boundaries.

**Cross-References:**
- [Microservices Overview](./00-overview.md)
- [Testing Strategy](../05-features/20-testing/01-test-strategy.md)
- [Error Management](../06-error-management/00-overview.md)

---

## Test Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        Integration Test Framework                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                         Test Runner (Go)                                 ││
│  │  • testify/suite for test organization                                  ││
│  │  • httptest for HTTP assertions                                         ││
│  │  • testcontainers-go for service isolation                              ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                    │                                         │
│  ┌─────────────────────────────────┴─────────────────────────────────────┐  │
│  │                        Test Environment                                │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐    │  │
│  │  │ Gateway  │ │SpecMgr   │ │Chronicle │ │AI-Bridge │ │  Scout   │    │  │
│  │  │  :8080   │ │  :8081   │ │  :8083   │ │  :8082   │ │  :8093   │    │  │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘    │  │
│  │       │            │            │            │            │          │  │
│  │       └────────────┴────────────┴────────────┴────────────┘          │  │
│  │                              │                                        │  │
│  │                    ┌─────────┴─────────┐                              │  │
│  │                    │   SQLite (test)   │                              │  │
│  │                    │   In-memory DBs   │                              │  │
│  │                    └───────────────────┘                              │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## Test Categories

### 1. Gateway → Service Routing Tests

Verify that Gateway correctly routes requests to downstream services.

#### Test Suite: `gateway_routing_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| GW-001 | `TestGateway_RouteToSpecManager` | Verify /api/v1/projects routes to SpecManager | Gateway → SpecManager |
| GW-002 | `TestGateway_RouteToChronicle` | Verify /api/v1/projects/{id}/commits routes to Chronicle | Gateway → Chronicle |
| GW-003 | `TestGateway_RouteToAIBridge` | Verify /api/v1/ai/* routes to AI-Bridge | Gateway → AI-Bridge |
| GW-004 | `TestGateway_RouteToScout` | Verify /api/v1/search routes to Scout | Gateway → Scout |
| GW-005 | `TestGateway_RouteToVoiceCLI` | Verify /api/v1/voice/* routes to Voice-CLI | Gateway → Voice-CLI |
| GW-006 | `TestGateway_RouteToNexusFlow` | Verify /api/v1/flows/* routes to Nexus-Flow | Gateway → Nexus-Flow |
| GW-007 | `TestGateway_UnknownRoute_Returns404` | Unknown paths return 404 | Gateway |
| GW-008 | `TestGateway_HealthAggregation` | /health aggregates all service health | Gateway → All |

```go
// Example: GW-001
func (s *GatewayRoutingSuite) TestGateway_RouteToSpecManager() {
    // Arrange
    project := fixtures.NewProject("test-project")
    s.specManager.On("CreateProject", mock.Anything).Return(project, nil)
    
    // Act
    resp := s.gateway.POST("/api/v1/projects", 
        CreateProjectRequest{Name: "test-project"})
    
    // Assert
    s.Equal(http.StatusCreated, resp.StatusCode)
    s.specManager.AssertCalled(s.T(), "CreateProject", mock.Anything)
    
    var result ProjectResponse
    s.NoError(json.Unmarshal(resp.Body, &result))
    s.Equal("test-project", result.Data.Name)
}
```

---

### 2. Authentication Flow Tests

Verify JWT token validation and propagation across services.

#### Test Suite: `auth_flow_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| AUTH-001 | `TestAuth_ValidJWT_PassesThrough` | Valid JWT allows request to proceed | Gateway → SpecManager |
| AUTH-002 | `TestAuth_InvalidJWT_Returns401` | Invalid JWT returns 401 Unauthorized | Gateway |
| AUTH-003 | `TestAuth_ExpiredJWT_Returns401` | Expired JWT returns 401 with refresh hint | Gateway |
| AUTH-004 | `TestAuth_MissingJWT_Returns401` | Missing Authorization header returns 401 | Gateway |
| AUTH-005 | `TestAuth_APIKey_ValidatesCorrectly` | API key authentication works | Gateway → SpecManager |
| AUTH-006 | `TestAuth_TokenPropagation` | Token propagates to downstream services | Gateway → SpecManager → Chronicle |
| AUTH-007 | `TestAuth_ServiceToService` | Internal service auth bypasses JWT | SpecManager → Chronicle |

```go
// Example: AUTH-006
func (s *AuthFlowSuite) TestAuth_TokenPropagation() {
    // Arrange
    token := s.generateValidJWT("user-123")
    
    // Act: Create project (SpecManager) which triggers commit (Chronicle)
    resp := s.gateway.POST("/api/v1/projects",
        CreateProjectRequest{Name: "test"},
        WithAuthHeader(token))
    
    // Assert: Both services received the token
    s.Equal(http.StatusCreated, resp.StatusCode)
    s.specManager.AssertHeaderReceived("Authorization", "Bearer "+token)
    s.chronicle.AssertHeaderReceived("Authorization", "Bearer "+token)
}
```

---

### 3. Project Lifecycle Tests

End-to-end tests for complete project workflows.

#### Test Suite: `project_lifecycle_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| PROJ-001 | `TestProject_CreateWithInitialCommit` | Creating project creates initial commit | Gateway → SpecManager → Chronicle |
| PROJ-002 | `TestProject_CreateSpec_TriggersIndexing` | Creating spec triggers Scout indexing | Gateway → SpecManager → Scout |
| PROJ-003 | `TestProject_UpdateSpec_CreatesCommit` | Updating spec creates Chronicle commit | Gateway → SpecManager → Chronicle |
| PROJ-004 | `TestProject_DeleteSpec_UpdatesIndex` | Deleting spec updates Scout index | Gateway → SpecManager → Scout |
| PROJ-005 | `TestProject_Rollback_RestoresState` | Rollback restores SpecManager state | Gateway → Chronicle → SpecManager |
| PROJ-006 | `TestProject_Search_ReturnsIndexedContent` | Search returns recently indexed content | Gateway → Scout → SpecManager |
| PROJ-007 | `TestProject_Export_IncludesHistory` | Export includes Chronicle history | Gateway → SpecManager → Chronicle |

```go
// Example: PROJ-001
func (s *ProjectLifecycleSuite) TestProject_CreateWithInitialCommit() {
    // Arrange
    token := s.authenticate("user@example.com")
    
    // Act: Create project through Gateway
    createResp := s.gateway.POST("/api/v1/projects",
        CreateProjectRequest{
            Name:        "my-project",
            Description: "Test project",
        },
        WithAuthHeader(token))
    
    s.Require().Equal(http.StatusCreated, createResp.StatusCode)
    
    var project ProjectResponse
    s.NoError(json.Unmarshal(createResp.Body, &project))
    
    // Assert: Initial commit was created in Chronicle
    commitsResp := s.gateway.GET(
        fmt.Sprintf("/api/v1/projects/%s/commits", project.Data.Id),
        WithAuthHeader(token))
    
    s.Equal(http.StatusOK, commitsResp.StatusCode)
    
    var commits CommitListResponse
    s.NoError(json.Unmarshal(commitsResp.Body, &commits))
    
    s.Len(commits.Data, 1)
    s.Contains(commits.Data[0].Message, "Initial commit")
}
```

---

### 4. Spec Edit → Commit Flow Tests

Verify the complete flow from editing specs to Chronicle commits.

#### Test Suite: `spec_commit_flow_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| COMMIT-001 | `TestSpecEdit_CreatesCommit` | Editing spec creates commit | SpecManager → Chronicle |
| COMMIT-002 | `TestSpecEdit_CommitContainsDiff` | Commit contains correct diff | SpecManager → Chronicle |
| COMMIT-003 | `TestMultipleEdits_BatchedCommit` | Multiple rapid edits batch into one commit | SpecManager → Chronicle |
| COMMIT-004 | `TestSpecMove_CreatesRenameCommit` | Moving spec creates rename commit | SpecManager → Chronicle |
| COMMIT-005 | `TestSpecDelete_CreatesDeleteCommit` | Deleting spec creates delete commit | SpecManager → Chronicle |
| COMMIT-006 | `TestConcurrentEdits_ConflictDetection` | Concurrent edits detect conflicts | SpecManager → Chronicle |
| COMMIT-007 | `TestRollback_RestoresContent` | Rolling back restores spec content | Chronicle → SpecManager |

```go
// Example: COMMIT-002
func (s *SpecCommitFlowSuite) TestSpecEdit_CommitContainsDiff() {
    // Arrange
    project := s.createProject("test-project")
    spec := s.createSpec(project.Id, "feature.md", "# Original Content")
    
    // Act: Update spec content
    s.gateway.PUT(
        fmt.Sprintf("/api/v1/projects/%s/specs/%s", project.Id, spec.Id),
        UpdateSpecRequest{Content: "# Updated Content\n\nNew paragraph"},
        s.authHeader())
    
    // Assert: Commit contains the diff
    commits := s.getCommits(project.Id)
    s.Require().Len(commits, 2) // Initial + update
    
    diff := s.getCommitDiff(project.Id, commits[0].Id)
    s.Contains(diff.Files[0].Hunks[0].Lines, DiffLine{
        Type:    "deletion",
        Content: "# Original Content",
    })
    s.Contains(diff.Files[0].Hunks[0].Lines, DiffLine{
        Type:    "addition", 
        Content: "# Updated Content",
    })
}
```

---

### 5. Search & Indexing Flow Tests

Verify Scout indexing and search integration.

#### Test Suite: `search_indexing_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| SEARCH-001 | `TestSpecCreate_IndexedForSearch` | New spec is searchable | SpecManager → Scout |
| SEARCH-002 | `TestSpecUpdate_IndexUpdated` | Updated spec content is searchable | SpecManager → Scout |
| SEARCH-003 | `TestSpecDelete_RemovedFromIndex` | Deleted spec not in search results | SpecManager → Scout |
| SEARCH-004 | `TestHybridSearch_CombinesFTSAndVector` | Hybrid search uses both FTS and vector | Scout |
| SEARCH-005 | `TestRAGContext_ReturnsFormattedChunks` | RAG endpoint returns formatted context | Gateway → Scout |
| SEARCH-006 | `TestSearchAcrossProjects` | Search respects project boundaries | Gateway → Scout |
| SEARCH-007 | `TestIndexConsistency_AfterRollback` | Index consistent after rollback | Chronicle → SpecManager → Scout |

```go
// Example: SEARCH-001
func (s *SearchIndexingSuite) TestSpecCreate_IndexedForSearch() {
    // Arrange
    project := s.createProject("searchable-project")
    
    // Act: Create spec with unique content
    spec := s.createSpec(project.Id, "unique-feature.md", 
        "# Quantum Entanglement Protocol\n\nThis spec describes...")
    
    // Wait for async indexing (with timeout)
    s.waitForIndexing(project.Id, spec.Id, 5*time.Second)
    
    // Assert: Spec is searchable
    results := s.search(project.Id, "Quantum Entanglement Protocol")
    
    s.Require().Len(results.Data, 1)
    s.Equal(spec.Id, results.Data[0].SpecId)
    s.Contains(results.Data[0].Snippet, "Quantum Entanglement")
}
```

---

### 6. AI-Bridge Integration Tests

Verify AI-Bridge communication with other services.

#### Test Suite: `ai_bridge_integration_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| AI-001 | `TestAI_ChatWithRAGContext` | Chat includes Scout RAG context | Gateway → AI-Bridge → Scout |
| AI-002 | `TestAI_StreamingResponse` | SSE streaming works through Gateway | Gateway → AI-Bridge |
| AI-003 | `TestAI_CodeGeneration_SavesFile` | Code generation saves to SpecManager | AI-Bridge → SpecManager |
| AI-004 | `TestAI_ProviderFallback` | Provider fallback on failure | AI-Bridge |
| AI-005 | `TestAI_RateLimiting` | Rate limiting applied correctly | Gateway → AI-Bridge |
| AI-006 | `TestAI_ConversationPersistence` | Conversation saved to database | AI-Bridge |
| AI-007 | `TestAI_EmbeddingGeneration` | Embeddings generated for Scout | AI-Bridge → Scout |

```go
// Example: AI-001
func (s *AIBridgeIntegrationSuite) TestAI_ChatWithRAGContext() {
    // Arrange
    project := s.createProject("ai-project")
    s.createSpec(project.Id, "architecture.md", 
        "# System Architecture\n\nThe system uses microservices...")
    s.waitForIndexing(project.Id)
    
    // Act: Chat with context retrieval
    resp := s.gateway.POST("/api/v1/ai/chat",
        ChatRequest{
            ProjectId: project.Id,
            Messages: []Message{
                {Role: "user", Content: "Explain the system architecture"},
            },
            UseRAG: true,
        },
        s.authHeader())
    
    // Assert: Response includes RAG context
    s.Equal(http.StatusOK, resp.StatusCode)
    
    var chat ChatResponse
    s.NoError(json.Unmarshal(resp.Body, &chat))
    
    s.NotEmpty(chat.Data.Context)
    s.Contains(chat.Data.Context[0].Content, "microservices")
}
```

---

### 7. Voice-CLI Integration Tests

Verify Voice-CLI WebSocket and transcription flows.

#### Test Suite: `voice_cli_integration_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| VOICE-001 | `TestVoice_WebSocketConnection` | WebSocket connects through Gateway | Gateway → Voice-CLI |
| VOICE-002 | `TestVoice_AudioStreamTranscription` | Audio stream transcribes correctly | Gateway → Voice-CLI |
| VOICE-003 | `TestVoice_TranscriptSavedToSpec` | Transcript saved as spec | Voice-CLI → SpecManager |
| VOICE-004 | `TestVoice_VADDetection` | Voice activity detection works | Voice-CLI |
| VOICE-005 | `TestVoice_SessionRecovery` | Session recovers from disconnect | Gateway → Voice-CLI |
| VOICE-006 | `TestVoice_CommandRecognition` | Voice commands parsed correctly | Voice-CLI → Nexus-Flow |

```go
// Example: VOICE-002
func (s *VoiceCLIIntegrationSuite) TestVoice_AudioStreamTranscription() {
    // Arrange
    project := s.createProject("voice-project")
    ws := s.connectWebSocket("/api/v1/voice/stream?project_id=" + project.Id)
    defer ws.Close()
    
    // Act: Send audio chunks
    audioData := s.loadTestAudio("hello-world.pcm16")
    for _, chunk := range s.chunkAudio(audioData, 4096) {
        ws.WriteMessage(websocket.BinaryMessage, chunk)
    }
    
    // Signal end of audio
    ws.WriteJSON(map[string]string{"type": "end_stream"})
    
    // Assert: Receive transcription
    var result TranscriptionResult
    s.NoError(ws.ReadJSON(&result))
    
    s.Equal("complete", result.Status)
    s.Contains(strings.ToLower(result.Text), "hello world")
}
```

---

### 8. Nexus-Flow Execution Tests

Verify Nexus-Flow pipeline execution across services.

#### Test Suite: `nexus_flow_execution_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| FLOW-001 | `TestFlow_ExecutionWithAIStage` | Flow with AI stage executes | Nexus-Flow → AI-Bridge |
| FLOW-002 | `TestFlow_ExecutionWithFileStage` | Flow with file stage executes | Nexus-Flow → SpecManager |
| FLOW-003 | `TestFlow_ConditionalBranching` | CEL conditions route correctly | Nexus-Flow |
| FLOW-004 | `TestFlow_ParallelExecution` | Parallel stages execute concurrently | Nexus-Flow |
| FLOW-005 | `TestFlow_WebSocketEvents` | Execution events stream correctly | Gateway → Nexus-Flow |
| FLOW-006 | `TestFlow_HumanInLoop_Pauses` | Human-in-loop pauses execution | Nexus-Flow |
| FLOW-007 | `TestFlow_RetryOnFailure` | Failed stages retry correctly | Nexus-Flow |
| FLOW-008 | `TestFlow_Rollback_OnError` | Flow rollback on critical error | Nexus-Flow → SpecManager → Chronicle |

```go
// Example: FLOW-001
func (s *NexusFlowExecutionSuite) TestFlow_ExecutionWithAIStage() {
    // Arrange
    project := s.createProject("flow-project")
    flow := s.createFlow(project.Id, FlowDefinition{
        Name: "ai-summary-flow",
        Stages: []Stage{
            {
                Id:   "read-spec",
                Type: "file_read",
                Config: map[string]any{
                    "path": "README.md",
                },
            },
            {
                Id:   "summarize",
                Type: "ai_prompt",
                Config: map[string]any{
                    "prompt":   "Summarize: {{stages.read-spec.output}}",
                    "provider": "ollama",
                    "model":    "llama3.2",
                },
                DependsOn: []string{"read-spec"},
            },
        },
    })
    
    // Create the README spec
    s.createSpec(project.Id, "README.md", "# My Project\n\nThis is a test project.")
    
    // Act: Execute flow
    ws := s.connectExecutionWebSocket(flow.Id)
    defer ws.Close()
    
    execResp := s.gateway.POST(
        fmt.Sprintf("/api/v1/flows/%s/execute", flow.Id),
        ExecuteRequest{},
        s.authHeader())
    
    s.Equal(http.StatusAccepted, execResp.StatusCode)
    
    // Assert: Receive stage completion events
    events := s.collectEvents(ws, 30*time.Second)
    
    s.assertEventSequence(events, []string{
        "execution_started",
        "stage_started:read-spec",
        "stage_completed:read-spec",
        "stage_started:summarize",
        "stage_completed:summarize",
        "execution_completed",
    })
    
    // Verify AI output
    summarizeEvent := s.findEvent(events, "stage_completed:summarize")
    s.Contains(summarizeEvent.Output, "project")
}
```

---

### 9. Error Propagation Tests

Verify errors propagate correctly with proper codes and context.

#### Test Suite: `error_propagation_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| ERR-001 | `TestError_SpecManagerError_PropagatesToGateway` | 3xxx errors reach client | Gateway → SpecManager |
| ERR-002 | `TestError_ChronicleError_PropagatesToGateway` | 4xxx errors reach client | Gateway → Chronicle |
| ERR-003 | `TestError_ScoutError_PropagatesToGateway` | 5xxx errors reach client | Gateway → Scout |
| ERR-004 | `TestError_AIBridgeError_PropagatesToGateway` | 6xxx errors reach client | Gateway → AI-Bridge |
| ERR-005 | `TestError_StackTraceIncluded` | Stack trace in error response | All |
| ERR-006 | `TestError_RequestIdPropagated` | Request ID in all error responses | All |
| ERR-007 | `TestError_CircuitBreaker_Opens` | Circuit breaker opens on failures | Gateway |
| ERR-008 | `TestError_RetryableFlag_Respected` | Retryable errors are retried | Gateway |

```go
// Example: ERR-001
func (s *ErrorPropagationSuite) TestError_SpecManagerError_PropagatesToGateway() {
    // Arrange: SpecManager will return validation error
    s.specManager.SetNextError(errors.NewAppError(
        3012, "ERR_SPEC_TITLE_REQUIRED", "title is required"))
    
    // Act: Make request through Gateway
    resp := s.gateway.POST("/api/v1/projects/test/specs",
        CreateSpecRequest{Content: "# No title"},
        s.authHeader())
    
    // Assert: Error propagated with correct structure
    s.Equal(http.StatusBadRequest, resp.StatusCode)
    
    var errResp ErrorResponse
    s.NoError(json.Unmarshal(resp.Body, &errResp))
    
    s.False(errResp.Success)
    s.Equal(3012, errResp.Error.Code)
    s.Equal("ERR_SPEC_TITLE_REQUIRED", errResp.Error.Constant)
    s.Contains(errResp.Error.Message, "title is required")
    s.NotEmpty(errResp.Error.StackTrace)
    s.NotEmpty(resp.Header.Get("X-Request-ID"))
}
```

---

### 10. Circuit Breaker & Resilience Tests

Verify resilience patterns work correctly.

#### Test Suite: `resilience_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| RES-001 | `TestCircuitBreaker_OpensAfterFailures` | Circuit opens after threshold | Gateway → SpecManager |
| RES-002 | `TestCircuitBreaker_HalfOpenRecovery` | Circuit recovers in half-open | Gateway → SpecManager |
| RES-003 | `TestTimeout_EnforcedOnSlowService` | Slow services timeout | Gateway → AI-Bridge |
| RES-004 | `TestRetry_ExponentialBackoff` | Retries use exponential backoff | Gateway |
| RES-005 | `TestBulkhead_IsolatesFailures` | Failures don't cascade | Gateway → All |
| RES-006 | `TestGracefulDegradation_PartialResponse` | Partial data on partial failure | Gateway → Scout |

```go
// Example: RES-001
func (s *ResilienceSuite) TestCircuitBreaker_OpensAfterFailures() {
    // Arrange: SpecManager returns errors
    s.specManager.SetAlwaysFail(true)
    
    // Act: Make requests until circuit opens
    var lastResp *http.Response
    for i := 0; i < 10; i++ {
        lastResp = s.gateway.GET("/api/v1/projects", s.authHeader())
    }
    
    // Assert: Circuit breaker opened
    s.Equal(http.StatusServiceUnavailable, lastResp.StatusCode)
    
    var errResp ErrorResponse
    s.NoError(json.Unmarshal(lastResp.Body, &errResp))
    s.Equal(2050, errResp.Error.Code) // ERR_CIRCUIT_OPEN
    s.Contains(errResp.Error.Message, "circuit breaker open")
}
```

---

### 11. Cross-Service Transaction Tests

Verify data consistency across services.

#### Test Suite: `cross_service_transaction_test.go`

| Test ID | Test Name | Description | Services |
|---------|-----------|-------------|----------|
| TXN-001 | `TestTransaction_RollbackOnPartialFailure` | Partial failure triggers rollback | SpecManager → Chronicle |
| TXN-002 | `TestTransaction_ConsistencyAfterCrash` | Data consistent after recovery | All |
| TXN-003 | `TestTransaction_IdempotentOperations` | Retry doesn't duplicate data | All |
| TXN-004 | `TestEventualConsistency_IndexSync` | Index eventually consistent | SpecManager → Scout |

---

### 12. Performance Integration Tests

Verify performance under load.

#### Test Suite: `performance_integration_test.go`

| Test ID | Test Name | Description | Target |
|---------|-----------|-------------|--------|
| PERF-001 | `TestLatency_GatewayToSpecManager` | Routing latency < 10ms | Gateway → SpecManager |
| PERF-002 | `TestLatency_SearchQuery` | Search latency < 100ms | Gateway → Scout |
| PERF-003 | `TestThroughput_ConcurrentRequests` | 100 concurrent requests/sec | Gateway |
| PERF-004 | `TestMemory_UnderLoad` | Memory stable under load | All |

---

## Test Environment Setup

### Docker Compose for Integration Tests

```yaml
# docker-compose.integration.yaml
version: '3.8'

services:
  gateway:
    build: ./cmd/gateway
    ports:
      - "8080:8080"
    environment:
      - ENV=test
      - LOG_LEVEL=debug
    depends_on:
      - specmanager
      - chronicle
      - scout
      - ai-bridge
      
  specmanager:
    build: ./cmd/specmanager
    ports:
      - "8081:8081"
    environment:
      - DATABASE_PATH=/data/test-projects.db
    volumes:
      - test-data:/data
      
  chronicle:
    build: ./cmd/chronicle
    ports:
      - "8083:8083"
    volumes:
      - test-data:/data
      
  scout:
    build: ./cmd/scout
    ports:
      - "8093:8093"
    volumes:
      - test-data:/data
      
  ai-bridge:
    build: ./cmd/aibridge
    ports:
      - "8082:8082"
    environment:
      - OLLAMA_HOST=http://ollama:11434
      
  nexus-flow:
    build: ./cmd/nexusflow
    ports:
      - "9000:9000"
    volumes:
      - test-data:/data
      
  voice-cli:
    build: ./cmd/voicecli
    ports:
      - "8084:8084"

volumes:
  test-data:
```

### Test Configuration

```go
// integration_test_config.go
type IntegrationTestConfig struct {
    GatewayURL     string `env:"GATEWAY_URL" default:"http://localhost:8080"`
    SpecManagerURL string `env:"SPECMANAGER_URL" default:"http://localhost:8081"`
    ChronicleURL   string `env:"CHRONICLE_URL" default:"http://localhost:8083"`
    ScoutURL       string `env:"SCOUT_URL" default:"http://localhost:8093"`
    AIBridgeURL    string `env:"AIBRIDGE_URL" default:"http://localhost:8082"`
    NexusFlowURL   string `env:"NEXUSFLOW_URL" default:"http://localhost:9000"`
    VoiceCLIURL    string `env:"VOICECLI_URL" default:"http://localhost:8084"`
    
    TestTimeout    time.Duration `env:"TEST_TIMEOUT" default:"30s"`
    RetryAttempts  int           `env:"RETRY_ATTEMPTS" default:"3"`
}
```

---

## Test Execution

### Running All Integration Tests

```bash
# Start services
docker-compose -f docker-compose.integration.yaml up -d

# Wait for services to be ready
./scripts/wait-for-services.sh

# Run tests
go test -v -tags=integration ./tests/integration/...

# Generate coverage report
go test -coverprofile=coverage.out -tags=integration ./tests/integration/...
go tool cover -html=coverage.out -o coverage.html

# Cleanup
docker-compose -f docker-compose.integration.yaml down -v
```

### Running Specific Test Suites

```bash
# Gateway routing tests only
go test -v -tags=integration -run "TestGateway" ./tests/integration/...

# Error propagation tests
go test -v -tags=integration -run "TestError" ./tests/integration/...

# Performance tests (longer timeout)
go test -v -tags=integration -timeout 5m -run "TestPerf" ./tests/integration/...
```

---

## CI/CD Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/integration-tests.yaml
name: Integration Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  integration-tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: '1.21'
          
      - name: Start services
        run: |
          docker-compose -f docker-compose.integration.yaml up -d
          ./scripts/wait-for-services.sh
          
      - name: Run integration tests
        run: |
          go test -v -tags=integration \
            -coverprofile=coverage.out \
            -timeout 10m \
            ./tests/integration/...
            
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.out
          flags: integration
          
      - name: Cleanup
        if: always()
        run: docker-compose -f docker-compose.integration.yaml down -v
```

---

## Test Metrics & Reporting

### Success Criteria

| Metric | Target | Critical |
|--------|--------|----------|
| Test Pass Rate | ≥ 98% | ≥ 95% |
| Gateway Routing Coverage | 100% | 100% |
| Error Propagation Coverage | 100% | 100% |
| P95 Latency | < 200ms | < 500ms |
| Flaky Test Rate | < 1% | < 5% |

### Test Categories by Priority

| Priority | Category | Blocking? |
|----------|----------|-----------|
| P0 | Authentication, Error Propagation | Yes |
| P1 | Project Lifecycle, Routing | Yes |
| P2 | Search, AI Integration | No |
| P3 | Performance, Resilience | No |

---

## See Also

- [Microservices Overview](./00-overview.md)
- [Testing Strategy](../05-features/20-testing/01-test-strategy.md)
- [Error Management](../06-error-management/00-overview.md)
- [CI/CD Pipeline](../08-roadmap-overview/07-integration-tests-pipeline.md)
