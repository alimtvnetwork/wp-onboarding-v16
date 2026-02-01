# Implementation Roadmap

**Version:** 1.2.0  
**Status:** Specification Complete  
**Updated:** 2026-01-29

> **⚠️ Historical Document:** File references to `01-backend/` and `02-frontend/` are preserved for historical context.  
> All specs have been migrated to `05-features/`. See [Features Overview](../05-features/00-overview.md) for current locations.

---

## Phase Overview

| Phase | Name | Credits | Status |
|-------|------|---------|--------|
| 1 | Idea & Data Models | 3-4 | ✅ Complete |
| 2 | Project Structure | 3-4 | ✅ Complete |
| 3 | Database Schema Spec | 4-5 | ✅ Complete |
| 4 | Backend Core API Spec | 4-5 | ✅ Complete |
| 5 | Backend History & Git Spec | 4-5 | ✅ Complete |
| 6 | Frontend Core Spec | 4-5 | ✅ Complete |
| 7 | Frontend Editor Spec | 4-5 | ✅ Complete |
| 8 | AI Integration Spec | 4-5 | ✅ Complete |
| 9 | Testing & Deployment Spec | 3-4 | ✅ Complete |
| 10 | Implementation Guidelines | 3-4 | ✅ Complete |
| 11 | **Code Generation System** | 6-8 | ✅ Complete |

**Total Estimated Credits:** 44-54

---

## Detailed Phases

### Phase 1: Idea & Data Models ✅

**Deliverables:**
- [x] Core concept documentation
- [x] Data model definitions (User, Project, File, Snapshot, Config)
- [x] API endpoint outline
- [x] AI chain flow diagram
- [x] Open questions documented

**Output:** `spec/wp-plugin/exam-manager/ideas/02-spec-management-software.md`

---

### Phase 2: Project Structure ✅

**Deliverables:**
- [x] Create `spec/spec-management-software/` folder
- [x] Project overview document
- [x] Backend folder structure
- [x] Frontend folder structure
- [x] Ideas folder with README
- [x] This roadmap document

**Output:** Project folder hierarchy

---

### Phase 3: Database Schema Spec ✅

**Deliverables:**
- [x] Complete SQLite schema with PascalCase columns
- [x] Table relationships (ER diagram)
- [x] Index definitions
- [x] Seed data structure
- [x] Migration strategy

**Output:** `01-backend/02-database-schema.md`

**Tables Created:**
- User, Session, Project, File, Snapshot, Config
- ProjectMetadata, Guideline, ProjectGuideline
- ModelRegistry, ModelSlot
- Instruction, InstructionTask, FileChange, InstructionRollback
- ConsistencyReport, ConsistencyFix

---

### Phase 4: Backend Core API Spec ✅

**Deliverables:**
- [x] Authentication endpoints (register, login, logout)
- [x] Project CRUD endpoints
- [x] File CRUD endpoints
- [x] Request/response schemas
- [x] Error code registry (1xxx-9xxx ranges)
- [x] Project metadata sync (spec.project.json)

**Output:** 
- `01-backend/03-api-endpoints.md`
- `01-backend/04-file-operations.md`
- `01-backend/07-authentication.md`

---

### Phase 5: Backend History & Git Spec ✅

**Deliverables:**
- [x] Snapshot creation/restore logic
- [x] .history folder structure
- [x] Git commit automation
- [x] External change detection (fsnotify)
- [x] Sync reconciliation strategy
- [x] Folder sync UI spec

**Output:**
- `01-backend/05-git-integration.md`
- `01-backend/06-history-system.md`
- `02-frontend/09-folder-sync.md`

---

### Phase 6: Frontend Core Spec ✅

**Deliverables:**
- [x] Theme system (4 themes: light, dark, ocean, forest)
- [x] Project dashboard cards
- [x] Navigation structure
- [x] Component hierarchy
- [x] State management approach (React Query + Zustand)

**Output:**
- `02-frontend/01-overview.md`
- `02-frontend/02-theme-system.md`
- `02-frontend/03-project-dashboard.md`

---

### Phase 7: Frontend Editor Spec ✅

**Deliverables:**
- [x] Folder tree component with drag-and-drop
- [x] Markdown editor integration (CodeMirror)
- [x] Auto-save mechanism
- [x] History management UI
- [x] Instruction system with voice-to-tasks pipeline

**Output:**
- `02-frontend/04-folder-tree.md`
- `02-frontend/05-markdown-editor.md`
- `02-frontend/06-history-ui.md`
- `01-backend/11-instruction-system.md`

---

### Phase 8: AI Integration Spec ✅

**Deliverables:**
- [x] LLaMA server configuration
- [x] Voice model integration (Whisper)
- [x] Reasoning model integration
- [x] Instruction history & rollback system
- [x] Consistency checker with auto-fix
- [x] Voice input UI
- [x] AI chat panel

**Output:**
- `01-backend/08-ai-integration.md`
- `01-backend/09-seeding-configuration.md`
- `01-backend/10-presets-guidelines.md`
- `01-backend/12-instruction-history.md`
- `01-backend/13-consistency-checker.md`
- `02-frontend/07-voice-input.md`
- `02-frontend/08-ai-chat.md`

---

### Phase 9: Testing & Deployment Spec ✅

**Deliverables:**
- [x] Backend test requirements (Go tests)
- [x] Frontend test requirements (Vitest)
- [x] E2E test scenarios
- [x] Deployment checklist
- [x] Documentation standards

**Output:** `03-testing-deployment.md`

---

### Phase 10: Implementation Guidelines ✅

**Deliverables:**
- [x] AI-friendly implementation checklists
- [x] Gap analysis documentation
- [x] Glossary and terminology

**Output:** `04-implementation-guidelines.md`, `05-gap-analysis.md`

---

### Phase 11: Code Generation System ✅

**Duration:** 20 days (8 sub-phases)  
**Specification Files:** 16

**Deliverables:**
- [x] System architecture and component design
- [x] 4-layer coding guidelines hierarchy (General → Language → User → Project)
- [x] Parallel code generation with topological sort
- [x] Git integration (local + GitHub/GitLab OAuth)
- [x] Build verification with brun CLI integration
- [x] Credit system with hybrid triggers
- [x] Coding model presets and selection
- [x] Data models (15 GORM entities)
- [x] REST API endpoints (26) and WebSocket events (6)
- [x] Error codes (8xxx range, 70 codes)
- [x] Testing strategy (60/30/10 pyramid)
- [x] Implementation guide with 8-phase roadmap

**Output:** `05-features/24-code-generation-system/` (16 files)

**Sub-phases:**

| # | Name | Duration | Dependencies |
|---|------|----------|--------------|
| 1 | Foundation & Data Models | 3 days | None |
| 2 | Coding Guidelines Hierarchy | 2 days | Sub-phase 1 |
| 3 | Plan Generator | 2 days | Sub-phase 2 |
| 4 | Parallel Execution Engine | 3 days | Sub-phase 3 |
| 5 | Git Integration | 3 days | Sub-phase 1 |
| 6 | Build Verification | 2 days | Sub-phase 4, brun CLI |
| 7 | Credit System | 2 days | Sub-phase 4 |
| 8 | API & Frontend | 3 days | All previous |

**Key Architectural Decisions:**
- **Guideline Priority:** Override with priority (later layers override earlier)
- **Dependency Handling:** Topological sort for parallel execution batches
- **Credit Triggers:** Hybrid (Per AI Request + Per File + Per Build Cycle)
- **Concurrency:** Per-project parallel with isolated worker pools

---

## Complete Spec Inventory

### Backend Specs (13 files)

| # | File | Description |
|---|------|-------------|
| 01 | `01-overview.md` | Architecture & folder structure |
| 02 | `02-database-schema.md` | SQLite tables, indexes, migrations |
| 03 | `03-api-endpoints.md` | REST API design & error codes |
| 04 | `04-file-operations.md` | FS CRUD & metadata sync |
| 05 | `05-git-integration.md` | Auto-commit & push |
| 06 | `06-history-system.md` | Snapshots & restore |
| 07 | `07-authentication.md` | JWT sessions & bcrypt |
| 08 | `08-ai-integration.md` | LLaMA server & models |
| 09 | `09-seeding-configuration.md` | Bootstrap data |
| 10 | `10-presets-guidelines.md` | Rule templates |
| 11 | `11-instruction-system.md` | Voice-to-tasks pipeline |
| 12 | `12-instruction-history.md` | Change tracking & rollback |
| 13 | `13-consistency-checker.md` | Automated validation |

### Frontend Specs (9 files)

| # | File | Description |
|---|------|-------------|
| 01 | `01-overview.md` | Architecture & components |
| 02 | `02-theme-system.md` | 4 themes with CSS variables |
| 03 | `03-project-dashboard.md` | Card grid & navigation |
| 04 | `04-folder-tree.md` | Tree with drag-and-drop |
| 05 | `05-markdown-editor.md` | CodeMirror integration |
| 06 | `06-history-ui.md` | Snapshot timeline |
| 07 | `07-voice-input.md` | Recording & transcription |
| 08 | `08-ai-chat.md` | Reasoning panel |
| 09 | `09-folder-sync.md` | External sync wizard |

### Supporting Docs (5 files)

| File | Description |
|------|-------------|
| `00-overview.md` | Project index |
| `01-roadmap.md` | This file |
| `03-testing-deployment.md` | QA & release |
| `04-summary.md` | Quick reference |
| `05-glossary.md` | Terms & acronyms |
| `99-consistency-report.md` | Static audit |

---

## Dependencies (Completed)

```
Phase 1 ──▶ Phase 2 ──▶ Phase 3 ──┬──▶ Phase 4 ──▶ Phase 5
     ✅          ✅          ✅    │        ✅          ✅
                                  │
                                  └──▶ Phase 6 ──▶ Phase 7
                                            ✅          ✅
                                            │
                                            └──▶ Phase 8 ──▶ Phase 9
                                                      ✅          ✅
```

---

## Next Steps: Implementation

With all specifications complete, the project is ready for implementation:

### Recommended Implementation Order

1. **Backend Foundation**
   - SQLite database setup & migrations
   - Authentication system (register/login/JWT)
   - Basic project & file CRUD

2. **Frontend Foundation**
   - Theme system & design tokens
   - Project dashboard
   - Router & layout

3. **Core Features**
   - Folder tree with drag-and-drop
   - Markdown editor with auto-save
   - File sync between DB & filesystem

4. **History & Git**
   - Snapshot creation/restore
   - Git auto-commit integration
   - External change detection

5. **AI Integration**
   - LLaMA server management
   - Voice input & transcription
   - Instruction system & chat panel

6. **Polish & Testing**
   - Consistency checker
   - E2E tests
   - Documentation

---

## Notes

- All specs follow [General Spec Standards](../general-spec/00-overview.md)
- Database uses PascalCase naming convention
- API follows standard envelope: `{success, data, error, meta}`
- Error codes aligned with general-spec ranges (1xxx-9xxx)
- Frontend uses React Query + Zustand for state management
