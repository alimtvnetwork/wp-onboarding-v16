# Feature: AI Integration

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Summary

Multi-backend AI integration supporting Ollama and llama.cpp for voice transcription, reasoning, and automated spec generation with category-based model selection and runtime switching.

---

## User Stories

- As a user, I want to speak my ideas and have them transcribed accurately
- As a user, I want AI to help refine my ideas into structured instructions with clarifying questions
- As a user, I want to generate specification files from instructions with proper cross-references
- As a user, I want to choose different AI models for different tasks (thinking, writing, voice, coding)
- As a user, I want models to load dynamically without server restarts
- As a user, I want fallback to alternate models if primary fails

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         AI Integration Architecture                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                  │
│   │   Frontend   │───▶│   Backend    │───▶│  AI Backend  │                  │
│   │   (React)    │    │   (Go API)   │    │  (Ollama/    │                  │
│   │              │    │              │    │  llama.cpp)  │                  │
│   └──────────────┘    └──────────────┘    └──────────────┘                  │
│          │                   │                    │                          │
│          ▼                   ▼                    ▼                          │
│   ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                  │
│   │  Voice Input │    │ RAG Context  │    │  Multi-Slot  │                  │
│   │  Recording   │    │  Assembly    │    │  Manager     │                  │
│   └──────────────┘    └──────────────┘    └──────────────┘                  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Model Categories

| Category | Purpose | Example Models | Selection Hierarchy |
|----------|---------|----------------|---------------------|
| **thinking** | Reasoning, planning, analysis | r1, o1, qwen-thinking, deepseek-r | Instruction → Project → User → System |
| **writing** | Content generation, drafting | llama-3, mistral, gemma | Instruction → Project → User → System |
| **voice** | Speech-to-text transcription | whisper-large-v3, faster-whisper | Instruction → Project → User → System |
| **coding** | Code generation, refactoring | codellama, deepseek-coder, qwen-coder | Instruction → Project → User → System |

---

## Backends

| Backend | Mode | Features |
|---------|------|----------|
| **Ollama** | Managed | Dynamic model pulling, native API, automatic GPU detection |
| **llama.cpp** | Router | Multi-slot management, llama-swap proxy, manual model loading |

Both backends support:
- Runtime model switching without server restart
- Health checking and automatic recovery
- Request queuing and load balancing
- Streaming responses

---

## Pipeline Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           AI Processing Pipeline                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   1. INPUT          2. TRANSCRIBE       3. PROOFREAD       4. RETRIEVE      │
│   ───────────────   ───────────────    ───────────────    ───────────────   │
│   Voice/Text ───▶   Whisper Model ───▶  Grammar/Spell ──▶  RAG Context     │
│                     (voice category)    Correction        Assembly          │
│                                                                              │
│   5. PLAN           6. VALIDATE         7. EXECUTE        8. OUTPUT         │
│   ───────────────   ───────────────    ───────────────   ───────────────    │
│   Reasoning    ───▶  Ask Clarifying ──▶  Generate Spec ──▶ Markdown +       │
│   (thinking)        Questions          (writing/coding)   JSON Artifact     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Components

### Backend

| # | Component | Type | Status | Description |
|---|-----------|------|--------|-------------|
| 01 | [AI Integration Core](./01-ai-integration.md) | Backend | Complete | LLM provider abstraction, model registry |
| 02 | [Presets & Guidelines](./02-presets-guidelines.md) | Backend | Complete | Prompt templates and rules |
| 03 | [Instruction System](./03-instruction-system.md) | Backend | Complete | Voice-to-spec pipeline |
| 04 | [Instruction History](./04-instruction-history.md) | Backend | Complete | Change tracking |
| 05 | [Instruction Segmentation](./05-instruction-segmentation.md) | Backend | Complete | Large instruction handling |
| 06 | [LLM Live Logging](./06-llm-live-logging.md) | Backend | Complete | Real-time AI output |
| 07 | [LLM Server Management](./07-llm-server-management.md) | Backend | Complete | Multi-model server with Ollama/llama.cpp |
| 12 | [Resilient Execution System](./12-resilient-execution-system.md) | Backend | Complete | 98%+ success rate infrastructure |

### Frontend

| # | Component | Type | Status | Description |
|---|-----------|------|--------|-------------|
| 08 | [AI Chat UI](./08-ai-chat-ui.md) | Frontend | Complete | Chat interface with streaming |
| 09 | [Instruction Builder UI](./09-instruction-builder-ui.md) | Frontend | Complete | Voice/text instruction input |
| 10 | [AI Prompt Panel](./10-ai-prompt-panel.md) | Frontend | Complete | Prompt editing sidebar |
| 13 | [Escalation Notifications](./13-escalation-notifications.md) | Frontend | Complete | In-app alerts, email templates |
| 14 | [Telemetry Dashboard](./14-telemetry-dashboard.md) | Frontend | Complete | Real-time success rate monitoring |
| 15 | [Mermaid Diagram Generation](./15-mermaid-diagram-generation.md) | Backend | Complete | Auto diagram generation |

### Testing

| # | Component | Type | Status | Description |
|---|-----------|------|--------|-------------|
| 11 | [AI Testing Strategy](./11-ai-testing.md) | Testing | Complete | Unit, integration, E2E tests |

---

## Key Interfaces

### TypeScript Types

```typescript
// Model category type
type ModelCategory = 'thinking' | 'writing' | 'voice' | 'coding';

// Model info from registry
interface ModelInfo {
  id: string;
  displayName: string;
  fileName: string;
  category: ModelCategory;
  modelPath: string;
  fileSizeBytes: number;
  isEnabled: boolean;
  contextSize?: number;
  gpuLayers?: number;
  tags?: string[];
}

// Category-based model overrides
interface CategoryModelOverrides {
  thinkingModelId?: string;
  writingModelId?: string;
  voiceModelId?: string;
  codingModelId?: string;
}

// AI chain request
interface AIChainRequest {
  projectId: string;
  userId: string;
  input: string | Blob; // text or audio
  overrides?: CategoryModelOverrides;
  skipTranscription?: boolean;
  outputType: 'idea' | 'spec';
}

// AI chain response
interface AIChainResponse {
  transcription?: string;
  intent: string;
  questions?: ClarifyingQuestion[];
  artifact?: {
    id: string;
    path: string;
    content: string;
  };
  ragContext?: {
    sessionId: string;
    chunkCount: number;
    sourcePaths: string[];
  };
}
```

---

## Security

- API keys stored encrypted in database (AES-256)
- Model paths validated against allowed directories
- Rate limiting per user (configurable)
- Request/response logging with PII redaction
- Sandboxed code execution for generated code

---

## Configuration

| Config Key | Default | Description |
|------------|---------|-------------|
| `ai.backend` | `ollama` | Backend type: `ollama` or `llama-cpp` |
| `ai.ollama.baseUrl` | `http://localhost:11434` | Ollama API URL |
| `ai.llama.serverPath` | `/usr/local/bin/llama-server` | llama.cpp server path |
| `ai.models.rootPaths` | `["/models"]` | Model file directories |
| `ai.defaults.thinkingModelId` | - | System default thinking model |
| `ai.defaults.writingModelId` | - | System default writing model |
| `ai.defaults.voiceModelId` | - | System default voice model |
| `ai.defaults.codingModelId` | - | System default coding model |
| `ai.maxConcurrentModels` | `3` | Max models loaded simultaneously |
| `ai.contextSize` | `8192` | Default context window |
| `ai.gpuLayers` | `35` | Default GPU offload layers |

---

## E2E Tests

| # | Test | Priority | Status |
|---|------|----------|--------|
| 01 | Voice-to-Idea Flow | Critical | Spec'd |
| 02 | Spec Generation with RAG | Critical | Spec'd |
| 03 | Model Selection Hierarchy | High | Spec'd |
| 04 | Multi-Backend Failover | High | Spec'd |
| 05 | Streaming Response | Medium | Spec'd |

---

## Dependencies

- [Knowledge Memory](../09-knowledge-memory/00-overview.md) — RAG context retrieval
- [Instructions Folder](../../02-instructions/README.md) — Output location
- [History System](../07-history-system/00-overview.md) — Artifact versioning
