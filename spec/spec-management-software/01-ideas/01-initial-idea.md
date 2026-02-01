# Idea: Spec Management Software

**Status:** Draft  
**Priority:** High  
**Complexity:** Complex  
**Created:** 2026-01-27  

## Summary

A full-stack application built with a Go backend and React frontend that manages specification documents in an organized, project-based way. The system supports markdown editing, history snapshots, Git integration, and AI-powered voice-to-spec generation using a local LLM server.

## Problem Statement

Managing large specification repositories manually is error-prone and time-consuming. There is no unified way to:

- Browse and edit spec files with a structured UI
- Track changes with versioned snapshots
- Integrate Git commits seamlessly
- Use AI assistance to generate and refine specifications from voice input

This application solves these problems by providing a centralized, database-backed system with a polished frontend.

## Proposed Solution

### Backend (Go)

**Initial Seeding and Indexing:**
- On first run, scan the Spec folder to discover projects, categories, and Markdown files
- If the SQLite database does not exist, initialize and seed it from the filesystem state
- Treat each root folder under Spec as a project; project name and slug default to the folder name
- Store project paths and all discovered file/folder relationships

**Project and Path Management:**
- API supports renaming and moving folders/files, updating stored paths accordingly
- Changes from UI are applied to filesystem and persisted to SQLite, including history records

**Git Integration:**
- Assume the repository is a Git repository
- Support two modes: automatic commit mode and explicit commit event mode
- Define commit message format, trigger conditions, and failure handling

**Storage Locations:**
- Inside the Go backend: `data/` folder containing SQLite database
- Inside each Spec project folder: `.history/` folder for snapshots

### Frontend (React)

**Core UI:**
- Dashboard listing projects as cards
- Project tree view showing folder structure when opening a project
- Drag-and-drop interactions to move folders and files
- High-quality Markdown editor for editing spec files
- 2–4 selectable UI themes with persistence

**API-Driven Editing:**
- All file operations happen via backend APIs
- UI sends commands → backend applies changes → persists to SQLite → updates history

**History Management UI:**
- View snapshots for a project
- Create snapshots on demand or automatically on change
- Restore snapshots (restoring also creates a new history entry)
- Delete snapshots (removes files on disk and records in SQLite)

### Snapshot and History Model

**Filesystem-Based Snapshots:**
- `.history/` directory inside each project's Spec folder
- Each snapshot is a full copy of current files at that time
- Naming format: `V{nn}-{YYYY-MM-DD}` (e.g., `V01-2026-01-27`)
- Restoring creates a new snapshot representing pre/post restore state

**Database Tracking:**
- SQLite stores snapshot metadata: snapshotName, createdAt, createdBy, notes
- Database stores parent-child relationships for folders/files and tracks change history
- File contents stored on filesystem, not in database

### Categories and Projects

- Support optional project categories (e.g., "WordPress plugin" is a category)
- Projects may belong to a category or live at Spec root without a category
- UI allows browsing by category and by all projects

### AI Integration (LLM Server)

**Configuration:**
- Config in Go backend for LLM server executable location
- Config for multiple model folders
- Support two model types: reasoning model and voice model

**Voice-to-Spec Pipeline:**
1. User provides voice input in React UI
2. Voice model converts voice to text
3. Reasoning model generates idea file first
4. Reasoning model proofreads and structures the idea
5. Reasoning model transitions to spec mode, generating phase-by-phase spec files
6. Reasoning model detects ambiguities and generates questions for user
7. User responds by voice, pipeline incorporates answers to refine specs

**Deferred Features:**
- Google search integration (marked with `?` for future discussion)

## Acceptance Criteria

### Backend
- [ ] Go backend scans Spec folder and seeds SQLite on first run
- [ ] API supports CRUD operations for projects, folders, and files
- [ ] File rename/move updates both filesystem and database
- [ ] Git integration supports automatic and explicit commit modes
- [ ] History snapshots created and stored in `.history/` folder
- [ ] Snapshot restore creates new history entry
- [ ] Snapshot deletion removes files and database records

### Frontend
- [ ] Dashboard displays project cards
- [ ] Project tree view with drag-and-drop reordering
- [ ] Markdown editor with syntax highlighting and preview
- [ ] Theme selector with 2–4 themes, persisted to backend
- [ ] History UI shows snapshots with create/restore/delete actions
- [ ] Category-based and all-projects browsing

### AI Integration
- [ ] Voice input captured in React UI
- [ ] Voice model converts speech to text
- [ ] Reasoning model generates idea files
- [ ] Reasoning model generates spec files phase-by-phase
- [ ] Ambiguity detection with question generation
- [ ] Voice response incorporation into spec refinement

## Technical Considerations

**Dependencies:**
- Go standard library + SQLite driver (e.g., `mattn/go-sqlite3`)
- React + TypeScript + preferred component library
- LLM server executable (local installation)
- Git CLI or library for commit operations

**Database Schema (Markdown tables only):**
- `projects` — id, name, slug, categoryId, path, createdAt, updatedAt
- `categories` — id, name, slug, createdAt
- `nodes` — id, projectId, parentId, type (folder/file), name, path, order
- `snapshots` — id, projectId, name, createdAt, createdBy, notes
- `changeEvents` — id, projectId, nodeId, action, oldValue, newValue, timestamp
- `gitCommits` — id, projectId, commitHash, message, timestamp
- `uiPreferences` — id, userId, theme, lastProjectId

**API Design:**
- RESTful endpoints for projects, nodes, snapshots, preferences
- WebSocket optional for real-time updates

**Security Implications:**
- File path validation to prevent directory traversal
- Input sanitization for all API endpoints
- Git credential handling (if push/pull supported)

## Related Specs

> **Migration Note:** Specs have been consolidated into `05-features/`. See updated locations below.

- [Authentication](../05-features/01-authentication/00-overview.md)
- [File Management](../05-features/02-file-management/00-overview.md)
- [History System](../05-features/07-history-system/00-overview.md)
- [AI Integration](../05-features/06-ai-integration/00-overview.md)
- [Database Schema](../07-database-design/00-overview.md)
- [API Endpoints](../05-features/15-api-client/00-overview.md)
- [Theme System](../05-features/10-theme-system/00-overview.md)

## Open Questions

| # | Question | Status |
|---|----------|--------|
| 1 | Are snapshots created automatically on every change or only on explicit actions? | Open |
| 2 | How are large projects and snapshot storage limits handled? | Open |
| 3 | Do Git commits happen per operation, per batch, or per snapshot? | Open |
| 4 | What are user authentication and permissions expectations? | Open |
| 5 | How are conflicts handled when filesystem changes occur outside the application? | Open |
| 6 | Google search integration scope and implementation? | Deferred (?) |

## Notes

**Original Input Sources:**
- Verbatim voice transcript captured and proofread
- Refined into structured prompt format

**Phased Implementation Plan:**
Specs should be created phase-by-phase, each small enough to implement in approximately 4–5 credits:

1. `01-idea-spec-management-software.md` — This file
2. `02-backend-seeding-and-indexing.md`
3. `03-frontend-dashboard-and-navigation.md`
4. `04-markdown-editor-and-file-ops.md`
5. `05-history-snapshots-and-restore.md`
6. `06-git-integration.md`
7. `07-data-model.md`
8. `08-llm-ai-integration.md`
9. `09-acceptance-criteria.md`

**Key Design Decisions:**
- SQLite for simplicity and portability
- ORM-based access from Go backend
- Filesystem-based snapshots (not database blobs)
- Voice-first AI interaction with reasoning chain
