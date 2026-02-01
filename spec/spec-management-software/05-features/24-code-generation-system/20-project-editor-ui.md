# Project Editor UI

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

The Project Editor provides a VS Code-like interface for managing specification and code files within a project. It features a file tree navigator, multi-tab editor with Markdown/JSON support, an AI Assistant panel with voice and text input, and mode switching between Spec and Code generation contexts.

**Cross-References:**
- [Spec Editor](../04-spec-editor/00-overview.md)
- [AI Integration](../06-ai-integration/00-overview.md)
- [Voice Input](../05-voice-input/00-overview.md)
- [Code Generation System](./00-overview.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)

---

## Layout Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  [← Back]  Project: My Spec Project                      [Spec Mode ▼] [⚙️]    │
├─────────────────────────────────────────────────────────────────────────────────┤
│     │                                                                           │
│  F  │  ┌──────────────────────────────────────────────────────────────────┐    │
│  I  │  │ [overview.md] [api-spec.md] [schema.md]  ×                       │    │
│  L  │  ├──────────────────────────────────────────────────────────────────┤    │
│  E  │  │                                                                   │    │
│     │  │                      EDITOR PANE                                  │    │
│  T  │  │                                                                   │    │
│  R  │  │     CodeMirror (Markdown) / Monaco (JSON)                         │    │
│  E  │  │                                                                   │    │
│  E  │  │                                                                   │    │
│     │  │                                                                   │    │
│     │  └───────────────────────────┬──────────────────────────────────────┘    │
│     │                              │                                           │
│     │  ┌───────────────────────────┴──────────────────────────────────────┐    │
│     │  │                      PREVIEW PANE                                 │    │
│     │  │           (Split view - Rendered Markdown)                        │    │
│     │  └──────────────────────────────────────────────────────────────────┘    │
│     │                                                                           │
├─────┴───────────────────────────────────────────────────────────────────────────┤
│                            AI ASSISTANT PANEL                                    │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │ [🎤 Voice] [📎 Upload] [📝 Text] │ [Validate ▼] [Generate ▼]               ││
│  ├─────────────────────────────────────────────────────────────────────────────┤│
│  │                                                                              ││
│  │  Chat history / instruction input area                                       ││
│  │                                                                              ││
│  │  [Memory: context.md, schema.md ×]                                          ││
│  │                                                                              ││
│  │  ┌──────────────────────────────────────────┐  [Send]                       ││
│  │  │ Type your instruction or question...     │                               ││
│  │  └──────────────────────────────────────────┘                               ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Component Hierarchy

```
ProjectEditor/
├── ProjectEditorPage.tsx          # Main page layout
├── components/
│   ├── Header/
│   │   ├── ProjectHeader.tsx      # Project name, back button
│   │   ├── ModeSelector.tsx       # Spec/Code mode toggle
│   │   └── SettingsButton.tsx     # Settings dropdown
│   │
│   ├── FileTree/
│   │   ├── FileTree.tsx           # Tree container
│   │   ├── FileTreeNode.tsx       # Individual node (file/folder)
│   │   ├── FileTreeContext.tsx    # Context menu (right-click)
│   │   ├── NewFileDialog.tsx      # Create file/folder
│   │   └── useFileTree.ts         # Tree state management
│   │
│   ├── TabBar/
│   │   ├── TabBar.tsx             # Tab container
│   │   ├── EditorTab.tsx          # Individual tab
│   │   ├── TabActions.tsx         # Close, close others
│   │   └── useTabManager.ts       # Tab state (open, close, reorder)
│   │
│   ├── EditorPane/
│   │   ├── EditorPane.tsx         # Editor container
│   │   ├── MarkdownEditor.tsx     # CodeMirror 6 for .md
│   │   ├── JsonEditor.tsx         # Monaco for .json
│   │   ├── PreviewPane.tsx        # Rendered markdown preview
│   │   ├── SplitView.tsx          # Resizable split
│   │   └── useEditor.ts           # Editor state, auto-save
│   │
│   ├── AIPanel/
│   │   ├── AIPanel.tsx            # Main AI panel
│   │   ├── InputModeSelector.tsx  # Voice/Upload/Text toggle
│   │   ├── VoiceInput.tsx         # Voice recording UI
│   │   ├── FileUploader.tsx       # Drag-drop file upload
│   │   ├── TextInput.tsx          # Text instruction input
│   │   ├── MemorySelector.tsx     # Context file picker
│   │   ├── ChatHistory.tsx        # Instruction/response history
│   │   ├── ActionDropdowns.tsx    # Validate, Generate menus
│   │   └── useAIPanel.ts          # Panel state
│   │
│   └── Validation/
│       ├── ValidationPanel.tsx    # Validation results view
│       ├── LoopProgress.tsx       # Multi-iteration progress
│       ├── ValidationReport.tsx   # Final report display
│       └── BuildStatus.tsx        # brun build status
│
├── hooks/
│   ├── useProjectEditor.ts        # Main editor orchestration
│   ├── useAutoSave.ts             # Periodic .temp saves
│   ├── useKeyboardShortcuts.ts    # Editor shortcuts
│   └── useProjectFiles.ts         # File CRUD operations
│
└── types/
    └── editor.ts                  # TypeScript interfaces
```

---

## Mode Selector (Spec vs Code)

The editor operates in two distinct modes that control AI context and output targets:

### Spec Mode (Default)

| Setting | Value |
|---------|-------|
| Context | Markdown specification files |
| Output Target | Spec files (`.md`) |
| AI Focus | Specification writing, documentation |
| Validation | Consistency checker |
| Memory Files | Spec folder contents |

### Code Mode

| Setting | Value |
|---------|-------|
| Context | Source code files + specs |
| Output Target | Code files (`.go`, `.tsx`, etc.) |
| AI Focus | Code generation from specs |
| Validation | Build verification (brun CLI) |
| Memory Files | Spec + generated code |

```typescript
interface EditorMode {
  mode: 'spec' | 'code';
  contextScope: 'spec-only' | 'spec-and-code';
  outputTarget: 'spec' | 'backend' | 'frontend' | 'both';
  validationType: 'consistency' | 'build' | 'both';
}

// Mode selector component
interface ModeSelectorProps {
  currentMode: EditorMode;
  onModeChange: (mode: EditorMode) => void;
  projectLanguages: string[]; // ['golang', 'react']
}
```

---

## File Tree Component

### Features

| Feature | Description |
|---------|-------------|
| Expand/Collapse | Folder toggle with arrow indicators |
| File Icons | Type-specific icons (md, json, go, tsx) |
| Context Menu | Right-click: New, Rename, Delete, Copy Path |
| Drag & Drop | Reorder files and folders |
| Search/Filter | Quick file search (Ctrl+P) |
| Multi-Select | Shift/Ctrl click for bulk actions |

### State Structure

```typescript
interface FileTreeState {
  rootPath: string;
  expandedFolders: Set<string>;
  selectedPaths: string[];
  focusedPath: string | null;
  filter: string;
  loading: boolean;
}

interface FileNode {
  path: string;           // Relative path
  name: string;           // File/folder name
  type: 'file' | 'folder';
  extension?: string;     // For files
  children?: FileNode[];  // For folders
  modified?: boolean;     // Unsaved changes
  isNew?: boolean;        // Just created
}
```

### Context Menu Actions

```typescript
interface FileTreeContextMenu {
  items: ContextMenuItem[];
}

const specModeMenu: ContextMenuItem[] = [
  { id: 'new-file', label: 'New File', icon: 'file-plus', shortcut: 'Ctrl+N' },
  { id: 'new-folder', label: 'New Folder', icon: 'folder-plus' },
  { type: 'separator' },
  { id: 'rename', label: 'Rename', icon: 'edit', shortcut: 'F2' },
  { id: 'delete', label: 'Delete', icon: 'trash', shortcut: 'Del', danger: true },
  { type: 'separator' },
  { id: 'copy-path', label: 'Copy Path', icon: 'copy' },
  { id: 'reveal', label: 'Reveal in File Manager', icon: 'folder-open' },
];

const codeModeMenu: ContextMenuItem[] = [
  ...specModeMenu,
  { type: 'separator' },
  { id: 'generate', label: 'Generate from Spec', icon: 'wand' },
  { id: 'validate', label: 'Validate with brun', icon: 'check-circle' },
];
```

---

## Tab Bar Component

### Features

| Feature | Description |
|---------|-------------|
| Multi-Tab | Multiple files open simultaneously |
| Dirty Indicator | Dot/asterisk for unsaved changes |
| Close Button | Per-tab close with save prompt |
| Reorder | Drag tabs to reorder |
| Overflow Menu | Horizontal scroll + dropdown for many tabs |
| Close Actions | Close, Close Others, Close All |

### Tab State

```typescript
interface TabState {
  id: string;              // Unique tab ID
  filePath: string;        // File path
  label: string;           // Display name
  isDirty: boolean;        // Has unsaved changes
  isPinned: boolean;       // Prevent close
  scrollPosition?: number; // Restore scroll
  cursorPosition?: {       // Restore cursor
    line: number;
    column: number;
  };
}

interface TabManagerState {
  tabs: TabState[];
  activeTabId: string | null;
  recentlyClosedTabs: TabState[]; // For undo close
}
```

---

## Editor Pane

### Markdown Editor (CodeMirror 6)

```typescript
interface MarkdownEditorProps {
  content: string;
  onChange: (content: string) => void;
  onSave: () => void;
  filePath: string;
  readOnly?: boolean;
}

// CodeMirror extensions
const markdownExtensions = [
  markdown(),
  syntaxHighlighting(defaultHighlightStyle),
  lineNumbers(),
  foldGutter(),
  bracketMatching(),
  autocompletion(),
  EditorView.lineWrapping,
  keymap.of([...defaultKeymap, ...searchKeymap]),
];
```

### JSON Editor (Monaco)

```typescript
interface JsonEditorProps {
  content: string;
  onChange: (content: string) => void;
  onSave: () => void;
  filePath: string;
  schema?: object; // JSON Schema for validation
}

// Monaco options
const monacoOptions: editor.IStandaloneEditorConstructionOptions = {
  language: 'json',
  theme: 'vs-dark', // or 'vs' for light
  automaticLayout: true,
  formatOnPaste: true,
  formatOnType: true,
  minimap: { enabled: false },
  scrollBeyondLastLine: false,
};
```

### Split View Modes

| Mode | Layout |
|------|--------|
| Editor Only | Full editor, no preview |
| Preview Only | Full preview, no editor |
| Split Horizontal | Editor top, preview bottom |
| Split Vertical | Editor left, preview right (default) |

```typescript
type SplitMode = 'editor' | 'preview' | 'split-h' | 'split-v';

interface SplitViewProps {
  mode: SplitMode;
  onModeChange: (mode: SplitMode) => void;
  editorContent: ReactNode;
  previewContent: ReactNode;
  defaultSplitPercent?: number; // 0-100
}
```

---

## AI Assistant Panel

### Panel Structure

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  INPUT MODE                           │  ACTIONS                            │
│  [🎤 Voice] [📎 Upload] [📝 Text]     │  [Validate ▼] [Generate ▼]         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  CHAT HISTORY                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 🤖 AI: I've analyzed the specification. Found 3 issues...          │   │
│  │                                                                      │   │
│  │ 👤 You: Please fix the broken links                                 │   │
│  │                                                                      │   │
│  │ 🤖 AI: Fixed 3 broken links in api-spec.md...                       │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  MEMORY/CONTEXT                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ Selected files for context:                                         │   │
│  │ [overview.md ×] [schema.md ×] [+ Add files]                         │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  INPUT AREA                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ Type your instruction...                                     [Send] │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Input Modes

```typescript
type InputMode = 'voice' | 'upload' | 'text';

interface AIInputState {
  mode: InputMode;
  
  // Voice mode
  isRecording: boolean;
  voiceBlob?: Blob;
  transcription?: string;
  
  // Upload mode
  uploadedFiles: File[];
  
  // Text mode
  textInput: string;
}
```

### Action Dropdowns

#### Validate Dropdown (Spec Mode)

| Action | Description | Shortcut |
|--------|-------------|----------|
| Validate Once | Single consistency check | Ctrl+Shift+V |
| Loop Validate | Iterate until 99%+ score | Ctrl+Shift+L |
| View Report | Show last validation report | - |

#### Validate Dropdown (Code Mode)

| Action | Description | Shortcut |
|--------|-------------|----------|
| Build Check | Run brun check once | Ctrl+B |
| Loop Build | Iterate until no errors | Ctrl+Shift+B |
| View Build Log | Show build output | - |

#### Generate Dropdown

| Action | Description | Mode |
|--------|-------------|------|
| Generate from Voice | Transcribe and generate | Both |
| Generate from Text | Use text input | Both |
| Generate from Selection | Use selected code/text | Both |
| Batch Generate | Generate multiple files | Code |

```typescript
interface ActionDropdownItem {
  id: string;
  label: string;
  icon: string;
  shortcut?: string;
  mode?: 'spec' | 'code' | 'both';
  onClick: () => void;
}

// Validate dropdown config
const validateActions: ActionDropdownItem[] = [
  {
    id: 'validate-once',
    label: 'Validate Once',
    icon: 'check',
    shortcut: 'Ctrl+Shift+V',
    mode: 'both',
    onClick: () => runValidation({ loop: false }),
  },
  {
    id: 'loop-validate',
    label: 'Loop Validate (until 99%)',
    icon: 'refresh-cw',
    shortcut: 'Ctrl+Shift+L',
    mode: 'both',
    onClick: () => runValidation({ loop: true, targetScore: 99 }),
  },
  // ...
];
```

### Memory Selector

Allows selecting files to include in AI context:

```typescript
interface MemoryFile {
  path: string;
  name: string;
  size: number;
  selected: boolean;
}

interface MemorySelectorProps {
  availableFiles: MemoryFile[];
  selectedFiles: string[];
  onSelectionChange: (paths: string[]) => void;
  maxContextSize?: number; // Token limit
  currentContextSize?: number;
}
```

---

## Loop Validation System

### Spec Loop Validation

```typescript
interface LoopValidationConfig {
  targetScore: number;      // Target percentage (99)
  maxIterations: number;    // Maximum loops (10)
  fixCategories: string[];  // Which issues to auto-fix
  pauseOnMajorChange: boolean;
}

interface LoopValidationState {
  status: 'idle' | 'running' | 'paused' | 'completed' | 'failed';
  currentIteration: number;
  iterations: ValidationIteration[];
  finalScore: number;
}

interface ValidationIteration {
  iteration: number;
  startedAt: Date;
  completedAt: Date;
  scoreBeforee: number;
  scoreAfter: number;
  issuesFound: number;
  issuesFixed: number;
  changes: FileChange[];
}
```

### Build Loop Validation (Code Mode)

```typescript
interface BuildLoopConfig {
  language: 'golang' | 'react' | 'both';
  maxIterations: number;
  brunOptions: BrunCheckOptions;
}

interface BuildIteration {
  iteration: number;
  startedAt: Date;
  completedAt: Date;
  errorsBefore: number;
  errorsAfter: number;
  fixesApplied: CodeFix[];
  brunOutput: string;
}
```

---

## Validation Reports & Logs

### Report Structure

```
{workDirectory}/
└── data/
    └── projects/
        └── {project_name}/
            └── logs/
                ├── validation/
                │   ├── 2026-01-29_001_spec-validation.json
                │   ├── 2026-01-29_002_spec-validation.json
                │   └── 2026-01-29_003_build-validation.json
                └── generation/
                    ├── 2026-01-29_001_code-gen.json
                    └── 2026-01-29_002_code-gen.json
```

### Report Schema

```typescript
interface ValidationReport {
  id: string;
  projectId: string;
  type: 'spec' | 'build';
  
  // Summary
  startedAt: Date;
  completedAt: Date;
  totalDurationMs: number;
  
  // Iterations
  iterations: Array<{
    number: number;
    durationMs: number;
    scoreOrErrors: number;
    issuesFound: Issue[];
    fixesApplied: Fix[];
  }>;
  
  // Final state
  finalScore?: number;        // Spec mode
  finalErrorCount?: number;   // Build mode
  success: boolean;
  
  // Logs
  detailedLog: string;        // Full text log
}
```

---

## State Management

```typescript
interface ProjectEditorState {
  // Project
  projectId: string;
  projectName: string;
  projectPath: string;
  
  // Mode
  editorMode: 'spec' | 'code';
  codeOutputTarget: 'backend' | 'frontend' | 'both';
  
  // File Tree
  fileTree: FileTreeState;
  
  // Tabs
  tabs: TabManagerState;
  
  // Editor
  activeFile: {
    path: string;
    content: string;
    originalContent: string;
    isDirty: boolean;
  } | null;
  
  // AI Panel
  aiPanel: {
    isOpen: boolean;
    inputMode: InputMode;
    chatHistory: ChatMessage[];
    memoryFiles: string[];
    isProcessing: boolean;
  };
  
  // Validation
  validation: {
    status: 'idle' | 'running' | 'completed';
    currentIteration?: number;
    report?: ValidationReport;
  };
}
```

---

## Keyboard Shortcuts

| Shortcut | Action | Scope |
|----------|--------|-------|
| Ctrl+S | Save current file | Editor |
| Ctrl+Shift+S | Save all files | Editor |
| Ctrl+P | Quick file open | Global |
| Ctrl+Shift+P | Command palette | Global |
| Ctrl+B | Toggle file tree | Global |
| Ctrl+J | Toggle AI panel | Global |
| Ctrl+W | Close current tab | Tabs |
| Ctrl+Tab | Next tab | Tabs |
| Ctrl+Shift+Tab | Previous tab | Tabs |
| Ctrl+/ | Toggle comment | Editor |
| Ctrl+F | Find in file | Editor |
| Ctrl+Shift+F | Find in project | Global |
| Ctrl+Shift+V | Validate once | AI Panel |
| Ctrl+Shift+L | Loop validate | AI Panel |
| Ctrl+Shift+B | Build check | Code Mode |
| F2 | Rename file | File Tree |

---

## Auto-Save System

```typescript
interface AutoSaveConfig {
  enabled: boolean;
  intervalSeconds: number;  // Default: 30
  tempFileExtension: string; // '.temp'
  maxTempFiles: number;     // Cleanup threshold
}

// Auto-save writes to .temp files
// Example: api-spec.md.temp
// On explicit save, .temp is removed

interface AutoSaveState {
  lastSaveTime: Date;
  pendingChanges: boolean;
  tempFilePath: string | null;
}
```

---

## API Integration

### File Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/projects/{id}/files` | List files |
| GET | `/api/v1/projects/{id}/files/{path}` | Get file content |
| POST | `/api/v1/projects/{id}/files` | Create file |
| PUT | `/api/v1/projects/{id}/files/{path}` | Update file |
| DELETE | `/api/v1/projects/{id}/files/{path}` | Delete file |
| POST | `/api/v1/projects/{id}/files/move` | Move/rename file |

### AI Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/projects/{id}/ai/instruct` | Submit instruction |
| POST | `/api/v1/projects/{id}/validate` | Run validation |
| POST | `/api/v1/projects/{id}/validate/loop` | Loop validation |
| POST | `/api/v1/projects/{id}/generate` | Generate code |
| GET | `/api/v1/projects/{id}/reports` | List reports |

---

## Related Specifications

- [Spec Editor](../04-spec-editor/00-overview.md)
- [AI Integration](../06-ai-integration/00-overview.md)
- [Voice Processing](../05-voice-input/04-voice-processing-pipeline.md)
- [Consistency Checker](../08-consistency-checker/00-overview.md)
- [Code Generation](./00-overview.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
