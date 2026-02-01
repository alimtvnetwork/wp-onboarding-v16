# Phase 2.3: Audio Sync & Recovery

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Reliable audio synchronization between frontend IndexedDB and backend storage with failure recovery, retry logic, and offline queue management.

**Cross-References:**
- [Voice Resilience](./02-voice-resilience.md)
- [Audio Capture](./02-01-audio-capture.md)
- [Sync Queue](./01-02-sync-queue.md)

---

## 1. Sync Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Frontend                                         │
│                                                                          │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐   │
│  │   IndexedDB      │    │   AudioSyncQueue │    │   SyncManager    │   │
│  │   (Blob Store)   │◄───│   (Operations)   │◄───│   (Orchestrator) │   │
│  └────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘   │
│           │                       │                       │              │
│           │                       │                       │              │
│           ▼                       ▼                       ▼              │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                     Network Layer                                │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  Online Detection (navigator.onLine + fetch probe)      │    │    │
│  │  │  Retry Strategy (exponential backoff + jitter)          │    │    │
│  │  │  Progress Tracking (upload %)                           │    │    │
│  │  │  Resumable Upload (chunk-based for large files)         │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  └──────────────────────────────┬──────────────────────────────────┘    │
│                                 │                                        │
└─────────────────────────────────┼───────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         Backend (Go)                                     │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  POST /api/v1/audio/upload                                      │    │
│  │  ├── Multipart upload (single file ≤50MB)                       │    │
│  │  ├── Chunked upload (large files, resumable)                    │    │
│  │  └── Returns: upload_id, status                                 │    │
│  └──────────────────────────────┬──────────────────────────────────┘    │
│                                 │                                        │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  File Storage                                                    │    │
│  │  ├── Path: /data/audio/{project_id}/{recording_id}.{ext}       │    │
│  │  ├── Metadata in SQLite                                         │    │
│  │  └── Auto-cleanup after 30 days (configurable)                  │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Audio Sync Queue

```typescript
// lib/audio/AudioSyncQueue.ts

import { IndexedDBBlob } from '@/lib/storage/IndexedDBBlob';
import { VersionedStorage } from '@/lib/storage/VersionedStorage';

/**
 * Audio-specific sync operation
 */
export interface AudioSyncOperation {
  id: string;
  recordingId: string;
  type: 'upload' | 'transcribe' | 'delete';
  status: 'pending' | 'uploading' | 'transcribing' | 'completed' | 'failed';
  priority: number;         // 0-10, higher = more urgent
  attempts: number;
  maxAttempts: number;
  lastError?: string;
  progress?: number;        // 0-100 for uploads
  createdAt: number;
  lastAttemptAt?: number;
  completedAt?: number;
  
  // Upload-specific
  uploadId?: string;        // Server-assigned for resumable
  uploadedBytes?: number;   // For resumable uploads
  totalBytes?: number;
}

/**
 * Manages audio upload queue
 */
export class AudioSyncQueue {
  private queue: Map<string, AudioSyncOperation> = new Map();
  private processing = false;
  private retryTimeouts: Map<string, ReturnType<typeof setTimeout>> = new Map();
  
  constructor(
    private blobStore: IndexedDBBlob,
    private storage: VersionedStorage,
    private apiBase: string
  ) {
    // Restore queue from storage on init
    this.restoreQueue();
    
    // Listen for online events
    window.addEventListener('online', () => this.processQueue());
  }
  
  /**
   * Add recording to upload queue
   */
  async enqueue(
    recordingId: string,
    type: 'upload' | 'transcribe' = 'upload',
    priority = 5
  ): Promise<string> {
    const id = `audio_${type}_${recordingId}_${Date.now()}`;
    
    const operation: AudioSyncOperation = {
      id,
      recordingId,
      type,
      status: 'pending',
      priority,
      attempts: 0,
      maxAttempts: 10,
      createdAt: Date.now(),
    };
    
    // Get blob size for progress tracking
    const blob = await this.blobStore.get(recordingId);
    if (blob && type === 'upload') {
      operation.totalBytes = blob.size;
      operation.uploadedBytes = 0;
    }
    
    this.queue.set(id, operation);
    this.persistQueue();
    
    // Start processing if online
    if (navigator.onLine) {
      this.processQueue();
    }
    
    return id;
  }
  
  /**
   * Get operation status
   */
  getOperation(id: string): AudioSyncOperation | undefined {
    return this.queue.get(id);
  }
  
  /**
   * Get all pending operations for a recording
   */
  getRecordingOperations(recordingId: string): AudioSyncOperation[] {
    return Array.from(this.queue.values())
      .filter(op => op.recordingId === recordingId);
  }
  
  /**
   * Cancel operation
   */
  cancel(id: string): boolean {
    const op = this.queue.get(id);
    if (!op || op.status === 'completed') return false;
    
    // Clear retry timeout
    const timeout = this.retryTimeouts.get(id);
    if (timeout) {
      clearTimeout(timeout);
      this.retryTimeouts.delete(id);
    }
    
    this.queue.delete(id);
    this.persistQueue();
    return true;
  }
  
  /**
   * Process queue
   */
  async processQueue(): Promise<void> {
    if (this.processing || !navigator.onLine) return;
    
    this.processing = true;
    
    try {
      // Get pending operations sorted by priority
      const pending = Array.from(this.queue.values())
        .filter(op => op.status === 'pending' || op.status === 'failed')
        .filter(op => op.attempts < op.maxAttempts)
        .sort((a, b) => b.priority - a.priority);
      
      for (const operation of pending) {
        // Check if should back off
        if (this.shouldBackoff(operation)) {
          this.scheduleRetry(operation);
          continue;
        }
        
        await this.processOperation(operation);
      }
    } finally {
      this.processing = false;
    }
  }
  
  /**
   * Process single operation
   */
  private async processOperation(operation: AudioSyncOperation): Promise<void> {
    operation.attempts++;
    operation.lastAttemptAt = Date.now();
    operation.status = operation.type === 'upload' ? 'uploading' : 'transcribing';
    this.persistQueue();
    
    try {
      switch (operation.type) {
        case 'upload':
          await this.performUpload(operation);
          break;
        case 'transcribe':
          await this.performTranscription(operation);
          break;
        case 'delete':
          await this.performDelete(operation);
          break;
      }
      
      operation.status = 'completed';
      operation.completedAt = Date.now();
      
    } catch (error) {
      operation.status = 'failed';
      operation.lastError = error instanceof Error ? error.message : 'Unknown error';
      
      // Schedule retry
      if (operation.attempts < operation.maxAttempts) {
        this.scheduleRetry(operation);
      }
    }
    
    this.persistQueue();
  }
  
  /**
   * Perform upload
   */
  private async performUpload(operation: AudioSyncOperation): Promise<void> {
    const blob = await this.blobStore.get(operation.recordingId);
    if (!blob) {
      throw new Error('Recording not found in IndexedDB');
    }
    
    // Get recording metadata
    const meta = this.storage.get<any>(`audio:${operation.recordingId}`);
    if (!meta) {
      throw new Error('Recording metadata not found');
    }
    
    // Determine upload strategy based on size
    if (blob.size > 5 * 1024 * 1024) { // > 5MB = chunked
      await this.chunkedUpload(operation, blob, meta);
    } else {
      await this.simpleUpload(operation, blob, meta);
    }
  }
  
  /**
   * Simple multipart upload for small files
   */
  private async simpleUpload(
    operation: AudioSyncOperation,
    blob: Blob,
    meta: any
  ): Promise<void> {
    const formData = new FormData();
    formData.append('audio', blob, `${operation.recordingId}.${meta.format}`);
    formData.append('metadata', JSON.stringify(meta));
    
    const response = await fetch(`${this.apiBase}/audio/upload`, {
      method: 'POST',
      body: formData,
    });
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.message || `Upload failed: ${response.status}`);
    }
    
    // Mark as synced in blob store
    await this.blobStore.markSynced(operation.recordingId);
    
    // Update metadata
    meta.status = 'synced';
    this.storage.set(`audio:${operation.recordingId}`, meta, true);
  }
  
  /**
   * Chunked resumable upload for large files
   */
  private async chunkedUpload(
    operation: AudioSyncOperation,
    blob: Blob,
    meta: any
  ): Promise<void> {
    const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
    const totalChunks = Math.ceil(blob.size / CHUNK_SIZE);
    
    // Initialize upload if no uploadId
    if (!operation.uploadId) {
      const initResponse = await fetch(`${this.apiBase}/audio/upload/init`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          recordingId: operation.recordingId,
          fileName: `${operation.recordingId}.${meta.format}`,
          fileSize: blob.size,
          mimeType: meta.mimeType,
          totalChunks,
        }),
      });
      
      if (!initResponse.ok) {
        throw new Error('Failed to initialize upload');
      }
      
      const { uploadId } = await initResponse.json();
      operation.uploadId = uploadId;
      this.persistQueue();
    }
    
    // Calculate starting chunk
    const startChunk = Math.floor((operation.uploadedBytes || 0) / CHUNK_SIZE);
    
    // Upload remaining chunks
    for (let i = startChunk; i < totalChunks; i++) {
      const start = i * CHUNK_SIZE;
      const end = Math.min(start + CHUNK_SIZE, blob.size);
      const chunk = blob.slice(start, end);
      
      const formData = new FormData();
      formData.append('chunk', chunk);
      formData.append('chunkIndex', String(i));
      formData.append('uploadId', operation.uploadId!);
      
      const response = await fetch(`${this.apiBase}/audio/upload/chunk`, {
        method: 'POST',
        body: formData,
      });
      
      if (!response.ok) {
        throw new Error(`Chunk ${i} upload failed`);
      }
      
      // Update progress
      operation.uploadedBytes = end;
      operation.progress = Math.round((end / blob.size) * 100);
      this.persistQueue();
    }
    
    // Complete upload
    const completeResponse = await fetch(`${this.apiBase}/audio/upload/complete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        uploadId: operation.uploadId,
        metadata: meta,
      }),
    });
    
    if (!completeResponse.ok) {
      throw new Error('Failed to complete upload');
    }
    
    // Mark as synced
    await this.blobStore.markSynced(operation.recordingId);
    meta.status = 'synced';
    this.storage.set(`audio:${operation.recordingId}`, meta, true);
  }
  
  /**
   * Request transcription
   */
  private async performTranscription(operation: AudioSyncOperation): Promise<void> {
    const response = await fetch(`${this.apiBase}/audio/transcribe`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        recordingId: operation.recordingId,
      }),
    });
    
    if (!response.ok) {
      throw new Error('Transcription request failed');
    }
    
    const result = await response.json();
    
    // Update metadata with transcription
    const meta = this.storage.get<any>(`audio:${operation.recordingId}`);
    if (meta) {
      meta.transcription = result;
      meta.status = 'completed';
      this.storage.set(`audio:${operation.recordingId}`, meta, true);
    }
  }
  
  /**
   * Delete from server
   */
  private async performDelete(operation: AudioSyncOperation): Promise<void> {
    const response = await fetch(
      `${this.apiBase}/audio/${operation.recordingId}`,
      { method: 'DELETE' }
    );
    
    if (!response.ok && response.status !== 404) {
      throw new Error('Delete failed');
    }
    
    // Clean up local storage
    await this.blobStore.delete(operation.recordingId);
    this.storage.remove(`audio:${operation.recordingId}`);
  }
  
  /**
   * Calculate if should backoff
   */
  private shouldBackoff(operation: AudioSyncOperation): boolean {
    if (!operation.lastAttemptAt) return false;
    
    const backoffMs = this.calculateBackoff(operation.attempts);
    const elapsed = Date.now() - operation.lastAttemptAt;
    
    return elapsed < backoffMs;
  }
  
  /**
   * Calculate exponential backoff with jitter
   */
  private calculateBackoff(attempts: number): number {
    const baseMs = 1000;
    const maxMs = 5 * 60 * 1000; // 5 minutes max
    
    const exponential = Math.min(baseMs * Math.pow(2, attempts), maxMs);
    const jitter = exponential * 0.1 * Math.random();
    
    return exponential + jitter;
  }
  
  /**
   * Schedule retry
   */
  private scheduleRetry(operation: AudioSyncOperation): void {
    const delay = this.calculateBackoff(operation.attempts);
    
    const timeout = setTimeout(() => {
      this.retryTimeouts.delete(operation.id);
      this.processQueue();
    }, delay);
    
    this.retryTimeouts.set(operation.id, timeout);
  }
  
  /**
   * Persist queue to storage
   */
  private persistQueue(): void {
    const entries = Array.from(this.queue.entries());
    this.storage.set('audioSyncQueue', entries, false);
  }
  
  /**
   * Restore queue from storage
   */
  private restoreQueue(): void {
    const entries = this.storage.get<[string, AudioSyncOperation][]>('audioSyncQueue');
    if (entries) {
      this.queue = new Map(entries);
      
      // Reset in-progress operations to pending
      for (const op of this.queue.values()) {
        if (op.status === 'uploading' || op.status === 'transcribing') {
          op.status = 'pending';
        }
      }
      this.persistQueue();
    }
  }
}
```

---

## 3. React Hook

```typescript
// hooks/useAudioSync.ts

import { useState, useEffect, useCallback, useMemo } from 'react';
import { AudioSyncQueue, AudioSyncOperation } from '@/lib/audio/AudioSyncQueue';
import { useOfflineStorage } from './useOfflineStorage';

interface AudioSyncState {
  isOnline: boolean;
  isSyncing: boolean;
  pendingCount: number;
  failedCount: number;
  operations: AudioSyncOperation[];
}

export function useAudioSync() {
  const { storage, blobStore, apiBase } = useOfflineStorage();
  const [state, setState] = useState<AudioSyncState>({
    isOnline: navigator.onLine,
    isSyncing: false,
    pendingCount: 0,
    failedCount: 0,
    operations: [],
  });
  
  // Create queue instance
  const queue = useMemo(() => {
    if (!storage || !blobStore) return null;
    return new AudioSyncQueue(blobStore, storage, apiBase);
  }, [storage, blobStore, apiBase]);
  
  // Update state from queue
  const refreshState = useCallback(() => {
    if (!queue) return;
    
    const allOps = Array.from((queue as any).queue.values()) as AudioSyncOperation[];
    
    setState({
      isOnline: navigator.onLine,
      isSyncing: allOps.some(op => 
        op.status === 'uploading' || op.status === 'transcribing'
      ),
      pendingCount: allOps.filter(op => op.status === 'pending').length,
      failedCount: allOps.filter(op => op.status === 'failed').length,
      operations: allOps,
    });
  }, [queue]);
  
  // Listen for online/offline events
  useEffect(() => {
    const handleOnline = () => {
      setState(s => ({ ...s, isOnline: true }));
      queue?.processQueue();
    };
    
    const handleOffline = () => {
      setState(s => ({ ...s, isOnline: false }));
    };
    
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, [queue]);
  
  // Refresh state periodically
  useEffect(() => {
    const interval = setInterval(refreshState, 1000);
    return () => clearInterval(interval);
  }, [refreshState]);
  
  /**
   * Queue recording for upload
   */
  const uploadRecording = useCallback(async (recordingId: string) => {
    if (!queue) throw new Error('Queue not initialized');
    
    const id = await queue.enqueue(recordingId, 'upload', 5);
    refreshState();
    return id;
  }, [queue, refreshState]);
  
  /**
   * Queue recording for transcription
   */
  const transcribeRecording = useCallback(async (recordingId: string) => {
    if (!queue) throw new Error('Queue not initialized');
    
    const id = await queue.enqueue(recordingId, 'transcribe', 8);
    refreshState();
    return id;
  }, [queue, refreshState]);
  
  /**
   * Cancel operation
   */
  const cancelOperation = useCallback((operationId: string) => {
    queue?.cancel(operationId);
    refreshState();
  }, [queue, refreshState]);
  
  /**
   * Retry all failed
   */
  const retryAllFailed = useCallback(() => {
    if (!queue) return;
    
    for (const op of state.operations) {
      if (op.status === 'failed') {
        op.status = 'pending';
        op.attempts = 0;
      }
    }
    
    queue.processQueue();
    refreshState();
  }, [queue, state.operations, refreshState]);
  
  /**
   * Get progress for specific recording
   */
  const getRecordingProgress = useCallback((recordingId: string): number | null => {
    const op = state.operations.find(
      o => o.recordingId === recordingId && o.type === 'upload'
    );
    return op?.progress ?? null;
  }, [state.operations]);
  
  return {
    ...state,
    uploadRecording,
    transcribeRecording,
    cancelOperation,
    retryAllFailed,
    getRecordingProgress,
  };
}
```

---

## 4. Backend Upload Handlers

```go
// internal/api/handlers/audio_upload.go

package handlers

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"sync"
	"time"
)

// In-memory upload state (use Redis in production)
var uploadSessions = struct {
	sync.RWMutex
	sessions map[string]*UploadSession
}{
	sessions: make(map[string]*UploadSession),
}

type UploadSession struct {
	ID          string
	RecordingID string
	FileName    string
	FileSize    int64
	MimeType    string
	TotalChunks int
	Chunks      map[int]bool
	TempDir     string
}

// InitUpload initializes a chunked upload
func (h *AudioHandler) InitUpload(w http.ResponseWriter, r *http.Request) {
	var req struct {
		RecordingID string `json:"recordingId"`
		FileName    string `json:"fileName"`
		FileSize    int64  `json:"fileSize"`
		MimeType    string `json:"mimeType"`
		TotalChunks int    `json:"totalChunks"`
	}
	
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request", http.StatusBadRequest)
		return
	}
	
	// Generate upload ID
	hash := sha256.Sum256([]byte(fmt.Sprintf("%s-%d", req.RecordingID, time.Now().UnixNano())))
	uploadID := hex.EncodeToString(hash[:8])
	
	// Create temp directory
	tempDir := filepath.Join(h.config.TempDir, uploadID)
	if err := os.MkdirAll(tempDir, 0755); err != nil {
		http.Error(w, "Failed to create temp dir", http.StatusInternalServerError)
		return
	}
	
	session := &UploadSession{
		ID:          uploadID,
		RecordingID: req.RecordingID,
		FileName:    req.FileName,
		FileSize:    req.FileSize,
		MimeType:    req.MimeType,
		TotalChunks: req.TotalChunks,
		Chunks:      make(map[int]bool),
		TempDir:     tempDir,
	}
	
	uploadSessions.Lock()
	uploadSessions.sessions[uploadID] = session
	uploadSessions.Unlock()
	
	json.NewEncoder(w).Encode(map[string]string{"uploadId": uploadID})
}

// UploadChunk handles a single chunk
func (h *AudioHandler) UploadChunk(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseMultipartForm(2 << 20); err != nil { // 2MB max
		http.Error(w, "Chunk too large", http.StatusBadRequest)
		return
	}
	
	uploadID := r.FormValue("uploadId")
	chunkIndex := r.FormValue("chunkIndex")
	
	uploadSessions.RLock()
	session, ok := uploadSessions.sessions[uploadID]
	uploadSessions.RUnlock()
	
	if !ok {
		http.Error(w, "Upload not found", http.StatusNotFound)
		return
	}
	
	file, _, err := r.FormFile("chunk")
	if err != nil {
		http.Error(w, "Missing chunk", http.StatusBadRequest)
		return
	}
	defer file.Close()
	
	// Save chunk
	chunkPath := filepath.Join(session.TempDir, fmt.Sprintf("chunk_%s", chunkIndex))
	out, err := os.Create(chunkPath)
	if err != nil {
		http.Error(w, "Failed to save chunk", http.StatusInternalServerError)
		return
	}
	defer out.Close()
	
	if _, err := io.Copy(out, file); err != nil {
		http.Error(w, "Failed to write chunk", http.StatusInternalServerError)
		return
	}
	
	// Mark chunk as received
	var idx int
	fmt.Sscanf(chunkIndex, "%d", &idx)
	
	uploadSessions.Lock()
	session.Chunks[idx] = true
	uploadSessions.Unlock()
	
	w.WriteHeader(http.StatusOK)
}

// CompleteUpload assembles chunks into final file
func (h *AudioHandler) CompleteUpload(w http.ResponseWriter, r *http.Request) {
	var req struct {
		UploadID string          `json:"uploadId"`
		Metadata json.RawMessage `json:"metadata"`
	}
	
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid request", http.StatusBadRequest)
		return
	}
	
	uploadSessions.RLock()
	session, ok := uploadSessions.sessions[req.UploadID]
	uploadSessions.RUnlock()
	
	if !ok {
		http.Error(w, "Upload not found", http.StatusNotFound)
		return
	}
	
	// Verify all chunks received
	for i := 0; i < session.TotalChunks; i++ {
		if !session.Chunks[i] {
			http.Error(w, fmt.Sprintf("Missing chunk %d", i), http.StatusBadRequest)
			return
		}
	}
	
	// Assemble final file
	finalPath := filepath.Join(h.config.AudioDir, session.RecordingID, session.FileName)
	if err := os.MkdirAll(filepath.Dir(finalPath), 0755); err != nil {
		http.Error(w, "Failed to create directory", http.StatusInternalServerError)
		return
	}
	
	finalFile, err := os.Create(finalPath)
	if err != nil {
		http.Error(w, "Failed to create file", http.StatusInternalServerError)
		return
	}
	defer finalFile.Close()
	
	// Concatenate chunks
	for i := 0; i < session.TotalChunks; i++ {
		chunkPath := filepath.Join(session.TempDir, fmt.Sprintf("chunk_%d", i))
		chunk, err := os.Open(chunkPath)
		if err != nil {
			http.Error(w, "Failed to read chunk", http.StatusInternalServerError)
			return
		}
		
		if _, err := io.Copy(finalFile, chunk); err != nil {
			chunk.Close()
			http.Error(w, "Failed to assemble file", http.StatusInternalServerError)
			return
		}
		chunk.Close()
	}
	
	// Cleanup temp directory
	os.RemoveAll(session.TempDir)
	
	// Remove session
	uploadSessions.Lock()
	delete(uploadSessions.sessions, req.UploadID)
	uploadSessions.Unlock()
	
	// Save metadata to database
	// ... db.SaveAudioRecording(session.RecordingID, finalPath, req.Metadata)
	
	json.NewEncoder(w).Encode(map[string]string{
		"status": "completed",
		"path":   finalPath,
	})
}
```

---

## 5. Error Recovery Strategies

### Client-Side Recovery

| Error Type | Recovery Strategy |
|------------|-------------------|
| Network timeout | Retry with exponential backoff |
| 5xx server error | Queue for later retry |
| 4xx client error | Mark as failed, require manual intervention |
| Partial upload | Resume from last successful chunk |
| IndexedDB error | Attempt localStorage fallback for metadata |

### Server-Side Recovery

| Error Type | Recovery Strategy |
|------------|-------------------|
| Disk full | Alert admin, reject new uploads |
| Transcription timeout | Re-queue with lower priority |
| Orphaned chunks | Cleanup job runs hourly |
| Database connection lost | Queue operations in memory, flush on reconnect |

---

## 6. Monitoring & Metrics

```typescript
// lib/audio/SyncMetrics.ts

export interface SyncMetrics {
  uploadsAttempted: number;
  uploadsCompleted: number;
  uploadsFailed: number;
  bytesUploaded: number;
  averageUploadTimeMs: number;
  transcriptionsCompleted: number;
  transcriptionsFailed: number;
  averageTranscriptionTimeMs: number;
}

export class SyncMetricsCollector {
  private metrics: SyncMetrics = {
    uploadsAttempted: 0,
    uploadsCompleted: 0,
    uploadsFailed: 0,
    bytesUploaded: 0,
    averageUploadTimeMs: 0,
    transcriptionsCompleted: 0,
    transcriptionsFailed: 0,
    averageTranscriptionTimeMs: 0,
  };
  
  private uploadTimes: number[] = [];
  private transcriptionTimes: number[] = [];
  
  recordUploadStart(): number {
    this.metrics.uploadsAttempted++;
    return Date.now();
  }
  
  recordUploadComplete(startTime: number, bytes: number): void {
    this.metrics.uploadsCompleted++;
    this.metrics.bytesUploaded += bytes;
    
    const duration = Date.now() - startTime;
    this.uploadTimes.push(duration);
    
    // Keep last 100 samples
    if (this.uploadTimes.length > 100) {
      this.uploadTimes.shift();
    }
    
    this.metrics.averageUploadTimeMs = 
      this.uploadTimes.reduce((a, b) => a + b, 0) / this.uploadTimes.length;
  }
  
  recordUploadFailed(): void {
    this.metrics.uploadsFailed++;
  }
  
  getMetrics(): SyncMetrics {
    return { ...this.metrics };
  }
}
```

---

## 7. Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| Offline queue | Operations queue when offline | Critical |
| Online resume | Queue processes when online | Critical |
| Retry logic | Failed operations retry with backoff | Critical |
| Chunked upload | Large files upload in chunks | High |
| Resume upload | Partial upload resumes correctly | High |
| Progress tracking | Upload progress updates accurately | Medium |
| Cleanup | Old synced recordings cleaned up | Medium |
| Concurrent uploads | Multiple uploads don't conflict | High |

---

## Related Specs

- [Voice Resilience](./02-voice-resilience.md)
- [Audio Capture](./02-01-audio-capture.md)
- [Transcription Service](./02-02-transcription-service.md)
- [Sync Queue](./01-02-sync-queue.md)
