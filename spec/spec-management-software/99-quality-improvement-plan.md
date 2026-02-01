# Spec Quality Improvement Plan

**Version:** 2.0.0  
**Created:** 2026-01-28  
**Goal:** Increase AI implementation success rate to 99%

---

## Architecture Clarification

| Component | Technology | Notes |
|-----------|------------|-------|
| Backend | Go + GORM | Self-hosted, runs locally |
| Database | SQLite | Local file, WAL mode |
| LLM Server | llama.cpp binary | Local models, user provides binary |
| Models | GGUF files | User has models locally |
| Frontend | React | Served by Go backend |
| Deployment | ❓ TBD | See `03-project-overview/02-deployment-architecture.md` |

**Key Point:** This is a **local/self-hosted application**, not a cloud service.

---

## Revised Success Targets

| Category | Current | Target |
|----------|---------|--------|
| Acceptance Criteria | ~20% | **99%** |
| Validation Checklist | ~30% | **99%** |
| Integration Scenarios | ~10% | **High** |
| Config Defaults | ~30% | **100%** ✅ Done |

---

## Completed Items

### ✅ Configuration Manifest
- Created: `04-coding-guidelines/02-configuration-manifest.md`
- All 80+ config keys documented with defaults
- Validation rules defined
- Environment variable override format specified

### ✅ Deployment Architecture Placeholder
- Created: `03-project-overview/02-deployment-architecture.md`
- Marked as "❓ TBD" for later discussion
- Documents known facts, lists open questions

---

## Phase 1: AI Pipeline Clarity (Priority)

The AI-related specs need maximum clarity for:
- Model selection logic
- Long-chain command handling
- File processing pipeline
- AI output file writing

### 1.1 AI Pipeline Flowchart

**Required in:** `06-ai-integration/03-instruction-system.md`

```markdown
## Instruction Execution Flow

### Step 1: Input Processing
INPUT: Voice/Text → Transcribe (if voice) → Proofread → Classify

### Step 2: Planning Phase
PLAN: Reasoning Model analyzes input
OUTPUT: Task list with dependencies (JSON)

### Step 3: Task Execution
FOR EACH task in topological order:
  IF task.dependsOn is empty OR all deps complete:
    Execute task with Writing/Coding model
    Write output to: {project}/artifacts/{instruction_id}/{task_id}.md
  
### Step 4: Artifact Assembly
COMBINE task outputs → Final spec files
WRITE TO: {project}/instructions/{slug}.md OR {project}/ideas/{slug}.md

### Step 5: Validation
RUN consistency checker on generated files
IF issues found → Generate clarification questions
```

### 1.2 Model Selection Logic

**Must document:**
```
Priority Order:
1. Instruction-level override (if user specified)
2. Project-level default (ProjectMetadata.aiSettings)
3. User preference (User.preferences)
4. System default (Config table)

Category Selection:
- "thinking" → Planning, analysis, reasoning tasks
- "writing" → Content generation, spec writing
- "coding" → Code snippets, technical specs
- "voice" → Transcription only
```

### 1.3 File Writing Conventions

**Must document:**
```
Artifact Output Locations:
├── {workDirectory}/
│   ├── ideas/
│   │   └── {NN}-idea-{slug}.md      # Raw ideas
│   ├── instructions/
│   │   └── {NN}-instruction-{slug}.md  # Refined instructions
│   └── artifacts/
│       └── {instruction_id}/
│           ├── plan.json            # Task plan
│           ├── task-{N}.md          # Individual task outputs
│           └── final.md             # Assembled result

Naming Rules:
- NN = 2-digit auto-increment (01, 02, 03...)
- slug = kebab-case from title (max 50 chars)
- All paths relative to workDirectory
```

---

## Phase 2: Acceptance Criteria (99% Target)

### Template for Every Spec

```markdown
## Acceptance Criteria

### MUST (Required for completion)
- [ ] AC-001: GIVEN [precondition] WHEN [action] THEN [expected result]
- [ ] AC-002: ...

### SHOULD (Expected behavior)
- [ ] AC-010: ...

### Edge Cases
- [ ] EC-001: Empty input → Returns error code [X]
- [ ] EC-002: Invalid path → Returns error code [Y]
- [ ] EC-003: Model not found → Falls back to [Z]
```

### Priority Specs for AC Addition

| Spec | Why Priority | AC Count Needed |
|------|--------------|-----------------|
| `06-ai-integration/03-instruction-system.md` | Core AI pipeline | 15-20 |
| `06-ai-integration/07-llm-server-management.md` | Model loading/switching | 10-15 |
| `09-knowledge-memory/01-rag-system.md` | Context retrieval | 10-15 |
| `02-file-management/01-file-operations.md` | File I/O | 10-12 |
| `08-consistency-checker/01-consistency-checker.md` | Validation | 8-10 |

---

## Phase 3: Integration Scenarios

### Scenario Template

```markdown
## Scenario: [Name]

### Prerequisites
- User authenticated
- Project exists with ID: {projectId}
- Model available: {modelName}

### Steps
1. **Action:** [API call or user action]
   **Input:** [exact payload]
   **Expected:** [exact response]
   **State Change:** [what changes in DB/filesystem]

2. **Action:** ...

### Verification
- [ ] Files created at expected paths
- [ ] Database records match expected state
- [ ] No orphaned resources
```

### Required Scenarios

| # | Scenario | Specs Involved |
|---|----------|----------------|
| 1 | Voice → Idea → Instruction → Spec | 06-ai, 09-knowledge |
| 2 | Import ZIP → Parse → Create Project | 03-project |
| 3 | Edit File → Auto-save → Sync | 02-file, 04-spec |
| 4 | Run Consistency Check → Fix Issues | 08-consistency |
| 5 | Model Hot-Swap During Generation | 06-ai/07-llm |
| 6 | RAG Context Injection | 09-knowledge |

---

## Phase 4: Validation Checklist (99% Target)

### Pre-Implementation Checklist

Before implementing ANY spec:

```markdown
## Pre-Implementation Validation

### Architecture
- [ ] Backend endpoint path defined? (`/api/...`)
- [ ] Request/Response types defined?
- [ ] Error codes mapped?

### Data
- [ ] Database tables exist? (or migration included)
- [ ] Required config keys have defaults?
- [ ] File paths follow conventions?

### AI-Specific
- [ ] Model category specified? (thinking/writing/coding/voice)
- [ ] Fallback behavior defined?
- [ ] Output file location specified?
- [ ] Long-running task handling defined?

### Dependencies
- [ ] All cross-referenced specs exist?
- [ ] Implementation order clear?
```

### Post-Implementation Checklist

After implementing:

```markdown
## Post-Implementation Validation

- [ ] All acceptance criteria pass?
- [ ] Error cases return correct codes?
- [ ] No hardcoded paths (use config)?
- [ ] Logging in place for debugging?
- [ ] WebSocket events fire correctly? (if applicable)
```

---

## Items NOT Needed (Per User)

| Item | Reason |
|------|--------|
| ~~Test data fixtures~~ | Not needed |
| ~~Deployment decisions~~ | TBD later (placeholder created) |
| ~~Supabase migration~~ | This is Go/SQLite, not Supabase |

---

## Next Actions

| # | Action | Priority | Effort |
|---|--------|----------|--------|
| 1 | Add AI pipeline flowchart to instruction-system.md | P0 | Medium |
| 2 | Add file writing conventions to instruction-system.md | P0 | Low |
| 3 | Add acceptance criteria to top 5 AI specs | P0 | High |
| 4 | Create integration scenarios document | P1 | Medium |
| 5 | Add model selection logic documentation | P1 | Low |
| 6 | Review all specs for 99% AC coverage | P2 | High |

---

## Success Metrics

| Metric | Before | After Phase 1 | Final Target |
|--------|--------|---------------|--------------|
| AI specs with AC | 0% | 80% | 99% |
| Config keys documented | 30% | 100% | 100% |
| Pipeline clarity | Low | High | Complete |
| Integration scenarios | 0 | 3 | 6+ |
| Validation checklist | N/A | Created | 99% |
