# AI Enhancements Test Plan

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Coverage Target:** 85% unit, 70% integration, 100% critical paths

---

## Overview

Comprehensive test plan covering all 6 phases of AI Enhancements with unit tests, integration tests, E2E tests, and acceptance criteria for each feature.

**Cross-References:**
- [Testing Strategy](../20-testing/00-overview.md)
- [Consistency Report](./99-consistency-report.md)
- [All Phase Specs](./00-overview.md)

---

## Test Environment

### Tools & Frameworks

| Category | Tool | Version | Purpose |
|----------|------|---------|---------|
| Unit Testing | Vitest | ^3.2.4 | Component/hook testing |
| Component Testing | @testing-library/react | ^16.0.0 | React component testing |
| E2E Testing | Playwright | ^1.40.0 | End-to-end flows |
| Mocking | MSW | ^2.0.0 | API mocking |
| DOM | jsdom | ^20.0.3 | Browser environment |
| Coverage | Vitest Coverage | built-in | Code coverage |

### Test Database

```typescript
// test/setup/test-db.ts
export const TEST_DB_CONFIG = {
  path: ':memory:',
  enableWAL: false,
  seedData: true,
};
```

---

## Phase 1: Offline-First Storage

### Unit Tests

| ID | Component | Test Case | Priority | Status |
|----|-----------|-----------|----------|--------|
| P1-U01 | VersionedStorage | `set()` stores data with correct key format | Critical | ⬜ |
| P1-U02 | VersionedStorage | `get()` retrieves stored data | Critical | ⬜ |
| P1-U03 | VersionedStorage | Returns `null` for non-existent keys | High | ⬜ |
| P1-U04 | VersionedStorage | `cleanup()` removes old version keys | Critical | ⬜ |
| P1-U05 | VersionedStorage | `cleanup()` preserves current version keys | Critical | ⬜ |
| P1-U06 | VersionedStorage | Handles corrupted JSON gracefully | High | ⬜ |
| P1-U07 | VersionedStorage | Triggers pruning on quota exceeded | High | ⬜ |
| P1-U08 | VersionedStorage | Prunes only synced entries | Critical | ⬜ |
| P1-U09 | SyncQueue | `enqueue()` adds operation with metadata | Critical | ⬜ |
| P1-U10 | SyncQueue | `processQueue()` calls API when online | Critical | ⬜ |
| P1-U11 | SyncQueue | `processQueue()` skips when offline | High | ⬜ |
| P1-U12 | SyncQueue | Increments retry count on failure | High | ⬜ |
| P1-U13 | SyncQueue | Removes entry after max retries | Medium | ⬜ |
| P1-U14 | SyncQueue | Debounces rapid online events | Medium | ⬜ |
| P1-U15 | BlobStorage | `save()` stores blob in IndexedDB | High | ⬜ |
| P1-U16 | BlobStorage | `get()` retrieves blob | High | ⬜ |
| P1-U17 | BlobStorage | `markSynced()` updates entry | Medium | ⬜ |
| P1-U18 | BlobStorage | `pruneOldSynced()` removes excess entries | Medium | ⬜ |

### Integration Tests

| ID | Scenario | Steps | Expected Result | Priority |
|----|----------|-------|-----------------|----------|
| P1-I01 | Offline → Online Sync | 1. Go offline<br>2. Save data<br>3. Go online | Data synced to backend | Critical |
| P1-I02 | Network Flapping | Rapidly toggle online/offline 10x | No duplicate syncs, no data loss | High |
| P1-I03 | Version Upgrade Migration | 1. Store with v1.0.0<br>2. Reload with v1.1.0 | Old data migrated correctly | High |
| P1-I04 | Quota Recovery | Fill to 90% capacity, write new data | Pruning triggers, write succeeds | High |
| P1-I05 | Concurrent Tabs | Two tabs writing simultaneously | No data corruption | Medium |

### E2E Tests

| ID | User Flow | Validation | Priority |
|----|-----------|------------|----------|
| P1-E01 | Type message → close tab → reopen | Draft text restored exactly | Critical |
| P1-E02 | Work offline 5 mins → go online | All changes appear on server | Critical |
| P1-E03 | Sync indicator shows pending count | Badge shows accurate count | High |
| P1-E04 | Force sync button works | Manual sync triggers immediately | Medium |

### Acceptance Criteria

- [ ] Every keystroke triggers debounced save (≤100ms)
- [ ] Data persists across browser refresh
- [ ] Offline indicator visible when disconnected
- [ ] Pending sync count visible in UI
- [ ] Old version keys cleaned up on app init
- [ ] No data loss during network transitions

---

## Phase 2: Voice Resilience

### Unit Tests

| ID | Component | Test Case | Priority | Status |
|----|-----------|-----------|----------|--------|
| P2-U01 | AudioRecorder | `start()` requests microphone permission | Critical | ⬜ |
| P2-U02 | AudioRecorder | Records in WebM/Opus format | Critical | ⬜ |
| P2-U03 | AudioRecorder | Falls back to MP3 if WebM unsupported | High | ⬜ |
| P2-U04 | AudioRecorder | `stop()` creates blob and saves to IndexedDB | Critical | ⬜ |
| P2-U05 | AudioRecorder | `onProgress` callback fires with amplitude | Medium | ⬜ |
| P2-U06 | AudioRecorder | Cleanup releases media stream | High | ⬜ |
| P2-U07 | AudioSyncQueue | Queues audio for upload when offline | Critical | ⬜ |
| P2-U08 | AudioSyncQueue | Uploads queued audio when online | Critical | ⬜ |
| P2-U09 | AudioSyncQueue | Chunks large files (>5MB) | High | ⬜ |
| P2-U10 | AudioSyncQueue | Resumes interrupted uploads | High | ⬜ |
| P2-U11 | WhisperService | Transcribes WebM audio | Critical | ⬜ |
| P2-U12 | WhisperService | Transcribes MP3 audio | Critical | ⬜ |
| P2-U13 | WhisperService | Converts to WAV internally | High | ⬜ |
| P2-U14 | WhisperService | Returns segments with timestamps | Medium | ⬜ |
| P2-U15 | WhisperService | Handles streaming input | High | ⬜ |

### Integration Tests

| ID | Scenario | Steps | Expected Result | Priority |
|----|----------|-------|-----------------|----------|
| P2-I01 | Full Recording Flow | 1. Start recording<br>2. Speak 10s<br>3. Stop<br>4. Wait for transcription | Accurate transcription returned | Critical |
| P2-I02 | Offline Recording | 1. Go offline<br>2. Record audio<br>3. Go online | Audio synced, transcription appears | Critical |
| P2-I03 | Long Recording | Record 5 minutes continuously | All audio saved, no gaps | High |
| P2-I04 | Microphone Denied | User denies permission | Graceful error message | High |
| P2-I05 | Upload Interruption | Disconnect mid-upload | Resume on reconnection | High |

### E2E Tests

| ID | User Flow | Validation | Priority |
|----|-----------|------------|----------|
| P2-E01 | Click mic → speak → stop | Transcription appears in chat input | Critical |
| P2-E02 | Record offline → go online | Transcription appears after sync | Critical |
| P2-E03 | Waveform visualization active during recording | Visual feedback shown | High |
| P2-E04 | Cancel recording mid-way | Audio discarded, UI resets | Medium |

### Acceptance Criteria

- [ ] Audio saved locally before any network operation
- [ ] Recording works in Chrome, Firefox, Safari, Edge
- [ ] Waveform visualization updates in real-time
- [ ] Transcription quality acceptable (WER < 10%)
- [ ] Large recordings (5min+) handled without memory issues
- [ ] Failed transcriptions retry automatically

---

## Phase 3: Plan Mode

### Unit Tests

| ID | Component | Test Case | Priority | Status |
|----|-----------|-----------|----------|--------|
| P3-U01 | PlanGenerator | Generates valid plan from prompt | Critical | ⬜ |
| P3-U02 | PlanGenerator | Plan contains Mermaid diagram | Critical | ⬜ |
| P3-U03 | PlanGenerator | Steps have correct types | High | ⬜ |
| P3-U04 | PlanGenerator | Dependencies correctly set | High | ⬜ |
| P3-U05 | PlanExecutor | Executes step successfully | Critical | ⬜ |
| P3-U06 | PlanExecutor | Updates step status to 'running' | High | ⬜ |
| P3-U07 | PlanExecutor | Updates step status to 'completed' | High | ⬜ |
| P3-U08 | PlanExecutor | Handles step failure gracefully | Critical | ⬜ |
| P3-U09 | PlanExecutor | Respects dependencies (blocked steps) | High | ⬜ |
| P3-U10 | PlanExecutor | Pause/resume works correctly | High | ⬜ |
| P3-U11 | PlanStore | Saves plan to database | High | ⬜ |
| P3-U12 | PlanStore | Updates plan status | High | ⬜ |
| P3-U13 | PlanStore | Records step history | Medium | ⬜ |
| P3-U14 | ApprovalWorkflow | Approve transitions status | Critical | ⬜ |
| P3-U15 | ApprovalWorkflow | Cancel transitions status | High | ⬜ |
| P3-U16 | ApprovalWorkflow | Modify step updates plan | High | ⬜ |

### Integration Tests

| ID | Scenario | Steps | Expected Result | Priority |
|----|----------|-------|-----------------|----------|
| P3-I01 | Plan Generation | Submit prompt → Wait for plan | Valid plan with diagram | Critical |
| P3-I02 | Full Execution | Approve → Execute All | All steps complete in order | Critical |
| P3-I03 | Step-by-Step | Approve → Execute one step → Continue | Pauses between steps | High |
| P3-I04 | Modify and Execute | Edit step → Approve → Execute | Modified step runs | High |
| P3-I05 | Cancel Mid-Execution | Start execution → Cancel | Execution stops, status updated | High |
| P3-I06 | Retry Failed Step | Step fails → Retry | Retry executes successfully | High |

### E2E Tests

| ID | User Flow | Validation | Priority |
|----|-----------|------------|----------|
| P3-E01 | Enter prompt → See plan → Approve → Watch execution | Full flow works end-to-end | Critical |
| P3-E02 | Edit step title before approval | Change persists | High |
| P3-E03 | Click "Execute All" button | All steps run sequentially | Critical |
| P3-E04 | Click "Pause" during execution | Execution pauses, can resume | High |
| P3-E05 | Mermaid diagram displays correctly | Visual diagram renders | High |

### Acceptance Criteria

- [ ] Plan generated within 5 seconds for typical prompts
- [ ] Mermaid diagram always valid syntax
- [ ] User can modify any step before approval
- [ ] Execution can be paused and resumed
- [ ] Failed steps show clear error messages
- [ ] Step history recorded for audit trail

---

## Phase 4: Mermaid Diagrams

### Unit Tests

| ID | Component | Test Case | Priority | Status |
|----|-----------|-----------|----------|--------|
| P4-U01 | DiagramGenerator | Generates flowchart from description | Critical | ⬜ |
| P4-U02 | DiagramGenerator | Generates sequence diagram | Critical | ⬜ |
| P4-U03 | DiagramGenerator | Generates ER diagram | High | ⬜ |
| P4-U04 | DiagramGenerator | Generates class diagram | High | ⬜ |
| P4-U05 | TypeDetector | Detects flowchart from prompt | High | ⬜ |
| P4-U06 | TypeDetector | Detects sequence from prompt | High | ⬜ |
| P4-U07 | TypeDetector | Detects ER from prompt | Medium | ⬜ |
| P4-U08 | ModelSelector | Selects llama-3 for flowchart | High | ⬜ |
| P4-U09 | ModelSelector | Selects mistral for ER diagram | Medium | ⬜ |
| P4-U10 | MermaidValidator | Validates correct syntax | Critical | ⬜ |
| P4-U11 | MermaidValidator | Rejects invalid syntax | Critical | ⬜ |
| P4-U12 | MermaidValidator | Provides correction hints | High | ⬜ |
| P4-U13 | DiagramCache | Caches generated diagrams | Medium | ⬜ |
| P4-U14 | DiagramCache | Returns cached on duplicate request | Medium | ⬜ |

### Integration Tests

| ID | Scenario | Steps | Expected Result | Priority |
|----|----------|-------|-----------------|----------|
| P4-I01 | Auto-Type Detection | Submit ambiguous prompt | Correct type detected | High |
| P4-I02 | Retry on Invalid | Generate → Invalid → Auto-retry | Valid diagram after retry | High |
| P4-I03 | Model Fallback | Primary model fails | Secondary model succeeds | Medium |
| P4-I04 | Prompt Template Loading | Request ER diagram | Correct template used | High |
| P4-I05 | Diagram Caching | Request same diagram twice | Second request uses cache | Medium |

### E2E Tests

| ID | User Flow | Validation | Priority |
|----|-----------|------------|----------|
| P4-E01 | Type "create flowchart of login" → See diagram | Flowchart renders correctly | Critical |
| P4-E02 | Click copy button on diagram | Mermaid code copied to clipboard | High |
| P4-E03 | Click download button | SVG/PNG downloaded | Medium |
| P4-E04 | Click fullscreen on diagram | Modal opens with large diagram | Medium |
| P4-E05 | Edit diagram code → Re-render | Updated diagram displays | High |

### Acceptance Criteria

- [ ] Flowchart generation < 3 seconds
- [ ] Sequence diagram generation < 4 seconds
- [ ] All generated diagrams have valid Mermaid syntax
- [ ] Auto-retry fixes 90% of syntax errors
- [ ] Diagrams render in both light and dark mode
- [ ] Export works for SVG and PNG formats

---

## Phase 5: Chat UI Redesign

### Unit Tests

| ID | Component | Test Case | Priority | Status |
|----|-----------|-----------|----------|--------|
| P5-U01 | ChatLayout | Renders three-panel layout | Critical | ⬜ |
| P5-U02 | ChatLayout | Sidebar collapses on mobile | High | ⬜ |
| P5-U03 | ChatLayout | Keyboard shortcuts work (⌘K) | High | ⬜ |
| P5-U04 | PlusMenu | Opens on click | Critical | ⬜ |
| P5-U05 | PlusMenu | All menu items trigger callbacks | High | ⬜ |
| P5-U06 | PlusMenu | Submenus open correctly | Medium | ⬜ |
| P5-U07 | ChatInput | Text input updates draft | Critical | ⬜ |
| P5-U08 | ChatInput | Enter sends message | Critical | ⬜ |
| P5-U09 | ChatInput | Shift+Enter adds newline | High | ⬜ |
| P5-U10 | ChatInput | Auto-resizes on multiline | Medium | ⬜ |
| P5-U11 | ChatInput | References display as badges | High | ⬜ |
| P5-U12 | ChatInput | Reference removal works | High | ⬜ |
| P5-U13 | useChatDraft | Saves draft to localStorage | Critical | ⬜ |
| P5-U14 | useChatDraft | Restores draft on mount | Critical | ⬜ |
| P5-U15 | useChatDraft | Debounces saves (300ms) | High | ⬜ |
| P5-U16 | MessageBubble | Renders user message | Critical | ⬜ |
| P5-U17 | MessageBubble | Renders AI message with Markdown | Critical | ⬜ |
| P5-U18 | MessageBubble | Renders code blocks with syntax highlighting | High | ⬜ |
| P5-U19 | MessageBubble | Renders Mermaid diagrams | High | ⬜ |
| P5-U20 | ModeSelector | Switches between modes | Critical | ⬜ |
| P5-U21 | ModeSelector | Mode persists on reload | High | ⬜ |
| P5-U22 | useMessageStream | Streams tokens progressively | Critical | ⬜ |
| P5-U23 | useMessageStream | Shows typing indicator | High | ⬜ |
| P5-U24 | HistorySidebar | Lists sessions grouped by date | High | ⬜ |
| P5-U25 | HistorySidebar | Session click loads messages | High | ⬜ |

### Integration Tests

| ID | Scenario | Steps | Expected Result | Priority |
|----|----------|-------|-----------------|----------|
| P5-I01 | Full Chat Flow | Type → Send → See response | Complete flow works | Critical |
| P5-I02 | Draft Persistence | Type → Refresh page | Draft restored | Critical |
| P5-I03 | File Reference | Plus → Upload → Send | File included in request | High |
| P5-I04 | Mode Switching | Switch to Plan Mode → Send | Plan generated | High |
| P5-I05 | Streaming Response | Send message | Tokens appear progressively | High |
| P5-I06 | Session History | Create 3 sessions → Navigate | All sessions accessible | High |

### E2E Tests

| ID | User Flow | Validation | Priority |
|----|-----------|------------|----------|
| P5-E01 | Type message → Send → See streamed response | Full chat works | Critical |
| P5-E02 | Click + → Screenshot → Capture → Send | Screenshot attached | High |
| P5-E03 | Click + → Add URL → Enter URL → Send | URL referenced | High |
| P5-E04 | Switch to Coding Mode → See Run button | Mode-specific UI appears | High |
| P5-E05 | Open sidebar → Click old session → See messages | History navigation works | High |
| P5-E06 | Type long message → See textarea expand | Auto-resize works | Medium |
| P5-E07 | ⌘K focuses input | Keyboard shortcut works | Medium |

### Acceptance Criteria

- [ ] Chat layout responsive (mobile, tablet, desktop)
- [ ] Plus menu accessible and functional
- [ ] Draft auto-saves within 300ms of typing
- [ ] Drafts persist across browser sessions
- [ ] Messages stream token-by-token
- [ ] All three modes functional (Spec, Coding, Plan)
- [ ] Markdown and code blocks render correctly
- [ ] Mermaid diagrams embed in messages

---

## Phase 6: Cross-Project Memory

### Unit Tests

| ID | Component | Test Case | Priority | Status |
|----|-----------|-----------|----------|--------|
| P6-U01 | ShareService | Creates share between projects | Critical | ⬜ |
| P6-U02 | ShareService | Validates source path exists | High | ⬜ |
| P6-U03 | ShareService | Prevents duplicate shares | High | ⬜ |
| P6-U04 | ShareService | Gets shared memories for project | Critical | ⬜ |
| P6-U05 | ShareService | Gets shared content | Critical | ⬜ |
| P6-U06 | ShareService | Revokes share correctly | High | ⬜ |
| P6-U07 | SyncWorker | Detects content changes | High | ⬜ |
| P6-U08 | SyncWorker | Syncs changed content | High | ⬜ |
| P6-U09 | SyncWorker | Detects conflicts via hash | High | ⬜ |
| P6-U10 | ConflictResolver | Applies source-wins strategy | High | ⬜ |
| P6-U11 | ConflictResolver | Applies target-wins strategy | Medium | ⬜ |
| P6-U12 | ConflictResolver | Applies last-write-wins strategy | Medium | ⬜ |
| P6-U13 | EmbeddingService | Chunks content correctly | High | ⬜ |
| P6-U14 | EmbeddingService | Generates embeddings | Critical | ⬜ |
| P6-U15 | SearchService | Finds similar content by vector | Critical | ⬜ |
| P6-U16 | SearchService | Respects token budget | High | ⬜ |
| P6-U17 | ContextAssembler | Assembles context from results | Critical | ⬜ |
| P6-U18 | ContextAssembler | Includes source attribution | High | ⬜ |

### Integration Tests

| ID | Scenario | Steps | Expected Result | Priority |
|----|----------|-------|-----------------|----------|
| P6-I01 | Share Spec File | Share spec A→B | B can access content | Critical |
| P6-I02 | Share Folder | Share folder A→B | All files accessible in B | High |
| P6-I03 | Sync on Change | Modify in A → Wait | B has updated content | High |
| P6-I04 | Conflict Detection | Modify in A and B | Conflict flagged | High |
| P6-I05 | Conflict Resolution | Resolve with source-wins | A's version in B | High |
| P6-I06 | RAG Search | Query with shared context | Results include shared content | Critical |
| P6-I07 | Revoke Share | Revoke A→B share | B loses access | High |

### E2E Tests

| ID | User Flow | Validation | Priority |
|----|-----------|------------|----------|
| P6-E01 | Plus → Share Memory → Select project → Share | Share created successfully | Critical |
| P6-E02 | View shared memories panel | List shows all shares | High |
| P6-E03 | Click shared memory → See content | Content displays | High |
| P6-E04 | Reference shared spec in chat | AI uses shared context | Critical |
| P6-E05 | Revoke share via UI | Share removed | High |
| P6-E06 | Copy shared memory to local | Local copy created | Medium |

### Acceptance Criteria

- [ ] Sharing works across all memory types (spec, folder, file, URL, memory)
- [ ] Permission levels enforced (read, copy, sync)
- [ ] Sync propagates within 30 seconds
- [ ] Conflicts detected and reported
- [ ] RAG search includes shared content
- [ ] Source attribution in assembled context
- [ ] Revoking share immediately removes access

---

## Cross-Phase Integration Tests

| ID | Phases | Scenario | Expected Result | Priority |
|----|--------|----------|-----------------|----------|
| CP-I01 | 1,2 | Record voice offline → Sync when online | Audio synced, transcription appears | Critical |
| CP-I02 | 1,5 | Type draft offline → Sync when online | Message sent successfully | Critical |
| CP-I03 | 3,4 | Generate plan with diagram | Plan contains valid Mermaid | Critical |
| CP-I04 | 5,3 | Switch to Plan Mode → Send prompt | Plan UI appears | High |
| CP-I05 | 5,6 | Reference shared memory in chat | Context used in response | High |
| CP-I06 | 1,6 | Sync shared content offline → Go online | Content synced correctly | High |
| CP-I07 | 2,5 | Use voice input in chat | Transcription appears in input | High |
| CP-I08 | 4,5 | AI responds with diagram | Diagram renders in message | High |

---

## Performance Tests

| ID | Area | Metric | Target | Method |
|----|------|--------|--------|--------|
| PERF-01 | Offline Storage | localStorage write latency | < 10ms | Benchmark |
| PERF-02 | Offline Storage | IndexedDB blob write (1MB) | < 100ms | Benchmark |
| PERF-03 | Sync Queue | Queue processing (100 items) | < 5s | Load test |
| PERF-04 | Voice Transcription | 30s audio transcription | < 10s | Benchmark |
| PERF-05 | Plan Generation | Plan from 100-word prompt | < 5s | Benchmark |
| PERF-06 | Mermaid Generation | Flowchart (10 nodes) | < 3s | Benchmark |
| PERF-07 | Message Streaming | Token render latency | < 50ms | Benchmark |
| PERF-08 | RAG Search | Semantic search (1000 chunks) | < 500ms | Benchmark |
| PERF-09 | Memory Sync | Sync 10 shared items | < 30s | Benchmark |
| PERF-10 | UI Responsiveness | Time to interactive | < 2s | Lighthouse |

---

## Accessibility Tests

| ID | Area | Requirement | Standard | Priority |
|----|------|-------------|----------|----------|
| A11Y-01 | Chat Input | Keyboard accessible | WCAG 2.1 AA | Critical |
| A11Y-02 | Voice Button | ARIA labels | WCAG 2.1 AA | High |
| A11Y-03 | Plus Menu | Focus management | WCAG 2.1 AA | High |
| A11Y-04 | Plan Approval | Screen reader compatible | WCAG 2.1 AA | High |
| A11Y-05 | Mode Selector | Keyboard navigation | WCAG 2.1 AA | High |
| A11Y-06 | Message List | Live region for new messages | WCAG 2.1 AA | Medium |
| A11Y-07 | Diagrams | Alt text for images | WCAG 2.1 AA | Medium |
| A11Y-08 | Color Contrast | All text meets ratio | WCAG 2.1 AA | High |

---

## Security Tests

| ID | Area | Test Case | Priority |
|----|------|-----------|----------|
| SEC-01 | Share Permissions | User cannot access unshared content | Critical |
| SEC-02 | Share Permissions | Revoked shares immediately block access | Critical |
| SEC-03 | API Auth | Unauthenticated requests rejected | Critical |
| SEC-04 | Input Validation | XSS prevention in chat messages | Critical |
| SEC-05 | Input Validation | SQL injection prevention | Critical |
| SEC-06 | Audio Upload | Malicious file rejection | High |
| SEC-07 | Rate Limiting | API rate limits enforced | High |
| SEC-08 | Data Isolation | Projects isolated from each other | Critical |

---

## Test Data Requirements

### Seed Data

```typescript
// test/fixtures/seed-data.ts

export const seedData = {
  projects: [
    { id: 'proj_1', name: 'Test Project A' },
    { id: 'proj_2', name: 'Test Project B' },
  ],
  
  specs: [
    { id: 'spec_1', projectId: 'proj_1', path: 'specs/api.md', content: '# API Spec\n...' },
    { id: 'spec_2', projectId: 'proj_1', path: 'specs/auth.md', content: '# Auth Spec\n...' },
  ],
  
  users: [
    { id: 'user_1', email: 'test@example.com', name: 'Test User' },
  ],
  
  sessions: [
    { id: 'session_1', projectId: 'proj_1', createdAt: new Date() },
  ],
  
  shares: [
    { id: 'share_1', sourceProjectId: 'proj_1', targetProjectId: 'proj_2', path: 'specs/api.md' },
  ],
};
```

### Mock Audio Files

| File | Duration | Format | Size | Use Case |
|------|----------|--------|------|----------|
| short.webm | 5s | WebM/Opus | 50KB | Quick tests |
| medium.webm | 30s | WebM/Opus | 300KB | Standard tests |
| long.webm | 5min | WebM/Opus | 2.5MB | Stress tests |
| noisy.webm | 10s | WebM/Opus | 100KB | Quality tests |

---

## CI/CD Integration

### Test Pipeline

```yaml
# .github/workflows/test.yml

name: Test Suite

on: [push, pull_request]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: oven-sh/setup-bun@v1
      - run: bun install
      - run: bun test:unit
      - uses: codecov/codecov-action@v4

  integration-tests:
    runs-on: ubuntu-latest
    needs: unit-tests
    steps:
      - uses: actions/checkout@v4
      - uses: oven-sh/setup-bun@v1
      - run: bun install
      - run: bun test:integration

  e2e-tests:
    runs-on: ubuntu-latest
    needs: integration-tests
    steps:
      - uses: actions/checkout@v4
      - uses: oven-sh/setup-bun@v1
      - run: bun install
      - run: bunx playwright install
      - run: bun test:e2e
```

### Coverage Thresholds

```typescript
// vitest.config.ts coverage settings

coverage: {
  provider: 'v8',
  reporter: ['text', 'json', 'html'],
  thresholds: {
    lines: 85,
    functions: 80,
    branches: 75,
    statements: 85,
  },
  include: ['src/**/*.ts', 'src/**/*.tsx'],
  exclude: ['src/**/*.test.ts', 'src/**/*.test.tsx', 'src/test/**'],
}
```

---

## Test Execution Schedule

| Test Type | When | Duration | Required to Pass |
|-----------|------|----------|------------------|
| Unit Tests | Every commit | ~2 min | Yes (PR merge) |
| Integration Tests | Every PR | ~10 min | Yes (PR merge) |
| E2E Tests | Nightly + Release | ~30 min | Yes (Release) |
| Performance Tests | Weekly | ~15 min | No (advisory) |
| Security Tests | Weekly + Release | ~10 min | Yes (Release) |
| Accessibility Tests | Release | ~5 min | Yes (Release) |

---

## Test Status Dashboard

### Phase Completion Tracker

| Phase | Unit | Integration | E2E | Total Tests | Passing | Coverage |
|-------|------|-------------|-----|-------------|---------|----------|
| 1. Offline-First | 0/18 | 0/5 | 0/4 | 27 | 0% | 0% |
| 2. Voice Resilience | 0/15 | 0/5 | 0/4 | 24 | 0% | 0% |
| 3. Plan Mode | 0/16 | 0/6 | 0/5 | 27 | 0% | 0% |
| 4. Mermaid Diagrams | 0/14 | 0/5 | 0/5 | 24 | 0% | 0% |
| 5. Chat UI Redesign | 0/25 | 0/6 | 0/7 | 38 | 0% | 0% |
| 6. Cross-Project Memory | 0/18 | 0/7 | 0/6 | 31 | 0% | 0% |
| **Cross-Phase** | — | 0/8 | — | 8 | 0% | — |
| **Total** | 0/106 | 0/42 | 0/31 | **179** | **0%** | **0%** |

---

## Related Specs

- [Testing Strategy](../20-testing/00-overview.md)
- [Consistency Report](./99-consistency-report.md)
- [00-overview.md](./00-overview.md)
- All 6 phase specifications
