# Voice CLI Microservice

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-30  

---

## Overview

The Voice CLI is a **standalone microservice** for voice-to-text transcription, operating independently but integrating with Nexus-Flow and the Spec Management Software. It provides real-time and batch transcription via local LLM (Whisper) with optional cloud fallback.

**Cross-References:**
- [Nexus-Flow Standalone Architecture](./09-nexus-flow-standalone-architecture.md) — Integration points
- [AI-Bridge Service](./03-ai-bridge.md) — LLM provider abstraction
- [Shared pkg/ Modules](./08-shared-pkg-modules.md) — Common utilities
- [Split Database System](../07-database-design/00-overview.md)

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Technology Stack](#2-technology-stack)
3. [Database Schema](#3-database-schema)
4. [CLI Commands](#4-cli-commands)
5. [HTTP API](#5-http-api)
6. [WebSocket Streaming Protocol](#6-websocket-streaming-protocol)
7. [LLM Transcription Integration](#7-llm-transcription-integration)
8. [Audio Processing Pipeline](#8-audio-processing-pipeline)
9. [Voice Command Recognition](#9-voice-command-recognition)
10. [Integration APIs](#10-integration-apis)
11. [Error Codes](#11-error-codes)
12. [Configuration](#12-configuration)

---

## 1. Architecture Overview

### 1.1 System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Voice CLI Service                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │   CLI Tool   │  │  HTTP API    │  │   WebSocket Server       │  │
│  │  (Cobra)     │  │  (Gin/Echo)  │  │   (gorilla/websocket)    │  │
│  └──────┬───────┘  └──────┬───────┘  └────────────┬─────────────┘  │
│         │                 │                        │                 │
│         └─────────────────┼────────────────────────┘                │
│                           │                                          │
│                    ┌──────▼───────┐                                 │
│                    │   Core       │                                 │
│                    │   Engine     │                                 │
│                    └──────┬───────┘                                 │
│                           │                                          │
│         ┌─────────────────┼─────────────────┐                       │
│         │                 │                 │                        │
│  ┌──────▼──────┐  ┌───────▼──────┐  ┌──────▼───────┐               │
│  │  Audio      │  │  Transcribe  │  │  Command     │               │
│  │  Capture    │  │  Engine      │  │  Parser      │               │
│  └─────────────┘  └──────┬───────┘  └──────────────┘               │
│                          │                                           │
│         ┌────────────────┼────────────────┐                         │
│         │                │                │                          │
│  ┌──────▼──────┐  ┌──────▼──────┐  ┌──────▼──────┐                 │
│  │  Whisper    │  │  OpenAI     │  │  ElevenLabs │                 │
│  │  (Local)    │  │  Realtime   │  │  Scribe     │                 │
│  └─────────────┘  └─────────────┘  └─────────────┘                 │
│                                                                      │
├─────────────────────────────────────────────────────────────────────┤
│                        Database Layer                                │
│  ┌──────────────┐  ┌────────────────────────────────────────────┐  │
│  │   root.db    │  │  {project}/{conversation-id}.db            │  │
│  │   (Index)    │  │  (Transcripts, Entities, Commands)         │  │
│  └──────────────┘  └────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                              │
            ┌─────────────────┼─────────────────┐
            │                 │                 │
     ┌──────▼──────┐   ┌──────▼──────┐   ┌──────▼──────┐
     │ Nexus-Flow  │   │ Spec Mgmt   │   │  External   │
     │ Integration │   │ Integration │   │  Clients    │
     └─────────────┘   └─────────────┘   └─────────────┘
```

### 1.2 Deployment Modes

| Mode | Description | Use Case |
|------|-------------|----------|
| **Standalone** | Independent CLI/service | Portable transcription tool |
| **Embedded** | Library integration | Direct API calls from other services |
| **Server** | HTTP + WebSocket daemon | Multi-client transcription service |

### 1.3 Codebase Structure

```
voice-cli/
├── cmd/
│   └── voice-cli/
│       └── main.go              # CLI entry point
├── internal/
│   ├── api/
│   │   ├── http/
│   │   │   ├── server.go        # HTTP server
│   │   │   ├── handlers.go      # HTTP handlers
│   │   │   └── middleware.go    # Auth, logging
│   │   └── websocket/
│   │       ├── server.go        # WebSocket server
│   │       ├── session.go       # Session management
│   │       └── protocol.go      # Message types
│   ├── audio/
│   │   ├── capture.go           # Microphone capture
│   │   ├── encoder.go           # PCM/WebM encoding
│   │   ├── player.go            # Audio playback
│   │   └── vad.go               # Voice Activity Detection
│   ├── transcribe/
│   │   ├── engine.go            # Transcription orchestrator
│   │   ├── whisper.go           # Local Whisper adapter
│   │   ├── openai.go            # OpenAI Realtime adapter
│   │   ├── elevenlabs.go        # ElevenLabs Scribe adapter
│   │   └── providers.go         # Provider registry
│   ├── commands/
│   │   ├── parser.go            # Voice command parser
│   │   ├── grammar.go           # Command grammar rules
│   │   └── executor.go          # Command execution
│   ├── db/
│   │   ├── manager.go           # Database connection manager
│   │   ├── models.go            # GORM models
│   │   └── migrations.go        # Schema migrations
│   └── config/
│       └── config.go            # Configuration management
├── pkg/                         # Shared packages (symlink)
├── configs/
│   └── voice-cli.yaml           # Default configuration
└── build/
    └── ...                      # Build artifacts
```

---

## 2. Technology Stack

| Component | Technology | Rationale |
|-----------|------------|-----------|
| Language | Go 1.22+ | Performance, static binary |
| CLI Framework | Cobra | Standard Go CLI |
| HTTP Server | Gin | Fast, middleware support |
| WebSocket | gorilla/websocket | Production-ready |
| Database | SQLite + GORM | Portable, single-file |
| Audio Capture | PortAudio (via Go bindings) | Cross-platform |
| Local Transcription | whisper.cpp | Offline, privacy-first |
| Cloud Transcription | OpenAI Realtime, ElevenLabs | Low-latency streaming |

---

## 3. Database Schema

### 3.1 Database Hierarchy

```
voice-cli-data/
├── root.db                          # Global index and settings
└── projects/
    └── {project-name}/
        └── conversations/
            └── {conversation-id}.db  # Per-conversation transcripts
```

### 3.2 Root Database (`root.db`)

```sql
-- Settings table for global configuration
CREATE TABLE Settings (
    Id TEXT PRIMARY KEY,
    Key TEXT UNIQUE NOT NULL,
    Value TEXT,
    Category TEXT NOT NULL,        -- 'audio', 'transcription', 'storage'
    Description TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

-- Index of all registered projects
CREATE TABLE ProjectIndex (
    Id TEXT PRIMARY KEY,
    Name TEXT NOT NULL UNIQUE,
    ExternalProjectId TEXT,         -- Links to Spec Mgmt / Nexus-Flow
    ExternalSystem TEXT,            -- 'spec-mgmt', 'nexus-flow', 'standalone'
    RootPath TEXT NOT NULL,         -- Absolute path to project directory
    DefaultLanguage TEXT DEFAULT 'en',
    TranscriptionProvider TEXT DEFAULT 'whisper',
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL,
    LastAccessedAt TEXT
);

CREATE INDEX idx_project_external ON ProjectIndex(ExternalProjectId, ExternalSystem);

-- Index of all conversations across projects
CREATE TABLE ConversationIndex (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL REFERENCES ProjectIndex(Id) ON DELETE CASCADE,
    Name TEXT,
    Description TEXT,
    DatabasePath TEXT NOT NULL,     -- Relative path to conversation DB
    Status TEXT DEFAULT 'active',   -- 'active', 'archived', 'deleted'
    TotalDuration INTEGER DEFAULT 0, -- Total seconds of audio
    SegmentCount INTEGER DEFAULT 0,
    WordCount INTEGER DEFAULT 0,
    Language TEXT,
    Tags TEXT,                      -- JSON array
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL,
    LastActivityAt TEXT
);

CREATE INDEX idx_conversation_project ON ConversationIndex(ProjectId);
CREATE INDEX idx_conversation_status ON ConversationIndex(Status);

-- Active transcription sessions
CREATE TABLE ActiveSession (
    Id TEXT PRIMARY KEY,
    ConversationId TEXT NOT NULL REFERENCES ConversationIndex(Id),
    ClientId TEXT NOT NULL,         -- WebSocket client identifier
    Provider TEXT NOT NULL,         -- 'whisper', 'openai', 'elevenlabs'
    Status TEXT DEFAULT 'connected', -- 'connected', 'streaming', 'paused', 'disconnected'
    StartedAt TEXT NOT NULL,
    LastActivityAt TEXT NOT NULL,
    Metadata TEXT                   -- JSON (device info, settings)
);

CREATE INDEX idx_session_conversation ON ActiveSession(ConversationId);

-- Provider usage and quota tracking
CREATE TABLE ProviderUsage (
    Id TEXT PRIMARY KEY,
    Provider TEXT NOT NULL,
    Date TEXT NOT NULL,             -- YYYY-MM-DD
    AudioSeconds INTEGER DEFAULT 0,
    RequestCount INTEGER DEFAULT 0,
    TokensUsed INTEGER DEFAULT 0,
    Cost REAL DEFAULT 0.0,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL,
    UNIQUE(Provider, Date)
);
```

### 3.3 Conversation Database (`{conversation-id}.db`)

```sql
-- Conversation metadata
CREATE TABLE ConversationMetadata (
    Id TEXT PRIMARY KEY,
    Name TEXT,
    Description TEXT,
    Language TEXT DEFAULT 'en',
    Provider TEXT NOT NULL,         -- Primary transcription provider
    AudioFormat TEXT,               -- 'pcm16', 'webm-opus', 'wav'
    SampleRate INTEGER DEFAULT 16000,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

-- Audio recording sessions within the conversation
CREATE TABLE RecordingSession (
    Id TEXT PRIMARY KEY,
    ConversationId TEXT NOT NULL,
    Status TEXT DEFAULT 'recording', -- 'recording', 'paused', 'completed', 'failed'
    AudioFilePath TEXT,             -- Path to raw audio file (if saved)
    StartedAt TEXT NOT NULL,
    EndedAt TEXT,
    Duration REAL,                  -- Seconds
    SampleRate INTEGER,
    Channels INTEGER DEFAULT 1,
    Encoding TEXT,                  -- 'pcm16', 'opus', 'mp3'
    Metadata TEXT,                  -- JSON (device info)
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE INDEX idx_session_conversation ON RecordingSession(ConversationId);

-- Transcript segments (utterances)
CREATE TABLE TranscriptSegment (
    Id TEXT PRIMARY KEY,
    SessionId TEXT REFERENCES RecordingSession(Id) ON DELETE CASCADE,
    SequenceNumber INTEGER NOT NULL, -- Order within session
    StartTime REAL NOT NULL,        -- Seconds from session start
    EndTime REAL NOT NULL,
    Duration REAL GENERATED ALWAYS AS (EndTime - StartTime) STORED,
    Text TEXT NOT NULL,
    TextNormalized TEXT,            -- Lowercase, punctuation removed
    Confidence REAL,                -- 0.0 - 1.0
    Language TEXT,
    Speaker TEXT,                   -- Speaker identification label
    IsPartial INTEGER DEFAULT 0,    -- 1 if interim/partial transcript
    IsFinal INTEGER DEFAULT 1,      -- 1 if finalized
    Provider TEXT NOT NULL,         -- Which provider transcribed this
    ProviderMetadata TEXT,          -- JSON (model version, etc.)
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE INDEX idx_segment_session ON TranscriptSegment(SessionId);
CREATE INDEX idx_segment_time ON TranscriptSegment(StartTime, EndTime);
CREATE INDEX idx_segment_text ON TranscriptSegment(TextNormalized);

-- Word-level timestamps within segments
CREATE TABLE WordTimestamp (
    Id TEXT PRIMARY KEY,
    SegmentId TEXT NOT NULL REFERENCES TranscriptSegment(Id) ON DELETE CASCADE,
    WordIndex INTEGER NOT NULL,     -- Position in segment
    Word TEXT NOT NULL,
    StartTime REAL NOT NULL,        -- Relative to segment start
    EndTime REAL NOT NULL,
    Confidence REAL,
    CreatedAt TEXT NOT NULL
);

CREATE INDEX idx_word_segment ON WordTimestamp(SegmentId);

-- Detected entities (commands, variables, names)
CREATE TABLE TranscriptEntity (
    Id TEXT PRIMARY KEY,
    SegmentId TEXT NOT NULL REFERENCES TranscriptSegment(Id) ON DELETE CASCADE,
    Type TEXT NOT NULL,             -- 'COMMAND', 'VARIABLE', 'STAGE_NAME', 'FILE_PATH', 'NUMBER', 'DATE', 'PERSON', 'CUSTOM'
    Value TEXT NOT NULL,            -- Normalized value
    RawText TEXT NOT NULL,          -- Original text span
    StartOffset INTEGER NOT NULL,   -- Character offset in segment text
    EndOffset INTEGER NOT NULL,
    Confidence REAL,
    Metadata TEXT,                  -- JSON (additional entity info)
    CreatedAt TEXT NOT NULL
);

CREATE INDEX idx_entity_segment ON TranscriptEntity(SegmentId);
CREATE INDEX idx_entity_type ON TranscriptEntity(Type);

-- Recognized voice commands
CREATE TABLE VoiceCommand (
    Id TEXT PRIMARY KEY,
    SegmentId TEXT NOT NULL REFERENCES TranscriptSegment(Id) ON DELETE CASCADE,
    CommandType TEXT NOT NULL,      -- 'CREATE_STAGE', 'SET_VARIABLE', 'NAVIGATE', etc.
    CommandText TEXT NOT NULL,      -- Full command as spoken
    ParsedCommand TEXT NOT NULL,    -- Normalized command
    Parameters TEXT,                -- JSON parameters extracted
    ConfidenceScore REAL,
    Status TEXT DEFAULT 'pending',  -- 'pending', 'executed', 'failed', 'cancelled'
    ExecutedAt TEXT,
    ExecutionResult TEXT,           -- JSON result
    ErrorMessage TEXT,
    CreatedAt TEXT NOT NULL,
    UpdatedAt TEXT NOT NULL
);

CREATE INDEX idx_command_segment ON VoiceCommand(SegmentId);
CREATE INDEX idx_command_type ON VoiceCommand(CommandType);
CREATE INDEX idx_command_status ON VoiceCommand(Status);

-- Audio events (non-speech)
CREATE TABLE AudioEvent (
    Id TEXT PRIMARY KEY,
    SessionId TEXT NOT NULL REFERENCES RecordingSession(Id) ON DELETE CASCADE,
    Type TEXT NOT NULL,             -- 'silence', 'noise', 'music', 'laughter', 'applause'
    StartTime REAL NOT NULL,
    EndTime REAL NOT NULL,
    Confidence REAL,
    Metadata TEXT,                  -- JSON
    CreatedAt TEXT NOT NULL
);

CREATE INDEX idx_event_session ON AudioEvent(SessionId);
CREATE INDEX idx_event_type ON AudioEvent(Type);

-- Full-text search on transcripts
CREATE VIRTUAL TABLE TranscriptFTS USING fts5(
    SegmentId,
    Text,
    content='TranscriptSegment',
    content_rowid='rowid'
);

-- Triggers to keep FTS in sync
CREATE TRIGGER transcript_ai AFTER INSERT ON TranscriptSegment BEGIN
    INSERT INTO TranscriptFTS(SegmentId, Text) VALUES (new.Id, new.Text);
END;

CREATE TRIGGER transcript_ad AFTER DELETE ON TranscriptSegment BEGIN
    INSERT INTO TranscriptFTS(TranscriptFTS, SegmentId, Text) VALUES('delete', old.Id, old.Text);
END;

CREATE TRIGGER transcript_au AFTER UPDATE ON TranscriptSegment BEGIN
    INSERT INTO TranscriptFTS(TranscriptFTS, SegmentId, Text) VALUES('delete', old.Id, old.Text);
    INSERT INTO TranscriptFTS(SegmentId, Text) VALUES (new.Id, new.Text);
END;
```

### 3.4 GORM Models

```go
// internal/db/models.go

type Settings struct {
    ID          string    `gorm:"primaryKey"`
    Key         string    `gorm:"uniqueIndex;not null"`
    Value       string
    Category    string    `gorm:"not null"`
    Description string
    CreatedAt   time.Time
    UpdatedAt   time.Time
}

type ProjectIndex struct {
    ID                    string    `gorm:"primaryKey"`
    Name                  string    `gorm:"uniqueIndex;not null"`
    ExternalProjectID     string    `gorm:"index"`
    ExternalSystem        string
    RootPath              string    `gorm:"not null"`
    DefaultLanguage       string    `gorm:"default:en"`
    TranscriptionProvider string    `gorm:"default:whisper"`
    CreatedAt             time.Time
    UpdatedAt             time.Time
    LastAccessedAt        *time.Time
    Conversations         []ConversationIndex `gorm:"foreignKey:ProjectId"`
}

type ConversationIndex struct {
    ID             string    `gorm:"primaryKey"`
    ProjectID      string    `gorm:"index;not null"`
    Name           string
    Description    string
    DatabasePath   string    `gorm:"not null"`
    Status         string    `gorm:"default:active"`
    TotalDuration  int64     `gorm:"default:0"`
    SegmentCount   int64     `gorm:"default:0"`
    WordCount      int64     `gorm:"default:0"`
    Language       string
    Tags           datatypes.JSON
    CreatedAt      time.Time
    UpdatedAt      time.Time
    LastActivityAt *time.Time
    Project        ProjectIndex `gorm:"foreignKey:ProjectID"`
}

type TranscriptSegment struct {
    ID               string    `gorm:"primaryKey"`
    SessionID        string    `gorm:"index;not null"`
    SequenceNumber   int       `gorm:"not null"`
    StartTime        float64   `gorm:"not null"`
    EndTime          float64   `gorm:"not null"`
    Text             string    `gorm:"not null"`
    TextNormalized   string
    Confidence       *float64
    Language         string
    Speaker          string
    IsPartial        bool      `gorm:"default:false"`
    IsFinal          bool      `gorm:"default:true"`
    Provider         string    `gorm:"not null"`
    ProviderMetadata datatypes.JSON
    CreatedAt        time.Time
    UpdatedAt        time.Time
    Words            []WordTimestamp    `gorm:"foreignKey:SegmentID"`
    Entities         []TranscriptEntity `gorm:"foreignKey:SegmentID"`
    Commands         []VoiceCommand     `gorm:"foreignKey:SegmentID"`
}

type VoiceCommand struct {
    ID              string    `gorm:"primaryKey"`
    SegmentID       string    `gorm:"index;not null"`
    CommandType     string    `gorm:"not null"`
    CommandText     string    `gorm:"not null"`
    ParsedCommand   string    `gorm:"not null"`
    Parameters      datatypes.JSON
    ConfidenceScore *float64
    Status          string    `gorm:"default:pending"`
    ExecutedAt      *time.Time
    ExecutionResult datatypes.JSON
    ErrorMessage    string
    CreatedAt       time.Time
    UpdatedAt       time.Time
    Segment         TranscriptSegment `gorm:"foreignKey:SegmentID"`
}
```

---

## 4. CLI Commands

### 4.1 Command Structure

```bash
voice-cli [global-flags] <command> [command-flags] [arguments]
```

### 4.2 Global Flags

| Flag | Short | Description | Default |
|------|-------|-------------|---------|
| `--config` | `-c` | Config file path | `~/.voice-cli/config.yaml` |
| `--data-dir` | `-d` | Data directory | `~/.voice-cli/data` |
| `--project` | `-p` | Active project name | (required for most commands) |
| `--verbose` | `-v` | Verbose output | `false` |
| `--json` | `-j` | JSON output format | `false` |
| `--quiet` | `-q` | Suppress non-error output | `false` |

### 4.3 Project Management Commands

```bash
# Initialize a new project
voice-cli project init <project-name> [flags]
  --external-id     Link to external project ID
  --external-system System type (spec-mgmt, nexus-flow)
  --language        Default language (en, es, de, etc.)
  --provider        Default provider (whisper, openai, elevenlabs)

# List all projects
voice-cli project list [flags]
  --status          Filter by status (active, archived)
  --format          Output format (table, json, csv)

# Show project details
voice-cli project info <project-name>

# Update project settings
voice-cli project update <project-name> [flags]
  --name            New project name
  --language        Default language
  --provider        Default provider

# Archive/delete project
voice-cli project archive <project-name>
voice-cli project delete <project-name> --confirm

# Link to external system
voice-cli project link <project-name> --external-id <id> --system <system>
```

### 4.4 Recording Commands

```bash
# Start live recording with real-time transcription
voice-cli record [flags]
  --project, -p     Project name (required)
  --conversation    Conversation ID (auto-generated if not provided)
  --provider        Transcription provider override
  --language        Language override
  --device          Audio input device (default: system default)
  --sample-rate     Sample rate in Hz (default: 16000)
  --vad             Enable Voice Activity Detection (default: true)
  --save-audio      Save raw audio file (default: false)
  --output, -o      Output file for transcript

# Example: Start recording with Whisper
voice-cli record -p my-project --provider whisper --vad

# Record with specific device
voice-cli record -p my-project --device "USB Microphone"

# Record and save audio
voice-cli record -p my-project --save-audio --output transcript.json
```

### 4.5 Transcription Commands

```bash
# Transcribe audio file (batch)
voice-cli transcribe <audio-file> [flags]
  --project, -p     Project name (required)
  --conversation    Conversation ID
  --provider        Provider (whisper, openai, elevenlabs)
  --language        Language hint
  --diarize         Enable speaker diarization
  --timestamps      Include word-level timestamps
  --output, -o      Output file path
  --format          Output format (json, txt, srt, vtt)

# Examples
voice-cli transcribe meeting.wav -p my-project --diarize --format srt
voice-cli transcribe audio.mp3 -p my-project --provider openai --timestamps

# Transcribe from URL
voice-cli transcribe --url https://example.com/audio.mp3 -p my-project

# Batch transcribe directory
voice-cli transcribe ./recordings/ -p my-project --recursive --output ./transcripts/
```

### 4.6 Conversation Commands

```bash
# List conversations in project
voice-cli conversation list -p <project-name> [flags]
  --status          Filter by status
  --since           Filter by date
  --limit           Max results

# Show conversation details
voice-cli conversation info <conversation-id> -p <project-name>

# Search within conversation
voice-cli conversation search <query> -p <project-name> [flags]
  --conversation    Specific conversation (or all)
  --context         Include context around matches
  --limit           Max results

# Export conversation
voice-cli conversation export <conversation-id> -p <project-name> [flags]
  --format          Output format (json, txt, srt, vtt, md)
  --output, -o      Output file path
  --include-audio   Include audio file reference
  --include-commands Include parsed commands

# Delete conversation
voice-cli conversation delete <conversation-id> -p <project-name> --confirm

# Merge conversations
voice-cli conversation merge <conv-id-1> <conv-id-2> -p <project-name> --output <new-id>
```

### 4.7 Server Commands

```bash
# Start the voice-cli server (HTTP + WebSocket)
voice-cli serve [flags]
  --host            Bind address (default: 127.0.0.1)
  --port            HTTP port (default: 8085)
  --ws-port         WebSocket port (default: 8086)
  --cors            Enable CORS
  --auth            Enable authentication
  --tls             Enable TLS
  --cert            TLS certificate path
  --key             TLS key path

# Start server with custom config
voice-cli serve --config /path/to/config.yaml

# Health check
voice-cli health --host localhost --port 8085
```

### 4.8 Provider Commands

```bash
# List available providers
voice-cli provider list

# Check provider status
voice-cli provider status <provider-name>

# Configure provider
voice-cli provider configure <provider-name> [flags]
  --api-key         API key (for cloud providers)
  --model           Model to use
  --endpoint        Custom endpoint URL

# Test provider
voice-cli provider test <provider-name> --audio test.wav
```

### 4.9 Utility Commands

```bash
# List audio devices
voice-cli devices list

# Test microphone
voice-cli devices test [device-name] --duration 5

# Check system requirements
voice-cli doctor

# Show version and build info
voice-cli version

# Generate shell completion
voice-cli completion [bash|zsh|fish|powershell]
```

---

## 5. HTTP API

### 5.1 Base Configuration

```
Base URL: http://localhost:8085/api/v1
Content-Type: application/json
```

### 5.2 Authentication

```http
Authorization: Bearer <token>
X-API-Key: <api-key>
```

### 5.3 Endpoints

#### Projects

```http
# List projects
GET /projects
Response: { "projects": [...], "total": 10 }

# Create project
POST /projects
Body: { "name": "my-project", "language": "en", "provider": "whisper" }
Response: { "project": {...} }

# Get project
GET /projects/{projectId}
Response: { "project": {...} }

# Update project
PATCH /projects/{projectId}
Body: { "name": "new-name", "language": "es" }

# Delete project
DELETE /projects/{projectId}
```

#### Conversations

```http
# List conversations
GET /projects/{projectId}/conversations
Query: ?status=active&limit=20&offset=0

# Create conversation
POST /projects/{projectId}/conversations
Body: { "name": "Meeting Notes", "language": "en" }

# Get conversation
GET /projects/{projectId}/conversations/{conversationId}

# Get transcript
GET /projects/{projectId}/conversations/{conversationId}/transcript
Query: ?format=json&include_words=true

# Search transcripts
GET /projects/{projectId}/conversations/{conversationId}/search
Query: ?q=search+term&context=2

# Export conversation
GET /projects/{projectId}/conversations/{conversationId}/export
Query: ?format=srt

# Delete conversation
DELETE /projects/{projectId}/conversations/{conversationId}
```

#### Transcription

```http
# Transcribe file (async)
POST /transcribe
Content-Type: multipart/form-data
Body: 
  - file: <audio-file>
  - project_id: <project-id>
  - conversation_id: <optional>
  - provider: whisper|openai|elevenlabs
  - language: en
  - diarize: true
  - timestamps: true

Response: { "job_id": "...", "status": "processing" }

# Check transcription status
GET /transcribe/{jobId}
Response: { "status": "completed", "transcript": {...} }

# Get real-time transcription token
POST /transcribe/token
Body: { "project_id": "...", "provider": "openai" }
Response: { "token": "...", "expires_at": "...", "websocket_url": "..." }
```

#### Commands

```http
# Get pending commands
GET /projects/{projectId}/commands
Query: ?status=pending

# Execute command
POST /projects/{projectId}/commands/{commandId}/execute

# Cancel command
POST /projects/{projectId}/commands/{commandId}/cancel
```

#### Health

```http
# Health check
GET /health
Response: { "status": "healthy", "providers": {...}, "version": "1.0.0" }

# Provider status
GET /providers
Response: { "providers": [{ "name": "whisper", "status": "available", ... }] }
```

---

## 6. WebSocket Streaming Protocol

### 6.1 Connection

```
URL: ws://localhost:8086/ws/transcribe
Headers:
  Authorization: Bearer <token>
  X-Project-ID: <project-id>
  X-Conversation-ID: <conversation-id>
```

### 6.2 Message Types

#### Client → Server Messages

```typescript
// Session configuration (send after connection)
interface SessionConfig {
  type: "session.configure";
  session_id?: string;
  config: {
    provider: "whisper" | "openai" | "elevenlabs";
    language?: string;
    sample_rate: number;       // 8000-48000
    encoding: "pcm16" | "opus" | "webm";
    channels: 1;
    vad_enabled: boolean;
    vad_threshold?: number;    // 0.0-1.0
    interim_results: boolean;  // Partial transcripts
    word_timestamps: boolean;
    speaker_diarization: boolean;
    command_recognition: boolean;
  };
}

// Audio data chunk
interface AudioChunk {
  type: "audio.chunk";
  sequence: number;           // Monotonic sequence number
  timestamp: number;          // Unix timestamp ms
  audio: string;              // Base64-encoded audio data
  is_final: boolean;          // Last chunk of utterance (manual commit)
}

// Manual commit (finalize current buffer)
interface AudioCommit {
  type: "audio.commit";
  timestamp: number;
}

// Pause/resume streaming
interface StreamControl {
  type: "stream.pause" | "stream.resume";
}

// End session
interface SessionEnd {
  type: "session.end";
  save_transcript: boolean;
}

// Client ping
interface Ping {
  type: "ping";
  timestamp: number;
}
```

#### Server → Client Messages

```typescript
// Session started confirmation
interface SessionStarted {
  type: "session.started";
  session_id: string;
  conversation_id: string;
  config: object;             // Echo of effective config
}

// Partial transcript (interim)
interface PartialTranscript {
  type: "transcript.partial";
  segment_id: string;
  sequence: number;
  text: string;
  confidence: number;
  start_time: number;
  language?: string;
}

// Final transcript (committed)
interface FinalTranscript {
  type: "transcript.final";
  segment_id: string;
  sequence: number;
  text: string;
  confidence: number;
  start_time: number;
  end_time: number;
  duration: number;
  language?: string;
  speaker?: string;
  words?: WordTimestamp[];
}

interface WordTimestamp {
  word: string;
  start: number;
  end: number;
  confidence: number;
}

// Voice command detected
interface CommandDetected {
  type: "command.detected";
  command_id: string;
  segment_id: string;
  command_type: string;
  command_text: string;
  parsed_command: string;
  parameters: object;
  confidence: number;
  awaiting_confirmation: boolean;
}

// Voice activity detection
interface VadEvent {
  type: "vad.speech_start" | "vad.speech_end";
  timestamp: number;
  confidence: number;
}

// Audio event (non-speech)
interface AudioEventDetected {
  type: "audio.event";
  event_type: string;         // "silence", "noise", "music"
  start_time: number;
  end_time?: number;
  confidence: number;
}

// Error
interface ErrorMessage {
  type: "error";
  code: number;
  message: string;
  details?: object;
  recoverable: boolean;
}

// Session ended
interface SessionEnded {
  type: "session.ended";
  session_id: string;
  summary: {
    duration: number;
    segment_count: number;
    word_count: number;
    command_count: number;
  };
}

// Server pong
interface Pong {
  type: "pong";
  timestamp: number;
  server_time: number;
}
```

### 6.3 Connection Flow

```
Client                                  Server
   |                                       |
   |-------- WebSocket Connect ----------->|
   |                                       |
   |<------- session.started --------------|
   |                                       |
   |-------- session.configure ----------->|
   |                                       |
   |<------- session.configured -----------|
   |                                       |
   |-------- audio.chunk ----------------->|
   |-------- audio.chunk ----------------->|
   |<------- transcript.partial -----------|
   |-------- audio.chunk ----------------->|
   |<------- transcript.partial -----------|
   |<------- vad.speech_end ---------------|
   |<------- transcript.final -------------|
   |<------- command.detected -------------|
   |                                       |
   |-------- session.end ----------------->|
   |<------- session.ended ----------------|
   |                                       |
   X-------- Connection Closed -----------X
```

### 6.4 Audio Format Requirements

| Provider | Sample Rate | Encoding | Channels | Chunk Size |
|----------|-------------|----------|----------|------------|
| Whisper | 16000 Hz | PCM16 | 1 (mono) | 100-500ms |
| OpenAI | 24000 Hz | PCM16 | 1 (mono) | 100-250ms |
| ElevenLabs | 16000 Hz | PCM16 | 1 (mono) | 100-1000ms |

### 6.5 Audio Encoding Utilities

```go
// internal/audio/encoder.go

// EncodePCM16ToBase64 converts float32 audio samples to base64-encoded PCM16
func EncodePCM16ToBase64(samples []float32) string {
    int16Data := make([]int16, len(samples))
    for i, s := range samples {
        // Clamp to [-1, 1]
        if s > 1.0 {
            s = 1.0
        } else if s < -1.0 {
            s = -1.0
        }
        // Convert to int16
        if s < 0 {
            int16Data[i] = int16(s * 0x8000)
        } else {
            int16Data[i] = int16(s * 0x7FFF)
        }
    }
    
    // Convert to bytes (little-endian)
    buf := new(bytes.Buffer)
    binary.Write(buf, binary.LittleEndian, int16Data)
    
    return base64.StdEncoding.EncodeToString(buf.Bytes())
}

// DecodePCM16FromBase64 decodes base64 PCM16 to float32 samples
func DecodePCM16FromBase64(encoded string) ([]float32, error) {
    data, err := base64.StdEncoding.DecodeString(encoded)
    if err != nil {
        return nil, err
    }
    
    int16Data := make([]int16, len(data)/2)
    buf := bytes.NewReader(data)
    binary.Read(buf, binary.LittleEndian, &int16Data)
    
    samples := make([]float32, len(int16Data))
    for i, s := range int16Data {
        samples[i] = float32(s) / 32768.0
    }
    
    return samples, nil
}
```

---

## 7. LLM Transcription Integration

### 7.1 Provider Abstraction

```go
// internal/transcribe/providers.go

type TranscriptionProvider interface {
    Name() string
    IsAvailable() bool
    SupportedFeatures() ProviderFeatures
    
    // Batch transcription
    Transcribe(ctx context.Context, audio io.Reader, opts TranscribeOptions) (*TranscriptResult, error)
    
    // Streaming transcription
    StreamTranscribe(ctx context.Context, audioStream <-chan []byte, opts TranscribeOptions) (<-chan TranscriptEvent, error)
    
    // Close and cleanup
    Close() error
}

type ProviderFeatures struct {
    SupportsStreaming     bool
    SupportsDiarization   bool
    SupportsWordTimestamps bool
    SupportsVAD           bool
    SupportedLanguages    []string
    SupportedFormats      []string
    MaxAudioDuration      time.Duration
}

type TranscribeOptions struct {
    Language       string
    SampleRate     int
    Encoding       string
    Diarize        bool
    WordTimestamps bool
    VADEnabled     bool
    VADThreshold   float64
    Model          string
}

type TranscriptResult struct {
    Text       string
    Segments   []TranscriptSegment
    Language   string
    Duration   float64
    Confidence float64
}

type TranscriptEvent struct {
    Type       string // "partial", "final", "vad", "error"
    Segment    *TranscriptSegment
    VADEvent   *VADEvent
    Error      error
}
```

### 7.2 Local Whisper Provider

```go
// internal/transcribe/whisper.go

type WhisperProvider struct {
    modelPath    string
    model        *whisper.Model
    processor    *whisper.Processor
    sampleRate   int
    mu           sync.Mutex
}

type WhisperConfig struct {
    ModelPath     string // Path to whisper.cpp model file
    ModelSize     string // "tiny", "base", "small", "medium", "large-v3"
    Language      string
    Threads       int
    UseGPU        bool
    GPUDevice     int
}

func NewWhisperProvider(config WhisperConfig) (*WhisperProvider, error) {
    // Load whisper.cpp model
    model, err := whisper.LoadModel(config.ModelPath)
    if err != nil {
        return nil, fmt.Errorf("failed to load whisper model: %w", err)
    }
    
    processor, err := model.NewProcessor(whisper.ProcessorOptions{
        Language:   config.Language,
        Threads:    config.Threads,
        UseGPU:     config.UseGPU,
        GPUDevice:  config.GPUDevice,
    })
    if err != nil {
        return nil, fmt.Errorf("failed to create processor: %w", err)
    }
    
    return &WhisperProvider{
        modelPath:  config.ModelPath,
        model:      model,
        processor:  processor,
        sampleRate: 16000,
    }, nil
}

func (w *WhisperProvider) Name() string {
    return "whisper"
}

func (w *WhisperProvider) IsAvailable() bool {
    return w.model != nil && w.processor != nil
}

func (w *WhisperProvider) SupportedFeatures() ProviderFeatures {
    return ProviderFeatures{
        SupportsStreaming:      true, // Via chunked processing
        SupportsDiarization:    false,
        SupportsWordTimestamps: true,
        SupportsVAD:            true,
        SupportedLanguages:     whisper.SupportedLanguages(),
        SupportedFormats:       []string{"wav", "mp3", "m4a", "webm", "ogg"},
        MaxAudioDuration:       0, // No limit for local
    }
}

func (w *WhisperProvider) Transcribe(ctx context.Context, audio io.Reader, opts TranscribeOptions) (*TranscriptResult, error) {
    w.mu.Lock()
    defer w.mu.Unlock()
    
    // Read and decode audio
    samples, err := w.decodeAudio(audio, opts.Encoding, opts.SampleRate)
    if err != nil {
        return nil, fmt.Errorf("failed to decode audio: %w", err)
    }
    
    // Process with whisper
    result, err := w.processor.Process(ctx, samples, whisper.ProcessOptions{
        Language:       opts.Language,
        WordTimestamps: opts.WordTimestamps,
    })
    if err != nil {
        return nil, fmt.Errorf("transcription failed: %w", err)
    }
    
    return w.convertResult(result), nil
}

func (w *WhisperProvider) StreamTranscribe(ctx context.Context, audioStream <-chan []byte, opts TranscribeOptions) (<-chan TranscriptEvent, error) {
    events := make(chan TranscriptEvent, 100)
    
    go func() {
        defer close(events)
        
        buffer := &AudioBuffer{
            sampleRate: opts.SampleRate,
            chunkDuration: 3 * time.Second, // Process in 3s chunks
        }
        
        for {
            select {
            case <-ctx.Done():
                return
            case chunk, ok := <-audioStream:
                if !ok {
                    // Process remaining buffer
                    if buffer.Len() > 0 {
                        w.processChunk(ctx, buffer.Flush(), opts, events, true)
                    }
                    return
                }
                
                buffer.Append(chunk)
                
                // Process when buffer is full
                if buffer.Duration() >= buffer.chunkDuration {
                    w.processChunk(ctx, buffer.Flush(), opts, events, false)
                }
            }
        }
    }()
    
    return events, nil
}
```

### 7.3 OpenAI Realtime Provider

```go
// internal/transcribe/openai.go

type OpenAIRealtimeProvider struct {
    apiKey    string
    wsConn    *websocket.Conn
    mu        sync.Mutex
    config    OpenAIConfig
}

type OpenAIConfig struct {
    APIKey          string
    Model           string // "gpt-4o-realtime-preview-2024-10-01"
    Voice           string
    Temperature     float64
    VADThreshold    float64
    SilenceDuration int // ms
}

func NewOpenAIRealtimeProvider(config OpenAIConfig) (*OpenAIRealtimeProvider, error) {
    if config.APIKey == "" {
        return nil, errors.New("OpenAI API key required")
    }
    
    if config.Model == "" {
        config.Model = "gpt-4o-realtime-preview-2024-10-01"
    }
    
    return &OpenAIRealtimeProvider{
        apiKey: config.APIKey,
        config: config,
    }, nil
}

func (o *OpenAIRealtimeProvider) Name() string {
    return "openai"
}

func (o *OpenAIRealtimeProvider) connect(ctx context.Context) error {
    o.mu.Lock()
    defer o.mu.Unlock()
    
    if o.wsConn != nil {
        return nil
    }
    
    url := fmt.Sprintf("wss://api.openai.com/v1/realtime?model=%s", o.config.Model)
    
    headers := http.Header{}
    headers.Set("Authorization", "Bearer "+o.apiKey)
    headers.Set("OpenAI-Beta", "realtime=v1")
    
    conn, _, err := websocket.DefaultDialer.DialContext(ctx, url, headers)
    if err != nil {
        return fmt.Errorf("failed to connect to OpenAI: %w", err)
    }
    
    o.wsConn = conn
    return nil
}

func (o *OpenAIRealtimeProvider) StreamTranscribe(ctx context.Context, audioStream <-chan []byte, opts TranscribeOptions) (<-chan TranscriptEvent, error) {
    if err := o.connect(ctx); err != nil {
        return nil, err
    }
    
    events := make(chan TranscriptEvent, 100)
    
    // Wait for session.created, then send session.update
    go o.handleMessages(ctx, events)
    
    // Send audio chunks
    go func() {
        for chunk := range audioStream {
            msg := map[string]interface{}{
                "type":  "input_audio_buffer.append",
                "audio": base64.StdEncoding.EncodeToString(chunk),
            }
            o.wsConn.WriteJSON(msg)
        }
    }()
    
    return events, nil
}

func (o *OpenAIRealtimeProvider) configureSession() error {
    config := map[string]interface{}{
        "type": "session.update",
        "session": map[string]interface{}{
            "modalities":          []string{"text", "audio"},
            "input_audio_format":  "pcm16",
            "output_audio_format": "pcm16",
            "input_audio_transcription": map[string]interface{}{
                "model": "whisper-1",
            },
            "turn_detection": map[string]interface{}{
                "type":               "server_vad",
                "threshold":          o.config.VADThreshold,
                "prefix_padding_ms":  300,
                "silence_duration_ms": o.config.SilenceDuration,
            },
            "temperature": o.config.Temperature,
        },
    }
    
    return o.wsConn.WriteJSON(config)
}

func (o *OpenAIRealtimeProvider) handleMessages(ctx context.Context, events chan<- TranscriptEvent) {
    defer close(events)
    
    sessionConfigured := false
    
    for {
        select {
        case <-ctx.Done():
            return
        default:
            _, message, err := o.wsConn.ReadMessage()
            if err != nil {
                events <- TranscriptEvent{Type: "error", Error: err}
                return
            }
            
            var msg map[string]interface{}
            if err := json.Unmarshal(message, &msg); err != nil {
                continue
            }
            
            msgType := msg["type"].(string)
            
            switch msgType {
            case "session.created":
                if !sessionConfigured {
                    o.configureSession()
                    sessionConfigured = true
                }
                
            case "conversation.item.input_audio_transcription.completed":
                transcript := msg["transcript"].(string)
                events <- TranscriptEvent{
                    Type: "final",
                    Segment: &TranscriptSegment{
                        Text:     transcript,
                        IsFinal:  true,
                        Provider: "openai",
                    },
                }
                
            case "input_audio_buffer.speech_started":
                events <- TranscriptEvent{
                    Type: "vad",
                    VADEvent: &VADEvent{
                        Type:      "speech_start",
                        Timestamp: time.Now(),
                    },
                }
                
            case "input_audio_buffer.speech_stopped":
                events <- TranscriptEvent{
                    Type: "vad",
                    VADEvent: &VADEvent{
                        Type:      "speech_end",
                        Timestamp: time.Now(),
                    },
                }
                
            case "error":
                errData := msg["error"].(map[string]interface{})
                events <- TranscriptEvent{
                    Type:  "error",
                    Error: fmt.Errorf("%s: %s", errData["type"], errData["message"]),
                }
            }
        }
    }
}
```

### 7.4 ElevenLabs Scribe Provider

```go
// internal/transcribe/elevenlabs.go

type ElevenLabsProvider struct {
    apiKey    string
    wsConn    *websocket.Conn
    config    ElevenLabsConfig
}

type ElevenLabsConfig struct {
    APIKey         string
    Model          string // "scribe_v2_realtime"
    CommitStrategy string // "vad" or "manual"
}

func NewElevenLabsProvider(config ElevenLabsConfig) (*ElevenLabsProvider, error) {
    if config.APIKey == "" {
        return nil, errors.New("ElevenLabs API key required")
    }
    
    if config.Model == "" {
        config.Model = "scribe_v2_realtime"
    }
    if config.CommitStrategy == "" {
        config.CommitStrategy = "vad"
    }
    
    return &ElevenLabsProvider{
        apiKey: config.APIKey,
        config: config,
    }, nil
}

func (e *ElevenLabsProvider) getToken(ctx context.Context) (string, error) {
    req, err := http.NewRequestWithContext(ctx, "POST",
        "https://api.elevenlabs.io/v1/single-use-token/realtime_scribe", nil)
    if err != nil {
        return "", err
    }
    
    req.Header.Set("xi-api-key", e.apiKey)
    
    resp, err := http.DefaultClient.Do(req)
    if err != nil {
        return "", err
    }
    defer resp.Body.Close()
    
    var result struct {
        Token string `json:"token"`
    }
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return "", err
    }
    
    return result.Token, nil
}

func (e *ElevenLabsProvider) StreamTranscribe(ctx context.Context, audioStream <-chan []byte, opts TranscribeOptions) (<-chan TranscriptEvent, error) {
    token, err := e.getToken(ctx)
    if err != nil {
        return nil, fmt.Errorf("failed to get token: %w", err)
    }
    
    url := fmt.Sprintf("wss://api.elevenlabs.io/v1/realtime-scribe?token=%s", token)
    
    conn, _, err := websocket.DefaultDialer.DialContext(ctx, url, nil)
    if err != nil {
        return nil, fmt.Errorf("failed to connect: %w", err)
    }
    
    e.wsConn = conn
    events := make(chan TranscriptEvent, 100)
    
    // Send config
    config := map[string]interface{}{
        "type":            "config",
        "model_id":        e.config.Model,
        "commit_strategy": e.config.CommitStrategy,
        "sample_rate":     opts.SampleRate,
    }
    conn.WriteJSON(config)
    
    // Handle incoming messages
    go e.handleMessages(ctx, events)
    
    // Send audio
    go func() {
        for chunk := range audioStream {
            msg := map[string]interface{}{
                "type":  "audio",
                "audio": base64.StdEncoding.EncodeToString(chunk),
            }
            e.wsConn.WriteJSON(msg)
        }
    }()
    
    return events, nil
}

func (e *ElevenLabsProvider) handleMessages(ctx context.Context, events chan<- TranscriptEvent) {
    defer close(events)
    
    for {
        select {
        case <-ctx.Done():
            return
        default:
            _, message, err := e.wsConn.ReadMessage()
            if err != nil {
                events <- TranscriptEvent{Type: "error", Error: err}
                return
            }
            
            var msg map[string]interface{}
            if err := json.Unmarshal(message, &msg); err != nil {
                continue
            }
            
            switch msg["type"].(string) {
            case "partial_transcript":
                events <- TranscriptEvent{
                    Type: "partial",
                    Segment: &TranscriptSegment{
                        Text:      msg["text"].(string),
                        IsPartial: true,
                        Provider:  "elevenlabs",
                    },
                }
                
            case "committed_transcript":
                events <- TranscriptEvent{
                    Type: "final",
                    Segment: &TranscriptSegment{
                        Text:     msg["text"].(string),
                        IsFinal:  true,
                        Provider: "elevenlabs",
                    },
                }
                
            case "committed_transcript_with_timestamps":
                words := e.parseWords(msg["words"])
                events <- TranscriptEvent{
                    Type: "final",
                    Segment: &TranscriptSegment{
                        Text:     msg["text"].(string),
                        IsFinal:  true,
                        Provider: "elevenlabs",
                        Words:    words,
                    },
                }
            }
        }
    }
}
```

### 7.5 Provider Registry

```go
// internal/transcribe/engine.go

type TranscriptionEngine struct {
    providers map[string]TranscriptionProvider
    default_  string
    mu        sync.RWMutex
}

func NewTranscriptionEngine(defaultProvider string) *TranscriptionEngine {
    return &TranscriptionEngine{
        providers: make(map[string]TranscriptionProvider),
        default_:  defaultProvider,
    }
}

func (e *TranscriptionEngine) Register(provider TranscriptionProvider) {
    e.mu.Lock()
    defer e.mu.Unlock()
    e.providers[provider.Name()] = provider
}

func (e *TranscriptionEngine) Get(name string) (TranscriptionProvider, error) {
    e.mu.RLock()
    defer e.mu.RUnlock()
    
    if name == "" {
        name = e.default_
    }
    
    provider, ok := e.providers[name]
    if !ok {
        return nil, fmt.Errorf("provider not found: %s", name)
    }
    
    if !provider.IsAvailable() {
        return nil, fmt.Errorf("provider not available: %s", name)
    }
    
    return provider, nil
}

func (e *TranscriptionEngine) ListProviders() []ProviderInfo {
    e.mu.RLock()
    defer e.mu.RUnlock()
    
    infos := make([]ProviderInfo, 0, len(e.providers))
    for name, p := range e.providers {
        infos = append(infos, ProviderInfo{
            Name:      name,
            Available: p.IsAvailable(),
            Features:  p.SupportedFeatures(),
        })
    }
    return infos
}
```

---

## 8. Audio Processing Pipeline

### 8.1 Audio Capture

```go
// internal/audio/capture.go

type AudioCapture struct {
    stream      *portaudio.Stream
    sampleRate  int
    channels    int
    framesPerBuffer int
    buffer      chan []float32
    done        chan struct{}
}

type CaptureConfig struct {
    DeviceName      string
    SampleRate      int
    Channels        int
    FramesPerBuffer int
    EchoCancellation bool
    NoiseSuppression bool
    AutoGainControl  bool
}

func NewAudioCapture(config CaptureConfig) (*AudioCapture, error) {
    if err := portaudio.Initialize(); err != nil {
        return nil, fmt.Errorf("failed to initialize PortAudio: %w", err)
    }
    
    device, err := findDevice(config.DeviceName)
    if err != nil {
        return nil, err
    }
    
    ac := &AudioCapture{
        sampleRate:      config.SampleRate,
        channels:        config.Channels,
        framesPerBuffer: config.FramesPerBuffer,
        buffer:          make(chan []float32, 100),
        done:            make(chan struct{}),
    }
    
    inputParams := portaudio.StreamParameters{
        Input: portaudio.StreamDeviceParameters{
            Device:   device,
            Channels: config.Channels,
            Latency:  device.DefaultLowInputLatency,
        },
        SampleRate:      float64(config.SampleRate),
        FramesPerBuffer: config.FramesPerBuffer,
    }
    
    stream, err := portaudio.OpenStream(inputParams, ac.processAudio)
    if err != nil {
        return nil, fmt.Errorf("failed to open stream: %w", err)
    }
    
    ac.stream = stream
    return ac, nil
}

func (ac *AudioCapture) Start() error {
    return ac.stream.Start()
}

func (ac *AudioCapture) Stop() error {
    close(ac.done)
    return ac.stream.Stop()
}

func (ac *AudioCapture) AudioStream() <-chan []float32 {
    return ac.buffer
}

func (ac *AudioCapture) processAudio(in []float32) {
    select {
    case <-ac.done:
        return
    case ac.buffer <- append([]float32{}, in...):
    default:
        // Buffer full, drop frame
    }
}
```

### 8.2 Voice Activity Detection

```go
// internal/audio/vad.go

type VAD struct {
    threshold     float64
    speechStart   bool
    silenceFrames int
    minSpeechFrames int
    maxSilenceFrames int
}

type VADConfig struct {
    Threshold       float64 // 0.0-1.0, default 0.5
    MinSpeechMs     int     // Minimum speech duration to trigger
    MaxSilenceMs    int     // Silence duration to end speech
    SampleRate      int
    FrameSize       int
}

func NewVAD(config VADConfig) *VAD {
    msToFrames := func(ms int) int {
        return (ms * config.SampleRate) / (1000 * config.FrameSize)
    }
    
    return &VAD{
        threshold:        config.Threshold,
        minSpeechFrames:  msToFrames(config.MinSpeechMs),
        maxSilenceFrames: msToFrames(config.MaxSilenceMs),
    }
}

type VADResult struct {
    IsSpeech    bool
    Energy      float64
    SpeechStart bool
    SpeechEnd   bool
}

func (v *VAD) Process(samples []float32) VADResult {
    energy := v.calculateEnergy(samples)
    isSpeech := energy > v.threshold
    
    result := VADResult{
        IsSpeech: isSpeech,
        Energy:   energy,
    }
    
    if isSpeech {
        if !v.speechStart {
            v.speechStart = true
            result.SpeechStart = true
        }
        v.silenceFrames = 0
    } else if v.speechStart {
        v.silenceFrames++
        if v.silenceFrames >= v.maxSilenceFrames {
            v.speechStart = false
            result.SpeechEnd = true
            v.silenceFrames = 0
        }
    }
    
    return result
}

func (v *VAD) calculateEnergy(samples []float32) float64 {
    var sum float64
    for _, s := range samples {
        sum += float64(s * s)
    }
    return math.Sqrt(sum / float64(len(samples)))
}
```

### 8.3 Audio Buffer Management

```go
// internal/audio/buffer.go

type AudioBuffer struct {
    samples    []float32
    sampleRate int
    mu         sync.Mutex
}

func NewAudioBuffer(sampleRate int) *AudioBuffer {
    return &AudioBuffer{
        samples:    make([]float32, 0, sampleRate*30), // 30s capacity
        sampleRate: sampleRate,
    }
}

func (b *AudioBuffer) Append(samples []float32) {
    b.mu.Lock()
    defer b.mu.Unlock()
    b.samples = append(b.samples, samples...)
}

func (b *AudioBuffer) Duration() time.Duration {
    b.mu.Lock()
    defer b.mu.Unlock()
    return time.Duration(len(b.samples)) * time.Second / time.Duration(b.sampleRate)
}

func (b *AudioBuffer) Len() int {
    b.mu.Lock()
    defer b.mu.Unlock()
    return len(b.samples)
}

func (b *AudioBuffer) Flush() []float32 {
    b.mu.Lock()
    defer b.mu.Unlock()
    samples := b.samples
    b.samples = make([]float32, 0, b.sampleRate*30)
    return samples
}

func (b *AudioBuffer) GetLast(duration time.Duration) []float32 {
    b.mu.Lock()
    defer b.mu.Unlock()
    
    sampleCount := int(duration.Seconds() * float64(b.sampleRate))
    if sampleCount > len(b.samples) {
        sampleCount = len(b.samples)
    }
    
    return b.samples[len(b.samples)-sampleCount:]
}
```

---

## 9. Voice Command Recognition

### 9.1 Command Grammar

```go
// internal/commands/grammar.go

type CommandGrammar struct {
    patterns map[CommandType][]*regexp.Regexp
    entities map[string]*regexp.Regexp
}

type CommandType string

const (
    CmdCreateStage    CommandType = "CREATE_STAGE"
    CmdConnectStages  CommandType = "CONNECT_STAGES"
    CmdSetVariable    CommandType = "SET_VARIABLE"
    CmdDeleteStage    CommandType = "DELETE_STAGE"
    CmdRenameStage    CommandType = "RENAME_STAGE"
    CmdRunFlow        CommandType = "RUN_FLOW"
    CmdStopFlow       CommandType = "STOP_FLOW"
    CmdNavigate       CommandType = "NAVIGATE"
    CmdUndo           CommandType = "UNDO"
    CmdRedo           CommandType = "REDO"
    CmdSave           CommandType = "SAVE"
    CmdHelp           CommandType = "HELP"
)

var DefaultGrammar = &CommandGrammar{
    patterns: map[CommandType][]*regexp.Regexp{
        CmdCreateStage: {
            regexp.MustCompile(`(?i)create\s+(?:a\s+)?(?:new\s+)?stage\s+(?:called\s+)?["']?(\w+)["']?`),
            regexp.MustCompile(`(?i)add\s+(?:a\s+)?(\w+)\s+stage\s+(?:named\s+)?["']?(\w+)["']?`),
            regexp.MustCompile(`(?i)new\s+(\w+)\s+stage\s+["']?(\w+)["']?`),
        },
        CmdConnectStages: {
            regexp.MustCompile(`(?i)connect\s+["']?(\w+)["']?\s+to\s+["']?(\w+)["']?`),
            regexp.MustCompile(`(?i)link\s+["']?(\w+)["']?\s+(?:with|to)\s+["']?(\w+)["']?`),
        },
        CmdSetVariable: {
            regexp.MustCompile(`(?i)set\s+(?:variable\s+)?["']?(\w+)["']?\s+to\s+["']?(.+?)["']?$`),
            regexp.MustCompile(`(?i)(?:define|create)\s+variable\s+["']?(\w+)["']?\s+(?:as|equals?)\s+["']?(.+?)["']?$`),
        },
        CmdDeleteStage: {
            regexp.MustCompile(`(?i)delete\s+(?:stage\s+)?["']?(\w+)["']?`),
            regexp.MustCompile(`(?i)remove\s+(?:stage\s+)?["']?(\w+)["']?`),
        },
        CmdRenameStage: {
            regexp.MustCompile(`(?i)rename\s+(?:stage\s+)?["']?(\w+)["']?\s+to\s+["']?(\w+)["']?`),
        },
        CmdRunFlow: {
            regexp.MustCompile(`(?i)(?:run|start|execute)\s+(?:the\s+)?flow`),
            regexp.MustCompile(`(?i)play\s+(?:the\s+)?flow`),
        },
        CmdStopFlow: {
            regexp.MustCompile(`(?i)(?:stop|pause|halt)\s+(?:the\s+)?flow`),
        },
        CmdNavigate: {
            regexp.MustCompile(`(?i)(?:go\s+to|navigate\s+to|open)\s+["']?(.+?)["']?$`),
        },
        CmdUndo: {
            regexp.MustCompile(`(?i)undo(?:\s+(?:that|last))?`),
        },
        CmdRedo: {
            regexp.MustCompile(`(?i)redo(?:\s+(?:that|last))?`),
        },
        CmdSave: {
            regexp.MustCompile(`(?i)save(?:\s+(?:the\s+)?(?:flow|project))?`),
        },
        CmdHelp: {
            regexp.MustCompile(`(?i)(?:help|what\s+can\s+(?:you|I)\s+(?:do|say))`),
        },
    },
    entities: map[string]*regexp.Regexp{
        "stage_type": regexp.MustCompile(`(?i)(prompt|search|transform|http|file|condition|loop|code)`),
        "variable":   regexp.MustCompile(`\$(\w+)`),
        "number":     regexp.MustCompile(`\b(\d+(?:\.\d+)?)\b`),
        "quoted":     regexp.MustCompile(`["']([^"']+)["']`),
    },
}
```

### 9.2 Command Parser

```go
// internal/commands/parser.go

type CommandParser struct {
    grammar     *CommandGrammar
    llmClient   *LLMClient // For complex command understanding
    useLLM      bool
}

type ParsedCommand struct {
    Type        CommandType
    RawText     string
    Normalized  string
    Parameters  map[string]interface{}
    Entities    []Entity
    Confidence  float64
    RequiresLLM bool
}

type Entity struct {
    Type   string
    Value  string
    Start  int
    End    int
}

func NewCommandParser(grammar *CommandGrammar, llmClient *LLMClient) *CommandParser {
    return &CommandParser{
        grammar:   grammar,
        llmClient: llmClient,
        useLLM:    llmClient != nil,
    }
}

func (p *CommandParser) Parse(ctx context.Context, text string) (*ParsedCommand, error) {
    normalized := strings.TrimSpace(strings.ToLower(text))
    
    // Try pattern matching first
    for cmdType, patterns := range p.grammar.patterns {
        for _, pattern := range patterns {
            if matches := pattern.FindStringSubmatch(text); matches != nil {
                return p.buildCommand(cmdType, text, normalized, matches)
            }
        }
    }
    
    // Fall back to LLM for complex commands
    if p.useLLM {
        return p.parseWithLLM(ctx, text)
    }
    
    return nil, nil // No command recognized
}

func (p *CommandParser) buildCommand(cmdType CommandType, raw, normalized string, matches []string) (*ParsedCommand, error) {
    cmd := &ParsedCommand{
        Type:       cmdType,
        RawText:    raw,
        Normalized: normalized,
        Parameters: make(map[string]interface{}),
        Confidence: 0.9,
    }
    
    // Extract parameters based on command type
    switch cmdType {
    case CmdCreateStage:
        if len(matches) > 1 {
            cmd.Parameters["name"] = matches[1]
        }
        if len(matches) > 2 {
            cmd.Parameters["type"] = matches[1]
            cmd.Parameters["name"] = matches[2]
        }
        
    case CmdConnectStages:
        if len(matches) > 2 {
            cmd.Parameters["source"] = matches[1]
            cmd.Parameters["target"] = matches[2]
        }
        
    case CmdSetVariable:
        if len(matches) > 2 {
            cmd.Parameters["name"] = matches[1]
            cmd.Parameters["value"] = matches[2]
        }
        
    case CmdDeleteStage, CmdRenameStage:
        if len(matches) > 1 {
            cmd.Parameters["name"] = matches[1]
        }
        if len(matches) > 2 {
            cmd.Parameters["new_name"] = matches[2]
        }
        
    case CmdNavigate:
        if len(matches) > 1 {
            cmd.Parameters["target"] = matches[1]
        }
    }
    
    // Extract entities
    cmd.Entities = p.extractEntities(raw)
    
    return cmd, nil
}

func (p *CommandParser) parseWithLLM(ctx context.Context, text string) (*ParsedCommand, error) {
    prompt := fmt.Sprintf(`Parse the following voice command and extract the intent and parameters.
    
Command: "%s"

Respond with JSON:
{
  "type": "COMMAND_TYPE",
  "parameters": {...},
  "confidence": 0.0-1.0
}

Valid command types: CREATE_STAGE, CONNECT_STAGES, SET_VARIABLE, DELETE_STAGE, RENAME_STAGE, RUN_FLOW, STOP_FLOW, NAVIGATE, UNDO, REDO, SAVE, HELP

If the command is unclear or not a valid command, return {"type": null, "confidence": 0}`, text)

    response, err := p.llmClient.Complete(ctx, prompt)
    if err != nil {
        return nil, err
    }
    
    var result struct {
        Type       *string                `json:"type"`
        Parameters map[string]interface{} `json:"parameters"`
        Confidence float64                `json:"confidence"`
    }
    
    if err := json.Unmarshal([]byte(response), &result); err != nil {
        return nil, err
    }
    
    if result.Type == nil {
        return nil, nil
    }
    
    return &ParsedCommand{
        Type:        CommandType(*result.Type),
        RawText:     text,
        Normalized:  strings.ToLower(text),
        Parameters:  result.Parameters,
        Confidence:  result.Confidence,
        RequiresLLM: true,
    }, nil
}
```

---

## 10. Integration APIs

### 10.1 Nexus-Flow Integration

```go
// internal/integrations/nexusflow/client.go

type NexusFlowClient struct {
    baseURL    string
    httpClient *http.Client
    wsConn     *websocket.Conn
}

func NewNexusFlowClient(baseURL string) *NexusFlowClient {
    return &NexusFlowClient{
        baseURL:    baseURL,
        httpClient: &http.Client{Timeout: 30 * time.Second},
    }
}

// Execute voice commands against Nexus-Flow
func (c *NexusFlowClient) ExecuteCommand(ctx context.Context, flowID string, cmd *ParsedCommand) (*CommandResult, error) {
    payload := map[string]interface{}{
        "command_type": cmd.Type,
        "parameters":   cmd.Parameters,
        "source":       "voice-cli",
    }
    
    resp, err := c.post(ctx, fmt.Sprintf("/flows/%s/commands", flowID), payload)
    if err != nil {
        return nil, err
    }
    
    var result CommandResult
    if err := json.Unmarshal(resp, &result); err != nil {
        return nil, err
    }
    
    return &result, nil
}

// Stream transcripts to Nexus-Flow for real-time flow building
func (c *NexusFlowClient) StreamTranscripts(ctx context.Context, flowID string, transcripts <-chan *TranscriptSegment) error {
    wsURL := strings.Replace(c.baseURL, "http", "ws", 1) + fmt.Sprintf("/flows/%s/voice", flowID)
    
    conn, _, err := websocket.DefaultDialer.DialContext(ctx, wsURL, nil)
    if err != nil {
        return err
    }
    defer conn.Close()
    
    for transcript := range transcripts {
        msg := map[string]interface{}{
            "type":       "transcript",
            "segment_id": transcript.ID,
            "text":       transcript.Text,
            "is_final":   transcript.IsFinal,
            "timestamp":  transcript.StartTime,
        }
        
        if err := conn.WriteJSON(msg); err != nil {
            return err
        }
    }
    
    return nil
}
```

### 10.2 Spec Management Integration

```go
// internal/integrations/specmgmt/client.go

type SpecManagementClient struct {
    baseURL    string
    httpClient *http.Client
}

func NewSpecManagementClient(baseURL string) *SpecManagementClient {
    return &SpecManagementClient{
        baseURL:    baseURL,
        httpClient: &http.Client{Timeout: 30 * time.Second},
    }
}

// Link voice conversation to a spec project
func (c *SpecManagementClient) LinkConversation(ctx context.Context, projectID, conversationID string) error {
    payload := map[string]string{
        "conversation_id": conversationID,
        "source":          "voice-cli",
    }
    
    _, err := c.post(ctx, fmt.Sprintf("/projects/%s/voice-links", projectID), payload)
    return err
}

// Get project context for voice commands
func (c *SpecManagementClient) GetProjectContext(ctx context.Context, projectID string) (*ProjectContext, error) {
    resp, err := c.get(ctx, fmt.Sprintf("/projects/%s/context", projectID))
    if err != nil {
        return nil, err
    }
    
    var ctx_ ProjectContext
    if err := json.Unmarshal(resp, &ctx_); err != nil {
        return nil, err
    }
    
    return &ctx_, nil
}

// Search project content using voice query
func (c *SpecManagementClient) VoiceSearch(ctx context.Context, projectID, query string) ([]SearchResult, error) {
    resp, err := c.get(ctx, fmt.Sprintf("/projects/%s/search?q=%s&source=voice", projectID, url.QueryEscape(query)))
    if err != nil {
        return nil, err
    }
    
    var results []SearchResult
    if err := json.Unmarshal(resp, &results); err != nil {
        return nil, err
    }
    
    return results, nil
}
```

---

## 11. Error Codes

Voice CLI uses error code range **11xxx**.

| Code | Name | Description |
|------|------|-------------|
| 11001 | ErrAudioDeviceNotFound | Specified audio device not found |
| 11002 | ErrAudioCaptureInit | Failed to initialize audio capture |
| 11003 | ErrAudioStreamFailed | Audio streaming error |
| 11004 | ErrTranscriptionInit | Failed to initialize transcription |
| 11005 | ErrTranscriptionFailed | Transcription processing error |
| 11006 | ErrProviderNotAvailable | Transcription provider unavailable |
| 11007 | ErrProviderAuthFailed | Provider authentication failed |
| 11008 | ErrProviderQuotaExceeded | Provider usage quota exceeded |
| 11009 | ErrProviderRateLimited | Provider rate limit hit |
| 11010 | ErrWebSocketConnect | WebSocket connection failed |
| 11011 | ErrWebSocketClosed | WebSocket unexpectedly closed |
| 11012 | ErrCommandParseFailed | Failed to parse voice command |
| 11013 | ErrCommandExecutionFailed | Command execution failed |
| 11014 | ErrDatabaseInit | Database initialization failed |
| 11015 | ErrDatabaseQuery | Database query failed |
| 11016 | ErrProjectNotFound | Project not found |
| 11017 | ErrConversationNotFound | Conversation not found |
| 11018 | ErrExportFailed | Export operation failed |
| 11019 | ErrImportFailed | Import operation failed |
| 11020 | ErrConfigInvalid | Invalid configuration |

---

## 12. Configuration

### 12.1 Configuration File

```yaml
# ~/.voice-cli/config.yaml

# Global settings
data_dir: ~/.voice-cli/data
log_level: info
log_format: json

# Audio settings
audio:
  sample_rate: 16000
  channels: 1
  device: default
  buffer_size: 4096
  vad:
    enabled: true
    threshold: 0.5
    min_speech_ms: 250
    max_silence_ms: 1000

# Default transcription provider
transcription:
  default_provider: whisper
  language: en
  
  # Local Whisper settings
  whisper:
    model_path: ~/.voice-cli/models/ggml-large-v3.bin
    model_size: large-v3
    threads: 4
    use_gpu: true
    gpu_device: 0
  
  # OpenAI Realtime settings
  openai:
    api_key: ${OPENAI_API_KEY}
    model: gpt-4o-realtime-preview-2024-10-01
    vad_threshold: 0.5
    silence_duration_ms: 1000
  
  # ElevenLabs Scribe settings
  elevenlabs:
    api_key: ${ELEVENLABS_API_KEY}
    model: scribe_v2_realtime
    commit_strategy: vad

# Server settings
server:
  host: 127.0.0.1
  http_port: 8085
  ws_port: 8086
  cors_enabled: true
  auth_enabled: false

# Command recognition
commands:
  enabled: true
  use_llm_fallback: true
  confirmation_required:
    - DELETE_STAGE
    - RUN_FLOW
  
# Integrations
integrations:
  nexus_flow:
    enabled: true
    base_url: http://localhost:8084
  spec_mgmt:
    enabled: true
    base_url: http://localhost:8080
```

### 12.2 Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `VOICE_CLI_CONFIG` | Config file path | `~/.voice-cli/config.yaml` |
| `VOICE_CLI_DATA_DIR` | Data directory | `~/.voice-cli/data` |
| `VOICE_CLI_LOG_LEVEL` | Log level | `info` |
| `OPENAI_API_KEY` | OpenAI API key | - |
| `ELEVENLABS_API_KEY` | ElevenLabs API key | - |
| `WHISPER_MODEL_PATH` | Whisper model file | - |

---

## Related Documents

- [Nexus-Flow Standalone Architecture](./09-nexus-flow-standalone-architecture.md) — Integration points
- [AI-Bridge Service](./03-ai-bridge.md) — LLM provider abstraction
- [Shared pkg/ Modules](./08-shared-pkg-modules.md) — Common utilities
- [Error Management](../06-error-management/backend/01-error-codes.md) — Error handling patterns
