# Editor State Hooks

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  
**Parent:** [Project Editor](./00-overview.md)

---

## Purpose

Define the React hooks for managing editor state, including cursor position, selection, scroll position, and undo/redo history. These hooks integrate with the input persistence system to preserve complete editor context.

---

## Hooks Overview

| Hook | Purpose |
|------|---------|
| `useEditorState` | Complete editor state management |
| `useCursorPosition` | Track and restore cursor/caret position |
| `useSelectionState` | Manage text selection |
| `useScrollPosition` | Preserve scroll position across sessions |
| `useUndoRedo` | Local undo/redo stack with persistence |

---

## useEditorState

Unified hook combining all editor state concerns:

```typescript
interface EditorState {
  readonly content: string;
  readonly cursorPosition: CursorPosition;
  readonly selection: SelectionRange | null;
  readonly scrollPosition: ScrollPosition;
  readonly isDirty: boolean;
  readonly lastSavedAt: Date | null;
}

interface CursorPosition {
  readonly line: number;
  readonly column: number;
  readonly offset: number; // Character offset from start
}

interface SelectionRange {
  readonly start: CursorPosition;
  readonly end: CursorPosition;
  readonly direction: 'forward' | 'backward';
}

interface ScrollPosition {
  readonly scrollTop: number;
  readonly scrollLeft: number;
  readonly viewportHeight: number;
}

interface UseEditorStateOptions {
  readonly projectId: string;
  readonly filePath: string;
  readonly initialContent: string;
  readonly autoSaveMs?: number;
}

interface UseEditorStateReturn {
  readonly state: EditorState;
  readonly setContent: (content: string) => void;
  readonly setCursor: (position: CursorPosition) => void;
  readonly setSelection: (range: SelectionRange | null) => void;
  readonly setScroll: (position: ScrollPosition) => void;
  readonly save: () => Promise<void>;
  readonly revert: () => void;
  readonly undo: () => void;
  readonly redo: () => void;
  readonly canUndo: boolean;
  readonly canRedo: boolean;
}

export function useEditorState(options: UseEditorStateOptions): UseEditorStateReturn {
  const { projectId, filePath, initialContent, autoSaveMs = 1000 } = options;
  
  const contextId = filePath.replace(/\//g, '_');
  
  // Content persistence
  const {
    value: content,
    setValue: setContent,
    isRestored,
  } = usePersistedInput({
    type: InputType.FileEditor,
    projectId,
    contextId,
    fieldId: 'content',
    debounceMs: autoSaveMs,
  });
  
  // Cursor position persistence
  const {
    value: cursorJson,
    setValue: setCursorJson,
  } = usePersistedInput({
    type: InputType.FileEditor,
    projectId,
    contextId,
    fieldId: 'cursor',
    debounceMs: 100,
  });
  
  // Scroll position persistence
  const {
    value: scrollJson,
    setValue: setScrollJson,
  } = usePersistedInput({
    type: InputType.FileEditor,
    projectId,
    contextId,
    fieldId: 'scroll',
    debounceMs: 200,
  });
  
  // Parse persisted JSON
  const cursorPosition = useMemo<CursorPosition>(() => {
    if (!cursorJson) return { line: 1, column: 1, offset: 0 };
    try {
      return JSON.parse(cursorJson);
    } catch {
      return { line: 1, column: 1, offset: 0 };
    }
  }, [cursorJson]);
  
  const scrollPosition = useMemo<ScrollPosition>(() => {
    if (!scrollJson) return { scrollTop: 0, scrollLeft: 0, viewportHeight: 0 };
    try {
      return JSON.parse(scrollJson);
    } catch {
      return { scrollTop: 0, scrollLeft: 0, viewportHeight: 0 };
    }
  }, [scrollJson]);
  
  // Selection state (not persisted - too volatile)
  const [selection, setSelection] = useState<SelectionRange | null>(null);
  
  // Dirty tracking
  const [savedContent, setSavedContent] = useState(initialContent);
  const isDirty = content !== savedContent;
  const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);
  
  // Undo/redo
  const { history, push, undo: undoHistory, redo: redoHistory, canUndo, canRedo } = 
    useUndoRedo({ maxHistory: 100 });
  
  // Setters
  const setCursor = useCallback((position: CursorPosition) => {
    setCursorJson(JSON.stringify(position));
  }, [setCursorJson]);
  
  const setScroll = useCallback((position: ScrollPosition) => {
    setScrollJson(JSON.stringify(position));
  }, [setScrollJson]);
  
  const handleSetContent = useCallback((newContent: string) => {
    push(content); // Add to undo stack
    setContent(newContent);
  }, [content, push, setContent]);
  
  // Save
  const save = useCallback(async () => {
    await saveFile(projectId, filePath, content);
    setSavedContent(content);
    setLastSavedAt(new Date());
  }, [projectId, filePath, content]);
  
  // Revert
  const revert = useCallback(() => {
    setContent(savedContent);
  }, [savedContent, setContent]);
  
  // Undo/Redo
  const undo = useCallback(() => {
    const previous = undoHistory();
    if (previous !== undefined) {
      setContent(previous);
    }
  }, [undoHistory, setContent]);
  
  const redo = useCallback(() => {
    const next = redoHistory();
    if (next !== undefined) {
      setContent(next);
    }
  }, [redoHistory, setContent]);
  
  const state: EditorState = {
    content: content || initialContent,
    cursorPosition,
    selection,
    scrollPosition,
    isDirty,
    lastSavedAt,
  };
  
  return {
    state,
    setContent: handleSetContent,
    setCursor,
    setSelection,
    setScroll,
    save,
    revert,
    undo,
    redo,
    canUndo,
    canRedo,
  };
}
```

---

## useCursorPosition

Standalone hook for cursor tracking:

```typescript
interface UseCursorPositionOptions {
  readonly editorRef: React.RefObject<HTMLTextAreaElement | HTMLDivElement>;
  readonly onCursorChange?: (position: CursorPosition) => void;
}

interface UseCursorPositionReturn {
  readonly position: CursorPosition;
  readonly setCursor: (position: CursorPosition) => void;
  readonly moveCursor: (direction: 'up' | 'down' | 'left' | 'right', count?: number) => void;
}

export function useCursorPosition(options: UseCursorPositionOptions): UseCursorPositionReturn {
  const { editorRef, onCursorChange } = options;
  const [position, setPosition] = useState<CursorPosition>({
    line: 1,
    column: 1,
    offset: 0,
  });
  
  // Track cursor on input events
  useEffect(() => {
    const editor = editorRef.current;
    if (!editor) return;
    
    const updateCursor = () => {
      const newPosition = getCursorFromElement(editor);
      setPosition(newPosition);
      onCursorChange?.(newPosition);
    };
    
    editor.addEventListener('click', updateCursor);
    editor.addEventListener('keyup', updateCursor);
    editor.addEventListener('input', updateCursor);
    
    return () => {
      editor.removeEventListener('click', updateCursor);
      editor.removeEventListener('keyup', updateCursor);
      editor.removeEventListener('input', updateCursor);
    };
  }, [editorRef, onCursorChange]);
  
  const setCursor = useCallback((newPosition: CursorPosition) => {
    const editor = editorRef.current;
    if (!editor) return;
    
    setCursorToElement(editor, newPosition);
    setPosition(newPosition);
  }, [editorRef]);
  
  const moveCursor = useCallback((
    direction: 'up' | 'down' | 'left' | 'right',
    count = 1
  ) => {
    // Implementation depends on editor type
    const newPosition = calculateNewPosition(position, direction, count);
    setCursor(newPosition);
  }, [position, setCursor]);
  
  return { position, setCursor, moveCursor };
}

// Helpers
function getCursorFromElement(element: HTMLElement): CursorPosition {
  if (element instanceof HTMLTextAreaElement) {
    const offset = element.selectionStart;
    const text = element.value.substring(0, offset);
    const lines = text.split('\n');
    return {
      line: lines.length,
      column: (lines[lines.length - 1]?.length ?? 0) + 1,
      offset,
    };
  }
  
  // For contenteditable
  const selection = window.getSelection();
  if (!selection?.rangeCount) {
    return { line: 1, column: 1, offset: 0 };
  }
  
  const range = selection.getRangeAt(0);
  // Calculate position from range...
  return { line: 1, column: 1, offset: range.startOffset };
}

function setCursorToElement(element: HTMLElement, position: CursorPosition): void {
  if (element instanceof HTMLTextAreaElement) {
    element.setSelectionRange(position.offset, position.offset);
    element.focus();
  }
  // Handle contenteditable...
}
```

---

## useScrollPosition

Preserve and restore scroll position:

```typescript
interface UseScrollPositionOptions {
  readonly containerRef: React.RefObject<HTMLElement>;
  readonly key: string;
  readonly debounceMs?: number;
}

interface UseScrollPositionReturn {
  readonly scrollPosition: ScrollPosition;
  readonly scrollTo: (position: Partial<ScrollPosition>) => void;
  readonly scrollToLine: (lineNumber: number) => void;
}

export function useScrollPosition(options: UseScrollPositionOptions): UseScrollPositionReturn {
  const { containerRef, key, debounceMs = 200 } = options;
  
  const {
    value: scrollJson,
    setValue: setScrollJson,
    isRestored,
  } = usePersistedInput({
    type: InputType.FileEditor,
    projectId: 'global',
    contextId: key,
    fieldId: 'scroll',
    debounceMs,
  });
  
  const scrollPosition = useMemo<ScrollPosition>(() => {
    if (!scrollJson) return { scrollTop: 0, scrollLeft: 0, viewportHeight: 0 };
    try {
      return JSON.parse(scrollJson);
    } catch {
      return { scrollTop: 0, scrollLeft: 0, viewportHeight: 0 };
    }
  }, [scrollJson]);
  
  // Restore scroll position on mount
  useEffect(() => {
    if (isRestored && containerRef.current) {
      containerRef.current.scrollTop = scrollPosition.scrollTop;
      containerRef.current.scrollLeft = scrollPosition.scrollLeft;
    }
  }, [isRestored, containerRef]);
  
  // Track scroll changes
  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;
    
    const handleScroll = () => {
      const newPosition: ScrollPosition = {
        scrollTop: container.scrollTop,
        scrollLeft: container.scrollLeft,
        viewportHeight: container.clientHeight,
      };
      setScrollJson(JSON.stringify(newPosition));
    };
    
    container.addEventListener('scroll', handleScroll, { passive: true });
    return () => container.removeEventListener('scroll', handleScroll);
  }, [containerRef, setScrollJson]);
  
  const scrollTo = useCallback((position: Partial<ScrollPosition>) => {
    const container = containerRef.current;
    if (!container) return;
    
    if (position.scrollTop !== undefined) {
      container.scrollTop = position.scrollTop;
    }
    if (position.scrollLeft !== undefined) {
      container.scrollLeft = position.scrollLeft;
    }
  }, [containerRef]);
  
  const scrollToLine = useCallback((lineNumber: number) => {
    const container = containerRef.current;
    if (!container) return;
    
    const lineHeight = 20; // Approximate
    const targetScroll = (lineNumber - 1) * lineHeight;
    container.scrollTop = targetScroll;
  }, [containerRef]);
  
  return { scrollPosition, scrollTo, scrollToLine };
}
```

---

## useUndoRedo

Local undo/redo with configurable history:

```typescript
interface UseUndoRedoOptions<T> {
  readonly maxHistory?: number;
  readonly initialValue?: T;
}

interface UseUndoRedoReturn<T> {
  readonly history: readonly T[];
  readonly push: (value: T) => void;
  readonly undo: () => T | undefined;
  readonly redo: () => T | undefined;
  readonly clear: () => void;
  readonly canUndo: boolean;
  readonly canRedo: boolean;
}

export function useUndoRedo<T>(options: UseUndoRedoOptions<T> = {}): UseUndoRedoReturn<T> {
  const { maxHistory = 50, initialValue } = options;
  
  const [history, setHistory] = useState<T[]>(
    initialValue !== undefined ? [initialValue] : []
  );
  const [currentIndex, setCurrentIndex] = useState(
    initialValue !== undefined ? 0 : -1
  );
  
  const push = useCallback((value: T) => {
    setHistory(prev => {
      // Remove any "redo" history
      const newHistory = prev.slice(0, currentIndex + 1);
      newHistory.push(value);
      
      // Limit history size
      if (newHistory.length > maxHistory) {
        newHistory.shift();
        return newHistory;
      }
      
      return newHistory;
    });
    setCurrentIndex(prev => Math.min(prev + 1, maxHistory - 1));
  }, [currentIndex, maxHistory]);
  
  const undo = useCallback((): T | undefined => {
    if (currentIndex <= 0) return undefined;
    
    const newIndex = currentIndex - 1;
    setCurrentIndex(newIndex);
    return history[newIndex];
  }, [currentIndex, history]);
  
  const redo = useCallback((): T | undefined => {
    if (currentIndex >= history.length - 1) return undefined;
    
    const newIndex = currentIndex + 1;
    setCurrentIndex(newIndex);
    return history[newIndex];
  }, [currentIndex, history]);
  
  const clear = useCallback(() => {
    setHistory([]);
    setCurrentIndex(-1);
  }, []);
  
  return {
    history,
    push,
    undo,
    redo,
    clear,
    canUndo: currentIndex > 0,
    canRedo: currentIndex < history.length - 1,
  };
}
```

---

## Integration Example

```tsx
import { useRef } from 'react';
import { useEditorState } from '@/hooks/use-editor-state';
import { useCursorPosition } from '@/hooks/use-cursor-position';
import { useScrollPosition } from '@/hooks/use-scroll-position';

export function MarkdownEditor({ projectId, filePath, initialContent }: EditorProps) {
  const editorRef = useRef<HTMLTextAreaElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  
  const {
    state,
    setContent,
    setCursor,
    setScroll,
    save,
    undo,
    redo,
    canUndo,
    canRedo,
  } = useEditorState({
    projectId,
    filePath,
    initialContent,
  });
  
  const { position } = useCursorPosition({
    editorRef,
    onCursorChange: setCursor,
  });
  
  const { scrollToLine } = useScrollPosition({
    containerRef,
    key: `${projectId}_${filePath}`,
  });
  
  // Keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        save();
      }
      if ((e.metaKey || e.ctrlKey) && e.key === 'z') {
        e.preventDefault();
        if (e.shiftKey) {
          redo();
        } else {
          undo();
        }
      }
    };
    
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [save, undo, redo]);
  
  return (
    <div ref={containerRef} className="h-full overflow-auto">
      <div className="flex items-center justify-between p-2 border-b">
        <span className="text-sm text-muted-foreground">
          Line {position.line}, Column {position.column}
        </span>
        <div className="flex gap-1">
          <Button size="sm" variant="ghost" onClick={undo} disabled={!canUndo}>
            Undo
          </Button>
          <Button size="sm" variant="ghost" onClick={redo} disabled={!canRedo}>
            Redo
          </Button>
          <Button size="sm" onClick={save} disabled={!state.isDirty}>
            Save
          </Button>
        </div>
      </div>
      <textarea
        ref={editorRef}
        value={state.content}
        onChange={(e) => setContent(e.target.value)}
        className="w-full h-full p-4 font-mono resize-none focus:outline-none"
      />
    </div>
  );
}
```

---

## Acceptance Criteria

| ID | Criterion | Priority | Validation |
|----|-----------|----------|------------|
| ESH-01 | Content persists across sessions | MUST | E2E test |
| ESH-02 | Cursor position restored on reload | MUST | E2E test |
| ESH-03 | Scroll position preserved | SHOULD | E2E test |
| ESH-04 | Undo/redo stack works correctly | MUST | Unit test |
| ESH-05 | Dirty state tracks unsaved changes | MUST | Unit test |
| ESH-06 | Keyboard shortcuts (Cmd+S, Cmd+Z) | MUST | E2E test |
| ESH-07 | Line/column display updates live | SHOULD | E2E test |
| ESH-08 | Selection state not over-persisted | SHOULD | Unit test |

---

## Related Specs

- [Input State Persistence](./06-input-state-persistence.md) — Storage layer
- [Draft Recovery UI](./01-draft-recovery-ui.md) — Recovery interface
- [Spec Editor](../04-spec-editor/01-markdown-editor.md) — Editor integration
