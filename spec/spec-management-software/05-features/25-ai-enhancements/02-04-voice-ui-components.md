# Phase 2.4: Voice Input UI Components

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

React components for voice input with visual feedback, recording controls, transcription display, and resilient offline behavior.

**Cross-References:**
- [Voice Resilience](./02-voice-resilience.md)
- [Audio Capture](./02-01-audio-capture.md)
- [Chat UI Redesign](./05-chat-ui-redesign.md)

---

## 1. Component Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         VoiceInputPanel                                  │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  Container: manages state, orchestrates sub-components             │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                                                                          │
│  ┌─────────────────────────┐  ┌─────────────────────────┐               │
│  │    RecordButton         │  │    WaveformDisplay      │               │
│  │  - Start/Stop/Pause     │  │  - Real-time amplitude  │               │
│  │  - State indicator      │  │  - Recording progress   │               │
│  │  - Keyboard shortcut    │  │  - Duration display     │               │
│  └─────────────────────────┘  └─────────────────────────┘               │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                     TranscriptionDisplay                         │    │
│  │  - Live transcription (streaming)                                │    │
│  │  - Editable final text                                           │    │
│  │  - Segment timestamps                                            │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                     SyncStatusIndicator                          │    │
│  │  - Upload progress                                               │    │
│  │  - Offline queue count                                           │    │
│  │  - Error notifications                                           │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. VoiceInputPanel (Container)

```typescript
// components/voice/VoiceInputPanel.tsx

import { useState, useCallback, useRef, useEffect } from 'react';
import { cn } from '@/lib/utils';
import { Card } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import { useAudioCapture } from '@/hooks/useAudioCapture';
import { useAudioSync } from '@/hooks/useAudioSync';
import { RecordButton } from './RecordButton';
import { WaveformDisplay } from './WaveformDisplay';
import { TranscriptionDisplay } from './TranscriptionDisplay';
import { SyncStatusIndicator } from './SyncStatusIndicator';
import { AudioRecordingMeta } from '@/lib/audio/AudioCaptureService';

interface VoiceInputPanelProps {
  projectId: string;
  sessionId?: string;
  onTranscriptionComplete?: (text: string, recording: AudioRecordingMeta) => void;
  onTranscriptionProgress?: (partialText: string) => void;
  className?: string;
  compact?: boolean;
}

type PanelState = 'idle' | 'recording' | 'paused' | 'processing' | 'transcribing';

export function VoiceInputPanel({
  projectId,
  sessionId,
  onTranscriptionComplete,
  onTranscriptionProgress,
  className,
  compact = false,
}: VoiceInputPanelProps) {
  const [state, setState] = useState<PanelState>('idle');
  const [amplitude, setAmplitude] = useState(0);
  const [duration, setDuration] = useState(0);
  const [transcription, setTranscription] = useState('');
  const [isEditing, setIsEditing] = useState(false);
  
  const { toast } = useToast();
  const { 
    isSupported,
    startRecording,
    stopRecording,
    pauseRecording,
    resumeRecording,
    currentRecording,
    error: captureError,
  } = useAudioCapture(projectId);
  
  const {
    isOnline,
    isSyncing,
    pendingCount,
    uploadRecording,
    transcribeRecording,
    getRecordingProgress,
  } = useAudioSync();
  
  // Handle recording start
  const handleStart = useCallback(async () => {
    try {
      setState('recording');
      await startRecording({
        onProgress: (amp, dur) => {
          setAmplitude(amp);
          setDuration(dur);
        },
      });
    } catch (error) {
      setState('idle');
      toast({
        variant: 'destructive',
        title: 'Recording Failed',
        description: error instanceof Error ? error.message : 'Unknown error',
      });
    }
  }, [startRecording, toast]);
  
  // Handle recording stop
  const handleStop = useCallback(async () => {
    setState('processing');
    
    const recording = await stopRecording();
    if (!recording) {
      setState('idle');
      return;
    }
    
    // Queue for upload and transcription
    await uploadRecording(recording.id);
    
    if (isOnline) {
      setState('transcribing');
      const opId = await transcribeRecording(recording.id);
      
      // Poll for transcription result
      // In production, use WebSocket for real-time updates
      const checkResult = async () => {
        const meta = currentRecording;
        if (meta?.transcription) {
          setTranscription(meta.transcription.text);
          setState('idle');
          onTranscriptionComplete?.(meta.transcription.text, meta);
        } else if (meta?.status === 'failed') {
          setState('idle');
          toast({
            variant: 'destructive',
            title: 'Transcription Failed',
            description: 'Your recording is saved. We\'ll retry later.',
          });
        } else {
          setTimeout(checkResult, 1000);
        }
      };
      checkResult();
    } else {
      setState('idle');
      setTranscription('');
      toast({
        title: 'Saved Offline',
        description: 'Recording will be transcribed when you\'re back online.',
      });
    }
  }, [stopRecording, uploadRecording, transcribeRecording, isOnline, currentRecording, onTranscriptionComplete, toast]);
  
  // Handle pause
  const handlePause = useCallback(() => {
    pauseRecording();
    setState('paused');
  }, [pauseRecording]);
  
  // Handle resume
  const handleResume = useCallback(() => {
    resumeRecording((amp, dur) => {
      setAmplitude(amp);
      setDuration(dur);
    });
    setState('recording');
  }, [resumeRecording]);
  
  // Handle transcription edit
  const handleTranscriptionChange = useCallback((text: string) => {
    setTranscription(text);
  }, []);
  
  // Handle transcription submit
  const handleTranscriptionSubmit = useCallback(() => {
    setIsEditing(false);
    if (currentRecording) {
      onTranscriptionComplete?.(transcription, currentRecording);
    }
  }, [transcription, currentRecording, onTranscriptionComplete]);
  
  // Keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Ctrl/Cmd + Shift + R to toggle recording
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'r') {
        e.preventDefault();
        if (state === 'idle') {
          handleStart();
        } else if (state === 'recording') {
          handleStop();
        }
      }
      
      // Space to pause/resume when recording
      if (e.key === ' ' && e.target === document.body) {
        if (state === 'recording') {
          e.preventDefault();
          handlePause();
        } else if (state === 'paused') {
          e.preventDefault();
          handleResume();
        }
      }
    };
    
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [state, handleStart, handleStop, handlePause, handleResume]);
  
  if (!isSupported) {
    return (
      <Card className={cn('p-4 text-center text-muted-foreground', className)}>
        Voice recording is not supported in this browser.
      </Card>
    );
  }
  
  if (compact) {
    return (
      <div className={cn('flex items-center gap-2', className)}>
        <RecordButton
          state={state}
          amplitude={amplitude}
          onStart={handleStart}
          onStop={handleStop}
          onPause={handlePause}
          onResume={handleResume}
          size="sm"
        />
        {state === 'recording' && (
          <span className="text-xs text-muted-foreground tabular-nums">
            {formatDuration(duration)}
          </span>
        )}
        {!isOnline && (
          <span className="text-xs text-warning">Offline</span>
        )}
      </div>
    );
  }
  
  return (
    <Card className={cn('p-4 space-y-4', className)}>
      {/* Recording controls */}
      <div className="flex items-center justify-center gap-4">
        <RecordButton
          state={state}
          amplitude={amplitude}
          onStart={handleStart}
          onStop={handleStop}
          onPause={handlePause}
          onResume={handleResume}
          size="lg"
        />
      </div>
      
      {/* Waveform visualization */}
      {(state === 'recording' || state === 'paused') && (
        <WaveformDisplay
          amplitude={amplitude}
          duration={duration}
          isPaused={state === 'paused'}
        />
      )}
      
      {/* Transcription display */}
      {(transcription || state === 'transcribing') && (
        <TranscriptionDisplay
          text={transcription}
          isLoading={state === 'transcribing'}
          isEditing={isEditing}
          onEdit={() => setIsEditing(true)}
          onChange={handleTranscriptionChange}
          onSubmit={handleTranscriptionSubmit}
          onCancel={() => setIsEditing(false)}
        />
      )}
      
      {/* Sync status */}
      <SyncStatusIndicator
        isOnline={isOnline}
        isSyncing={isSyncing}
        pendingCount={pendingCount}
        progress={currentRecording ? getRecordingProgress(currentRecording.id) : null}
      />
    </Card>
  );
}

function formatDuration(ms: number): string {
  const seconds = Math.floor(ms / 1000);
  const minutes = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${minutes}:${secs.toString().padStart(2, '0')}`;
}
```

---

## 3. RecordButton Component

```typescript
// components/voice/RecordButton.tsx

import { forwardRef } from 'react';
import { Mic, MicOff, Pause, Play, Loader2, Check, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type ButtonState = 'idle' | 'recording' | 'paused' | 'processing' | 'transcribing';

interface RecordButtonProps {
  state: ButtonState;
  amplitude?: number;
  onStart: () => void;
  onStop: () => void;
  onPause?: () => void;
  onResume?: () => void;
  size?: 'sm' | 'md' | 'lg';
  disabled?: boolean;
  className?: string;
}

const sizeClasses = {
  sm: 'h-8 w-8',
  md: 'h-12 w-12',
  lg: 'h-16 w-16',
};

const iconSizes = {
  sm: 'h-4 w-4',
  md: 'h-5 w-5',
  lg: 'h-6 w-6',
};

export const RecordButton = forwardRef<HTMLButtonElement, RecordButtonProps>(
  ({ state, amplitude = 0, onStart, onStop, onPause, onResume, size = 'md', disabled, className }, ref) => {
    const isRecording = state === 'recording';
    const isPaused = state === 'paused';
    const isProcessing = state === 'processing' || state === 'transcribing';
    
    const handleClick = () => {
      if (disabled || isProcessing) return;
      
      if (state === 'idle') {
        onStart();
      } else if (isRecording) {
        onStop();
      } else if (isPaused) {
        onResume?.();
      }
    };
    
    const handleDoubleClick = () => {
      if (isRecording && onPause) {
        onPause();
      }
    };
    
    const getIcon = () => {
      switch (state) {
        case 'idle':
          return <Mic className={iconSizes[size]} />;
        case 'recording':
          return <MicOff className={iconSizes[size]} />;
        case 'paused':
          return <Play className={iconSizes[size]} />;
        case 'processing':
        case 'transcribing':
          return <Loader2 className={cn(iconSizes[size], 'animate-spin')} />;
        default:
          return <Mic className={iconSizes[size]} />;
      }
    };
    
    const getTooltip = () => {
      switch (state) {
        case 'idle':
          return 'Start recording (Ctrl+Shift+R)';
        case 'recording':
          return 'Stop recording (click) or pause (double-click)';
        case 'paused':
          return 'Resume recording';
        case 'processing':
          return 'Saving recording...';
        case 'transcribing':
          return 'Transcribing...';
        default:
          return '';
      }
    };
    
    return (
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            ref={ref}
            variant={isRecording ? 'destructive' : 'default'}
            size="icon"
            className={cn(
              'relative rounded-full transition-all',
              sizeClasses[size],
              isRecording && 'animate-pulse',
              className
            )}
            disabled={disabled || isProcessing}
            onClick={handleClick}
            onDoubleClick={handleDoubleClick}
          >
            {getIcon()}
            
            {/* Amplitude ring effect */}
            {isRecording && (
              <span
                className="absolute inset-0 rounded-full border-2 border-current opacity-50 pointer-events-none"
                style={{
                  transform: `scale(${1 + amplitude * 0.4})`,
                  transition: 'transform 50ms ease-out',
                }}
              />
            )}
            
            {/* Outer pulse ring */}
            {isRecording && (
              <span className="absolute inset-0 rounded-full border-2 border-destructive animate-ping opacity-25" />
            )}
          </Button>
        </TooltipTrigger>
        <TooltipContent>
          <p>{getTooltip()}</p>
        </TooltipContent>
      </Tooltip>
    );
  }
);

RecordButton.displayName = 'RecordButton';
```

---

## 4. WaveformDisplay Component

```typescript
// components/voice/WaveformDisplay.tsx

import { useRef, useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

interface WaveformDisplayProps {
  amplitude: number;
  duration: number;
  isPaused?: boolean;
  className?: string;
  barCount?: number;
}

export function WaveformDisplay({
  amplitude,
  duration,
  isPaused = false,
  className,
  barCount = 40,
}: WaveformDisplayProps) {
  const [history, setHistory] = useState<number[]>(() => Array(barCount).fill(0));
  const historyRef = useRef(history);
  
  // Update history with new amplitude
  useEffect(() => {
    if (isPaused) return;
    
    const newHistory = [...historyRef.current.slice(1), amplitude];
    historyRef.current = newHistory;
    setHistory(newHistory);
  }, [amplitude, isPaused]);
  
  // Reset on unmount
  useEffect(() => {
    return () => {
      historyRef.current = Array(barCount).fill(0);
    };
  }, [barCount]);
  
  const formatDuration = (ms: number): string => {
    const seconds = Math.floor(ms / 1000);
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
  };
  
  return (
    <div className={cn('flex flex-col items-center gap-2', className)}>
      {/* Waveform bars */}
      <div className="flex items-center justify-center gap-0.5 h-12 w-full">
        {history.map((value, index) => (
          <div
            key={index}
            className={cn(
              'w-1 rounded-full transition-all duration-75',
              isPaused ? 'bg-muted' : 'bg-primary'
            )}
            style={{
              height: `${Math.max(4, value * 48)}px`,
              opacity: isPaused ? 0.5 : 0.3 + (index / barCount) * 0.7,
            }}
          />
        ))}
      </div>
      
      {/* Duration */}
      <div className="flex items-center gap-2">
        <span className="text-sm font-medium tabular-nums">
          {formatDuration(duration)}
        </span>
        {isPaused && (
          <span className="text-xs text-muted-foreground">PAUSED</span>
        )}
      </div>
    </div>
  );
}
```

---

## 5. TranscriptionDisplay Component

```typescript
// components/voice/TranscriptionDisplay.tsx

import { useState, useRef, useEffect } from 'react';
import { Edit2, Check, X, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface TranscriptionDisplayProps {
  text: string;
  isLoading?: boolean;
  isEditing?: boolean;
  onEdit?: () => void;
  onChange?: (text: string) => void;
  onSubmit?: () => void;
  onCancel?: () => void;
  className?: string;
}

export function TranscriptionDisplay({
  text,
  isLoading = false,
  isEditing = false,
  onEdit,
  onChange,
  onSubmit,
  onCancel,
  className,
}: TranscriptionDisplayProps) {
  const [editText, setEditText] = useState(text);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  
  // Sync edit text with prop
  useEffect(() => {
    if (!isEditing) {
      setEditText(text);
    }
  }, [text, isEditing]);
  
  // Focus textarea when editing starts
  useEffect(() => {
    if (isEditing && textareaRef.current) {
      textareaRef.current.focus();
      textareaRef.current.setSelectionRange(editText.length, editText.length);
    }
  }, [isEditing]);
  
  const handleSubmit = () => {
    onChange?.(editText);
    onSubmit?.();
  };
  
  const handleCancel = () => {
    setEditText(text);
    onCancel?.();
  };
  
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      handleSubmit();
    }
    if (e.key === 'Escape') {
      e.preventDefault();
      handleCancel();
    }
  };
  
  if (isLoading) {
    return (
      <div className={cn('space-y-2', className)}>
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          <span>Transcribing...</span>
        </div>
        <Skeleton className="h-20 w-full" />
      </div>
    );
  }
  
  if (isEditing) {
    return (
      <div className={cn('space-y-2', className)}>
        <Textarea
          ref={textareaRef}
          value={editText}
          onChange={(e) => setEditText(e.target.value)}
          onKeyDown={handleKeyDown}
          className="min-h-[100px] resize-y"
          placeholder="Transcription..."
        />
        <div className="flex items-center justify-end gap-2">
          <Button
            size="sm"
            variant="ghost"
            onClick={handleCancel}
          >
            <X className="h-4 w-4 mr-1" />
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={handleSubmit}
          >
            <Check className="h-4 w-4 mr-1" />
            Save
          </Button>
        </div>
        <p className="text-xs text-muted-foreground">
          Press Ctrl+Enter to save, Escape to cancel
        </p>
      </div>
    );
  }
  
  return (
    <div className={cn('group relative', className)}>
      <div className="p-3 rounded-md bg-muted/50 min-h-[60px]">
        <p className="text-sm whitespace-pre-wrap">
          {text || <span className="text-muted-foreground italic">No transcription</span>}
        </p>
      </div>
      
      {text && onEdit && (
        <Button
          size="icon"
          variant="ghost"
          className="absolute top-2 right-2 h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity"
          onClick={onEdit}
        >
          <Edit2 className="h-3 w-3" />
        </Button>
      )}
    </div>
  );
}
```

---

## 6. SyncStatusIndicator Component

```typescript
// components/voice/SyncStatusIndicator.tsx

import { Cloud, CloudOff, Upload, Check, AlertCircle, RefreshCw } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface SyncStatusIndicatorProps {
  isOnline: boolean;
  isSyncing: boolean;
  pendingCount: number;
  progress?: number | null;
  onRetry?: () => void;
  className?: string;
}

export function SyncStatusIndicator({
  isOnline,
  isSyncing,
  pendingCount,
  progress,
  onRetry,
  className,
}: SyncStatusIndicatorProps) {
  // All synced, online
  if (isOnline && pendingCount === 0 && !isSyncing) {
    return (
      <div className={cn('flex items-center gap-2 text-xs text-muted-foreground', className)}>
        <Check className="h-3 w-3 text-success" />
        <span>All synced</span>
      </div>
    );
  }
  
  // Offline
  if (!isOnline) {
    return (
      <div className={cn('flex items-center gap-2 text-xs', className)}>
        <CloudOff className="h-3 w-3 text-warning" />
        <span className="text-warning">Offline</span>
        {pendingCount > 0 && (
          <span className="text-muted-foreground">
            ({pendingCount} pending)
          </span>
        )}
      </div>
    );
  }
  
  // Syncing with progress
  if (isSyncing && progress !== null && progress !== undefined) {
    return (
      <div className={cn('space-y-1', className)}>
        <div className="flex items-center gap-2 text-xs">
          <Upload className="h-3 w-3 text-primary animate-pulse" />
          <span>Uploading... {progress}%</span>
        </div>
        <Progress value={progress} className="h-1" />
      </div>
    );
  }
  
  // Syncing without progress
  if (isSyncing) {
    return (
      <div className={cn('flex items-center gap-2 text-xs', className)}>
        <RefreshCw className="h-3 w-3 text-primary animate-spin" />
        <span>Syncing...</span>
        {pendingCount > 1 && (
          <span className="text-muted-foreground">
            ({pendingCount - 1} remaining)
          </span>
        )}
      </div>
    );
  }
  
  // Pending items
  if (pendingCount > 0) {
    return (
      <div className={cn('flex items-center gap-2 text-xs', className)}>
        <Cloud className="h-3 w-3 text-muted-foreground" />
        <span>{pendingCount} pending</span>
        {onRetry && (
          <Button
            variant="ghost"
            size="sm"
            className="h-5 px-2 text-xs"
            onClick={onRetry}
          >
            Retry
          </Button>
        )}
      </div>
    );
  }
  
  return null;
}
```

---

## 7. useAudioCapture Hook

```typescript
// hooks/useAudioCapture.ts

import { useState, useCallback, useRef, useMemo, useEffect } from 'react';
import { 
  AudioCaptureService, 
  AudioRecordingMeta,
  AudioCaptureError,
  CaptureEvents,
} from '@/lib/audio/AudioCaptureService';
import { useOfflineStorage } from './useOfflineStorage';

interface UseAudioCaptureReturn {
  isSupported: boolean;
  isRecording: boolean;
  isPaused: boolean;
  currentRecording: AudioRecordingMeta | null;
  error: AudioCaptureError | null;
  startRecording: (events?: CaptureEvents) => Promise<string>;
  stopRecording: () => Promise<AudioRecordingMeta | null>;
  pauseRecording: () => void;
  resumeRecording: (onProgress?: (amplitude: number, duration: number) => void) => void;
}

export function useAudioCapture(projectId: string): UseAudioCaptureReturn {
  const { storage, blobStore } = useOfflineStorage();
  const [isRecording, setIsRecording] = useState(false);
  const [isPaused, setIsPaused] = useState(false);
  const [currentRecording, setCurrentRecording] = useState<AudioRecordingMeta | null>(null);
  const [error, setError] = useState<AudioCaptureError | null>(null);
  
  const serviceRef = useRef<AudioCaptureService | null>(null);
  
  // Initialize service
  useEffect(() => {
    if (storage && blobStore && !serviceRef.current) {
      serviceRef.current = new AudioCaptureService(storage, blobStore, projectId);
    }
  }, [storage, blobStore, projectId]);
  
  const isSupported = useMemo(() => AudioCaptureService.isSupported(), []);
  
  const startRecording = useCallback(async (events?: CaptureEvents): Promise<string> => {
    if (!serviceRef.current) {
      throw new Error('Audio capture not initialized');
    }
    
    setError(null);
    
    const recordingId = await serviceRef.current.start({
      ...events,
      onComplete: (recording) => {
        setIsRecording(false);
        setIsPaused(false);
        setCurrentRecording(recording);
        events?.onComplete?.(recording);
      },
      onError: (err) => {
        setIsRecording(false);
        setIsPaused(false);
        setError(err);
        events?.onError?.(err);
      },
    });
    
    setIsRecording(true);
    setIsPaused(false);
    
    return recordingId;
  }, []);
  
  const stopRecording = useCallback(async (): Promise<AudioRecordingMeta | null> => {
    if (!serviceRef.current) return null;
    
    const recording = await serviceRef.current.stop();
    setIsRecording(false);
    setIsPaused(false);
    
    if (recording) {
      setCurrentRecording(recording);
    }
    
    return recording;
  }, []);
  
  const pauseRecording = useCallback(() => {
    serviceRef.current?.pause();
    setIsPaused(true);
  }, []);
  
  const resumeRecording = useCallback((onProgress?: (amplitude: number, duration: number) => void) => {
    serviceRef.current?.resume(onProgress);
    setIsPaused(false);
  }, []);
  
  return {
    isSupported,
    isRecording,
    isPaused,
    currentRecording,
    error,
    startRecording,
    stopRecording,
    pauseRecording,
    resumeRecording,
  };
}
```

---

## 8. Accessibility Requirements

| Feature | Requirement |
|---------|-------------|
| Keyboard navigation | Full control via keyboard shortcuts |
| Screen reader | Announce state changes (recording started, stopped, etc.) |
| Focus management | Focus moves logically between controls |
| Color contrast | All text meets WCAG AA |
| Motion | Respect prefers-reduced-motion for animations |
| Error messages | Clear, actionable error descriptions |

---

## 9. Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| Start/stop recording | Basic flow works | Critical |
| Keyboard shortcuts | Ctrl+Shift+R toggles recording | High |
| Amplitude visualization | Waveform responds to audio | Medium |
| Pause/resume | Recording continues correctly | Medium |
| Transcription editing | Edit and save transcription | High |
| Offline indicator | Shows offline status | High |
| Upload progress | Progress bar updates | Medium |
| Accessibility | Screen reader announces states | High |

---

## Related Specs

- [Voice Resilience](./02-voice-resilience.md)
- [Audio Capture](./02-01-audio-capture.md)
- [Audio Sync](./02-03-audio-sync.md)
- [Chat UI Redesign](./05-chat-ui-redesign.md)
