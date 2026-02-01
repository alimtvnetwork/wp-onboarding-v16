# 28 - Consistency Loop Dashboard

## Overview

The Consistency Loop Dashboard provides real-time visualization of the iterative consistency checking process, enabling users to monitor progress toward the 99% health score target, review iteration history, and analyze blockers with actionable fix suggestions.

---

## Component Architecture

### Primary Components

```
ConsistencyLoopDashboard/
├── LoopProgressPanel/
│   ├── ScoreGauge.tsx           # Animated circular progress gauge
│   ├── IterationCounter.tsx     # Current iteration / max iterations
│   ├── TargetIndicator.tsx      # Visual target score marker
│   └── LoopStatusBadge.tsx      # running | completed | stalled | stopped
├── IterationTimeline/
│   ├── TimelineContainer.tsx    # Scrollable timeline view
│   ├── IterationCard.tsx        # Individual iteration snapshot
│   ├── ScoreDeltaChip.tsx       # +3.2% improvement indicator
│   └── IterationDiff.tsx        # Expandable diff viewer
├── BlockerAnalysis/
│   ├── BlockerList.tsx          # Grouped blocker findings
│   ├── BlockerCard.tsx          # Individual blocker with severity
│   ├── FixSuggestion.tsx        # AI-generated fix with preview
│   ├── ApplyFixButton.tsx       # One-click fix application
│   └── BlockerTrend.tsx         # Cross-iteration blocker tracking
├── LoopControls/
│   ├── StartLoopButton.tsx      # Initiate new loop
│   ├── StopLoopButton.tsx       # Manual intervention
│   ├── LoopConfigDialog.tsx     # Target score, max iterations
│   └── AutoApplyToggle.tsx      # Enable automatic fix application
└── LoopHistory/
    ├── HistoryTable.tsx         # Past loop executions
    ├── LoopComparisonView.tsx   # Compare two loop runs
    └── ExportReportButton.tsx   # Download detailed report
```

---

## Real-Time Progress Visualization

### Score Gauge Component

```typescript
interface ScoreGaugeProps {
  currentScore: number;        // 0-100
  targetScore: number;         // Default: 99
  previousScore: number;       // For delta calculation
  isAnimating: boolean;        // During score updates
}

// Visual specifications
const gaugeConfig = {
  size: 200,                   // px diameter
  strokeWidth: 12,
  colors: {
    track: 'hsl(var(--muted))',
    progress: {
      low: 'hsl(var(--destructive))',      // 0-60
      medium: 'hsl(var(--warning))',        // 60-85
      high: 'hsl(var(--success))',          // 85-99
      perfect: 'hsl(var(--primary))',       // 99-100
    },
    target: 'hsl(var(--accent))',
  },
  animation: {
    duration: 800,             // ms
    easing: 'easeOutCubic',
  },
};
```

### SSE Connection for Live Updates

```typescript
interface LoopStreamEvent {
  type: 'iteration_start' | 'iteration_complete' | 'finding_detected' | 
        'fix_applied' | 'loop_complete' | 'loop_stalled' | 'error';
  payload: IterationPayload | FindingPayload | FixPayload | ErrorPayload;
  timestamp: string;
}

// Hook implementation
function useLoopStream(loopId: string) {
  const [state, dispatch] = useReducer(loopStreamReducer, initialState);
  
  useEffect(() => {
    const eventSource = new EventSource(
      `/api/v1/projects/${projectId}/consistency/loop/${loopId}/stream`
    );
    
    eventSource.onmessage = (event) => {
      const data: LoopStreamEvent = JSON.parse(event.data);
      dispatch({ type: data.type, payload: data.payload });
    };
    
    eventSource.onerror = () => {
      dispatch({ type: 'connection_error' });
      // Exponential backoff reconnection
    };
    
    return () => eventSource.close();
  }, [loopId, projectId]);
  
  return state;
}
```

### Progress States

| State | Visual | Behavior |
|-------|--------|----------|
| `idle` | Empty gauge, muted colors | Start button enabled |
| `running` | Animated pulse on gauge | Real-time score updates |
| `stalled` | Warning border, pulse stops | Show stall reason, suggest actions |
| `completed` | Success checkmark overlay | Show final report link |
| `stopped` | Neutral state with stop icon | Show partial results |
| `error` | Error border, retry option | Display error message |

---

## Iteration Timeline

### Timeline Layout

```
┌─────────────────────────────────────────────────────────────┐
│ Iteration Timeline                              [Collapse ▼]│
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ●────●────●────●────◐────○────○────○────○────○             │
│  1    2    3    4    5    6    7    8    9   10             │
│  78%  82%  85%  89%  92%  ...                               │
│                 ↑                                           │
│            Current                                          │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Iteration 4                              +4.0% ▲        │ │
│ │ Score: 89/100 │ Duration: 2.3s │ Fixes: 3              │ │
│ │                                                         │ │
│ │ Findings Resolved:                                      │ │
│ │  ✓ Missing cross-reference in 02-database-schema.md    │ │
│ │  ✓ Duplicate definition: ArtifactType enum             │ │
│ │  ✓ Naming violation: PathManager → path-manager        │ │
│ │                                                         │ │
│ │ [View Diff] [View Full Report]                         │ │
│ └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Iteration Card Component

```typescript
interface IterationCardProps {
  iteration: {
    number: number;
    score: number;
    previousScore: number;
    duration: number;           // milliseconds
    findingsCount: number;
    fixesApplied: number;
    fixesFailed: number;
    timestamp: string;
  };
  isExpanded: boolean;
  isCurrent: boolean;
  onExpand: () => void;
  onViewDiff: () => void;
}

// Score delta chip variants
type DeltaVariant = 'positive' | 'negative' | 'neutral' | 'stalled';

function getScoreDelta(current: number, previous: number): DeltaVariant {
  const delta = current - previous;
  if (delta > 0.5) return 'positive';
  if (delta < -0.5) return 'negative';
  if (delta === 0) return 'stalled';
  return 'neutral';
}
```

### Diff Viewer

```typescript
interface IterationDiffProps {
  iterationId: string;
  files: Array<{
    path: string;
    changeType: 'added' | 'modified' | 'deleted';
    additions: number;
    deletions: number;
  }>;
  showInline: boolean;         // Inline vs side-by-side
}

// Uses Monaco diff editor for detailed view
// Collapsible per-file sections
// Syntax highlighting for markdown
```

---

## Blocker Analysis Panel

### Blocker Grouping Strategy

```typescript
type BlockerCategory = 
  | 'cross_reference'          // Broken links, missing refs
  | 'naming_convention'        // Kebab-case, prefix violations
  | 'missing_section'          // Required headers absent
  | 'duplicate_definition'     // Same term defined multiple times
  | 'schema_mismatch'          // DB schema vs code discrepancy
  | 'orphaned_artifact'        // Files not linked anywhere
  | 'circular_dependency';     // Spec A refs B refs A

interface BlockerGroup {
  category: BlockerCategory;
  severity: 'critical' | 'high' | 'medium' | 'low';
  count: number;
  scoreImpact: number;         // Estimated points if resolved
  blockers: Blocker[];
}
```

### Blocker Card Layout

```
┌─────────────────────────────────────────────────────────────┐
│ 🔴 CRITICAL │ Missing Cross-Reference                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ File: spec/01-backend/04-file-operations.md                 │
│ Line: 127                                                   │
│                                                             │
│ Issue: Reference to `PathManager` not found in spec.        │
│        Expected link: `17-path-manager.md#PathManager`      │
│                                                             │
│ Impact: -2.5 points │ Blocking iterations: 3                │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 💡 Suggested Fix                                        │ │
│ │                                                         │ │
│ │ Add cross-reference section:                            │ │
│ │ ```markdown                                             │ │
│ │ ## Cross-References                                     │ │
│ │ - [PathManager](./17-path-manager.md#PathManager)       │ │
│ │ ```                                                     │ │
│ │                                                         │ │
│ │ [Preview] [Apply Fix] [Dismiss] [Mark Won't Fix]        │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ History: First detected iteration 2 │ Attempts: 2          │
└─────────────────────────────────────────────────────────────┘
```

### Fix Suggestion Component

```typescript
interface FixSuggestionProps {
  blocker: Blocker;
  fix: {
    id: string;
    description: string;
    confidence: number;        // 0-1, AI confidence score
    preview: string;           // Markdown diff preview
    autoApplyable: boolean;    // Safe for automatic application
    requiresReview: boolean;   // Needs human verification
  };
  onApply: () => Promise<void>;
  onPreview: () => void;
  onDismiss: () => void;
  onMarkWontFix: (reason: string) => void;
}

// Confidence indicator
const confidenceColors = {
  high: 'hsl(var(--success))',      // > 0.85
  medium: 'hsl(var(--warning))',    // 0.6-0.85
  low: 'hsl(var(--destructive))',   // < 0.6
};
```

### Blocker Trend Chart

```typescript
// Shows blocker count across iterations
interface BlockerTrendProps {
  data: Array<{
    iteration: number;
    critical: number;
    high: number;
    medium: number;
    low: number;
  }>;
  height: number;
}

// Stacked area chart using Recharts
// Hoverable data points with tooltips
// Click to jump to specific iteration
```

---

## Loop Controls

### Configuration Dialog

```typescript
interface LoopConfig {
  targetScore: number;         // Default: 99, Range: 80-100
  maxIterations: number;       // Default: 10, Range: 1-50
  stallThreshold: number;      // Default: 3, consecutive no-improvement
  autoApplyFixes: boolean;     // Default: false
  fixConfidenceThreshold: number; // Default: 0.85, for auto-apply
  includePaths: string[];      // Glob patterns to include
  excludePaths: string[];      // Glob patterns to exclude
}

// Form validation
const loopConfigSchema = z.object({
  targetScore: z.number().min(80).max(100),
  maxIterations: z.number().min(1).max(50),
  stallThreshold: z.number().min(1).max(10),
  autoApplyFixes: z.boolean(),
  fixConfidenceThreshold: z.number().min(0.5).max(1),
});
```

### Control Button States

| Button | State | Enabled When | Action |
|--------|-------|--------------|--------|
| Start Loop | Primary | No active loop | Opens config dialog |
| Stop Loop | Destructive | Loop running | Confirms then stops |
| Pause Loop | Secondary | Loop running | Pauses at next iteration |
| Resume Loop | Primary | Loop paused | Continues from pause |
| Export Report | Outline | Loop completed/stopped | Downloads PDF/MD |

---

## Loop History

### History Table Columns

| Column | Type | Sortable | Filterable |
|--------|------|----------|------------|
| Started | DateTime | ✓ | Date range |
| Duration | Duration | ✓ | - |
| Initial Score | Number | ✓ | Range |
| Final Score | Number | ✓ | Range |
| Target Reached | Boolean | - | ✓ |
| Iterations | Number | ✓ | Range |
| Stop Reason | Enum | - | ✓ |
| Actions | Buttons | - | - |

### Comparison View

```
┌─────────────────────────────────────────────────────────────┐
│ Loop Comparison                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Loop A (Jan 15)          │  Loop B (Jan 28)               │
│  ─────────────────        │  ─────────────────             │
│  Score: 78 → 94           │  Score: 92 → 99                │
│  Iterations: 8            │  Iterations: 5                 │
│  Duration: 45s            │  Duration: 23s                 │
│                           │                                 │
│  Blockers Resolved:       │  Blockers Resolved:            │
│  • 12 cross-references    │  • 4 cross-references          │
│  • 5 naming violations    │  • 2 schema mismatches         │
│  • 3 missing sections     │  • 1 duplicate definition      │
│                           │                                 │
│  [View Full Diff]                                          │
└─────────────────────────────────────────────────────────────┘
```

---

## Keyboard Shortcuts

| Shortcut | Action | Context |
|----------|--------|---------|
| `Ctrl+Shift+C` | Open consistency dashboard | Global |
| `Enter` | Start/Resume loop | Dashboard focused |
| `Escape` | Stop loop (with confirm) | Loop running |
| `←` / `→` | Navigate iterations | Timeline focused |
| `Space` | Expand/collapse iteration | Iteration selected |
| `A` | Apply selected fix | Fix suggestion focused |
| `D` | Dismiss blocker | Blocker card focused |
| `E` | Export report | Dashboard focused |

---

## Accessibility Requirements

### ARIA Attributes

```typescript
// Score gauge
<div
  role="progressbar"
  aria-valuenow={currentScore}
  aria-valuemin={0}
  aria-valuemax={100}
  aria-label={`Health score: ${currentScore}%. Target: ${targetScore}%`}
/>

// Live region for updates
<div
  aria-live="polite"
  aria-atomic="true"
  className="sr-only"
>
  {`Iteration ${iteration} complete. Score improved to ${score}%`}
</div>

// Blocker list
<ul role="list" aria-label="Consistency blockers">
  <li role="listitem" aria-describedby={`blocker-${id}-description`}>
```

### Focus Management

- Focus moves to score gauge on loop start
- Focus moves to first new blocker when detected
- Focus trap in fix preview modal
- Return focus to trigger element on modal close

---

## Loading & Error States

### Skeleton Loaders

```typescript
function DashboardSkeleton() {
  return (
    <div className="space-y-6">
      {/* Score gauge skeleton */}
      <Skeleton className="h-52 w-52 rounded-full mx-auto" />
      
      {/* Timeline skeleton */}
      <div className="flex gap-2">
        {Array(10).fill(0).map((_, i) => (
          <Skeleton key={i} className="h-4 w-4 rounded-full" />
        ))}
      </div>
      
      {/* Blocker cards skeleton */}
      <div className="space-y-4">
        {Array(3).fill(0).map((_, i) => (
          <Skeleton key={i} className="h-32 w-full rounded-lg" />
        ))}
      </div>
    </div>
  );
}
```

### Error States

| Error | Display | Recovery Action |
|-------|---------|-----------------|
| SSE connection lost | Toast + reconnecting indicator | Auto-retry with backoff |
| Loop start failed | Inline error in dialog | Show reason, retry button |
| Fix apply failed | Error badge on blocker card | Show error, manual fix link |
| History load failed | Empty state with error | Retry button |

---

## State Management

### Dashboard State Structure

```typescript
interface ConsistencyLoopState {
  // Active loop
  activeLoop: {
    id: string | null;
    status: LoopStatus;
    config: LoopConfig;
    currentIteration: number;
    currentScore: number;
    initialScore: number;
    iterations: IterationSnapshot[];
  };
  
  // Blockers
  blockers: {
    items: Blocker[];
    groupedByCategory: Record<BlockerCategory, Blocker[]>;
    selectedId: string | null;
  };
  
  // UI state
  ui: {
    expandedIterations: Set<number>;
    timelineView: 'compact' | 'expanded';
    blockerSort: 'severity' | 'impact' | 'age';
    showResolvedBlockers: boolean;
  };
  
  // History
  history: {
    items: LoopSummary[];
    isLoading: boolean;
    selectedForComparison: [string, string] | null;
  };
}
```

### React Query Keys

```typescript
const consistencyKeys = {
  all: ['consistency'] as const,
  loops: () => [...consistencyKeys.all, 'loops'] as const,
  loop: (id: string) => [...consistencyKeys.loops(), id] as const,
  loopIterations: (id: string) => [...consistencyKeys.loop(id), 'iterations'] as const,
  blockers: (loopId: string) => [...consistencyKeys.loop(loopId), 'blockers'] as const,
  history: (projectId: string) => [...consistencyKeys.all, 'history', projectId] as const,
};
```

---

## Performance Considerations

### Optimization Strategies

1. **Virtual scrolling** for iteration timeline with 50+ iterations
2. **Debounced SSE updates** - batch rapid events into 100ms windows
3. **Lazy load** blocker fix previews on demand
4. **Memoized** score delta calculations
5. **Skeleton loading** for perceived performance

### Bundle Splitting

```typescript
// Lazy load heavy components
const DiffViewer = lazy(() => import('./IterationDiff'));
const TrendChart = lazy(() => import('./BlockerTrend'));
const ComparisonView = lazy(() => import('./LoopComparisonView'));
```

---

## Cross-References

- **Consistency Checker**: [01-consistency-checker.md](./01-consistency-checker.md)
- **Implementation**: [02-consistency-checker-implementation.md](./02-consistency-checker-implementation.md)
- **Database Schema**: [01-schema.md](../../07-database-design/01-schema.md)
- **Realtime**: [00-overview.md](../18-realtime/00-overview.md)
- **Error UI**: [00-overview.md](../13-error-ui/00-overview.md)

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-28 | Initial specification |
