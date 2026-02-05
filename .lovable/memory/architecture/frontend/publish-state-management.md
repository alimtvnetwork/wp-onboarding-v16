# Memory: architecture/frontend/publish-state-management
Updated: 2026-02-05

Publishing operations are managed via a global Zustand store (`src/stores/publishStore.ts`), enabling "Quick Publish" workflows where deployments run in the background. This architecture ensures that publishing state, progress indicators, and real-time logs remain persistent and synchronized even as users navigate between views.

## Store Structure

```typescript
interface PublishOperation {
  id: string;
  pluginId: number;
  pluginName: string;
  siteId: number;
  siteName: string;
  siteUrl: string;
  sessionId: string;
  status: 'pending' | 'running' | 'success' | 'error';
  progress: number;
  stage: string;
  message: string;
  error?: string;
  startedAt: Date;
  completedAt?: Date;
  logs: PublishLogEntry[];
}
```

## Key Actions

- `startOperation()` - Initiate a new publish operation
- `updateProgress()` - Update progress from WebSocket events
- `completeOperation()` - Mark as success/error
- `addLog()` - Append real-time log entries
- `clearCompleted()` - Remove finished operations

## WebSocket Integration

The store listens for events: `publish_progress`, `publish_complete`, `publish_error`, `stage_complete`, `log`.

## UI Components

- `QuickPublishIndicator` - Inline card status (spinner, checkmark, error)
- `GlobalPublishProgress` - Header badge showing active operation count

## Lifecycle

- Operations persist across route navigation
- Completed operations auto-cleanup after 30 minutes
- Error operations preserved until explicitly dismissed

## Related Files

- `src/stores/publishStore.ts` - State store
- `src/hooks/useQuickPublish.ts` - Quick publish hook
- `src/components/plugins/GlobalPublishProgress.tsx` - Header component
- `spec/wp-plugin-publish/02-frontend/27-quick-publish.md` - Full specification
