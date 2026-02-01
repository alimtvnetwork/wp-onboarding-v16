# End-to-End Integration Test Scenarios

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-28  

---

## Overview

Six critical E2E integration test scenarios validating core system workflows. Each scenario includes setup, execution steps, assertions, and cleanup procedures.

---

## Test Environment

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         E2E TEST ARCHITECTURE                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                      │
│  │   Test DB   │    │  LLM Mock   │    │  FS Sandbox │                      │
│  │  (SQLite)   │    │  (WireMock) │    │  (/tmp/e2e) │                      │
│  └──────┬──────┘    └──────┬──────┘    └──────┬──────┘                      │
│         │                  │                  │                              │
│         └──────────────────┼──────────────────┘                              │
│                            │                                                 │
│                    ┌───────▼───────┐                                         │
│                    │  Test Runner  │                                         │
│                    │   (Go test)   │                                         │
│                    └───────┬───────┘                                         │
│                            │                                                 │
│         ┌──────────────────┼──────────────────┐                              │
│         │                  │                  │                              │
│         ▼                  ▼                  ▼                              │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                      │
│  │   Backend   │    │   Frontend  │    │   Workers   │                      │
│  │   Server    │    │  (Playwright)│   │   (Async)   │                      │
│  └─────────────┘    └─────────────┘    └─────────────┘                      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Test Infrastructure

```go
// internal/e2e/infrastructure.go
package e2e

import (
    "os"
    "path/filepath"
    "testing"
    
    "github.com/testcontainers/testcontainers-go"
)

type TestEnvironment struct {
    DB          *gorm.DB
    Server      *httptest.Server
    LLMMock     *wiremock.Server
    WorkDir     string
    Cleanup     func()
}

func SetupEnvironment(t *testing.T) *TestEnvironment {
    t.Helper()
    
    // Create temp work directory
    workDir, err := os.MkdirTemp("", "e2e-test-*")
    require.NoError(t, err)
    
    // Initialize test database
    db := setupTestDB(t)
    
    // Seed required data
    seeder := seed.NewSeeder(db, "testdata/Prompts", "test")
    require.NoError(t, seeder.Run())
    
    // Start LLM mock server
    llmMock := startLLMMock(t)
    
    // Start application server
    app := server.New(db, server.Config{
        LLMEndpoint: llmMock.URL(),
        WorkDir:     workDir,
    })
    srv := httptest.NewServer(app.Handler())
    
    return &TestEnvironment{
        DB:      db,
        Server:  srv,
        LLMMock: llmMock,
        WorkDir: workDir,
        Cleanup: func() {
            srv.Close()
            llmMock.Stop()
            os.RemoveAll(workDir)
        },
    }
}
```

---

## Scenario 1: Voice-to-Spec Pipeline

### Description

Tests the complete flow from voice audio input through transcription, proofreading, enhancement, instruction generation, and spec file creation.

### Test Data

```go
// testdata/audio/sample-voice-input.wav
// 10-second audio: "I want to add a user authentication feature with 
// email and password login, OAuth support for Google and GitHub, 
// and password reset functionality via email"

var VoiceToSpecFixtures = struct {
    AudioFile       string
    ExpectedTranscript string
    ExpectedCategory   string
    ExpectedArtifacts  []string
}{
    AudioFile: "testdata/audio/sample-voice-input.wav",
    ExpectedTranscript: "I want to add a user authentication feature with email and password login, OAuth support for Google and GitHub, and password reset functionality via email",
    ExpectedCategory: "feature",
    ExpectedArtifacts: []string{
        "ideas/01-idea-user-authentication.md",
        "instructions/01-instruction-user-authentication.md",
    },
}
```

### Test Implementation

```go
// internal/e2e/voice_to_spec_test.go
package e2e

func TestVoiceToSpecPipeline(t *testing.T) {
    env := SetupEnvironment(t)
    defer env.Cleanup()
    
    // Phase 1: Project Setup
    t.Run("create_project", func(t *testing.T) {
        project := createTestProject(env, "voice-test-project")
        require.NotEmpty(t, project.Id)
        
        // Initialize project folder structure
        initProjectFolders(env.WorkDir, project.Slug)
    })
    
    // Phase 2: Audio Upload & Transcription
    t.Run("upload_and_transcribe", func(t *testing.T) {
        // Configure LLM mock for Whisper response
        env.LLMMock.Stub(wiremock.Post("/v1/audio/transcriptions").
            WillReturn(wiremock.JSON(map[string]string{
                "text": VoiceToSpecFixtures.ExpectedTranscript,
            })))
        
        // Upload audio file
        audioData, err := os.ReadFile(VoiceToSpecFixtures.AudioFile)
        require.NoError(t, err)
        
        resp := httpPost(env.Server.URL+"/api/voice/upload", multipartForm{
            "audio": audioData,
            "format": "wav",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Transcript string `json:"transcript"`
            Duration   float64 `json:"duration"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Equal(t, VoiceToSpecFixtures.ExpectedTranscript, result.Transcript)
        assert.Greater(t, result.Duration, 0.0)
    })
    
    // Phase 3: Proofreading
    t.Run("proofread_transcript", func(t *testing.T) {
        env.LLMMock.Stub(wiremock.Post("/v1/chat/completions").
            WithRequestBody(wiremock.ContainingString("proofread")).
            WillReturn(wiremock.JSON(chatCompletionResponse(
                "I want to add a user authentication feature with email and password login, OAuth support for Google and GitHub, and password reset functionality via email.",
            ))))
        
        resp := httpPost(env.Server.URL+"/api/instruction/proofread", jsonBody{
            "runId": testRunId,
            "rawInput": VoiceToSpecFixtures.ExpectedTranscript,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            ProofreadInput string `json:"proofreadInput"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Contains(t, result.ProofreadInput, "authentication")
    })
    
    // Phase 4: Classification
    t.Run("classify_input", func(t *testing.T) {
        env.LLMMock.Stub(wiremock.Post("/v1/chat/completions").
            WithRequestBody(wiremock.ContainingString("classify")).
            WillReturn(wiremock.JSON(chatCompletionResponse(
                `{"category": "feature", "confidence": 0.95}`,
            ))))
        
        resp := httpPost(env.Server.URL+"/api/instruction/classify", jsonBody{
            "runId": testRunId,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Category   string  `json:"category"`
            Confidence float64 `json:"confidence"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Equal(t, VoiceToSpecFixtures.ExpectedCategory, result.Category)
        assert.Greater(t, result.Confidence, 0.8)
    })
    
    // Phase 5: Enhancement
    t.Run("enhance_input", func(t *testing.T) {
        env.LLMMock.Stub(wiremock.Post("/v1/chat/completions").
            WithRequestBody(wiremock.ContainingString("enhance")).
            WillReturn(wiremock.JSON(chatCompletionResponse(
                `## User Authentication Feature

### Overview
Implement a comprehensive user authentication system...

### Requirements
1. Email/password login with validation
2. OAuth 2.0 integration (Google, GitHub)
3. Password reset via email with secure tokens`,
            ))))
        
        resp := httpPost(env.Server.URL+"/api/instruction/enhance", jsonBody{
            "runId": testRunId,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
    })
    
    // Phase 6: Instruction Generation
    t.Run("generate_instruction", func(t *testing.T) {
        env.LLMMock.Stub(wiremock.Post("/v1/chat/completions").
            WithRequestBody(wiremock.ContainingString("generate")).
            WillReturn(streamingResponse([]string{
                `# User Authentication Feature`,
                `

**Version:** 1.0.0`,
                `
**Status:** Draft`,
                `

## Overview`,
                `

Implement user authentication with...`,
            })))
        
        // Use SSE client for streaming response
        events := make(chan string, 100)
        go subscribeSSE(env.Server.URL+"/api/instruction/generate/"+testRunId, events)
        
        var fullContent strings.Builder
        timeout := time.After(30 * time.Second)
        
        loop:
        for {
            select {
            case event := <-events:
                if event == "[DONE]" {
                    break loop
                }
                fullContent.WriteString(event)
            case <-timeout:
                t.Fatal("Generation timeout")
            }
        }
        
        assert.Contains(t, fullContent.String(), "User Authentication")
        assert.Contains(t, fullContent.String(), "## Overview")
    })
    
    // Phase 7: Artifact Storage
    t.Run("verify_artifacts_saved", func(t *testing.T) {
        // Wait for async artifact saving
        time.Sleep(500 * time.Millisecond)
        
        for _, expectedPath := range VoiceToSpecFixtures.ExpectedArtifacts {
            fullPath := filepath.Join(env.WorkDir, "voice-test-project", expectedPath)
            
            _, err := os.Stat(fullPath)
            assert.NoError(t, err, "Artifact not found: %s", expectedPath)
            
            // Verify content
            content, _ := os.ReadFile(fullPath)
            assert.Contains(t, string(content), "authentication")
        }
        
        // Verify database records
        var artifacts []RunArtifact
        env.DB.Where("RunId = ?", testRunId).Find(&artifacts)
        assert.Len(t, artifacts, 2)
    })
    
    // Phase 8: Verify Complete Pipeline
    t.Run("verify_run_completed", func(t *testing.T) {
        var run InstructionRun
        env.DB.First(&run, "Id = ?", testRunId)
        
        assert.Equal(t, "completed", run.Status)
        assert.NotNil(t, run.CompletedAt)
        assert.Greater(t, run.TokensUsed, 0)
        assert.Greater(t, run.DurationMs, int64(0))
    })
}
```

### Assertions

| Step | Assertion | Error Code |
|------|-----------|------------|
| Audio upload | File accepted, format validated | ERR_AUDIO_INVALID_FORMAT (4501) |
| Transcription | Text returned within 30s | ERR_VOICE_TRANSCRIPTION_TIMEOUT (4510) |
| Classification | Category matches expected | ERR_CLASSIFICATION_FAILED (7301) |
| Enhancement | Output contains structured sections | ERR_ENHANCEMENT_FAILED (7302) |
| Generation | Spec file created with valid format | ERR_GENERATION_FAILED (7303) |
| Storage | Artifacts saved to filesystem | ERR_ARTIFACT_SAVE_FAILED (6510) |
| Database | Run status = completed | ERR_RUN_STATE_INVALID (5301) |

---

## Scenario 2: Idea Promotion Flow

### Description

Tests promoting a raw idea from the `ideas/` folder to a structured instruction in `instructions/`, including RAG context injection and versioning.

### Test Data

```go
var IdeaPromotionFixtures = struct {
    InitialIdea      string
    RelatedSpecs     []string
    ExpectedInstruction string
}{
    InitialIdea: `# Voice-Controlled Navigation

## Raw Idea
Allow users to navigate the spec tree using voice commands.
"Go to authentication spec" or "Open database design".

## Notes
- Need voice recognition
- Should work with folder tree component
- Consider accessibility
`,
    RelatedSpecs: []string{
        "05-features/01-authentication/00-overview.md",
        "05-features/02-file-management/01-file-operations.md",
    },
    ExpectedInstruction: "instruction-voice-navigation.md",
}
```

### Test Implementation

```go
// internal/e2e/idea_promotion_test.go
package e2e

func TestIdeaPromotionFlow(t *testing.T) {
    env := SetupEnvironment(t)
    defer env.Cleanup()
    
    var projectId, ideaId string
    
    // Phase 1: Setup project with existing specs
    t.Run("setup_project_with_specs", func(t *testing.T) {
        project := createTestProject(env, "idea-promotion-test")
        projectId = project.Id
        
        // Create sample spec files for RAG context
        for _, specPath := range IdeaPromotionFixtures.RelatedSpecs {
            fullPath := filepath.Join(env.WorkDir, project.Slug, specPath)
            os.MkdirAll(filepath.Dir(fullPath), 0755)
            os.WriteFile(fullPath, []byte(sampleSpecContent(specPath)), 0644)
        }
        
        // Index files for RAG
        indexProjectFiles(env, projectId)
    })
    
    // Phase 2: Create initial idea
    t.Run("create_idea", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/ideas", jsonBody{
            "projectId": projectId,
            "content":   IdeaPromotionFixtures.InitialIdea,
            "title":     "Voice-Controlled Navigation",
            "tags":      []string{"voice", "navigation", "accessibility"},
        })
        require.Equal(t, http.StatusCreated, resp.StatusCode)
        
        var result struct {
            Id           string `json:"id"`
            RelativePath string `json:"relativePath"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        ideaId = result.Id
        assert.Contains(t, result.RelativePath, "ideas/")
        
        // Verify file created
        ideaPath := filepath.Join(env.WorkDir, "idea-promotion-test", result.RelativePath)
        _, err := os.Stat(ideaPath)
        assert.NoError(t, err)
    })
    
    // Phase 3: Verify RAG indexing
    t.Run("verify_idea_indexed", func(t *testing.T) {
        // Wait for async indexing
        time.Sleep(1 * time.Second)
        
        var artifact ArtifactRegistry
        err := env.DB.Where("ProjectId = ? AND ArtifactType = ?", projectId, "idea").
            First(&artifact).Error
        require.NoError(t, err)
        
        assert.Equal(t, "Voice-Controlled Navigation", artifact.Title)
        assert.Contains(t, artifact.Tags, "voice")
    })
    
    // Phase 4: Test RAG retrieval for related context
    t.Run("retrieve_related_context", func(t *testing.T) {
        // Mock embedding generation
        env.LLMMock.Stub(wiremock.Post("/v1/embeddings").
            WillReturn(wiremock.JSON(map[string]interface{}{
                "data": []map[string]interface{}{
                    {"embedding": generateMockEmbedding(384)},
                },
            })))
        
        resp := httpPost(env.Server.URL+"/api/rag/retrieve", jsonBody{
            "projectId": projectId,
            "query":     "voice navigation folder tree",
            "topK":      5,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Chunks []struct {
                Content     string  `json:"content"`
                Score       float64 `json:"score"`
                RelativePath string `json:"relativePath"`
            } `json:"chunks"`
            TotalTokens int `json:"totalTokens"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Greater(t, len(result.Chunks), 0)
        assert.Greater(t, result.TotalTokens, 0)
    })
    
    // Phase 5: Initiate promotion
    t.Run("promote_idea_to_instruction", func(t *testing.T) {
        // Mock LLM for instruction generation with context
        env.LLMMock.Stub(wiremock.Post("/v1/chat/completions").
            WithRequestBody(wiremock.ContainingString("promote")).
            WillReturn(wiremock.JSON(chatCompletionResponse(
                `# Voice-Controlled Navigation

**Version:** 1.0.0  
**Status:** Draft  
**Promoted From:** ideas/01-idea-voice-navigation.md

## Overview

Implement voice-controlled navigation for the specification tree...

## Related Specifications

- [Authentication](../05-features/01-authentication/00-overview.md)
- [File Operations](../05-features/02-file-management/01-file-operations.md)

## Requirements

### Functional Requirements

FR-001: System shall recognize voice commands for navigation
FR-002: System shall support "go to [spec name]" command pattern
FR-003: System shall provide audio feedback for navigation actions

## Implementation Tasks

1. Integrate Web Speech API
2. Create voice command parser
3. Connect to folder tree navigation
4. Add accessibility announcements`,
            ))))
        
        resp := httpPost(env.Server.URL+"/api/ideas/"+ideaId+"/promote", jsonBody{
            "includeContext": true,
            "targetCategory": "instruction",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            InstructionId   string `json:"instructionId"`
            RelativePath    string `json:"relativePath"`
            ContextChunks   int    `json:"contextChunksUsed"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.NotEmpty(t, result.InstructionId)
        assert.Contains(t, result.RelativePath, "instructions/")
        assert.Greater(t, result.ContextChunks, 0)
    })
    
    // Phase 6: Verify instruction created
    t.Run("verify_instruction_file", func(t *testing.T) {
        instructionPath := filepath.Join(env.WorkDir, "idea-promotion-test",
            "instructions/01-instruction-voice-navigation.md")
        
        content, err := os.ReadFile(instructionPath)
        require.NoError(t, err)
        
        contentStr := string(content)
        assert.Contains(t, contentStr, "**Promoted From:**")
        assert.Contains(t, contentStr, "## Related Specifications")
        assert.Contains(t, contentStr, "FR-001")
    })
    
    // Phase 7: Verify promotion tracking
    t.Run("verify_promotion_recorded", func(t *testing.T) {
        var idea ArtifactRegistry
        env.DB.First(&idea, "Id = ?", ideaId)
        
        // Idea should be marked as promoted (not deleted)
        assert.True(t, idea.IsPinned) // Promoted ideas get pinned
        
        // Instruction should exist
        var instruction ArtifactRegistry
        err := env.DB.Where("ProjectId = ? AND ArtifactType = ?", projectId, "instruction").
            First(&instruction).Error
        require.NoError(t, err)
        
        assert.Equal(t, "Voice-Controlled Navigation", instruction.Title)
    })
    
    // Phase 8: Test task decomposition
    t.Run("decompose_into_tasks", func(t *testing.T) {
        env.LLMMock.Stub(wiremock.Post("/v1/chat/completions").
            WithRequestBody(wiremock.ContainingString("decompose")).
            WillReturn(wiremock.JSON(chatCompletionResponse(
                `[
                    {"title": "Integrate Web Speech API", "order": 1, "dependsOn": []},
                    {"title": "Create voice command parser", "order": 2, "dependsOn": [1]},
                    {"title": "Connect to folder tree", "order": 3, "dependsOn": [2]},
                    {"title": "Add accessibility feedback", "order": 4, "dependsOn": [3]}
                ]`,
            ))))
        
        // Get the run ID from promotion
        var run InstructionRun
        env.DB.Where("ProjectId = ?", projectId).Order("CreatedAt DESC").First(&run)
        
        resp := httpPost(env.Server.URL+"/api/instruction/"+run.Id+"/decompose", nil)
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var tasks []InstructionTask
        env.DB.Where("RunId = ?", run.Id).Order("TaskOrder").Find(&tasks)
        
        assert.Len(t, tasks, 4)
        assert.Equal(t, "Integrate Web Speech API", tasks[0].Title)
        assert.Contains(t, tasks[2].DependsOn, "2") // Task 3 depends on task 2
    })
}
```

### Assertions

| Step | Assertion | Error Code |
|------|-----------|------------|
| Idea creation | File saved with correct naming | ERR_IDEA_CREATE_FAILED (5401) |
| RAG indexing | Idea appears in artifact registry | ERR_INDEX_FAILED (8201) |
| Context retrieval | Related specs returned | ERR_RETRIEVAL_FAILED (8301) |
| Promotion | Instruction file created | ERR_PROMOTION_FAILED (5410) |
| Cross-reference | Links to original idea preserved | ERR_REF_BROKEN (9201) |
| Task decomposition | Dependencies correctly mapped | ERR_DECOMPOSE_FAILED (7401) |

---

## Scenario 3: RAG Retrieval Accuracy

### Description

Tests RAG system accuracy across semantic search, hybrid retrieval, and context assembly with various query types.

### Test Data

```go
var RAGAccuracyFixtures = struct {
    IndexedDocuments []struct {
        Path    string
        Content string
        Tags    []string
    }
    TestQueries []struct {
        Query           string
        ExpectedMatches []string
        MinScore        float64
        MaxResults      int
    }
}{
    IndexedDocuments: []struct{
        Path    string
        Content string
        Tags    []string
    }{
        {
            Path:    "05-features/01-authentication/01-jwt-tokens.md",
            Content: "# JWT Token Authentication\n\nJSON Web Tokens (JWT) provide stateless authentication...",
            Tags:    []string{"auth", "jwt", "security"},
        },
        {
            Path:    "05-features/01-authentication/02-oauth.md",
            Content: "# OAuth 2.0 Integration\n\nOAuth provides third-party authentication...",
            Tags:    []string{"auth", "oauth", "google", "github"},
        },
        {
            Path:    "07-database-design/02-unified-schema.md",
            Content: "# Unified Database Schema\n\nAll models use GORM with PascalCase...",
            Tags:    []string{"database", "schema", "gorm"},
        },
    },
    TestQueries: []struct{
        Query           string
        ExpectedMatches []string
        MinScore        float64
        MaxResults      int
    }{
        {
            Query:           "How do I implement JWT authentication?",
            ExpectedMatches: []string{"01-jwt-tokens.md"},
            MinScore:        0.75,
            MaxResults:      3,
        },
        {
            Query:           "database schema foreign keys",
            ExpectedMatches: []string{"02-unified-schema.md"},
            MinScore:        0.70,
            MaxResults:      3,
        },
        {
            Query:           "third party login google github",
            ExpectedMatches: []string{"02-oauth.md"},
            MinScore:        0.72,
            MaxResults:      3,
        },
    },
}
```

### Test Implementation

```go
// internal/e2e/rag_accuracy_test.go
package e2e

func TestRAGRetrievalAccuracy(t *testing.T) {
    env := SetupEnvironment(t)
    defer env.Cleanup()
    
    var projectId string
    
    // Phase 1: Index test documents
    t.Run("index_documents", func(t *testing.T) {
        project := createTestProject(env, "rag-accuracy-test")
        projectId = project.Id
        
        for _, doc := range RAGAccuracyFixtures.IndexedDocuments {
            fullPath := filepath.Join(env.WorkDir, project.Slug, doc.Path)
            os.MkdirAll(filepath.Dir(fullPath), 0755)
            os.WriteFile(fullPath, []byte(doc.Content), 0644)
        }
        
        // Mock embedding endpoint
        env.LLMMock.Stub(wiremock.Post("/v1/embeddings").
            WillReturn(wiremock.Func(func(req *http.Request) *wiremock.Response {
                // Generate deterministic embeddings based on input
                var body struct {
                    Input string `json:"input"`
                }
                json.NewDecoder(req.Body).Decode(&body)
                
                embedding := generateDeterministicEmbedding(body.Input, 384)
                return wiremock.JSON(map[string]interface{}{
                    "data": []map[string]interface{}{
                        {"embedding": embedding},
                    },
                })
            })))
        
        // Trigger indexing
        resp := httpPost(env.Server.URL+"/api/rag/index", jsonBody{
            "projectId": projectId,
            "force":     true,
        })
        require.Equal(t, http.StatusAccepted, resp.StatusCode)
        
        // Wait for indexing to complete
        waitForCondition(t, 10*time.Second, func() bool {
            var registry []FileRegistry
            env.DB.Where("ProjectId = ? AND Status = ?", projectId, "indexed").Find(&registry)
            return len(registry) == len(RAGAccuracyFixtures.IndexedDocuments)
        })
    })
    
    // Phase 2: Verify chunk generation
    t.Run("verify_chunks_created", func(t *testing.T) {
        var chunks []ChunkRegistry
        env.DB.Joins("JOIN FileRegistry ON ChunkRegistry.FileRegistryId = FileRegistry.Id").
            Where("FileRegistry.ProjectId = ?", projectId).
            Find(&chunks)
        
        assert.Greater(t, len(chunks), 0)
        
        // Verify chunk properties
        for _, chunk := range chunks {
            assert.NotEmpty(t, chunk.Content)
            assert.Greater(t, chunk.TokenCount, 0)
            assert.LessOrEqual(t, chunk.TokenCount, 512) // Max chunk size
        }
    })
    
    // Phase 3: Test semantic search accuracy
    t.Run("semantic_search_accuracy", func(t *testing.T) {
        for _, query := range RAGAccuracyFixtures.TestQueries {
            t.Run(query.Query, func(t *testing.T) {
                resp := httpPost(env.Server.URL+"/api/rag/retrieve", jsonBody{
                    "projectId": projectId,
                    "query":     query.Query,
                    "topK":      query.MaxResults,
                    "method":    "semantic",
                })
                require.Equal(t, http.StatusOK, resp.StatusCode)
                
                var result struct {
                    Chunks []struct {
                        RelativePath string  `json:"relativePath"`
                        Score        float64 `json:"score"`
                        Content      string  `json:"content"`
                    } `json:"chunks"`
                }
                json.NewDecoder(resp.Body).Decode(&result)
                
                // Verify expected matches appear
                matchedPaths := make([]string, len(result.Chunks))
                for i, chunk := range result.Chunks {
                    matchedPaths[i] = chunk.RelativePath
                }
                
                for _, expected := range query.ExpectedMatches {
                    found := false
                    for _, path := range matchedPaths {
                        if strings.Contains(path, expected) {
                            found = true
                            break
                        }
                    }
                    assert.True(t, found, "Expected match not found: %s", expected)
                }
                
                // Verify score threshold
                if len(result.Chunks) > 0 {
                    assert.GreaterOrEqual(t, result.Chunks[0].Score, query.MinScore)
                }
            })
        }
    })
    
    // Phase 4: Test hybrid retrieval (semantic + keyword)
    t.Run("hybrid_retrieval", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/rag/retrieve", jsonBody{
            "projectId": projectId,
            "query":     "JWT token authentication security",
            "topK":      5,
            "method":    "hybrid",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Chunks []struct {
                RelativePath  string  `json:"relativePath"`
                Score         float64 `json:"score"`
                SemanticScore float64 `json:"semanticScore"`
                KeywordScore  float64 `json:"keywordScore"`
            } `json:"chunks"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        // Hybrid should return both scores
        for _, chunk := range result.Chunks {
            assert.Greater(t, chunk.SemanticScore, 0.0)
            // Keyword score may be 0 if no keyword matches
        }
        
        // RRF score should combine both
        assert.Greater(t, result.Chunks[0].Score, 0.0)
    })
    
    // Phase 5: Test context assembly with token budgeting
    t.Run("context_assembly_token_budget", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/rag/assemble-context", jsonBody{
            "projectId":   projectId,
            "query":       "authentication implementation",
            "tokenBudget": 1000,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Context     string `json:"context"`
            TotalTokens int    `json:"totalTokens"`
            ChunksUsed  int    `json:"chunksUsed"`
            Truncated   bool   `json:"truncated"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.LessOrEqual(t, result.TotalTokens, 1000)
        assert.Greater(t, result.ChunksUsed, 0)
        assert.NotEmpty(t, result.Context)
    })
    
    // Phase 6: Test retrieval caching
    t.Run("retrieval_caching", func(t *testing.T) {
        query := "JWT authentication"
        
        // First request
        start1 := time.Now()
        resp1 := httpPost(env.Server.URL+"/api/rag/retrieve", jsonBody{
            "projectId": projectId,
            "query":     query,
            "topK":      5,
        })
        duration1 := time.Since(start1)
        require.Equal(t, http.StatusOK, resp1.StatusCode)
        
        var result1 struct {
            Cached bool `json:"cached"`
        }
        json.NewDecoder(resp1.Body).Decode(&result1)
        assert.False(t, result1.Cached)
        
        // Second request (should be cached)
        start2 := time.Now()
        resp2 := httpPost(env.Server.URL+"/api/rag/retrieve", jsonBody{
            "projectId": projectId,
            "query":     query,
            "topK":      5,
        })
        duration2 := time.Since(start2)
        require.Equal(t, http.StatusOK, resp2.StatusCode)
        
        var result2 struct {
            Cached bool `json:"cached"`
        }
        json.NewDecoder(resp2.Body).Decode(&result2)
        assert.True(t, result2.Cached)
        
        // Cached request should be faster
        assert.Less(t, duration2, duration1)
    })
    
    // Phase 7: Test edge cases
    t.Run("edge_cases", func(t *testing.T) {
        // Empty query
        resp := httpPost(env.Server.URL+"/api/rag/retrieve", jsonBody{
            "projectId": projectId,
            "query":     "",
            "topK":      5,
        })
        assert.Equal(t, http.StatusBadRequest, resp.StatusCode)
        
        // Non-existent project
        resp = httpPost(env.Server.URL+"/api/rag/retrieve", jsonBody{
            "projectId": "non-existent",
            "query":     "test",
            "topK":      5,
        })
        assert.Equal(t, http.StatusNotFound, resp.StatusCode)
        
        // Query with no matches
        resp = httpPost(env.Server.URL+"/api/rag/retrieve", jsonBody{
            "projectId": projectId,
            "query":     "xyzzy foobar nonexistent",
            "topK":      5,
        })
        assert.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Chunks []interface{} `json:"chunks"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        assert.Len(t, result.Chunks, 0)
    })
}

// Helper for deterministic embeddings
func generateDeterministicEmbedding(input string, dims int) []float64 {
    h := fnv.New64a()
    h.Write([]byte(input))
    seed := h.Sum64()
    
    rng := rand.New(rand.NewSource(int64(seed)))
    embedding := make([]float64, dims)
    for i := range embedding {
        embedding[i] = rng.Float64()*2 - 1 // Range [-1, 1]
    }
    
    // Normalize
    var norm float64
    for _, v := range embedding {
        norm += v * v
    }
    norm = math.Sqrt(norm)
    for i := range embedding {
        embedding[i] /= norm
    }
    
    return embedding
}
```

### Accuracy Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Precision@3 | ≥ 0.80 | Correct results in top 3 |
| Recall@10 | ≥ 0.90 | All relevant docs in top 10 |
| MRR | ≥ 0.75 | Mean Reciprocal Rank |
| Latency (cold) | < 500ms | First query |
| Latency (cached) | < 50ms | Repeated query |

---

## Scenario 4: Consistency Loop to 99%

### Description

Tests the iterative consistency improvement workflow: detect issues → generate questions → collect answers → regenerate → verify improvement until reaching 99% consistency.

### Test Data

```go
var ConsistencyLoopFixtures = struct {
    InitialSpecs map[string]string
    ExpectedIssues []struct {
        Type        string
        Severity    string
        Description string
    }
    ClarificationAnswers map[string]string
    TargetScore float64
}{
    InitialSpecs: map[string]string{
        "05-features/01-auth/00-overview.md": `# Authentication
**Version:** 1.0.0

## Overview
User authentication with JWT tokens.

## Cross-References
- [User Model](../../07-database-design/01-users.md)
- [Session Management](./02-sessions.md)
`,
        "05-features/01-auth/02-sessions.md": `# Session Management
**Version:** 1.0.0

## Overview
Manage user sessions with refresh tokens.

## Cross-References
- [JWT Tokens](./01-jwt.md)  <!-- BROKEN: file doesn't exist -->
`,
        "07-database-design/01-users.md": `# User Model
**Version:** 1.0.0

## Schema
| Column | Type |
|--------|------|
| id | UUID |
| email | TEXT |
| passwordHash | TEXT |  <!-- NAMING: should be PasswordHash -->
`,
    },
    ExpectedIssues: []struct{
        Type        string
        Severity    string
        Description string
    }{
        {Type: "broken_link", Severity: "critical", Description: "Reference to non-existent file"},
        {Type: "naming_violation", Severity: "warning", Description: "Column naming convention"},
    },
    ClarificationAnswers: map[string]string{
        "q1": "Create the missing 01-jwt.md file",
        "q2": "Yes, rename to PascalCase",
    },
    TargetScore: 99.0,
}
```

### Test Implementation

```go
// internal/e2e/consistency_loop_test.go
package e2e

func TestConsistencyLoopTo99Percent(t *testing.T) {
    env := SetupEnvironment(t)
    defer env.Cleanup()
    
    var projectId, reportId string
    var initialScore float64
    
    // Phase 1: Setup project with inconsistent specs
    t.Run("setup_inconsistent_project", func(t *testing.T) {
        project := createTestProject(env, "consistency-loop-test")
        projectId = project.Id
        
        for path, content := range ConsistencyLoopFixtures.InitialSpecs {
            fullPath := filepath.Join(env.WorkDir, project.Slug, path)
            os.MkdirAll(filepath.Dir(fullPath), 0755)
            os.WriteFile(fullPath, []byte(content), 0644)
        }
    })
    
    // Phase 2: Run initial consistency check
    t.Run("run_initial_consistency_check", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/consistency/check", jsonBody{
            "projectId": projectId,
        })
        require.Equal(t, http.StatusAccepted, resp.StatusCode)
        
        var result struct {
            ReportId string `json:"reportId"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        reportId = result.ReportId
        
        // Wait for check to complete
        waitForCondition(t, 30*time.Second, func() bool {
            var report InconsistencyReport
            env.DB.First(&report, "Id = ?", reportId)
            return report.Status != "processing"
        })
    })
    
    // Phase 3: Verify issues detected
    t.Run("verify_issues_detected", func(t *testing.T) {
        var report InconsistencyReport
        env.DB.First(&report, "Id = ?", reportId)
        
        initialScore = report.ConsistencyScore
        assert.Less(t, initialScore, 99.0)
        assert.Greater(t, report.TotalIssues, 0)
        
        // Verify specific issues found
        assert.Contains(t, report.ReportContent, "broken")
        assert.Contains(t, report.ReportContent, "naming")
    })
    
    // Phase 4: Retrieve clarification questions
    t.Run("get_clarification_questions", func(t *testing.T) {
        resp := httpGet(env.Server.URL + "/api/consistency/" + reportId + "/questions")
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Questions []struct {
                Id       string `json:"id"`
                Text     string `json:"text"`
                Category string `json:"category"`
                Priority string `json:"priority"`
            } `json:"questions"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Greater(t, len(result.Questions), 0)
        
        // Verify question categories
        categories := make(map[string]bool)
        for _, q := range result.Questions {
            categories[q.Category] = true
        }
        assert.True(t, categories["conflict"] || categories["ambiguity"])
    })
    
    // Phase 5: Submit answers
    t.Run("submit_clarification_answers", func(t *testing.T) {
        var questions []ClarificationQuestion
        env.DB.Where("ReportId = ?", reportId).Find(&questions)
        
        for _, q := range questions {
            answerText := "Fix this issue automatically"
            if strings.Contains(q.QuestionText, "naming") {
                answerText = "Yes, rename to PascalCase"
            } else if strings.Contains(q.QuestionText, "missing") {
                answerText = "Create the missing file with basic structure"
            }
            
            resp := httpPost(env.Server.URL+"/api/consistency/questions/"+q.Id+"/answer", jsonBody{
                "answerText": answerText,
                "confidence": "definite",
            })
            require.Equal(t, http.StatusOK, resp.StatusCode)
        }
        
        // Verify all questions answered
        var answered int64
        env.DB.Model(&ClarificationAnswer{}).
            Joins("JOIN ClarificationQuestion ON ClarificationAnswer.QuestionId = ClarificationQuestion.Id").
            Where("ClarificationQuestion.ReportId = ?", reportId).
            Count(&answered)
        
        assert.Equal(t, int64(len(questions)), answered)
    })
    
    // Phase 6: Trigger regeneration
    t.Run("trigger_regeneration", func(t *testing.T) {
        // Mock LLM for fix generation
        env.LLMMock.Stub(wiremock.Post("/v1/chat/completions").
            WithRequestBody(wiremock.ContainingString("regenerate")).
            WillReturn(wiremock.JSON(chatCompletionResponse(
                `Fixed content with corrected references and naming...`,
            ))))
        
        resp := httpPost(env.Server.URL+"/api/consistency/"+reportId+"/regenerate", jsonBody{
            "applyFixes": true,
        })
        require.Equal(t, http.StatusAccepted, resp.StatusCode)
        
        var result struct {
            EventId string `json:"eventId"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        // Wait for regeneration to complete
        waitForCondition(t, 60*time.Second, func() bool {
            var event RegenerationEvent
            env.DB.First(&event, "Id = ?", result.EventId)
            return event.Status == "completed"
        })
    })
    
    // Phase 7: Verify fixes applied
    t.Run("verify_fixes_applied", func(t *testing.T) {
        // Check that missing file was created
        missingFilePath := filepath.Join(env.WorkDir, "consistency-loop-test",
            "05-features/01-auth/01-jwt.md")
        _, err := os.Stat(missingFilePath)
        assert.NoError(t, err, "Missing file should have been created")
        
        // Check naming was fixed
        userModelPath := filepath.Join(env.WorkDir, "consistency-loop-test",
            "07-database-design/01-users.md")
        content, _ := os.ReadFile(userModelPath)
        assert.Contains(t, string(content), "PasswordHash")
    })
    
    // Phase 8: Run follow-up consistency check
    t.Run("run_followup_check", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/consistency/check", jsonBody{
            "projectId": projectId,
        })
        require.Equal(t, http.StatusAccepted, resp.StatusCode)
        
        var result struct {
            ReportId string `json:"reportId"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        waitForCondition(t, 30*time.Second, func() bool {
            var report InconsistencyReport
            env.DB.First(&report, "Id = ?", result.ReportId)
            return report.Status != "processing"
        })
        
        var newReport InconsistencyReport
        env.DB.First(&newReport, "Id = ?", result.ReportId)
        
        // Score should have improved
        assert.Greater(t, newReport.ConsistencyScore, initialScore)
    })
    
    // Phase 9: Iterate until 99%
    t.Run("iterate_to_target_score", func(t *testing.T) {
        maxIterations := 5
        currentScore := initialScore
        
        for i := 0; i < maxIterations && currentScore < ConsistencyLoopFixtures.TargetScore; i++ {
            t.Logf("Iteration %d: Current score = %.1f%%", i+1, currentScore)
            
            // Get latest report
            var latestReport InconsistencyReport
            env.DB.Where("ProjectId = ?", projectId).
                Order("CreatedAt DESC").
                First(&latestReport)
            
            if latestReport.TotalIssues == 0 {
                currentScore = 100.0
                break
            }
            
            // Answer remaining questions
            var questions []ClarificationQuestion
            env.DB.Where("ReportId = ? AND Status = ?", latestReport.Id, "pending").
                Find(&questions)
            
            for _, q := range questions {
                httpPost(env.Server.URL+"/api/consistency/questions/"+q.Id+"/answer", jsonBody{
                    "answerText": "Apply suggested fix",
                    "confidence": "definite",
                })
            }
            
            // Regenerate
            resp := httpPost(env.Server.URL+"/api/consistency/"+latestReport.Id+"/regenerate", jsonBody{
                "applyFixes": true,
            })
            
            var regenResult struct {
                EventId string `json:"eventId"`
            }
            json.NewDecoder(resp.Body).Decode(&regenResult)
            
            waitForCondition(t, 60*time.Second, func() bool {
                var event RegenerationEvent
                env.DB.First(&event, "Id = ?", regenResult.EventId)
                return event.Status == "completed"
            })
            
            // Check new score
            var event RegenerationEvent
            env.DB.First(&event, "Id = ?", regenResult.EventId)
            if event.NewScore != nil {
                currentScore = *event.NewScore
            }
        }
        
        assert.GreaterOrEqual(t, currentScore, ConsistencyLoopFixtures.TargetScore)
        t.Logf("Final consistency score: %.1f%%", currentScore)
    })
    
    // Phase 10: Verify final state
    t.Run("verify_final_consistency", func(t *testing.T) {
        // All cross-references should be valid
        resp := httpGet(env.Server.URL + "/api/consistency/" + projectId + "/links")
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var links struct {
            Valid   int `json:"valid"`
            Broken  int `json:"broken"`
            Total   int `json:"total"`
        }
        json.NewDecoder(resp.Body).Decode(&links)
        
        assert.Equal(t, 0, links.Broken)
        assert.Equal(t, links.Total, links.Valid)
        
        // All naming conventions should be followed
        resp = httpGet(env.Server.URL + "/api/consistency/" + projectId + "/naming")
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var naming struct {
            Violations int `json:"violations"`
        }
        json.NewDecoder(resp.Body).Decode(&naming)
        
        assert.Equal(t, 0, naming.Violations)
    })
}
```

### Consistency Metrics

| Metric | Initial | Target | Max Iterations |
|--------|---------|--------|----------------|
| Consistency Score | < 80% | ≥ 99% | 5 |
| Broken Links | > 0 | 0 | - |
| Naming Violations | > 0 | 0 | - |
| Missing Sections | > 0 | 0 | - |

---

## Scenario 5: LLM Server Failover

### Description

Tests LLM server health monitoring, automatic failover between backends, and graceful degradation.

### Test Implementation

```go
// internal/e2e/llm_failover_test.go
package e2e

func TestLLMServerFailover(t *testing.T) {
    env := SetupEnvironment(t)
    defer env.Cleanup()
    
    // Setup multiple LLM server mocks
    primaryMock := startLLMMock(t, "primary", 8080)
    backupMock := startLLMMock(t, "backup", 8081)
    defer primaryMock.Stop()
    defer backupMock.Stop()
    
    // Phase 1: Normal operation with primary
    t.Run("normal_operation_primary", func(t *testing.T) {
        primaryMock.Stub(wiremock.Post("/v1/chat/completions").
            WillReturn(wiremock.JSON(chatCompletionResponse("Primary response"))))
        
        resp := httpPost(env.Server.URL+"/api/llm/chat", jsonBody{
            "messages": []map[string]string{
                {"role": "user", "content": "Hello"},
            },
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Content string `json:"content"`
            Server  string `json:"server"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Equal(t, "Primary response", result.Content)
        assert.Equal(t, "primary", result.Server)
    })
    
    // Phase 2: Simulate primary failure
    t.Run("primary_failure_detection", func(t *testing.T) {
        // Make primary return errors
        primaryMock.Reset()
        primaryMock.Stub(wiremock.Post("/v1/chat/completions").
            WillReturn(wiremock.Status(500)))
        primaryMock.Stub(wiremock.Get("/health").
            WillReturn(wiremock.Status(503)))
        
        // Wait for health check to detect failure
        time.Sleep(2 * time.Second)
        
        // Verify server marked unhealthy
        var server LLMServer
        env.DB.Where("Name = ?", "primary").First(&server)
        assert.Equal(t, "unhealthy", server.HealthStatus)
    })
    
    // Phase 3: Automatic failover to backup
    t.Run("automatic_failover", func(t *testing.T) {
        backupMock.Stub(wiremock.Post("/v1/chat/completions").
            WillReturn(wiremock.JSON(chatCompletionResponse("Backup response"))))
        backupMock.Stub(wiremock.Get("/health").
            WillReturn(wiremock.Status(200)))
        
        resp := httpPost(env.Server.URL+"/api/llm/chat", jsonBody{
            "messages": []map[string]string{
                {"role": "user", "content": "Hello"},
            },
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Content string `json:"content"`
            Server  string `json:"server"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Equal(t, "Backup response", result.Content)
        assert.Equal(t, "backup", result.Server)
    })
    
    // Phase 4: Verify failover logged
    t.Run("failover_event_logged", func(t *testing.T) {
        // Check system events
        resp := httpGet(env.Server.URL + "/api/system/events?type=llm_failover")
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var events struct {
            Events []struct {
                Type      string `json:"type"`
                FromServer string `json:"fromServer"`
                ToServer   string `json:"toServer"`
                Reason    string `json:"reason"`
                Timestamp string `json:"timestamp"`
            } `json:"events"`
        }
        json.NewDecoder(resp.Body).Decode(&events)
        
        require.Greater(t, len(events.Events), 0)
        assert.Equal(t, "primary", events.Events[0].FromServer)
        assert.Equal(t, "backup", events.Events[0].ToServer)
    })
    
    // Phase 5: Primary recovery
    t.Run("primary_recovery", func(t *testing.T) {
        // Restore primary
        primaryMock.Reset()
        primaryMock.Stub(wiremock.Post("/v1/chat/completions").
            WillReturn(wiremock.JSON(chatCompletionResponse("Primary recovered"))))
        primaryMock.Stub(wiremock.Get("/health").
            WillReturn(wiremock.Status(200)))
        
        // Wait for health check cycle
        time.Sleep(35 * time.Second) // Health check interval + buffer
        
        // Verify primary marked healthy again
        var server LLMServer
        env.DB.Where("Name = ?", "primary").First(&server)
        assert.Equal(t, "healthy", server.HealthStatus)
    })
    
    // Phase 6: Failback to primary
    t.Run("failback_to_primary", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/llm/chat", jsonBody{
            "messages": []map[string]string{
                {"role": "user", "content": "Hello"},
            },
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Server string `json:"server"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        // Should prefer primary when healthy
        assert.Equal(t, "primary", result.Server)
    })
    
    // Phase 7: All servers down - graceful degradation
    t.Run("all_servers_down", func(t *testing.T) {
        primaryMock.Reset()
        backupMock.Reset()
        
        primaryMock.Stub(wiremock.Any().WillReturn(wiremock.Status(503)))
        backupMock.Stub(wiremock.Any().WillReturn(wiremock.Status(503)))
        
        // Wait for detection
        time.Sleep(2 * time.Second)
        
        resp := httpPost(env.Server.URL+"/api/llm/chat", jsonBody{
            "messages": []map[string]string{
                {"role": "user", "content": "Hello"},
            },
        })
        
        // Should return service unavailable
        assert.Equal(t, http.StatusServiceUnavailable, resp.StatusCode)
        
        var result struct {
            Error     string `json:"error"`
            ErrorCode int    `json:"errorCode"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Equal(t, 7101, result.ErrorCode) // ERR_LLM_ALL_SERVERS_DOWN
        assert.Contains(t, result.Error, "No LLM servers available")
    })
    
    // Phase 8: Request queuing during outage
    t.Run("request_queuing", func(t *testing.T) {
        // Send multiple requests while servers are down
        var wg sync.WaitGroup
        results := make(chan int, 5)
        
        for i := 0; i < 5; i++ {
            wg.Add(1)
            go func() {
                defer wg.Done()
                resp := httpPost(env.Server.URL+"/api/llm/chat", jsonBody{
                    "messages": []map[string]string{{"role": "user", "content": "Test"}},
                    "queueIfUnavailable": true,
                })
                results <- resp.StatusCode
            }()
        }
        
        // Restore a server
        time.Sleep(1 * time.Second)
        backupMock.Reset()
        backupMock.Stub(wiremock.Post("/v1/chat/completions").
            WillReturn(wiremock.JSON(chatCompletionResponse("Queued request handled"))))
        backupMock.Stub(wiremock.Get("/health").
            WillReturn(wiremock.Status(200)))
        
        wg.Wait()
        close(results)
        
        // Count successful responses (queued and processed)
        successCount := 0
        for status := range results {
            if status == http.StatusOK || status == http.StatusAccepted {
                successCount++
            }
        }
        
        assert.Greater(t, successCount, 0)
    })
    
    // Phase 9: Circuit breaker behavior
    t.Run("circuit_breaker", func(t *testing.T) {
        // Cause multiple failures to trip circuit breaker
        primaryMock.Reset()
        primaryMock.Stub(wiremock.Post("/v1/chat/completions").
            WillReturn(wiremock.Status(500)))
        
        for i := 0; i < 10; i++ {
            httpPost(env.Server.URL+"/api/llm/chat", jsonBody{
                "messages":       []map[string]string{{"role": "user", "content": "Test"}},
                "preferServer": "primary",
            })
        }
        
        // Circuit should be open
        resp := httpGet(env.Server.URL + "/api/llm/servers/primary/status")
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var status struct {
            CircuitState string `json:"circuitState"`
        }
        json.NewDecoder(resp.Body).Decode(&status)
        
        assert.Equal(t, "open", status.CircuitState)
        
        // Requests should immediately fail without trying
        start := time.Now()
        resp = httpPost(env.Server.URL+"/api/llm/chat", jsonBody{
            "messages":      []map[string]string{{"role": "user", "content": "Test"}},
            "preferServer": "primary",
        })
        duration := time.Since(start)
        
        assert.Less(t, duration, 100*time.Millisecond) // Fails fast
        assert.Equal(t, http.StatusServiceUnavailable, resp.StatusCode)
    })
}
```

### Failover Metrics

| Metric | Target |
|--------|--------|
| Failure detection time | < 5s |
| Failover switch time | < 1s |
| Recovery detection time | < 35s |
| Circuit breaker trip threshold | 5 failures in 30s |

---

## Scenario 6: File Sync Conflict Resolution

### Description

Tests bidirectional file synchronization between filesystem and database, including conflict detection and resolution strategies.

### Test Implementation

```go
// internal/e2e/file_sync_conflict_test.go
package e2e

func TestFileSyncConflictResolution(t *testing.T) {
    env := SetupEnvironment(t)
    defer env.Cleanup()
    
    var projectId string
    
    // Phase 1: Setup project with files
    t.Run("setup_synced_project", func(t *testing.T) {
        project := createTestProject(env, "sync-conflict-test")
        projectId = project.Id
        
        // Create initial file via API (creates in DB and filesystem)
        resp := httpPost(env.Server.URL+"/api/files", jsonBody{
            "projectId":    projectId,
            "relativePath": "docs/readme.md",
            "content":      "# Initial Content\n\nVersion 1",
        })
        require.Equal(t, http.StatusCreated, resp.StatusCode)
    })
    
    // Phase 2: Simulate external file modification
    t.Run("external_file_modification", func(t *testing.T) {
        // Modify file directly on filesystem (simulating external edit)
        filePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/readme.md")
        
        newContent := "# Modified Externally\n\nVersion 2 - External Edit"
        os.WriteFile(filePath, []byte(newContent), 0644)
        
        // Database still has old hash
        var file File
        env.DB.Where("ProjectId = ? AND RelativePath = ?", projectId, "docs/readme.md").First(&file)
        
        // Hash mismatch indicates conflict
        currentHash := computeFileHash(filePath)
        assert.NotEqual(t, file.ContentHash, currentHash)
    })
    
    // Phase 3: Trigger sync and detect conflict
    t.Run("detect_sync_conflict", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/sync/scan", jsonBody{
            "projectId": projectId,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Conflicts []struct {
                RelativePath    string `json:"relativePath"`
                DBHash          string `json:"dbHash"`
                FSHash          string `json:"fsHash"`
                DBModifiedAt    string `json:"dbModifiedAt"`
                FSModifiedAt    string `json:"fsModifiedAt"`
            } `json:"conflicts"`
            Added   []string `json:"added"`
            Deleted []string `json:"deleted"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        require.Len(t, result.Conflicts, 1)
        assert.Equal(t, "docs/readme.md", result.Conflicts[0].RelativePath)
        assert.NotEqual(t, result.Conflicts[0].DBHash, result.Conflicts[0].FSHash)
    })
    
    // Phase 4: Test "Keep Filesystem" resolution
    t.Run("resolve_keep_filesystem", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/sync/resolve", jsonBody{
            "projectId":    projectId,
            "relativePath": "docs/readme.md",
            "strategy":     "keep_filesystem",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        // Database should now match filesystem
        var file File
        env.DB.Where("ProjectId = ? AND RelativePath = ?", projectId, "docs/readme.md").First(&file)
        
        filePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/readme.md")
        currentHash := computeFileHash(filePath)
        
        assert.Equal(t, currentHash, file.ContentHash)
        
        // Content should reflect external edit
        content, _ := os.ReadFile(filePath)
        assert.Contains(t, string(content), "External Edit")
    })
    
    // Phase 5: Create another conflict for "Keep Database" test
    t.Run("create_db_priority_conflict", func(t *testing.T) {
        // Update via API
        resp := httpPut(env.Server.URL+"/api/files/"+projectId+"/docs/readme.md", jsonBody{
            "content": "# Database Version\n\nVersion 3 - API Edit",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        // Simultaneously modify filesystem
        filePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/readme.md")
        os.WriteFile(filePath, []byte("# Filesystem Version\n\nVersion 4 - FS Edit"), 0644)
    })
    
    // Phase 6: Test "Keep Database" resolution
    t.Run("resolve_keep_database", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/sync/resolve", jsonBody{
            "projectId":    projectId,
            "relativePath": "docs/readme.md",
            "strategy":     "keep_database",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        // Filesystem should now match database
        filePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/readme.md")
        content, _ := os.ReadFile(filePath)
        
        assert.Contains(t, string(content), "API Edit")
        assert.NotContains(t, string(content), "FS Edit")
    })
    
    // Phase 7: Test merge resolution
    t.Run("resolve_with_merge", func(t *testing.T) {
        // Create conflict with mergeable changes
        filePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/readme.md")
        
        // DB version
        httpPut(env.Server.URL+"/api/files/"+projectId+"/docs/readme.md", jsonBody{
            "content": "# Header\n\n## Section A\nDB content\n\n## Section B\nOriginal",
        })
        
        // FS version (different section modified)
        os.WriteFile(filePath, []byte("# Header\n\n## Section A\nOriginal\n\n## Section B\nFS content"), 0644)
        
        resp := httpPost(env.Server.URL+"/api/sync/resolve", jsonBody{
            "projectId":    projectId,
            "relativePath": "docs/readme.md",
            "strategy":     "merge",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            MergedContent string `json:"mergedContent"`
            HasConflicts  bool   `json:"hasConflicts"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        // Both changes should be present
        assert.Contains(t, result.MergedContent, "DB content")
        assert.Contains(t, result.MergedContent, "FS content")
        assert.False(t, result.HasConflicts)
    })
    
    // Phase 8: Test merge with conflicts
    t.Run("merge_with_conflicts", func(t *testing.T) {
        filePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/readme.md")
        
        // Both modify same section
        httpPut(env.Server.URL+"/api/files/"+projectId+"/docs/readme.md", jsonBody{
            "content": "# Header\n\n## Section A\nDB version of section A",
        })
        
        os.WriteFile(filePath, []byte("# Header\n\n## Section A\nFS version of section A"), 0644)
        
        resp := httpPost(env.Server.URL+"/api/sync/resolve", jsonBody{
            "projectId":    projectId,
            "relativePath": "docs/readme.md",
            "strategy":     "merge",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            MergedContent  string `json:"mergedContent"`
            HasConflicts   bool   `json:"hasConflicts"`
            ConflictMarkers []struct {
                StartLine int    `json:"startLine"`
                EndLine   int    `json:"endLine"`
                DBContent string `json:"dbContent"`
                FSContent string `json:"fsContent"`
            } `json:"conflictMarkers"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.True(t, result.HasConflicts)
        assert.Greater(t, len(result.ConflictMarkers), 0)
        assert.Contains(t, result.MergedContent, "<<<<<<< DATABASE")
        assert.Contains(t, result.MergedContent, ">>>>>>> FILESYSTEM")
    })
    
    // Phase 9: Test manual conflict resolution
    t.Run("manual_conflict_resolution", func(t *testing.T) {
        resp := httpPost(env.Server.URL+"/api/sync/resolve", jsonBody{
            "projectId":    projectId,
            "relativePath": "docs/readme.md",
            "strategy":     "manual",
            "manualContent": "# Header\n\n## Section A\nManually resolved content",
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        // Both DB and FS should have manual content
        var file File
        env.DB.Where("ProjectId = ? AND RelativePath = ?", projectId, "docs/readme.md").First(&file)
        
        filePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/readme.md")
        fsContent, _ := os.ReadFile(filePath)
        
        assert.Equal(t, file.ContentHash, computeFileHash(filePath))
        assert.Contains(t, string(fsContent), "Manually resolved")
    })
    
    // Phase 10: Test new file detection
    t.Run("detect_new_files", func(t *testing.T) {
        // Add file directly to filesystem
        newFilePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/new-file.md")
        os.WriteFile(newFilePath, []byte("# New File\n\nCreated externally"), 0644)
        
        resp := httpPost(env.Server.URL+"/api/sync/scan", jsonBody{
            "projectId": projectId,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Added []string `json:"added"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Contains(t, result.Added, "docs/new-file.md")
    })
    
    // Phase 11: Test deleted file detection
    t.Run("detect_deleted_files", func(t *testing.T) {
        // Create file in DB only
        httpPost(env.Server.URL+"/api/files", jsonBody{
            "projectId":    projectId,
            "relativePath": "docs/to-delete.md",
            "content":      "Will be deleted",
        })
        
        // Delete from filesystem
        deletePath := filepath.Join(env.WorkDir, "sync-conflict-test", "docs/to-delete.md")
        os.Remove(deletePath)
        
        resp := httpPost(env.Server.URL+"/api/sync/scan", jsonBody{
            "projectId": projectId,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Deleted []string `json:"deleted"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.Contains(t, result.Deleted, "docs/to-delete.md")
    })
    
    // Phase 12: Test .syncignore patterns
    t.Run("syncignore_patterns", func(t *testing.T) {
        // Create .syncignore
        ignorePath := filepath.Join(env.WorkDir, "sync-conflict-test", ".syncignore")
        os.WriteFile(ignorePath, []byte("*.tmp\n.cache/\nnode_modules/"), 0644)
        
        // Create ignored files
        os.WriteFile(filepath.Join(env.WorkDir, "sync-conflict-test", "test.tmp"), []byte("temp"), 0644)
        os.MkdirAll(filepath.Join(env.WorkDir, "sync-conflict-test", ".cache"), 0755)
        os.WriteFile(filepath.Join(env.WorkDir, "sync-conflict-test", ".cache/data"), []byte("cached"), 0644)
        
        resp := httpPost(env.Server.URL+"/api/sync/scan", jsonBody{
            "projectId": projectId,
        })
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var result struct {
            Added   []string `json:"added"`
            Ignored []string `json:"ignored"`
        }
        json.NewDecoder(resp.Body).Decode(&result)
        
        assert.NotContains(t, result.Added, "test.tmp")
        assert.NotContains(t, result.Added, ".cache/data")
        assert.Contains(t, result.Ignored, "test.tmp")
    })
    
    // Phase 13: Verify sync history
    t.Run("verify_sync_history", func(t *testing.T) {
        resp := httpGet(env.Server.URL + "/api/sync/" + projectId + "/history")
        require.Equal(t, http.StatusOK, resp.StatusCode)
        
        var history struct {
            Events []struct {
                Type         string `json:"type"`
                RelativePath string `json:"relativePath"`
                Strategy     string `json:"strategy"`
                Timestamp    string `json:"timestamp"`
            } `json:"events"`
        }
        json.NewDecoder(resp.Body).Decode(&history)
        
        assert.Greater(t, len(history.Events), 0)
        
        // Verify different resolution strategies were used
        strategies := make(map[string]bool)
        for _, event := range history.Events {
            if event.Strategy != "" {
                strategies[event.Strategy] = true
            }
        }
        
        assert.True(t, strategies["keep_filesystem"] || strategies["keep_database"] || strategies["merge"])
    })
}

func computeFileHash(path string) string {
    content, _ := os.ReadFile(path)
    hash := sha256.Sum256(content)
    return hex.EncodeToString(hash[:])
}
```

### Sync Resolution Strategies

| Strategy | Behavior | Use Case |
|----------|----------|----------|
| keep_filesystem | DB updates to match FS | External editor changes |
| keep_database | FS updates to match DB | API is source of truth |
| merge | Three-way merge | Non-overlapping changes |
| manual | User provides final content | Complex conflicts |

---

## Test Execution

### Running All E2E Tests

```bash
# Run all scenarios
go test -v -tags=e2e ./internal/e2e/... -timeout 30m

# Run specific scenario
go test -v -tags=e2e ./internal/e2e/... -run TestVoiceToSpecPipeline

# With coverage
go test -v -tags=e2e -coverprofile=e2e-coverage.out ./internal/e2e/...

# Generate HTML report
go tool cover -html=e2e-coverage.out -o e2e-coverage.html
```

### CI/CD Integration

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  e2e:
    runs-on: ubuntu-latest
    timeout-minutes: 45
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Go
        uses: actions/setup-go@v5
        with:
          go-version: '1.22'
      
      - name: Start test dependencies
        run: |
          docker-compose -f docker-compose.test.yml up -d
          sleep 10
      
      - name: Run E2E tests
        run: |
          go test -v -tags=e2e ./internal/e2e/... \
            -timeout 30m \
            -coverprofile=coverage.out \
            2>&1 | tee test-output.log
      
      - name: Upload coverage
        uses: codecov/codecov-action@v4
        with:
          files: coverage.out
          flags: e2e
      
      - name: Upload test artifacts
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: e2e-logs
          path: test-output.log
```

---

## Related Documents

- [Implementation Order Guide](../08-roadmap-overview/02-implementation-order-guide.md)
- [Error Code Registry](../06-error-management/error-code-registry.md)
- [Unified Database Schema](../07-database-design/02-unified-schema.md)
- [Seed Data & Fixtures](../07-database-design/03-seed-data.md)
