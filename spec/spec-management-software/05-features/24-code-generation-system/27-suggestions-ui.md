# Suggestions UI

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Frontend specification for displaying and managing AI-generated suggestions. Includes inline suggestions within chat messages, a dedicated suggestions panel, and suggestion action workflows.

**Cross-References:**
- [Suggestions System](./16-suggestions-system.md) - Backend logic
- [AI Chat Interface](./20-ai-chat-interface.md) - Chat integration
- [Project Editor UI](./15-project-editor-ui.md) - Main interface

---

## UI Locations

### 1. Inline Chat Suggestions

Suggestions appear directly within AI messages after task completion.

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  🤖 AI                                                              10:30 AM   │
│                                                                                  │
│  I've completed the authentication module implementation. Here's what was done: │
│                                                                                  │
│  ✅ Created JWT token generation and validation                                 │
│  ✅ Added login and logout endpoints                                            │
│  ✅ Implemented password hashing with bcrypt                                    │
│                                                                                  │
│  ─────────────────────────────────────────────────────────────────────────────  │
│                                                                                  │
│  💡 Suggestions (3)                                                             │
│                                                                                  │
│  ┌───────────────────────────────────────────────────────────────────────────┐ │
│  │ ⚡ High                                                                    │ │
│  │                                                                            │ │
│  │ Add Rate Limiting to Auth Endpoints                                       │ │
│  │                                                                            │ │
│  │ Protect against brute force attacks by limiting login attempts.           │ │
│  │ Est. 1-2 hours                                                             │ │
│  │                                                            [▶] [⏸] [×]   │ │
│  └───────────────────────────────────────────────────────────────────────────┘ │
│                                                                                  │
│  ┌───────────────────────────────────────────────────────────────────────────┐ │
│  │ 📋 Medium                                                                  │ │
│  │                                                                            │ │
│  │ Add Session Management                                                     │ │
│  │                                                                            │ │
│  │ Track active sessions and allow users to view/revoke them.                │ │
│  │ Est. 2-4 hours                                                             │ │
│  │                                                            [▶] [⏸] [×]   │ │
│  └───────────────────────────────────────────────────────────────────────────┘ │
│                                                                                  │
│  + 1 more suggestion                                               [View All]   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 2. Suggestions Panel (Sidebar)

A collapsible sidebar showing all pending suggestions for the project.

```
┌─────────────────────────┐
│  📋 Suggestions (12)    │
│  ────────────────────── │
│                         │
│  FILTERS                │
│  Priority: [All ▼]      │
│  Category: [All ▼]      │
│  ────────────────────── │
│                         │
│  HIGH PRIORITY (3)      │
│                         │
│  ┌───────────────────┐  │
│  │ ⚡ Rate Limiting  │  │
│  │ Auth • 1-2h       │  │
│  │         [▶] [×]   │  │
│  └───────────────────┘  │
│                         │
│  ┌───────────────────┐  │
│  │ ⚡ Input Valid.   │  │
│  │ API • 2-3h        │  │
│  │         [▶] [×]   │  │
│  └───────────────────┘  │
│                         │
│  ┌───────────────────┐  │
│  │ ⚡ Error Logging  │  │
│  │ System • 1h       │  │
│  │         [▶] [×]   │  │
│  └───────────────────┘  │
│                         │
│  MEDIUM PRIORITY (6)    │
│  ...                    │
│                         │
│  LOW PRIORITY (3)       │
│  ...                    │
│                         │
│  ────────────────────── │
│  [View Resolved (24)]   │
│                         │
└─────────────────────────┘
```

### 3. Full Suggestions Page

Dedicated page for comprehensive suggestion management.

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  Suggestions                                        [Refresh] [Export] [Filter] │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌──────────────────────────────────────────────────────────────────────────┐   │
│  │  [Pending (12)]  [In Progress (2)]  [Resolved (24)]  [Rejected (5)]      │   │
│  └──────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│  Search: [________________________]  Priority: [All ▼]  Category: [All ▼]       │
│                                                                                  │
│  ┌──────────────────────────────────────────────────────────────────────────┐   │
│  │                                                                           │   │
│  │  ⚡ HIGH PRIORITY                                                         │   │
│  │                                                                           │   │
│  │  ┌─────────────────────────────────────────────────────────────────────┐ │   │
│  │  │ Add Rate Limiting to Auth Endpoints                                  │ │   │
│  │  │                                                                      │ │   │
│  │  │ Protect against brute force attacks by limiting login attempts to   │ │   │
│  │  │ prevent credential stuffing and DoS attacks on authentication.      │ │   │
│  │  │                                                                      │ │   │
│  │  │ Source: Implement User Authentication (task_abc123)                  │ │   │
│  │  │ Created: 2h ago • Est: 1-2 hours • Tags: security, api              │ │   │
│  │  │                                                                      │ │   │
│  │  │ Affected files:                                                      │ │   │
│  │  │ • BE/internal/api/auth_handler.go                                    │ │   │
│  │  │ • BE/internal/middleware/rate_limiter.go (new)                       │ │   │
│  │  │                                                                      │ │   │
│  │  │                          [Implement] [Defer] [Reject] [View Source] │ │   │
│  │  └─────────────────────────────────────────────────────────────────────┘ │   │
│  │                                                                           │   │
│  │  ...more suggestions                                                      │   │
│  │                                                                           │   │
│  └──────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│  Showing 1-10 of 12                                               [Load More]   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Component Hierarchy

```
Suggestions/
├── SuggestionInlineList.tsx        # Inline suggestions in chat
├── SuggestionSidebar.tsx           # Collapsible sidebar panel
├── SuggestionPage.tsx              # Full management page
├── components/
│   ├── SuggestionCard.tsx          # Individual suggestion card
│   ├── SuggestionCardCompact.tsx   # Compact version for sidebar
│   ├── SuggestionDetail.tsx        # Expanded detail view
│   ├── SuggestionFilters.tsx       # Filter controls
│   ├── SuggestionSearch.tsx        # Search input
│   ├── SuggestionTabs.tsx          # Status tab navigation
│   ├── SuggestionActions.tsx       # Action buttons
│   ├── PriorityBadge.tsx           # Priority indicator
│   └── CategoryTag.tsx             # Category tag
│
├── dialogs/
│   ├── ImplementDialog.tsx         # Confirmation to implement
│   ├── RejectDialog.tsx            # Rejection reason dialog
│   ├── DeferDialog.tsx             # Defer with note
│   └── ViewSourceDialog.tsx        # Show originating task
│
└── hooks/
    ├── useSuggestions.ts           # Fetch and manage suggestions
    ├── useSuggestionFilters.ts     # Filter state
    └── useSuggestionActions.ts     # Action handlers
```

---

## TypeScript Interfaces

```typescript
interface Suggestion {
  id: string;
  title: string;
  summary: string;
  description?: string;
  priority: 'low' | 'medium' | 'high' | 'critical';
  status: 'pending' | 'in_progress' | 'completed' | 'deferred' | 'rejected';
  
  // Source reference
  sourceType: 'task' | 'chat' | 'validation' | 'build';
  sourceId: string;
  sourceTitle: string;
  
  // Affected files
  affectedFiles: string[];
  relatedFiles: string[];
  
  // Effort estimation
  estimatedHours?: number;
  complexity: 'low' | 'medium' | 'high';
  
  // Categorization
  tags: string[];
  category?: string;
  
  // Timestamps
  createdAt: Date;
  updatedAt: Date;
  resolvedAt?: Date;
  
  // Resolution
  resolutionNotes?: string;
}

interface SuggestionFilters {
  search: string;
  status: Suggestion['status'][];
  priority: Suggestion['priority'][];
  category: string[];
  tags: string[];
  dateRange?: {
    from: Date;
    to: Date;
  };
}

interface SuggestionStats {
  total: number;
  pending: number;
  inProgress: number;
  completed: number;
  deferred: number;
  rejected: number;
  byPriority: {
    critical: number;
    high: number;
    medium: number;
    low: number;
  };
}
```

---

## Suggestion Card Component

```typescript
interface SuggestionCardProps {
  suggestion: Suggestion;
  variant: 'compact' | 'default' | 'expanded';
  onImplement: (id: string) => void;
  onDefer: (id: string, note?: string) => void;
  onReject: (id: string, reason: string) => void;
  onViewSource: (sourceId: string) => void;
  showSource?: boolean;
  showAffectedFiles?: boolean;
}

const SuggestionCard: React.FC<SuggestionCardProps> = ({
  suggestion,
  variant,
  onImplement,
  onDefer,
  onReject,
  onViewSource,
  showSource = true,
  showAffectedFiles = false,
}) => {
  const priorityConfig = {
    critical: { icon: AlertOctagon, color: 'text-red-500', bg: 'bg-red-500/10' },
    high: { icon: Zap, color: 'text-orange-500', bg: 'bg-orange-500/10' },
    medium: { icon: AlertCircle, color: 'text-yellow-500', bg: 'bg-yellow-500/10' },
    low: { icon: Info, color: 'text-blue-500', bg: 'bg-blue-500/10' },
  };
  
  const config = priorityConfig[suggestion.priority];
  const PriorityIcon = config.icon;
  
  if (variant === 'compact') {
    return (
      <div className="p-3 border rounded-lg hover:bg-muted/50 transition-colors">
        <div className="flex items-start gap-2">
          <PriorityIcon className={cn("h-4 w-4 mt-0.5", config.color)} />
          <div className="flex-1 min-w-0">
            <p className="font-medium text-sm truncate">{suggestion.title}</p>
            <p className="text-xs text-muted-foreground">
              {suggestion.category} • {formatEstimate(suggestion.estimatedHours)}
            </p>
          </div>
          <div className="flex gap-1">
            <Button
              size="icon"
              variant="ghost"
              className="h-6 w-6"
              onClick={() => onImplement(suggestion.id)}
            >
              <Play className="h-3 w-3" />
            </Button>
            <Button
              size="icon"
              variant="ghost"
              className="h-6 w-6"
              onClick={() => onReject(suggestion.id, '')}
            >
              <X className="h-3 w-3" />
            </Button>
          </div>
        </div>
      </div>
    );
  }
  
  return (
    <Card className={cn("border-l-4", config.bg)}>
      <CardHeader className="pb-2">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-2">
            <Badge variant="outline" className={config.color}>
              <PriorityIcon className="h-3 w-3 mr-1" />
              {suggestion.priority}
            </Badge>
            {suggestion.tags.slice(0, 2).map(tag => (
              <Badge key={tag} variant="secondary" className="text-xs">
                {tag}
              </Badge>
            ))}
          </div>
          <span className="text-xs text-muted-foreground">
            {formatRelativeTime(suggestion.createdAt)}
          </span>
        </div>
        <CardTitle className="text-base">{suggestion.title}</CardTitle>
      </CardHeader>
      
      <CardContent className="pb-3">
        <p className="text-sm text-muted-foreground">{suggestion.summary}</p>
        
        {showSource && (
          <div className="mt-3 text-xs text-muted-foreground">
            Source: {suggestion.sourceTitle}
          </div>
        )}
        
        {showAffectedFiles && suggestion.affectedFiles.length > 0 && (
          <div className="mt-3">
            <p className="text-xs font-medium mb-1">Affected files:</p>
            <ul className="text-xs text-muted-foreground space-y-0.5">
              {suggestion.affectedFiles.slice(0, 3).map(file => (
                <li key={file} className="flex items-center gap-1">
                  <FileCode className="h-3 w-3" />
                  <span className="truncate">{file}</span>
                </li>
              ))}
              {suggestion.affectedFiles.length > 3 && (
                <li className="text-muted-foreground/70">
                  +{suggestion.affectedFiles.length - 3} more
                </li>
              )}
            </ul>
          </div>
        )}
        
        <div className="mt-3 flex items-center justify-between">
          <span className="text-xs text-muted-foreground">
            Est: {formatEstimate(suggestion.estimatedHours)} • {suggestion.complexity}
          </span>
          
          <div className="flex gap-1">
            <Button
              size="sm"
              variant="default"
              onClick={() => onImplement(suggestion.id)}
            >
              <Play className="h-3 w-3 mr-1" />
              Implement
            </Button>
            <Button
              size="sm"
              variant="outline"
              onClick={() => onDefer(suggestion.id)}
            >
              <Clock className="h-3 w-3 mr-1" />
              Defer
            </Button>
            <Button
              size="sm"
              variant="ghost"
              onClick={() => onReject(suggestion.id, '')}
            >
              <X className="h-3 w-3" />
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
};
```

---

## Action Workflows

### Implement Action

1. User clicks "Implement"
2. Confirmation dialog appears with suggestion details
3. User confirms
4. Suggestion moves to "in_progress"
5. AI begins implementing the suggestion
6. On completion, suggestion moves to "completed"

```typescript
async function handleImplement(suggestionId: string) {
  const confirmed = await confirmDialog({
    title: 'Implement Suggestion',
    description: 'The AI will start working on this suggestion. Continue?',
    confirmText: 'Start Implementation',
  });
  
  if (!confirmed) return;
  
  try {
    await api.updateSuggestionStatus(suggestionId, 'in_progress');
    await api.triggerSuggestionImplementation(suggestionId);
    toast.success('Implementation started');
  } catch (error) {
    toast.error('Failed to start implementation');
  }
}
```

### Defer Action

1. User clicks "Defer"
2. Optional note dialog
3. Suggestion stays in "pending" but is deprioritized
4. User can revisit later

### Reject Action

1. User clicks "Reject"
2. Reason dialog appears
3. User provides reason (optional)
4. Suggestion moves to "rejected"
5. File moves to resolved/ folder

---

## Hooks

### useSuggestions Hook

```typescript
interface UseSuggestionsOptions {
  projectId: string;
  status?: Suggestion['status'][];
  autoRefresh?: boolean;
  refreshInterval?: number;
}

function useSuggestions(options: UseSuggestionsOptions) {
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [stats, setStats] = useState<SuggestionStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  
  const fetch = useCallback(async () => {
    setIsLoading(true);
    try {
      const [data, statsData] = await Promise.all([
        api.getSuggestions(options.projectId, { status: options.status }),
        api.getSuggestionStats(options.projectId),
      ]);
      setSuggestions(data);
      setStats(statsData);
      setError(null);
    } catch (err) {
      setError(err as Error);
    } finally {
      setIsLoading(false);
    }
  }, [options.projectId, options.status]);
  
  // Auto-refresh
  useEffect(() => {
    if (options.autoRefresh) {
      const interval = setInterval(fetch, options.refreshInterval || 30000);
      return () => clearInterval(interval);
    }
  }, [fetch, options.autoRefresh, options.refreshInterval]);
  
  // Actions
  const implement = async (id: string) => {
    await api.updateSuggestionStatus(id, 'in_progress');
    await api.triggerSuggestionImplementation(id);
    await fetch();
  };
  
  const defer = async (id: string, note?: string) => {
    await api.deferSuggestion(id, note);
    await fetch();
  };
  
  const reject = async (id: string, reason: string) => {
    await api.rejectSuggestion(id, reason);
    await fetch();
  };
  
  return {
    suggestions,
    stats,
    isLoading,
    error,
    refetch: fetch,
    implement,
    defer,
    reject,
  };
}
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/projects/{id}/suggestions` | List suggestions |
| GET | `/api/v1/projects/{id}/suggestions/stats` | Get statistics |
| GET | `/api/v1/suggestions/{id}` | Get suggestion detail |
| PATCH | `/api/v1/suggestions/{id}/status` | Update status |
| POST | `/api/v1/suggestions/{id}/implement` | Trigger implementation |
| POST | `/api/v1/suggestions/{id}/defer` | Defer with note |
| POST | `/api/v1/suggestions/{id}/reject` | Reject with reason |

---

## Related Specifications

- [Suggestions System](./16-suggestions-system.md)
- [AI Chat Interface](./20-ai-chat-interface.md)
- [Project Editor UI](./15-project-editor-ui.md)
