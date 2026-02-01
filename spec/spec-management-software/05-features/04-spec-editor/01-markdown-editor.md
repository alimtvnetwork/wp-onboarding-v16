# Markdown Editor Component

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-28  

---

## Overview

Core markdown editing component using CodeMirror with toolbar, keyboard shortcuts, and intelligent editing features.

**Cross-References:**
- [Spec Editor Overview](./00-overview.md)
- [Preview Renderer](./02-preview-renderer.md)
- [File Management](../02-file-management/00-overview.md)

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| CodeMirror Integration | Full-featured code editor with markdown mode | High |
| Toolbar | Formatting buttons (bold, italic, headers, lists, links) | High |
| Keyboard Shortcuts | Standard markdown shortcuts (Ctrl+B, Ctrl+I, etc.) | High |
| Auto-save | Debounced save with visual indicator | High |
| Syntax Highlighting | Markdown syntax + fenced code blocks | Medium |
| Line Numbers | Optional line number gutter | Low |
| Search & Replace | Find/replace with regex support | Medium |
| Undo/Redo | Full history stack with visual indicator | High |

---

## Component Structure

```
MarkdownEditor/
├── MarkdownEditor.tsx        # Main editor component
├── EditorToolbar.tsx         # Formatting toolbar
├── EditorStatusBar.tsx       # Save status, cursor position
├── useEditorState.ts         # Editor state management
├── useAutoSave.ts            # Debounced auto-save hook
└── editorConfig.ts           # CodeMirror configuration
```

---

## Props Interface

```typescript
interface MarkdownEditorProps {
  /** Initial content */
  value: string;
  /** Change callback */
  onChange: (value: string) => void;
  /** Auto-save callback */
  onSave?: (value: string) => Promise<void>;
  /** Auto-save delay in ms (default: 1000) */
  autoSaveDelay?: number;
  /** Show line numbers */
  lineNumbers?: boolean;
  /** Show toolbar */
  showToolbar?: boolean;
  /** Read-only mode */
  readOnly?: boolean;
  /** Placeholder text */
  placeholder?: string;
  /** Expected hash for optimistic locking */
  expectedHash?: string;
}
```

---

## Toolbar Actions

| Action | Shortcut | Markdown |
|--------|----------|----------|
| Bold | Ctrl+B | `**text**` |
| Italic | Ctrl+I | `*text*` |
| Heading 1 | Ctrl+1 | `# text` |
| Heading 2 | Ctrl+2 | `## text` |
| Heading 3 | Ctrl+3 | `### text` |
| Bullet List | Ctrl+U | `- item` |
| Numbered List | Ctrl+O | `1. item` |
| Link | Ctrl+K | `[text](url)` |
| Code | Ctrl+` | `` `code` `` |
| Code Block | Ctrl+Shift+` | ` ```code``` ` |
| Quote | Ctrl+Q | `> quote` |
| Table | Ctrl+T | Insert table template |

---

## Auto-save Behavior

```typescript
// useAutoSave.ts
interface AutoSaveState {
  status: 'idle' | 'saving' | 'saved' | 'error';
  lastSaved: Date | null;
  error: Error | null;
}

// Debounce saves by 1000ms (configurable)
// Show visual indicator during save
// Handle conflict detection via expectedHash
// Retry on network failure with exponential backoff
```

---

## Conflict Detection

When `expectedHash` is provided:
1. Server returns `409 Conflict` if hash mismatch
2. Editor shows conflict modal with options:
   - **Overwrite** — Force save current content
   - **Reload** — Discard changes and reload
   - **Merge** — Open diff view (future feature)

---

## CodeMirror Configuration

```typescript
// editorConfig.ts
const editorExtensions = [
  markdown(),
  syntaxHighlighting(markdownHighlighting),
  history(),
  keymap.of([
    ...defaultKeymap,
    ...markdownKeymap,
    ...historyKeymap,
  ]),
  placeholder('Start writing...'),
  EditorView.lineWrapping,
];
```

---

## State Management

```typescript
// useEditorState.ts
interface EditorState {
  content: string;
  isDirty: boolean;
  cursorPosition: { line: number; column: number };
  selection: { from: number; to: number } | null;
  history: { canUndo: boolean; canRedo: boolean };
}
```

---

## Accessibility

- Full keyboard navigation
- ARIA labels on toolbar buttons
- Screen reader announcements for save status
- High contrast mode support
- Focus management on modal dialogs

---

## Error Handling

| Error | Code | User Message |
|-------|------|--------------|
| Save failed | 6001 | "Failed to save. Retrying..." |
| Conflict detected | 6002 | "File modified elsewhere. Review changes." |
| Network error | 6003 | "Connection lost. Changes saved locally." |

---

## Related Specs

- [Preview Renderer](./02-preview-renderer.md)
- [File Operations](../02-file-management/01-file-operations.md)
- [Error Management](../../06-error-management/00-overview.md)
