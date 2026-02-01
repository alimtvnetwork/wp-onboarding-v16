# Audio Player Component Specification

> **Version:** 2.0.0  
> **Status:** Draft  
> **Last Updated:** 2026-01-30  
> **Related:** [02-transcription-display.md](./02-transcription-display.md), [01-voice-recorder.md](./01-voice-recorder.md)

---

## 1. Overview

The Audio Player component provides playback controls with precise timestamp synchronization to the Transcription Display. It supports variable speed playback, waveform timeline scrubbing, segment looping, and bidirectional sync with word-level highlighting.

---

## 2. Component Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        AudioPlayer                               │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    WaveformTimeline                          ││
│  │  ┌─────────────────────────────────────────────────────────┐││
│  │  │ ████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ │││
│  │  │ ▲ Playhead                        Segments marked       │││
│  │  └─────────────────────────────────────────────────────────┘││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    PlaybackControls                          ││
│  │  ┌────┐ ┌────┐ ┌────────┐ ┌────┐ ┌────┐   ┌──────┐ ┌─────┐ ││
│  │  │ ⏮ │ │ ◀◀ │ │  ▶/❚❚  │ │ ▶▶ │ │ ⏭ │   │ 1.0x │ │ 🔊  │ ││
│  │  └────┘ └────┘ └────────┘ └────┘ └────┘   └──────┘ └─────┘ ││
│  │   Prev   -10s   Play/Pause  +10s  Next    Speed   Volume   ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  00:01:23 / 00:05:47          [A ←→ B] Loop  [↓] Download  ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. TypeScript Interfaces

### 3.1 Core Types

```typescript
// Playback state machine
type PlaybackStatus = 
  | 'Idle'           // No audio loaded
  | 'Loading'        // Audio source loading
  | 'Ready'          // Loaded, not playing
  | 'Playing'        // Active playback
  | 'Paused'         // User paused
  | 'Seeking'        // Scrubbing timeline
  | 'Buffering'      // Network buffering
  | 'Error';         // Playback error

interface PlaybackState {
  readonly status: PlaybackStatus;
  readonly currentTime: number;      // Seconds
  readonly duration: number;         // Seconds
  readonly bufferedRanges: TimeRange[];
  readonly playbackRate: number;     // 0.5 - 2.0
  readonly volume: number;           // 0.0 - 1.0
  readonly isMuted: boolean;
  readonly isLooping: boolean;
  readonly loopRange: TimeRange | null;
}

interface TimeRange {
  readonly start: number;  // Seconds
  readonly end: number;    // Seconds
}

interface AudioSource {
  readonly id: string;
  readonly url: string;
  readonly mimeType: string;
  readonly duration: number;
  readonly sampleRate: number;
  readonly channels: number;
  readonly waveformData?: Float32Array;  // Pre-computed peaks
}
```

### 3.2 Synchronization Types

```typescript
interface TimestampSyncConfig {
  readonly transcriptId: string;
  readonly syncMode: 'bidirectional' | 'playback-only' | 'transcript-only';
  readonly highlightLead: number;     // ms ahead of playhead
  readonly scrollBehavior: 'smooth' | 'instant' | 'none';
  readonly wordSnapThreshold: number; // ms tolerance for word boundaries
}

interface SyncEvent {
  readonly type: 'seek' | 'word-click' | 'segment-click';
  readonly timestamp: number;
  readonly source: 'player' | 'transcript';
  readonly wordId?: string;
  readonly segmentId?: string;
}

// Emitted during playback for transcript highlighting
interface PlayheadUpdate {
  readonly currentTime: number;
  readonly currentWordId: string | null;
  readonly currentSegmentId: string | null;
  readonly upcomingWords: readonly string[];  // Next 3 word IDs
}
```

### 3.3 Segment & Marker Types

```typescript
interface AudioSegment {
  readonly id: string;
  readonly start: number;
  readonly end: number;
  readonly label: string;
  readonly color: string;        // Semantic token reference
  readonly speakerId?: string;
}

interface AudioMarker {
  readonly id: string;
  readonly timestamp: number;
  readonly type: 'bookmark' | 'note' | 'intent' | 'error';
  readonly label: string;
  readonly metadata?: Record<string, unknown>;
}

interface WaveformConfig {
  readonly barWidth: number;         // Pixels per bar
  readonly barGap: number;           // Gap between bars
  readonly barRadius: number;        // Border radius
  readonly playedColor: string;      // CSS variable reference
  readonly unplayedColor: string;    // CSS variable reference
  readonly cursorColor: string;      // Playhead color
  readonly segmentOpacity: number;   // 0.0 - 1.0
}
```

---

## 4. Hook Specifications

### 4.1 useAudioPlayback

Primary hook for audio element control and state management.

```typescript
interface UseAudioPlaybackOptions {
  readonly src: string | null;
  readonly autoPlay?: boolean;
  readonly initialVolume?: number;
  readonly initialRate?: number;
  readonly onTimeUpdate?: (time: number) => void;
  readonly onEnded?: () => void;
  readonly onError?: (error: AudioPlaybackError) => void;
}

interface UseAudioPlaybackReturn {
  // Refs
  readonly audioRef: React.RefObject<HTMLAudioElement>;
  
  // State
  readonly state: PlaybackState;
  readonly isReady: boolean;
  readonly error: AudioPlaybackError | null;
  
  // Actions
  readonly play: () => Promise<void>;
  readonly pause: () => void;
  readonly togglePlayPause: () => Promise<void>;
  readonly seek: (time: number) => void;
  readonly seekRelative: (delta: number) => void;
  readonly setVolume: (volume: number) => void;
  readonly setMuted: (muted: boolean) => void;
  readonly setPlaybackRate: (rate: number) => void;
  readonly setLoopRange: (range: TimeRange | null) => void;
  
  // Utilities
  readonly formatTime: (seconds: number) => string;
  readonly getBufferedPercent: () => number;
}

// Implementation pattern
function useAudioPlayback(options: UseAudioPlaybackOptions): UseAudioPlaybackReturn {
  const audioRef = useRef<HTMLAudioElement>(null);
  
  const [state, dispatch] = useReducer(playbackReducer, {
    status: 'Idle',
    currentTime: 0,
    duration: 0,
    bufferedRanges: [],
    playbackRate: options.initialRate ?? 1.0,
    volume: options.initialVolume ?? 1.0,
    isMuted: false,
    isLooping: false,
    loopRange: null,
  });

  // RAF-based time updates for smooth playhead
  useEffect(() => {
    if (state.status !== 'Playing') return;
    
    let rafId: number;
    const updateTime = () => {
      if (audioRef.current) {
        const time = audioRef.current.currentTime;
        dispatch({ type: 'TIME_UPDATE', payload: time });
        options.onTimeUpdate?.(time);
      }
      rafId = requestAnimationFrame(updateTime);
    };
    
    rafId = requestAnimationFrame(updateTime);
    return () => cancelAnimationFrame(rafId);
  }, [state.status]);

  // Loop range enforcement
  useEffect(() => {
    if (!state.loopRange || !audioRef.current) return;
    
    const audio = audioRef.current;
    const handleTimeUpdate = () => {
      if (audio.currentTime >= state.loopRange!.end) {
        audio.currentTime = state.loopRange!.start;
      }
    };
    
    audio.addEventListener('timeupdate', handleTimeUpdate);
    return () => audio.removeEventListener('timeupdate', handleTimeUpdate);
  }, [state.loopRange]);

  return { audioRef, state, /* ... actions */ };
}
```

### 4.2 useTimestampSync

Coordinates playback position with transcript word highlighting.

```typescript
interface UseTimestampSyncOptions {
  readonly config: TimestampSyncConfig;
  readonly segments: readonly TranscriptSegment[];
  readonly playbackState: PlaybackState;
  readonly onSeekRequest: (time: number) => void;
}

interface UseTimestampSyncReturn {
  readonly currentWordId: string | null;
  readonly currentSegmentId: string | null;
  readonly visibleRange: TimeRange;
  
  // Called when user clicks a word in transcript
  readonly handleWordClick: (wordId: string, timestamp: number) => void;
  
  // Called when user clicks a segment header
  readonly handleSegmentClick: (segmentId: string) => void;
  
  // Subscribe to playhead updates
  readonly subscribeToPlayhead: (callback: (update: PlayheadUpdate) => void) => () => void;
}

function useTimestampSync(options: UseTimestampSyncOptions): UseTimestampSyncReturn {
  const { config, segments, playbackState, onSeekRequest } = options;
  
  // Build word index for O(log n) lookup
  const wordIndex = useMemo(() => {
    const index: Array<{ wordId: string; segmentId: string; start: number; end: number }> = [];
    
    for (const segment of segments) {
      for (const word of segment.words) {
        index.push({
          wordId: word.id,
          segmentId: segment.id,
          start: word.startTime,
          end: word.endTime,
        });
      }
    }
    
    return index.sort((a, b) => a.start - b.start);
  }, [segments]);

  // Binary search for current word
  const findCurrentWord = useCallback((time: number) => {
    const adjustedTime = time + (config.highlightLead / 1000);
    
    let left = 0;
    let right = wordIndex.length - 1;
    
    while (left <= right) {
      const mid = Math.floor((left + right) / 2);
      const word = wordIndex[mid];
      
      if (adjustedTime >= word.start && adjustedTime < word.end) {
        return word;
      } else if (adjustedTime < word.start) {
        right = mid - 1;
      } else {
        left = mid + 1;
      }
    }
    
    return null;
  }, [wordIndex, config.highlightLead]);

  // Current word tracking with RAF
  const [currentWord, setCurrentWord] = useState<{ wordId: string; segmentId: string } | null>(null);
  
  useEffect(() => {
    if (playbackState.status !== 'Playing') return;
    
    let rafId: number;
    const update = () => {
      const word = findCurrentWord(playbackState.currentTime);
      if (word?.wordId !== currentWord?.wordId) {
        setCurrentWord(word);
      }
      rafId = requestAnimationFrame(update);
    };
    
    rafId = requestAnimationFrame(update);
    return () => cancelAnimationFrame(rafId);
  }, [playbackState.status, playbackState.currentTime, findCurrentWord]);

  return {
    currentWordId: currentWord?.wordId ?? null,
    currentSegmentId: currentWord?.segmentId ?? null,
    // ... other returns
  };
}
```

### 4.3 useWaveformData

Extracts and caches waveform visualization data.

```typescript
interface UseWaveformDataOptions {
  readonly audioUrl: string | null;
  readonly barsCount: number;          // Number of bars to render
  readonly cacheKey?: string;          // For persistent caching
}

interface UseWaveformDataReturn {
  readonly peaks: Float32Array | null;
  readonly isLoading: boolean;
  readonly error: Error | null;
  readonly duration: number;
}

function useWaveformData(options: UseWaveformDataOptions): UseWaveformDataReturn {
  const { audioUrl, barsCount, cacheKey } = options;
  
  const [state, setState] = useState<{
    peaks: Float32Array | null;
    isLoading: boolean;
    error: Error | null;
    duration: number;
  }>({
    peaks: null,
    isLoading: false,
    error: null,
    duration: 0,
  });

  useEffect(() => {
    if (!audioUrl) return;
    
    const controller = new AbortController();
    
    async function extractPeaks() {
      setState(s => ({ ...s, isLoading: true, error: null }));
      
      try {
        // Check cache first
        if (cacheKey) {
          const cached = await loadFromIndexedDB(cacheKey);
          if (cached) {
            setState({ peaks: cached.peaks, duration: cached.duration, isLoading: false, error: null });
            return;
          }
        }
        
        // Fetch and decode audio
        const response = await fetch(audioUrl, { signal: controller.signal });
        const arrayBuffer = await response.arrayBuffer();
        
        const audioContext = new OfflineAudioContext(1, 1, 44100);
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
        
        // Extract peaks
        const channelData = audioBuffer.getChannelData(0);
        const samplesPerBar = Math.floor(channelData.length / barsCount);
        const peaks = new Float32Array(barsCount);
        
        for (let i = 0; i < barsCount; i++) {
          const start = i * samplesPerBar;
          const end = start + samplesPerBar;
          let max = 0;
          
          for (let j = start; j < end; j++) {
            const abs = Math.abs(channelData[j]);
            if (abs > max) max = abs;
          }
          
          peaks[i] = max;
        }
        
        // Cache result
        if (cacheKey) {
          await saveToIndexedDB(cacheKey, { peaks, duration: audioBuffer.duration });
        }
        
        setState({ peaks, duration: audioBuffer.duration, isLoading: false, error: null });
      } catch (err) {
        if (!controller.signal.aborted) {
          setState(s => ({ ...s, isLoading: false, error: err as Error }));
        }
      }
    }
    
    extractPeaks();
    return () => controller.abort();
  }, [audioUrl, barsCount, cacheKey]);

  return state;
}
```

---

## 5. Component Specifications

### 5.1 AudioPlayer (Container)

```typescript
interface AudioPlayerProps {
  readonly source: AudioSource | null;
  readonly segments?: readonly AudioSegment[];
  readonly markers?: readonly AudioMarker[];
  readonly syncConfig?: TimestampSyncConfig;
  readonly transcriptSegments?: readonly TranscriptSegment[];
  readonly onPlayheadUpdate?: (update: PlayheadUpdate) => void;
  readonly onSegmentClick?: (segment: AudioSegment) => void;
  readonly onMarkerClick?: (marker: AudioMarker) => void;
  readonly className?: string;
}

// Usage
<AudioPlayer
  source={audioSource}
  segments={speakerSegments}
  markers={intentMarkers}
  syncConfig={{
    transcriptId: 'transcript-001',
    syncMode: 'bidirectional',
    highlightLead: 100,
    scrollBehavior: 'smooth',
    wordSnapThreshold: 50,
  }}
  transcriptSegments={transcriptData}
  onPlayheadUpdate={handlePlayheadUpdate}
/>
```

### 5.2 WaveformTimeline

Canvas-based waveform with interactive scrubbing.

```typescript
interface WaveformTimelineProps {
  readonly peaks: Float32Array | null;
  readonly duration: number;
  readonly currentTime: number;
  readonly bufferedRanges: readonly TimeRange[];
  readonly segments?: readonly AudioSegment[];
  readonly markers?: readonly AudioMarker[];
  readonly loopRange?: TimeRange | null;
  readonly config?: Partial<WaveformConfig>;
  readonly onSeek: (time: number) => void;
  readonly onLoopRangeChange?: (range: TimeRange | null) => void;
}

// Rendering implementation
function WaveformTimeline({ peaks, duration, currentTime, ...props }: WaveformTimelineProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  
  const config: WaveformConfig = {
    barWidth: 3,
    barGap: 1,
    barRadius: 1,
    playedColor: 'hsl(var(--primary))',
    unplayedColor: 'hsl(var(--muted))',
    cursorColor: 'hsl(var(--accent))',
    segmentOpacity: 0.3,
    ...props.config,
  };

  // Draw waveform
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas || !peaks) return;
    
    const ctx = canvas.getContext('2d')!;
    const { width, height } = canvas;
    const playedWidth = (currentTime / duration) * width;
    
    ctx.clearRect(0, 0, width, height);
    
    // Draw bars
    const totalBarWidth = config.barWidth + config.barGap;
    const barsVisible = Math.floor(width / totalBarWidth);
    
    for (let i = 0; i < barsVisible && i < peaks.length; i++) {
      const x = i * totalBarWidth;
      const barHeight = peaks[i] * height * 0.8;
      const y = (height - barHeight) / 2;
      
      ctx.fillStyle = x < playedWidth ? config.playedColor : config.unplayedColor;
      ctx.beginPath();
      ctx.roundRect(x, y, config.barWidth, barHeight, config.barRadius);
      ctx.fill();
    }
    
    // Draw segments
    for (const segment of props.segments ?? []) {
      const startX = (segment.start / duration) * width;
      const endX = (segment.end / duration) * width;
      
      ctx.fillStyle = segment.color;
      ctx.globalAlpha = config.segmentOpacity;
      ctx.fillRect(startX, 0, endX - startX, height);
      ctx.globalAlpha = 1;
    }
    
    // Draw playhead
    ctx.fillStyle = config.cursorColor;
    ctx.fillRect(playedWidth - 1, 0, 2, height);
    
  }, [peaks, currentTime, duration, config, props.segments]);

  // Scrubbing interaction
  const handlePointerDown = (e: React.PointerEvent) => {
    const rect = containerRef.current!.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const time = (x / rect.width) * duration;
    props.onSeek(Math.max(0, Math.min(duration, time)));
    
    // Continue tracking for drag
    const handleMove = (e: PointerEvent) => {
      const x = e.clientX - rect.left;
      const time = (x / rect.width) * duration;
      props.onSeek(Math.max(0, Math.min(duration, time)));
    };
    
    const handleUp = () => {
      document.removeEventListener('pointermove', handleMove);
      document.removeEventListener('pointerup', handleUp);
    };
    
    document.addEventListener('pointermove', handleMove);
    document.addEventListener('pointerup', handleUp);
  };

  return (
    <div 
      ref={containerRef}
      className="relative h-16 cursor-pointer"
      onPointerDown={handlePointerDown}
    >
      <canvas 
        ref={canvasRef}
        className="w-full h-full"
      />
      {/* Marker overlays */}
      {props.markers?.map(marker => (
        <MarkerIndicator 
          key={marker.id}
          marker={marker}
          position={(marker.timestamp / duration) * 100}
        />
      ))}
    </div>
  );
}
```

### 5.3 PlaybackControls

Transport controls with speed and volume.

```typescript
interface PlaybackControlsProps {
  readonly state: PlaybackState;
  readonly onPlay: () => void;
  readonly onPause: () => void;
  readonly onSeekRelative: (delta: number) => void;
  readonly onPrevSegment?: () => void;
  readonly onNextSegment?: () => void;
  readonly onRateChange: (rate: number) => void;
  readonly onVolumeChange: (volume: number) => void;
  readonly onMuteToggle: () => void;
  readonly availableRates?: readonly number[];
}

const DEFAULT_RATES = [0.5, 0.75, 1.0, 1.25, 1.5, 1.75, 2.0] as const;

function PlaybackControls({ state, ...handlers }: PlaybackControlsProps) {
  const rates = handlers.availableRates ?? DEFAULT_RATES;
  
  return (
    <div className="flex items-center justify-between gap-4">
      {/* Transport */}
      <div className="flex items-center gap-2">
        <Button
          variant="ghost"
          size="icon"
          onClick={handlers.onPrevSegment}
          disabled={!handlers.onPrevSegment}
          aria-label="Previous segment"
        >
          <SkipBack className="h-4 w-4" />
        </Button>
        
        <Button
          variant="ghost"
          size="icon"
          onClick={() => handlers.onSeekRelative(-10)}
          aria-label="Rewind 10 seconds"
        >
          <Rewind className="h-4 w-4" />
        </Button>
        
        <Button
          variant="default"
          size="icon"
          onClick={state.status === 'Playing' ? handlers.onPause : handlers.onPlay}
          aria-label={state.status === 'Playing' ? 'Pause' : 'Play'}
        >
          {state.status === 'Playing' 
            ? <Pause className="h-5 w-5" />
            : <Play className="h-5 w-5" />
          }
        </Button>
        
        <Button
          variant="ghost"
          size="icon"
          onClick={() => handlers.onSeekRelative(10)}
          aria-label="Forward 10 seconds"
        >
          <FastForward className="h-4 w-4" />
        </Button>
        
        <Button
          variant="ghost"
          size="icon"
          onClick={handlers.onNextSegment}
          disabled={!handlers.onNextSegment}
          aria-label="Next segment"
        >
          <SkipForward className="h-4 w-4" />
        </Button>
      </div>
      
      {/* Speed control */}
      <Select
        value={String(state.playbackRate)}
        onValueChange={(v) => handlers.onRateChange(parseFloat(v))}
      >
        <SelectTrigger className="w-20">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {rates.map(rate => (
            <SelectItem key={rate} value={String(rate)}>
              {rate}x
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      
      {/* Volume control */}
      <div className="flex items-center gap-2">
        <Button
          variant="ghost"
          size="icon"
          onClick={handlers.onMuteToggle}
          aria-label={state.isMuted ? 'Unmute' : 'Mute'}
        >
          {state.isMuted || state.volume === 0 
            ? <VolumeX className="h-4 w-4" />
            : state.volume < 0.5
            ? <Volume1 className="h-4 w-4" />
            : <Volume2 className="h-4 w-4" />
          }
        </Button>
        
        <Slider
          value={[state.isMuted ? 0 : state.volume]}
          max={1}
          step={0.05}
          onValueChange={([v]) => handlers.onVolumeChange(v)}
          className="w-24"
          aria-label="Volume"
        />
      </div>
    </div>
  );
}
```

### 5.4 TimeDisplay

Current time and duration with segment info.

```typescript
interface TimeDisplayProps {
  readonly currentTime: number;
  readonly duration: number;
  readonly currentSegment?: AudioSegment | null;
  readonly loopRange?: TimeRange | null;
  readonly format?: '12h' | '24h' | 'compact';
}

function TimeDisplay({ currentTime, duration, currentSegment, loopRange, format = 'compact' }: TimeDisplayProps) {
  const formatTime = useCallback((seconds: number): string => {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hrs > 0) {
      return `${hrs}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }, []);

  return (
    <div className="flex items-center gap-4 text-sm font-mono">
      <span className="text-foreground">
        {formatTime(currentTime)}
      </span>
      <span className="text-muted-foreground">/</span>
      <span className="text-muted-foreground">
        {formatTime(duration)}
      </span>
      
      {currentSegment && (
        <Badge variant="outline" style={{ borderColor: currentSegment.color }}>
          {currentSegment.label}
        </Badge>
      )}
      
      {loopRange && (
        <Badge variant="secondary">
          Loop: {formatTime(loopRange.start)} → {formatTime(loopRange.end)}
        </Badge>
      )}
    </div>
  );
}
```

### 5.5 LoopRangeSelector

A/B loop selection overlay.

```typescript
interface LoopRangeSelectorProps {
  readonly duration: number;
  readonly range: TimeRange | null;
  readonly isActive: boolean;
  readonly onRangeChange: (range: TimeRange | null) => void;
  readonly onToggle: () => void;
}

function LoopRangeSelector({ duration, range, isActive, onRangeChange, onToggle }: LoopRangeSelectorProps) {
  const [isSettingA, setIsSettingA] = useState(true);
  
  const handleSetPoint = (time: number) => {
    if (isSettingA) {
      onRangeChange({ start: time, end: range?.end ?? duration });
      setIsSettingA(false);
    } else {
      onRangeChange({ start: range?.start ?? 0, end: time });
      setIsSettingA(true);
    }
  };

  return (
    <div className="flex items-center gap-2">
      <Button
        variant={isActive ? 'default' : 'outline'}
        size="sm"
        onClick={onToggle}
      >
        <Repeat className="h-4 w-4 mr-1" />
        A ↔ B
      </Button>
      
      {isActive && (
        <>
          <Button
            variant={isSettingA ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setIsSettingA(true)}
          >
            Set A
          </Button>
          <Button
            variant={!isSettingA ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setIsSettingA(false)}
          >
            Set B
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onRangeChange(null)}
          >
            Clear
          </Button>
        </>
      )}
    </div>
  );
}
```

---

## 6. Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `Space` | Toggle play/pause |
| `←` / `→` | Seek ±5 seconds |
| `Shift + ←` / `→` | Seek ±30 seconds |
| `↑` / `↓` | Volume ±10% |
| `M` | Toggle mute |
| `[` / `]` | Decrease/increase playback speed |
| `Home` | Jump to start |
| `End` | Jump to end |
| `L` | Toggle loop mode |
| `A` | Set loop start point |
| `B` | Set loop end point |
| `Escape` | Clear loop range |

### 6.1 useAudioKeyboard Hook

```typescript
interface UseAudioKeyboardOptions {
  readonly enabled?: boolean;
  readonly onPlayPause: () => void;
  readonly onSeek: (delta: number) => void;
  readonly onVolumeChange: (delta: number) => void;
  readonly onMuteToggle: () => void;
  readonly onRateChange: (delta: number) => void;
  readonly onLoopToggle: () => void;
  readonly onSetLoopPoint: (point: 'A' | 'B') => void;
  readonly onClearLoop: () => void;
  readonly onJumpToStart: () => void;
  readonly onJumpToEnd: () => void;
}

function useAudioKeyboard(options: UseAudioKeyboardOptions): void {
  const { enabled = true, ...handlers } = options;
  
  useEffect(() => {
    if (!enabled) return;
    
    const handleKeyDown = (e: KeyboardEvent) => {
      // Ignore when focused on inputs
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
        return;
      }
      
      switch (e.key) {
        case ' ':
          e.preventDefault();
          handlers.onPlayPause();
          break;
        case 'ArrowLeft':
          e.preventDefault();
          handlers.onSeek(e.shiftKey ? -30 : -5);
          break;
        case 'ArrowRight':
          e.preventDefault();
          handlers.onSeek(e.shiftKey ? 30 : 5);
          break;
        case 'ArrowUp':
          e.preventDefault();
          handlers.onVolumeChange(0.1);
          break;
        case 'ArrowDown':
          e.preventDefault();
          handlers.onVolumeChange(-0.1);
          break;
        case 'm':
        case 'M':
          handlers.onMuteToggle();
          break;
        case '[':
          handlers.onRateChange(-0.25);
          break;
        case ']':
          handlers.onRateChange(0.25);
          break;
        case 'l':
        case 'L':
          handlers.onLoopToggle();
          break;
        case 'a':
        case 'A':
          handlers.onSetLoopPoint('A');
          break;
        case 'b':
        case 'B':
          handlers.onSetLoopPoint('B');
          break;
        case 'Home':
          e.preventDefault();
          handlers.onJumpToStart();
          break;
        case 'End':
          e.preventDefault();
          handlers.onJumpToEnd();
          break;
        case 'Escape':
          handlers.onClearLoop();
          break;
      }
    };
    
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [enabled, handlers]);
}
```

---

## 7. Synchronization Protocol

### 7.1 Playback → Transcript Flow

```
AudioPlayer                              TranscriptionDisplay
    │                                            │
    │ ─── onPlayheadUpdate({                     │
    │       currentTime: 45.2,                   │
    │       currentWordId: 'word-123',           │
    │       currentSegmentId: 'seg-5'            │
    │     }) ────────────────────────────────────┤
    │                                            │
    │                                    Highlight word-123
    │                                    Scroll if needed
    │                                            │
```

### 7.2 Transcript → Playback Flow

```
TranscriptionDisplay                     AudioPlayer
    │                                            │
    │ User clicks word at 45.2s                  │
    │                                            │
    │ ─── onWordClick(wordId, 45.2) ─────────────┤
    │                                            │
    │                                    seek(45.2)
    │                                    play()
    │                                            │
```

### 7.3 Event Types

```typescript
// Published by AudioPlayer
interface AudioPlayerEvents {
  'playhead:update': PlayheadUpdate;
  'segment:enter': { segmentId: string; timestamp: number };
  'segment:exit': { segmentId: string; timestamp: number };
  'marker:reach': { markerId: string; marker: AudioMarker };
  'loop:start': { range: TimeRange };
  'loop:end': { range: TimeRange };
}

// Published by TranscriptionDisplay
interface TranscriptionEvents {
  'word:click': { wordId: string; timestamp: number };
  'segment:click': { segmentId: string; startTime: number };
  'selection:change': { startWordId: string; endWordId: string };
}
```

---

## 8. Accessibility Requirements

### 8.1 ARIA Roles & Labels

```tsx
<div role="application" aria-label="Audio player">
  <audio
    ref={audioRef}
    aria-label={`Audio: ${source?.name ?? 'Untitled'}`}
  />
  
  <div role="slider"
    aria-label="Playback position"
    aria-valuemin={0}
    aria-valuemax={duration}
    aria-valuenow={currentTime}
    aria-valuetext={formatTime(currentTime)}
    tabIndex={0}
  >
    {/* WaveformTimeline */}
  </div>
  
  <div role="slider"
    aria-label="Volume"
    aria-valuemin={0}
    aria-valuemax={100}
    aria-valuenow={volume * 100}
    aria-valuetext={`${Math.round(volume * 100)}%`}
  >
    {/* Volume slider */}
  </div>
</div>
```

### 8.2 Screen Reader Announcements

```typescript
function usePlaybackAnnouncements(state: PlaybackState) {
  const prevStatus = useRef(state.status);
  
  useEffect(() => {
    if (state.status !== prevStatus.current) {
      const announcement = getAnnouncement(prevStatus.current, state.status);
      if (announcement) {
        announce(announcement);
      }
      prevStatus.current = state.status;
    }
  }, [state.status]);
}

function getAnnouncement(prev: PlaybackStatus, next: PlaybackStatus): string | null {
  if (next === 'Playing') return 'Playback started';
  if (next === 'Paused') return 'Playback paused';
  if (next === 'Error') return 'Playback error occurred';
  return null;
}
```

---

## 9. Performance Requirements

| Metric | Threshold |
|--------|-----------|
| Playhead update latency | < 16ms (60fps) |
| Waveform render time | < 50ms |
| Seek response time | < 100ms |
| Word highlight sync drift | < 50ms |
| Peak extraction (1min audio) | < 2s |
| Memory for 1hr waveform | < 10MB |

### 9.1 Optimization Strategies

1. **RAF-based playhead**: Use `requestAnimationFrame` for smooth updates
2. **Canvas offscreen**: Pre-render waveform segments off-screen
3. **Binary search**: O(log n) word lookup for timestamp sync
4. **Debounced seeking**: Batch rapid scrub events
5. **IndexedDB caching**: Persist computed waveform peaks

---

## 10. Error Handling

### 10.1 Error Types

```typescript
type AudioPlaybackError = 
  | { code: 'LOAD_FAILED'; message: string; url: string }
  | { code: 'DECODE_ERROR'; message: string }
  | { code: 'NETWORK_ERROR'; message: string }
  | { code: 'PERMISSION_DENIED'; message: string }
  | { code: 'FORMAT_UNSUPPORTED'; message: string; mimeType: string };
```

### 10.2 Error Recovery

```typescript
function useAudioErrorRecovery(error: AudioPlaybackError | null, retry: () => void) {
  const [retryCount, setRetryCount] = useState(0);
  const MAX_RETRIES = 3;
  
  useEffect(() => {
    if (!error) {
      setRetryCount(0);
      return;
    }
    
    // Auto-retry for network errors
    if (error.code === 'NETWORK_ERROR' && retryCount < MAX_RETRIES) {
      const timeout = setTimeout(() => {
        setRetryCount(c => c + 1);
        retry();
      }, Math.pow(2, retryCount) * 1000);
      
      return () => clearTimeout(timeout);
    }
  }, [error, retryCount, retry]);
}
```

---

## 11. Integration Example

```tsx
function VoiceReviewPage() {
  const [source] = useState<AudioSource>(/* loaded audio */);
  const [transcriptSegments] = useState<TranscriptSegment[]>(/* loaded transcript */);
  const [currentWordId, setCurrentWordId] = useState<string | null>(null);
  
  const handlePlayheadUpdate = useCallback((update: PlayheadUpdate) => {
    setCurrentWordId(update.currentWordId);
  }, []);
  
  const handleWordClick = useCallback((wordId: string, timestamp: number) => {
    playerRef.current?.seek(timestamp);
    playerRef.current?.play();
  }, []);

  return (
    <div className="grid grid-rows-[auto_1fr] h-screen">
      {/* Fixed audio player */}
      <AudioPlayer
        source={source}
        syncConfig={{
          transcriptId: 'transcript-001',
          syncMode: 'bidirectional',
          highlightLead: 100,
          scrollBehavior: 'smooth',
          wordSnapThreshold: 50,
        }}
        transcriptSegments={transcriptSegments}
        onPlayheadUpdate={handlePlayheadUpdate}
      />
      
      {/* Scrollable transcript */}
      <TranscriptionDisplay
        segments={transcriptSegments}
        currentWordId={currentWordId}
        onWordClick={handleWordClick}
      />
    </div>
  );
}
```

---

## 12. File Structure

```
src/
├── components/
│   └── audio-player/
│       ├── AudioPlayer.tsx           # Container component
│       ├── WaveformTimeline.tsx      # Canvas waveform
│       ├── PlaybackControls.tsx      # Transport buttons
│       ├── TimeDisplay.tsx           # Time counter
│       ├── LoopRangeSelector.tsx     # A/B loop UI
│       ├── MarkerIndicator.tsx       # Timeline markers
│       └── index.ts                  # Barrel export
├── hooks/
│   ├── useAudioPlayback.ts           # Core playback state
│   ├── useTimestampSync.ts           # Transcript sync
│   ├── useWaveformData.ts            # Peak extraction
│   └── useAudioKeyboard.ts           # Keyboard shortcuts
└── lib/
    └── audio/
        ├── types.ts                  # Shared types
        └── utils.ts                  # Time formatting, etc.
```

---

## Appendix A: Cross-References

- **Voice Recorder**: [01-voice-recorder.md](./01-voice-recorder.md)
- **Transcription Display**: [02-transcription-display.md](./02-transcription-display.md)
- **Voice-CLI OpenAPI**: [../14-microservices/16-voice-cli-openapi.md](../14-microservices/16-voice-cli-openapi.md)
- **React Guidelines**: [/memories/constraints/react-guidelines](/memories/constraints/react-guidelines)
