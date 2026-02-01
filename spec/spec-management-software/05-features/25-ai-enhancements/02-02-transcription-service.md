# Phase 2.2: Local Whisper Transcription Service

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Go backend service integrating whisper.cpp for private, local speech-to-text transcription. No external API dependencies - all processing happens on the server.

**Cross-References:**
- [Voice Resilience](./02-voice-resilience.md)
- [Audio Capture](./02-01-audio-capture.md)
- [AI Integration](../06-ai-integration/00-overview.md)

---

## 1. Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Frontend                                         │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  AudioRecording (Blob) + Metadata                                │    │
│  └──────────────────────────────┬──────────────────────────────────┘    │
└─────────────────────────────────┼───────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    Go HTTP Server                                        │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  POST /api/v1/audio/transcribe                                  │    │
│  │  ├── Parse multipart/form-data                                  │    │
│  │  ├── Validate audio format (webm, mp3, wav, ogg)               │    │
│  │  ├── Store to temp file                                        │    │
│  │  └── Queue transcription job                                   │    │
│  └──────────────────────────────┬──────────────────────────────────┘    │
│                                 │                                        │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  WebSocket /api/v1/audio/stream                                 │    │
│  │  ├── Real-time PCM streaming                                   │    │
│  │  ├── Chunked transcription                                     │    │
│  │  └── Progressive results                                       │    │
│  └──────────────────────────────┬──────────────────────────────────┘    │
│                                 │                                        │
└─────────────────────────────────┼───────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    Transcription Worker                                  │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Job Queue (Channel-based)                                      │    │
│  │  ├── Max concurrent: 2 (based on CPU)                          │    │
│  │  ├── Timeout: 5 minutes per job                                │    │
│  │  └── Retry on failure: 3 attempts                              │    │
│  └──────────────────────────────┬──────────────────────────────────┘    │
│                                 │                                        │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Audio Preprocessor (FFmpeg)                                    │    │
│  │  ├── Convert any format → WAV (16kHz mono)                     │    │
│  │  ├── Normalize audio levels                                    │    │
│  │  └── Split long audio (>30s) into chunks                       │    │
│  └──────────────────────────────┬──────────────────────────────────┘    │
│                                 │                                        │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  whisper.cpp (Large-v3 Model)                                   │    │
│  │  ├── Model path: /models/whisper-large-v3.bin                  │    │
│  │  ├── Language: auto-detect                                     │    │
│  │  ├── Output: JSON with timestamps                              │    │
│  │  └── VAD: enabled (skip silence)                               │    │
│  └──────────────────────────────┬──────────────────────────────────┘    │
│                                 │                                        │
└─────────────────────────────────┼───────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         SQLite Database                                  │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  audio_recordings                                               │    │
│  │  ├── id, project_id, file_path                                 │    │
│  │  ├── transcription, transcription_status                       │    │
│  │  └── segments (JSON), language, confidence                     │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Whisper Model Selection

### Model Comparison

| Model | Size | Speed | Quality | VRAM | Use Case |
|-------|------|-------|---------|------|----------|
| tiny | 39MB | ~32x | Low | 1GB | Testing only |
| base | 74MB | ~16x | Fair | 1GB | Fast drafts |
| small | 244MB | ~6x | Good | 2GB | General use |
| medium | 769MB | ~2x | Great | 5GB | Production |
| large-v3 | 1.5GB | 1x | Best | 10GB | Recommended |

### Recommended: `whisper-large-v3`

**Rationale:**
- Best accuracy for multi-language support
- Superior handling of accents and noisy audio
- Improved timestamp accuracy
- Local execution = no API costs or data privacy concerns

**Hardware Requirements:**
- CPU: 8+ cores recommended
- RAM: 16GB minimum
- GPU: Optional (CUDA/Metal acceleration)

---

## 3. Go Service Implementation

### Project Structure

```
internal/
├── ai/
│   └── whisper/
│       ├── service.go          # Main service
│       ├── worker.go           # Job processing
│       ├── preprocessor.go     # FFmpeg integration
│       ├── parser.go           # Output parsing
│       └── models.go           # Types
├── api/
│   └── handlers/
│       └── audio.go            # HTTP handlers
└── storage/
    └── audio.go                # File storage
```

### Core Service

```go
// internal/ai/whisper/service.go

package whisper

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"sync"
	"time"
)

// Config for whisper service
type Config struct {
	ModelPath      string        // Path to .bin model file
	WhisperBinary  string        // Path to whisper.cpp binary
	TempDir        string        // Temp directory for audio files
	MaxConcurrent  int           // Max concurrent transcriptions
	JobTimeout     time.Duration // Timeout per job
	MaxRetries     int           // Retry attempts on failure
}

// Service manages whisper transcription
type Service struct {
	config      Config
	jobQueue    chan *TranscriptionJob
	workerWg    sync.WaitGroup
	preprocessor *Preprocessor
	
	mu          sync.RWMutex
	activeJobs  map[string]*TranscriptionJob
}

// NewService creates a whisper service
func NewService(cfg Config) (*Service, error) {
	// Validate model exists
	if _, err := os.Stat(cfg.ModelPath); os.IsNotExist(err) {
		return nil, fmt.Errorf("model not found: %s", cfg.ModelPath)
	}
	
	// Validate binary exists
	if _, err := os.Stat(cfg.WhisperBinary); os.IsNotExist(err) {
		return nil, fmt.Errorf("whisper binary not found: %s", cfg.WhisperBinary)
	}
	
	// Create temp directory
	if err := os.MkdirAll(cfg.TempDir, 0755); err != nil {
		return nil, fmt.Errorf("failed to create temp dir: %w", err)
	}
	
	s := &Service{
		config:      cfg,
		jobQueue:    make(chan *TranscriptionJob, 100),
		activeJobs:  make(map[string]*TranscriptionJob),
		preprocessor: NewPreprocessor(),
	}
	
	// Start workers
	for i := 0; i < cfg.MaxConcurrent; i++ {
		s.workerWg.Add(1)
		go s.worker(i)
	}
	
	return s, nil
}

// Transcribe processes audio file synchronously
func (s *Service) Transcribe(ctx context.Context, audioPath, format string) (*TranscriptionResult, error) {
	job := &TranscriptionJob{
		ID:        generateJobID(),
		AudioPath: audioPath,
		Format:    format,
		Status:    JobPending,
		CreatedAt: time.Now(),
		result:    make(chan *jobResult, 1),
	}
	
	// Track active job
	s.mu.Lock()
	s.activeJobs[job.ID] = job
	s.mu.Unlock()
	
	defer func() {
		s.mu.Lock()
		delete(s.activeJobs, job.ID)
		s.mu.Unlock()
	}()
	
	// Send to queue
	select {
	case s.jobQueue <- job:
	case <-ctx.Done():
		return nil, ctx.Err()
	}
	
	// Wait for result
	select {
	case result := <-job.result:
		if result.err != nil {
			return nil, result.err
		}
		return result.transcription, nil
	case <-ctx.Done():
		return nil, ctx.Err()
	}
}

// TranscribeAsync returns immediately with job ID
func (s *Service) TranscribeAsync(audioPath, format string, callback func(*TranscriptionResult, error)) string {
	job := &TranscriptionJob{
		ID:        generateJobID(),
		AudioPath: audioPath,
		Format:    format,
		Status:    JobPending,
		CreatedAt: time.Now(),
		callback:  callback,
	}
	
	s.mu.Lock()
	s.activeJobs[job.ID] = job
	s.mu.Unlock()
	
	s.jobQueue <- job
	
	return job.ID
}

// GetJobStatus returns current status of a job
func (s *Service) GetJobStatus(jobID string) (JobStatus, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	
	job, ok := s.activeJobs[jobID]
	if !ok {
		return "", fmt.Errorf("job not found: %s", jobID)
	}
	
	return job.Status, nil
}

// Shutdown gracefully stops the service
func (s *Service) Shutdown() {
	close(s.jobQueue)
	s.workerWg.Wait()
}

func generateJobID() string {
	return fmt.Sprintf("job_%d", time.Now().UnixNano())
}
```

### Worker Implementation

```go
// internal/ai/whisper/worker.go

package whisper

import (
	"bytes"
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"time"
)

// worker processes transcription jobs
func (s *Service) worker(id int) {
	defer s.workerWg.Done()
	
	for job := range s.jobQueue {
		s.processJob(id, job)
	}
}

func (s *Service) processJob(workerID int, job *TranscriptionJob) {
	ctx, cancel := context.WithTimeout(context.Background(), s.config.JobTimeout)
	defer cancel()
	
	job.Status = JobProcessing
	job.StartedAt = time.Now()
	
	var lastErr error
	
	for attempt := 0; attempt <= s.config.MaxRetries; attempt++ {
		result, err := s.runTranscription(ctx, job)
		if err == nil {
			job.Status = JobCompleted
			s.deliverResult(job, result, nil)
			return
		}
		
		lastErr = err
		
		// Exponential backoff before retry
		if attempt < s.config.MaxRetries {
			time.Sleep(time.Duration(1<<attempt) * time.Second)
		}
	}
	
	job.Status = JobFailed
	job.Error = lastErr.Error()
	s.deliverResult(job, nil, lastErr)
}

func (s *Service) runTranscription(ctx context.Context, job *TranscriptionJob) (*TranscriptionResult, error) {
	// Step 1: Preprocess audio to WAV
	wavPath, err := s.preprocessor.ToWAV(ctx, job.AudioPath, job.Format)
	if err != nil {
		return nil, fmt.Errorf("preprocessing failed: %w", err)
	}
	defer os.Remove(wavPath)
	
	// Step 2: Run whisper.cpp
	outputPath := filepath.Join(s.config.TempDir, fmt.Sprintf("%s.json", job.ID))
	defer os.Remove(outputPath)
	
	cmd := exec.CommandContext(ctx, s.config.WhisperBinary,
		"--model", s.config.ModelPath,
		"--file", wavPath,
		"--output-json",
		"--output-file", outputPath[:len(outputPath)-5], // whisper adds .json
		"--language", "auto",
		"--no-prints",
		"--threads", "4",
	)
	
	var stderr bytes.Buffer
	cmd.Stderr = &stderr
	
	if err := cmd.Run(); err != nil {
		return nil, fmt.Errorf("whisper failed: %w, stderr: %s", err, stderr.String())
	}
	
	// Step 3: Parse output
	result, err := parseWhisperOutput(outputPath)
	if err != nil {
		return nil, fmt.Errorf("parsing failed: %w", err)
	}
	
	return result, nil
}

func (s *Service) deliverResult(job *TranscriptionJob, result *TranscriptionResult, err error) {
	if job.callback != nil {
		job.callback(result, err)
	}
	
	if job.result != nil {
		job.result <- &jobResult{
			transcription: result,
			err:          err,
		}
	}
	
	// Cleanup job from active map
	s.mu.Lock()
	delete(s.activeJobs, job.ID)
	s.mu.Unlock()
}
```

### Audio Preprocessor

```go
// internal/ai/whisper/preprocessor.go

package whisper

import (
	"bytes"
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
)

// Preprocessor handles audio format conversion
type Preprocessor struct {
	ffmpegPath string
}

// NewPreprocessor creates a new preprocessor
func NewPreprocessor() *Preprocessor {
	return &Preprocessor{
		ffmpegPath: "ffmpeg", // Assume in PATH
	}
}

// ToWAV converts any audio format to 16kHz mono WAV
func (p *Preprocessor) ToWAV(ctx context.Context, inputPath, format string) (string, error) {
	// Generate output path
	outputPath := inputPath[:len(inputPath)-len(filepath.Ext(inputPath))] + "_converted.wav"
	
	// FFmpeg command for optimal whisper input
	args := []string{
		"-i", inputPath,
		"-ar", "16000",        // 16kHz sample rate
		"-ac", "1",            // Mono
		"-c:a", "pcm_s16le",   // 16-bit PCM
		"-y",                  // Overwrite
		outputPath,
	}
	
	cmd := exec.CommandContext(ctx, p.ffmpegPath, args...)
	
	var stderr bytes.Buffer
	cmd.Stderr = &stderr
	
	if err := cmd.Run(); err != nil {
		return "", fmt.Errorf("ffmpeg failed: %w, stderr: %s", err, stderr.String())
	}
	
	return outputPath, nil
}

// NormalizeAudio applies loudness normalization
func (p *Preprocessor) NormalizeAudio(ctx context.Context, inputPath string) (string, error) {
	outputPath := inputPath[:len(inputPath)-len(filepath.Ext(inputPath))] + "_normalized.wav"
	
	// Two-pass loudness normalization to -16 LUFS
	args := []string{
		"-i", inputPath,
		"-af", "loudnorm=I=-16:LRA=11:TP=-1.5",
		"-ar", "16000",
		"-ac", "1",
		"-c:a", "pcm_s16le",
		"-y",
		outputPath,
	}
	
	cmd := exec.CommandContext(ctx, p.ffmpegPath, args...)
	
	var stderr bytes.Buffer
	cmd.Stderr = &stderr
	
	if err := cmd.Run(); err != nil {
		return "", fmt.Errorf("normalization failed: %w", err)
	}
	
	return outputPath, nil
}

// GetDuration returns audio duration in seconds
func (p *Preprocessor) GetDuration(ctx context.Context, inputPath string) (float64, error) {
	cmd := exec.CommandContext(ctx, "ffprobe",
		"-v", "quiet",
		"-show_entries", "format=duration",
		"-of", "csv=p=0",
		inputPath,
	)
	
	output, err := cmd.Output()
	if err != nil {
		return 0, err
	}
	
	var duration float64
	fmt.Sscanf(string(bytes.TrimSpace(output)), "%f", &duration)
	
	return duration, nil
}

// SplitAudio splits audio into chunks of specified duration
func (p *Preprocessor) SplitAudio(ctx context.Context, inputPath string, chunkSeconds int) ([]string, error) {
	duration, err := p.GetDuration(ctx, inputPath)
	if err != nil {
		return nil, err
	}
	
	if duration <= float64(chunkSeconds) {
		return []string{inputPath}, nil
	}
	
	var chunks []string
	basePath := inputPath[:len(inputPath)-len(filepath.Ext(inputPath))]
	
	for i := 0; float64(i*chunkSeconds) < duration; i++ {
		chunkPath := fmt.Sprintf("%s_chunk%d.wav", basePath, i)
		
		args := []string{
			"-i", inputPath,
			"-ss", fmt.Sprintf("%d", i*chunkSeconds),
			"-t", fmt.Sprintf("%d", chunkSeconds),
			"-ar", "16000",
			"-ac", "1",
			"-c:a", "pcm_s16le",
			"-y",
			chunkPath,
		}
		
		cmd := exec.CommandContext(ctx, p.ffmpegPath, args...)
		if err := cmd.Run(); err != nil {
			// Cleanup created chunks
			for _, c := range chunks {
				os.Remove(c)
			}
			return nil, err
		}
		
		chunks = append(chunks, chunkPath)
	}
	
	return chunks, nil
}
```

### Output Parser

```go
// internal/ai/whisper/parser.go

package whisper

import (
	"encoding/json"
	"os"
)

// WhisperOutput represents whisper.cpp JSON output
type WhisperOutput struct {
	Transcription []WhisperSegment `json:"transcription"`
}

// WhisperSegment is a single transcription segment
type WhisperSegment struct {
	Timestamps struct {
		From string `json:"from"`
		To   string `json:"to"`
	} `json:"timestamps"`
	Offsets struct {
		From int64 `json:"from"`
		To   int64 `json:"to"`
	} `json:"offsets"`
	Text string `json:"text"`
}

// parseWhisperOutput reads and parses whisper.cpp JSON output
func parseWhisperOutput(jsonPath string) (*TranscriptionResult, error) {
	data, err := os.ReadFile(jsonPath)
	if err != nil {
		return nil, err
	}
	
	var output WhisperOutput
	if err := json.Unmarshal(data, &output); err != nil {
		return nil, err
	}
	
	result := &TranscriptionResult{
		Segments: make([]Segment, 0, len(output.Transcription)),
	}
	
	var fullText string
	
	for _, seg := range output.Transcription {
		// Convert milliseconds to seconds
		start := float64(seg.Offsets.From) / 1000.0
		end := float64(seg.Offsets.To) / 1000.0
		
		result.Segments = append(result.Segments, Segment{
			Start: start,
			End:   end,
			Text:  seg.Text,
		})
		
		fullText += seg.Text + " "
	}
	
	result.Text = fullText
	
	// Detect language from first segment (whisper provides this)
	if len(output.Transcription) > 0 {
		result.Language = "auto" // Could be enhanced to detect
	}
	
	// Calculate duration from last segment
	if len(result.Segments) > 0 {
		result.Duration = result.Segments[len(result.Segments)-1].End
	}
	
	return result, nil
}
```

---

## 4. Data Models

```go
// internal/ai/whisper/models.go

package whisper

import "time"

// TranscriptionResult is the output of transcription
type TranscriptionResult struct {
	Text      string    `json:"text"`
	Language  string    `json:"language,omitempty"`
	Duration  float64   `json:"duration"`
	Segments  []Segment `json:"segments,omitempty"`
}

// Segment represents a timed piece of transcription
type Segment struct {
	Start      float64 `json:"start"`       // seconds
	End        float64 `json:"end"`         // seconds
	Text       string  `json:"text"`
	Confidence float64 `json:"confidence,omitempty"`
}

// JobStatus represents transcription job state
type JobStatus string

const (
	JobPending    JobStatus = "pending"
	JobProcessing JobStatus = "processing"
	JobCompleted  JobStatus = "completed"
	JobFailed     JobStatus = "failed"
)

// TranscriptionJob represents a queued job
type TranscriptionJob struct {
	ID        string
	AudioPath string
	Format    string
	Status    JobStatus
	Error     string
	CreatedAt time.Time
	StartedAt time.Time
	
	// Internal fields
	result   chan *jobResult
	callback func(*TranscriptionResult, error)
}

type jobResult struct {
	transcription *TranscriptionResult
	err          error
}
```

---

## 5. HTTP API

### Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/audio/transcribe` | Upload and transcribe audio |
| POST | `/api/v1/audio/transcribe/async` | Async transcription (returns job ID) |
| GET | `/api/v1/audio/jobs/{id}` | Get job status |
| GET | `/api/v1/audio/jobs/{id}/result` | Get transcription result |
| WS | `/api/v1/audio/stream` | Real-time streaming transcription |

### Request/Response

```typescript
// POST /api/v1/audio/transcribe
// Content-Type: multipart/form-data

// Request
FormData {
  audio: File,          // Audio file (webm, mp3, wav, ogg)
  language?: string,    // Optional language hint (default: "auto")
}

// Response (200 OK)
{
  "text": "Full transcription text...",
  "language": "en",
  "duration": 45.5,
  "segments": [
    {
      "start": 0.0,
      "end": 2.5,
      "text": "Hello world",
      "confidence": 0.95
    }
  ]
}

// Error Response (4xx/5xx)
{
  "error": "transcription_failed",
  "message": "Audio format not supported",
  "code": "INVALID_FORMAT"
}
```

---

## 6. Database Schema

```sql
-- Transcription jobs table
CREATE TABLE IF NOT EXISTS transcription_jobs (
  id TEXT PRIMARY KEY,
  recording_id TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending', 'processing', 'completed', 'failed')),
  error TEXT,
  result_text TEXT,
  result_language TEXT,
  result_duration REAL,
  result_segments TEXT,  -- JSON
  attempts INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME,
  completed_at DATETIME,
  FOREIGN KEY (recording_id) REFERENCES audio_recordings(id)
);

CREATE INDEX idx_jobs_status ON transcription_jobs(status);
CREATE INDEX idx_jobs_recording ON transcription_jobs(recording_id);

-- Update audio_recordings to reference job
ALTER TABLE audio_recordings ADD COLUMN transcription_job_id TEXT
  REFERENCES transcription_jobs(id);
```

---

## 7. Deployment Requirements

### whisper.cpp Installation

```bash
# Clone and build
git clone https://github.com/ggerganov/whisper.cpp
cd whisper.cpp
make

# Download large-v3 model
bash ./models/download-ggml-model.sh large-v3

# Verify
./main -m models/ggml-large-v3.bin -f samples/jfk.wav
```

### FFmpeg Installation

```bash
# Ubuntu/Debian
apt-get install ffmpeg

# macOS
brew install ffmpeg

# Verify
ffmpeg -version
```

### Service Configuration

```yaml
# config.yaml
whisper:
  model_path: "/opt/whisper/models/ggml-large-v3.bin"
  binary_path: "/opt/whisper/main"
  temp_dir: "/tmp/whisper"
  max_concurrent: 2
  job_timeout: "5m"
  max_retries: 3
```

---

## 8. Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| WebM transcription | WebM/Opus input produces text | Critical |
| MP3 transcription | MP3 input produces text | Critical |
| Long audio | 10-minute audio processes correctly | High |
| Concurrent jobs | Multiple jobs process without deadlock | High |
| Job timeout | Long job times out gracefully | Medium |
| Retry on failure | Failed job retries correctly | Medium |
| Language detection | Auto-detect works for EN/ES/FR | Medium |
| Segment timestamps | Timestamps accurate within 0.5s | Low |

---

## Related Specs

- [Voice Resilience](./02-voice-resilience.md)
- [Audio Capture](./02-01-audio-capture.md)
- [Audio Sync](./02-03-audio-sync.md)
