# Phase 2: Voice Resilience

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Robust voice input system with local audio capture, failure-resilient streaming, and Local Whisper transcription via Go backend. Audio is always saved locally before any network operations.

---

## Sub-Specifications

| # | Component | Description |
|---|-----------|-------------|
| 01 | [Audio Capture](./02-01-audio-capture.md) | Browser MediaRecorder, WebM/Opus encoding, IndexedDB storage |
| 02 | [Transcription Service](./02-02-transcription-service.md) | Go backend with whisper.cpp integration |
| 03 | [Audio Sync](./02-03-audio-sync.md) | Resilient upload queue, chunked uploads, retry logic |
| 04 | [Voice UI Components](./02-04-voice-ui-components.md) | React components with waveform, transcription display |

**Cross-References:**
- [Voice Input](../05-voice-input/00-overview.md)
- [Offline-First Storage](./01-offline-first-storage.md)
- [AI Integration](../06-ai-integration/00-overview.md)

---

## 2.1 Audio Capture Architecture

### Recording Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                      User Speaks                                │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│               MediaRecorder API (WebM/Opus)                     │
│               - 16kHz sample rate                               │
│               - Mono channel                                    │
│               - 128kbps bitrate                                 │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Local Storage (First!)                        │
│  - Save audio blob to IndexedDB                                 │
│  - Generate unique recording ID                                 │
│  - Mark as pending transcription                                │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
          ┌─────────────────┴─────────────────┐
          │                                   │
     Online?                              Offline?
          │                                   │
          ▼                                   ▼
┌───────────────────┐               ┌───────────────────┐
│  Stream to Backend │               │  Queue for Later  │
│  WebSocket PCM     │               │  Retry on Online  │
└─────────┬─────────┘               └───────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────────────────────┐
│              Go Backend: Whisper Large v3                       │
│              - Local model (no external API)                    │
│              - Stream transcription chunks                      │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                Update Local Storage                             │
│  - Mark as synced                                               │
│  - Store transcription                                          │
│  - Optional: delete audio after 24h                             │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2.2 Audio Format Selection

### Recommended: WebM/Opus

| Format | Quality | Size | Browser Support | Whisper Compat |
|--------|---------|------|-----------------|----------------|
| WebM/Opus | Excellent | Small | Chrome, Firefox, Edge | ✓ |
| MP3 | Good | Medium | All | ✓ |
| WAV | Lossless | Large | All | ✓ |

**Decision:** Use WebM/Opus as primary format for:
- Superior compression (smaller files = faster sync)
- Excellent quality at 128kbps
- Native browser support (no encoding overhead)
- Whisper supports WebM input

**Fallback:** MP3 for Safari (via AudioEncoder API)

---

## 2.3 Frontend Audio Recorder

```typescript
// lib/audio/AudioRecorder.ts

import { VersionedStorage } from '@/lib/storage/VersionedStorage';

export interface AudioRecording {
  id: string;
  blob: Blob;
  format: 'webm' | 'mp3' | 'wav';
  duration: number;
  timestamp: number;
  transcription?: string;
  status: 'recording' | 'saved' | 'syncing' | 'synced' | 'failed';
}

interface RecorderOptions {
  onProgress?: (amplitude: number) => void;
  onComplete?: (recording: AudioRecording) => void;
  onError?: (error: Error) => void;
}

export class AudioRecorder {
  private mediaRecorder: MediaRecorder | null = null;
  private audioContext: AudioContext | null = null;
  private analyser: AnalyserNode | null = null;
  private chunks: Blob[] = [];
  private startTime = 0;
  private animationFrame: number | null = null;
  private storage: VersionedStorage;
  
  constructor(storage: VersionedStorage) {
    this.storage = storage;
  }
  
  /**
   * Start recording audio
   */
  async start(options: RecorderOptions = {}): Promise<void> {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: {
          channelCount: 1,
          sampleRate: 16000,
          echoCancellation: true,
          noiseSuppression: true,
        },
      });
      
      // Setup audio analysis for visualization
      this.audioContext = new AudioContext();
      const source = this.audioContext.createMediaStreamSource(stream);
      this.analyser = this.audioContext.createAnalyser();
      this.analyser.fftSize = 256;
      source.connect(this.analyser);
      
      // Determine best supported format
      const mimeType = this.getSupportedMimeType();
      
      this.mediaRecorder = new MediaRecorder(stream, {
        mimeType,
        audioBitsPerSecond: 128000,
      });
      
      this.chunks = [];
      this.startTime = Date.now();
      
      this.mediaRecorder.ondataavailable = (e) => {
        if (e.data.size > 0) {
          this.chunks.push(e.data);
        }
      };
      
      this.mediaRecorder.onstop = async () => {
        const blob = new Blob(this.chunks, { type: mimeType });
        const recording = await this.saveRecording(blob, mimeType);
        options.onComplete?.(recording);
        this.cleanup(stream);
      };
      
      this.mediaRecorder.onerror = (e) => {
        options.onError?.(new Error('Recording failed'));
        this.cleanup(stream);
      };
      
      // Start recording
      this.mediaRecorder.start(1000); // 1 second chunks
      
      // Start amplitude monitoring
      if (options.onProgress) {
        this.monitorAmplitude(options.onProgress);
      }
    } catch (error) {
      options.onError?.(error instanceof Error ? error : new Error('Microphone access denied'));
    }
  }
  
  /**
   * Stop recording
   */
  stop(): void {
    if (this.mediaRecorder?.state === 'recording') {
      this.mediaRecorder.stop();
    }
    if (this.animationFrame) {
      cancelAnimationFrame(this.animationFrame);
    }
  }
  
  /**
   * Get supported MIME type
   */
  private getSupportedMimeType(): string {
    const types = [
      'audio/webm;codecs=opus',
      'audio/webm',
      'audio/mp4',
      'audio/ogg;codecs=opus',
    ];
    
    for (const type of types) {
      if (MediaRecorder.isTypeSupported(type)) {
        return type;
      }
    }
    
    return 'audio/webm'; // Fallback
  }
  
  /**
   * Save recording to IndexedDB via storage layer
   */
  private async saveRecording(blob: Blob, mimeType: string): Promise<AudioRecording> {
    const id = crypto.randomUUID();
    const format = mimeType.includes('webm') ? 'webm' : 
                   mimeType.includes('mp4') ? 'mp3' : 'wav';
    
    const recording: AudioRecording = {
      id,
      blob,
      format,
      duration: Date.now() - this.startTime,
      timestamp: Date.now(),
      status: 'saved',
    };
    
    // Save to IndexedDB for large blob storage
    await this.saveBlobToIndexedDB(id, blob);
    
    // Save metadata to localStorage
    this.storage.set(`audio:${id}`, {
      ...recording,
      blob: undefined, // Don't store blob in localStorage
    }, false);
    
    return recording;
  }
  
  /**
   * Save blob to IndexedDB
   */
  private async saveBlobToIndexedDB(id: string, blob: Blob): Promise<void> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open('specmgmt_audio', 1);
      
      request.onupgradeneeded = (e) => {
        const db = (e.target as IDBOpenDBRequest).result;
        if (!db.objectStoreNames.contains('recordings')) {
          db.createObjectStore('recordings', { keyPath: 'id' });
        }
      };
      
      request.onsuccess = (e) => {
        const db = (e.target as IDBOpenDBRequest).result;
        const tx = db.transaction('recordings', 'readwrite');
        const store = tx.objectStore('recordings');
        store.put({ id, blob, timestamp: Date.now() });
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
      };
      
      request.onerror = () => reject(request.error);
    });
  }
  
  /**
   * Monitor audio amplitude for visualization
   */
  private monitorAmplitude(callback: (amplitude: number) => void): void {
    if (!this.analyser) return;
    
    const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
    
    const update = () => {
      if (!this.analyser) return;
      
      this.analyser.getByteFrequencyData(dataArray);
      const average = dataArray.reduce((a, b) => a + b) / dataArray.length;
      const normalized = average / 255;
      
      callback(normalized);
      this.animationFrame = requestAnimationFrame(update);
    };
    
    update();
  }
  
  /**
   * Cleanup resources
   */
  private cleanup(stream: MediaStream): void {
    stream.getTracks().forEach(track => track.stop());
    this.audioContext?.close();
    this.audioContext = null;
    this.analyser = null;
    this.mediaRecorder = null;
  }
}
```

---

## 2.4 Go Backend: Whisper Integration

### Whisper Service

```go
// internal/ai/whisper/service.go

package whisper

import (
	"bytes"
	"context"
	"fmt"
	"io"
	"os/exec"
	"sync"
)

type TranscriptionResult struct {
	Text      string  `json:"text"`
	Language  string  `json:"language"`
	Duration  float64 `json:"duration"`
	Segments  []Segment `json:"segments,omitempty"`
}

type Segment struct {
	Start float64 `json:"start"`
	End   float64 `json:"end"`
	Text  string  `json:"text"`
}

type Service struct {
	modelPath string
	mutex     sync.Mutex
}

func NewService(modelPath string) *Service {
	return &Service{
		modelPath: modelPath,
	}
}

// Transcribe audio file using whisper.cpp
func (s *Service) Transcribe(ctx context.Context, audioData []byte, format string) (*TranscriptionResult, error) {
	s.mutex.Lock()
	defer s.mutex.Unlock()
	
	// Convert to WAV if needed (whisper.cpp works best with WAV)
	wavData, err := s.convertToWav(audioData, format)
	if err != nil {
		return nil, fmt.Errorf("audio conversion failed: %w", err)
	}
	
	// Run whisper.cpp
	cmd := exec.CommandContext(ctx, "./whisper",
		"--model", s.modelPath,
		"--language", "auto",
		"--output-json",
		"-f", "-", // Read from stdin
	)
	
	cmd.Stdin = bytes.NewReader(wavData)
	
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr
	
	if err := cmd.Run(); err != nil {
		return nil, fmt.Errorf("whisper failed: %w, stderr: %s", err, stderr.String())
	}
	
	return s.parseOutput(stdout.Bytes())
}

// TranscribeStream handles streaming transcription for real-time
func (s *Service) TranscribeStream(
	ctx context.Context,
	audioStream io.Reader,
	onSegment func(Segment),
) (*TranscriptionResult, error) {
	// Buffer audio chunks
	var buffer bytes.Buffer
	chunk := make([]byte, 16000*2) // 1 second of 16kHz 16-bit audio
	
	var allSegments []Segment
	var fullText string
	
	for {
		select {
		case <-ctx.Done():
			return nil, ctx.Err()
		default:
		}
		
		n, err := audioStream.Read(chunk)
		if err == io.EOF {
			break
		}
		if err != nil {
			return nil, err
		}
		
		buffer.Write(chunk[:n])
		
		// Process when we have 5 seconds of audio
		if buffer.Len() >= 16000*2*5 {
			result, err := s.Transcribe(ctx, buffer.Bytes(), "pcm")
			if err != nil {
				continue
			}
			
			for _, seg := range result.Segments {
				onSegment(seg)
				allSegments = append(allSegments, seg)
			}
			fullText += result.Text
			buffer.Reset()
		}
	}
	
	// Process remaining audio
	if buffer.Len() > 0 {
		result, err := s.Transcribe(ctx, buffer.Bytes(), "pcm")
		if err == nil {
			for _, seg := range result.Segments {
				onSegment(seg)
				allSegments = append(allSegments, seg)
			}
			fullText += result.Text
		}
	}
	
	return &TranscriptionResult{
		Text:     fullText,
		Segments: allSegments,
	}, nil
}

func (s *Service) convertToWav(data []byte, format string) ([]byte, error) {
	if format == "wav" || format == "pcm" {
		return data, nil
	}
	
	// Use ffmpeg for conversion
	cmd := exec.Command("ffmpeg",
		"-i", "pipe:0",
		"-ar", "16000",
		"-ac", "1",
		"-f", "wav",
		"pipe:1",
	)
	
	cmd.Stdin = bytes.NewReader(data)
	
	var stdout bytes.Buffer
	cmd.Stdout = &stdout
	
	if err := cmd.Run(); err != nil {
		return nil, err
	}
	
	return stdout.Bytes(), nil
}

func (s *Service) parseOutput(data []byte) (*TranscriptionResult, error) {
	// Parse whisper.cpp JSON output
	// Implementation depends on whisper.cpp output format
	return &TranscriptionResult{
		Text: string(data), // Simplified
	}, nil
}
```

### HTTP Handler

```go
// internal/api/handlers/audio.go

package handlers

import (
	"encoding/json"
	"io"
	"net/http"
	
	"github.com/go-chi/chi/v5"
	"specmgmt/internal/ai/whisper"
)

type AudioHandler struct {
	whisper *whisper.Service
}

func NewAudioHandler(w *whisper.Service) *AudioHandler {
	return &AudioHandler{whisper: w}
}

// POST /api/v1/audio/transcribe
func (h *AudioHandler) Transcribe(w http.ResponseWriter, r *http.Request) {
	// Parse multipart form
	if err := r.ParseMultipartForm(50 << 20); err != nil { // 50MB max
		http.Error(w, "File too large", http.StatusBadRequest)
		return
	}
	
	file, header, err := r.FormFile("audio")
	if err != nil {
		http.Error(w, "Missing audio file", http.StatusBadRequest)
		return
	}
	defer file.Close()
	
	// Read audio data
	audioData, err := io.ReadAll(file)
	if err != nil {
		http.Error(w, "Failed to read audio", http.StatusInternalServerError)
		return
	}
	
	// Detect format from content type or filename
	format := detectAudioFormat(header.Header.Get("Content-Type"), header.Filename)
	
	// Transcribe
	result, err := h.whisper.Transcribe(r.Context(), audioData, format)
	if err != nil {
		http.Error(w, "Transcription failed: "+err.Error(), http.StatusInternalServerError)
		return
	}
	
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(result)
}

// WebSocket endpoint for streaming transcription
func (h *AudioHandler) TranscribeStream(w http.ResponseWriter, r *http.Request) {
	// Upgrade to WebSocket and handle streaming
	// Implementation for real-time transcription
}

func detectAudioFormat(contentType, filename string) string {
	switch contentType {
	case "audio/webm":
		return "webm"
	case "audio/mp3", "audio/mpeg":
		return "mp3"
	case "audio/wav":
		return "wav"
	}
	
	// Fallback to extension
	if len(filename) > 4 {
		ext := filename[len(filename)-4:]
		switch ext {
		case ".webm":
			return "webm"
		case ".mp3":
			return "mp3"
		case ".wav":
			return "wav"
		}
	}
	
	return "webm" // Default
}

// Routes
func (h *AudioHandler) Routes() chi.Router {
	r := chi.NewRouter()
	r.Post("/transcribe", h.Transcribe)
	r.HandleFunc("/stream", h.TranscribeStream)
	return r
}
```

---

## 2.5 React Voice Input Component

```typescript
// components/ai/VoiceInputResilient.tsx

import { useState, useCallback, useRef, useEffect } from 'react';
import { Mic, MicOff, Loader2, AlertCircle, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { AudioRecorder, AudioRecording } from '@/lib/audio/AudioRecorder';
import { useOfflineStorage } from '@/hooks/useOfflineStorage';
import { useToast } from '@/hooks/use-toast';

interface VoiceInputProps {
  onTranscriptionComplete: (text: string, recording: AudioRecording) => void;
  onTranscriptionProgress?: (partial: string) => void;
  disabled?: boolean;
}

type RecordingState = 'idle' | 'recording' | 'processing' | 'transcribing' | 'complete' | 'error';

export function VoiceInputResilient({
  onTranscriptionComplete,
  onTranscriptionProgress,
  disabled,
}: VoiceInputProps) {
  const [state, setState] = useState<RecordingState>('idle');
  const [amplitude, setAmplitude] = useState(0);
  const [duration, setDuration] = useState(0);
  const [error, setError] = useState<string | null>(null);
  
  const { storage, isOnline, saveWithSync } = useOfflineStorage();
  const { toast } = useToast();
  
  const recorderRef = useRef<AudioRecorder | null>(null);
  const durationInterval = useRef<NodeJS.Timeout | null>(null);
  
  // Initialize recorder
  useEffect(() => {
    recorderRef.current = new AudioRecorder(storage);
    return () => {
      recorderRef.current = null;
    };
  }, [storage]);
  
  /**
   * Start recording
   */
  const startRecording = useCallback(async () => {
    if (!recorderRef.current) return;
    
    setError(null);
    setState('recording');
    setDuration(0);
    
    // Start duration timer
    const start = Date.now();
    durationInterval.current = setInterval(() => {
      setDuration(Date.now() - start);
    }, 100);
    
    await recorderRef.current.start({
      onProgress: setAmplitude,
      onComplete: async (recording) => {
        clearInterval(durationInterval.current!);
        setState('processing');
        
        // Save to sync queue
        saveWithSync(`audio:${recording.id}`, recording, 'audio', 'create');
        
        // Attempt transcription
        await transcribeRecording(recording);
      },
      onError: (err) => {
        clearInterval(durationInterval.current!);
        setError(err.message);
        setState('error');
      },
    });
  }, [saveWithSync]);
  
  /**
   * Stop recording
   */
  const stopRecording = useCallback(() => {
    recorderRef.current?.stop();
  }, []);
  
  /**
   * Transcribe recording
   */
  const transcribeRecording = async (recording: AudioRecording) => {
    setState('transcribing');
    
    if (!isOnline) {
      // Queue for later transcription
      toast({
        title: 'Offline',
        description: 'Audio saved. Transcription will happen when you\'re back online.',
      });
      setState('complete');
      return;
    }
    
    try {
      const formData = new FormData();
      formData.append('audio', recording.blob, `recording.${recording.format}`);
      
      const response = await fetch('/api/v1/audio/transcribe', {
        method: 'POST',
        body: formData,
      });
      
      if (!response.ok) {
        throw new Error('Transcription failed');
      }
      
      const result = await response.json();
      
      // Update recording with transcription
      const updatedRecording: AudioRecording = {
        ...recording,
        transcription: result.text,
        status: 'synced',
      };
      
      storage.set(`audio:${recording.id}`, updatedRecording, true);
      
      setState('complete');
      onTranscriptionComplete(result.text, updatedRecording);
      
    } catch (err) {
      setError('Transcription failed. Audio saved for retry.');
      setState('error');
      
      toast({
        variant: 'destructive',
        title: 'Transcription Failed',
        description: 'Your recording is saved. We\'ll retry when possible.',
      });
    }
  };
  
  /**
   * Format duration display
   */
  const formatDuration = (ms: number): string => {
    const seconds = Math.floor(ms / 1000);
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
  };
  
  return (
    <div className="flex flex-col items-center gap-4">
      {/* Main button */}
      <Button
        size="lg"
        variant={state === 'recording' ? 'destructive' : 'default'}
        className={cn(
          'relative h-16 w-16 rounded-full',
          state === 'recording' && 'animate-pulse'
        )}
        disabled={disabled || state === 'processing' || state === 'transcribing'}
        onClick={state === 'recording' ? stopRecording : startRecording}
      >
        {state === 'idle' && <Mic className="h-6 w-6" />}
        {state === 'recording' && <MicOff className="h-6 w-6" />}
        {(state === 'processing' || state === 'transcribing') && (
          <Loader2 className="h-6 w-6 animate-spin" />
        )}
        {state === 'complete' && <Check className="h-6 w-6" />}
        {state === 'error' && <AlertCircle className="h-6 w-6" />}
        
        {/* Amplitude ring */}
        {state === 'recording' && (
          <span
            className="absolute inset-0 rounded-full border-4 border-primary opacity-50"
            style={{
              transform: `scale(${1 + amplitude * 0.5})`,
              transition: 'transform 100ms ease-out',
            }}
          />
        )}
      </Button>
      
      {/* Status text */}
      <div className="text-center">
        {state === 'idle' && (
          <p className="text-sm text-muted-foreground">Click to start recording</p>
        )}
        {state === 'recording' && (
          <p className="text-sm font-medium">{formatDuration(duration)}</p>
        )}
        {state === 'processing' && (
          <p className="text-sm text-muted-foreground">Saving...</p>
        )}
        {state === 'transcribing' && (
          <p className="text-sm text-muted-foreground">Transcribing...</p>
        )}
        {state === 'complete' && (
          <p className="text-sm text-success">Done!</p>
        )}
        {state === 'error' && (
          <p className="text-sm text-destructive">{error}</p>
        )}
      </div>
      
      {/* Offline indicator */}
      {!isOnline && (
        <p className="text-xs text-muted-foreground">
          Offline mode - recordings will sync later
        </p>
      )}
    </div>
  );
}
```

---

## 2.6 Database Schema

```sql
-- Audio recordings table (SQLite)
CREATE TABLE IF NOT EXISTS audio_recordings (
  id TEXT PRIMARY KEY,
  project_id TEXT NOT NULL,
  session_id TEXT,
  file_path TEXT NOT NULL,
  format TEXT NOT NULL CHECK (format IN ('webm', 'mp3', 'wav')),
  duration_ms INTEGER,
  file_size_bytes INTEGER,
  transcription TEXT,
  transcription_status TEXT DEFAULT 'pending' 
    CHECK (transcription_status IN ('pending', 'processing', 'completed', 'failed')),
  transcription_error TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  transcribed_at DATETIME,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE INDEX idx_audio_status ON audio_recordings(transcription_status);
CREATE INDEX idx_audio_project ON audio_recordings(project_id);
```

---

## 2.7 Technical Requirements

### Audio Specifications
- Sample rate: 16kHz
- Channels: Mono
- Bitrate: 128kbps
- Format: WebM/Opus (primary), MP3 (fallback)
- Max duration: 10 minutes
- Max file size: 50MB

### Whisper Model
- Model: `whisper-large-v3`
- Location: Local (Go binary with whisper.cpp)
- Languages: Auto-detect

### Error Recovery
- Audio always saved before transcription attempt
- Failed transcriptions queued for retry
- Max retries: 10
- Retry interval: 5 seconds (from sync queue)

---

## 2.8 Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| Record offline | Audio saved to IndexedDB without network | Critical |
| Transcribe online | Successful transcription updates recording | Critical |
| Retry on reconnect | Pending transcriptions process when online | Critical |
| Large audio | 10-minute recording handled correctly | High |
| Format fallback | MP3 used when WebM unsupported | Medium |
| Amplitude display | Waveform visualization accurate | Low |

---

## Related Specs

- [Voice Input](../05-voice-input/00-overview.md)
- [Offline-First Storage](./01-offline-first-storage.md)
- [AI Integration](../06-ai-integration/00-overview.md)
