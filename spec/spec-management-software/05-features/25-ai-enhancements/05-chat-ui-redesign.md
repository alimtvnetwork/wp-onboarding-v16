# Phase 5: Chat UI Redesign

**Version:** 1.1.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Lovable-style chat interface with plus (+) menu for actions, history panel, knowledge/memory management, connectors, attachments, and URL references.

**Cross-References:**
- [AI Chat UI](../06-ai-integration/08-ai-chat-ui.md)
- [Knowledge Memory](../09-knowledge-memory/00-overview.md)
- [Cross-Project Memory](./06-cross-project-memory.md)

---

## Sub-Specifications

| Spec | Description |
|------|-------------|
| [05-01-chat-layout.md](./05-01-chat-layout.md) | Responsive layout, panels, keyboard navigation |
| [05-02-chat-input.md](./05-02-chat-input.md) | Plus menu, input component, voice input, draft persistence |
| [05-03-message-display.md](./05-03-message-display.md) | Message bubbles, streaming, code blocks, execution status |
| [05-04-mode-selector.md](./05-04-mode-selector.md) | Mode switching, mode-specific UI and behavior |

---

## Architecture Summary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           CHAT UI ARCHITECTURE                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  ChatLayout (05-01)                                                  │   │
│  │  ┌──────────────┐  ┌──────────────────────────────────────────────┐ │   │
│  │  │ HistorySidebar│  │  ChatContainer                              │ │   │
│  │  │ (collapsible) │  │  ┌──────────────────────────────────────┐   │ │   │
│  │  │               │  │  │  Header + ModeSelector (05-04)       │   │ │   │
│  │  │  Sessions     │  │  └──────────────────────────────────────┘   │ │   │
│  │  │  Knowledge    │  │  ┌──────────────────────────────────────┐   │ │   │
│  │  │  Connectors   │  │  │  MessageList (05-03)                 │   │ │   │
│  │  │  Settings     │  │  │  - MessageBubble                     │   │ │   │
│  │  │               │  │  │  - MessageContent                    │   │ │   │
│  │  │               │  │  │  - CodeBlock                         │   │ │   │
│  │  │               │  │  │  - ExecutionStatus                   │   │ │   │
│  │  │               │  │  │  - TypingIndicator                   │   │ │   │
│  │  │               │  │  └──────────────────────────────────────┘   │ │   │
│  │  │               │  │  ┌──────────────────────────────────────┐   │ │   │
│  │  │               │  │  │  ChatInput (05-02)                   │   │ │   │
│  │  │               │  │  │  [+] │ Input area         [🎤] [➤]  │   │ │   │
│  │  │               │  │  │  References: [📄] [🔗]               │   │ │   │
│  │  └──────────────┘  │  └──────────────────────────────────────┘   │ │   │
│  │                    └──────────────────────────────────────────────┘ │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Key Features

### Plus (+) Menu
- **Attach**: Screenshot, file upload, URL reference
- **Reference**: Spec files, projects, knowledge base
- **Memory**: Add/remove/share memories
- **Connectors**: GitHub, Figma, Notion integration

### Mode Selector
- **Spec Mode**: Draft specifications with Markdown output
- **Coding Mode**: Generate code with Run button and brun presets
- **Plan Mode**: Create execution plans with Mermaid diagrams

### Message Streaming
- Token-by-token display with blinking cursor
- Markdown rendering with syntax highlighting
- Mermaid diagram embedding
- File diff visualization

### Draft Persistence
- Auto-save drafts to localStorage
- Restore on page reload
- Debounced saving (300ms)

---

## 5.1 Layout Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ┌──────────────────┐  ┌──────────────────────────────────────────────────┐ │
│  │    History       │  │  AI Chat                            [Mode ▼] [⚙] │ │
│  │  ──────────────  │  ├──────────────────────────────────────────────────┤ │
│  │  ▶ Today         │  │                                                  │ │
│  │    └ Session 1   │  │  ┌────────────────────────────────────────────┐  │ │
│  │    └ Session 2   │  │  │  🤖 I'll help you create a new spec...     │  │ │
│  │  ▶ Yesterday     │  │  └────────────────────────────────────────────┘  │ │
│  │    └ Session 3   │  │                                                  │ │
│  │  ▶ This Week     │  │  ┌────────────────────────────────────────────┐  │ │
│  │                  │  │  │  👤 Create an API specification for...     │  │ │
│  │  ──────────────  │  │  └────────────────────────────────────────────┘  │ │
│  │  📚 Knowledge    │  │                                                  │ │
│  │  🔗 Connectors   │  │  ┌────────────────────────────────────────────┐  │ │
│  │  ⚙  Settings     │  │  │  🤖 Here's my plan for the API:            │  │ │
│  │                  │  │  │                                            │  │ │
│  └──────────────────┘  │  │  ```mermaid                                │  │ │
│                        │  │  flowchart TD                              │  │ │
│                        │  │    A[Parse Request] --> B[Validate]        │  │ │
│                        │  │  ```                                       │  │ │
│                        │  └────────────────────────────────────────────┘  │ │
│                        │                                                  │ │
│                        ├──────────────────────────────────────────────────┤ │
│                        │  ┌──┐ ┌────────────────────────────────────────┐ │ │
│                        │  │+ │ │ Message AI...                     [🎤]│ │ │
│                        │  └──┘ └────────────────────────────────────────┘ │ │
│                        │                                                  │ │
│                        │  References: [📄 auth-spec.md] [🔗 API Docs]     │ │
│                        └──────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 5.2 Plus Menu Actions

### Menu Structure

| Category | Action | Icon | Description |
|----------|--------|------|-------------|
| **Attach** | Screenshot | 📷 | Capture screen area |
| | File | 📎 | Upload file attachment |
| | URL | 🔗 | Add URL reference |
| **Reference** | Spec File | 📄 | Reference existing spec |
| | Project | 📁 | Reference another project |
| | Memory | 🧠 | Add from knowledge base |
| **Memory** | Add Memory | ➕ | Save current context |
| | Remove Memory | ➖ | Delete memory item |
| | Share Memory | 📤 | Share to another project |
| **Connectors** | GitHub | <img> | Connect/browse repos |

### Component Implementation

```typescript
// components/ai/PlusMenu.tsx

import { useState } from 'react';
import {
  Camera, Paperclip, Link, FileText, FolderOpen,
  Brain, Plus, Minus, Share2, Github, MoreHorizontal
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
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';

interface PlusMenuProps {
  onScreenshot: () => void;
  onFileUpload: () => void;
  onUrlAdd: () => void;
  onSpecReference: () => void;
  onProjectReference: () => void;
  onMemoryReference: () => void;
  onMemoryAdd: () => void;
  onMemoryRemove: () => void;
  onMemoryShare: () => void;
  onGitHubConnect: () => void;
}

export function PlusMenu({
  onScreenshot,
  onFileUpload,
  onUrlAdd,
  onSpecReference,
  onProjectReference,
  onMemoryReference,
  onMemoryAdd,
  onMemoryRemove,
  onMemoryShare,
  onGitHubConnect,
}: PlusMenuProps) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="icon" className="h-10 w-10">
          <Plus className="h-5 w-5" />
        </Button>
      </DropdownMenuTrigger>
      
      <DropdownMenuContent align="start" className="w-56">
        {/* Attach Section */}
        <DropdownMenuLabel>Attach</DropdownMenuLabel>
        <DropdownMenuItem onClick={onScreenshot}>
          <Camera className="h-4 w-4 mr-2" />
          Screenshot
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onFileUpload}>
          <Paperclip className="h-4 w-4 mr-2" />
          Upload File
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onUrlAdd}>
          <Link className="h-4 w-4 mr-2" />
          Add URL
        </DropdownMenuItem>
        
        <DropdownMenuSeparator />
        
        {/* Reference Section */}
        <DropdownMenuLabel>Reference</DropdownMenuLabel>
        <DropdownMenuItem onClick={onSpecReference}>
          <FileText className="h-4 w-4 mr-2" />
          Spec File
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onProjectReference}>
          <FolderOpen className="h-4 w-4 mr-2" />
          Another Project
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onMemoryReference}>
          <Brain className="h-4 w-4 mr-2" />
          From Knowledge
        </DropdownMenuItem>
        
        <DropdownMenuSeparator />
        
        {/* Memory Section */}
        <DropdownMenuSub>
          <DropdownMenuSubTrigger>
            <Brain className="h-4 w-4 mr-2" />
            Memory
          </DropdownMenuSubTrigger>
          <DropdownMenuSubContent>
            <DropdownMenuItem onClick={onMemoryAdd}>
              <Plus className="h-4 w-4 mr-2" />
              Add to Memory
            </DropdownMenuItem>
            <DropdownMenuItem onClick={onMemoryRemove}>
              <Minus className="h-4 w-4 mr-2" />
              Remove Memory
            </DropdownMenuItem>
            <DropdownMenuItem onClick={onMemoryShare}>
              <Share2 className="h-4 w-4 mr-2" />
              Share Memory
            </DropdownMenuItem>
          </DropdownMenuSubContent>
        </DropdownMenuSub>
        
        <DropdownMenuSeparator />
        
        {/* Connectors */}
        <DropdownMenuLabel>Connectors</DropdownMenuLabel>
        <DropdownMenuItem onClick={onGitHubConnect}>
          <Github className="h-4 w-4 mr-2" />
          GitHub
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
```

---

## 5.3 Chat Input Component

```typescript
// components/ai/ChatInput.tsx

import { useState, useRef, useCallback, KeyboardEvent } from 'react';
import { Mic, Send, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { PlusMenu } from './PlusMenu';
import { VoiceInputResilient } from './VoiceInputResilient';
import { useChatDraft } from '@/hooks/useChatDraft';
import { cn } from '@/lib/utils';

interface Reference {
  type: 'file' | 'url' | 'memory' | 'project';
  id: string;
  name: string;
  path?: string;
}

interface ChatInputProps {
  sessionId: string;
  onSend: (message: string, references: Reference[]) => void;
  disabled?: boolean;
}

export function ChatInput({ sessionId, onSend, disabled }: ChatInputProps) {
  const { draft, updateText, clearDraft } = useChatDraft(sessionId);
  const [references, setReferences] = useState<Reference[]>([]);
  const [isVoiceMode, setIsVoiceMode] = useState(false);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  
  // Auto-resize textarea
  const handleTextChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    updateText(e.target.value);
    
    // Auto-resize
    const textarea = e.target;
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 200)}px`;
  }, [updateText]);
  
  // Handle send
  const handleSend = useCallback(() => {
    if (!draft.text.trim() && references.length === 0) return;
    
    onSend(draft.text, references);
    clearDraft();
    setReferences([]);
    
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
    }
  }, [draft.text, references, onSend, clearDraft]);
  
  // Handle keyboard
  const handleKeyDown = useCallback((e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  }, [handleSend]);
  
  // Add reference
  const addReference = useCallback((ref: Reference) => {
    setReferences(prev => [...prev, ref]);
  }, []);
  
  // Remove reference
  const removeReference = useCallback((id: string) => {
    setReferences(prev => prev.filter(r => r.id !== id));
  }, []);
  
  // Handle voice transcription
  const handleVoiceComplete = useCallback((text: string) => {
    updateText(draft.text + (draft.text ? ' ' : '') + text);
    setIsVoiceMode(false);
  }, [draft.text, updateText]);
  
  // Reference type icons
  const getReferenceIcon = (type: Reference['type']) => {
    switch (type) {
      case 'file': return '📄';
      case 'url': return '🔗';
      case 'memory': return '🧠';
      case 'project': return '📁';
    }
  };
  
  return (
    <div className="border-t bg-background p-4">
      {/* References bar */}
      {references.length > 0 && (
        <div className="flex flex-wrap gap-2 mb-3">
          {references.map(ref => (
            <Badge key={ref.id} variant="secondary" className="gap-1 pr-1">
              <span>{getReferenceIcon(ref.type)}</span>
              <span className="max-w-32 truncate">{ref.name}</span>
              <button
                onClick={() => removeReference(ref.id)}
                className="ml-1 hover:bg-muted rounded-full p-0.5"
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
          onScreenshot={() => {/* TODO */}}
          onFileUpload={() => {/* TODO */}}
          onUrlAdd={() => {/* TODO */}}
          onSpecReference={() => {/* TODO: Open spec picker */}}
          onProjectReference={() => {/* TODO: Open project picker */}}
          onMemoryReference={() => {/* TODO: Open memory picker */}}
          onMemoryAdd={() => {/* TODO */}}
          onMemoryRemove={() => {/* TODO */}}
          onMemoryShare={() => {/* TODO */}}
          onGitHubConnect={() => {/* TODO */}}
        />
        
        {/* Text input or voice */}
        {isVoiceMode ? (
          <div className="flex-1 flex items-center justify-center py-2">
            <VoiceInputResilient
              onTranscriptionComplete={handleVoiceComplete}
            />
          </div>
        ) : (
          <div className="flex-1 relative">
            <Textarea
              ref={textareaRef}
              value={draft.text}
              onChange={handleTextChange}
              onKeyDown={handleKeyDown}
              placeholder="Message AI..."
              disabled={disabled}
              className="min-h-10 max-h-48 resize-none pr-10"
              rows={1}
            />
            <Button
              variant="ghost"
              size="icon"
              className="absolute right-1 bottom-1"
              onClick={() => setIsVoiceMode(true)}
            >
              <Mic className="h-4 w-4" />
            </Button>
          </div>
        )}
        
        {/* Send button */}
        <Button
          size="icon"
          onClick={handleSend}
          disabled={disabled || (!draft.text.trim() && references.length === 0)}
        >
          <Send className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
```

---

## 5.4 History Sidebar

```typescript
// components/ai/HistorySidebar.tsx

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  MessageSquare, Brain, Link2, Settings,
  ChevronDown, ChevronRight, Trash2, MoreHorizontal
} from 'lucide-react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

interface ChatSession {
  id: string;
  title: string;
  createdAt: Date;
  messageCount: number;
}

interface HistorySidebarProps {
  projectId: string;
  currentSessionId?: string;
  onSessionSelect: (sessionId: string) => void;
  onNewSession: () => void;
  onKnowledgeOpen: () => void;
  onConnectorsOpen: () => void;
  onSettingsOpen: () => void;
}

export function HistorySidebar({
  projectId,
  currentSessionId,
  onSessionSelect,
  onNewSession,
  onKnowledgeOpen,
  onConnectorsOpen,
  onSettingsOpen,
}: HistorySidebarProps) {
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set(['today']));
  
  // Fetch chat history
  const { data: sessions = [] } = useQuery({
    queryKey: ['chat-sessions', projectId],
    queryFn: async () => {
      const response = await fetch(`/api/v1/projects/${projectId}/sessions`);
      return response.json() as Promise<ChatSession[]>;
    },
  });
  
  // Group sessions by date
  const groupedSessions = groupSessionsByDate(sessions);
  
  const toggleGroup = (group: string) => {
    setExpandedGroups(prev => {
      const next = new Set(prev);
      if (next.has(group)) {
        next.delete(group);
      } else {
        next.add(group);
      }
      return next;
    });
  };
  
  return (
    <div className="w-64 border-r bg-muted/30 flex flex-col h-full">
      {/* Header */}
      <div className="p-4 border-b">
        <Button onClick={onNewSession} className="w-full">
          <MessageSquare className="h-4 w-4 mr-2" />
          New Chat
        </Button>
      </div>
      
      {/* Sessions list */}
      <ScrollArea className="flex-1">
        <div className="p-2">
          {Object.entries(groupedSessions).map(([group, groupSessions]) => (
            <Collapsible
              key={group}
              open={expandedGroups.has(group)}
              onOpenChange={() => toggleGroup(group)}
            >
              <CollapsibleTrigger className="flex items-center gap-2 w-full px-2 py-1.5 text-sm text-muted-foreground hover:text-foreground">
                {expandedGroups.has(group) ? (
                  <ChevronDown className="h-4 w-4" />
                ) : (
                  <ChevronRight className="h-4 w-4" />
                )}
                <span className="capitalize">{group}</span>
                <span className="text-xs">({groupSessions.length})</span>
              </CollapsibleTrigger>
              
              <CollapsibleContent>
                <div className="ml-4 space-y-0.5">
                  {groupSessions.map(session => (
                    <SessionItem
                      key={session.id}
                      session={session}
                      isActive={session.id === currentSessionId}
                      onSelect={() => onSessionSelect(session.id)}
                    />
                  ))}
                </div>
              </CollapsibleContent>
            </Collapsible>
          ))}
        </div>
      </ScrollArea>
      
      {/* Bottom actions */}
      <div className="border-t p-2 space-y-1">
        <Button variant="ghost" className="w-full justify-start" onClick={onKnowledgeOpen}>
          <Brain className="h-4 w-4 mr-2" />
          Knowledge
        </Button>
        <Button variant="ghost" className="w-full justify-start" onClick={onConnectorsOpen}>
          <Link2 className="h-4 w-4 mr-2" />
          Connectors
        </Button>
        <Button variant="ghost" className="w-full justify-start" onClick={onSettingsOpen}>
          <Settings className="h-4 w-4 mr-2" />
          Settings
        </Button>
      </div>
    </div>
  );
}

interface SessionItemProps {
  session: ChatSession;
  isActive: boolean;
  onSelect: () => void;
}

function SessionItem({ session, isActive, onSelect }: SessionItemProps) {
  return (
    <div
      className={cn(
        'group flex items-center gap-2 px-2 py-1.5 rounded text-sm cursor-pointer',
        isActive ? 'bg-primary/10 text-primary' : 'hover:bg-muted'
      )}
      onClick={onSelect}
    >
      <span className="flex-1 truncate">{session.title}</span>
      
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <button className="opacity-0 group-hover:opacity-100 p-0.5 hover:bg-muted rounded">
            <MoreHorizontal className="h-4 w-4" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem className="text-destructive">
            <Trash2 className="h-4 w-4 mr-2" />
            Delete
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}

function groupSessionsByDate(sessions: ChatSession[]): Record<string, ChatSession[]> {
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000);
  const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
  
  const groups: Record<string, ChatSession[]> = {
    today: [],
    yesterday: [],
    'this week': [],
    older: [],
  };
  
  for (const session of sessions) {
    const date = new Date(session.createdAt);
    if (date >= today) {
      groups.today.push(session);
    } else if (date >= yesterday) {
      groups.yesterday.push(session);
    } else if (date >= weekAgo) {
      groups['this week'].push(session);
    } else {
      groups.older.push(session);
    }
  }
  
  // Remove empty groups
  return Object.fromEntries(
    Object.entries(groups).filter(([_, sessions]) => sessions.length > 0)
  );
}
```

---

## 5.5 Mode Selector

```typescript
// components/ai/ModeSelector.tsx

import { FileText, Code, GitBranch } from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

type AIMode = 'spec' | 'coding' | 'plan';

interface ModeSelectorProps {
  value: AIMode;
  onChange: (mode: AIMode) => void;
}

export function ModeSelector({ value, onChange }: ModeSelectorProps) {
  const modes: { value: AIMode; label: string; icon: React.ReactNode; description: string }[] = [
    {
      value: 'spec',
      label: 'Spec Mode',
      icon: <FileText className="h-4 w-4" />,
      description: 'Draft specifications',
    },
    {
      value: 'coding',
      label: 'Coding Mode',
      icon: <Code className="h-4 w-4" />,
      description: 'Generate code',
    },
    {
      value: 'plan',
      label: 'Plan Mode',
      icon: <GitBranch className="h-4 w-4" />,
      description: 'Create execution plans',
    },
  ];
  
  return (
    <Select value={value} onValueChange={(v) => onChange(v as AIMode)}>
      <SelectTrigger className="w-40">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {modes.map(mode => (
          <SelectItem key={mode.value} value={mode.value}>
            <div className="flex items-center gap-2">
              {mode.icon}
              <div>
                <div className="font-medium">{mode.label}</div>
                <div className="text-xs text-muted-foreground">{mode.description}</div>
              </div>
            </div>
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
```

---

## 5.6 Main Chat Container

```typescript
// components/ai/ChatContainer.tsx

import { useState, useCallback } from 'react';
import { HistorySidebar } from './HistorySidebar';
import { ChatMessages } from './ChatMessages';
import { ChatInput, Reference } from './ChatInput';
import { ModeSelector } from './ModeSelector';
import { PlanView } from './PlanView';
import { usePlan } from '@/hooks/usePlan';
import { useChat } from '@/hooks/useChat';
import { Settings } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ChatContainerProps {
  projectId: string;
}

export function ChatContainer({ projectId }: ChatContainerProps) {
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [mode, setMode] = useState<'spec' | 'coding' | 'plan'>('spec');
  
  const chat = useChat(projectId, sessionId);
  const plan = usePlan(projectId, sessionId ?? '');
  
  // Handle message send
  const handleSend = useCallback(async (text: string, references: Reference[]) => {
    if (mode === 'plan') {
      // Generate execution plan
      plan.generatePlan(text);
    } else {
      // Regular chat message
      await chat.sendMessage(text, references);
    }
  }, [mode, plan, chat]);
  
  // Create new session
  const handleNewSession = useCallback(async () => {
    const newSession = await chat.createSession();
    setSessionId(newSession.id);
  }, [chat]);
  
  return (
    <div className="flex h-full">
      {/* History sidebar */}
      <HistorySidebar
        projectId={projectId}
        currentSessionId={sessionId ?? undefined}
        onSessionSelect={setSessionId}
        onNewSession={handleNewSession}
        onKnowledgeOpen={() => {/* TODO */}}
        onConnectorsOpen={() => {/* TODO */}}
        onSettingsOpen={() => {/* TODO */}}
      />
      
      {/* Main chat area */}
      <div className="flex-1 flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-2 border-b">
          <h2 className="font-semibold">AI Chat</h2>
          <div className="flex items-center gap-2">
            <ModeSelector value={mode} onChange={setMode} />
            <Button variant="ghost" size="icon">
              <Settings className="h-4 w-4" />
            </Button>
          </div>
        </div>
        
        {/* Messages or Plan view */}
        <div className="flex-1 overflow-hidden">
          {mode === 'plan' && plan.plan ? (
            <div className="p-4 h-full overflow-auto">
              <PlanView
                plan={plan.plan}
                onApprove={plan.approvePlan}
                onCancel={plan.cancelPlan}
                onStepModify={plan.modifyStep}
                onExecuteStep={plan.executeStep}
                onExecuteAll={plan.executeAll}
                onPause={plan.pauseExecution}
              />
            </div>
          ) : (
            <ChatMessages
              messages={chat.messages}
              isLoading={chat.isLoading}
            />
          )}
        </div>
        
        {/* Input */}
        <ChatInput
          sessionId={sessionId ?? 'new'}
          onSend={handleSend}
          disabled={chat.isLoading || plan.isGenerating}
        />
      </div>
    </div>
  );
}
```

---

## 5.7 Testing Checklist

| Test | Description | Priority |
|------|-------------|----------|
| Plus menu opens | All actions accessible | Critical |
| File reference | Spec file can be attached | Critical |
| Voice input | Recording works from input | Critical |
| History navigation | Sessions load correctly | High |
| Mode switching | Modes change behavior correctly | High |
| Reference display | Attached items show correctly | Medium |
| Keyboard shortcuts | Enter sends, Shift+Enter newline | Medium |

---

## Related Specs

- [AI Chat UI](../06-ai-integration/08-ai-chat-ui.md)
- [Plan Mode](./03-plan-mode.md)
- [Cross-Project Memory](./06-cross-project-memory.md)
