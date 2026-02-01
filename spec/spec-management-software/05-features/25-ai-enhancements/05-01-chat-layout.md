# Phase 5.1: Chat Layout Architecture

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Chat UI Redesign](./05-chat-ui-redesign.md)

---

## Overview

Responsive three-panel layout with collapsible history sidebar, main chat area, and contextual panels. Implements Lovable-style aesthetics with glass morphism, smooth animations, and adaptive breakpoints.

---

## 1. Layout Structure

### 1.1 Desktop Layout (≥1024px)

```
┌────────────────────────────────────────────────────────────────────────────┐
│ ┌─────────────┐ ┌────────────────────────────────────────────────────────┐ │
│ │  SIDEBAR    │ │  MAIN CHAT AREA                                        │ │
│ │  (260px)    │ │  ┌──────────────────────────────────────────────────┐  │ │
│ │             │ │  │  HEADER BAR (56px)                               │  │ │
│ │  History    │ │  │  [≡] AI Chat              [Mode ▼] [⋯] [⚙]      │  │ │
│ │  Sessions   │ │  └──────────────────────────────────────────────────┘  │ │
│ │             │ │  ┌──────────────────────────────────────────────────┐  │ │
│ │  ─────────  │ │  │                                                  │  │ │
│ │             │ │  │  MESSAGE STREAM (flex-1)                         │  │ │
│ │  Knowledge  │ │  │                                                  │  │ │
│ │  Connectors │ │  │  Messages with virtualized scrolling             │  │ │
│ │  Settings   │ │  │                                                  │  │ │
│ └─────────────┘ │  └──────────────────────────────────────────────────┘  │ │
│                 │  ┌──────────────────────────────────────────────────┐  │ │
│                 │  │  INPUT AREA (auto height, max 200px)             │  │ │
│                 │  │  [+] │ Message AI...                    [🎤][➤] │  │ │
│                 │  │  References: [📄 file.md] [🔗 url]               │  │ │
│                 │  └──────────────────────────────────────────────────┘  │ │
│                 └────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Tablet Layout (768px - 1023px)

```
┌──────────────────────────────────────────────────────────────┐
│ ┌──────────────────────────────────────────────────────────┐ │
│ │  HEADER BAR                                              │ │
│ │  [☰] AI Chat                          [Mode ▼] [⋯] [⚙]  │ │
│ └──────────────────────────────────────────────────────────┘ │
│ ┌──────────────────────────────────────────────────────────┐ │
│ │                                                          │ │
│ │  MESSAGE STREAM (full width)                             │ │
│ │                                                          │ │
│ └──────────────────────────────────────────────────────────┘ │
│ ┌──────────────────────────────────────────────────────────┐ │
│ │  INPUT AREA                                              │ │
│ │  [+] │ Message AI...                          [🎤] [➤]  │ │
│ └──────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────┐
│  SIDEBAR (overlay)   │  ← Opens via hamburger menu
│  Slides from left    │
│  280px width         │
│  Dark scrim overlay  │
└──────────────────────┘
```

### 1.3 Mobile Layout (<768px)

```
┌────────────────────────────────┐
│ ┌────────────────────────────┐ │
│ │ [☰] AI Chat      [Mode] [⚙]│ │
│ └────────────────────────────┘ │
│ ┌────────────────────────────┐ │
│ │                            │ │
│ │  MESSAGE STREAM            │ │
│ │  (full width, compact)     │ │
│ │                            │ │
│ └────────────────────────────┘ │
│ ┌────────────────────────────┐ │
│ │ [+] Message...    [🎤][➤] │ │
│ └────────────────────────────┘ │
└────────────────────────────────┘
```

---

## 2. Component Architecture

### 2.1 Layout Components

```typescript
// components/ai/layout/ChatLayout.tsx

import { useState, useCallback } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Menu } from 'lucide-react';

interface ChatLayoutProps {
  sidebar: React.ReactNode;
  header: React.ReactNode;
  content: React.ReactNode;
  input: React.ReactNode;
  className?: string;
}

export function ChatLayout({
  sidebar,
  header,
  content,
  input,
  className,
}: ChatLayoutProps) {
  const isMobile = useIsMobile();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  
  // Breakpoint detection for tablet vs desktop
  const [isTablet, setIsTablet] = useState(false);
  
  useEffect(() => {
    const checkBreakpoint = () => {
      const width = window.innerWidth;
      setIsTablet(width >= 768 && width < 1024);
    };
    checkBreakpoint();
    window.addEventListener('resize', checkBreakpoint);
    return () => window.removeEventListener('resize', checkBreakpoint);
  }, []);
  
  const showInlineSidebar = !isMobile && !isTablet;
  
  return (
    <div className={cn('flex h-full bg-background', className)}>
      {/* Desktop: Inline sidebar */}
      {showInlineSidebar && (
        <aside className="w-64 border-r bg-muted/30 flex-shrink-0">
          {sidebar}
        </aside>
      )}
      
      {/* Mobile/Tablet: Sheet sidebar */}
      {!showInlineSidebar && (
        <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
          <SheetContent side="left" className="w-72 p-0">
            {sidebar}
          </SheetContent>
        </Sheet>
      )}
      
      {/* Main content area */}
      <main className="flex-1 flex flex-col min-w-0">
        {/* Header with mobile menu trigger */}
        <header className="flex items-center gap-2 px-4 h-14 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
          {!showInlineSidebar && (
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setSidebarOpen(true)}
              className="flex-shrink-0"
            >
              <Menu className="h-5 w-5" />
            </Button>
          )}
          <div className="flex-1 min-w-0">
            {header}
          </div>
        </header>
        
        {/* Scrollable message area */}
        <div className="flex-1 overflow-hidden">
          {content}
        </div>
        
        {/* Fixed input area */}
        <div className="flex-shrink-0 border-t bg-background">
          {input}
        </div>
      </main>
    </div>
  );
}
```

### 2.2 Resizable Panel Support

```typescript
// components/ai/layout/ResizableChatLayout.tsx

import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { useLocalStorage } from '@/hooks/useLocalStorage';

interface ResizableChatLayoutProps {
  sidebar: React.ReactNode;
  mainContent: React.ReactNode;
  contextPanel?: React.ReactNode;
  showContextPanel?: boolean;
}

export function ResizableChatLayout({
  sidebar,
  mainContent,
  contextPanel,
  showContextPanel = false,
}: ResizableChatLayoutProps) {
  // Persist panel sizes
  const [sidebarSize, setSidebarSize] = useLocalStorage('chat-sidebar-size', 20);
  const [contextSize, setContextSize] = useLocalStorage('chat-context-size', 30);
  
  return (
    <ResizablePanelGroup direction="horizontal" className="h-full">
      {/* Sidebar panel */}
      <ResizablePanel
        defaultSize={sidebarSize}
        minSize={15}
        maxSize={30}
        onResize={setSidebarSize}
        className="bg-muted/30"
      >
        {sidebar}
      </ResizablePanel>
      
      <ResizableHandle withHandle />
      
      {/* Main chat panel */}
      <ResizablePanel
        defaultSize={showContextPanel ? 50 : 80}
        minSize={40}
      >
        {mainContent}
      </ResizablePanel>
      
      {/* Optional context panel (for code preview, docs, etc.) */}
      {showContextPanel && contextPanel && (
        <>
          <ResizableHandle withHandle />
          <ResizablePanel
            defaultSize={contextSize}
            minSize={20}
            maxSize={50}
            onResize={setContextSize}
          >
            {contextPanel}
          </ResizablePanel>
        </>
      )}
    </ResizablePanelGroup>
  );
}
```

---

## 3. Visual Design System

### 3.1 CSS Variables

```css
/* index.css - Chat UI tokens */
:root {
  /* Chat-specific colors */
  --chat-bg: hsl(var(--background));
  --chat-sidebar-bg: hsl(var(--muted) / 0.3);
  --chat-input-bg: hsl(var(--background));
  --chat-message-user-bg: hsl(var(--primary));
  --chat-message-user-fg: hsl(var(--primary-foreground));
  --chat-message-ai-bg: hsl(var(--muted));
  --chat-message-ai-fg: hsl(var(--foreground));
  
  /* Glass morphism */
  --chat-glass-bg: hsl(var(--background) / 0.8);
  --chat-glass-border: hsl(var(--border) / 0.5);
  --chat-glass-blur: 12px;
  
  /* Spacing */
  --chat-message-gap: 1rem;
  --chat-bubble-radius: 1rem;
  --chat-input-radius: 0.75rem;
  
  /* Shadows */
  --chat-shadow-sm: 0 1px 2px hsl(var(--foreground) / 0.05);
  --chat-shadow-md: 0 4px 12px hsl(var(--foreground) / 0.1);
  --chat-shadow-glow: 0 0 20px hsl(var(--primary) / 0.15);
}

.dark {
  --chat-glass-bg: hsl(var(--background) / 0.7);
  --chat-shadow-sm: 0 1px 2px hsl(0 0% 0% / 0.2);
  --chat-shadow-md: 0 4px 12px hsl(0 0% 0% / 0.3);
}
```

### 3.2 Animation Tokens

```css
/* Animations */
@keyframes chat-slide-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes chat-fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes chat-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

@keyframes typing-dot {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-4px); }
}

.chat-message-enter {
  animation: chat-slide-in 0.3s ease-out;
}

.chat-skeleton {
  animation: chat-pulse 1.5s ease-in-out infinite;
}

.typing-indicator span {
  animation: typing-dot 1.4s ease-in-out infinite;
}

.typing-indicator span:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
  animation-delay: 0.4s;
}
```

---

## 4. Keyboard Navigation

### 4.1 Keyboard Shortcuts

| Shortcut | Action | Scope |
|----------|--------|-------|
| `⌘/Ctrl + N` | New chat session | Global |
| `⌘/Ctrl + K` | Focus search/input | Global |
| `⌘/Ctrl + B` | Toggle sidebar | Global |
| `⌘/Ctrl + 1/2/3` | Switch mode (Spec/Coding/Plan) | Global |
| `↑/↓` | Navigate history (when input focused) | Input |
| `Enter` | Send message | Input |
| `Shift + Enter` | New line | Input |
| `Escape` | Cancel voice recording | Voice mode |
| `⌘/Ctrl + Enter` | Send with execution (Coding mode) | Input |

### 4.2 Keyboard Handler

```typescript
// hooks/useChatKeyboard.ts

import { useEffect, useCallback } from 'react';

interface ChatKeyboardOptions {
  onNewSession: () => void;
  onToggleSidebar: () => void;
  onModeChange: (mode: 'spec' | 'coding' | 'plan') => void;
  onFocusInput: () => void;
  inputRef: React.RefObject<HTMLTextAreaElement>;
}

export function useChatKeyboard({
  onNewSession,
  onToggleSidebar,
  onModeChange,
  onFocusInput,
  inputRef,
}: ChatKeyboardOptions) {
  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    const isMod = e.metaKey || e.ctrlKey;
    
    // Don't handle if in input field (except specific shortcuts)
    const isInInput = document.activeElement === inputRef.current;
    
    if (isMod) {
      switch (e.key.toLowerCase()) {
        case 'n':
          e.preventDefault();
          onNewSession();
          break;
        case 'k':
          e.preventDefault();
          onFocusInput();
          break;
        case 'b':
          e.preventDefault();
          onToggleSidebar();
          break;
        case '1':
          e.preventDefault();
          onModeChange('spec');
          break;
        case '2':
          e.preventDefault();
          onModeChange('coding');
          break;
        case '3':
          e.preventDefault();
          onModeChange('plan');
          break;
      }
    }
  }, [onNewSession, onToggleSidebar, onModeChange, onFocusInput, inputRef]);
  
  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [handleKeyDown]);
}
```

---

## 5. Accessibility

### 5.1 ARIA Landmarks

```typescript
// Semantic structure
<div role="application" aria-label="AI Chat Interface">
  <nav role="navigation" aria-label="Chat history">
    {/* Sidebar */}
  </nav>
  
  <main role="main" aria-label="Chat conversation">
    <header role="banner">
      {/* Mode selector, settings */}
    </header>
    
    <div role="log" aria-live="polite" aria-label="Chat messages">
      {/* Messages */}
    </div>
    
    <form role="form" aria-label="Message input">
      {/* Input area */}
    </form>
  </main>
</div>
```

### 5.2 Screen Reader Announcements

```typescript
// hooks/useChatAnnouncer.ts

import { useCallback } from 'react';

export function useChatAnnouncer() {
  const announce = useCallback((message: string, priority: 'polite' | 'assertive' = 'polite') => {
    const el = document.createElement('div');
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', priority);
    el.setAttribute('aria-atomic', 'true');
    el.className = 'sr-only';
    el.textContent = message;
    
    document.body.appendChild(el);
    setTimeout(() => document.body.removeChild(el), 1000);
  }, []);
  
  return {
    announceNewMessage: (sender: string) => 
      announce(`New message from ${sender}`),
    announceTyping: () => 
      announce('AI is typing'),
    announceError: (error: string) => 
      announce(`Error: ${error}`, 'assertive'),
    announceModeChange: (mode: string) => 
      announce(`Switched to ${mode} mode`),
  };
}
```

---

## 6. Performance Optimizations

### 6.1 Message Virtualization

```typescript
// components/ai/VirtualizedMessageList.tsx

import { useVirtualizer } from '@tanstack/react-virtual';
import { useRef, useCallback } from 'react';

interface Message {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  createdAt: Date;
}

interface VirtualizedMessageListProps {
  messages: Message[];
  estimateSize?: number;
}

export function VirtualizedMessageList({
  messages,
  estimateSize = 100,
}: VirtualizedMessageListProps) {
  const parentRef = useRef<HTMLDivElement>(null);
  
  const virtualizer = useVirtualizer({
    count: messages.length,
    getScrollElement: () => parentRef.current,
    estimateSize: () => estimateSize,
    overscan: 5,
    getItemKey: (index) => messages[index].id,
  });
  
  // Auto-scroll to bottom on new messages
  const scrollToBottom = useCallback(() => {
    if (parentRef.current) {
      parentRef.current.scrollTop = parentRef.current.scrollHeight;
    }
  }, []);
  
  useEffect(() => {
    scrollToBottom();
  }, [messages.length, scrollToBottom]);
  
  return (
    <div ref={parentRef} className="h-full overflow-auto px-4">
      <div
        style={{
          height: `${virtualizer.getTotalSize()}px`,
          width: '100%',
          position: 'relative',
        }}
      >
        {virtualizer.getVirtualItems().map((virtualItem) => (
          <div
            key={virtualItem.key}
            data-index={virtualItem.index}
            ref={virtualizer.measureElement}
            style={{
              position: 'absolute',
              top: 0,
              left: 0,
              width: '100%',
              transform: `translateY(${virtualItem.start}px)`,
            }}
          >
            <MessageBubble message={messages[virtualItem.index]} />
          </div>
        ))}
      </div>
    </div>
  );
}
```

### 6.2 Lazy Loading

```typescript
// Lazy load heavy components
const MermaidDiagram = lazy(() => import('./MermaidDiagram'));
const CodeBlock = lazy(() => import('./CodeBlock'));
const PlanView = lazy(() => import('./PlanView'));

// Use with Suspense
<Suspense fallback={<Skeleton className="h-32" />}>
  <MermaidDiagram code={diagramCode} />
</Suspense>
```

---

## 7. Testing Requirements

| Test | Description | Priority |
|------|-------------|----------|
| Responsive breakpoints | Layout adapts at 768px, 1024px | Critical |
| Sidebar toggle | Opens/closes correctly on mobile | Critical |
| Keyboard navigation | All shortcuts functional | High |
| Virtualization | Large message lists render efficiently | High |
| Focus management | Focus moves correctly between elements | High |
| Theme switching | Light/dark mode transitions | Medium |
| Panel resizing | Sizes persist across sessions | Medium |

---

## Related Specs

- [Chat Input Component](./05-02-chat-input.md)
- [Message Bubbles](./05-03-message-display.md)
- [Mode Selector](./05-04-mode-selector.md)
