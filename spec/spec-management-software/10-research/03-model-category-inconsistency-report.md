# Inconsistency Report: Model Categories & Task Execution

**Date:** 2026-01-28  
**Status:** Analysis Complete  

---

## 1. Identified Inconsistencies

### 1.1 Model Type Categories

**Current State (Inconsistent):**

| Spec | Model Types Defined |
|------|---------------------|
| `08-ai-integration.md` (line 252-261) | `voice`, `reasoning` only |
| `08-ai-integration.md` (line 330) | `modelType` param accepts only `"reasoning"` or `"voice"` |
| `28-llm-server-management.md` | No `category` field in `LLMModelAssignment` |
| `11-instruction-system.md` (line 102) | Only `ReasoningModelId` stored |
| User table | Only `DefaultReasoningModelId` and `DefaultVoiceModelId` |

**Required State (4 Categories):**

| Category | Purpose | Example Models |
|----------|---------|----------------|
| `thinking` | Long-chain reasoning, planning, analysis | deepseek-r1, o1-preview |
| `writing` | Content generation, spec drafting | llama3, mistral, gpt-4 |
| `voice` | Speech-to-text transcription | whisper-large-v3 |
| `coding` | Code generation, refactoring | codellama, deepseek-coder |

### 1.2 Task Execution Mode

**Current State (Missing):**
- `11-instruction-system.md` has no parallel execution logic
- Tasks executed sequentially by `SortOrder`
- No dependency graph between tasks

**Required State:**
- Tasks with dependencies execute in chain (sequential)
- Independent tasks execute in parallel
- Explicit `DependsOn` field for task dependencies

### 1.3 Model Resolution by Category

**Current State:**
- `ResolveModel()` accepts `modelType string` as `"reasoning"` or `"voice"`
- Config keys: `llama.models.defaultReasoningModelId`, `llama.models.defaultVoiceModelId`

**Required State:**
- `ResolveModel()` accepts `category ModelCategory` enum
- Config keys for all 4 categories
- User/Project defaults for all 4 categories

---

## 2. Files Requiring Updates

| File | Section | Change Required |
|------|---------|-----------------|
| `08-ai-integration.md` | 7.2, 7.3 | Expand `ModelType` enum to 4 categories |
| `08-ai-integration.md` | 7.3 | Update `inferModelType()` for 4 categories |
| `08-ai-integration.md` | 7.3 | Update `ResolveModel()` signature and logic |
| `09-seeding-configuration.md` | 9.2 | Add default model keys for all 4 categories |
| `28-llm-server-management.md` | 28.3 | Add `category` to `LLMModelAssignment` |
| `11-instruction-system.md` | 11.3 | Add per-category model IDs to Instruction table |
| `11-instruction-system.md` | 11.3 | Add `DependsOn` field to InstructionTask |
| `11-instruction-system.md` | NEW | Add parallel/chain task execution logic |
| `02-database-schema.md` | User | Add default model IDs for all 4 categories |
| `02-database-schema.md` | ProjectSettings | Add default model IDs for all 4 categories |

---

## 3. Proposed Schema Changes

### 3.1 ModelCategory Enum

```go
type ModelCategory string

const (
    ModelCategoryThinking ModelCategory = "thinking"
    ModelCategoryWriting  ModelCategory = "writing"
    ModelCategoryVoice    ModelCategory = "voice"
    ModelCategoryCoding   ModelCategory = "coding"
)

var AllModelCategories = []ModelCategory{
    ModelCategoryThinking,
    ModelCategoryWriting,
    ModelCategoryVoice,
    ModelCategoryCoding,
}
```

### 3.2 LLMModelAssignment Update

```typescript
interface LLMModelAssignment {
  modelId: string;
  fileName?: string;
  ollamaName?: string;
  category: "thinking" | "writing" | "voice" | "coding";  // NEW
  contextSize?: number;
  gpuLayers?: number;
  priority?: number;
  warmup?: boolean;
}
```

### 3.3 InstructionTask Dependencies

```sql
ALTER TABLE InstructionTask ADD COLUMN DependsOn TEXT; -- JSON array of task IDs

-- Example: ["task_001", "task_002"] means this task waits for both
```

### 3.4 Parallel Execution Logic

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        TASK EXECUTION MODES                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  PARALLEL EXECUTION (DependsOn = null or [])                                 │
│  ─────────────────────────────────────────────                              │
│  Tasks with no dependencies execute concurrently                            │
│                                                                              │
│  ┌────────┐  ┌────────┐  ┌────────┐                                         │
│  │ Task A │  │ Task B │  │ Task C │   (all start simultaneously)            │
│  └────────┘  └────────┘  └────────┘                                         │
│                                                                              │
│  CHAIN EXECUTION (DependsOn = ["parent_id"])                                 │
│  ─────────────────────────────────────────────                              │
│  Tasks wait for dependencies to complete                                    │
│                                                                              │
│  ┌────────┐                                                                  │
│  │ Task A │                                                                  │
│  └────┬───┘                                                                  │
│       ▼                                                                      │
│  ┌────────┐                                                                  │
│  │ Task B │  (DependsOn: ["A"])                                              │
│  └────┬───┘                                                                  │
│       ▼                                                                      │
│  ┌────────┐                                                                  │
│  │ Task C │  (DependsOn: ["B"])                                              │
│  └────────┘                                                                  │
│                                                                              │
│  MIXED EXECUTION (Complex Dependencies)                                      │
│  ─────────────────────────────────────────                                  │
│                                                                              │
│  ┌────────┐  ┌────────┐                                                      │
│  │ Task A │  │ Task B │   (parallel - no deps)                               │
│  └────┬───┘  └────┬───┘                                                      │
│       │          │                                                           │
│       └────┬─────┘                                                           │
│            ▼                                                                 │
│       ┌────────┐                                                             │
│       │ Task C │   (DependsOn: ["A", "B"] - waits for both)                  │
│       └────┬───┘                                                             │
│            ▼                                                                 │
│       ┌────────┐  ┌────────┐                                                 │
│       │ Task D │  │ Task E │   (both depend only on C - parallel)            │
│       └────────┘  └────────┘                                                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Model Folder Scanning Updates

### 4.1 Category Detection Rules

```go
func inferModelCategory(filename string) ModelCategory {
    lowerName := strings.ToLower(filename)
    
    // Voice detection
    if containsAny(lowerName, "whisper", "speech", "voice", "transcribe") {
        return ModelCategoryVoice
    }
    
    // Coding detection
    if containsAny(lowerName, "code", "coder", "starcoder", "codellama", "deepseek-coder") {
        return ModelCategoryCoding
    }
    
    // Thinking/Reasoning detection
    if containsAny(lowerName, "reasoning", "think", "o1", "r1", "qwen-qwq") {
        return ModelCategoryThinking
    }
    
    // Default to writing for general-purpose models
    return ModelCategoryWriting
}
```

### 4.2 Category Override in Config

Users can override auto-detected category:

```json
{
  "modelId": "llama3-8b",
  "fileName": "llama3-8b-instruct.gguf",
  "category": "writing",  // Explicit override
  "categoryOverride": true
}
```

---

## 5. Configuration Keys to Add

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `llm.defaults.thinkingModelId` | string | `null` | Default model for thinking/reasoning |
| `llm.defaults.writingModelId` | string | `null` | Default model for writing/generation |
| `llm.defaults.voiceModelId` | string | `null` | Default model for voice transcription |
| `llm.defaults.codingModelId` | string | `null` | Default model for code generation |
| `llm.models.foldersByCategory.thinking` | json_array | `["/models/thinking"]` | Folders to scan for thinking models |
| `llm.models.foldersByCategory.writing` | json_array | `["/models/writing"]` | Folders to scan for writing models |
| `llm.models.foldersByCategory.voice` | json_array | `["/models/voice"]` | Folders to scan for voice models |
| `llm.models.foldersByCategory.coding` | json_array | `["/models/coding"]` | Folders to scan for coding models |

---

## 6. Next Steps

1. Update `08-ai-integration.md` with 4-category model system
2. Update `28-llm-server-management.md` with category field
3. Update `09-seeding-configuration.md` with new config keys
4. Update `11-instruction-system.md` with:
   - Per-category model selection
   - Task dependency system (`DependsOn` field)
   - Parallel/chain task executor
5. Update `02-database-schema.md` with category defaults
