# Transcription Display Component

**Version:** 2.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  

---

## Overview

Real-time transcript display component with live partial updates during recording, word-level timestamp highlighting, inline editing capabilities, speaker diarization support, and audio playback synchronization.

**Cross-References:**
- [Voice Input Overview](./00-overview.md) — Feature context
- [Voice Recorder](./01-voice-recorder.md) — Audio capture integration
- [Audio Player](./03-audio-player.md) — Playback synchronization
- [Voice-CLI Service](../../14-microservices/15-voice-cli-service.md) — Transcript data source
- [Voice-CLI OpenAPI](../../14-microservices/16-voice-cli-openapi.md) — Transcript message format
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md) — React standards

---

## Component Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                      TranscriptionDisplay                            │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    TranscriptHeader                          │   │
│  │  Transcript                        [Edit] [Export ▾] [📋]   │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    TranscriptContent                         │   │
│  │  ┌─────────────────────────────────────────────────────────┐│   │
│  │  │ 🎤 Speaker 1                                    0:00    ││   │
│  │  │ The quick brown fox jumps over the lazy dog.            ││   │
│  │  │     ▲           ▲                                       ││   │
│  │  │  word-span  word-span (highlighted during playback)     ││   │
│  │  └─────────────────────────────────────────────────────────┘│   │
│  │  ┌─────────────────────────────────────────────────────────┐│   │
│  │  │ 🎤 Speaker 2                                    0:05    ││   │
│  │  │ This is a response from another speaker.                ││   │
│  │  └─────────────────────────────────────────────────────────┘│   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    PartialTranscript                         │   │
│  │  And now I'm speaking about... █                            │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    IntentPreview                             │   │
│  │  🎯 CREATE_SPEC detected (92% confidence)                   │   │
│  │  └─ title: "authentication" • type: "feature"               │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Live Transcription | Real-time text display during recording | High |
| Partial Updates | Show interim results with visual distinction | High |
| Word-Level Timestamps | Click word to seek in audio, highlight during playback | High |
| Inline Editing | Edit transcript text directly with save/cancel | High |
| Intent Display | Show recognized commands with slots and confidence | High |
| Speaker Labels | Display speaker diarization results | Medium |
| Confidence Indicators | Visual cues for low-confidence words | Medium |
| Copy/Export | Copy transcript to clipboard, export as SRT/VTT | Medium |
| Virtual Scrolling | Efficient rendering for long transcripts | Medium |
| Audio Sync | Highlight current word during playback | Medium |

---

## Component Hierarchy

```
TranscriptionDisplay/
├── index.ts                      # Public exports
├── TranscriptionDisplay.tsx      # Main container component
├── components/
│   ├── TranscriptHeader.tsx      # Title bar with actions
│   ├── TranscriptContent.tsx     # Scrollable transcript area
│   ├── TranscriptSegment.tsx     # Single transcript segment
│   ├── PartialTranscript.tsx     # Interim/live text display
│   ├── WordSpan.tsx              # Individual word with timestamp
│   ├── SpeakerLabel.tsx          # Speaker identification badge
│   ├── ConfidenceIndicator.tsx   # Low-confidence word marker
│   ├── IntentPreview.tsx         # Recognized intent display
│   ├── TranscriptEditor.tsx      # Edit mode component
│   └── ExportMenu.tsx            # Export format options
├── hooks/
│   ├── useTranscription.ts       # Transcript state management
│   ├── useWordHighlight.ts       # Audio sync highlighting
│   ├── useVirtualScroll.ts       # Virtual scroll for long lists
│   └── useTranscriptExport.ts    # Export functionality
├── utils/
│   ├── transcript-formatter.ts   # Text processing utilities
│   ├── export-formats.ts         # SRT, VTT, JSON export
│   ├── word-utils.ts             # Word timestamp utilities
│   └── speaker-colors.ts         # Speaker color assignment
├── types.ts                      # TypeScript interfaces
└── constants.ts                  # Configuration constants
```

---

## Type Definitions

```typescript
// types.ts

import type { CommandIntent } from '@/types/voice';

// === Transcript Types ===

export interface TranscriptWord {
  readonly word: string;
  readonly start: number;           // Start time in milliseconds
  readonly end: number;             // End time in milliseconds
  readonly probability: number;     // Confidence 0-1
}

export interface TranscriptSegment {
  readonly id: string;
  readonly text: string;
  readonly language: string;
  readonly confidence: number;
  readonly durationMs: number;
  readonly words: readonly TranscriptWord[];
  readonly speaker?: string;
  readonly startTime?: number;
  readonly endTime?: number;
  readonly isEdited?: boolean;
  readonly editedText?: string;
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

// === Display State ===

export const DisplayMode = {
  Live: 'live',           // During recording
  Review: 'review',       // After recording
  Edit: 'edit',           // Editing transcript
  Playback: 'playback',   // Synced with audio
} as const;

export type DisplayMode = typeof DisplayMode[keyof typeof DisplayMode];

export const ExportFormat = {
  Text: 'text',
  SRT: 'srt',
  VTT: 'vtt',
  JSON: 'json',
} as const;

export type ExportFormat = typeof ExportFormat[keyof typeof ExportFormat];

// === Component Props ===

export interface TranscriptionDisplayProps {
  /** Committed transcript segments */
  readonly transcripts: readonly TranscriptSegment[];
  /** Current partial/interim transcript */
  readonly partialText: string;
  /** Recognized intents */
  readonly intents: readonly IntentMatch[];
  /** Command execution results */
  readonly commands: readonly CommandResult[];
  /** Whether currently processing audio */
  readonly isProcessing: boolean;
  /** Current playback time in milliseconds */
  readonly currentTime?: number;
  /** Enable editing mode */
  readonly editable?: boolean;
  /** Show timestamps */
  readonly showTimestamps?: boolean;
  /** Show speaker labels */
  readonly showSpeakers?: boolean;
  /** Show confidence indicators */
  readonly showConfidence?: boolean;
  /** Show recognized intents */
  readonly showIntents?: boolean;
  /** Maximum height before scroll */
  readonly maxHeight?: number;
  /** Edit callback */
  readonly onEdit?: (segmentId: string, newText: string) => void;
  /** Word click callback (for audio sync) */
  readonly onWordClick?: (timestampMs: number) => void;
  /** Intent action click callback */
  readonly onActionClick?: (action: FollowupAction) => void;
  /** Export callback */
  readonly onExport?: (format: ExportFormat, content: string) => void;
  /** Custom class name */
  readonly className?: string;
}

export interface TranscriptSegmentProps {
  readonly segment: TranscriptSegment;
  readonly isEditing: boolean;
  readonly currentTimeMs?: number;
  readonly showTimestamp: boolean;
  readonly showSpeaker: boolean;
  readonly showConfidence: boolean;
  readonly onStartEdit: () => void;
  readonly onSaveEdit: (newText: string) => void;
  readonly onCancelEdit: () => void;
  readonly onWordClick: (timestampMs: number) => void;
  readonly className?: string;
}

export interface WordSpanProps {
  readonly word: TranscriptWord;
  readonly isHighlighted: boolean;
  readonly isLowConfidence: boolean;
  readonly onClick: () => void;
  readonly className?: string;
}

export interface PartialTranscriptProps {
  readonly text: string;
  readonly isProcessing: boolean;
  readonly className?: string;
}

export interface IntentPreviewProps {
  readonly intent: IntentMatch;
  readonly command?: CommandResult;
  readonly onActionClick?: (action: FollowupAction) => void;
  readonly className?: string;
}

export interface SpeakerLabelProps {
  readonly speaker: string;
  readonly color: string;
  readonly editable?: boolean;
  readonly onRename?: (newName: string) => void;
  readonly className?: string;
}

export interface TranscriptEditorProps {
  readonly segment: TranscriptSegment;
  readonly onSave: (newText: string) => void;
  readonly onCancel: () => void;
  readonly className?: string;
}

export interface ExportMenuProps {
  readonly transcripts: readonly TranscriptSegment[];
  readonly includeSpeakers: boolean;
  readonly includeTimestamps: boolean;
  readonly onExport: (format: ExportFormat, content: string) => void;
  readonly className?: string;
}

// === Hook Return Types ===

export interface UseTranscriptionReturn {
  readonly segments: readonly TranscriptSegment[];
  readonly partialText: string;
  readonly intents: readonly IntentMatch[];
  readonly commands: readonly CommandResult[];
  readonly editingSegmentId: string | null;
  readonly startEdit: (segmentId: string) => void;
  readonly saveEdit: (segmentId: string, newText: string) => void;
  readonly cancelEdit: () => void;
  readonly clearAll: () => void;
}

export interface UseWordHighlightReturn {
  readonly highlightedWordIndex: number | null;
  readonly highlightedSegmentId: string | null;
  readonly scrollToWord: (segmentId: string, wordIndex: number) => void;
}
```

---

## Constants

```typescript
// constants.ts

// Confidence thresholds
export const CONFIDENCE_HIGH = 0.9;
export const CONFIDENCE_MEDIUM = 0.7;
export const CONFIDENCE_LOW = 0.5;

// Virtual scroll
export const VIRTUAL_SCROLL_THRESHOLD = 100; // Enable after N segments
export const VIRTUAL_ITEM_HEIGHT = 80;       // Estimated segment height
export const VIRTUAL_OVERSCAN = 5;           // Extra items to render

// Animation
export const PARTIAL_PULSE_DURATION = 1500;  // ms
export const WORD_HIGHLIGHT_DURATION = 200;  // ms

// Export
export const MAX_LINE_LENGTH = 80;           // For SRT/VTT formatting

// Speaker colors (semantic tokens)
export const SPEAKER_COLORS = [
  'hsl(var(--primary))',
  'hsl(var(--secondary))',
  'hsl(221 83% 53%)',     // Blue
  'hsl(142 76% 36%)',     // Green
  'hsl(38 92% 50%)',      // Orange
  'hsl(280 65% 60%)',     // Purple
  'hsl(350 89% 60%)',     // Red
  'hsl(180 70% 45%)',     // Teal
] as const;
```

---

## Main Component

```typescript
// TranscriptionDisplay.tsx

import { memo, useMemo } from 'react';
import { cn } from '@/lib/utils';
import { ScrollArea } from '@/components/ui/scroll-area';
import { TranscriptHeader } from './components/TranscriptHeader';
import { TranscriptContent } from './components/TranscriptContent';
import { PartialTranscript } from './components/PartialTranscript';
import { IntentPreview } from './components/IntentPreview';
import { useTranscription } from './hooks/useTranscription';
import { useWordHighlight } from './hooks/useWordHighlight';
import type { TranscriptionDisplayProps, ExportFormat } from './types';

export const TranscriptionDisplay = memo(function TranscriptionDisplay({
  transcripts,
  partialText,
  intents,
  commands,
  isProcessing,
  currentTime,
  editable = false,
  showTimestamps = true,
  showSpeakers = true,
  showConfidence = false,
  showIntents = true,
  maxHeight = 400,
  onEdit,
  onWordClick,
  onActionClick,
  onExport,
  className,
}: TranscriptionDisplayProps) {
  const transcription = useTranscription({
    segments: transcripts,
    partialText,
    intents,
    commands,
  });

  const wordHighlight = useWordHighlight({
    segments: transcripts,
    currentTimeMs: currentTime,
  });

  // Get the latest intent/command pair
  const latestIntent = intents.length > 0 ? intents[intents.length - 1] : null;
  const latestCommand = commands.length > 0 ? commands[commands.length - 1] : null;

  // Handle export
  const handleExport = (format: ExportFormat) => {
    if (!onExport) return;
    const content = formatTranscriptForExport(transcripts, format, {
      includeSpeakers: showSpeakers,
      includeTimestamps: showTimestamps,
    });
    onExport(format, content);
  };

  // Empty state
  if (transcripts.length === 0 && !partialText && !isProcessing) {
    return (
      <div className={cn('flex items-center justify-center p-8 text-muted-foreground', className)}>
        <p>Start recording to see transcription</p>
      </div>
    );
  }

  return (
    <div className={cn('flex flex-col rounded-lg border bg-card', className)}>
      {/* Header */}
      <TranscriptHeader
        segmentCount={transcripts.length}
        editable={editable}
        onExport={handleExport}
        onCopy={() => handleExport('text')}
      />

      {/* Content */}
      <ScrollArea style={{ maxHeight }} className="flex-1">
        <div className="flex flex-col gap-3 p-4">
          {/* Committed Segments */}
          <TranscriptContent
            segments={transcripts}
            editingSegmentId={transcription.editingSegmentId}
            currentTimeMs={currentTime}
            highlightedWordIndex={wordHighlight.highlightedWordIndex}
            highlightedSegmentId={wordHighlight.highlightedSegmentId}
            showTimestamps={showTimestamps}
            showSpeakers={showSpeakers}
            showConfidence={showConfidence}
            onStartEdit={transcription.startEdit}
            onSaveEdit={transcription.saveEdit}
            onCancelEdit={transcription.cancelEdit}
            onWordClick={onWordClick ?? (() => {})}
          />

          {/* Partial Transcript */}
          {(partialText || isProcessing) && (
            <PartialTranscript
              text={partialText}
              isProcessing={isProcessing}
            />
          )}

          {/* Intent Preview */}
          {showIntents && latestIntent && (
            <IntentPreview
              intent={latestIntent}
              command={latestCommand ?? undefined}
              onActionClick={onActionClick}
            />
          )}
        </div>
      </ScrollArea>
    </div>
  );
});
```

---

## Transcript Segment Component

```typescript
// components/TranscriptSegment.tsx

import { memo, useState, useCallback, useRef, useEffect } from 'react';
import { Edit2, Check, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { WordSpan } from './WordSpan';
import { SpeakerLabel } from './SpeakerLabel';
import { getSpeakerColor } from '../utils/speaker-colors';
import type { TranscriptSegmentProps } from '../types';
import { CONFIDENCE_MEDIUM } from '../constants';

export const TranscriptSegment = memo(function TranscriptSegment({
  segment,
  isEditing,
  currentTimeMs,
  showTimestamp,
  showSpeaker,
  showConfidence,
  onStartEdit,
  onSaveEdit,
  onCancelEdit,
  onWordClick,
  className,
}: TranscriptSegmentProps) {
  const [editText, setEditText] = useState(segment.text);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  // Focus textarea when entering edit mode
  useEffect(() => {
    if (isEditing && textareaRef.current) {
      textareaRef.current.focus();
      textareaRef.current.select();
    }
  }, [isEditing]);

  // Reset edit text when segment changes
  useEffect(() => {
    setEditText(segment.editedText ?? segment.text);
  }, [segment.text, segment.editedText]);

  const handleSave = useCallback(() => {
    onSaveEdit(editText.trim());
  }, [editText, onSaveEdit]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
      e.preventDefault();
      handleSave();
    } else if (e.key === 'Escape') {
      e.preventDefault();
      onCancelEdit();
    }
  }, [handleSave, onCancelEdit]);

  // Format timestamp
  const formatTime = (ms: number): string => {
    const seconds = Math.floor(ms / 1000);
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
  };

  if (isEditing) {
    return (
      <div
        id={`segment-${segment.id}`}
        className={cn(
          'rounded-lg border-2 border-primary bg-primary/5 p-3',
          className
        )}
      >
        <Textarea
          ref={textareaRef}
          value={editText}
          onChange={(e) => setEditText(e.target.value)}
          onKeyDown={handleKeyDown}
          className="min-h-[80px] resize-none"
          placeholder="Edit transcript..."
        />
        <div className="mt-2 flex items-center justify-between">
          <span className="text-xs text-muted-foreground">
            Press ⌘+Enter to save, Escape to cancel
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" onClick={onCancelEdit}>
              <X className="mr-1 h-3.5 w-3.5" />
              Cancel
            </Button>
            <Button size="sm" onClick={handleSave}>
              <Check className="mr-1 h-3.5 w-3.5" />
              Save
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div
      id={`segment-${segment.id}`}
      className={cn(
        'group rounded-lg p-3 transition-colors hover:bg-muted/50',
        segment.isEdited && 'border-l-2 border-l-primary/50',
        className
      )}
    >
      {/* Header: Speaker + Timestamp */}
      <div className="mb-1.5 flex items-center justify-between">
        <div className="flex items-center gap-2">
          {showSpeaker && segment.speaker && (
            <SpeakerLabel
              speaker={segment.speaker}
              color={getSpeakerColor(segment.speaker)}
            />
          )}
          {segment.isEdited && (
            <span className="text-xs text-muted-foreground">(edited)</span>
          )}
        </div>
        <div className="flex items-center gap-2">
          {showTimestamp && segment.startTime !== undefined && (
            <span className="text-xs tabular-nums text-muted-foreground">
              {formatTime(segment.startTime)}
            </span>
          )}
          <Button
            size="icon"
            variant="ghost"
            className="h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100"
            onClick={onStartEdit}
          >
            <Edit2 className="h-3.5 w-3.5" />
            <span className="sr-only">Edit segment</span>
          </Button>
        </div>
      </div>

      {/* Words */}
      <div className="leading-relaxed">
        {segment.words.length > 0 ? (
          segment.words.map((word, index) => {
            const isHighlighted = currentTimeMs !== undefined &&
              currentTimeMs >= word.start &&
              currentTimeMs < word.end;
            const isLowConfidence = showConfidence && word.probability < CONFIDENCE_MEDIUM;

            return (
              <WordSpan
                key={`${segment.id}-${index}`}
                word={word}
                isHighlighted={isHighlighted}
                isLowConfidence={isLowConfidence}
                onClick={() => onWordClick(word.start)}
              />
            );
          })
        ) : (
          <span>{segment.editedText ?? segment.text}</span>
        )}
      </div>

      {/* Confidence bar (optional) */}
      {showConfidence && (
        <div className="mt-2 flex items-center gap-2">
          <span className="text-xs text-muted-foreground">Confidence:</span>
          <div className="h-1 flex-1 overflow-hidden rounded-full bg-muted">
            <div
              className={cn(
                'h-full transition-all',
                segment.confidence >= 0.9 && 'bg-green-500',
                segment.confidence >= 0.7 && segment.confidence < 0.9 && 'bg-yellow-500',
                segment.confidence < 0.7 && 'bg-red-500'
              )}
              style={{ width: `${segment.confidence * 100}%` }}
            />
          </div>
          <span className="text-xs tabular-nums text-muted-foreground">
            {Math.round(segment.confidence * 100)}%
          </span>
        </div>
      )}
    </div>
  );
});
```

---

## Word Span Component

```typescript
// components/WordSpan.tsx

import { memo } from 'react';
import { cn } from '@/lib/utils';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import type { WordSpanProps } from '../types';

export const WordSpan = memo(function WordSpan({
  word,
  isHighlighted,
  isLowConfidence,
  onClick,
  className,
}: WordSpanProps) {
  const formatTime = (ms: number): string => {
    const seconds = (ms / 1000).toFixed(2);
    return `${seconds}s`;
  };

  const content = (
    <span
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick();
        }
      }}
      className={cn(
        'cursor-pointer rounded-sm px-0.5 transition-all duration-150',
        // Base styles
        'hover:bg-muted',
        // Highlighted (during playback)
        isHighlighted && 'bg-primary/20 text-primary font-medium',
        // Low confidence
        isLowConfidence && 'text-muted-foreground underline decoration-dotted decoration-orange-400',
        // Focus
        'focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1',
        className
      )}
    >
      {word.word}
    </span>
  );

  // Show tooltip with timestamp for low confidence words
  if (isLowConfidence) {
    return (
      <Tooltip>
        <TooltipTrigger asChild>
          {content}
        </TooltipTrigger>
        <TooltipContent side="top" className="text-xs">
          <div className="flex flex-col gap-1">
            <span>Low confidence: {Math.round(word.probability * 100)}%</span>
            <span className="text-muted-foreground">
              {formatTime(word.start)} - {formatTime(word.end)}
            </span>
          </div>
        </TooltipContent>
      </Tooltip>
    );
  }

  return (
    <>
      {content}
      {' '}
    </>
  );
});
```

---

## Partial Transcript Component

```typescript
// components/PartialTranscript.tsx

import { memo } from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PartialTranscriptProps } from '../types';

export const PartialTranscript = memo(function PartialTranscript({
  text,
  isProcessing,
  className,
}: PartialTranscriptProps) {
  return (
    <div
      className={cn(
        'flex items-start gap-2 rounded-lg border border-dashed border-muted-foreground/30 bg-muted/30 p-3',
        className
      )}
      role="status"
      aria-live="polite"
      aria-atomic="false"
    >
      {isProcessing && (
        <Loader2 className="mt-0.5 h-4 w-4 shrink-0 animate-spin text-muted-foreground" />
      )}
      <p
        className={cn(
          'flex-1 italic text-muted-foreground',
          'animate-pulse',
        )}
        style={{
          animationDuration: '1.5s',
        }}
      >
        {text || 'Listening...'}
        <span className="ml-0.5 inline-block h-4 w-0.5 animate-blink bg-muted-foreground" />
      </p>
    </div>
  );
});

// Add to global CSS:
// @keyframes blink {
//   0%, 50% { opacity: 1; }
//   51%, 100% { opacity: 0; }
// }
// .animate-blink { animation: blink 1s infinite; }
```

---

## Intent Preview Component

```typescript
// components/IntentPreview.tsx

import { memo } from 'react';
import { Target, CheckCircle2, XCircle, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { IntentPreviewProps, FollowupAction } from '../types';

export const IntentPreview = memo(function IntentPreview({
  intent,
  command,
  onActionClick,
  className,
}: IntentPreviewProps) {
  const confidencePercent = Math.round(intent.confidence * 100);
  const hasSlots = Object.keys(intent.slots).length > 0;

  return (
    <div
      className={cn(
        'rounded-lg border p-3',
        command?.success && 'border-green-500/30 bg-green-500/5',
        command && !command.success && 'border-red-500/30 bg-red-500/5',
        !command && 'border-primary/30 bg-primary/5',
        className
      )}
    >
      {/* Intent Header */}
      <div className="flex items-center gap-2">
        {command ? (
          command.success ? (
            <CheckCircle2 className="h-4 w-4 text-green-500" />
          ) : (
            <XCircle className="h-4 w-4 text-red-500" />
          )
        ) : (
          <Target className="h-4 w-4 text-primary" />
        )}
        
        <Badge variant="secondary" className="font-mono text-xs">
          {intent.intent}
        </Badge>
        
        <span className="text-xs text-muted-foreground">
          {confidencePercent}% confidence
        </span>
        
        <Badge variant="outline" className="ml-auto text-xs">
          {intent.method}
        </Badge>
      </div>

      {/* Slots */}
      {hasSlots && (
        <div className="mt-2 flex flex-wrap gap-2">
          {Object.entries(intent.slots).map(([key, value]) => (
            <div key={key} className="flex items-center gap-1 text-sm">
              <span className="text-muted-foreground">{key}:</span>
              <span className="font-medium">"{String(value.normalized)}"</span>
            </div>
          ))}
        </div>
      )}

      {/* Command Result */}
      {command && (
        <div className="mt-2 border-t pt-2">
          <p className={cn(
            'text-sm',
            command.success ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'
          )}>
            {command.message}
          </p>
          
          {command.speakText && (
            <p className="mt-1 text-xs italic text-muted-foreground">
              "{command.speakText}"
            </p>
          )}
        </div>
      )}

      {/* Follow-up Actions */}
      {command?.actions && command.actions.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-2 border-t pt-2">
          {command.actions.map((action, index) => (
            <Button
              key={index}
              size="sm"
              variant="outline"
              onClick={() => onActionClick?.(action)}
              className="h-7 text-xs"
            >
              {action.label}
              <ChevronRight className="ml-1 h-3 w-3" />
            </Button>
          ))}
        </div>
      )}
    </div>
  );
});
```

---

## Speaker Label Component

```typescript
// components/SpeakerLabel.tsx

import { memo, useState, useRef, useEffect } from 'react';
import { User, Edit2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import type { SpeakerLabelProps } from '../types';

export const SpeakerLabel = memo(function SpeakerLabel({
  speaker,
  color,
  editable = false,
  onRename,
  className,
}: SpeakerLabelProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [editName, setEditName] = useState(speaker);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isEditing && inputRef.current) {
      inputRef.current.focus();
      inputRef.current.select();
    }
  }, [isEditing]);

  const handleSave = () => {
    const trimmed = editName.trim();
    if (trimmed && trimmed !== speaker) {
      onRename?.(trimmed);
    }
    setIsEditing(false);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleSave();
    } else if (e.key === 'Escape') {
      setEditName(speaker);
      setIsEditing(false);
    }
  };

  if (isEditing) {
    return (
      <Input
        ref={inputRef}
        value={editName}
        onChange={(e) => setEditName(e.target.value)}
        onBlur={handleSave}
        onKeyDown={handleKeyDown}
        className="h-6 w-24 text-xs"
      />
    );
  }

  return (
    <div
      className={cn(
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
        editable && 'cursor-pointer hover:opacity-80',
        className
      )}
      style={{ backgroundColor: `${color}20`, color }}
      onClick={() => editable && setIsEditing(true)}
      role={editable ? 'button' : undefined}
      tabIndex={editable ? 0 : undefined}
    >
      <User className="h-3 w-3" />
      <span>{speaker}</span>
      {editable && <Edit2 className="h-2.5 w-2.5 opacity-50" />}
    </div>
  );
});
```

---

## Transcription Hook

```typescript
// hooks/useTranscription.ts

import { useCallback, useMemo, useReducer } from 'react';
import type {
  UseTranscriptionReturn,
  TranscriptSegment,
  IntentMatch,
  CommandResult,
} from '../types';

// === State ===

interface TranscriptionState {
  readonly editingSegmentId: string | null;
  readonly editedSegments: Readonly<Record<string, string>>;
}

const initialState: TranscriptionState = {
  editingSegmentId: null,
  editedSegments: {},
};

// === Actions ===

type TranscriptionAction =
  | { type: 'START_EDIT'; payload: string }
  | { type: 'SAVE_EDIT'; payload: { segmentId: string; text: string } }
  | { type: 'CANCEL_EDIT' }
  | { type: 'CLEAR_ALL' };

function transcriptionReducer(
  state: TranscriptionState,
  action: TranscriptionAction
): TranscriptionState {
  switch (action.type) {
    case 'START_EDIT':
      return { ...state, editingSegmentId: action.payload };
    case 'SAVE_EDIT':
      return {
        ...state,
        editingSegmentId: null,
        editedSegments: {
          ...state.editedSegments,
          [action.payload.segmentId]: action.payload.text,
        },
      };
    case 'CANCEL_EDIT':
      return { ...state, editingSegmentId: null };
    case 'CLEAR_ALL':
      return initialState;
    default: {
      const _exhaustive: never = action;
      return state;
    }
  }
}

// === Hook ===

interface UseTranscriptionOptions {
  readonly segments: readonly TranscriptSegment[];
  readonly partialText: string;
  readonly intents: readonly IntentMatch[];
  readonly commands: readonly CommandResult[];
}

export function useTranscription(options: UseTranscriptionOptions): UseTranscriptionReturn {
  const { segments, partialText, intents, commands } = options;
  const [state, dispatch] = useReducer(transcriptionReducer, initialState);

  // Merge edited text into segments
  const mergedSegments = useMemo(() => {
    return segments.map((segment) => {
      const editedText = state.editedSegments[segment.id];
      if (editedText !== undefined) {
        return {
          ...segment,
          isEdited: true,
          editedText,
        };
      }
      return segment;
    });
  }, [segments, state.editedSegments]);

  const startEdit = useCallback((segmentId: string) => {
    dispatch({ type: 'START_EDIT', payload: segmentId });
  }, []);

  const saveEdit = useCallback((segmentId: string, newText: string) => {
    dispatch({ type: 'SAVE_EDIT', payload: { segmentId, text: newText } });
  }, []);

  const cancelEdit = useCallback(() => {
    dispatch({ type: 'CANCEL_EDIT' });
  }, []);

  const clearAll = useCallback(() => {
    dispatch({ type: 'CLEAR_ALL' });
  }, []);

  return {
    segments: mergedSegments,
    partialText,
    intents,
    commands,
    editingSegmentId: state.editingSegmentId,
    startEdit,
    saveEdit,
    cancelEdit,
    clearAll,
  };
}
```

---

## Word Highlight Hook

```typescript
// hooks/useWordHighlight.ts

import { useMemo, useEffect, useRef } from 'react';
import type { UseWordHighlightReturn, TranscriptSegment } from '../types';

interface UseWordHighlightOptions {
  readonly segments: readonly TranscriptSegment[];
  readonly currentTimeMs?: number;
  readonly autoScroll?: boolean;
}

export function useWordHighlight(options: UseWordHighlightOptions): UseWordHighlightReturn {
  const { segments, currentTimeMs, autoScroll = true } = options;
  const lastScrolledRef = useRef<string | null>(null);

  // Find the currently highlighted word
  const { highlightedSegmentId, highlightedWordIndex } = useMemo(() => {
    if (currentTimeMs === undefined) {
      return { highlightedSegmentId: null, highlightedWordIndex: null };
    }

    for (const segment of segments) {
      if (!segment.words.length) continue;

      const wordIndex = segment.words.findIndex(
        (word) => currentTimeMs >= word.start && currentTimeMs < word.end
      );

      if (wordIndex !== -1) {
        return {
          highlightedSegmentId: segment.id,
          highlightedWordIndex: wordIndex,
        };
      }
    }

    return { highlightedSegmentId: null, highlightedWordIndex: null };
  }, [segments, currentTimeMs]);

  // Auto-scroll to highlighted segment
  useEffect(() => {
    if (!autoScroll || !highlightedSegmentId) return;
    if (lastScrolledRef.current === highlightedSegmentId) return;

    const element = document.getElementById(`segment-${highlightedSegmentId}`);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      lastScrolledRef.current = highlightedSegmentId;
    }
  }, [highlightedSegmentId, autoScroll]);

  const scrollToWord = (segmentId: string, _wordIndex: number) => {
    const element = document.getElementById(`segment-${segmentId}`);
    element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  return {
    highlightedWordIndex,
    highlightedSegmentId,
    scrollToWord,
  };
}
```

---

## Export Utilities

```typescript
// utils/export-formats.ts

import type { TranscriptSegment, ExportFormat } from '../types';
import { MAX_LINE_LENGTH } from '../constants';

interface ExportOptions {
  readonly includeSpeakers: boolean;
  readonly includeTimestamps: boolean;
}

export function formatTranscriptForExport(
  segments: readonly TranscriptSegment[],
  format: ExportFormat,
  options: ExportOptions
): string {
  switch (format) {
    case 'text':
      return exportAsText(segments, options);
    case 'srt':
      return exportAsSRT(segments);
    case 'vtt':
      return exportAsVTT(segments);
    case 'json':
      return exportAsJSON(segments);
    default: {
      const _exhaustive: never = format;
      return '';
    }
  }
}

function exportAsText(
  segments: readonly TranscriptSegment[],
  options: ExportOptions
): string {
  return segments
    .map((segment) => {
      const text = segment.editedText ?? segment.text;
      
      if (options.includeSpeakers && segment.speaker) {
        return `${segment.speaker}: ${text}`;
      }
      
      return text;
    })
    .join('\n\n');
}

function exportAsSRT(segments: readonly TranscriptSegment[]): string {
  return segments
    .map((segment, index) => {
      const startTime = formatSRTTime(segment.startTime ?? 0);
      const endTime = formatSRTTime(segment.endTime ?? segment.durationMs);
      const text = wrapText(segment.editedText ?? segment.text, MAX_LINE_LENGTH);

      return `${index + 1}\n${startTime} --> ${endTime}\n${text}`;
    })
    .join('\n\n');
}

function exportAsVTT(segments: readonly TranscriptSegment[]): string {
  const header = 'WEBVTT\n\n';
  
  const cues = segments
    .map((segment) => {
      const startTime = formatVTTTime(segment.startTime ?? 0);
      const endTime = formatVTTTime(segment.endTime ?? segment.durationMs);
      const text = segment.editedText ?? segment.text;

      return `${startTime} --> ${endTime}\n${text}`;
    })
    .join('\n\n');

  return header + cues;
}

function exportAsJSON(segments: readonly TranscriptSegment[]): string {
  return JSON.stringify(
    segments.map((segment) => ({
      id: segment.id,
      text: segment.editedText ?? segment.text,
      speaker: segment.speaker,
      language: segment.language,
      confidence: segment.confidence,
      startTime: segment.startTime,
      endTime: segment.endTime,
      words: segment.words,
      isEdited: segment.isEdited,
    })),
    null,
    2
  );
}

function formatSRTTime(ms: number): string {
  const hours = Math.floor(ms / 3600000);
  const minutes = Math.floor((ms % 3600000) / 60000);
  const seconds = Math.floor((ms % 60000) / 1000);
  const milliseconds = ms % 1000;

  return [
    hours.toString().padStart(2, '0'),
    minutes.toString().padStart(2, '0'),
    seconds.toString().padStart(2, '0'),
  ].join(':') + ',' + milliseconds.toString().padStart(3, '0');
}

function formatVTTTime(ms: number): string {
  const hours = Math.floor(ms / 3600000);
  const minutes = Math.floor((ms % 3600000) / 60000);
  const seconds = Math.floor((ms % 60000) / 1000);
  const milliseconds = ms % 1000;

  return [
    hours.toString().padStart(2, '0'),
    minutes.toString().padStart(2, '0'),
    seconds.toString().padStart(2, '0'),
  ].join(':') + '.' + milliseconds.toString().padStart(3, '0');
}

function wrapText(text: string, maxLength: number): string {
  const words = text.split(' ');
  const lines: string[] = [];
  let currentLine = '';

  for (const word of words) {
    if (currentLine.length + word.length + 1 <= maxLength) {
      currentLine += (currentLine ? ' ' : '') + word;
    } else {
      if (currentLine) lines.push(currentLine);
      currentLine = word;
    }
  }
  
  if (currentLine) lines.push(currentLine);
  return lines.join('\n');
}
```

---

## Speaker Color Utility

```typescript
// utils/speaker-colors.ts

import { SPEAKER_COLORS } from '../constants';

const speakerColorMap = new Map<string, string>();

export function getSpeakerColor(speaker: string): string {
  if (speakerColorMap.has(speaker)) {
    return speakerColorMap.get(speaker)!;
  }

  // Assign next available color
  const index = speakerColorMap.size % SPEAKER_COLORS.length;
  const color = SPEAKER_COLORS[index];
  speakerColorMap.set(speaker, color);
  
  return color;
}

export function resetSpeakerColors(): void {
  speakerColorMap.clear();
}
```

---

## Display Modes

### Live Recording Mode

```
┌─────────────────────────────────────────┐
│ Transcript                    [⚙] [📋] │
├─────────────────────────────────────────┤
│                                         │
│ The quick brown fox jumps over the      │
│ lazy dog. This is a complete sentence   │
│ that has been committed.                │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ And now I'm speaking about... █     │ │ ← Partial (italic/pulsing)
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 🎯 CREATE_SPEC (92%)                │ │ ← Intent preview
│ │    title: "authentication"          │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

### Review/Edit Mode

```
┌─────────────────────────────────────────┐
│ Transcript (3)        [Edit] [▾] [📋]  │
├─────────────────────────────────────────┤
│                                         │
│ 🎤 Speaker 1                    0:00 ✏  │
│ The quick brown fox jumps over the      │
│ lazy dog.                               │
│ ━━━━━━━━━━━━━━━━━━ 95%                  │
│                                         │
│ 🎤 Speaker 2                    0:05 ✏  │
│ This is a response from another         │
│ speaker in the conversation.            │
│ ━━━━━━━━━━━━━━━━━ 88%                   │
│                                         │
│ 🎤 Speaker 1               (edited) ✏   │
│ And here we continue discussing.        │
│ ━━━━━━━━━━━━━━━━━━━ 92%                 │
│                                         │
└─────────────────────────────────────────┘
```

### Playback Mode (Audio Sync)

```
┌─────────────────────────────────────────┐
│ Transcript                     0:03/0:15│
├─────────────────────────────────────────┤
│                                         │
│ The quick [brown] fox jumps over the    │
│            ▲                            │
│         highlighted word                │
│                                         │
└─────────────────────────────────────────┘
```

---

## Accessibility Requirements

| Requirement | Implementation |
|-------------|----------------|
| Live regions | `aria-live="polite"` for partial transcripts |
| Keyboard navigation | Tab between segments, Enter to edit |
| Focus management | Focus textarea on edit, restore on save/cancel |
| Screen reader | ARIA labels for speaker badges, timestamps |
| Word interaction | Focus ring on word spans, keyboard activation |
| High contrast | Underline style for low-confidence words |

---

## Performance Considerations

| Concern | Mitigation |
|---------|------------|
| Long transcripts | Virtual scrolling after 100 segments |
| Re-renders | Memoized components, stable callbacks |
| Word highlighting | Efficient binary search for current word |
| Partial updates | Debounced updates (100ms) |
| Export | Web Worker for large transcript processing |

---

## Error States

| State | Display |
|-------|---------|
| No transcript | "Start recording to see transcription" |
| Processing | Spinner + "Processing audio..." |
| Error | "Transcription failed. [Retry]" |
| Empty result | "No speech detected in recording" |
| Edit conflict | "Changes could not be saved. [Retry]" |

---

## Related Specifications

- [Voice Input Overview](./00-overview.md)
- [Voice Recorder](./01-voice-recorder.md)
- [Audio Player](./03-audio-player.md)
- [Voice-CLI Service](../../14-microservices/15-voice-cli-service.md)
- [Voice-CLI OpenAPI](../../14-microservices/16-voice-cli-openapi.md)
