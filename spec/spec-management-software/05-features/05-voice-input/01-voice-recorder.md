# Voice Recorder Component

**Version:** 2.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  

---

## Overview

A React component providing browser-based audio capture with real-time waveform visualization, WebSocket streaming to the Voice-CLI service, and visual feedback for VAD state, transcription progress, and command recognition.

**Cross-References:**
- [Voice Input Overview](./00-overview.md) — Feature context
- [Voice-CLI Service](../../14-microservices/15-voice-cli-service.md) — Backend integration
- [Voice-CLI OpenAPI](../../14-microservices/16-voice-cli-openapi.md) — WebSocket protocol
- [Transcription Display](./02-transcription-display.md) — Transcript rendering
- [Realtime Communication](../18-realtime/00-overview.md) — WebSocket patterns
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md) — React standards

---

## Component Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        VoiceRecorder                                 │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    WaveformVisualizer                        │   │
│  │  ┌─────────────────────────────────────────────────────────┐│   │
│  │  │ ▂▃▅▇█▇▅▃▂▁▂▃▅▇█▇▅▃▂▁▂▃▅▇█▇▅▃▂▁▂▃▅▇█▇▅▃▂▁▂▃▅▇█▇▅▃▂ ││   │
│  │  └─────────────────────────────────────────────────────────┘│   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    TranscriptPreview                         │   │
│  │  "Create a new feature spec for authentication..."          │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    RecorderControls                          │   │
│  │  ┌───────┐  ┌───────────┐  ┌────────┐  ┌──────────────┐    │   │
│  │  │ 🎤    │  │ ⏸ Pause   │  │ ⏹ Stop │  │ ⚙️ Settings  │    │   │
│  │  │Record │  └───────────┘  └────────┘  └──────────────┘    │   │
│  │  └───────┘                                                  │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    StatusIndicator                           │   │
│  │  🟢 Listening • VAD: Active • Model: large-v3               │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Audio Capture | Browser-based microphone recording via Web Audio API | High |
| WebSocket Streaming | Real-time audio streaming to Voice-CLI service | High |
| Waveform Visualization | Canvas-based real-time audio level display | High |
| VAD Feedback | Visual indication of speech detection state | High |
| Permission Handling | Graceful microphone permission flow | High |
| Recording Controls | Start, stop, pause, resume with keyboard support | High |
| Live Transcription | Display partial and final transcripts | High |
| Intent Display | Show recognized commands with confidence | Medium |
| Audio Enhancement | Noise suppression, echo cancellation | Medium |
| Recording Timer | Duration display with limit warning | Medium |
| Device Selection | Choose input device if multiple mics | Low |

---

## Component Hierarchy

```
VoiceRecorder/
├── index.ts                    # Public exports
├── VoiceRecorder.tsx           # Main container component
├── components/
│   ├── WaveformVisualizer.tsx  # Canvas-based audio visualization
│   ├── TranscriptPreview.tsx   # Live transcript display
│   ├── RecorderControls.tsx    # Record/pause/stop buttons
│   ├── StatusIndicator.tsx     # Connection & VAD status
│   ├── SettingsPanel.tsx       # Audio/model configuration
│   └── PermissionPrompt.tsx    # Microphone permission request
├── hooks/
│   ├── useVoiceRecorder.ts     # Main recording logic
│   ├── useAudioCapture.ts      # Web Audio API capture
│   ├── useVoiceWebSocket.ts    # WebSocket connection
│   ├── useWaveformData.ts      # Audio visualization data
│   └── useAudioLevel.ts        # Real-time audio level
├── utils/
│   ├── audio-encoder.ts        # PCM16 encoding utilities
│   ├── waveform-renderer.ts    # Canvas rendering helpers
│   └── audio-worklet.ts        # AudioWorklet processor
├── types.ts                    # TypeScript interfaces
└── constants.ts                # Configuration constants
```

---

## Type Definitions

```typescript
// types.ts

import type { CommandIntent } from '@/types/voice';

// === Recording State ===

export const RecordingStatus = {
  Idle: 'idle',
  Requesting: 'requesting',     // Requesting mic permission
  Connecting: 'connecting',     // WebSocket connecting
  Ready: 'ready',               // Ready to record
  Recording: 'recording',       // Actively recording
  Paused: 'paused',             // Recording paused
  Processing: 'processing',     // Processing final audio
  Error: 'error',               // Error state
} as const;

export type RecordingStatus = typeof RecordingStatus[keyof typeof RecordingStatus];

export const VADState = {
  Silence: 'silence',
  SpeechStart: 'speech_start',
  Speech: 'speech',
  SpeechEnd: 'speech_end',
} as const;

export type VADState = typeof VADState[keyof typeof VADState];

export const ConnectionStatus = {
  Disconnected: 'disconnected',
  Connecting: 'connecting',
  Connected: 'connected',
  Reconnecting: 'reconnecting',
  Error: 'error',
} as const;

export type ConnectionStatus = typeof ConnectionStatus[keyof typeof ConnectionStatus];

// === Audio Configuration ===

export interface AudioConfig {
  readonly sampleRate: 8000 | 16000 | 24000 | 44100 | 48000;
  readonly channelCount: 1;
  readonly echoCancellation: boolean;
  readonly noiseSuppression: boolean;
  readonly autoGainControl: boolean;
}

export interface SessionConfig {
  readonly audioFormat: 'pcm16';
  readonly sampleRate: number;
  readonly vadEnabled: boolean;
  readonly vadThreshold: number;
  readonly whisperModel: WhisperModel;
  readonly language: string;
  readonly intentEnabled: boolean;
  readonly llmFallback: boolean;
}

export const WhisperModel = {
  Tiny: 'tiny',
  Base: 'base',
  Small: 'small',
  Medium: 'medium',
  LargeV3: 'large-v3',
} as const;

export type WhisperModel = typeof WhisperModel[keyof typeof WhisperModel];

// === Transcript Types ===

export interface TranscriptWord {
  readonly word: string;
  readonly start: number;
  readonly end: number;
  readonly probability: number;
}

export interface Transcript {
  readonly id: string;
  readonly text: string;
  readonly isFinal: boolean;
  readonly language: string;
  readonly confidence: number;
  readonly words: readonly TranscriptWord[];
  readonly durationMs: number;
}

// === Intent Types ===

export const RecognitionMethod = {
  Grammar: 'grammar',
  LLM: 'llm',
  Hybrid: 'hybrid',
} as const;

export type RecognitionMethod = typeof RecognitionMethod[keyof typeof RecognitionMethod];

export interface SlotValue {
  readonly raw: string;
  readonly normalized: unknown;
  readonly type: string;
  readonly confidence: number;
}

export interface IntentMatch {
  readonly intent: CommandIntent;
  readonly confidence: number;
  readonly slots: Readonly<Record<string, SlotValue>>;
  readonly rawText: string;
  readonly method: RecognitionMethod;
}

// === Command Types ===

export interface FollowupAction {
  readonly label: string;
  readonly intent: CommandIntent;
  readonly slots: Readonly<Record<string, string>>;
}

export interface CommandResult {
  readonly intent: CommandIntent;
  readonly success: boolean;
  readonly message: string;
  readonly data?: unknown;
  readonly speakText?: string;
  readonly actions: readonly FollowupAction[];
}

// === WebSocket Message Types ===

export const WSMessageType = {
  // Client → Server
  Audio: 'audio',
  Config: 'config',
  Control: 'control',
  Text: 'text',
  // Server → Client
  Status: 'status',
  Partial: 'partial',
  Transcript: 'transcript',
  Intent: 'intent',
  Command: 'command',
  VAD: 'vad',
  Level: 'level',
  Error: 'error',
} as const;

export type WSMessageType = typeof WSMessageType[keyof typeof WSMessageType];

export interface WSMessage<T = unknown> {
  readonly type: WSMessageType;
  readonly id?: string;
  readonly payload: T;
}

// === Component Props ===

export interface VoiceRecorderProps {
  readonly projectId?: string;
  readonly conversationId?: string;
  readonly autoConnect?: boolean;
  readonly showWaveform?: boolean;
  readonly showTranscript?: boolean;
  readonly showControls?: boolean;
  readonly showStatus?: boolean;
  readonly config?: Partial<SessionConfig>;
  readonly onTranscript?: (transcript: Transcript) => void;
  readonly onIntent?: (intent: IntentMatch) => void;
  readonly onCommand?: (result: CommandResult) => void;
  readonly onError?: (error: VoiceError) => void;
  readonly onStatusChange?: (status: RecordingStatus) => void;
  readonly className?: string;
}

export interface WaveformVisualizerProps {
  readonly audioData: Float32Array | null;
  readonly isRecording: boolean;
  readonly vadState: VADState;
  readonly width?: number;
  readonly height?: number;
  readonly barWidth?: number;
  readonly barGap?: number;
  readonly className?: string;
}

export interface TranscriptPreviewProps {
  readonly partialText: string;
  readonly finalTranscripts: readonly Transcript[];
  readonly isProcessing: boolean;
  readonly maxHeight?: number;
  readonly className?: string;
}

export interface RecorderControlsProps {
  readonly status: RecordingStatus;
  readonly onStart: () => void;
  readonly onPause: () => void;
  readonly onResume: () => void;
  readonly onStop: () => void;
  readonly onCancel: () => void;
  readonly disabled?: boolean;
  readonly className?: string;
}

export interface StatusIndicatorProps {
  readonly connectionStatus: ConnectionStatus;
  readonly recordingStatus: RecordingStatus;
  readonly vadState: VADState;
  readonly audioLevel: number;
  readonly model: WhisperModel;
  readonly language: string;
  readonly className?: string;
}

// === Hook Return Types ===

export interface UseVoiceRecorderReturn {
  // State
  readonly status: RecordingStatus;
  readonly connectionStatus: ConnectionStatus;
  readonly vadState: VADState;
  readonly audioLevel: number;
  readonly partialTranscript: string;
  readonly transcripts: readonly Transcript[];
  readonly intents: readonly IntentMatch[];
  readonly commands: readonly CommandResult[];
  readonly error: VoiceError | null;
  readonly sessionId: string | null;
  
  // Audio data for visualization
  readonly audioData: Float32Array | null;
  readonly frequencyData: Uint8Array | null;
  
  // Actions
  readonly connect: () => Promise<void>;
  readonly disconnect: () => void;
  readonly startRecording: () => Promise<void>;
  readonly pauseRecording: () => void;
  readonly resumeRecording: () => void;
  readonly stopRecording: () => void;
  readonly cancelRecording: () => void;
  readonly sendText: (text: string) => void;
  readonly updateConfig: (config: Partial<SessionConfig>) => void;
  readonly clearTranscripts: () => void;
}

export interface UseAudioCaptureReturn {
  readonly isCapturing: boolean;
  readonly audioLevel: number;
  readonly audioData: Float32Array | null;
  readonly frequencyData: Uint8Array | null;
  readonly error: Error | null;
  readonly start: (config: AudioConfig) => Promise<void>;
  readonly stop: () => void;
  readonly pause: () => void;
  readonly resume: () => void;
}

export interface UseVoiceWebSocketReturn {
  readonly status: ConnectionStatus;
  readonly sessionId: string | null;
  readonly error: VoiceError | null;
  readonly connect: (url: string) => Promise<void>;
  readonly disconnect: () => void;
  readonly send: <T>(message: WSMessage<T>) => void;
  readonly sendAudio: (audioData: Float32Array) => void;
  readonly sendConfig: (config: SessionConfig) => void;
  readonly sendControl: (action: ControlAction) => void;
}

export type ControlAction = 'start' | 'stop' | 'pause' | 'resume' | 'cancel';

// === Error Types ===

export interface VoiceError {
  readonly code: number;
  readonly constant: string;
  readonly message: string;
  readonly details?: Record<string, unknown>;
  readonly retryable: boolean;
}
```

---

## Constants

```typescript
// constants.ts

import type { AudioConfig, SessionConfig, WhisperModel } from './types';

export const VOICE_WS_URL = import.meta.env.VITE_VOICE_WS_URL || 'ws://localhost:8086/ws/stream';

export const DEFAULT_AUDIO_CONFIG: AudioConfig = {
  sampleRate: 16000,
  channelCount: 1,
  echoCancellation: true,
  noiseSuppression: true,
  autoGainControl: true,
} as const;

export const DEFAULT_SESSION_CONFIG: SessionConfig = {
  audioFormat: 'pcm16',
  sampleRate: 16000,
  vadEnabled: true,
  vadThreshold: 0.5,
  whisperModel: 'large-v3',
  language: 'auto',
  intentEnabled: true,
  llmFallback: true,
} as const;

// Audio processing
export const AUDIO_CHUNK_SIZE = 4096;           // Samples per chunk
export const AUDIO_CHUNK_MS = 100;              // Target chunk duration
export const MAX_RECORDING_DURATION_MS = 300000; // 5 minutes

// Waveform visualization
export const WAVEFORM_BAR_COUNT = 64;
export const WAVEFORM_BAR_WIDTH = 3;
export const WAVEFORM_BAR_GAP = 2;
export const WAVEFORM_MIN_HEIGHT = 2;
export const WAVEFORM_SMOOTHING = 0.8;

// WebSocket
export const WS_RECONNECT_DELAY_MS = 1000;
export const WS_MAX_RECONNECT_ATTEMPTS = 5;
export const WS_HEARTBEAT_INTERVAL_MS = 30000;

// Whisper models metadata
export const WHISPER_MODELS: Record<WhisperModel, { name: string; size: string; quality: string }> = {
  tiny: { name: 'Tiny', size: '39M', quality: 'Fast, lower accuracy' },
  base: { name: 'Base', size: '74M', quality: 'Good balance' },
  small: { name: 'Small', size: '244M', quality: 'Better accuracy' },
  medium: { name: 'Medium', size: '769M', quality: 'High accuracy' },
  'large-v3': { name: 'Large v3', size: '1.5B', quality: 'Best accuracy' },
} as const;
```

---

## Main Hook Implementation

```typescript
// hooks/useVoiceRecorder.ts

import { useCallback, useEffect, useReducer, useRef } from 'react';
import { useAudioCapture } from './useAudioCapture';
import { useVoiceWebSocket } from './useVoiceWebSocket';
import type {
  UseVoiceRecorderReturn,
  RecordingStatus,
  VADState,
  SessionConfig,
  Transcript,
  IntentMatch,
  CommandResult,
  VoiceError,
  WSMessage,
} from '../types';
import {
  RecordingStatus as Status,
  VADState as VAD,
  WSMessageType,
  ConnectionStatus,
} from '../types';
import { DEFAULT_SESSION_CONFIG, VOICE_WS_URL } from '../constants';

// === State ===

interface RecorderState {
  readonly status: RecordingStatus;
  readonly vadState: VADState;
  readonly partialTranscript: string;
  readonly transcripts: readonly Transcript[];
  readonly intents: readonly IntentMatch[];
  readonly commands: readonly CommandResult[];
  readonly error: VoiceError | null;
  readonly sessionId: string | null;
  readonly config: SessionConfig;
}

const initialState: RecorderState = {
  status: Status.Idle,
  vadState: VAD.Silence,
  partialTranscript: '',
  transcripts: [],
  intents: [],
  commands: [],
  error: null,
  sessionId: null,
  config: DEFAULT_SESSION_CONFIG,
};

// === Actions ===

type RecorderAction =
  | { type: 'SET_STATUS'; payload: RecordingStatus }
  | { type: 'SET_VAD_STATE'; payload: VADState }
  | { type: 'SET_PARTIAL'; payload: string }
  | { type: 'ADD_TRANSCRIPT'; payload: Transcript }
  | { type: 'ADD_INTENT'; payload: IntentMatch }
  | { type: 'ADD_COMMAND'; payload: CommandResult }
  | { type: 'SET_ERROR'; payload: VoiceError | null }
  | { type: 'SET_SESSION_ID'; payload: string | null }
  | { type: 'UPDATE_CONFIG'; payload: Partial<SessionConfig> }
  | { type: 'CLEAR_TRANSCRIPTS' }
  | { type: 'RESET' };

function recorderReducer(state: RecorderState, action: RecorderAction): RecorderState {
  switch (action.type) {
    case 'SET_STATUS':
      return { ...state, status: action.payload };
    case 'SET_VAD_STATE':
      return { ...state, vadState: action.payload };
    case 'SET_PARTIAL':
      return { ...state, partialTranscript: action.payload };
    case 'ADD_TRANSCRIPT':
      return {
        ...state,
        transcripts: [...state.transcripts, action.payload],
        partialTranscript: '',
      };
    case 'ADD_INTENT':
      return { ...state, intents: [...state.intents, action.payload] };
    case 'ADD_COMMAND':
      return { ...state, commands: [...state.commands, action.payload] };
    case 'SET_ERROR':
      return { ...state, error: action.payload, status: action.payload ? Status.Error : state.status };
    case 'SET_SESSION_ID':
      return { ...state, sessionId: action.payload };
    case 'UPDATE_CONFIG':
      return { ...state, config: { ...state.config, ...action.payload } };
    case 'CLEAR_TRANSCRIPTS':
      return { ...state, transcripts: [], intents: [], commands: [], partialTranscript: '' };
    case 'RESET':
      return initialState;
    default: {
      const _exhaustive: never = action;
      return state;
    }
  }
}

// === Hook ===

interface UseVoiceRecorderOptions {
  readonly projectId?: string;
  readonly conversationId?: string;
  readonly config?: Partial<SessionConfig>;
  readonly onTranscript?: (transcript: Transcript) => void;
  readonly onIntent?: (intent: IntentMatch) => void;
  readonly onCommand?: (result: CommandResult) => void;
  readonly onError?: (error: VoiceError) => void;
  readonly onStatusChange?: (status: RecordingStatus) => void;
}

export function useVoiceRecorder(options: UseVoiceRecorderOptions = {}): UseVoiceRecorderReturn {
  const {
    projectId,
    conversationId,
    config: initialConfig,
    onTranscript,
    onIntent,
    onCommand,
    onError,
    onStatusChange,
  } = options;

  const [state, dispatch] = useReducer(recorderReducer, {
    ...initialState,
    config: { ...DEFAULT_SESSION_CONFIG, ...initialConfig },
  });

  const callbacksRef = useRef({ onTranscript, onIntent, onCommand, onError, onStatusChange });
  callbacksRef.current = { onTranscript, onIntent, onCommand, onError, onStatusChange };

  // Audio capture
  const audioCapture = useAudioCapture();

  // WebSocket connection
  const ws = useVoiceWebSocket({
    onMessage: useCallback((message: WSMessage) => {
      handleWSMessage(message, dispatch, callbacksRef.current);
    }, []),
  });

  // Status change callback
  useEffect(() => {
    callbacksRef.current.onStatusChange?.(state.status);
  }, [state.status]);

  // Stream audio to WebSocket when recording
  useEffect(() => {
    if (state.status !== Status.Recording || !audioCapture.audioData) return;
    
    ws.sendAudio(audioCapture.audioData);
  }, [state.status, audioCapture.audioData, ws]);

  // === Actions ===

  const connect = useCallback(async () => {
    dispatch({ type: 'SET_STATUS', payload: Status.Connecting });
    
    try {
      const url = buildWSUrl(VOICE_WS_URL, { projectId, conversationId });
      await ws.connect(url);
      dispatch({ type: 'SET_STATUS', payload: Status.Ready });
    } catch (error) {
      const voiceError = createVoiceError(error);
      dispatch({ type: 'SET_ERROR', payload: voiceError });
      callbacksRef.current.onError?.(voiceError);
    }
  }, [ws, projectId, conversationId]);

  const disconnect = useCallback(() => {
    audioCapture.stop();
    ws.disconnect();
    dispatch({ type: 'RESET' });
  }, [audioCapture, ws]);

  const startRecording = useCallback(async () => {
    dispatch({ type: 'SET_STATUS', payload: Status.Requesting });
    
    try {
      // Request microphone permission and start capture
      await audioCapture.start({
        sampleRate: state.config.sampleRate,
        channelCount: 1,
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
      });

      // Connect if not already
      if (ws.status === ConnectionStatus.Disconnected) {
        await connect();
      }

      // Configure session
      ws.sendConfig(state.config);
      
      // Start recording
      ws.sendControl('start');
      dispatch({ type: 'SET_STATUS', payload: Status.Recording });
    } catch (error) {
      const voiceError = createVoiceError(error);
      dispatch({ type: 'SET_ERROR', payload: voiceError });
      callbacksRef.current.onError?.(voiceError);
    }
  }, [audioCapture, ws, state.config, connect]);

  const pauseRecording = useCallback(() => {
    audioCapture.pause();
    ws.sendControl('pause');
    dispatch({ type: 'SET_STATUS', payload: Status.Paused });
  }, [audioCapture, ws]);

  const resumeRecording = useCallback(() => {
    audioCapture.resume();
    ws.sendControl('resume');
    dispatch({ type: 'SET_STATUS', payload: Status.Recording });
  }, [audioCapture, ws]);

  const stopRecording = useCallback(() => {
    dispatch({ type: 'SET_STATUS', payload: Status.Processing });
    audioCapture.stop();
    ws.sendControl('stop');
  }, [audioCapture, ws]);

  const cancelRecording = useCallback(() => {
    audioCapture.stop();
    ws.sendControl('cancel');
    dispatch({ type: 'SET_STATUS', payload: Status.Ready });
    dispatch({ type: 'SET_PARTIAL', payload: '' });
  }, [audioCapture, ws]);

  const sendText = useCallback((text: string) => {
    ws.send({
      type: WSMessageType.Text,
      payload: { text },
    });
  }, [ws]);

  const updateConfig = useCallback((config: Partial<SessionConfig>) => {
    dispatch({ type: 'UPDATE_CONFIG', payload: config });
    if (ws.status === ConnectionStatus.Connected) {
      ws.sendConfig({ ...state.config, ...config });
    }
  }, [ws, state.config]);

  const clearTranscripts = useCallback(() => {
    dispatch({ type: 'CLEAR_TRANSCRIPTS' });
  }, []);

  return {
    // State
    status: state.status,
    connectionStatus: ws.status,
    vadState: state.vadState,
    audioLevel: audioCapture.audioLevel,
    partialTranscript: state.partialTranscript,
    transcripts: state.transcripts,
    intents: state.intents,
    commands: state.commands,
    error: state.error,
    sessionId: state.sessionId,
    
    // Audio data
    audioData: audioCapture.audioData,
    frequencyData: audioCapture.frequencyData,
    
    // Actions
    connect,
    disconnect,
    startRecording,
    pauseRecording,
    resumeRecording,
    stopRecording,
    cancelRecording,
    sendText,
    updateConfig,
    clearTranscripts,
  };
}

// === Helpers ===

function handleWSMessage(
  message: WSMessage,
  dispatch: React.Dispatch<RecorderAction>,
  callbacks: {
    onTranscript?: (t: Transcript) => void;
    onIntent?: (i: IntentMatch) => void;
    onCommand?: (c: CommandResult) => void;
    onError?: (e: VoiceError) => void;
  }
): void {
  switch (message.type) {
    case WSMessageType.Status: {
      const payload = message.payload as { sessionId?: string; status?: string };
      if (payload.sessionId) {
        dispatch({ type: 'SET_SESSION_ID', payload: payload.sessionId });
      }
      if (payload.status === 'closed') {
        dispatch({ type: 'SET_STATUS', payload: Status.Ready });
      }
      break;
    }
    case WSMessageType.Partial: {
      const payload = message.payload as { text: string };
      dispatch({ type: 'SET_PARTIAL', payload: payload.text });
      break;
    }
    case WSMessageType.Transcript: {
      const transcript = message.payload as Transcript;
      dispatch({ type: 'ADD_TRANSCRIPT', payload: transcript });
      dispatch({ type: 'SET_STATUS', payload: Status.Ready });
      callbacks.onTranscript?.(transcript);
      break;
    }
    case WSMessageType.Intent: {
      const intent = message.payload as IntentMatch;
      dispatch({ type: 'ADD_INTENT', payload: intent });
      callbacks.onIntent?.(intent);
      break;
    }
    case WSMessageType.Command: {
      const command = message.payload as CommandResult;
      dispatch({ type: 'ADD_COMMAND', payload: command });
      callbacks.onCommand?.(command);
      break;
    }
    case WSMessageType.VAD: {
      const payload = message.payload as { state: VADState };
      dispatch({ type: 'SET_VAD_STATE', payload: payload.state });
      break;
    }
    case WSMessageType.Error: {
      const error = message.payload as VoiceError;
      dispatch({ type: 'SET_ERROR', payload: error });
      callbacks.onError?.(error);
      break;
    }
    default:
      break;
  }
}

function buildWSUrl(
  baseUrl: string,
  params: { projectId?: string; conversationId?: string }
): string {
  const url = new URL(baseUrl);
  if (params.projectId) url.searchParams.set('project', params.projectId);
  if (params.conversationId) url.searchParams.set('conversation', params.conversationId);
  return url.toString();
}

function createVoiceError(error: unknown): VoiceError {
  if (error instanceof Error) {
    if (error.name === 'NotAllowedError') {
      return {
        code: 11001,
        constant: 'ERR_MICROPHONE_DENIED',
        message: 'Microphone access was denied. Please allow microphone access to use voice features.',
        retryable: true,
      };
    }
    return {
      code: 11000,
      constant: 'ERR_VOICE_UNKNOWN',
      message: error.message,
      retryable: false,
    };
  }
  return {
    code: 11000,
    constant: 'ERR_VOICE_UNKNOWN',
    message: 'An unknown error occurred',
    retryable: false,
  };
}
```

---

## Audio Capture Hook

```typescript
// hooks/useAudioCapture.ts

import { useCallback, useEffect, useRef, useState } from 'react';
import type { UseAudioCaptureReturn, AudioConfig } from '../types';
import { AUDIO_CHUNK_SIZE, DEFAULT_AUDIO_CONFIG } from '../constants';

export function useAudioCapture(): UseAudioCaptureReturn {
  const [isCapturing, setIsCapturing] = useState(false);
  const [audioLevel, setAudioLevel] = useState(0);
  const [audioData, setAudioData] = useState<Float32Array | null>(null);
  const [frequencyData, setFrequencyData] = useState<Uint8Array | null>(null);
  const [error, setError] = useState<Error | null>(null);

  const streamRef = useRef<MediaStream | null>(null);
  const audioContextRef = useRef<AudioContext | null>(null);
  const analyserRef = useRef<AnalyserNode | null>(null);
  const processorRef = useRef<ScriptProcessorNode | null>(null);
  const sourceRef = useRef<MediaStreamAudioSourceNode | null>(null);
  const animationRef = useRef<number | null>(null);
  const isPausedRef = useRef(false);

  const start = useCallback(async (config: AudioConfig = DEFAULT_AUDIO_CONFIG) => {
    try {
      setError(null);
      
      // Request microphone access
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: {
          sampleRate: config.sampleRate,
          channelCount: config.channelCount,
          echoCancellation: config.echoCancellation,
          noiseSuppression: config.noiseSuppression,
          autoGainControl: config.autoGainControl,
        },
      });
      streamRef.current = stream;

      // Create audio context
      const audioContext = new AudioContext({ sampleRate: config.sampleRate });
      audioContextRef.current = audioContext;

      // Create analyser for visualization
      const analyser = audioContext.createAnalyser();
      analyser.fftSize = 256;
      analyser.smoothingTimeConstant = 0.8;
      analyserRef.current = analyser;

      // Create source from stream
      const source = audioContext.createMediaStreamSource(stream);
      sourceRef.current = source;
      source.connect(analyser);

      // Create processor for audio data
      const processor = audioContext.createScriptProcessor(AUDIO_CHUNK_SIZE, 1, 1);
      processorRef.current = processor;

      processor.onaudioprocess = (event) => {
        if (isPausedRef.current) return;
        
        const inputData = event.inputBuffer.getChannelData(0);
        setAudioData(new Float32Array(inputData));
      };

      source.connect(processor);
      processor.connect(audioContext.destination);

      // Start visualization loop
      const updateVisualization = () => {
        if (!analyserRef.current) return;

        const freqData = new Uint8Array(analyserRef.current.frequencyBinCount);
        analyserRef.current.getByteFrequencyData(freqData);
        setFrequencyData(freqData);

        // Calculate audio level (RMS)
        const timeData = new Float32Array(analyserRef.current.fftSize);
        analyserRef.current.getFloatTimeDomainData(timeData);
        
        let sum = 0;
        for (let i = 0; i < timeData.length; i++) {
          sum += timeData[i] * timeData[i];
        }
        const rms = Math.sqrt(sum / timeData.length);
        setAudioLevel(Math.min(1, rms * 5)); // Scale for visibility

        animationRef.current = requestAnimationFrame(updateVisualization);
      };

      animationRef.current = requestAnimationFrame(updateVisualization);
      setIsCapturing(true);
      isPausedRef.current = false;
    } catch (err) {
      const captureError = err instanceof Error ? err : new Error('Failed to start audio capture');
      setError(captureError);
      throw captureError;
    }
  }, []);

  const stop = useCallback(() => {
    // Stop animation
    if (animationRef.current) {
      cancelAnimationFrame(animationRef.current);
      animationRef.current = null;
    }

    // Disconnect nodes
    if (sourceRef.current) {
      sourceRef.current.disconnect();
      sourceRef.current = null;
    }
    if (processorRef.current) {
      processorRef.current.disconnect();
      processorRef.current = null;
    }

    // Close audio context
    if (audioContextRef.current) {
      audioContextRef.current.close();
      audioContextRef.current = null;
    }

    // Stop media stream
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }

    setIsCapturing(false);
    setAudioLevel(0);
    setAudioData(null);
    setFrequencyData(null);
    isPausedRef.current = false;
  }, []);

  const pause = useCallback(() => {
    isPausedRef.current = true;
  }, []);

  const resume = useCallback(() => {
    isPausedRef.current = false;
  }, []);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      stop();
    };
  }, [stop]);

  return {
    isCapturing,
    audioLevel,
    audioData,
    frequencyData,
    error,
    start,
    stop,
    pause,
    resume,
  };
}
```

---

## WebSocket Hook

```typescript
// hooks/useVoiceWebSocket.ts

import { useCallback, useEffect, useRef, useState } from 'react';
import type {
  UseVoiceWebSocketReturn,
  ConnectionStatus,
  SessionConfig,
  ControlAction,
  VoiceError,
  WSMessage,
} from '../types';
import { ConnectionStatus as Status, WSMessageType } from '../types';
import { encodeAudioForAPI } from '../utils/audio-encoder';
import {
  WS_RECONNECT_DELAY_MS,
  WS_MAX_RECONNECT_ATTEMPTS,
  WS_HEARTBEAT_INTERVAL_MS,
} from '../constants';

interface UseVoiceWebSocketOptions {
  readonly onMessage: (message: WSMessage) => void;
  readonly autoReconnect?: boolean;
}

export function useVoiceWebSocket(options: UseVoiceWebSocketOptions): UseVoiceWebSocketReturn {
  const { onMessage, autoReconnect = true } = options;

  const [status, setStatus] = useState<ConnectionStatus>(Status.Disconnected);
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [error, setError] = useState<VoiceError | null>(null);

  const wsRef = useRef<WebSocket | null>(null);
  const urlRef = useRef<string>('');
  const reconnectAttemptsRef = useRef(0);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const heartbeatIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const onMessageRef = useRef(onMessage);
  onMessageRef.current = onMessage;

  const clearTimers = useCallback(() => {
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
      reconnectTimeoutRef.current = null;
    }
    if (heartbeatIntervalRef.current) {
      clearInterval(heartbeatIntervalRef.current);
      heartbeatIntervalRef.current = null;
    }
  }, []);

  const connect = useCallback(async (url: string): Promise<void> => {
    return new Promise((resolve, reject) => {
      urlRef.current = url;
      setStatus(Status.Connecting);
      setError(null);

      const ws = new WebSocket(url);
      wsRef.current = ws;

      ws.onopen = () => {
        setStatus(Status.Connected);
        reconnectAttemptsRef.current = 0;

        // Start heartbeat
        heartbeatIntervalRef.current = setInterval(() => {
          if (ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ type: 'ping' }));
          }
        }, WS_HEARTBEAT_INTERVAL_MS);

        resolve();
      };

      ws.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data) as WSMessage;
          
          // Extract session ID from status messages
          if (message.type === WSMessageType.Status) {
            const payload = message.payload as { sessionId?: string };
            if (payload.sessionId) {
              setSessionId(payload.sessionId);
            }
          }

          onMessageRef.current(message);
        } catch (err) {
          console.error('Failed to parse WebSocket message:', err);
        }
      };

      ws.onerror = (event) => {
        console.error('WebSocket error:', event);
        const wsError: VoiceError = {
          code: 11060,
          constant: 'ERR_WS_CONNECTION_FAILED',
          message: 'WebSocket connection failed',
          retryable: true,
        };
        setError(wsError);
        reject(new Error(wsError.message));
      };

      ws.onclose = (event) => {
        clearTimers();
        setStatus(Status.Disconnected);
        setSessionId(null);

        // Attempt reconnection if enabled and not a clean close
        if (autoReconnect && !event.wasClean && reconnectAttemptsRef.current < WS_MAX_RECONNECT_ATTEMPTS) {
          setStatus(Status.Reconnecting);
          reconnectAttemptsRef.current++;
          
          const delay = WS_RECONNECT_DELAY_MS * Math.pow(2, reconnectAttemptsRef.current - 1);
          reconnectTimeoutRef.current = setTimeout(() => {
            connect(urlRef.current).catch(() => {
              // Reconnection failed, will retry if attempts remain
            });
          }, delay);
        }
      };
    });
  }, [autoReconnect, clearTimers]);

  const disconnect = useCallback(() => {
    clearTimers();
    reconnectAttemptsRef.current = WS_MAX_RECONNECT_ATTEMPTS; // Prevent auto-reconnect
    
    if (wsRef.current) {
      wsRef.current.close(1000, 'Client disconnect');
      wsRef.current = null;
    }
    
    setStatus(Status.Disconnected);
    setSessionId(null);
  }, [clearTimers]);

  const send = useCallback(<T,>(message: WSMessage<T>) => {
    if (wsRef.current?.readyState === WebSocket.OPEN) {
      wsRef.current.send(JSON.stringify(message));
    }
  }, []);

  const sendAudio = useCallback((audioData: Float32Array) => {
    if (wsRef.current?.readyState !== WebSocket.OPEN) return;

    const base64Audio = encodeAudioForAPI(audioData);
    
    send({
      type: WSMessageType.Audio,
      payload: {
        data: base64Audio,
        timestamp: Date.now(),
      },
    });
  }, [send]);

  const sendConfig = useCallback((config: SessionConfig) => {
    send({
      type: WSMessageType.Config,
      payload: config,
    });
  }, [send]);

  const sendControl = useCallback((action: ControlAction) => {
    send({
      type: WSMessageType.Control,
      payload: { action },
    });
  }, [send]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      disconnect();
    };
  }, [disconnect]);

  return {
    status,
    sessionId,
    error,
    connect,
    disconnect,
    send,
    sendAudio,
    sendConfig,
    sendControl,
  };
}
```

---

## Audio Encoder Utility

```typescript
// utils/audio-encoder.ts

/**
 * Converts Float32Array audio data to base64-encoded PCM16 for API transmission.
 */
export function encodeAudioForAPI(float32Array: Float32Array): string {
  // Convert float32 (-1 to 1) to int16 (-32768 to 32767)
  const int16Array = new Int16Array(float32Array.length);
  
  for (let i = 0; i < float32Array.length; i++) {
    const sample = Math.max(-1, Math.min(1, float32Array[i]));
    int16Array[i] = sample < 0 ? sample * 0x8000 : sample * 0x7FFF;
  }

  // Convert to base64
  const uint8Array = new Uint8Array(int16Array.buffer);
  let binary = '';
  const chunkSize = 0x8000; // Process in chunks to avoid call stack issues

  for (let i = 0; i < uint8Array.length; i += chunkSize) {
    const chunk = uint8Array.subarray(i, Math.min(i + chunkSize, uint8Array.length));
    binary += String.fromCharCode.apply(null, Array.from(chunk));
  }

  return btoa(binary);
}

/**
 * Decodes base64 PCM16 audio data to Float32Array.
 */
export function decodeAudioFromAPI(base64: string): Float32Array {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }

  const int16Array = new Int16Array(bytes.buffer);
  const float32Array = new Float32Array(int16Array.length);

  for (let i = 0; i < int16Array.length; i++) {
    float32Array[i] = int16Array[i] / (int16Array[i] < 0 ? 0x8000 : 0x7FFF);
  }

  return float32Array;
}

/**
 * Creates a WAV file blob from Float32Array audio data.
 */
export function createWavBlob(audioData: Float32Array, sampleRate: number): Blob {
  const numChannels = 1;
  const bitsPerSample = 16;
  const bytesPerSample = bitsPerSample / 8;
  const blockAlign = numChannels * bytesPerSample;
  const byteRate = sampleRate * blockAlign;
  const dataSize = audioData.length * bytesPerSample;
  const fileSize = 44 + dataSize;

  const buffer = new ArrayBuffer(fileSize);
  const view = new DataView(buffer);

  // WAV header
  writeString(view, 0, 'RIFF');
  view.setUint32(4, fileSize - 8, true);
  writeString(view, 8, 'WAVE');
  writeString(view, 12, 'fmt ');
  view.setUint32(16, 16, true); // Subchunk1Size
  view.setUint16(20, 1, true); // AudioFormat (PCM)
  view.setUint16(22, numChannels, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, byteRate, true);
  view.setUint16(32, blockAlign, true);
  view.setUint16(34, bitsPerSample, true);
  writeString(view, 36, 'data');
  view.setUint32(40, dataSize, true);

  // Audio data
  let offset = 44;
  for (let i = 0; i < audioData.length; i++) {
    const sample = Math.max(-1, Math.min(1, audioData[i]));
    const int16 = sample < 0 ? sample * 0x8000 : sample * 0x7FFF;
    view.setInt16(offset, int16, true);
    offset += 2;
  }

  return new Blob([buffer], { type: 'audio/wav' });
}

function writeString(view: DataView, offset: number, str: string): void {
  for (let i = 0; i < str.length; i++) {
    view.setUint8(offset + i, str.charCodeAt(i));
  }
}
```

---

## Waveform Visualizer Component

```typescript
// components/WaveformVisualizer.tsx

import { useEffect, useRef, memo } from 'react';
import { cn } from '@/lib/utils';
import type { WaveformVisualizerProps, VADState } from '../types';
import { VADState as VAD } from '../types';
import {
  WAVEFORM_BAR_COUNT,
  WAVEFORM_BAR_WIDTH,
  WAVEFORM_BAR_GAP,
  WAVEFORM_MIN_HEIGHT,
  WAVEFORM_SMOOTHING,
} from '../constants';

export const WaveformVisualizer = memo(function WaveformVisualizer({
  audioData,
  isRecording,
  vadState,
  width = 320,
  height = 64,
  barWidth = WAVEFORM_BAR_WIDTH,
  barGap = WAVEFORM_BAR_GAP,
  className,
}: WaveformVisualizerProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const barsRef = useRef<number[]>(new Array(WAVEFORM_BAR_COUNT).fill(0));
  const animationRef = useRef<number | null>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const dpr = window.devicePixelRatio || 1;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.scale(dpr, dpr);

    const draw = () => {
      ctx.clearRect(0, 0, width, height);

      // Get colors based on VAD state
      const { barColor, glowColor } = getVADColors(vadState, isRecording);

      // Calculate bar positions
      const totalBarWidth = barWidth + barGap;
      const startX = (width - WAVEFORM_BAR_COUNT * totalBarWidth + barGap) / 2;

      // Process audio data into bars
      if (audioData && isRecording) {
        const samplesPerBar = Math.floor(audioData.length / WAVEFORM_BAR_COUNT);
        
        for (let i = 0; i < WAVEFORM_BAR_COUNT; i++) {
          let sum = 0;
          const startSample = i * samplesPerBar;
          
          for (let j = 0; j < samplesPerBar; j++) {
            sum += Math.abs(audioData[startSample + j] || 0);
          }
          
          const average = sum / samplesPerBar;
          const targetHeight = Math.max(WAVEFORM_MIN_HEIGHT, average * height * 2);
          
          // Smooth transition
          barsRef.current[i] = barsRef.current[i] * WAVEFORM_SMOOTHING + targetHeight * (1 - WAVEFORM_SMOOTHING);
        }
      } else {
        // Decay bars when not recording
        for (let i = 0; i < WAVEFORM_BAR_COUNT; i++) {
          barsRef.current[i] = Math.max(WAVEFORM_MIN_HEIGHT, barsRef.current[i] * 0.95);
        }
      }

      // Draw bars
      for (let i = 0; i < WAVEFORM_BAR_COUNT; i++) {
        const barHeight = barsRef.current[i];
        const x = startX + i * totalBarWidth;
        const y = (height - barHeight) / 2;

        // Draw glow effect for active speech
        if (vadState === VAD.Speech || vadState === VAD.SpeechStart) {
          ctx.shadowBlur = 8;
          ctx.shadowColor = glowColor;
        } else {
          ctx.shadowBlur = 0;
        }

        ctx.fillStyle = barColor;
        ctx.beginPath();
        ctx.roundRect(x, y, barWidth, barHeight, barWidth / 2);
        ctx.fill();
      }

      animationRef.current = requestAnimationFrame(draw);
    };

    animationRef.current = requestAnimationFrame(draw);

    return () => {
      if (animationRef.current) {
        cancelAnimationFrame(animationRef.current);
      }
    };
  }, [audioData, isRecording, vadState, width, height, barWidth, barGap]);

  return (
    <canvas
      ref={canvasRef}
      style={{ width, height }}
      className={cn(
        'rounded-lg bg-muted/50',
        className
      )}
    />
  );
});

function getVADColors(vadState: VADState, isRecording: boolean): { barColor: string; glowColor: string } {
  if (!isRecording) {
    return {
      barColor: 'hsl(var(--muted-foreground) / 0.3)',
      glowColor: 'transparent',
    };
  }

  switch (vadState) {
    case VAD.Speech:
    case VAD.SpeechStart:
      return {
        barColor: 'hsl(var(--primary))',
        glowColor: 'hsl(var(--primary) / 0.5)',
      };
    case VAD.SpeechEnd:
      return {
        barColor: 'hsl(var(--primary) / 0.7)',
        glowColor: 'hsl(var(--primary) / 0.3)',
      };
    case VAD.Silence:
    default:
      return {
        barColor: 'hsl(var(--muted-foreground) / 0.5)',
        glowColor: 'transparent',
      };
  }
}
```

---

## Main Component

```typescript
// VoiceRecorder.tsx

import { memo } from 'react';
import { cn } from '@/lib/utils';
import { useVoiceRecorder } from './hooks/useVoiceRecorder';
import { WaveformVisualizer } from './components/WaveformVisualizer';
import { TranscriptPreview } from './components/TranscriptPreview';
import { RecorderControls } from './components/RecorderControls';
import { StatusIndicator } from './components/StatusIndicator';
import { PermissionPrompt } from './components/PermissionPrompt';
import type { VoiceRecorderProps } from './types';
import { RecordingStatus } from './types';

export const VoiceRecorder = memo(function VoiceRecorder({
  projectId,
  conversationId,
  autoConnect = false,
  showWaveform = true,
  showTranscript = true,
  showControls = true,
  showStatus = true,
  config,
  onTranscript,
  onIntent,
  onCommand,
  onError,
  onStatusChange,
  className,
}: VoiceRecorderProps) {
  const recorder = useVoiceRecorder({
    projectId,
    conversationId,
    config,
    onTranscript,
    onIntent,
    onCommand,
    onError,
    onStatusChange,
  });

  // Handle permission request
  if (recorder.status === RecordingStatus.Requesting) {
    return (
      <PermissionPrompt
        onCancel={recorder.cancelRecording}
        className={className}
      />
    );
  }

  return (
    <div
      className={cn(
        'flex flex-col gap-4 rounded-xl border bg-card p-4',
        className
      )}
    >
      {/* Waveform Visualization */}
      {showWaveform && (
        <WaveformVisualizer
          audioData={recorder.audioData}
          isRecording={recorder.status === RecordingStatus.Recording}
          vadState={recorder.vadState}
          width={320}
          height={64}
        />
      )}

      {/* Transcript Preview */}
      {showTranscript && (
        <TranscriptPreview
          partialText={recorder.partialTranscript}
          finalTranscripts={recorder.transcripts}
          isProcessing={recorder.status === RecordingStatus.Processing}
        />
      )}

      {/* Controls */}
      {showControls && (
        <RecorderControls
          status={recorder.status}
          onStart={recorder.startRecording}
          onPause={recorder.pauseRecording}
          onResume={recorder.resumeRecording}
          onStop={recorder.stopRecording}
          onCancel={recorder.cancelRecording}
        />
      )}

      {/* Status Indicator */}
      {showStatus && (
        <StatusIndicator
          connectionStatus={recorder.connectionStatus}
          recordingStatus={recorder.status}
          vadState={recorder.vadState}
          audioLevel={recorder.audioLevel}
          model={config?.whisperModel ?? 'large-v3'}
          language={config?.language ?? 'auto'}
        />
      )}

      {/* Error Display */}
      {recorder.error && (
        <div className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
          <p className="font-medium">{recorder.error.message}</p>
          {recorder.error.retryable && (
            <button
              onClick={recorder.startRecording}
              className="mt-2 text-xs underline hover:no-underline"
            >
              Try again
            </button>
          )}
        </div>
      )}
    </div>
  );
});

export { useVoiceRecorder } from './hooks/useVoiceRecorder';
export type { VoiceRecorderProps, UseVoiceRecorderReturn } from './types';
```

---

## Recorder Controls Component

```typescript
// components/RecorderControls.tsx

import { memo } from 'react';
import { Mic, Pause, Play, Square, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import type { RecorderControlsProps, RecordingStatus } from '../types';
import { RecordingStatus as Status } from '../types';

export const RecorderControls = memo(function RecorderControls({
  status,
  onStart,
  onPause,
  onResume,
  onStop,
  onCancel,
  disabled = false,
  className,
}: RecorderControlsProps) {
  const isIdle = status === Status.Idle || status === Status.Ready;
  const isRecording = status === Status.Recording;
  const isPaused = status === Status.Paused;
  const isProcessing = status === Status.Processing;

  return (
    <div className={cn('flex items-center justify-center gap-3', className)}>
      {/* Main Record/Pause Button */}
      {isIdle && (
        <Button
          size="lg"
          onClick={onStart}
          disabled={disabled}
          className="h-14 w-14 rounded-full"
        >
          <Mic className="h-6 w-6" />
          <span className="sr-only">Start recording</span>
        </Button>
      )}

      {isRecording && (
        <Button
          size="lg"
          variant="secondary"
          onClick={onPause}
          disabled={disabled}
          className="h-14 w-14 rounded-full"
        >
          <Pause className="h-6 w-6" />
          <span className="sr-only">Pause recording</span>
        </Button>
      )}

      {isPaused && (
        <Button
          size="lg"
          onClick={onResume}
          disabled={disabled}
          className="h-14 w-14 rounded-full"
        >
          <Play className="h-6 w-6" />
          <span className="sr-only">Resume recording</span>
        </Button>
      )}

      {isProcessing && (
        <Button
          size="lg"
          disabled
          className="h-14 w-14 rounded-full"
        >
          <div className="h-5 w-5 animate-spin rounded-full border-2 border-current border-t-transparent" />
          <span className="sr-only">Processing</span>
        </Button>
      )}

      {/* Stop Button */}
      {(isRecording || isPaused) && (
        <Button
          size="lg"
          variant="destructive"
          onClick={onStop}
          disabled={disabled}
          className="h-12 w-12 rounded-full"
        >
          <Square className="h-5 w-5" />
          <span className="sr-only">Stop recording</span>
        </Button>
      )}

      {/* Cancel Button */}
      {(isRecording || isPaused) && (
        <Button
          size="sm"
          variant="ghost"
          onClick={onCancel}
          disabled={disabled}
          className="h-10 w-10 rounded-full"
        >
          <X className="h-4 w-4" />
          <span className="sr-only">Cancel recording</span>
        </Button>
      )}
    </div>
  );
});
```

---

## Status Indicator Component

```typescript
// components/StatusIndicator.tsx

import { memo } from 'react';
import { Wifi, WifiOff, Mic, MicOff, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { StatusIndicatorProps, ConnectionStatus, RecordingStatus, VADState } from '../types';
import {
  ConnectionStatus as ConnStatus,
  RecordingStatus as RecStatus,
  VADState as VAD,
} from '../types';
import { WHISPER_MODELS } from '../constants';

export const StatusIndicator = memo(function StatusIndicator({
  connectionStatus,
  recordingStatus,
  vadState,
  audioLevel,
  model,
  language,
  className,
}: StatusIndicatorProps) {
  return (
    <div
      className={cn(
        'flex items-center justify-between gap-4 rounded-lg bg-muted/50 px-3 py-2 text-xs text-muted-foreground',
        className
      )}
    >
      {/* Connection Status */}
      <div className="flex items-center gap-2">
        <ConnectionIcon status={connectionStatus} />
        <span>{getConnectionLabel(connectionStatus)}</span>
      </div>

      {/* VAD Status */}
      <div className="flex items-center gap-2">
        <VADIndicator state={vadState} isRecording={recordingStatus === RecStatus.Recording} />
        <span>VAD: {getVADLabel(vadState)}</span>
      </div>

      {/* Audio Level */}
      <div className="flex items-center gap-2">
        <div className="h-1.5 w-16 overflow-hidden rounded-full bg-muted">
          <div
            className="h-full bg-primary transition-all duration-75"
            style={{ width: `${audioLevel * 100}%` }}
          />
        </div>
      </div>

      {/* Model */}
      <div className="flex items-center gap-1">
        <span className="font-medium">{WHISPER_MODELS[model]?.name ?? model}</span>
        {language !== 'auto' && <span>• {language.toUpperCase()}</span>}
      </div>
    </div>
  );
});

function ConnectionIcon({ status }: { status: ConnectionStatus }) {
  switch (status) {
    case ConnStatus.Connected:
      return <Wifi className="h-3.5 w-3.5 text-green-500" />;
    case ConnStatus.Connecting:
    case ConnStatus.Reconnecting:
      return <Loader2 className="h-3.5 w-3.5 animate-spin text-yellow-500" />;
    case ConnStatus.Error:
      return <WifiOff className="h-3.5 w-3.5 text-destructive" />;
    case ConnStatus.Disconnected:
    default:
      return <WifiOff className="h-3.5 w-3.5" />;
  }
}

function getConnectionLabel(status: ConnectionStatus): string {
  switch (status) {
    case ConnStatus.Connected:
      return 'Connected';
    case ConnStatus.Connecting:
      return 'Connecting...';
    case ConnStatus.Reconnecting:
      return 'Reconnecting...';
    case ConnStatus.Error:
      return 'Error';
    case ConnStatus.Disconnected:
    default:
      return 'Disconnected';
  }
}

function VADIndicator({ state, isRecording }: { state: VADState; isRecording: boolean }) {
  if (!isRecording) {
    return <MicOff className="h-3.5 w-3.5" />;
  }

  const isActive = state === VAD.Speech || state === VAD.SpeechStart;
  
  return (
    <div className="relative">
      <Mic className={cn('h-3.5 w-3.5', isActive && 'text-primary')} />
      {isActive && (
        <span className="absolute -right-0.5 -top-0.5 h-2 w-2 animate-pulse rounded-full bg-primary" />
      )}
    </div>
  );
}

function getVADLabel(state: VADState): string {
  switch (state) {
    case VAD.Speech:
    case VAD.SpeechStart:
      return 'Active';
    case VAD.SpeechEnd:
      return 'Ending';
    case VAD.Silence:
    default:
      return 'Silent';
  }
}
```

---

## Permission Flow

```
┌─────────────────────────────────────────┐
│ 🎤 Microphone Access Required           │
├─────────────────────────────────────────┤
│                                         │
│  We need microphone access to record    │
│  your voice input for transcription.    │
│                                         │
│  Your audio is processed securely and   │
│  not stored permanently.                │
│                                         │
│         [Allow Microphone Access]       │
│                                         │
└─────────────────────────────────────────┘
```

### Permission States

| State | UI |
|-------|-----|
| `prompt` | Show explanation + request button |
| `granted` | Show recorder controls |
| `denied` | Show instructions to enable in browser settings |

---

## Browser Compatibility

| Browser | MediaRecorder | Web Audio API | WebSocket | Notes |
|---------|---------------|---------------|-----------|-------|
| Chrome 49+ | ✓ | ✓ | ✓ | Full support |
| Firefox 25+ | ✓ | ✓ | ✓ | Full support |
| Safari 14.1+ | ✓ | ✓ | ✓ | Requires user gesture |
| Edge 79+ | ✓ | ✓ | ✓ | Full support |
| Mobile Safari | ✓ | ✓ | ✓ | iOS 14.5+ |

---

## Error Codes

| Error | Code | User Message |
|-------|------|--------------|
| Permission denied | 11001 | "Microphone access denied. Please enable in browser settings." |
| No device found | 11002 | "No microphone detected. Please connect a microphone." |
| Recording failed | 11003 | "Recording failed. Please try again." |
| Max duration reached | 11004 | "Maximum recording time reached." |
| Browser unsupported | 11005 | "Your browser doesn't support audio recording." |
| WebSocket failed | 11060 | "Connection to voice service failed." |
| WebSocket message invalid | 11061 | "Invalid message from voice service." |

---

## Accessibility Requirements

| Requirement | Implementation |
|-------------|----------------|
| Keyboard navigation | All controls focusable and operable via keyboard (Space/Enter) |
| Screen reader support | ARIA labels on all interactive elements |
| Focus indicators | Visible focus rings on all controls |
| Motion preferences | Respect `prefers-reduced-motion` for animations |
| Color contrast | Meet WCAG 2.1 AA contrast ratios |
| Status announcements | ARIA live regions for status changes |

---

## Testing Scenarios

| Scenario | Expected Behavior |
|----------|-------------------|
| Microphone permission denied | Show permission prompt with retry option |
| WebSocket connection failure | Display error, attempt reconnection |
| Recording with VAD active | Waveform shows active speech detection |
| Long recording (>5 min) | Auto-stop with warning before limit |
| Network disconnect during recording | Queue audio, sync on reconnect |
| Rapid start/stop | Debounce actions, maintain state consistency |
| Multiple transcript segments | Correctly append and display all transcripts |

---

## Performance Considerations

| Concern | Mitigation |
|---------|------------|
| Canvas rendering | Use `requestAnimationFrame`, limit redraws |
| Audio processing | Use AudioWorklet for off-main-thread processing |
| WebSocket throughput | Batch audio chunks at 100ms intervals |
| Memory usage | Limit transcript history, clear old audio data |
| Re-renders | Memo components, use refs for callbacks |

---

## Related Specifications

- [Voice Input Overview](./00-overview.md)
- [Transcription Display](./02-transcription-display.md)
- [Audio Player](./03-audio-player.md)
- [Voice-CLI Service](../../14-microservices/15-voice-cli-service.md)
- [Voice-CLI OpenAPI](../../14-microservices/16-voice-cli-openapi.md)
