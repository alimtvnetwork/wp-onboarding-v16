# Spec Update Plan: AI & UI Enhancements

**Status:** Archived (Historical)  
**Priority:** High  
**Complexity:** Complex  
**Created:** 2026-01-27  
**Updated:** 2026-01-28  
**Source:** [03-ai-and-ui-ideas.md](./03-ai-and-ui-ideas.md)

> **⚠️ Historical Document:** This plan references the legacy `01-backend/` and `02-frontend/` folder structure.  
> All specs have since been migrated to `05-features/`. File references below are preserved for historical context.  
> See [05-features Overview](../05-features/00-overview.md) for current locations.

---

## Executive Summary

This plan identifies **gaps** between the new requirements in `03-ai-and-ui-ideas.md` and the existing specifications, then outlines **phases** to update each affected spec file.

---

## ✅ Decisions Made

| # | Question | Decision | Notes |
|---|----------|----------|-------|
| 1 | **LLM Server Scope** | Global singleton | One server serves all users/projects. Must support running multiple models with configurable ports and firewall rules. |
| 2 | **Instruction Execution** | Configurable per-project | Each project can choose automatic or approval-required mode. |
| 3 | **Instruction Artifact Format** | Both Markdown + JSON | Markdown for human display, JSON for machine processing. |

### Multi-Model Mechanism (Global Singleton)

Since we're using a global singleton LLaMA server that needs to run **multiple models simultaneously**, the spec must define:

1. **Port Management:**
   - Base port (e.g., `8080`) for primary model
   - Port range for additional models (e.g., `8081-8089`)
   - Each active model gets its own port
   - Configurable via `llama.server.portRange` config key

2. **Model Slot Allocation:**
   - `ModelSlot` table tracking: slotId, modelId, port, status (idle/loading/active/error), startedAt
   - Maximum concurrent models limit via `llama.server.maxConcurrentModels`
   - LRU eviction when slots are full

3. **Firewall Configuration:**
   - All model ports bind to `127.0.0.1` by default (localhost only)
   - Configurable bind address via `llama.server.bindAddress`
   - External access requires explicit firewall rule changes
   - Document firewall setup in deployment spec

4. **Model Switching:**
   - API to request a model (voice or reasoning)
   - Server checks if model already loaded → returns port
   - If not loaded, loads model into available slot → returns port
   - Client connects directly to model port for inference

---

## Gap Analysis

### ❌ MISSING: Seeding Values & Config System

**Current State:**
- `02-database-schema.md` has a basic `Config` table and `seed.json`
- No explicit list of ALL seeding values
- No seeding principle documentation

**Required by 03-ai-and-ui-ideas:**
- Explicit seeding values section with:
  - `llamaServerExecutablePath`
  - `llamaServerShellCommandTemplate`
  - `llamaServerWorkingDirectory`
  - `modelRoots` (multiple folders)
  - `defaultReasoningModelId`
  - `defaultVoiceModelId`
  - `modelRegistryRefreshMode`
  - `maxConcurrentModelRequests`
  - Feature flags (optional)
- Seeding principle: seed → persist to DB → DB is source of truth → UI can modify

**Action:** Update `01-backend/02-database-schema.md` + create new `01-backend/09-seeding-configuration.md`

---

### ❌ MISSING: Model Registry & Selection UI

**Current State:**
- `08-ai-integration.md` has basic model path config
- No model registry table
- No per-user/per-project model selection

**Required:**
- `ModelRegistry` table with: modelId, displayName, modelType, modelPath, tags, isEnabled
- Model selection UI for reasoning + voice models
- Per-user default, per-project default, per-instruction override
- Shell command template resolution

**Action:** Update `01-backend/02-database-schema.md` + `01-backend/08-ai-integration.md` + `02-frontend/08-ai-chat.md`

---

### ❌ MISSING: Presets & Layered Guidelines System

**Current State:**
- No preset system for new projects
- No guideline layers (global → category → language → project)

**Required:**
- Preset templates (general, language-specific, app-type, personalized)
- Guideline modules: coding, file formatting, error handling, logging, acceptance criteria
- Layered inheritance model

**Action:** Create new `01-backend/10-presets-guidelines.md` + update `02-frontend/03-project-dashboard.md`

---

### ❌ MISSING: Project Metadata JSON File

**Current State:**
- Project metadata is only in database
- No `spec.project.json` file concept

**Required:**
- `spec.project.json` per project with:
  - projectName, projectSlug, version, summary
  - authorName, designerName, responsiblePersonName, responsiblePersonEmail
  - createdAt, updatedAt
- Bidirectional sync: DB ↔ filesystem JSON

**Action:** Update `01-backend/04-file-operations.md` + `01-backend/02-database-schema.md`

---

### ❌ MISSING: Folder Sync & Import Workflow

**Current State:**
- Basic seeding logic exists
- No explicit "sync screen" or reconciliation UI

**Required:**
- Post-login sync screen/banner showing:
  - Detected folders, inferred projects/categories
  - Import all / review / ignore actions
- Rules for determining project roots vs categories

**Action:** Create new `02-frontend/09-folder-sync.md` + update `01-backend/01-overview.md`

---

### ❌ MISSING: Instructions System (Voice → Tasks → Spec)

**Current State:**
- Voice input exists but only goes to "transcription → AI"
- No instruction artifact storage
- No task breakdown system

**Required:**
- Instruction storage structure: `instructions/{global,backend,frontend,fileScoped}/`
- Instruction record fields: instructionId, createdAt, scope, targetFilePath, instructionText, derivedTasks, status
- Long-chain reasoning: proofread → plan → tasks → child tasks
- Instruction artifacts saved to filesystem + DB

**Action:** Create new `01-backend/11-instruction-system.md` + update `02-frontend/07-voice-input.md`

---

### ❌ MISSING: Instruction → Change Tracking

**Current State:**
- Snapshots exist but not linked to instructions
- No audit trail mapping instruction → file changes

**Required:**
- For each instruction execution, record:
  - Modified/created/deleted files
  - Before/after snapshot IDs
  - Git commit ID
- UI to navigate from instruction → snapshots → git commits

**Action:** Update `01-backend/06-history-system.md` + create new `01-backend/12-instruction-history.md`

---

### ⚠️ PARTIAL: Database Schema Updates Needed

**Current tables exist but need additions:**

| New Table | Purpose |
|-----------|---------|
| `ModelRegistry` | Available AI models |
| `ProjectSettings` | Per-project defaults (models, etc.) |
| `Preset` | Template presets for new projects |
| `Guideline` | Layered guidelines (global, category, language, project) |
| `ProjectMetadata` | Extended project metadata (or merge into Project) |
| `Instruction` | Voice instruction artifacts |
| `InstructionTask` | Derived tasks from instructions |
| `InstructionFileImpact` | Files modified by instruction execution |
| `ConfigSeedEvent` | Audit log for seeding events |

**Action:** Major update to `01-backend/02-database-schema.md`

---

### ⚠️ PARTIAL: Acceptance Criteria Updates

**Current specs have acceptance criteria but need additions for:**
- Model selection UI and persistence
- Seeding behavior and DB source of truth
- Project creation from presets
- Folder sync and import flow
- Project metadata JSON round-trip
- Voice → instruction pipeline
- Instruction task breakdown quality gates
- Instruction → file impact history

**Action:** Update acceptance criteria in all affected spec files

---

## Open Questions to Resolve

| # | Question | Options | Decision |
|---|----------|---------|----------|
| 1 | LLM server scope | Per project / Per user / Global singleton | TBD |
| 2 | Instruction execution | Automatic / Requires approval | TBD |
| 3 | Instruction artifact format | Markdown / JSON / Both | TBD |

---

## Phased Update Plan

### Phase 1: Seeding & Configuration (Est: 4 credits)

**Files to update:**
- `01-backend/02-database-schema.md` — Add `ConfigSeedEvent` table
- NEW: `01-backend/09-seeding-configuration.md` — Complete seeding values section

**Deliverables:**
- [ ] Explicit seeding values list
- [ ] Seeding principle documentation
- [ ] ConfigSeedEvent table definition
- [ ] First-run vs subsequent-run behavior

---

### Phase 2: Model Registry & Selection (Est: 4 credits)

**Files to update:**
- `01-backend/02-database-schema.md` — Add `ModelRegistry`, `ProjectSettings` tables
- `01-backend/08-ai-integration.md` — Add model selection logic, shell command template
- `02-frontend/08-ai-chat.md` — Add model selector UI

**Deliverables:**
- [ ] ModelRegistry table with all fields
- [ ] ProjectSettings table for per-project defaults
- [ ] Model selection API endpoints
- [ ] Model selector component spec

---

### Phase 3: Presets & Guidelines (Est: 4 credits)

**Files to create/update:**
- NEW: `01-backend/10-presets-guidelines.md`
- `01-backend/02-database-schema.md` — Add `Preset`, `Guideline` tables
- `02-frontend/03-project-dashboard.md` — Add "New from Preset" flow

**Deliverables:**
- [ ] Preset table and API
- [ ] Guideline inheritance model (global → category → language → project)
- [ ] Built-in guideline modules list
- [ ] UI for preset selection when creating project

---

### Phase 4: Project Metadata JSON (Est: 3 credits)

**Files to update:**
- `01-backend/04-file-operations.md` — Add spec.project.json handling
- `01-backend/02-database-schema.md` — Extend Project table or add ProjectMetadata

**Deliverables:**
- [ ] spec.project.json schema definition
- [ ] Bidirectional sync logic (DB ↔ JSON)
- [ ] API for reading/updating project metadata

---

### Phase 5: Folder Sync UI (Est: 3 credits)

**Files to create/update:**
- NEW: `02-frontend/09-folder-sync.md`
- `01-backend/01-overview.md` — Reference sync workflow

**Deliverables:**
- [ ] Sync screen/banner component spec
- [ ] Import actions (all, review, ignore)
- [ ] Detection rules for projects vs categories

---

### Phase 6: Instruction System (Est: 5 credits)

**Files to create/update:**
- NEW: `01-backend/11-instruction-system.md`
- `01-backend/02-database-schema.md` — Add `Instruction`, `InstructionTask` tables
- `02-frontend/07-voice-input.md` — Update to save instructions, show task breakdown

**Deliverables:**
- [ ] Instruction storage structure
- [ ] Instruction record schema
- [ ] Task breakdown flow (long-chain reasoning)
- [ ] Voice → instruction pipeline UI

---

### Phase 7: Instruction History & Change Tracking (Est: 4 credits)

**Files to create/update:**
- NEW: `01-backend/12-instruction-history.md`
- `01-backend/02-database-schema.md` — Add `InstructionFileImpact` table
- `01-backend/06-history-system.md` — Link instructions to snapshots
- `02-frontend/06-history-ui.md` — Add instruction history view

**Deliverables:**
- [ ] InstructionFileImpact table
- [ ] Instruction ↔ snapshot ↔ git commit mapping
- [ ] UI to browse instruction history

---

### Phase 8: Acceptance Criteria & Consistency (Est: 2 credits)

**Files to update:**
- All modified spec files — Add/update acceptance criteria
- `99-consistency-report.md` — Full consistency audit

**Deliverables:**
- [ ] Complete acceptance criteria for all new features
- [ ] Cross-reference validation
- [ ] Final consistency report

---

## Total Estimated Credits: 29 credits (8 phases)

---

## Files to Create

| File | Phase |
|------|-------|
| `01-backend/09-seeding-configuration.md` | 1 |
| `01-backend/10-presets-guidelines.md` | 3 |
| `01-backend/11-instruction-system.md` | 6 |
| `01-backend/12-instruction-history.md` | 7 |
| `02-frontend/09-folder-sync.md` | 5 |

---

## Files to Update

| File | Phases |
|------|--------|
| `01-backend/01-overview.md` | 5 |
| `01-backend/02-database-schema.md` | 1, 2, 3, 4, 6, 7 |
| `01-backend/04-file-operations.md` | 4 |
| `01-backend/06-history-system.md` | 7 |
| `01-backend/08-ai-integration.md` | 2 |
| `02-frontend/03-project-dashboard.md` | 3 |
| `02-frontend/06-history-ui.md` | 7 |
| `02-frontend/07-voice-input.md` | 6 |
| `02-frontend/08-ai-chat.md` | 2 |
| `99-consistency-report.md` | 8 |

---

## Next Steps

1. **Decide open questions** (LLM scope, instruction approval, artifact format)
2. **Start Phase 1** — Seeding & Configuration
3. **Iterate through phases** in order

---

## Cross-References

- [01-initial-idea.md](./01-initial-idea.md) — Original idea
- [03-ai-and-ui-ideas.md](./03-ai-and-ui-ideas.md) — Source requirements
- [General Spec Standards](../../general-spec/00-overview.md)
- [WordPress Plugin Seeding Pattern](../../wp-plugin/exam-manager/01-admin-backend/split-spec/35-plugin-settings.md)
