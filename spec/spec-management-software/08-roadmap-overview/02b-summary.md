# Spec Management Software - Summary

**Version:** 1.1.0  
**Status:** Specification Complete  
**Updated:** 2026-01-28

> **⚠️ Historical Document:** File references to `01-backend/` and `02-frontend/` are preserved for historical context.  
> All specs have been migrated to `05-features/`. See [05-features Overview](../05-features/00-overview.md) for current locations.

---

## Quick Reference

| Component | Technology | Key Files |
|-----------|------------|-----------|
| Backend | Go + SQLite + Git | `05-features/*` (consolidated) |
| Frontend | React + TypeScript | `05-features/*` (consolidated) |
| Testing | Go Test + Vitest + Playwright | `06-testing-deployment.md` |

---

## Phase Summary

### Phase 1: Idea & Data Models ✅
**Output:** Core concept and data model definitions

- Project hierarchy with categories
- File/Snapshot versioning model
- AI chain flow (Voice → Reasoning → Generation)
- `.history` folder structure

---

### Phase 2: Project Structure ✅
**Output:** Folder hierarchy

```
spec/spec-management-software/
├── 00-overview.md
├── 01-roadmap.md
├── 01-backend/
├── 02-frontend/
├── 03-testing-deployment.md
├── 04-summary.md
└── ideas/
```

---

### Phase 3: Database Schema ✅
**Output:** `01-backend/02-database-schema.md`

| Table | Purpose |
|-------|---------|
| User | Authentication accounts |
| Session | JWT session management |
| Project | Hierarchy with ParentId |
| File | Markdown file metadata |
| Snapshot | V{nn}-{date} versioning |
| Config | LLaMA paths, settings |

**Key Patterns:**
- PascalCase columns
- Cascade deletes for integrity
- `UpdatedAt` triggers

---

### Phase 4: Backend Core API ✅
**Output:** `01-backend/03-api-endpoints.md`

| Category | Endpoints |
|----------|-----------|
| Auth | `POST /register`, `/login`, `/logout` |
| Projects | CRUD + `/tree` hierarchy |
| Files | Content + metadata operations |
| Snapshots | Create, restore, list, delete |

**Response Envelope:**
```json
{ "success": bool, "data": {}, "error": {}, "meta": {} }
```

---

### Phase 5: History & Git Integration ✅
**Output:** `01-backend/05-git-integration.md`, `06-history-system.md`

**Git Integration:**
- CommitQueue with 2s debounce
- Format: `[Spec] {Action}: {Target}`
- SSH + HTTPS/Token auth
- fsnotify for external changes

**History System:**
- Snapshots: `V{nn}-{YYYY-MM-DD}/`
- PRE-RESTORE safety snapshots
- Retention: 90 days / 50 max
- Full file copies per snapshot

---

### Phase 6: Frontend Core ✅
**Output:** `02-frontend/02-theme-system.md`, `03-project-dashboard.md`

**Themes (4 Options):**
| Theme | Background | Accent |
|-------|------------|--------|
| Light | `#FFFFFF` | `#3B82F6` |
| Dark | `#1E1E1E` | `#60A5FA` |
| Nord | `#2E3440` | `#88C0D0` |
| Solarized | `#002B36` | `#268BD2` |

**Dashboard:**
- Card grid (responsive: 1/2/3 cols)
- Quick actions (New Project, Search)
- Category grouping with drag-drop

---

### Phase 7: Frontend Editor ✅
**Output:** `02-frontend/04-folder-tree.md`, `05-markdown-editor.md`, `06-history-ui.md`

**Folder Tree:**
- `@dnd-kit/core` drag-and-drop
- Context menu (New, Rename, Delete)
- SSE for external change sync

**Markdown Editor:**
- CodeMirror 6 + `react-markdown`
- 2s auto-save debounce
- Split view (editor | preview)

**History UI:**
- Timeline visualization
- Snapshot cards with file preview
- One-click restore with safety backup

---

### Phase 8: AI Integration ✅
**Output:** `01-backend/07-ai-integration.md`, `02-frontend/07-voice-input.md`, `08-ai-chat.md`

**AI Chain:**
```
Voice Input → Transcription → Intent Analysis → Questions → Generation
```

**Backend Components:**
- `LLaMAManager`: Process lifecycle, model swap
- `AIChainService`: 3-stage pipeline
- SSE streaming for generation

**Frontend Components:**
- Voice: Push-to-Talk / Continuous modes
- Chat: Question flow → Preview → Accept/Edit
- Waveform visualization

---

### Phase 9: Testing & Deployment ✅
**Output:** `03-testing-deployment.md`

**Test Pyramid:**
| Layer | Tool | Coverage |
|-------|------|----------|
| Unit | Go `testing` / Vitest | 80% |
| Integration | testify + SQLite | Critical paths |
| E2E | Playwright | Auth, Edit, AI flows |

**Deployment Checklist:**
- [ ] `go vet` + `golangci-lint`
- [ ] `npm run type-check`
- [ ] Coverage ≥ 80%
- [ ] Health endpoint verified
- [ ] Rollback procedure documented

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    React Frontend                        │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────────┐ │
│  │Dashboard │ │FolderTree│ │ Editor   │ │  AI Chat    │ │
│  └──────────┘ └──────────┘ └──────────┘ └─────────────┘ │
└────────────────────────┬────────────────────────────────┘
                         │ REST API + SSE
┌────────────────────────▼────────────────────────────────┐
│                    Go Backend                            │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────────┐ │
│  │  Auth    │ │ Project  │ │ History  │ │ AI Chain    │ │
│  │ Service  │ │ Service  │ │ Service  │ │  Service    │ │
│  └──────────┘ └──────────┘ └──────────┘ └─────────────┘ │
└───────┬─────────────┬─────────────┬─────────────┬───────┘
        │             │             │             │
   ┌────▼────┐   ┌────▼────┐   ┌────▼────┐   ┌────▼────┐
   │ SQLite  │   │   Git   │   │.history │   │ LLaMA   │
   │   DB    │   │  Repo   │   │ Folder  │   │ Server  │
   └─────────┘   └─────────┘   └─────────┘   └─────────┘
```

---

## Key Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Database | SQLite | Single-file, zero-config |
| Naming | PascalCase columns | Consistency with spec standards |
| Versioning | Filesystem snapshots | Independent from Git, full copies |
| Git commits | 2s debounce queue | Batch rapid changes |
| AI models | Local LLaMA | Privacy, offline capability |
| Editor | CodeMirror 6 | Performance, extensibility |
| Drag-drop | @dnd-kit | Accessible, tree-friendly |

---

## File Index

### Backend (`01-backend/`)
| File | Purpose |
|------|---------|
| `01-overview.md` | Architecture summary |
| `02-database-schema.md` | SQLite tables, indexes |
| `03-api-endpoints.md` | REST API specification |
| `05-git-integration.md` | Commit automation |
| `06-history-system.md` | Snapshot management |
| `07-ai-integration.md` | LLaMA + AI chain |

### Frontend (`02-frontend/`)
| File | Purpose |
|------|---------|
| `01-overview.md` | Component architecture |
| `02-theme-system.md` | 4-theme implementation |
| `03-project-dashboard.md` | Card grid, navigation |
| `04-folder-tree.md` | Hierarchy + drag-drop |
| `05-markdown-editor.md` | CodeMirror + preview |
| `06-history-ui.md` | Snapshot timeline |
| `07-voice-input.md` | Recording modes |
| `08-ai-chat.md` | Question flow, generation |

### Cross-Cutting
| File | Purpose |
|------|---------|
| `00-overview.md` | Project introduction |
| `01-roadmap.md` | Phase tracking |
| `03-testing-deployment.md` | QA + release process |
| `04-summary.md` | This document |

---

## Next Steps

1. **Implementation Planning** - Break down into development sprints
2. **Prototype** - Build MVP with core file management
3. **AI Integration** - Configure local LLaMA server
4. **Testing** - Establish CI pipeline with coverage gates

---

## Related Documents

- [General Spec Standards](../general-spec/00-overview.md)
- [Folder Structure Guidelines](../00-folder-structure-guideline.md)
- [Original Idea](../wp-plugin/exam-manager/ideas/02-spec-management-software.md)
