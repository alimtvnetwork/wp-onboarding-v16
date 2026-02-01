# Voice Session Manager Specification

> **Version:** 1.0.0  
> **Status:** Draft  
> **Last Updated:** 2026-01-30  
> **Related:** [01-voice-recorder.md](./01-voice-recorder.md), [02-transcription-display.md](./02-transcription-display.md), [03-audio-player.md](./03-audio-player.md)

---

## 1. Overview

The Voice Session Manager orchestrates the complete lifecycle of voice input sessions, coordinating the Voice Recorder, Audio Player, and Transcription Display components. It manages state transitions, data flow, persistence, and integration with the Voice-CLI microservice.

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          VoiceSessionManager                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                         SessionContext                                   ││
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ ││
│  │  │  SessionID   │  │    Phase     │  │   Duration   │  │  Speakers    │ ││
│  │  └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘ ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌───────────────────────┐  ┌───────────────────────┐  ┌──────────────────┐ │
│  │    VoiceRecorder      │  │    AudioPlayer        │  │ TranscriptDisplay│ │
│  │  ┌─────────────────┐  │  │  ┌─────────────────┐  │  │ ┌──────────────┐ │ │
│  │  │ useVoiceRecorder│  │  │  │ useAudioPlayback│  │  │ │useTranscript │ │ │
│  │  └─────────────────┘  │  │  └─────────────────┘  │  │ └──────────────┘ │ │
│  │  ┌─────────────────┐  │  │  ┌─────────────────┐  │  │ ┌──────────────┐ │ │
│  │  │ useAudioCapture │  │  │  │ useTimestampSync│  │  │ │useWordHighlt │ │ │
│  │  └─────────────────┘  │  │  └─────────────────┘  │  │ └──────────────┘ │ │
│  │  ┌─────────────────┐  │  │  ┌─────────────────┐  │  │ ┌──────────────┐ │ │
│  │  │ useVoiceWebSocket│ │  │  │ useWaveformData │  │  │ │useIntentPrev │ │ │
│  │  └─────────────────┘  │  │  └─────────────────┘  │  │ └──────────────┘ │ │
│  └───────────────────────┘  └───────────────────────┘  └──────────────────┘ │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                      SessionPersistence                                  ││
│  │  ┌──────────────────┐  ┌──────────────────┐  ┌────────────────────────┐ ││
│  │  │   IndexedDB      │  │   AudioBlobs     │  │   TranscriptCache      │ ││
│  │  │   (Resilient)    │  │   (Local Store)  │  │   (Real-time Sync)     │ ││
│  │  └──────────────────┘  └──────────────────┘  └────────────────────────┘ ││
│  └─────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
                    ┌────────────────────────────────┐
                    │       Voice-CLI Service        │
                    │  WebSocket + REST (Port 8084)  │
                    └────────────────────────────────┘
```

---

## 3. Session Lifecycle

### 3.1 Phase State Machine

```
                    ┌─────────────┐
                    │    Idle     │◄──────────────────────────┐
                    └──────┬──────┘                           │
                           │ startSession()                   │
                           ▼                                  │
                    ┌─────────────┐                           │
                    │ Connecting  │───── error ───────────────┤
                    └──────┬──────┘                           │
                           │ ws.open                          │
                           ▼                                  │
                    ┌─────────────┐                           │
             ┌──────│  Recording  │◄─────┐                    │
             │      └──────┬──────┘      │                    │
             │             │             │                    │
       pause │             │ stop()      │ resume()           │
             │             ▼             │                    │
             │      ┌─────────────┐      │                    │
             └─────►│   Paused    │──────┘                    │
                    └──────┬──────┘                           │
                           │ finalize()                       │
                           ▼                                  │
                    ┌─────────────┐                           │
                    │ Processing  │                           │
                    └──────┬──────┘                           │
                           │ transcription complete           │
                           ▼                                  │
                    ┌─────────────┐                           │
                    │  Reviewing  │                           │
                    └──────┬──────┘                           │
                           │ save() / discard()               │
                           ▼                                  │
                    ┌─────────────┐                           │
                    │  Complete   │───── newSession() ────────┘
                    └─────────────┘
```

### 3.2 Phase Types

```typescript
type SessionPhase =
  | 'Idle'           // No active session
  | 'Connecting'     // Establishing WebSocket connection
  | 'Recording'      // Actively capturing audio
  | 'Paused'         // Recording paused
  | 'Processing'     // Finalizing transcription
  | 'Reviewing'      // User reviewing transcript/playback
  | 'Complete'       // Session saved or discarded
  | 'Error';         // Unrecoverable error state

interface PhaseTransition {
  readonly from: SessionPhase;
  readonly to: SessionPhase;
  readonly trigger: string;
  readonly timestamp: number;
  readonly metadata?: Record<string, unknown>;
}
```

---

## 4. TypeScript Interfaces

### 4.1 Session Types

```typescript
interface VoiceSession {
  readonly id: string;                    // UUID v7
  readonly projectId: string;             // Parent project
  readonly conversationId?: string;       // Optional conversation context
  readonly phase: SessionPhase;
  readonly createdAt: number;             // Unix timestamp
  readonly updatedAt: number;
  readonly duration: number;              // Total recording duration (ms)
  readonly speakers: readonly Speaker[];
  readonly metadata: SessionMetadata;
}

interface SessionMetadata {
  readonly title?: string;
  readonly tags: readonly string[];
  readonly whisperModel: string;          // e.g., 'large-v3'
  readonly language?: string;             // Detected or specified
  readonly sampleRate: number;            // Audio sample rate
  readonly channels: number;              // Mono/stereo
  readonly intentCount: number;           // Recognized commands
}

interface Speaker {
  readonly id: string;
  readonly label: string;                 // 'Speaker 1', 'User', etc.
  readonly color: string;                 // Semantic color token
  readonly totalDuration: number;         // Speaking time (ms)
}

interface SessionRecording {
  readonly sessionId: string;
  readonly audioBlob: Blob;
  readonly mimeType: string;
  readonly size: number;
  readonly chunks: readonly AudioChunk[];
}

interface AudioChunk {
  readonly id: string;
  readonly startTime: number;
  readonly endTime: number;
  readonly blob: Blob;
  readonly isSynced: boolean;             // Sent to backend
}
```

### 4.2 Manager State

```typescript
interface SessionManagerState {
  readonly session: VoiceSession | null;
  readonly phase: SessionPhase;
  readonly recording: SessionRecording | null;
  readonly transcript: TranscriptDocument | null;
  readonly intents: readonly RecognizedIntent[];
  readonly errors: readonly SessionError[];
  readonly connection: ConnectionState;
  readonly permissions: PermissionState;
}

interface ConnectionState {
  readonly status: 'disconnected' | 'connecting' | 'connected' | 'reconnecting';
  readonly wsUrl: string | null;
  readonly latency: number;               // Last ping (ms)
  readonly reconnectAttempts: number;
}

interface PermissionState {
  readonly microphone: 'prompt' | 'granted' | 'denied';
  readonly storage: 'available' | 'limited' | 'unavailable';
}

interface TranscriptDocument {
  readonly sessionId: string;
  readonly segments: readonly TranscriptSegment[];
  readonly partialText: string;           // Current partial transcript
  readonly isFinalized: boolean;
  readonly wordCount: number;
  readonly confidence: number;            // Average word confidence
}

interface RecognizedIntent {
  readonly id: string;
  readonly type: string;                  // CREATE_STAGE, RUN_FLOW, etc.
  readonly confidence: number;
  readonly parameters: Record<string, unknown>;
  readonly timestamp: number;
  readonly segmentId: string;             // Source segment
  readonly isExecuted: boolean;
}
```

### 4.3 Action Types

```typescript
type SessionAction =
  | { type: 'START_SESSION'; payload: { projectId: string; conversationId?: string } }
  | { type: 'CONNECTION_ESTABLISHED'; payload: { wsUrl: string } }
  | { type: 'CONNECTION_FAILED'; payload: { error: SessionError } }
  | { type: 'PAUSE_RECORDING' }
  | { type: 'RESUME_RECORDING' }
  | { type: 'STOP_RECORDING' }
  | { type: 'AUDIO_CHUNK_CAPTURED'; payload: { chunk: AudioChunk } }
  | { type: 'AUDIO_CHUNK_SYNCED'; payload: { chunkId: string } }
  | { type: 'PARTIAL_TRANSCRIPT'; payload: { text: string; isFinal: boolean } }
  | { type: 'SEGMENT_RECEIVED'; payload: { segment: TranscriptSegment } }
  | { type: 'INTENT_RECOGNIZED'; payload: { intent: RecognizedIntent } }
  | { type: 'INTENT_EXECUTED'; payload: { intentId: string; result: unknown } }
  | { type: 'FINALIZE_TRANSCRIPT' }
  | { type: 'TRANSCRIPT_FINALIZED'; payload: { document: TranscriptDocument } }
  | { type: 'SAVE_SESSION' }
  | { type: 'SESSION_SAVED'; payload: { sessionId: string } }
  | { type: 'DISCARD_SESSION' }
  | { type: 'ERROR'; payload: { error: SessionError } }
  | { type: 'RESET' };
```

---

## 5. Hook Specifications

### 5.1 useVoiceSessionManager

Primary orchestration hook for the entire voice input system.

```typescript
interface UseVoiceSessionManagerOptions {
  readonly projectId: string;
  readonly conversationId?: string;
  readonly autoConnect?: boolean;
  readonly whisperModel?: string;
  readonly onIntentRecognized?: (intent: RecognizedIntent) => void;
  readonly onSessionComplete?: (session: VoiceSession) => void;
  readonly onError?: (error: SessionError) => void;
}

interface UseVoiceSessionManagerReturn {
  // State
  readonly state: SessionManagerState;
  readonly isRecording: boolean;
  readonly isPaused: boolean;
  readonly isProcessing: boolean;
  readonly canRecord: boolean;
  
  // Session actions
  readonly startSession: () => Promise<void>;
  readonly pauseSession: () => void;
  readonly resumeSession: () => void;
  readonly stopSession: () => Promise<void>;
  readonly saveSession: () => Promise<string>;
  readonly discardSession: () => void;
  
  // Playback (during review phase)
  readonly playbackState: PlaybackState | null;
  readonly seekTo: (time: number) => void;
  readonly togglePlayback: () => void;
  
  // Transcript
  readonly transcript: TranscriptDocument | null;
  readonly editSegment: (segmentId: string, text: string) => void;
  readonly currentWordId: string | null;
  
  // Intents
  readonly pendingIntents: readonly RecognizedIntent[];
  readonly executeIntent: (intentId: string) => Promise<void>;
  readonly dismissIntent: (intentId: string) => void;
  
  // Utilities
  readonly requestPermissions: () => Promise<boolean>;
  readonly exportTranscript: (format: ExportFormat) => Promise<Blob>;
}
```

### 5.2 Implementation Pattern

```typescript
function useVoiceSessionManager(options: UseVoiceSessionManagerOptions): UseVoiceSessionManagerReturn {
  const { projectId, conversationId, autoConnect = false, whisperModel = 'large-v3' } = options;
  
  // Core state reducer
  const [state, dispatch] = useReducer(sessionReducer, initialState);
  
  // Child hooks - composed together
  const recorder = useVoiceRecorder({
    onAudioData: handleAudioData,
    onVADStateChange: handleVADChange,
    enabled: state.phase === 'Recording',
  });
  
  const websocket = useVoiceWebSocket({
    url: state.connection.wsUrl,
    enabled: state.connection.status !== 'disconnected',
    onMessage: handleWSMessage,
    onError: handleWSError,
    onReconnect: handleReconnect,
  });
  
  const playback = useAudioPlayback({
    src: state.recording?.audioBlob ? URL.createObjectURL(state.recording.audioBlob) : null,
    onTimeUpdate: handleTimeUpdate,
  });
  
  const timestampSync = useTimestampSync({
    config: {
      transcriptId: state.session?.id ?? '',
      syncMode: 'bidirectional',
      highlightLead: 100,
      scrollBehavior: 'smooth',
      wordSnapThreshold: 50,
    },
    segments: state.transcript?.segments ?? [],
    playbackState: playback.state,
    onSeekRequest: playback.seek,
  });
  
  const persistence = useSessionPersistence({
    sessionId: state.session?.id,
    enabled: state.phase !== 'Idle',
  });

  // Audio data flow: Recorder → WebSocket
  const handleAudioData = useCallback((audioData: Float32Array) => {
    const encoded = encodeAudioForAPI(audioData);
    websocket.send({ type: 'input_audio_buffer.append', audio: encoded });
    
    // Also store locally for resilience
    const chunk = createAudioChunk(audioData, state.session!.duration);
    dispatch({ type: 'AUDIO_CHUNK_CAPTURED', payload: { chunk } });
    persistence.storeChunk(chunk);
  }, [websocket, state.session, persistence]);

  // WebSocket message routing
  const handleWSMessage = useCallback((message: WSMessage) => {
    switch (message.type) {
      case 'partial':
        dispatch({ type: 'PARTIAL_TRANSCRIPT', payload: { text: message.text, isFinal: false } });
        break;
        
      case 'final':
        const segment = parseTranscriptSegment(message);
        dispatch({ type: 'SEGMENT_RECEIVED', payload: { segment } });
        persistence.storeSegment(segment);
        break;
        
      case 'intent':
        const intent = parseIntent(message);
        dispatch({ type: 'INTENT_RECOGNIZED', payload: { intent } });
        options.onIntentRecognized?.(intent);
        break;
        
      case 'error':
        dispatch({ type: 'ERROR', payload: { error: parseError(message) } });
        break;
    }
  }, [persistence, options.onIntentRecognized]);

  // Session lifecycle methods
  const startSession = useCallback(async () => {
    // Request permissions if needed
    if (state.permissions.microphone !== 'granted') {
      const granted = await requestMicrophonePermission();
      if (!granted) {
        dispatch({ type: 'ERROR', payload: { error: { code: 'PERMISSION_DENIED', message: 'Microphone access required' } } });
        return;
      }
    }
    
    // Create new session
    const session = createSession(projectId, conversationId);
    dispatch({ type: 'START_SESSION', payload: { projectId, conversationId } });
    
    // Connect to Voice-CLI
    const wsUrl = buildWebSocketUrl(session.id, whisperModel);
    await websocket.connect(wsUrl);
    
    // Start recording
    await recorder.start();
  }, [projectId, conversationId, whisperModel, state.permissions, websocket, recorder]);

  const stopSession = useCallback(async () => {
    dispatch({ type: 'STOP_RECORDING' });
    
    // Stop recorder and finalize audio
    const audioBlob = await recorder.stop();
    
    // Request final transcript
    websocket.send({ type: 'finalize' });
    
    // Wait for finalization
    await waitForFinalization();
    
    dispatch({ type: 'FINALIZE_TRANSCRIPT' });
  }, [recorder, websocket]);

  const saveSession = useCallback(async (): Promise<string> => {
    dispatch({ type: 'SAVE_SESSION' });
    
    // Persist to backend
    const sessionId = await persistence.saveSession({
      session: state.session!,
      recording: state.recording!,
      transcript: state.transcript!,
    });
    
    dispatch({ type: 'SESSION_SAVED', payload: { sessionId } });
    options.onSessionComplete?.(state.session!);
    
    return sessionId;
  }, [state, persistence, options.onSessionComplete]);

  return {
    state,
    isRecording: state.phase === 'Recording',
    isPaused: state.phase === 'Paused',
    isProcessing: state.phase === 'Processing',
    canRecord: state.permissions.microphone === 'granted' && state.connection.status === 'connected',
    startSession,
    pauseSession: () => dispatch({ type: 'PAUSE_RECORDING' }),
    resumeSession: () => dispatch({ type: 'RESUME_RECORDING' }),
    stopSession,
    saveSession,
    discardSession: () => dispatch({ type: 'DISCARD_SESSION' }),
    playbackState: state.phase === 'Reviewing' ? playback.state : null,
    seekTo: playback.seek,
    togglePlayback: playback.togglePlayPause,
    transcript: state.transcript,
    editSegment: (id, text) => persistence.updateSegment(id, text),
    currentWordId: timestampSync.currentWordId,
    pendingIntents: state.intents.filter(i => !i.isExecuted),
    executeIntent: async (id) => { /* Execute via Voice-CLI */ },
    dismissIntent: (id) => { /* Mark as dismissed */ },
    requestPermissions,
    exportTranscript: (format) => formatTranscriptForExport(state.transcript!, format),
  };
}
```

### 5.3 useSessionPersistence

Handles IndexedDB storage for resilient recording.

```typescript
interface UseSessionPersistenceOptions {
  readonly sessionId: string | undefined;
  readonly enabled: boolean;
  readonly syncInterval?: number;         // ms between sync attempts
}

interface UseSessionPersistenceReturn {
  readonly isSynced: boolean;
  readonly pendingChunks: number;
  readonly storageUsed: number;           // bytes
  
  readonly storeChunk: (chunk: AudioChunk) => Promise<void>;
  readonly storeSegment: (segment: TranscriptSegment) => Promise<void>;
  readonly updateSegment: (segmentId: string, text: string) => Promise<void>;
  readonly saveSession: (data: SessionData) => Promise<string>;
  readonly loadSession: (sessionId: string) => Promise<SessionData | null>;
  readonly deleteSession: (sessionId: string) => Promise<void>;
  readonly recoverUnfinished: () => Promise<VoiceSession[]>;
}

// IndexedDB Schema
interface VoiceSessionDB {
  sessions: {
    key: string;
    value: VoiceSession;
    indexes: { projectId: string; createdAt: number };
  };
  audioChunks: {
    key: string;
    value: AudioChunk & { sessionId: string };
    indexes: { sessionId: string; isSynced: boolean };
  };
  transcriptSegments: {
    key: string;
    value: TranscriptSegment & { sessionId: string };
    indexes: { sessionId: string };
  };
}
```

### 5.4 useSessionRecovery

Recovers interrupted sessions on page load.

```typescript
interface UseSessionRecoveryOptions {
  readonly onSessionRecovered?: (session: VoiceSession) => void;
  readonly autoRecover?: boolean;
}

interface UseSessionRecoveryReturn {
  readonly unfinishedSessions: readonly VoiceSession[];
  readonly isRecovering: boolean;
  readonly recoverSession: (sessionId: string) => Promise<void>;
  readonly discardSession: (sessionId: string) => Promise<void>;
  readonly discardAll: () => Promise<void>;
}

function useSessionRecovery(options: UseSessionRecoveryOptions): UseSessionRecoveryReturn {
  const [unfinishedSessions, setUnfinishedSessions] = useState<VoiceSession[]>([]);
  const [isRecovering, setIsRecovering] = useState(false);
  
  useEffect(() => {
    async function checkForUnfinished() {
      const db = await openVoiceSessionDB();
      const sessions = await db.getAll('sessions');
      const unfinished = sessions.filter(s => 
        s.phase !== 'Complete' && s.phase !== 'Idle'
      );
      setUnfinishedSessions(unfinished);
      
      if (options.autoRecover && unfinished.length === 1) {
        await recoverSession(unfinished[0].id);
      }
    }
    
    checkForUnfinished();
  }, []);
  
  const recoverSession = async (sessionId: string) => {
    setIsRecovering(true);
    try {
      const db = await openVoiceSessionDB();
      const session = await db.get('sessions', sessionId);
      const chunks = await db.getAllFromIndex('audioChunks', 'sessionId', sessionId);
      const segments = await db.getAllFromIndex('transcriptSegments', 'sessionId', sessionId);
      
      // Reconstruct audio blob from chunks
      const audioBlob = await reconstructAudioBlob(chunks);
      
      options.onSessionRecovered?.({
        ...session!,
        phase: 'Reviewing',
      });
    } finally {
      setIsRecovering(false);
    }
  };
  
  return { unfinishedSessions, isRecovering, recoverSession, discardSession, discardAll };
}
```

---

## 6. Component Specifications

### 6.1 VoiceSessionManager (Container)

```typescript
interface VoiceSessionManagerProps {
  readonly projectId: string;
  readonly conversationId?: string;
  readonly mode?: 'inline' | 'modal' | 'fullscreen';
  readonly onIntentExecute?: (intent: RecognizedIntent, result: unknown) => void;
  readonly onComplete?: (session: VoiceSession) => void;
  readonly onCancel?: () => void;
  readonly className?: string;
}

function VoiceSessionManager({ projectId, conversationId, mode = 'inline', ...props }: VoiceSessionManagerProps) {
  const manager = useVoiceSessionManager({
    projectId,
    conversationId,
    onIntentRecognized: handleIntent,
    onSessionComplete: props.onComplete,
  });

  return (
    <SessionContext.Provider value={manager}>
      <div className={cn('voice-session-manager', modeStyles[mode], props.className)}>
        {/* Phase-based UI rendering */}
        {manager.state.phase === 'Idle' && (
          <SessionStarter onStart={manager.startSession} />
        )}
        
        {(manager.state.phase === 'Recording' || manager.state.phase === 'Paused') && (
          <RecordingInterface
            isRecording={manager.isRecording}
            isPaused={manager.isPaused}
            duration={manager.state.session?.duration ?? 0}
            transcript={manager.transcript}
            onPause={manager.pauseSession}
            onResume={manager.resumeSession}
            onStop={manager.stopSession}
          />
        )}
        
        {manager.state.phase === 'Processing' && (
          <ProcessingIndicator 
            progress={/* transcription progress */}
          />
        )}
        
        {manager.state.phase === 'Reviewing' && (
          <ReviewInterface
            session={manager.state.session!}
            recording={manager.state.recording!}
            transcript={manager.transcript!}
            playbackState={manager.playbackState}
            currentWordId={manager.currentWordId}
            onSeek={manager.seekTo}
            onTogglePlayback={manager.togglePlayback}
            onEditSegment={manager.editSegment}
            onSave={manager.saveSession}
            onDiscard={manager.discardSession}
          />
        )}
        
        {/* Intent execution panel */}
        {manager.pendingIntents.length > 0 && (
          <IntentPanel
            intents={manager.pendingIntents}
            onExecute={manager.executeIntent}
            onDismiss={manager.dismissIntent}
          />
        )}
        
        {/* Error display */}
        {manager.state.errors.length > 0 && (
          <ErrorBanner errors={manager.state.errors} />
        )}
      </div>
    </SessionContext.Provider>
  );
}
```

### 6.2 RecordingInterface

Active recording UI combining recorder and live transcript.

```typescript
interface RecordingInterfaceProps {
  readonly isRecording: boolean;
  readonly isPaused: boolean;
  readonly duration: number;
  readonly transcript: TranscriptDocument | null;
  readonly onPause: () => void;
  readonly onResume: () => void;
  readonly onStop: () => void;
}

function RecordingInterface({
  isRecording,
  isPaused,
  duration,
  transcript,
  onPause,
  onResume,
  onStop,
}: RecordingInterfaceProps) {
  return (
    <div className="grid grid-rows-[1fr_auto_auto] h-full gap-4">
      {/* Live waveform visualization */}
      <div className="flex-1 overflow-hidden">
        <WaveformVisualizer
          isActive={isRecording}
          isPaused={isPaused}
        />
      </div>
      
      {/* Live transcript */}
      <div className="h-48 overflow-y-auto border rounded-lg p-4">
        <LiveTranscript
          segments={transcript?.segments ?? []}
          partialText={transcript?.partialText ?? ''}
        />
      </div>
      
      {/* Controls */}
      <div className="flex items-center justify-between">
        <DurationDisplay duration={duration} isActive={isRecording} />
        
        <div className="flex items-center gap-4">
          {isRecording ? (
            <Button variant="outline" onClick={onPause}>
              <Pause className="h-4 w-4 mr-2" />
              Pause
            </Button>
          ) : (
            <Button variant="outline" onClick={onResume}>
              <Play className="h-4 w-4 mr-2" />
              Resume
            </Button>
          )}
          
          <Button variant="destructive" onClick={onStop}>
            <Square className="h-4 w-4 mr-2" />
            Stop Recording
          </Button>
        </div>
        
        <ConnectionIndicator />
      </div>
    </div>
  );
}
```

### 6.3 ReviewInterface

Post-recording review combining player and editable transcript.

```typescript
interface ReviewInterfaceProps {
  readonly session: VoiceSession;
  readonly recording: SessionRecording;
  readonly transcript: TranscriptDocument;
  readonly playbackState: PlaybackState | null;
  readonly currentWordId: string | null;
  readonly onSeek: (time: number) => void;
  readonly onTogglePlayback: () => void;
  readonly onEditSegment: (segmentId: string, text: string) => void;
  readonly onSave: () => Promise<string>;
  readonly onDiscard: () => void;
}

function ReviewInterface({
  session,
  recording,
  transcript,
  playbackState,
  currentWordId,
  onSeek,
  onTogglePlayback,
  onEditSegment,
  onSave,
  onDiscard,
}: ReviewInterfaceProps) {
  const [isSaving, setIsSaving] = useState(false);
  
  const handleSave = async () => {
    setIsSaving(true);
    try {
      await onSave();
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="grid grid-rows-[auto_1fr_auto] h-full gap-4">
      {/* Audio player with waveform */}
      <AudioPlayer
        source={{
          id: session.id,
          url: URL.createObjectURL(recording.audioBlob),
          mimeType: recording.mimeType,
          duration: session.duration / 1000,
          sampleRate: session.metadata.sampleRate,
          channels: session.metadata.channels,
        }}
        segments={session.speakers.map(s => ({
          id: s.id,
          start: 0,
          end: session.duration / 1000,
          label: s.label,
          color: s.color,
          speakerId: s.id,
        }))}
        onPlayheadUpdate={({ currentWordId }) => {
          // Handled by timestampSync
        }}
      />
      
      {/* Editable transcript */}
      <div className="flex-1 overflow-y-auto">
        <TranscriptionDisplay
          segments={transcript.segments}
          currentWordId={currentWordId}
          isEditable={true}
          onWordClick={(wordId, timestamp) => onSeek(timestamp)}
          onSegmentEdit={onEditSegment}
        />
      </div>
      
      {/* Actions */}
      <div className="flex items-center justify-between border-t pt-4">
        <SessionMetadataDisplay session={session} />
        
        <div className="flex items-center gap-4">
          <ExportMenu transcript={transcript} session={session} />
          
          <Button variant="outline" onClick={onDiscard}>
            Discard
          </Button>
          
          <Button onClick={handleSave} disabled={isSaving}>
            {isSaving ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
            Save Session
          </Button>
        </div>
      </div>
    </div>
  );
}
```

### 6.4 IntentPanel

Displays recognized commands for execution.

```typescript
interface IntentPanelProps {
  readonly intents: readonly RecognizedIntent[];
  readonly onExecute: (intentId: string) => Promise<void>;
  readonly onDismiss: (intentId: string) => void;
}

function IntentPanel({ intents, onExecute, onDismiss }: IntentPanelProps) {
  const [executingId, setExecutingId] = useState<string | null>(null);

  return (
    <div className="fixed bottom-4 right-4 w-80 space-y-2">
      <AnimatePresence>
        {intents.map(intent => (
          <motion.div
            key={intent.id}
            initial={{ opacity: 0, y: 20, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, x: 100 }}
            className="bg-card border rounded-lg p-4 shadow-lg"
          >
            <div className="flex items-start justify-between">
              <div>
                <Badge variant="outline">{intent.type}</Badge>
                <p className="text-sm text-muted-foreground mt-1">
                  Confidence: {Math.round(intent.confidence * 100)}%
                </p>
              </div>
              
              <Button
                variant="ghost"
                size="icon"
                onClick={() => onDismiss(intent.id)}
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
            
            <div className="mt-3 flex items-center gap-2">
              <Button
                size="sm"
                onClick={async () => {
                  setExecutingId(intent.id);
                  await onExecute(intent.id);
                  setExecutingId(null);
                }}
                disabled={executingId === intent.id}
              >
                {executingId === intent.id ? (
                  <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                ) : (
                  <Play className="h-4 w-4 mr-1" />
                )}
                Execute
              </Button>
            </div>
          </motion.div>
        ))}
      </AnimatePresence>
    </div>
  );
}
```

---

## 7. Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              User Actions                                │
└─────────────────────────────────────────────────────────────────────────┘
                                     │
          ┌──────────────────────────┼──────────────────────────┐
          │                          │                          │
          ▼                          ▼                          ▼
   ┌─────────────┐           ┌─────────────┐           ┌─────────────┐
   │   Record    │           │   Playback  │           │   Edit      │
   │   Actions   │           │   Actions   │           │   Actions   │
   └──────┬──────┘           └──────┬──────┘           └──────┬──────┘
          │                          │                          │
          ▼                          ▼                          ▼
   ┌─────────────┐           ┌─────────────┐           ┌─────────────┐
   │ useVoice    │           │ useAudio    │           │ useTranscrip│
   │ Recorder    │           │ Playback    │           │ tion        │
   └──────┬──────┘           └──────┬──────┘           └──────┬──────┘
          │                          │                          │
          └──────────────────────────┼──────────────────────────┘
                                     │
                                     ▼
                          ┌─────────────────────┐
                          │  useVoiceSession    │
                          │      Manager        │
                          └──────────┬──────────┘
                                     │
          ┌──────────────────────────┼──────────────────────────┐
          │                          │                          │
          ▼                          ▼                          ▼
   ┌─────────────┐           ┌─────────────┐           ┌─────────────┐
   │  IndexedDB  │           │  WebSocket  │           │   REST API  │
   │ (Resilient) │           │ (Real-time) │           │  (Persist)  │
   └──────┬──────┘           └──────┬──────┘           └──────┬──────┘
          │                          │                          │
          └──────────────────────────┼──────────────────────────┘
                                     │
                                     ▼
                          ┌─────────────────────┐
                          │   Voice-CLI Service │
                          │    (Port 8084)      │
                          └─────────────────────┘
                                     │
          ┌──────────────────────────┼──────────────────────────┐
          │                          │                          │
          ▼                          ▼                          ▼
   ┌─────────────┐           ┌─────────────┐           ┌─────────────┐
   │  Whisper    │           │   Intent    │           │  Session    │
   │Transcription│           │ Recognition │           │  Storage    │
   └─────────────┘           └─────────────┘           └─────────────┘
```

---

## 8. Error Handling

### 8.1 Error Types

```typescript
interface SessionError {
  readonly code: SessionErrorCode;
  readonly message: string;
  readonly timestamp: number;
  readonly recoverable: boolean;
  readonly context?: Record<string, unknown>;
}

type SessionErrorCode =
  // Permission errors (11000-11099)
  | 'PERMISSION_DENIED'        // 11001
  | 'PERMISSION_REVOKED'       // 11002
  
  // Connection errors (11100-11199)
  | 'CONNECTION_FAILED'        // 11101
  | 'CONNECTION_LOST'          // 11102
  | 'CONNECTION_TIMEOUT'       // 11103
  | 'RECONNECT_EXHAUSTED'      // 11104
  
  // Recording errors (11200-11299)
  | 'RECORDING_FAILED'         // 11201
  | 'AUDIO_CONTEXT_FAILED'     // 11202
  | 'CHUNK_SYNC_FAILED'        // 11203
  
  // Transcription errors (11300-11399)
  | 'TRANSCRIPTION_FAILED'     // 11301
  | 'WHISPER_MODEL_UNAVAILABLE'// 11302
  | 'LANGUAGE_UNSUPPORTED'     // 11303
  
  // Storage errors (11400-11499)
  | 'STORAGE_FULL'             // 11401
  | 'STORAGE_UNAVAILABLE'      // 11402
  | 'SAVE_FAILED'              // 11403
  
  // Intent errors (11500-11599)
  | 'INTENT_EXECUTION_FAILED'  // 11501
  | 'INTENT_TIMEOUT';          // 11502
```

### 8.2 Error Recovery Strategies

```typescript
const errorRecoveryStrategies: Record<SessionErrorCode, RecoveryStrategy> = {
  CONNECTION_LOST: {
    action: 'reconnect',
    maxRetries: 5,
    backoff: 'exponential',
    userMessage: 'Connection lost. Reconnecting...',
  },
  
  CHUNK_SYNC_FAILED: {
    action: 'queue',
    maxRetries: 10,
    backoff: 'linear',
    userMessage: null, // Silent retry
  },
  
  STORAGE_FULL: {
    action: 'prompt',
    userMessage: 'Storage is full. Please free up space or save to cloud.',
    options: ['Clear old sessions', 'Save to cloud'],
  },
  
  PERMISSION_DENIED: {
    action: 'block',
    userMessage: 'Microphone access is required for voice recording.',
    options: ['Open settings', 'Cancel'],
  },
};
```

---

## 9. Performance Requirements

| Metric | Threshold |
|--------|-----------|
| Session start latency | < 500ms |
| Audio chunk capture interval | 100ms |
| Chunk sync latency | < 200ms |
| Partial transcript latency | < 300ms |
| Phase transition time | < 100ms |
| IndexedDB write latency | < 50ms |
| Session recovery time | < 2s |
| Memory usage (10min recording) | < 100MB |

---

## 10. File Structure

```
src/
├── components/
│   └── voice-session/
│       ├── VoiceSessionManager.tsx    # Container orchestrator
│       ├── SessionStarter.tsx         # Initial prompt
│       ├── RecordingInterface.tsx     # Active recording UI
│       ├── ReviewInterface.tsx        # Post-recording review
│       ├── ProcessingIndicator.tsx    # Transcription progress
│       ├── IntentPanel.tsx            # Command execution
│       ├── SessionRecoveryModal.tsx   # Recover interrupted
│       ├── ExportMenu.tsx             # Export options
│       ├── ConnectionIndicator.tsx    # WebSocket status
│       └── index.ts
├── hooks/
│   ├── useVoiceSessionManager.ts      # Primary orchestration
│   ├── useSessionPersistence.ts       # IndexedDB storage
│   ├── useSessionRecovery.ts          # Interrupted session recovery
│   └── useIntentExecution.ts          # Command execution
├── contexts/
│   └── SessionContext.tsx             # Session state context
├── reducers/
│   └── sessionReducer.ts              # State management
└── lib/
    └── voice-session/
        ├── types.ts                   # Shared types
        ├── errors.ts                  # Error definitions
        ├── db.ts                      # IndexedDB schema
        └── utils.ts                   # Helpers
```

---

## Appendix A: Cross-References

- **Voice Recorder**: [01-voice-recorder.md](./01-voice-recorder.md)
- **Transcription Display**: [02-transcription-display.md](./02-transcription-display.md)
- **Audio Player**: [03-audio-player.md](./03-audio-player.md)
- **Voice-CLI OpenAPI**: [../14-microservices/16-voice-cli-openapi.md](../14-microservices/16-voice-cli-openapi.md)
- **Voice-CLI Service**: [/memories/architecture/microservices/voice-cli-service](/memories/architecture/microservices/voice-cli-service)
- **Split Database System**: [/memories/architecture/split-database-system](/memories/architecture/split-database-system)
- **Resilient Execution**: [/memories/features/resilient-execution-system](/memories/features/resilient-execution-system)
