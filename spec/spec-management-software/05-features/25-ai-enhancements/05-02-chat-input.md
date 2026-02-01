# Phase 5.2: Chat Input & Plus Menu

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Chat UI Redesign](./05-chat-ui-redesign.md)

---

## Overview

Lovable-style input area with expandable textarea, plus (+) action menu, voice input toggle, reference attachments, and draft persistence.

---

## 1. Plus Menu Architecture

### 1.1 Menu Categories

```
┌─────────────────────────────────────────┐
│  + Menu                                 │
├─────────────────────────────────────────┤
│  ATTACH                                 │
│  ├─ 📷 Screenshot    Capture area       │
│  ├─ 📎 Upload File   Images, docs       │
│  └─ 🔗 Add URL       Web reference      │
├─────────────────────────────────────────┤
│  REFERENCE                              │
│  ├─ 📄 Spec File     From project       │
│  ├─ 📁 Project       Cross-project      │
│  └─ 🧠 Knowledge     From memory        │
├─────────────────────────────────────────┤
│  MEMORY                              ▶  │
│  │  ├─ ➕ Add Memory                    │
│  │  ├─ ➖ Remove Memory                 │
│  │  └─ 📤 Share Memory                  │
├─────────────────────────────────────────┤
│  CONNECTORS                             │
│  ├─ <GitHub> GitHub   Browse repos      │
│  ├─ <Figma> Figma     Design files      │
│  └─ <Notion> Notion   Documents         │
└─────────────────────────────────────────┘
```

### 1.2 Menu Component Implementation

```typescript
// components/ai/input/PlusMenu.tsx

import { useState } from 'react';
import {
  Camera, Paperclip, Link, FileText, FolderOpen,
  Brain, Plus, Minus, Share2, Github, Figma,
  ExternalLink, Loader2
} from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  DropdownMenuSub,
  DropdownMenuSubTrigger,
  DropdownMenuSubContent,
  DropdownMenuShortcut,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface MenuAction {
  id: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  shortcut?: string;
  disabled?: boolean;
  loading?: boolean;
  onClick: () => void | Promise<void>;
}

interface MenuCategory {
  label: string;
  actions: MenuAction[];
}

interface PlusMenuProps {
  categories: MenuCategory[];
  subMenus?: {
    triggerId: string;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    actions: MenuAction[];
  }[];
  disabled?: boolean;
  className?: string;
}

export function PlusMenu({
  categories,
  subMenus = [],
  disabled,
  className,
}: PlusMenuProps) {
  const [open, setOpen] = useState(false);
  const [loadingAction, setLoadingAction] = useState<string | null>(null);
  
  const handleAction = async (action: MenuAction) => {
    if (action.disabled || action.loading) return;
    
    setLoadingAction(action.id);
    try {
      await action.onClick();
    } finally {
      setLoadingAction(null);
      setOpen(false);
    }
  };
  
  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="icon"
          disabled={disabled}
          className={cn(
            'h-10 w-10 rounded-xl transition-all',
            'hover:bg-primary hover:text-primary-foreground',
            'focus-visible:ring-2 focus-visible:ring-primary',
            open && 'bg-primary text-primary-foreground rotate-45',
            className
          )}
        >
          <Plus className="h-5 w-5 transition-transform" />
        </Button>
      </DropdownMenuTrigger>
      
      <DropdownMenuContent
        align="start"
        side="top"
        className="w-64 max-h-[70vh] overflow-auto"
        sideOffset={8}
      >
        {categories.map((category, categoryIndex) => (
          <div key={category.label}>
            {categoryIndex > 0 && <DropdownMenuSeparator />}
            <DropdownMenuLabel className="text-xs text-muted-foreground uppercase tracking-wider">
              {category.label}
            </DropdownMenuLabel>
            
            {category.actions.map((action) => {
              // Check if this action should render as a submenu
              const subMenu = subMenus.find(s => s.triggerId === action.id);
              
              if (subMenu) {
                return (
                  <DropdownMenuSub key={action.id}>
                    <DropdownMenuSubTrigger disabled={action.disabled}>
                      <action.icon className="h-4 w-4 mr-2" />
                      {action.label}
                    </DropdownMenuSubTrigger>
                    <DropdownMenuSubContent>
                      {subMenu.actions.map((subAction) => (
                        <DropdownMenuItem
                          key={subAction.id}
                          onClick={() => handleAction(subAction)}
                          disabled={subAction.disabled}
                        >
                          {loadingAction === subAction.id ? (
                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                          ) : (
                            <subAction.icon className="h-4 w-4 mr-2" />
                          )}
                          {subAction.label}
                        </DropdownMenuItem>
                      ))}
                    </DropdownMenuSubContent>
                  </DropdownMenuSub>
                );
              }
              
              return (
                <DropdownMenuItem
                  key={action.id}
                  onClick={() => handleAction(action)}
                  disabled={action.disabled}
                  className="cursor-pointer"
                >
                  {loadingAction === action.id ? (
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  ) : (
                    <action.icon className="h-4 w-4 mr-2" />
                  )}
                  <span className="flex-1">{action.label}</span>
                  {action.shortcut && (
                    <DropdownMenuShortcut>{action.shortcut}</DropdownMenuShortcut>
                  )}
                </DropdownMenuItem>
              );
            })}
          </div>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
```

### 1.3 Plus Menu Hook

```typescript
// hooks/usePlusMenuActions.ts

import { useCallback, useMemo } from 'react';
import { Camera, Paperclip, Link, FileText, FolderOpen, Brain, Plus, Minus, Share2, Github } from 'lucide-react';
import { useScreenCapture } from '@/hooks/useScreenCapture';
import { useFileUpload } from '@/hooks/useFileUpload';
import { useUrlDialog } from '@/hooks/useUrlDialog';
import { useSpecPicker } from '@/hooks/useSpecPicker';
import { useProjectPicker } from '@/hooks/useProjectPicker';
import { useMemoryPicker } from '@/hooks/useMemoryPicker';
import { useMemoryManager } from '@/hooks/useMemoryManager';
import { useGitHubConnector } from '@/hooks/useGitHubConnector';

export interface Reference {
  type: 'file' | 'url' | 'memory' | 'project' | 'screenshot';
  id: string;
  name: string;
  path?: string;
  content?: string;
  mimeType?: string;
}

interface UsePlusMenuActionsOptions {
  projectId: string;
  sessionId: string;
  onReferenceAdd: (ref: Reference) => void;
  currentContext?: string;
}

export function usePlusMenuActions({
  projectId,
  sessionId,
  onReferenceAdd,
  currentContext,
}: UsePlusMenuActionsOptions) {
  const screenCapture = useScreenCapture();
  const fileUpload = useFileUpload();
  const urlDialog = useUrlDialog();
  const specPicker = useSpecPicker(projectId);
  const projectPicker = useProjectPicker();
  const memoryPicker = useMemoryPicker(projectId);
  const memoryManager = useMemoryManager(projectId);
  const github = useGitHubConnector();
  
  // Screenshot handler
  const handleScreenshot = useCallback(async () => {
    const screenshot = await screenCapture.capture();
    if (screenshot) {
      onReferenceAdd({
        type: 'screenshot',
        id: `screenshot-${Date.now()}`,
        name: 'Screenshot',
        content: screenshot.dataUrl,
        mimeType: 'image/png',
      });
    }
  }, [screenCapture, onReferenceAdd]);
  
  // File upload handler
  const handleFileUpload = useCallback(async () => {
    const files = await fileUpload.pick({
      accept: ['image/*', '.md', '.txt', '.json', '.yaml', '.yml'],
      multiple: true,
    });
    
    for (const file of files) {
      onReferenceAdd({
        type: 'file',
        id: `file-${Date.now()}-${file.name}`,
        name: file.name,
        content: file.content,
        mimeType: file.type,
      });
    }
  }, [fileUpload, onReferenceAdd]);
  
  // URL handler
  const handleUrlAdd = useCallback(async () => {
    const url = await urlDialog.open();
    if (url) {
      onReferenceAdd({
        type: 'url',
        id: `url-${Date.now()}`,
        name: url.title || new URL(url.href).hostname,
        path: url.href,
      });
    }
  }, [urlDialog, onReferenceAdd]);
  
  // Spec file reference
  const handleSpecReference = useCallback(async () => {
    const spec = await specPicker.pick();
    if (spec) {
      onReferenceAdd({
        type: 'file',
        id: `spec-${spec.path}`,
        name: spec.name,
        path: spec.path,
        content: spec.content,
      });
    }
  }, [specPicker, onReferenceAdd]);
  
  // Project reference
  const handleProjectReference = useCallback(async () => {
    const project = await projectPicker.pick();
    if (project) {
      onReferenceAdd({
        type: 'project',
        id: `project-${project.id}`,
        name: project.name,
        path: project.id,
      });
    }
  }, [projectPicker, onReferenceAdd]);
  
  // Memory reference
  const handleMemoryReference = useCallback(async () => {
    const memory = await memoryPicker.pick();
    if (memory) {
      onReferenceAdd({
        type: 'memory',
        id: `memory-${memory.id}`,
        name: memory.title,
        content: memory.content,
      });
    }
  }, [memoryPicker, onReferenceAdd]);
  
  // Memory management
  const handleMemoryAdd = useCallback(async () => {
    if (currentContext) {
      await memoryManager.add({
        title: `Context from session ${sessionId}`,
        content: currentContext,
        source: 'chat',
      });
    }
  }, [memoryManager, currentContext, sessionId]);
  
  const handleMemoryRemove = useCallback(async () => {
    await memoryManager.openRemoveDialog();
  }, [memoryManager]);
  
  const handleMemoryShare = useCallback(async () => {
    await memoryManager.openShareDialog();
  }, [memoryManager]);
  
  // GitHub
  const handleGitHubConnect = useCallback(async () => {
    const file = await github.browse();
    if (file) {
      onReferenceAdd({
        type: 'file',
        id: `github-${file.path}`,
        name: file.name,
        path: file.url,
        content: file.content,
      });
    }
  }, [github, onReferenceAdd]);
  
  // Build menu structure
  const menuConfig = useMemo(() => ({
    categories: [
      {
        label: 'Attach',
        actions: [
          { id: 'screenshot', label: 'Screenshot', icon: Camera, shortcut: '⌘⇧S', onClick: handleScreenshot },
          { id: 'file', label: 'Upload File', icon: Paperclip, onClick: handleFileUpload },
          { id: 'url', label: 'Add URL', icon: Link, onClick: handleUrlAdd },
        ],
      },
      {
        label: 'Reference',
        actions: [
          { id: 'spec', label: 'Spec File', icon: FileText, onClick: handleSpecReference },
          { id: 'project', label: 'Another Project', icon: FolderOpen, onClick: handleProjectReference },
          { id: 'memory', label: 'From Knowledge', icon: Brain, onClick: handleMemoryReference },
        ],
      },
      {
        label: 'Memory',
        actions: [
          { id: 'memory-submenu', label: 'Memory', icon: Brain, onClick: () => {} },
        ],
      },
      {
        label: 'Connectors',
        actions: [
          { id: 'github', label: 'GitHub', icon: Github, onClick: handleGitHubConnect, disabled: !github.isConnected },
        ],
      },
    ],
    subMenus: [
      {
        triggerId: 'memory-submenu',
        label: 'Memory',
        icon: Brain,
        actions: [
          { id: 'memory-add', label: 'Add to Memory', icon: Plus, onClick: handleMemoryAdd, disabled: !currentContext },
          { id: 'memory-remove', label: 'Remove Memory', icon: Minus, onClick: handleMemoryRemove },
          { id: 'memory-share', label: 'Share Memory', icon: Share2, onClick: handleMemoryShare },
        ],
      },
    ],
  }), [
    handleScreenshot,
    handleFileUpload,
    handleUrlAdd,
    handleSpecReference,
    handleProjectReference,
    handleMemoryReference,
    handleMemoryAdd,
    handleMemoryRemove,
    handleMemoryShare,
    handleGitHubConnect,
    github.isConnected,
    currentContext,
  ]);
  
  return menuConfig;
}
```

---

## 2. Input Component

### 2.1 Expandable Textarea

```typescript
// components/ai/input/ChatInput.tsx

import { useState, useRef, useCallback, useEffect, KeyboardEvent } from 'react';
import { Mic, MicOff, Send, Loader2, X, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { PlusMenu } from './PlusMenu';
import { VoiceInput } from './VoiceInput';
import { usePlusMenuActions, Reference } from '@/hooks/usePlusMenuActions';
import { useChatDraft } from '@/hooks/useChatDraft';
import { cn } from '@/lib/utils';

interface ChatInputProps {
  projectId: string;
  sessionId: string;
  mode: 'spec' | 'coding' | 'plan';
  onSend: (message: string, references: Reference[]) => Promise<void>;
  disabled?: boolean;
  placeholder?: string;
}

export function ChatInput({
  projectId,
  sessionId,
  mode,
  onSend,
  disabled,
  placeholder = 'Message AI...',
}: ChatInputProps) {
  // Draft persistence
  const { draft, updateDraft, clearDraft } = useChatDraft(sessionId);
  
  // Local state
  const [references, setReferences] = useState<Reference[]>([]);
  const [isVoiceMode, setIsVoiceMode] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const formRef = useRef<HTMLFormElement>(null);
  
  // Plus menu actions
  const plusMenu = usePlusMenuActions({
    projectId,
    sessionId,
    onReferenceAdd: (ref) => setReferences(prev => [...prev, ref]),
    currentContext: draft.text,
  });
  
  // Auto-resize textarea
  const adjustTextareaHeight = useCallback(() => {
    const textarea = textareaRef.current;
    if (!textarea) return;
    
    textarea.style.height = 'auto';
    const newHeight = Math.min(Math.max(textarea.scrollHeight, 44), 200);
    textarea.style.height = `${newHeight}px`;
  }, []);
  
  useEffect(() => {
    adjustTextareaHeight();
  }, [draft.text, adjustTextareaHeight]);
  
  // Handle text change
  const handleTextChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    updateDraft({ text: e.target.value });
    setError(null);
  }, [updateDraft]);
  
  // Handle send
  const handleSend = useCallback(async () => {
    const text = draft.text.trim();
    if (!text && references.length === 0) return;
    if (isSending) return;
    
    setIsSending(true);
    setError(null);
    
    try {
      await onSend(text, references);
      clearDraft();
      setReferences([]);
      
      if (textareaRef.current) {
        textareaRef.current.style.height = 'auto';
        textareaRef.current.focus();
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to send message');
    } finally {
      setIsSending(false);
    }
  }, [draft.text, references, isSending, onSend, clearDraft]);
  
  // Keyboard handling
  const handleKeyDown = useCallback((e: KeyboardEvent<HTMLTextAreaElement>) => {
    // Enter to send (without shift)
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
      return;
    }
    
    // Cmd/Ctrl + Enter for execute in coding mode
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey) && mode === 'coding') {
      e.preventDefault();
      // TODO: Send with auto-execute flag
      handleSend();
      return;
    }
  }, [handleSend, mode]);
  
  // Voice transcription complete
  const handleVoiceComplete = useCallback((text: string) => {
    const separator = draft.text ? ' ' : '';
    updateDraft({ text: draft.text + separator + text });
    setIsVoiceMode(false);
    textareaRef.current?.focus();
  }, [draft.text, updateDraft]);
  
  // Remove reference
  const removeReference = useCallback((id: string) => {
    setReferences(prev => prev.filter(r => r.id !== id));
  }, []);
  
  // Reference icon mapping
  const getReferenceIcon = (type: Reference['type']) => {
    const icons: Record<string, string> = {
      file: '📄',
      url: '🔗',
      memory: '🧠',
      project: '📁',
      screenshot: '📷',
    };
    return icons[type] || '📎';
  };
  
  const canSend = (draft.text.trim() || references.length > 0) && !disabled && !isSending;
  
  return (
    <form
      ref={formRef}
      onSubmit={(e) => { e.preventDefault(); handleSend(); }}
      className="px-4 py-3"
    >
      {/* Error display */}
      {error && (
        <div className="flex items-center gap-2 mb-2 p-2 bg-destructive/10 text-destructive rounded-lg text-sm">
          <AlertCircle className="h-4 w-4 flex-shrink-0" />
          <span>{error}</span>
          <button
            type="button"
            onClick={() => setError(null)}
            className="ml-auto hover:bg-destructive/20 rounded p-0.5"
          >
            <X className="h-3 w-3" />
          </button>
        </div>
      )}
      
      {/* References bar */}
      {references.length > 0 && (
        <div className="flex flex-wrap gap-2 mb-3">
          {references.map((ref) => (
            <Badge
              key={ref.id}
              variant="secondary"
              className="gap-1.5 pr-1 max-w-48"
            >
              <span>{getReferenceIcon(ref.type)}</span>
              <span className="truncate">{ref.name}</span>
              <button
                type="button"
                onClick={() => removeReference(ref.id)}
                className="ml-1 hover:bg-muted rounded-full p-0.5 transition-colors"
              >
                <X className="h-3 w-3" />
              </button>
            </Badge>
          ))}
        </div>
      )}
      
      {/* Input row */}
      <div className="flex items-end gap-2">
        {/* Plus menu */}
        <PlusMenu
          categories={plusMenu.categories}
          subMenus={plusMenu.subMenus}
          disabled={disabled || isSending}
        />
        
        {/* Text input or voice */}
        <div className="flex-1 relative">
          {isVoiceMode ? (
            <div className="flex items-center justify-center py-3 px-4 bg-muted/50 rounded-xl min-h-[44px]">
              <VoiceInput
                onComplete={handleVoiceComplete}
                onCancel={() => setIsVoiceMode(false)}
              />
            </div>
          ) : (
            <div className="relative">
              <Textarea
                ref={textareaRef}
                value={draft.text}
                onChange={handleTextChange}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                disabled={disabled || isSending}
                className={cn(
                  'min-h-[44px] max-h-[200px] resize-none pr-12',
                  'rounded-xl border-muted-foreground/20',
                  'focus-visible:ring-1 focus-visible:ring-primary',
                  'transition-all'
                )}
                rows={1}
              />
              
              {/* Voice button inside textarea */}
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="absolute right-1 bottom-1 h-8 w-8"
                    onClick={() => setIsVoiceMode(true)}
                    disabled={disabled || isSending}
                  >
                    <Mic className="h-4 w-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Voice input (V)</TooltipContent>
              </Tooltip>
            </div>
          )}
        </div>
        
        {/* Send button */}
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="submit"
              size="icon"
              disabled={!canSend}
              className={cn(
                'h-10 w-10 rounded-xl transition-all',
                canSend && 'bg-primary hover:bg-primary/90 shadow-lg'
              )}
            >
              {isSending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Send className="h-4 w-4" />
              )}
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            {mode === 'coding' ? 'Send (⌘↵ to execute)' : 'Send (↵)'}
          </TooltipContent>
        </Tooltip>
      </div>
      
      {/* Mode hint */}
      <div className="flex justify-between items-center mt-2 px-1 text-xs text-muted-foreground">
        <span>
          {mode === 'spec' && 'Spec Mode: Draft specifications'}
          {mode === 'coding' && 'Coding Mode: Generate and run code'}
          {mode === 'plan' && 'Plan Mode: Create execution plans'}
        </span>
        <span className="hidden sm:inline">
          Shift+Enter for new line
        </span>
      </div>
    </form>
  );
}
```

---

## 3. Voice Input Component

```typescript
// components/ai/input/VoiceInput.tsx

import { useState, useEffect, useCallback } from 'react';
import { Mic, MicOff, Loader2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { useVoiceRecording } from '@/hooks/useVoiceRecording';
import { cn } from '@/lib/utils';

interface VoiceInputProps {
  onComplete: (text: string) => void;
  onCancel: () => void;
  maxDuration?: number; // seconds
}

export function VoiceInput({
  onComplete,
  onCancel,
  maxDuration = 60,
}: VoiceInputProps) {
  const {
    isRecording,
    isTranscribing,
    duration,
    audioLevel,
    error,
    startRecording,
    stopRecording,
    cancelRecording,
  } = useVoiceRecording({
    maxDuration,
    onTranscriptionComplete: onComplete,
  });
  
  // Auto-start recording
  useEffect(() => {
    startRecording();
    return () => cancelRecording();
  }, []);
  
  // Handle stop
  const handleStop = useCallback(async () => {
    await stopRecording();
  }, [stopRecording]);
  
  // Handle cancel
  const handleCancel = useCallback(() => {
    cancelRecording();
    onCancel();
  }, [cancelRecording, onCancel]);
  
  // Format duration
  const formatDuration = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };
  
  if (error) {
    return (
      <div className="flex items-center gap-3 text-destructive">
        <MicOff className="h-5 w-5" />
        <span className="text-sm">{error}</span>
        <Button variant="ghost" size="sm" onClick={handleCancel}>
          Dismiss
        </Button>
      </div>
    );
  }
  
  if (isTranscribing) {
    return (
      <div className="flex items-center gap-3">
        <Loader2 className="h-5 w-5 animate-spin text-primary" />
        <span className="text-sm">Transcribing...</span>
      </div>
    );
  }
  
  return (
    <div className="flex items-center gap-4 w-full">
      {/* Waveform visualization */}
      <div className="flex items-center gap-1 h-8">
        {Array.from({ length: 5 }).map((_, i) => (
          <div
            key={i}
            className="w-1 bg-primary rounded-full transition-all duration-75"
            style={{
              height: `${Math.max(4, audioLevel * (20 + i * 5))}px`,
              opacity: isRecording ? 1 : 0.3,
            }}
          />
        ))}
      </div>
      
      {/* Duration */}
      <div className="flex-1">
        <div className="flex items-center justify-between mb-1">
          <span className="text-sm font-medium">
            {formatDuration(duration)}
          </span>
          <span className="text-xs text-muted-foreground">
            / {formatDuration(maxDuration)}
          </span>
        </div>
        <Progress value={(duration / maxDuration) * 100} className="h-1" />
      </div>
      
      {/* Controls */}
      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="icon"
          onClick={handleCancel}
          className="h-8 w-8"
        >
          <X className="h-4 w-4" />
        </Button>
        
        <Button
          variant="default"
          size="sm"
          onClick={handleStop}
          className={cn(
            'gap-2',
            isRecording && 'animate-pulse'
          )}
        >
          <Mic className="h-4 w-4" />
          Stop
        </Button>
      </div>
    </div>
  );
}
```

---

## 4. Draft Persistence Hook

```typescript
// hooks/useChatDraft.ts

import { useState, useEffect, useCallback, useRef } from 'react';
import { debounce } from '@/lib/utils';

interface ChatDraft {
  text: string;
  references: string[]; // Reference IDs
  updatedAt: number;
}

const STORAGE_KEY = 'specmgmt_v1_chat_drafts';
const DEBOUNCE_MS = 300;

export function useChatDraft(sessionId: string) {
  const [draft, setDraft] = useState<ChatDraft>({
    text: '',
    references: [],
    updatedAt: Date.now(),
  });
  
  // Load draft on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const drafts = JSON.parse(stored) as Record<string, ChatDraft>;
        if (drafts[sessionId]) {
          setDraft(drafts[sessionId]);
        }
      }
    } catch (e) {
      console.error('Failed to load draft:', e);
    }
  }, [sessionId]);
  
  // Debounced save
  const saveDraft = useRef(
    debounce((sessionId: string, draft: ChatDraft) => {
      try {
        const stored = localStorage.getItem(STORAGE_KEY);
        const drafts = stored ? JSON.parse(stored) : {};
        drafts[sessionId] = draft;
        
        // Cleanup old drafts (keep last 50)
        const keys = Object.keys(drafts);
        if (keys.length > 50) {
          const sorted = keys.sort((a, b) => 
            (drafts[b].updatedAt || 0) - (drafts[a].updatedAt || 0)
          );
          for (const key of sorted.slice(50)) {
            delete drafts[key];
          }
        }
        
        localStorage.setItem(STORAGE_KEY, JSON.stringify(drafts));
      } catch (e) {
        console.error('Failed to save draft:', e);
      }
    }, DEBOUNCE_MS)
  ).current;
  
  // Update draft
  const updateDraft = useCallback((partial: Partial<ChatDraft>) => {
    setDraft(prev => {
      const updated = { ...prev, ...partial, updatedAt: Date.now() };
      saveDraft(sessionId, updated);
      return updated;
    });
  }, [sessionId, saveDraft]);
  
  // Clear draft
  const clearDraft = useCallback(() => {
    setDraft({ text: '', references: [], updatedAt: Date.now() });
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const drafts = JSON.parse(stored);
        delete drafts[sessionId];
        localStorage.setItem(STORAGE_KEY, JSON.stringify(drafts));
      }
    } catch (e) {
      console.error('Failed to clear draft:', e);
    }
  }, [sessionId]);
  
  return { draft, updateDraft, clearDraft };
}
```

---

## 5. File Upload Handler

```typescript
// hooks/useFileUpload.ts

import { useCallback, useRef } from 'react';

interface UploadedFile {
  name: string;
  type: string;
  size: number;
  content: string; // Base64 or text content
}

interface PickOptions {
  accept?: string[];
  multiple?: boolean;
  maxSize?: number; // bytes
}

export function useFileUpload() {
  const inputRef = useRef<HTMLInputElement | null>(null);
  
  const pick = useCallback(async (options: PickOptions = {}): Promise<UploadedFile[]> => {
    return new Promise((resolve) => {
      // Create hidden input
      if (!inputRef.current) {
        inputRef.current = document.createElement('input');
        inputRef.current.type = 'file';
        inputRef.current.style.display = 'none';
        document.body.appendChild(inputRef.current);
      }
      
      const input = inputRef.current;
      input.accept = options.accept?.join(',') || '*';
      input.multiple = options.multiple ?? false;
      
      input.onchange = async (e) => {
        const files = Array.from((e.target as HTMLInputElement).files || []);
        const results: UploadedFile[] = [];
        
        for (const file of files) {
          // Check size
          if (options.maxSize && file.size > options.maxSize) {
            console.warn(`File ${file.name} exceeds max size`);
            continue;
          }
          
          // Read content
          const content = await readFile(file);
          results.push({
            name: file.name,
            type: file.type,
            size: file.size,
            content,
          });
        }
        
        // Reset input
        input.value = '';
        resolve(results);
      };
      
      input.click();
    });
  }, []);
  
  return { pick };
}

async function readFile(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    
    reader.onload = () => {
      if (file.type.startsWith('text/') || 
          file.type === 'application/json' ||
          file.name.endsWith('.md') ||
          file.name.endsWith('.yaml') ||
          file.name.endsWith('.yml')) {
        // Return as text
        resolve(reader.result as string);
      } else {
        // Return as base64
        resolve(reader.result as string);
      }
    };
    
    reader.onerror = () => reject(reader.error);
    
    if (file.type.startsWith('text/') || 
        file.type === 'application/json' ||
        file.name.endsWith('.md')) {
      reader.readAsText(file);
    } else {
      reader.readAsDataURL(file);
    }
  });
}
```

---

## 6. Testing Requirements

| Test | Description | Priority |
|------|-------------|----------|
| Plus menu opens | All categories render correctly | Critical |
| File upload | Files are attached and displayed | Critical |
| Voice recording | Records and transcribes audio | Critical |
| Draft persistence | Drafts survive page reload | High |
| Keyboard shortcuts | Enter sends, Shift+Enter newlines | High |
| Reference display | Badges show and can be removed | High |
| Error handling | Errors display and can be dismissed | Medium |
| Mobile layout | Input adapts to small screens | Medium |

---

## Related Specs

- [Chat Layout](./05-01-chat-layout.md)
- [Voice Resilience](./02-voice-resilience.md)
- [Message Display](./05-03-message-display.md)
