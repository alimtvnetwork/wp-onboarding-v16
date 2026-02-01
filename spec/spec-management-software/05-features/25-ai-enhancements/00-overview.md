# AI Enhancements

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Comprehensive AI system enhancements covering Plan Mode, voice resilience, offline-first architecture, Lovable-style chat UI, and cross-project memory sharing.

**Cross-References:**
- [AI Integration](../06-ai-integration/00-overview.md)
- [Voice Input](../05-voice-input/00-overview.md)
- [Knowledge Memory](../09-knowledge-memory/00-overview.md)
- [State Management](../16-state-management/00-overview.md)
- [Realtime](../18-realtime/00-overview.md)

---

## Implementation Phases

| Phase | Focus | Components | Est. Complexity |
|-------|-------|------------|-----------------|
| 1 | [Offline-First Storage](./01-offline-first-storage.md) | localStorage versioning, sync queue | Medium |
| 2 | [Voice Resilience](./02-voice-resilience.md) | Audio capture, Local Whisper, failure recovery | High |
| 3 | [Plan Mode](./03-plan-mode.md) | Execution planning, approval workflow, modification | Medium |
| 4 | [Mermaid Diagrams](./04-mermaid-diagrams.md) | Architectural diagram generation, model categorization | Low |
| 5 | [Chat UI Redesign](./05-chat-ui-redesign.md) | Lovable-style interface, plus menu, actions panel | High |
| 6 | [Cross-Project Memory](./06-cross-project-memory.md) | Memory sharing, project spec references | High |

---

## Key Requirements

### Data Resilience
- **Zero Data Loss:** All input (text, voice, single character) saved to localStorage first
- **Offline Support:** Full functionality without network connection
- **Version-Keyed Storage:** App version as root key, auto-cleanup on version change
- **Sync Queue:** Pending operations sync when connection restored

### Voice Pipeline
- **Audio Format:** Best quality format (WebM/Opus or MP3)
- **Transcription:** Local Whisper via Go backend
- **Failure Recovery:** Audio saved locally, retried on reconnection

### AI Modes
- **Spec Mode:** Specification drafting (existing)
- **Coding Mode:** Code generation with Run button (existing)
- **Plan Mode:** NEW - Show execution plan before running

### Chat Features (Lovable-style)
- Plus (+) button with action menu
- History panel
- Knowledge/memory management
- Connectors (GitHub, etc.)
- Screenshot attachment
- File/URL references
- Memory add/remove/share

---

## Model Categorization

| Category | Purpose | Models | Config Key |
|----------|---------|--------|------------|
| Thinking | Reasoning, planning | r1, o1, qwen-thinking | `ai.models.thinking` |
| Writing | Content generation | llama-3, mistral | `ai.models.writing` |
| Voice | Transcription | whisper-large-v3 | `ai.models.voice` |
| Coding | Code generation | codellama, deepseek | `ai.models.coding` |
| Diagram | Mermaid/architecture | llama-3, mistral | `ai.models.diagram` |

---

## Database Changes

### New Tables

```sql
-- Pending sync queue for offline operations
CREATE TABLE sync_queue (
  id TEXT PRIMARY KEY,
  operation TEXT NOT NULL, -- 'create', 'update', 'delete'
  entity_type TEXT NOT NULL, -- 'message', 'audio', 'file'
  entity_id TEXT NOT NULL,
  payload TEXT NOT NULL, -- JSON
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  retries INTEGER DEFAULT 0,
  last_error TEXT
);

-- Audio recordings for voice input
CREATE TABLE audio_recordings (
  id TEXT PRIMARY KEY,
  project_id TEXT NOT NULL,
  chat_session_id TEXT,
  audio_data BLOB NOT NULL,
  format TEXT NOT NULL, -- 'webm', 'mp3', 'wav'
  duration_ms INTEGER,
  transcription TEXT,
  transcription_status TEXT DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  synced_at DATETIME,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

-- Cross-project memory references
CREATE TABLE memory_shares (
  id TEXT PRIMARY KEY,
  source_project_id TEXT NOT NULL,
  target_project_id TEXT NOT NULL,
  memory_type TEXT NOT NULL, -- 'spec', 'folder', 'file', 'url'
  memory_path TEXT NOT NULL,
  shared_by TEXT NOT NULL,
  shared_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (source_project_id) REFERENCES projects(id),
  FOREIGN KEY (target_project_id) REFERENCES projects(id)
);

-- Execution plans for Plan Mode
CREATE TABLE execution_plans (
  id TEXT PRIMARY KEY,
  project_id TEXT NOT NULL,
  chat_session_id TEXT NOT NULL,
  plan_json TEXT NOT NULL, -- Mermaid + steps JSON
  status TEXT DEFAULT 'draft', -- 'draft', 'approved', 'executing', 'completed', 'cancelled'
  current_step INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME,
  completed_at DATETIME,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);
```

---

## Seeding Config Additions

```yaml
# config/seed.yaml additions

ai:
  models:
    thinking:
      - id: "r1"
        name: "DeepSeek R1"
        priority: 1
      - id: "o1"
        name: "OpenAI O1"
        priority: 2
      - id: "qwen-thinking"
        name: "Qwen Thinking"
        priority: 3
    
    diagram:
      - id: "llama-3"
        name: "LLaMA 3"
        priority: 1
        capabilities:
          - mermaid
          - architecture
          - flowchart
      - id: "mistral"
        name: "Mistral"
        priority: 2
        capabilities:
          - mermaid
          - sequence
    
    voice:
      - id: "whisper-large-v3"
        name: "Whisper Large v3"
        local: true
        priority: 1
        formats:
          - webm
          - mp3
          - wav

storage:
  offline:
    version_key_format: "specmgmt_v{version}"
    auto_cleanup: true
    max_audio_size_mb: 50
    sync_retry_interval_ms: 5000
    max_retries: 10
```

---

## Component Counts

| Phase | Backend | Frontend | Tests |
|-------|---------|----------|-------|
| 1. Offline-First | 1 | 3 | 2 |
| 2. Voice Resilience | 3 | 2 | 2 |
| 3. Plan Mode | 2 | 2 | 1 |
| 4. Mermaid Diagrams | 1 | 1 | 1 |
| 5. Chat UI Redesign | 0 | 5 | 2 |
| 6. Cross-Project Memory | 2 | 2 | 2 |
| **Total** | **9** | **15** | **10** |

---

## Related Specs

- [AI Integration](../06-ai-integration/00-overview.md)
- [Voice Input](../05-voice-input/00-overview.md)
- [Knowledge Memory](../09-knowledge-memory/00-overview.md)
- [Realtime](../18-realtime/00-overview.md)
- [State Management](../16-state-management/00-overview.md)
