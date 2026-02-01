# Phase 2.1: Audio Capture Engine

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Browser-based audio capture system using MediaRecorder API with WebM/Opus encoding. Audio is always persisted locally before any network operations to ensure zero data loss.

**Cross-References:**
- [Voice Resilience](./02-voice-resilience.md)
- [Versioned Storage](./01-01-versioned-storage.md)

---

## 1. Audio Capture Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        User Microphone                                  │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                   getUserMedia() + Constraints                          │
│   ┌─────────────────────────────────────────────────────────────┐       │
│   │  echoCancellation: true                                     │       │
│   │  noiseSuppression: true                                     │       │
│   │  autoGainControl: true                                      │       │
│   │  sampleRate: 16000 (preferred)                              │       │
│   │  channelCount: 1 (mono)                                     │       │
│   └─────────────────────────────────────────────────────────────┘       │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
                   ┌────────────┴────────────┐
                   │                         │
                   ▼                         ▼
┌────────────────────────────┐    ┌────────────────────────────┐
│     MediaRecorder          │    │     AudioContext           │
│  ┌──────────────────────┐  │    │  ┌──────────────────────┐  │
│  │ mimeType: webm/opus  │  │    │  │ MediaStreamSource    │  │
│  │ audioBitsPerSecond:  │  │    │  │        ▼             │  │
│  │   128000             │  │    │  │ AnalyserNode         │  │
│  │ timeslice: 1000ms    │  │    │  │ (FFT visualization)  │  │
│  └──────────────────────┘  │    │  └──────────────────────┘  │
│            │               │    │            │               │
│            ▼               │    │            ▼               │
│     ondataavailable        │    │     Amplitude Data         │
│     (Blob chunks)          │    │     (60fps updates)        │
└────────────┬───────────────┘    └────────────┬───────────────┘
             │                                  │
             ▼                                  ▼
┌────────────────────────────┐    ┌────────────────────────────┐
│     Chunk Accumulator      │    │     UI Visualization       │
│  - Collect Blob[]          │    │  - Waveform display        │
│  - Track duration          │    │  - Amplitude ring          │
│  - Monitor size            │    │  - Level meter             │
└────────────┬───────────────┘    └────────────────────────────┘
             │
             ▼ (on stop)
┌─────────────────────────────────────────────────────────────────────────┐
│                         Final Blob Assembly                             │
│  new Blob(chunks, { type: mimeType })                                   │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    IndexedDB (Immediate Save)                           │
│  - Store blob in 'recordings' object store                              │
│  - Generate UUID for recording                                          │
│  - Persist metadata to localStorage                                     │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Format Selection Matrix

### Browser Support Table

| Browser | WebM/Opus | MP4/AAC | OGG/Opus | WAV |
|---------|-----------|---------|----------|-----|
| Chrome | ✓ Native | ✓ | ✓ | ✓ |
| Firefox | ✓ Native | ✗ | ✓ Native | ✓ |
| Edge | ✓ Native | ✓ | ✓ | ✓ |
| Safari | ✗ | ✓ Native | ✗ | ✓ |

### Format Priority List

```typescript
const FORMAT_PRIORITY = [
  { mimeType: 'audio/webm;codecs=opus', ext: 'webm', quality: 'excellent' },
  { mimeType: 'audio/webm', ext: 'webm', quality: 'excellent' },
  { mimeType: 'audio/ogg;codecs=opus', ext: 'ogg', quality: 'excellent' },
  { mimeType: 'audio/mp4', ext: 'm4a', quality: 'good' },
  { mimeType: 'audio/mpeg', ext: 'mp3', quality: 'good' },
] as const;
```

### Recommended Settings

| Setting | Value | Rationale |
|---------|-------|-----------|
| Sample Rate | 16kHz | Optimal for speech recognition |
| Channels | Mono | Voice only, reduces file size |
| Bitrate | 128kbps | Good quality/size balance |
| Chunk Interval | 1000ms | Balance between memory and recovery |
| Max Duration | 10 minutes | Practical limit for transcription |
| Max File Size | 50MB | Backend upload limit |

---

## 3. AudioCaptureService Implementation

```typescript
// lib/audio/AudioCaptureService.ts

import { VersionedStorage } from '@/lib/storage/VersionedStorage';
import { IndexedDBBlob } from '@/lib/storage/IndexedDBBlob';

/**
 * Audio recording metadata
 */
export interface AudioRecordingMeta {
  id: string;
  projectId: string;
  sessionId?: string;
  format: 'webm' | 'ogg' | 'm4a' | 'mp3' | 'wav';
  mimeType: string;
  duration: number;        // milliseconds
  size: number;            // bytes
  sampleRate: number;
  channels: number;
  timestamp: number;       // Unix ms
  status: RecordingStatus;
  transcription?: TranscriptionResult;
  error?: string;
}

export type RecordingStatus = 
  | 'recording'    // Currently capturing
  | 'saved'        // Saved to IndexedDB
  | 'queued'       // In sync queue
  | 'syncing'      // Uploading to server
  | 'synced'       // Server confirmed
  | 'transcribing' // Whisper processing
  | 'completed'    // Transcription done
  | 'failed';      // Error state

export interface TranscriptionResult {
  text: string;
  language?: string;
  confidence?: number;
  segments?: TranscriptionSegment[];
}

export interface TranscriptionSegment {
  start: number;  // seconds
  end: number;    // seconds
  text: string;
  confidence?: number;
}

/**
 * Capture events for UI binding
 */
export interface CaptureEvents {
  onStart?: () => void;
  onProgress?: (amplitude: number, duration: number) => void;
  onChunk?: (chunk: Blob, chunkIndex: number) => void;
  onComplete?: (recording: AudioRecordingMeta) => void;
  onError?: (error: AudioCaptureError) => void;
}

/**
 * Custom error class for audio capture
 */
export class AudioCaptureError extends Error {
  constructor(
    message: string,
    public readonly code: AudioErrorCode,
    public readonly recoverable: boolean = true
  ) {
    super(message);
    this.name = 'AudioCaptureError';
  }
}

export type AudioErrorCode =
  | 'PERMISSION_DENIED'
  | 'DEVICE_NOT_FOUND'
  | 'DEVICE_IN_USE'
  | 'ENCODER_NOT_SUPPORTED'
  | 'STORAGE_QUOTA_EXCEEDED'
  | 'RECORDING_INTERRUPTED'
  | 'UNKNOWN';

/**
 * Main audio capture service
 */
export class AudioCaptureService {
  private mediaRecorder: MediaRecorder | null = null;
  private audioContext: AudioContext | null = null;
  private analyserNode: AnalyserNode | null = null;
  private mediaStream: MediaStream | null = null;
  
  private chunks: Blob[] = [];
  private startTime = 0;
  private animationFrameId: number | null = null;
  private durationIntervalId: ReturnType<typeof setInterval> | null = null;
  
  private currentRecordingId: string | null = null;
  private selectedMimeType: string = '';
  
  constructor(
    private storage: VersionedStorage,
    private blobStore: IndexedDBBlob,
    private projectId: string
  ) {}
  
  /**
   * Check if recording is supported
   */
  static isSupported(): boolean {
    return !!(
      navigator.mediaDevices?.getUserMedia &&
      window.MediaRecorder &&
      window.AudioContext
    );
  }
  
  /**
   * Get best supported MIME type
   */
  static getBestMimeType(): { mimeType: string; format: string } | null {
    const types = [
      { mimeType: 'audio/webm;codecs=opus', format: 'webm' },
      { mimeType: 'audio/webm', format: 'webm' },
      { mimeType: 'audio/ogg;codecs=opus', format: 'ogg' },
      { mimeType: 'audio/mp4', format: 'm4a' },
    ];
    
    for (const type of types) {
      if (MediaRecorder.isTypeSupported(type.mimeType)) {
        return type;
      }
    }
    return null;
  }
  
  /**
   * Start audio capture
   */
  async start(events: CaptureEvents = {}): Promise<string> {
    if (this.mediaRecorder?.state === 'recording') {
      throw new AudioCaptureError(
        'Recording already in progress',
        'RECORDING_INTERRUPTED',
        false
      );
    }
    
    // Generate recording ID upfront
    this.currentRecordingId = crypto.randomUUID();
    
    try {
      // Request microphone access
      this.mediaStream = await this.requestMicrophone();
      
      // Setup audio analysis for visualization
      this.setupAudioAnalysis(this.mediaStream);
      
      // Select best format
      const format = AudioCaptureService.getBestMimeType();
      if (!format) {
        throw new AudioCaptureError(
          'No supported audio format found',
          'ENCODER_NOT_SUPPORTED',
          false
        );
      }
      this.selectedMimeType = format.mimeType;
      
      // Create MediaRecorder
      this.mediaRecorder = new MediaRecorder(this.mediaStream, {
        mimeType: format.mimeType,
        audioBitsPerSecond: 128000,
      });
      
      this.chunks = [];
      this.startTime = Date.now();
      
      // Bind events
      this.bindRecorderEvents(events, format.format);
      
      // Start recording with 1-second chunks
      this.mediaRecorder.start(1000);
      
      // Start amplitude monitoring
      if (events.onProgress) {
        this.startAmplitudeMonitoring(events.onProgress);
      }
      
      events.onStart?.();
      
      return this.currentRecordingId;
      
    } catch (error) {
      this.cleanup();
      throw this.mapError(error);
    }
  }
  
  /**
   * Stop recording
   */
  async stop(): Promise<AudioRecordingMeta | null> {
    return new Promise((resolve, reject) => {
      if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') {
        resolve(null);
        return;
      }
      
      // Store reference for closure
      const recorder = this.mediaRecorder;
      const existingOnStop = recorder.onstop;
      
      recorder.onstop = async (event) => {
        // Call original handler first
        if (existingOnStop) {
          (existingOnStop as (e: Event) => void)(event);
        }
        
        try {
          const meta = await this.finalizeRecording();
          resolve(meta);
        } catch (error) {
          reject(error);
        }
      };
      
      recorder.stop();
      this.stopAmplitudeMonitoring();
    });
  }
  
  /**
   * Pause recording
   */
  pause(): void {
    if (this.mediaRecorder?.state === 'recording') {
      this.mediaRecorder.pause();
      this.stopAmplitudeMonitoring();
    }
  }
  
  /**
   * Resume recording
   */
  resume(onProgress?: (amplitude: number, duration: number) => void): void {
    if (this.mediaRecorder?.state === 'paused') {
      this.mediaRecorder.resume();
      if (onProgress) {
        this.startAmplitudeMonitoring(onProgress);
      }
    }
  }
  
  /**
   * Get current state
   */
  getState(): RecordingState | null {
    return this.mediaRecorder?.state || null;
  }
  
  /**
   * Get current duration in ms
   */
  getDuration(): number {
    if (!this.startTime) return 0;
    return Date.now() - this.startTime;
  }
  
  /**
   * Request microphone access
   */
  private async requestMicrophone(): Promise<MediaStream> {
    try {
      return await navigator.mediaDevices.getUserMedia({
        audio: {
          channelCount: 1,
          sampleRate: { ideal: 16000 },
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
        },
      });
    } catch (error) {
      if (error instanceof DOMException) {
        switch (error.name) {
          case 'NotAllowedError':
            throw new AudioCaptureError(
              'Microphone permission denied',
              'PERMISSION_DENIED',
              false
            );
          case 'NotFoundError':
            throw new AudioCaptureError(
              'No microphone found',
              'DEVICE_NOT_FOUND',
              false
            );
          case 'NotReadableError':
            throw new AudioCaptureError(
              'Microphone is in use by another application',
              'DEVICE_IN_USE',
              true
            );
        }
      }
      throw error;
    }
  }
  
  /**
   * Setup audio context and analyser
   */
  private setupAudioAnalysis(stream: MediaStream): void {
    this.audioContext = new AudioContext({ sampleRate: 16000 });
    const source = this.audioContext.createMediaStreamSource(stream);
    
    this.analyserNode = this.audioContext.createAnalyser();
    this.analyserNode.fftSize = 256;
    this.analyserNode.smoothingTimeConstant = 0.8;
    
    source.connect(this.analyserNode);
    // Don't connect to destination - we don't want playback during recording
  }
  
  /**
   * Bind MediaRecorder events
   */
  private bindRecorderEvents(events: CaptureEvents, format: string): void {
    if (!this.mediaRecorder) return;
    
    let chunkIndex = 0;
    
    this.mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) {
        this.chunks.push(e.data);
        events.onChunk?.(e.data, chunkIndex++);
      }
    };
    
    this.mediaRecorder.onerror = (e) => {
      const error = new AudioCaptureError(
        'Recording error occurred',
        'RECORDING_INTERRUPTED',
        true
      );
      events.onError?.(error);
      this.cleanup();
    };
  }
  
  /**
   * Start amplitude monitoring for visualization
   */
  private startAmplitudeMonitoring(
    onProgress: (amplitude: number, duration: number) => void
  ): void {
    if (!this.analyserNode) return;
    
    const dataArray = new Uint8Array(this.analyserNode.frequencyBinCount);
    
    const update = () => {
      if (!this.analyserNode) return;
      
      this.analyserNode.getByteFrequencyData(dataArray);
      
      // Calculate RMS amplitude
      let sum = 0;
      for (let i = 0; i < dataArray.length; i++) {
        sum += dataArray[i] * dataArray[i];
      }
      const rms = Math.sqrt(sum / dataArray.length);
      const normalized = Math.min(rms / 128, 1); // Normalize to 0-1
      
      const duration = this.getDuration();
      onProgress(normalized, duration);
      
      this.animationFrameId = requestAnimationFrame(update);
    };
    
    update();
  }
  
  /**
   * Stop amplitude monitoring
   */
  private stopAmplitudeMonitoring(): void {
    if (this.animationFrameId !== null) {
      cancelAnimationFrame(this.animationFrameId);
      this.animationFrameId = null;
    }
  }
  
  /**
   * Finalize and save recording
   */
  private async finalizeRecording(): Promise<AudioRecordingMeta> {
    const id = this.currentRecordingId!;
    const blob = new Blob(this.chunks, { type: this.selectedMimeType });
    const duration = Date.now() - this.startTime;
    
    const format = this.selectedMimeType.includes('webm') ? 'webm' :
                   this.selectedMimeType.includes('ogg') ? 'ogg' :
                   this.selectedMimeType.includes('mp4') ? 'm4a' : 'mp3';
    
    // Create metadata
    const meta: AudioRecordingMeta = {
      id,
      projectId: this.projectId,
      format: format as AudioRecordingMeta['format'],
      mimeType: this.selectedMimeType,
      duration,
      size: blob.size,
      sampleRate: 16000,
      channels: 1,
      timestamp: Date.now(),
      status: 'saved',
    };
    
    // Save blob to IndexedDB
    await this.blobStore.put(id, blob);
    
    // Save metadata to versioned storage
    this.storage.set(`audio:${id}`, meta, false);
    
    // Cleanup
    this.cleanup();
    
    return meta;
  }
  
  /**
   * Map native errors to AudioCaptureError
   */
  private mapError(error: unknown): AudioCaptureError {
    if (error instanceof AudioCaptureError) {
      return error;
    }
    
    if (error instanceof DOMException) {
      if (error.name === 'QuotaExceededError') {
        return new AudioCaptureError(
          'Storage quota exceeded',
          'STORAGE_QUOTA_EXCEEDED',
          true
        );
      }
    }
    
    return new AudioCaptureError(
      error instanceof Error ? error.message : 'Unknown error',
      'UNKNOWN',
      true
    );
  }
  
  /**
   * Cleanup all resources
   */
  private cleanup(): void {
    this.stopAmplitudeMonitoring();
    
    if (this.durationIntervalId) {
      clearInterval(this.durationIntervalId);
      this.durationIntervalId = null;
    }
    
    if (this.mediaStream) {
      this.mediaStream.getTracks().forEach(track => track.stop());
      this.mediaStream = null;
    }
    
    if (this.audioContext?.state !== 'closed') {
      this.audioContext?.close();
    }
    this.audioContext = null;
    this.analyserNode = null;
    this.mediaRecorder = null;
    this.chunks = [];
    this.currentRecordingId = null;
  }
}

type RecordingState = 'inactive' | 'recording' | 'paused';
```

---

## 4. IndexedDB Blob Storage

```typescript
// lib/storage/IndexedDBBlob.ts

const DB_NAME = 'specmgmt_blobs';
const DB_VERSION = 1;
const STORE_NAME = 'recordings';

/**
 * IndexedDB wrapper for large blob storage
 */
export class IndexedDBBlob {
  private db: IDBDatabase | null = null;
  private initPromise: Promise<void> | null = null;
  
  /**
   * Initialize the database
   */
  async init(): Promise<void> {
    if (this.db) return;
    
    if (this.initPromise) {
      await this.initPromise;
      return;
    }
    
    this.initPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      
      request.onupgradeneeded = (event) => {
        const db = (event.target as IDBOpenDBRequest).result;
        
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          const store = db.createObjectStore(STORE_NAME, { keyPath: 'id' });
          store.createIndex('timestamp', 'timestamp', { unique: false });
          store.createIndex('synced', 'synced', { unique: false });
        }
      };
      
      request.onsuccess = (event) => {
        this.db = (event.target as IDBOpenDBRequest).result;
        resolve();
      };
      
      request.onerror = () => {
        reject(new Error('Failed to open IndexedDB'));
      };
    });
    
    await this.initPromise;
  }
  
  /**
   * Store a blob
   */
  async put(id: string, blob: Blob, synced = false): Promise<void> {
    await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      
      store.put({
        id,
        blob,
        timestamp: Date.now(),
        synced,
      });
      
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }
  
  /**
   * Retrieve a blob
   */
  async get(id: string): Promise<Blob | null> {
    await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const request = store.get(id);
      
      request.onsuccess = () => {
        resolve(request.result?.blob || null);
      };
      request.onerror = () => reject(request.error);
    });
  }
  
  /**
   * Delete a blob
   */
  async delete(id: string): Promise<void> {
    await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      store.delete(id);
      
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }
  
  /**
   * Get all unsynced recordings
   */
  async getUnsynced(): Promise<Array<{ id: string; blob: Blob; timestamp: number }>> {
    await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('synced');
      const request = index.getAll(IDBKeyRange.only(false));
      
      request.onsuccess = () => {
        resolve(request.result || []);
      };
      request.onerror = () => reject(request.error);
    });
  }
  
  /**
   * Mark as synced
   */
  async markSynced(id: string): Promise<void> {
    await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.get(id);
      
      request.onsuccess = () => {
        if (request.result) {
          request.result.synced = true;
          store.put(request.result);
        }
      };
      
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }
  
  /**
   * Cleanup old synced recordings (>24h)
   */
  async cleanupOld(maxAgeMs = 24 * 60 * 60 * 1000): Promise<number> {
    await this.init();
    
    const cutoff = Date.now() - maxAgeMs;
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('timestamp');
      const range = IDBKeyRange.upperBound(cutoff);
      const request = index.openCursor(range);
      
      let deleted = 0;
      
      request.onsuccess = (event) => {
        const cursor = (event.target as IDBRequest<IDBCursorWithValue>).result;
        if (cursor) {
          // Only delete if synced
          if (cursor.value.synced) {
            cursor.delete();
            deleted++;
          }
          cursor.continue();
        }
      };
      
      tx.oncomplete = () => resolve(deleted);
      tx.onerror = () => reject(tx.error);
    });
  }
  
  /**
   * Get total storage used
   */
  async getStorageUsed(): Promise<number> {
    await this.init();
    
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const request = store.getAll();
      
      request.onsuccess = () => {
        const total = request.result.reduce((sum, item) => {
          return sum + (item.blob?.size || 0);
        }, 0);
        resolve(total);
      };
      request.onerror = () => reject(request.error);
    });
  }
}
```

---

## 5. Audio Constraints Helper

```typescript
// lib/audio/constraints.ts

export interface AudioConstraintsConfig {
  quality: 'low' | 'medium' | 'high';
  optimizeFor: 'speech' | 'music' | 'general';
  enableProcessing: boolean;
}

/**
 * Build MediaTrackConstraints based on config
 */
export function buildAudioConstraints(
  config: AudioConstraintsConfig
): MediaTrackConstraints {
  const base: MediaTrackConstraints = {
    channelCount: 1,
  };
  
  // Sample rate based on quality
  switch (config.quality) {
    case 'low':
      base.sampleRate = { ideal: 8000 };
      break;
    case 'medium':
      base.sampleRate = { ideal: 16000 };
      break;
    case 'high':
      base.sampleRate = { ideal: 44100 };
      break;
  }
  
  // Processing based on optimization target
  if (config.enableProcessing) {
    if (config.optimizeFor === 'speech') {
      base.echoCancellation = true;
      base.noiseSuppression = true;
      base.autoGainControl = true;
    } else if (config.optimizeFor === 'music') {
      base.echoCancellation = false;
      base.noiseSuppression = false;
      base.autoGainControl = false;
    } else {
      base.echoCancellation = { ideal: true };
      base.noiseSuppression = { ideal: true };
      base.autoGainControl = { ideal: true };
    }
  }
  
  return base;
}

/**
 * Check device capabilities
 */
export async function getAudioCapabilities(): Promise<{
  hasMicrophone: boolean;
  devices: MediaDeviceInfo[];
  permissions: PermissionState | 'unknown';
}> {
  const devices = await navigator.mediaDevices.enumerateDevices();
  const audioInputs = devices.filter(d => d.kind === 'audioinput');
  
  let permissions: PermissionState | 'unknown' = 'unknown';
  try {
    const result = await navigator.permissions.query({ 
      name: 'microphone' as PermissionName 
    });
    permissions = result.state;
  } catch {
    // Permissions API not supported
  }
  
  return {
    hasMicrophone: audioInputs.length > 0,
    devices: audioInputs,
    permissions,
  };
}
```

---

## 6. Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| Browser support detection | Correctly identify supported formats | Critical |
| Permission handling | Graceful error on denial | Critical |
| Recording basic | Start/stop produces valid blob | Critical |
| IndexedDB save | Blob persisted before network | Critical |
| Amplitude visualization | Values update at 60fps | Medium |
| Pause/resume | Recording continues correctly | Medium |
| Device switching | Handle device disconnect | Low |
| Storage quota | Handle quota exceeded | High |

---

## Related Specs

- [Voice Resilience](./02-voice-resilience.md)
- [Transcription Service](./02-02-transcription-service.md)
- [Audio Sync](./02-03-audio-sync.md)
