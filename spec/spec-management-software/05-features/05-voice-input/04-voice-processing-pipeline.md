# Voice Processing Pipeline

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

The Voice Processing Pipeline handles large audio inputs by saving files to the filesystem, chunking audio into segments, processing chunks in parallel via transcription AI, and storing results in a dedicated Voice database. This architecture addresses AI context window limitations for long-form audio.

**Cross-References:**
- [Voice Recorder](./01-voice-recorder.md)
- [Transcription Display](./02-transcription-display.md)
- [AI Integration](../06-ai-integration/00-overview.md)
- [Instruction System](../06-ai-integration/03-instruction-system.md)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        VOICE PROCESSING PIPELINE                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐   │
│  │    Audio     │    │    Save to   │    │    Audio     │    │   Parallel   │   │
│  │    Input     │───▶│  Filesystem  │───▶│   Chunker    │───▶│ Transcriber  │   │
│  │  (Browser)   │    │  + Voice DB  │    │  (1-min)     │    │   (AI Pool)  │   │
│  └──────────────┘    └──────────────┘    └──────────────┘    └──────────────┘   │
│                             │                   │                    │           │
│                             ▼                   ▼                    ▼           │
│                      ┌──────────────┐    ┌──────────────┐    ┌──────────────┐   │
│                      │   VoiceFile  │    │  VoiceChunk  │    │   Compiled   │   │
│                      │    (Root)    │    │   (Segments) │    │ Transcription│   │
│                      └──────────────┘    └──────────────┘    └──────────────┘   │
│                                                                      │           │
│                                                                      ▼           │
│                                                              ┌──────────────┐   │
│                                                              │   Ready for  │   │
│                                                              │  Instruction │   │
│                                                              │   Pipeline   │   │
│                                                              └──────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Filesystem Structure

```
{workDirectory}/
└── data/
    └── projects/
        └── {project_name}/
            └── voices/
                ├── 2026-01-29_001_a1b2c3d4.webm    # Original audio
                ├── 2026-01-29_001_a1b2c3d4/        # Chunks directory
                │   ├── chunk_001.webm              # 0:00 - 1:00
                │   ├── chunk_002.webm              # 1:00 - 2:00
                │   ├── chunk_003.webm              # 2:00 - 3:00
                │   └── ...
                ├── 2026-01-29_002_e5f6g7h8.webm    # Second recording
                └── 2026-01-29_002_e5f6g7h8/
                    └── ...
```

### File Naming Convention

| Component | Format | Example |
|-----------|--------|---------|
| Date | `YYYY-MM-DD` | `2026-01-29` |
| Sequence | `###` (3-digit, zero-padded) | `001`, `002` |
| ID | First 8 chars of UUID | `a1b2c3d4` |
| Full Name | `{date}_{seq}_{id}.{ext}` | `2026-01-29_001_a1b2c3d4.webm` |

---

## Database Schema (Voice.db)

### VoiceFile Table (Root Table)

```sql
CREATE TABLE VoiceFile (
    Id TEXT PRIMARY KEY,              -- UUID
    ProjectId TEXT NOT NULL,          -- Reference to project
    
    -- File Information
    OriginalFilePath TEXT NOT NULL,   -- Relative path to original audio
    FileName TEXT NOT NULL,           -- Original filename
    FileSizeBytes INTEGER NOT NULL,   -- File size in bytes
    DurationSeconds REAL NOT NULL,    -- Total duration
    MimeType TEXT NOT NULL,           -- audio/webm, audio/ogg, etc.
    
    -- Metadata
    Title TEXT,                       -- User-provided or AI-generated title
    Description TEXT,                 -- Brief description of content
    LanguageCode TEXT DEFAULT 'auto', -- ISO 639-3 code or 'auto' for detection
    
    -- Processing Status
    Status TEXT NOT NULL CHECK (Status IN (
        'uploaded',       -- File saved to filesystem
        'chunking',       -- Being split into segments
        'chunked',        -- Chunks created
        'transcribing',   -- Chunks being processed
        'transcribed',    -- All chunks done
        'compiling',      -- Combining transcriptions
        'completed',      -- Ready for use
        'failed'          -- Processing failed
    )) DEFAULT 'uploaded',
    
    -- Chunk Configuration
    ChunkDurationSeconds INTEGER DEFAULT 60,  -- Target chunk size
    TotalChunks INTEGER DEFAULT 0,            -- Number of chunks created
    
    -- Processing Metrics
    ProcessingStartedAt TEXT,
    ProcessingCompletedAt TEXT,
    ProcessingDurationMs INTEGER,
    
    -- Error Tracking
    ErrorMessage TEXT,
    RetryCount INTEGER DEFAULT 0,
    
    -- Timestamps
    RecordedAt TEXT,                  -- When audio was recorded (if known)
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    
    -- Sequence for ordering within project
    Sequence INTEGER NOT NULL,        -- Auto-incrementing per project
    
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE
);

CREATE INDEX IX_VoiceFile_ProjectId ON VoiceFile(ProjectId);
CREATE INDEX IX_VoiceFile_Status ON VoiceFile(Status);
CREATE INDEX IX_VoiceFile_CreatedAt ON VoiceFile(CreatedAt DESC);
CREATE INDEX IX_VoiceFile_Sequence ON VoiceFile(ProjectId, Sequence);
```

### VoiceChunk Table (Segment Table)

```sql
CREATE TABLE VoiceChunk (
    Id TEXT PRIMARY KEY,              -- UUID
    VoiceFileId TEXT NOT NULL,        -- Parent voice file
    
    -- Chunk Information
    ChunkIndex INTEGER NOT NULL,      -- 1-based index
    ChunkFilePath TEXT NOT NULL,      -- Relative path to chunk file
    StartTimeSeconds REAL NOT NULL,   -- Start position in original
    EndTimeSeconds REAL NOT NULL,     -- End position in original
    DurationSeconds REAL NOT NULL,    -- Chunk duration
    FileSizeBytes INTEGER NOT NULL,   -- Chunk file size
    
    -- Transcription Status
    Status TEXT NOT NULL CHECK (Status IN (
        'pending',        -- Waiting to process
        'processing',     -- Being transcribed
        'completed',      -- Transcription done
        'failed'          -- Transcription failed
    )) DEFAULT 'pending',
    
    -- Transcription Result
    TranscribedText TEXT,             -- Raw transcription
    Confidence REAL,                  -- Transcription confidence (0-1)
    DetectedLanguage TEXT,            -- Detected language code
    
    -- Word-Level Timestamps (JSON array)
    WordTimestamps TEXT,              -- JSON: [{word, start, end}, ...]
    
    -- Processing Metrics
    TranscriptionStartedAt TEXT,
    TranscriptionCompletedAt TEXT,
    TranscriptionDurationMs INTEGER,
    TokensUsed INTEGER,               -- AI tokens consumed
    
    -- Model Used
    ModelId TEXT,                     -- Which voice model processed this
    
    -- Error Tracking
    ErrorMessage TEXT,
    RetryCount INTEGER DEFAULT 0,
    
    -- Timestamps
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (VoiceFileId) REFERENCES VoiceFile(Id) ON DELETE CASCADE
);

CREATE INDEX IX_VoiceChunk_VoiceFileId ON VoiceChunk(VoiceFileId);
CREATE INDEX IX_VoiceChunk_Status ON VoiceChunk(Status);
CREATE INDEX IX_VoiceChunk_ChunkIndex ON VoiceChunk(VoiceFileId, ChunkIndex);
```

### VoiceTranscription Table (Compiled Result)

```sql
CREATE TABLE VoiceTranscription (
    Id TEXT PRIMARY KEY,              -- UUID
    VoiceFileId TEXT NOT NULL UNIQUE, -- One-to-one with VoiceFile
    
    -- Compiled Transcription
    FullText TEXT NOT NULL,           -- Complete combined transcription
    FormattedText TEXT,               -- With paragraph breaks, punctuation
    
    -- Word-Level Data (Combined from chunks)
    WordTimestamps TEXT,              -- JSON: [{word, start, end, chunkId}, ...]
    
    -- Summary (AI-generated)
    Summary TEXT,                     -- Brief summary of content
    Keywords TEXT,                    -- JSON: ["keyword1", "keyword2", ...]
    
    -- Statistics
    WordCount INTEGER NOT NULL,
    CharacterCount INTEGER NOT NULL,
    SentenceCount INTEGER,
    ParagraphCount INTEGER,
    
    -- Quality Metrics
    AverageConfidence REAL,           -- Average across all chunks
    LowConfidenceSegments TEXT,       -- JSON: [{start, end, text}, ...]
    
    -- Processing Info
    CompiledAt TEXT NOT NULL,
    CompilationDurationMs INTEGER,
    TotalTokensUsed INTEGER,          -- Sum of all chunk tokens
    
    -- Timestamps
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    
    FOREIGN KEY (VoiceFileId) REFERENCES VoiceFile(Id) ON DELETE CASCADE
);

CREATE INDEX IX_VoiceTranscription_VoiceFileId ON VoiceTranscription(VoiceFileId);
```

---

## GORM Models (Go)

```go
package models

import (
    "time"
    "gorm.io/datatypes"
)

// VoiceFile represents the root voice recording
type VoiceFile struct {
    Id        string `gorm:"primaryKey;type:TEXT"`
    ProjectId string `gorm:"type:TEXT;not null;index"`
    Project   *Project `gorm:"foreignKey:ProjectId"`
    
    // File Information
    OriginalFilePath string  `gorm:"type:TEXT;not null"`
    FileName         string  `gorm:"type:TEXT;not null"`
    FileSizeBytes    int64   `gorm:"not null"`
    DurationSeconds  float64 `gorm:"not null"`
    MimeType         string  `gorm:"type:TEXT;not null"`
    
    // Metadata
    Title        *string `gorm:"type:TEXT"`
    Description  *string `gorm:"type:TEXT"`
    LanguageCode string  `gorm:"type:TEXT;default:'auto'"`
    
    // Processing Status
    Status string `gorm:"type:TEXT;not null;default:'uploaded';index"`
    
    // Chunk Configuration
    ChunkDurationSeconds int `gorm:"default:60"`
    TotalChunks          int `gorm:"default:0"`
    
    // Processing Metrics
    ProcessingStartedAt   *time.Time
    ProcessingCompletedAt *time.Time
    ProcessingDurationMs  *int
    
    // Error Tracking
    ErrorMessage *string `gorm:"type:TEXT"`
    RetryCount   int     `gorm:"default:0"`
    
    // Sequence
    Sequence int `gorm:"not null;index:idx_voice_project_seq,priority:2"`
    
    // Timestamps
    RecordedAt *time.Time
    CreatedAt  time.Time `gorm:"not null;index"`
    UpdatedAt  time.Time `gorm:"not null"`
    
    // Relations
    Chunks       []VoiceChunk       `gorm:"foreignKey:VoiceFileId;constraint:OnDelete:CASCADE"`
    Transcription *VoiceTranscription `gorm:"foreignKey:VoiceFileId;constraint:OnDelete:CASCADE"`
}

// VoiceChunk represents a segment of the voice file
type VoiceChunk struct {
    Id          string `gorm:"primaryKey;type:TEXT"`
    VoiceFileId string `gorm:"type:TEXT;not null;index"`
    VoiceFile   *VoiceFile `gorm:"foreignKey:VoiceFileId"`
    
    // Chunk Information
    ChunkIndex       int     `gorm:"not null"`
    ChunkFilePath    string  `gorm:"type:TEXT;not null"`
    StartTimeSeconds float64 `gorm:"not null"`
    EndTimeSeconds   float64 `gorm:"not null"`
    DurationSeconds  float64 `gorm:"not null"`
    FileSizeBytes    int64   `gorm:"not null"`
    
    // Status
    Status string `gorm:"type:TEXT;not null;default:'pending';index"`
    
    // Transcription Result
    TranscribedText  *string  `gorm:"type:TEXT"`
    Confidence       *float64
    DetectedLanguage *string  `gorm:"type:TEXT"`
    
    // Word Timestamps (JSON)
    WordTimestamps datatypes.JSON `gorm:"type:TEXT"`
    
    // Processing Metrics
    TranscriptionStartedAt   *time.Time
    TranscriptionCompletedAt *time.Time
    TranscriptionDurationMs  *int
    TokensUsed               *int
    
    // Model
    ModelId *string `gorm:"type:TEXT"`
    
    // Error Tracking
    ErrorMessage *string `gorm:"type:TEXT"`
    RetryCount   int     `gorm:"default:0"`
    
    // Timestamps
    CreatedAt time.Time `gorm:"not null"`
    UpdatedAt time.Time `gorm:"not null"`
}

// VoiceTranscription represents the compiled transcription
type VoiceTranscription struct {
    Id          string `gorm:"primaryKey;type:TEXT"`
    VoiceFileId string `gorm:"type:TEXT;not null;uniqueIndex"`
    VoiceFile   *VoiceFile `gorm:"foreignKey:VoiceFileId"`
    
    // Compiled Text
    FullText      string  `gorm:"type:TEXT;not null"`
    FormattedText *string `gorm:"type:TEXT"`
    
    // Word Data
    WordTimestamps datatypes.JSON `gorm:"type:TEXT"`
    
    // Summary
    Summary  *string        `gorm:"type:TEXT"`
    Keywords datatypes.JSON `gorm:"type:TEXT"`
    
    // Statistics
    WordCount      int `gorm:"not null"`
    CharacterCount int `gorm:"not null"`
    SentenceCount  *int
    ParagraphCount *int
    
    // Quality
    AverageConfidence     *float64
    LowConfidenceSegments datatypes.JSON `gorm:"type:TEXT"`
    
    // Processing
    CompiledAt           time.Time `gorm:"not null"`
    CompilationDurationMs *int
    TotalTokensUsed      *int
    
    // Timestamps
    CreatedAt time.Time `gorm:"not null"`
    UpdatedAt time.Time `gorm:"not null"`
}
```

---

## Audio Chunking (Golang)

### Required Libraries

| Library | Purpose | Import Path |
|---------|---------|-------------|
| **go-audio/audio** | Audio data structures | `github.com/go-audio/audio` |
| **go-audio/wav** | WAV encoding/decoding | `github.com/go-audio/wav` |
| **hajimehoshi/oto** | Audio playback (optional) | `github.com/hajimehoshi/oto/v2` |
| **faiface/beep** | Audio processing toolkit | `github.com/faiface/beep` |
| **faiface/beep/mp3** | MP3 support | `github.com/faiface/beep/mp3` |
| **faiface/beep/wav** | WAV support | `github.com/faiface/beep/wav` |
| **zaf/resample** | Sample rate conversion | `github.com/zaf/resample` |

### FFmpeg Integration (Recommended)

For production-grade audio processing, use FFmpeg via CLI wrapper:

```go
package audio

import (
    "context"
    "fmt"
    "os/exec"
    "path/filepath"
    "strconv"
)

// ChunkerConfig holds chunking configuration
type ChunkerConfig struct {
    ChunkDurationSeconds int    // Target chunk duration (default: 60)
    OutputFormat         string // Output format: "wav", "mp3", "webm"
    SampleRate           int    // Target sample rate (default: 16000)
    Channels             int    // Number of channels (default: 1 = mono)
    FFmpegPath           string // Path to ffmpeg binary
}

// DefaultChunkerConfig returns sensible defaults
func DefaultChunkerConfig() ChunkerConfig {
    return ChunkerConfig{
        ChunkDurationSeconds: 60,
        OutputFormat:         "wav",
        SampleRate:           16000,
        Channels:             1,
        FFmpegPath:           "ffmpeg",
    }
}

// AudioChunker splits audio files into segments
type AudioChunker struct {
    config ChunkerConfig
}

// NewAudioChunker creates a new chunker
func NewAudioChunker(config ChunkerConfig) *AudioChunker {
    return &AudioChunker{config: config}
}

// AudioMetadata contains audio file information
type AudioMetadata struct {
    DurationSeconds float64
    SampleRate      int
    Channels        int
    Bitrate         int
    Format          string
}

// GetMetadata extracts audio metadata using ffprobe
func (c *AudioChunker) GetMetadata(ctx context.Context, filePath string) (*AudioMetadata, error) {
    cmd := exec.CommandContext(ctx, "ffprobe",
        "-v", "quiet",
        "-print_format", "json",
        "-show_format",
        "-show_streams",
        filePath,
    )
    
    output, err := cmd.Output()
    if err != nil {
        return nil, fmt.Errorf("ffprobe failed: %w", err)
    }
    
    // Parse JSON output and extract metadata
    // ... (parsing implementation)
    
    return &AudioMetadata{}, nil
}

// ChunkResult represents a single chunk
type ChunkResult struct {
    Index            int
    FilePath         string
    StartTimeSeconds float64
    EndTimeSeconds   float64
    DurationSeconds  float64
    FileSizeBytes    int64
}

// ChunkAudio splits audio into segments
func (c *AudioChunker) ChunkAudio(
    ctx context.Context,
    inputPath string,
    outputDir string,
    totalDuration float64,
) ([]ChunkResult, error) {
    var chunks []ChunkResult
    chunkIndex := 1
    currentTime := 0.0
    
    for currentTime < totalDuration {
        endTime := currentTime + float64(c.config.ChunkDurationSeconds)
        if endTime > totalDuration {
            endTime = totalDuration
        }
        
        chunkFileName := fmt.Sprintf("chunk_%03d.%s", chunkIndex, c.config.OutputFormat)
        chunkPath := filepath.Join(outputDir, chunkFileName)
        
        // FFmpeg command to extract segment
        args := []string{
            "-i", inputPath,
            "-ss", fmt.Sprintf("%.3f", currentTime),
            "-t", fmt.Sprintf("%.3f", endTime-currentTime),
            "-ar", strconv.Itoa(c.config.SampleRate),
            "-ac", strconv.Itoa(c.config.Channels),
            "-y", // Overwrite
            chunkPath,
        }
        
        cmd := exec.CommandContext(ctx, c.config.FFmpegPath, args...)
        if err := cmd.Run(); err != nil {
            return nil, fmt.Errorf("chunk %d failed: %w", chunkIndex, err)
        }
        
        // Get chunk file size
        info, _ := os.Stat(chunkPath)
        
        chunks = append(chunks, ChunkResult{
            Index:            chunkIndex,
            FilePath:         chunkPath,
            StartTimeSeconds: currentTime,
            EndTimeSeconds:   endTime,
            DurationSeconds:  endTime - currentTime,
            FileSizeBytes:    info.Size(),
        })
        
        chunkIndex++
        currentTime = endTime
    }
    
    return chunks, nil
}
```

### Pure Go Alternative (go-audio)

```go
package audio

import (
    "io"
    "os"
    
    "github.com/go-audio/audio"
    "github.com/go-audio/wav"
)

// PureGoChunker uses pure Go libraries (no FFmpeg dependency)
type PureGoChunker struct {
    chunkSamples int // Samples per chunk
}

// ChunkWav splits a WAV file using pure Go
func (c *PureGoChunker) ChunkWav(inputPath, outputDir string) ([]ChunkResult, error) {
    f, err := os.Open(inputPath)
    if err != nil {
        return nil, err
    }
    defer f.Close()
    
    decoder := wav.NewDecoder(f)
    if !decoder.IsValidFile() {
        return nil, fmt.Errorf("invalid WAV file")
    }
    
    sampleRate := decoder.SampleRate
    samplesPerChunk := sampleRate * 60 // 60 seconds
    
    var chunks []ChunkResult
    chunkIndex := 1
    
    for {
        // Read chunk of samples
        buf := &audio.IntBuffer{
            Data:   make([]int, samplesPerChunk),
            Format: decoder.Format(),
        }
        
        n, err := decoder.PCMBuffer(buf)
        if n == 0 || err == io.EOF {
            break
        }
        
        // Write chunk to file
        chunkPath := filepath.Join(outputDir, fmt.Sprintf("chunk_%03d.wav", chunkIndex))
        if err := c.writeWav(chunkPath, buf, sampleRate); err != nil {
            return nil, err
        }
        
        chunks = append(chunks, ChunkResult{
            Index:    chunkIndex,
            FilePath: chunkPath,
            // ... calculate times
        })
        
        chunkIndex++
    }
    
    return chunks, nil
}
```

---

## Parallel Transcription Service

```go
package voice

import (
    "context"
    "sync"
    "time"
    
    "golang.org/x/sync/errgroup"
)

// TranscriptionConfig configures parallel processing
type TranscriptionConfig struct {
    MaxParallelWorkers int           // Concurrent transcription workers
    RequestTimeout     time.Duration // Per-chunk timeout
    RetryAttempts      int           // Retries on failure
    RetryDelay         time.Duration // Delay between retries
}

// DefaultTranscriptionConfig returns sensible defaults
func DefaultTranscriptionConfig() TranscriptionConfig {
    return TranscriptionConfig{
        MaxParallelWorkers: 4,
        RequestTimeout:     120 * time.Second,
        RetryAttempts:      3,
        RetryDelay:         5 * time.Second,
    }
}

// TranscriptionResult from AI model
type TranscriptionResult struct {
    ChunkId          string
    Text             string
    Confidence       float64
    DetectedLanguage string
    WordTimestamps   []WordTimestamp
    TokensUsed       int
    DurationMs       int
}

// WordTimestamp represents word-level timing
type WordTimestamp struct {
    Word  string  `json:"word"`
    Start float64 `json:"start"` // Seconds from chunk start
    End   float64 `json:"end"`
}

// ParallelTranscriber processes chunks concurrently
type ParallelTranscriber struct {
    config      TranscriptionConfig
    aiService   AIVoiceService
    voiceRepo   VoiceRepository
    eventBus    EventBus
}

// NewParallelTranscriber creates a new transcriber
func NewParallelTranscriber(
    config TranscriptionConfig,
    aiService AIVoiceService,
    voiceRepo VoiceRepository,
    eventBus EventBus,
) *ParallelTranscriber {
    return &ParallelTranscriber{
        config:    config,
        aiService: aiService,
        voiceRepo: voiceRepo,
        eventBus:  eventBus,
    }
}

// TranscribeChunks processes all chunks in parallel
func (t *ParallelTranscriber) TranscribeChunks(
    ctx context.Context,
    voiceFileId string,
    chunks []VoiceChunk,
) ([]TranscriptionResult, error) {
    // Update voice file status
    t.voiceRepo.UpdateVoiceFileStatus(ctx, voiceFileId, "transcribing")
    
    // Create worker pool
    g, gCtx := errgroup.WithContext(ctx)
    g.SetLimit(t.config.MaxParallelWorkers)
    
    // Results channel
    resultsChan := make(chan TranscriptionResult, len(chunks))
    
    // Process each chunk
    for _, chunk := range chunks {
        chunk := chunk // Capture for goroutine
        
        g.Go(func() error {
            result, err := t.transcribeChunk(gCtx, chunk)
            if err != nil {
                return err
            }
            resultsChan <- *result
            return nil
        })
    }
    
    // Wait for all chunks
    if err := g.Wait(); err != nil {
        t.voiceRepo.UpdateVoiceFileError(ctx, voiceFileId, err.Error())
        return nil, err
    }
    close(resultsChan)
    
    // Collect results
    var results []TranscriptionResult
    for result := range resultsChan {
        results = append(results, result)
    }
    
    // Sort by chunk index
    sort.Slice(results, func(i, j int) bool {
        return results[i].ChunkId < results[j].ChunkId
    })
    
    return results, nil
}

// transcribeChunk processes a single chunk with retries
func (t *ParallelTranscriber) transcribeChunk(
    ctx context.Context,
    chunk VoiceChunk,
) (*TranscriptionResult, error) {
    // Update chunk status
    t.voiceRepo.UpdateChunkStatus(ctx, chunk.Id, "processing")
    t.eventBus.Publish("voice:chunk:started", map[string]interface{}{
        "chunkId": chunk.Id,
        "index":   chunk.ChunkIndex,
    })
    
    startTime := time.Now()
    
    var lastErr error
    for attempt := 0; attempt <= t.config.RetryAttempts; attempt++ {
        if attempt > 0 {
            time.Sleep(t.config.RetryDelay)
        }
        
        // Read chunk audio file
        audioData, err := os.ReadFile(chunk.ChunkFilePath)
        if err != nil {
            lastErr = err
            continue
        }
        
        // Call AI voice model
        ctx, cancel := context.WithTimeout(ctx, t.config.RequestTimeout)
        result, err := t.aiService.Transcribe(ctx, TranscribeRequest{
            AudioData:    audioData,
            LanguageHint: chunk.VoiceFile.LanguageCode,
            EnableWordTimestamps: true,
        })
        cancel()
        
        if err != nil {
            lastErr = err
            continue
        }
        
        // Success - save result
        duration := int(time.Since(startTime).Milliseconds())
        
        t.voiceRepo.UpdateChunkTranscription(ctx, chunk.Id, UpdateChunkRequest{
            Status:           "completed",
            TranscribedText:  result.Text,
            Confidence:       result.Confidence,
            DetectedLanguage: result.Language,
            WordTimestamps:   result.Words,
            TokensUsed:       result.TokensUsed,
            DurationMs:       duration,
        })
        
        t.eventBus.Publish("voice:chunk:completed", map[string]interface{}{
            "chunkId":    chunk.Id,
            "index":      chunk.ChunkIndex,
            "durationMs": duration,
        })
        
        return &TranscriptionResult{
            ChunkId:          chunk.Id,
            Text:             result.Text,
            Confidence:       result.Confidence,
            DetectedLanguage: result.Language,
            WordTimestamps:   result.Words,
            TokensUsed:       result.TokensUsed,
            DurationMs:       duration,
        }, nil
    }
    
    // All retries failed
    t.voiceRepo.UpdateChunkError(ctx, chunk.Id, lastErr.Error())
    t.eventBus.Publish("voice:chunk:failed", map[string]interface{}{
        "chunkId": chunk.Id,
        "error":   lastErr.Error(),
    })
    
    return nil, fmt.Errorf("chunk %d failed after %d attempts: %w", 
        chunk.ChunkIndex, t.config.RetryAttempts, lastErr)
}
```

---

## Transcription Compiler

```go
package voice

import (
    "context"
    "sort"
    "strings"
    "time"
)

// TranscriptionCompiler combines chunk transcriptions
type TranscriptionCompiler struct {
    voiceRepo VoiceRepository
    eventBus  EventBus
}

// CompileTranscription combines all chunks into final transcription
func (c *TranscriptionCompiler) CompileTranscription(
    ctx context.Context,
    voiceFileId string,
) (*VoiceTranscription, error) {
    // Update status
    c.voiceRepo.UpdateVoiceFileStatus(ctx, voiceFileId, "compiling")
    startTime := time.Now()
    
    // Get all chunks in order
    chunks, err := c.voiceRepo.GetChunksByVoiceFileId(ctx, voiceFileId)
    if err != nil {
        return nil, err
    }
    
    // Sort by index
    sort.Slice(chunks, func(i, j int) bool {
        return chunks[i].ChunkIndex < chunks[j].ChunkIndex
    })
    
    // Combine transcriptions
    var textParts []string
    var allWords []WordTimestampWithChunk
    var totalTokens int
    var totalConfidence float64
    
    for _, chunk := range chunks {
        if chunk.TranscribedText != nil {
            textParts = append(textParts, *chunk.TranscribedText)
        }
        
        // Adjust word timestamps to absolute time
        if chunk.WordTimestamps != nil {
            var words []WordTimestamp
            json.Unmarshal(chunk.WordTimestamps, &words)
            
            for _, word := range words {
                allWords = append(allWords, WordTimestampWithChunk{
                    Word:    word.Word,
                    Start:   chunk.StartTimeSeconds + word.Start,
                    End:     chunk.StartTimeSeconds + word.End,
                    ChunkId: chunk.Id,
                })
            }
        }
        
        if chunk.TokensUsed != nil {
            totalTokens += *chunk.TokensUsed
        }
        if chunk.Confidence != nil {
            totalConfidence += *chunk.Confidence
        }
    }
    
    // Compile full text
    fullText := strings.Join(textParts, " ")
    
    // Calculate statistics
    wordCount := len(strings.Fields(fullText))
    charCount := len(fullText)
    avgConfidence := totalConfidence / float64(len(chunks))
    
    // Create transcription record
    transcription := &VoiceTranscription{
        Id:                uuid.New().String(),
        VoiceFileId:       voiceFileId,
        FullText:          fullText,
        FormattedText:     c.formatText(fullText),
        WordTimestamps:    allWords,
        WordCount:         wordCount,
        CharacterCount:    charCount,
        AverageConfidence: &avgConfidence,
        TotalTokensUsed:   &totalTokens,
        CompiledAt:        time.Now(),
        CompilationDurationMs: int(time.Since(startTime).Milliseconds()),
    }
    
    // Save transcription
    if err := c.voiceRepo.CreateTranscription(ctx, transcription); err != nil {
        return nil, err
    }
    
    // Update voice file status
    c.voiceRepo.UpdateVoiceFileStatus(ctx, voiceFileId, "completed")
    
    c.eventBus.Publish("voice:transcription:completed", map[string]interface{}{
        "voiceFileId": voiceFileId,
        "wordCount":   wordCount,
        "durationMs":  transcription.CompilationDurationMs,
    })
    
    return transcription, nil
}

// formatText adds paragraph breaks and improves readability
func (c *TranscriptionCompiler) formatText(text string) *string {
    // Split into sentences and group into paragraphs
    // Add proper punctuation and capitalization
    formatted := text // Placeholder - implement NLP-based formatting
    return &formatted
}
```

---

## Voice Processing Service (Main Orchestrator)

```go
package voice

import (
    "context"
    "os"
    "path/filepath"
    "time"
    
    "github.com/google/uuid"
)

// VoiceProcessingService orchestrates the entire pipeline
type VoiceProcessingService struct {
    config     VoiceConfig
    pathMgr    PathManager
    chunker    *AudioChunker
    transcriber *ParallelTranscriber
    compiler   *TranscriptionCompiler
    voiceRepo  VoiceRepository
    eventBus   EventBus
}

// VoiceConfig holds service configuration
type VoiceConfig struct {
    ChunkDurationSeconds int
    MaxParallelWorkers   int
    SupportedFormats     []string // webm, wav, mp3, ogg
    MaxFileSizeMB        int
    TempDirectory        string
}

// ProcessVoiceInput handles the complete voice processing pipeline
func (s *VoiceProcessingService) ProcessVoiceInput(
    ctx context.Context,
    projectId string,
    audioData []byte,
    fileName string,
    options ProcessOptions,
) (*VoiceFile, error) {
    // 1. Validate input
    if err := s.validateInput(audioData, fileName); err != nil {
        return nil, err
    }
    
    // 2. Generate file metadata
    voiceId := uuid.New().String()
    shortId := voiceId[:8]
    today := time.Now().Format("2006-01-02")
    sequence := s.getNextSequence(ctx, projectId)
    
    // 3. Build file paths
    projectName := s.getProjectName(ctx, projectId)
    voiceFileName := fmt.Sprintf("%s_%03d_%s%s", 
        today, sequence, shortId, filepath.Ext(fileName))
    
    relativePath := filepath.Join("data", "projects", projectName, "voices", voiceFileName)
    absolutePath := s.pathMgr.GetAbsolutePath(relativePath)
    
    // 4. Ensure directory exists
    voiceDir := filepath.Dir(absolutePath)
    if err := os.MkdirAll(voiceDir, 0755); err != nil {
        return nil, fmt.Errorf("create voice directory: %w", err)
    }
    
    // 5. Save audio file
    if err := os.WriteFile(absolutePath, audioData, 0644); err != nil {
        return nil, fmt.Errorf("save audio file: %w", err)
    }
    
    // 6. Get audio metadata
    metadata, err := s.chunker.GetMetadata(ctx, absolutePath)
    if err != nil {
        return nil, fmt.Errorf("get audio metadata: %w", err)
    }
    
    // 7. Create VoiceFile record
    voiceFile := &VoiceFile{
        Id:                   voiceId,
        ProjectId:            projectId,
        OriginalFilePath:     relativePath,
        FileName:             fileName,
        FileSizeBytes:        int64(len(audioData)),
        DurationSeconds:      metadata.DurationSeconds,
        MimeType:             getMimeType(fileName),
        Title:                options.Title,
        Description:          options.Description,
        LanguageCode:         options.LanguageCode,
        Status:               "uploaded",
        ChunkDurationSeconds: s.config.ChunkDurationSeconds,
        Sequence:             sequence,
        RecordedAt:           options.RecordedAt,
        CreatedAt:            time.Now(),
        UpdatedAt:            time.Now(),
    }
    
    if err := s.voiceRepo.CreateVoiceFile(ctx, voiceFile); err != nil {
        return nil, err
    }
    
    s.eventBus.Publish("voice:file:created", map[string]interface{}{
        "voiceFileId": voiceId,
        "projectId":   projectId,
        "duration":    metadata.DurationSeconds,
    })
    
    // 8. Start async processing pipeline
    go s.processVoiceAsync(context.Background(), voiceFile)
    
    return voiceFile, nil
}

// processVoiceAsync runs chunking, transcription, and compilation
func (s *VoiceProcessingService) processVoiceAsync(
    ctx context.Context,
    voiceFile *VoiceFile,
) {
    defer func() {
        if r := recover(); r != nil {
            s.voiceRepo.UpdateVoiceFileError(ctx, voiceFile.Id, fmt.Sprint(r))
        }
    }()
    
    startTime := time.Now()
    s.voiceRepo.UpdateVoiceFileProcessingStart(ctx, voiceFile.Id)
    
    // 1. CHUNK: Split audio into segments
    s.voiceRepo.UpdateVoiceFileStatus(ctx, voiceFile.Id, "chunking")
    
    absolutePath := s.pathMgr.GetAbsolutePath(voiceFile.OriginalFilePath)
    chunksDir := absolutePath[:len(absolutePath)-len(filepath.Ext(absolutePath))]
    
    if err := os.MkdirAll(chunksDir, 0755); err != nil {
        s.voiceRepo.UpdateVoiceFileError(ctx, voiceFile.Id, err.Error())
        return
    }
    
    chunkResults, err := s.chunker.ChunkAudio(ctx, 
        absolutePath, chunksDir, voiceFile.DurationSeconds)
    if err != nil {
        s.voiceRepo.UpdateVoiceFileError(ctx, voiceFile.Id, err.Error())
        return
    }
    
    // Save chunk records to database
    var chunks []VoiceChunk
    for _, cr := range chunkResults {
        chunk := VoiceChunk{
            Id:               uuid.New().String(),
            VoiceFileId:      voiceFile.Id,
            ChunkIndex:       cr.Index,
            ChunkFilePath:    s.pathMgr.GetRelativePath(cr.FilePath),
            StartTimeSeconds: cr.StartTimeSeconds,
            EndTimeSeconds:   cr.EndTimeSeconds,
            DurationSeconds:  cr.DurationSeconds,
            FileSizeBytes:    cr.FileSizeBytes,
            Status:           "pending",
            CreatedAt:        time.Now(),
            UpdatedAt:        time.Now(),
        }
        chunks = append(chunks, chunk)
    }
    
    if err := s.voiceRepo.CreateChunks(ctx, chunks); err != nil {
        s.voiceRepo.UpdateVoiceFileError(ctx, voiceFile.Id, err.Error())
        return
    }
    
    s.voiceRepo.UpdateVoiceFileTotalChunks(ctx, voiceFile.Id, len(chunks))
    s.voiceRepo.UpdateVoiceFileStatus(ctx, voiceFile.Id, "chunked")
    
    s.eventBus.Publish("voice:chunking:completed", map[string]interface{}{
        "voiceFileId": voiceFile.Id,
        "totalChunks": len(chunks),
    })
    
    // 2. TRANSCRIBE: Process chunks in parallel
    results, err := s.transcriber.TranscribeChunks(ctx, voiceFile.Id, chunks)
    if err != nil {
        s.voiceRepo.UpdateVoiceFileError(ctx, voiceFile.Id, err.Error())
        return
    }
    
    s.voiceRepo.UpdateVoiceFileStatus(ctx, voiceFile.Id, "transcribed")
    
    // 3. COMPILE: Combine transcriptions
    transcription, err := s.compiler.CompileTranscription(ctx, voiceFile.Id)
    if err != nil {
        s.voiceRepo.UpdateVoiceFileError(ctx, voiceFile.Id, err.Error())
        return
    }
    
    // 4. Update final status
    processingDuration := int(time.Since(startTime).Milliseconds())
    s.voiceRepo.UpdateVoiceFileComplete(ctx, voiceFile.Id, processingDuration)
    
    s.eventBus.Publish("voice:processing:completed", map[string]interface{}{
        "voiceFileId":  voiceFile.Id,
        "wordCount":    transcription.WordCount,
        "durationMs":   processingDuration,
        "totalChunks":  len(chunks),
    })
}
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/projects/{projectId}/voices` | Upload voice file |
| GET | `/api/v1/projects/{projectId}/voices` | List project voices |
| GET | `/api/v1/voices/{voiceId}` | Get voice details |
| GET | `/api/v1/voices/{voiceId}/chunks` | Get voice chunks |
| GET | `/api/v1/voices/{voiceId}/transcription` | Get compiled transcription |
| DELETE | `/api/v1/voices/{voiceId}` | Delete voice and files |
| POST | `/api/v1/voices/{voiceId}/retry` | Retry failed processing |

---

## WebSocket Events

| Event | Direction | Payload |
|-------|-----------|---------|
| `voice:file:created` | Server→Client | `{voiceFileId, projectId, duration}` |
| `voice:chunking:started` | Server→Client | `{voiceFileId}` |
| `voice:chunking:completed` | Server→Client | `{voiceFileId, totalChunks}` |
| `voice:chunk:started` | Server→Client | `{chunkId, index}` |
| `voice:chunk:completed` | Server→Client | `{chunkId, index, durationMs}` |
| `voice:chunk:failed` | Server→Client | `{chunkId, error}` |
| `voice:transcription:completed` | Server→Client | `{voiceFileId, wordCount}` |
| `voice:processing:completed` | Server→Client | `{voiceFileId, wordCount, durationMs}` |
| `voice:processing:failed` | Server→Client | `{voiceFileId, error}` |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 7010 | ERR_VOICE_FILE_TOO_LARGE | Audio file exceeds size limit |
| 7011 | ERR_VOICE_UNSUPPORTED_FORMAT | Unsupported audio format |
| 7012 | ERR_VOICE_SAVE_FAILED | Failed to save audio file |
| 7013 | ERR_VOICE_CHUNKING_FAILED | Audio chunking failed |
| 7014 | ERR_VOICE_TRANSCRIPTION_FAILED | Transcription failed |
| 7015 | ERR_VOICE_COMPILATION_FAILED | Transcription compilation failed |
| 7016 | ERR_VOICE_NOT_FOUND | Voice file not found |
| 7017 | ERR_VOICE_FFMPEG_NOT_AVAILABLE | FFmpeg not installed |
| 7018 | ERR_VOICE_INVALID_DURATION | Invalid audio duration |
| 7019 | ERR_VOICE_PROJECT_NOT_FOUND | Project not found |

---

## Configuration Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `voice.chunk.durationSeconds` | int | 60 | Target chunk duration |
| `voice.chunk.minDurationSeconds` | int | 5 | Minimum chunk to process |
| `voice.parallel.maxWorkers` | int | 4 | Concurrent transcription workers |
| `voice.transcription.timeoutSeconds` | int | 120 | Per-chunk timeout |
| `voice.transcription.retryAttempts` | int | 3 | Retry count on failure |
| `voice.file.maxSizeMB` | int | 500 | Maximum file size |
| `voice.file.supportedFormats` | []string | ["webm","wav","mp3","ogg"] | Allowed formats |
| `voice.storage.basePath` | string | "data/projects" | Base storage path |
| `voice.ffmpeg.path` | string | "ffmpeg" | FFmpeg binary path |

---

## Related Specifications

- [Voice Recorder](./01-voice-recorder.md)
- [Transcription Display](./02-transcription-display.md)
- [Audio Player](./03-audio-player.md)
- [Instruction System](../06-ai-integration/03-instruction-system.md)
- [AI Integration](../06-ai-integration/00-overview.md)
