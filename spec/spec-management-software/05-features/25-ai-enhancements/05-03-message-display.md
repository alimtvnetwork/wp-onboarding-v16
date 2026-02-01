# Phase 5.3: Message Display & Streaming

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Chat UI Redesign](./05-chat-ui-redesign.md)

---

## Overview

Message bubbles with streaming token display, Markdown rendering, code blocks, Mermaid diagrams, file diffs, and execution status indicators.

---

## 1. Message Types

### 1.1 Message Schema

```typescript
// types/chat.ts

export interface ChatMessage {
  id: string;
  sessionId: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  status: 'pending' | 'streaming' | 'complete' | 'error';
  
  // Metadata
  createdAt: Date;
  completedAt?: Date;
  
  // Attachments
  references?: MessageReference[];
  
  // AI-specific
  model?: string;
  tokenCount?: number;
  
  // Execution (Coding mode)
  execution?: ExecutionResult;
  
  // Plan (Plan mode)
  plan?: ExecutionPlan;
  
  // Error details
  error?: {
    code: string;
    message: string;
    retryable: boolean;
  };
}

export interface MessageReference {
  type: 'file' | 'url' | 'memory' | 'project' | 'screenshot';
  id: string;
  name: string;
  path?: string;
  preview?: string;
}

export interface ExecutionResult {
  status: 'pending' | 'running' | 'success' | 'failed';
  command?: string;
  output?: string;
  error?: string;
  duration?: number;
  files?: FileChange[];
}

export interface FileChange {
  path: string;
  action: 'create' | 'modify' | 'delete';
  diff?: string;
  status: 'pending' | 'applied' | 'failed';
}
```

### 1.2 Message States

```
┌─────────────────────────────────────────────────────────────┐
│  MESSAGE STATES                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  PENDING ──► STREAMING ──► COMPLETE                         │
│     │             │              │                          │
│     └─────────────┴──────────────┴──► ERROR                 │
│                                                             │
│  Visual indicators:                                         │
│  • PENDING:   Pulsing skeleton                              │
│  • STREAMING: Blinking cursor, live token append            │
│  • COMPLETE:  Full content, no cursor                       │
│  • ERROR:     Red border, retry button                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Message Bubble Components

### 2.1 Base Message Bubble

```typescript
// components/ai/messages/MessageBubble.tsx

import { memo, useMemo } from 'react';
import { User, Bot, AlertCircle, RefreshCw } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { MessageContent } from './MessageContent';
import { MessageReferences } from './MessageReferences';
import { ExecutionStatus } from './ExecutionStatus';
import { cn } from '@/lib/utils';
import type { ChatMessage } from '@/types/chat';

interface MessageBubbleProps {
  message: ChatMessage;
  isLatest?: boolean;
  onRetry?: () => void;
  onCopy?: () => void;
  className?: string;
}

export const MessageBubble = memo(function MessageBubble({
  message,
  isLatest,
  onRetry,
  onCopy,
  className,
}: MessageBubbleProps) {
  const isUser = message.role === 'user';
  const isStreaming = message.status === 'streaming';
  const isError = message.status === 'error';
  const isPending = message.status === 'pending';
  
  return (
    <div
      className={cn(
        'group flex gap-3 px-4 py-4 chat-message-enter',
        isUser ? 'flex-row-reverse' : 'flex-row',
        isLatest && 'mb-4',
        className
      )}
      role="article"
      aria-label={`Message from ${isUser ? 'you' : 'AI'}`}
    >
      {/* Avatar */}
      <Avatar className={cn(
        'h-8 w-8 flex-shrink-0',
        isUser ? 'bg-primary' : 'bg-muted'
      )}>
        <AvatarFallback>
          {isUser ? (
            <User className="h-4 w-4 text-primary-foreground" />
          ) : (
            <Bot className="h-4 w-4" />
          )}
        </AvatarFallback>
      </Avatar>
      
      {/* Content */}
      <div
        className={cn(
          'flex-1 space-y-2 min-w-0',
          isUser ? 'items-end' : 'items-start'
        )}
      >
        {/* Message bubble */}
        <div
          className={cn(
            'relative rounded-2xl px-4 py-3 max-w-[85%]',
            isUser
              ? 'bg-primary text-primary-foreground ml-auto rounded-br-sm'
              : 'bg-muted rounded-bl-sm',
            isError && 'border-2 border-destructive',
            isStreaming && 'shadow-md'
          )}
        >
          {/* Pending state */}
          {isPending && (
            <div className="space-y-2">
              <Skeleton className="h-4 w-48" />
              <Skeleton className="h-4 w-32" />
            </div>
          )}
          
          {/* Content */}
          {!isPending && (
            <>
              <MessageContent
                content={message.content}
                isStreaming={isStreaming}
              />
              
              {/* Streaming cursor */}
              {isStreaming && (
                <span className="inline-block w-2 h-4 bg-current ml-0.5 animate-pulse" />
              )}
            </>
          )}
          
          {/* Error state */}
          {isError && message.error && (
            <div className="flex items-center gap-2 mt-2 pt-2 border-t border-destructive/30">
              <AlertCircle className="h-4 w-4 text-destructive" />
              <span className="text-sm text-destructive">{message.error.message}</span>
              {message.error.retryable && onRetry && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={onRetry}
                  className="ml-auto"
                >
                  <RefreshCw className="h-3 w-3 mr-1" />
                  Retry
                </Button>
              )}
            </div>
          )}
        </div>
        
        {/* References */}
        {message.references && message.references.length > 0 && (
          <MessageReferences references={message.references} />
        )}
        
        {/* Execution status (Coding mode) */}
        {message.execution && (
          <ExecutionStatus execution={message.execution} />
        )}
        
        {/* Actions (on hover) */}
        {!isStreaming && !isPending && (
          <div className={cn(
            'flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity',
            isUser ? 'justify-end' : 'justify-start'
          )}>
            <MessageActions
              message={message}
              onCopy={onCopy}
              onRetry={onRetry}
            />
          </div>
        )}
      </div>
    </div>
  );
});
```

### 2.2 Message Content Renderer

```typescript
// components/ai/messages/MessageContent.tsx

import { memo, useMemo } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { CodeBlock } from './CodeBlock';
import { MermaidBlock } from './MermaidBlock';
import { cn } from '@/lib/utils';

interface MessageContentProps {
  content: string;
  isStreaming?: boolean;
}

export const MessageContent = memo(function MessageContent({
  content,
  isStreaming,
}: MessageContentProps) {
  // Custom components for markdown
  const components = useMemo(() => ({
    // Code blocks with syntax highlighting
    code({ node, inline, className, children, ...props }: any) {
      const match = /language-(\w+)/.exec(className || '');
      const language = match ? match[1] : '';
      const code = String(children).replace(/\n$/, '');
      
      // Mermaid diagrams
      if (language === 'mermaid') {
        return <MermaidBlock code={code} />;
      }
      
      // Inline code
      if (inline) {
        return (
          <code
            className="px-1.5 py-0.5 rounded bg-muted font-mono text-sm"
            {...props}
          >
            {children}
          </code>
        );
      }
      
      // Code blocks
      return (
        <CodeBlock
          code={code}
          language={language}
          isStreaming={isStreaming}
        />
      );
    },
    
    // Links open in new tab
    a({ href, children, ...props }: any) {
      return (
        <a
          href={href}
          target="_blank"
          rel="noopener noreferrer"
          className="text-primary underline underline-offset-4 hover:no-underline"
          {...props}
        >
          {children}
        </a>
      );
    },
    
    // Tables
    table({ children, ...props }: any) {
      return (
        <div className="my-4 overflow-x-auto">
          <table className="min-w-full divide-y divide-border" {...props}>
            {children}
          </table>
        </div>
      );
    },
    
    th({ children, ...props }: any) {
      return (
        <th
          className="px-4 py-2 text-left text-sm font-semibold bg-muted"
          {...props}
        >
          {children}
        </th>
      );
    },
    
    td({ children, ...props }: any) {
      return (
        <td className="px-4 py-2 text-sm border-t" {...props}>
          {children}
        </td>
      );
    },
    
    // Lists
    ul({ children, ...props }: any) {
      return (
        <ul className="list-disc list-inside space-y-1 my-2" {...props}>
          {children}
        </ul>
      );
    },
    
    ol({ children, ...props }: any) {
      return (
        <ol className="list-decimal list-inside space-y-1 my-2" {...props}>
          {children}
        </ol>
      );
    },
    
    // Blockquotes
    blockquote({ children, ...props }: any) {
      return (
        <blockquote
          className="border-l-4 border-primary/50 pl-4 my-2 italic text-muted-foreground"
          {...props}
        >
          {children}
        </blockquote>
      );
    },
    
    // Headings
    h1: ({ children }: any) => <h1 className="text-2xl font-bold mt-4 mb-2">{children}</h1>,
    h2: ({ children }: any) => <h2 className="text-xl font-semibold mt-3 mb-2">{children}</h2>,
    h3: ({ children }: any) => <h3 className="text-lg font-medium mt-2 mb-1">{children}</h3>,
    
    // Paragraphs
    p({ children, ...props }: any) {
      return (
        <p className="leading-relaxed [&:not(:last-child)]:mb-2" {...props}>
          {children}
        </p>
      );
    },
  }), [isStreaming]);
  
  return (
    <div className={cn(
      'prose prose-sm dark:prose-invert max-w-none',
      'prose-pre:bg-transparent prose-pre:p-0'
    )}>
      <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
        {content}
      </ReactMarkdown>
    </div>
  );
});
```

### 2.3 Code Block Component

```typescript
// components/ai/messages/CodeBlock.tsx

import { memo, useState, useCallback } from 'react';
import { Check, Copy, Play, ChevronDown, ChevronUp } from 'lucide-react';
import { Prism as SyntaxHighlighter } from 'react-syntax-highlighter';
import { oneDark, oneLight } from 'react-syntax-highlighter/dist/esm/styles/prism';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useTheme } from 'next-themes';
import { cn } from '@/lib/utils';

interface CodeBlockProps {
  code: string;
  language?: string;
  filename?: string;
  isStreaming?: boolean;
  onExecute?: () => void;
  collapsible?: boolean;
  maxHeight?: number;
}

export const CodeBlock = memo(function CodeBlock({
  code,
  language = 'text',
  filename,
  isStreaming,
  onExecute,
  collapsible = false,
  maxHeight = 400,
}: CodeBlockProps) {
  const { resolvedTheme } = useTheme();
  const [copied, setCopied] = useState(false);
  const [isOpen, setIsOpen] = useState(true);
  
  const isDark = resolvedTheme === 'dark';
  const lineCount = code.split('\n').length;
  const showCollapse = collapsible && lineCount > 15;
  
  // Copy to clipboard
  const handleCopy = useCallback(async () => {
    await navigator.clipboard.writeText(code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }, [code]);
  
  const codeContent = (
    <div
      className={cn(
        'overflow-auto rounded-md',
        isStreaming && 'animate-pulse'
      )}
      style={{ maxHeight: `${maxHeight}px` }}
    >
      <SyntaxHighlighter
        language={language}
        style={isDark ? oneDark : oneLight}
        customStyle={{
          margin: 0,
          padding: '1rem',
          fontSize: '0.875rem',
          lineHeight: 1.5,
          background: 'transparent',
        }}
        showLineNumbers={lineCount > 5}
        wrapLines
      >
        {code}
      </SyntaxHighlighter>
    </div>
  );
  
  return (
    <div className="my-3 rounded-lg border bg-muted/30 overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between px-3 py-2 bg-muted/50 border-b">
        <div className="flex items-center gap-2">
          {filename && (
            <span className="text-xs font-mono text-muted-foreground">
              {filename}
            </span>
          )}
          {language && !filename && (
            <span className="text-xs uppercase text-muted-foreground">
              {language}
            </span>
          )}
          {showCollapse && (
            <span className="text-xs text-muted-foreground">
              ({lineCount} lines)
            </span>
          )}
        </div>
        
        <div className="flex items-center gap-1">
          {onExecute && (
            <Button
              variant="ghost"
              size="icon"
              className="h-7 w-7"
              onClick={onExecute}
              title="Execute"
            >
              <Play className="h-3.5 w-3.5" />
            </Button>
          )}
          
          <Button
            variant="ghost"
            size="icon"
            className="h-7 w-7"
            onClick={handleCopy}
            title={copied ? 'Copied!' : 'Copy'}
          >
            {copied ? (
              <Check className="h-3.5 w-3.5 text-green-500" />
            ) : (
              <Copy className="h-3.5 w-3.5" />
            )}
          </Button>
          
          {showCollapse && (
            <Button
              variant="ghost"
              size="icon"
              className="h-7 w-7"
              onClick={() => setIsOpen(!isOpen)}
            >
              {isOpen ? (
                <ChevronUp className="h-3.5 w-3.5" />
              ) : (
                <ChevronDown className="h-3.5 w-3.5" />
              )}
            </Button>
          )}
        </div>
      </div>
      
      {/* Code content */}
      {showCollapse ? (
        <Collapsible open={isOpen} onOpenChange={setIsOpen}>
          <CollapsibleContent>
            {codeContent}
          </CollapsibleContent>
        </Collapsible>
      ) : (
        codeContent
      )}
    </div>
  );
});
```

---

## 3. Streaming Implementation

### 3.1 Streaming Hook

```typescript
// hooks/useMessageStream.ts

import { useState, useCallback, useRef, useEffect } from 'react';
import type { ChatMessage } from '@/types/chat';

interface StreamingOptions {
  sessionId: string;
  onComplete?: (message: ChatMessage) => void;
  onError?: (error: Error) => void;
}

export function useMessageStream({ sessionId, onComplete, onError }: StreamingOptions) {
  const [streamingMessage, setStreamingMessage] = useState<ChatMessage | null>(null);
  const [isStreaming, setIsStreaming] = useState(false);
  const abortControllerRef = useRef<AbortController | null>(null);
  const contentRef = useRef('');
  
  // Start streaming
  const startStream = useCallback(async (
    content: string,
    references: any[]
  ) => {
    // Abort any existing stream
    abortControllerRef.current?.abort();
    abortControllerRef.current = new AbortController();
    
    const messageId = `msg-${Date.now()}`;
    contentRef.current = '';
    
    // Create initial streaming message
    const initialMessage: ChatMessage = {
      id: messageId,
      sessionId,
      role: 'assistant',
      content: '',
      status: 'streaming',
      createdAt: new Date(),
    };
    
    setStreamingMessage(initialMessage);
    setIsStreaming(true);
    
    try {
      const response = await fetch('/api/v1/chat/stream', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          sessionId,
          content,
          references,
        }),
        signal: abortControllerRef.current.signal,
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      
      const reader = response.body?.getReader();
      if (!reader) throw new Error('No response body');
      
      const decoder = new TextDecoder();
      let buffer = '';
      
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        
        buffer += decoder.decode(value, { stream: true });
        
        // Process SSE lines
        const lines = buffer.split('\n');
        buffer = lines.pop() || ''; // Keep incomplete line in buffer
        
        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = line.slice(6);
            if (data === '[DONE]') continue;
            
            try {
              const parsed = JSON.parse(data);
              const delta = parsed.choices?.[0]?.delta?.content;
              
              if (delta) {
                contentRef.current += delta;
                setStreamingMessage(prev => prev ? {
                  ...prev,
                  content: contentRef.current,
                } : null);
              }
            } catch {
              // Ignore parse errors for partial JSON
            }
          }
        }
      }
      
      // Complete the message
      const completeMessage: ChatMessage = {
        ...initialMessage,
        content: contentRef.current,
        status: 'complete',
        completedAt: new Date(),
      };
      
      setStreamingMessage(null);
      setIsStreaming(false);
      onComplete?.(completeMessage);
      
    } catch (error) {
      if ((error as Error).name === 'AbortError') {
        return; // Cancelled, ignore
      }
      
      const errorMessage: ChatMessage = {
        ...initialMessage,
        content: contentRef.current,
        status: 'error',
        error: {
          code: 'STREAM_ERROR',
          message: (error as Error).message,
          retryable: true,
        },
      };
      
      setStreamingMessage(errorMessage);
      setIsStreaming(false);
      onError?.(error as Error);
    }
  }, [sessionId, onComplete, onError]);
  
  // Cancel streaming
  const cancelStream = useCallback(() => {
    abortControllerRef.current?.abort();
    setStreamingMessage(null);
    setIsStreaming(false);
  }, []);
  
  // Cleanup on unmount
  useEffect(() => {
    return () => {
      abortControllerRef.current?.abort();
    };
  }, []);
  
  return {
    streamingMessage,
    isStreaming,
    startStream,
    cancelStream,
  };
}
```

### 3.2 Typing Indicator

```typescript
// components/ai/messages/TypingIndicator.tsx

import { memo } from 'react';
import { Bot } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';

export const TypingIndicator = memo(function TypingIndicator() {
  return (
    <div className="flex gap-3 px-4 py-4 chat-message-enter">
      <Avatar className="h-8 w-8 bg-muted">
        <AvatarFallback>
          <Bot className="h-4 w-4" />
        </AvatarFallback>
      </Avatar>
      
      <div className="flex items-center gap-1.5 px-4 py-3 bg-muted rounded-2xl rounded-bl-sm">
        <div className="typing-indicator flex gap-1">
          <span className="w-2 h-2 bg-foreground/40 rounded-full" />
          <span className="w-2 h-2 bg-foreground/40 rounded-full" />
          <span className="w-2 h-2 bg-foreground/40 rounded-full" />
        </div>
      </div>
    </div>
  );
});
```

---

## 4. Execution Status Component

```typescript
// components/ai/messages/ExecutionStatus.tsx

import { memo } from 'react';
import { Check, X, Loader2, Clock, Terminal, File, FilePlus, FileX, FileEdit } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ExecutionResult, FileChange } from '@/types/chat';

interface ExecutionStatusProps {
  execution: ExecutionResult;
}

export const ExecutionStatus = memo(function ExecutionStatus({
  execution,
}: ExecutionStatusProps) {
  const statusIcons = {
    pending: <Clock className="h-4 w-4 text-muted-foreground" />,
    running: <Loader2 className="h-4 w-4 animate-spin text-primary" />,
    success: <Check className="h-4 w-4 text-green-500" />,
    failed: <X className="h-4 w-4 text-destructive" />,
  };
  
  const statusLabels = {
    pending: 'Pending',
    running: 'Running...',
    success: 'Completed',
    failed: 'Failed',
  };
  
  const fileActionIcons = {
    create: FilePlus,
    modify: FileEdit,
    delete: FileX,
  };
  
  return (
    <div className="mt-2 rounded-lg border bg-muted/30">
      {/* Header */}
      <div className="flex items-center gap-2 px-3 py-2 border-b">
        <Terminal className="h-4 w-4" />
        <span className="text-sm font-medium">Execution</span>
        <Badge
          variant={execution.status === 'success' ? 'default' : 
                   execution.status === 'failed' ? 'destructive' : 'secondary'}
          className="gap-1"
        >
          {statusIcons[execution.status]}
          {statusLabels[execution.status]}
        </Badge>
        {execution.duration && (
          <span className="text-xs text-muted-foreground ml-auto">
            {(execution.duration / 1000).toFixed(2)}s
          </span>
        )}
      </div>
      
      {/* Command */}
      {execution.command && (
        <div className="px-3 py-2 font-mono text-xs bg-muted/50 border-b">
          $ {execution.command}
        </div>
      )}
      
      {/* File changes */}
      {execution.files && execution.files.length > 0 && (
        <div className="divide-y">
          {execution.files.map((file, index) => (
            <FileChangeItem key={index} file={file} />
          ))}
        </div>
      )}
      
      {/* Output */}
      {(execution.output || execution.error) && (
        <Collapsible>
          <CollapsibleTrigger className="flex items-center gap-2 w-full px-3 py-2 text-sm hover:bg-muted/50">
            <span>Output</span>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <pre className={cn(
              'px-3 py-2 text-xs font-mono overflow-x-auto max-h-48',
              execution.error && 'text-destructive'
            )}>
              {execution.error || execution.output}
            </pre>
          </CollapsibleContent>
        </Collapsible>
      )}
    </div>
  );
});

function FileChangeItem({ file }: { file: FileChange }) {
  const Icon = {
    create: FilePlus,
    modify: FileEdit,
    delete: FileX,
  }[file.action];
  
  const statusColors = {
    pending: 'text-muted-foreground',
    applied: 'text-green-500',
    failed: 'text-destructive',
  };
  
  return (
    <div className="flex items-center gap-2 px-3 py-1.5 text-sm">
      <Icon className={cn('h-4 w-4', statusColors[file.status])} />
      <span className="flex-1 font-mono text-xs truncate">{file.path}</span>
      <Badge variant="outline" className="text-xs">
        {file.status}
      </Badge>
    </div>
  );
}
```

---

## 5. Message List Container

```typescript
// components/ai/messages/MessageList.tsx

import { useRef, useEffect, useCallback } from 'react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { MessageBubble } from './MessageBubble';
import { TypingIndicator } from './TypingIndicator';
import { cn } from '@/lib/utils';
import type { ChatMessage } from '@/types/chat';

interface MessageListProps {
  messages: ChatMessage[];
  streamingMessage?: ChatMessage | null;
  isTyping?: boolean;
  onRetry?: (messageId: string) => void;
  className?: string;
}

export function MessageList({
  messages,
  streamingMessage,
  isTyping,
  onRetry,
  className,
}: MessageListProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const bottomRef = useRef<HTMLDivElement>(null);
  
  // Auto-scroll to bottom
  const scrollToBottom = useCallback((smooth = true) => {
    bottomRef.current?.scrollIntoView({
      behavior: smooth ? 'smooth' : 'instant',
    });
  }, []);
  
  // Scroll on new messages
  useEffect(() => {
    scrollToBottom();
  }, [messages.length, streamingMessage?.content, scrollToBottom]);
  
  // All messages including streaming
  const allMessages = streamingMessage
    ? [...messages, streamingMessage]
    : messages;
  
  return (
    <ScrollArea
      ref={scrollRef}
      className={cn('h-full', className)}
    >
      <div className="py-4 space-y-1">
        {allMessages.length === 0 ? (
          <EmptyState />
        ) : (
          <>
            {allMessages.map((message, index) => (
              <MessageBubble
                key={message.id}
                message={message}
                isLatest={index === allMessages.length - 1}
                onRetry={
                  message.status === 'error' && message.error?.retryable
                    ? () => onRetry?.(message.id)
                    : undefined
                }
              />
            ))}
          </>
        )}
        
        {/* Typing indicator */}
        {isTyping && !streamingMessage && <TypingIndicator />}
        
        {/* Scroll anchor */}
        <div ref={bottomRef} />
      </div>
    </ScrollArea>
  );
}

function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center h-64 text-center px-4">
      <div className="text-4xl mb-4">💬</div>
      <h3 className="text-lg font-medium mb-1">Start a conversation</h3>
      <p className="text-muted-foreground text-sm max-w-sm">
        Ask me to help with specifications, generate code, or create execution plans.
      </p>
    </div>
  );
}
```

---

## 6. Testing Requirements

| Test | Description | Priority |
|------|-------------|----------|
| Token streaming | Tokens appear incrementally | Critical |
| Markdown rendering | All markdown elements render | Critical |
| Code highlighting | Syntax highlighting works | High |
| Mermaid rendering | Diagrams render in messages | High |
| Error display | Errors show retry button | High |
| Auto-scroll | List scrolls on new content | High |
| Copy code | Code blocks copyable | Medium |
| Execution status | File changes display correctly | Medium |

---

## Related Specs

- [Chat Layout](./05-01-chat-layout.md)
- [Chat Input](./05-02-chat-input.md)
- [Mode Selector](./05-04-mode-selector.md)
