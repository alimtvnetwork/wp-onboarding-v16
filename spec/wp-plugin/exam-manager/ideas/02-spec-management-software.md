# Idea: Spec Management Software

**Status:** Draft  
**Priority:** High  
**Complexity:** Complex  
**Created:** 2026-01-27  

---

## Summary

A full-stack application for managing specification documents with a Golang backend (SQLite, Git integration) and React frontend (Markdown editor, folder tree, theming). Features AI-powered voice-to-spec generation using LLaMA server integration.

---

## Problem Statement

Managing large specification repositories manually is error-prone and time-consuming. There's no unified tool that combines:
- Visual folder/file management with drag-and-drop
- Version history with snapshot/restore capabilities
- AI-assisted spec generation from voice input
- Git integration for version control
- Multi-user collaboration

---

## Proposed Solution

### Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    React Frontend                        │
│  (Themes, Folder Tree, Markdown Editor, Voice Input)    │
└─────────────────────┬───────────────────────────────────┘
                      │ REST API
┌─────────────────────▼───────────────────────────────────┐
│                   Golang Backend                         │
│  (File Ops, Git, History, Auth, LLaMA Integration)      │
└─────────────────────┬───────────────────────────────────┘
                      │
        ┌─────────────┼─────────────┐
        ▼             ▼             ▼
   ┌─────────┐  ┌──────────┐  ┌──────────┐
   │ SQLite  │  │ Spec FS  │  │ LLaMA    │
   │ Database│  │ (.history)│  │ Server   │
   └─────────┘  └──────────┘  └──────────┘
```

---

## Core Features

### 1. Backend (Golang)

#### 1.1 File System Management
- Read `spec/` folder structure on initialization
- Create, rename, move, delete files and folders
- Detect external file changes and sync to database
- Support nested category structures (unlimited depth)

#### 1.2 SQLite Database
- Location: `go-backend/data/spec.db`
- Store project metadata, file paths, user accounts
- Track parent-child relationships for folders/files
- Store snapshot history metadata
- Store LLaMA server configuration

#### 1.3 Git Integration
- Assume `spec/` root is a Git repository
- Auto-commit on file changes with descriptive messages
- Auto-push after commits
- Message format: `[Spec] Modified: {filename} | Version: {version}`

#### 1.4 History/Snapshot System
- Location: `spec/{category}/.history/`
- Snapshot naming: `V{nn}-{YYYY-MM-DD-HHmmss}/`
- Each snapshot contains full file copies
- Create, restore, remove snapshots via API
- Database tracks snapshot metadata

#### 1.5 Authentication
- Multi-user support via SQLite
- User registration and login
- Session management

#### 1.6 Configuration
- Seeding file for initial configuration
- LLaMA server executable path
- Model folder paths (voice model, reasoning model)
- Google Search API (placeholder for future)

### 2. Frontend (React)

#### 2.1 Theming
- Minimum 2-4 theme options
- Theme persistence per user
- Clean, modern UI design

#### 2.2 Project Dashboard
- Card-based view of projects/categories
- Visual distinction for categories vs root projects
- Quick navigation to project contents

#### 2.3 Folder Tree View
- Hierarchical display of spec structure
- Drag-and-drop for file/folder reorganization
- Context menu for operations (rename, delete, move)
- Visual indicators for modified files

#### 2.4 Markdown Editor
- Rich markdown editing with preview
- Syntax highlighting
- Auto-save functionality
- All changes sent to backend API

#### 2.5 History Management UI
- View all snapshots with timestamps
- Create new snapshots
- Restore to any snapshot
- Delete snapshots (removes files + DB record)
- Visual diff between versions (future enhancement)

### 3. AI Integration (LLaMA Server)

#### 3.1 Configuration
- Seeded config → Database (modifiable)
- LLaMA server executable path
- Voice model folder path
- Reasoning model folder path

#### 3.2 AI Chain Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────────────┐
│ Voice Input │────▶│ Voice Model │────▶│ Text Transcription  │
└─────────────┘     └─────────────┘     └──────────┬──────────┘
                                                    │
                                                    ▼
                                        ┌─────────────────────┐
                                        │  Reasoning Model    │
                                        │  (Ambiguity Check)  │
                                        └──────────┬──────────┘
                                                    │
                         ┌──────────────────────────┼──────────────────────────┐
                         ▼                          ▼                          ▼
              ┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
              │ Has Ambiguities │       │ Clear Input     │       │ Needs Research  │
              │ → Ask Questions │       │ → Generate Idea │       │ → Google (?)    │
              └────────┬────────┘       └────────┬────────┘       └─────────────────┘
                       │                         │
                       ▼                         ▼
              ┌─────────────────┐       ┌─────────────────┐
              │ User Answers    │       │ Create Idea.md  │
              │ (Voice/Text)    │       │ Then Spec.md    │
              └────────┬────────┘       └─────────────────┘
                       │
                       ▼
              ┌─────────────────┐
              │ Refined Input   │
              │ → Generate Spec │
              └─────────────────┘
```

#### 3.3 AI Features
- **Voice Input**: Capture user voice, convert to text
- **Ambiguity Detection**: Analyze input for unclear requirements
- **Question Generation**: Create clarifying questions for user
- **Idea Generation**: Structure raw input into Idea file format
- **Spec Generation**: Expand Idea into detailed Spec file
- **Google Search**: (?) Future feature for research assistance

---

## Data Models

### User Table
| Field | Type | Description |
|-------|------|-------------|
| Id | UUID | Primary key |
| Username | String | Unique username |
| PasswordHash | String | Hashed password |
| Email | String | User email |
| CreatedAt | DateTime | Account creation |
| LastLoginAt | DateTime | Last login timestamp |
| ThemePreference | String | Selected theme name |

### Project Table
| Field | Type | Description |
|-------|------|-------------|
| Id | UUID | Primary key |
| Name | String | Display name |
| Slug | String | URL-safe identifier |
| Path | String | Relative path from spec root |
| ParentId | UUID | Nullable, for nested categories |
| Type | Enum | 'category' or 'project' |
| CreatedAt | DateTime | Creation timestamp |
| UpdatedAt | DateTime | Last modification |

### File Table
| Field | Type | Description |
|-------|------|-------------|
| Id | UUID | Primary key |
| ProjectId | UUID | Foreign key to Project |
| Name | String | Filename |
| Path | String | Relative path |
| ParentId | UUID | Nullable, for nested folders |
| Type | Enum | 'folder' or 'file' |
| ContentHash | String | MD5 for change detection |
| CreatedAt | DateTime | Creation timestamp |
| UpdatedAt | DateTime | Last modification |

### Snapshot Table
| Field | Type | Description |
|-------|------|-------------|
| Id | UUID | Primary key |
| ProjectId | UUID | Foreign key to Project |
| Name | String | e.g., "V01-2026-01-27-143022" |
| Description | String | Optional user description |
| CreatedBy | UUID | User who created snapshot |
| CreatedAt | DateTime | Snapshot timestamp |

### Config Table
| Field | Type | Description |
|-------|------|-------------|
| Key | String | Primary key |
| Value | String | Configuration value |
| Source | Enum | 'seed' or 'user' |
| UpdatedAt | DateTime | Last modification |

### Config Keys (Seeded)
- `llama_server_path`: Path to llama.cpp executable
- `voice_model_path`: Path to voice model folder
- `reasoning_model_path`: Path to reasoning model folder
- `google_search_enabled`: Boolean (default: false)
- `google_search_api_key`: (?) Future use

---

## API Endpoints (Draft)

### Authentication
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`

### Projects
- `GET /api/projects` - List all projects/categories
- `GET /api/projects/:id` - Get project details
- `POST /api/projects` - Create project/category
- `PUT /api/projects/:id` - Update project
- `DELETE /api/projects/:id` - Delete project

### Files
- `GET /api/files/:projectId` - List files in project
- `GET /api/files/:id/content` - Get file content
- `POST /api/files` - Create file/folder
- `PUT /api/files/:id` - Update file metadata
- `PUT /api/files/:id/content` - Update file content
- `DELETE /api/files/:id` - Delete file/folder
- `POST /api/files/:id/move` - Move file/folder

### Snapshots
- `GET /api/snapshots/:projectId` - List snapshots
- `POST /api/snapshots` - Create snapshot
- `POST /api/snapshots/:id/restore` - Restore snapshot
- `DELETE /api/snapshots/:id` - Delete snapshot

### AI
- `POST /api/ai/transcribe` - Voice to text
- `POST /api/ai/analyze` - Analyze for ambiguities
- `POST /api/ai/generate-idea` - Generate idea file
- `POST /api/ai/generate-spec` - Generate spec file

### Config
- `GET /api/config` - Get all config
- `PUT /api/config/:key` - Update config value

### Sync
- `POST /api/sync/scan` - Scan for external changes
- `POST /api/sync/apply` - Apply detected changes

---

## Open Questions

| # | Question | Status |
|---|----------|--------|
| 1 | Google Search integration details | Deferred (?) |
| 2 | Specific LLaMA model recommendations | TBD |
| 3 | Conflict resolution for external edits | TBD |
| 4 | Real-time collaboration features | Out of scope (v1) |

---

## Implementation Phases

### Phase 1: Idea & Data Models ✓ (This File)
- Document core concept
- Define data models
- Outline API structure

### Phase 2: Project Folder Structure
- Create `spec/spec-management-software/` folder
- Set up backend/frontend structure docs
- Create detailed roadmap

### Phase 3: Database Schema Spec
- Detailed SQLite schema
- Seed data structure
- Migration strategy

### Phase 4: Backend API Spec - Core
- File system operations
- Project/File CRUD
- Authentication

### Phase 5: Backend API Spec - History
- Snapshot management
- Git integration
- External change detection

### Phase 6: Frontend Spec - Core
- Theme system
- Project dashboard
- Folder tree component

### Phase 7: Frontend Spec - Editor
- Markdown editor
- File operations UI
- History management UI

### Phase 8: AI Integration Spec
- LLaMA server integration
- Voice processing pipeline
- Reasoning chain implementation

### Phase 9: Testing & Deployment
- Test specifications
- Deployment checklist
- Documentation

---

## Technical Considerations

### Dependencies
- **Backend**: Go 1.21+, SQLite3, go-git library
- **Frontend**: React 18+, TypeScript, TailwindCSS
- **AI**: llama.cpp server (pre-installed)

### Security
- Password hashing (bcrypt)
- JWT session tokens
- File path validation (prevent traversal)
- Sanitize markdown content

### Performance
- File content caching
- Lazy loading for large folders
- Debounced auto-save
- Background Git operations

### File System Watching
- Use fsnotify (Go) for external change detection
- Periodic polling as fallback
- Queue changes for batch processing

---

## Related Specs

- [Ideas Folder README](./README.md)
- [General Spec Overview](../../general-spec/00-overview.md)

---

## Notes

- This is the master idea document. Subsequent phases will create detailed specs in a dedicated project folder.
- Credit budget per phase: 4-5 credits maximum
- All code generation deferred until specs are complete
- Voice input UI should support both push-to-talk and continuous modes
