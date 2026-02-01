# AI Prompt Panel

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

The AI Prompt Panel provides a ChatGPT-like interface for interacting with AI to generate, modify, or enhance specification content. It supports both global (project-wide) and file-scoped operations, voice input, and multi-file batch operations.

---

## 12.1 Panel Scope Modes

### Global Scope Mode
- Operates on entire project or selected folders
- Can generate new files, modify multiple files
- Accessible from project header or keyboard shortcut

### File Scope Mode  
- Operates on currently selected file(s)
- Can modify, enhance, or regenerate content
- Accessible from editor toolbar or context menu

### Multi-File Scope Mode
- Operates on explicitly selected files
- Batch operations with preview per file
- File selector in panel header

---

## 12.2 Panel Layout Variants

### Variant A: Slide-Out Panel (Default)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Project View                                                                │
├─────────────────────────────────────┬───────────────────────────────────────┤
│                                     │  AI Assistant                    [×] │
│  ┌─────────┐                        ├───────────────────────────────────────┤
│  │ Files   │   [Editor Content]     │  Scope: ● Global  ○ Current File     │
│  │ Tree    │                        │                                       │
│  │         │                        │  ┌─────────────────────────────────┐  │
│  │         │                        │  │ 🎤 ░░░░░░░░░░░░░░░░░░░░░░░░░░  │  │
│  │         │                        │  │    Press to record voice input  │  │
│  │         │                        │  └─────────────────────────────────┘  │
│  │         │                        │                                       │
│  │         │                        │  ┌─────────────────────────────────┐  │
│  │         │                        │  │ What would you like me to do?  │  │
│  │         │                        │  │                                 │  │
│  │         │                        │  │                                 │  │
│  │         │                        │  │                          [Send] │  │
│  │         │                        │  └─────────────────────────────────┘  │
│  │         │                        │                                       │
│  │         │                        │  Recent Prompts:                      │
│  │         │                        │  • Generate database schema           │
│  │         │                        │  • Add error handling section         │
│  │         │                        │  • Create API endpoint docs           │
│  │         │                        │                                       │
│  └─────────┘                        └───────────────────────────────────────┘
└─────────────────────────────────────────────────────────────────────────────┘
```

### Variant B: Bottom Drawer

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ← Back   Exam Manager                              [AI ✨] [History] [⚙]  │
├────────────────────┬────────────────────────────────────────────────────────┤
│                    │                                                        │
│  📁 Files          │   [Editor Content]                                    │
│                    │                                                        │
│                    │                                                        │
│                    │                                                        │
├────────────────────┴────────────────────────────────────────────────────────┤
│  AI Assistant                          Scope: [Global ▼]  Files: All  [×]  │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │ Describe what you want to create or modify...                         │ │
│  │                                                                        │ │
│  │                                                    [🎤 Voice] [Send]  │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Variant C: Floating Modal (Focused Mode)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│         ┌─────────────────────────────────────────────────────────┐         │
│         │  AI Assistant                                      [×]  │         │
│         ├─────────────────────────────────────────────────────────┤         │
│         │                                                         │         │
│         │  Scope: ○ Global  ● Selected Files (3)                 │         │
│         │         [02-schema.md, 03-api.md, 04-models.md]        │         │
│         │                                                         │         │
│         │  ┌───────────────────────────────────────────────────┐ │         │
│         │  │                                                   │ │         │
│         │  │  🎤 Recording... (tap to stop)                   │ │         │
│         │  │                                                   │ │         │
│         │  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │ │         │
│         │  │                                                   │ │         │
│         │  └───────────────────────────────────────────────────┘ │         │
│         │                                                         │         │
│         │  ┌───────────────────────────────────────────────────┐ │         │
│         │  │ Add consistent error handling patterns across    │ │         │
│         │  │ all three spec files...                          │ │         │
│         │  └───────────────────────────────────────────────────┘ │         │
│         │                                                         │         │
│         │  [Cancel]                           [Analyze & Preview] │         │
│         │                                                         │         │
│         └─────────────────────────────────────────────────────────┘         │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 12.3 View Mode Preference

Users can select their preferred panel display mode:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  AI Panel Settings                                                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Display Mode                                                                │
│  ─────────────                                                              │
│                                                                              │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐           │
│  │   ┌────┬────┐   │  │   ┌──────────┐   │  │                  │           │
│  │   │    │ AI │   │  │   │          │   │  │   ┌──────────┐   │           │
│  │   │    │    │   │  │   ├──────────┤   │  │   │    AI    │   │           │
│  │   │    │    │   │  │   │    AI    │   │  │   └──────────┘   │           │
│  │   └────┴────┘   │  │   └──────────┘   │  │                  │           │
│  │                  │  │                  │  │                  │           │
│  │   ● Side Panel   │  │   ○ Bottom       │  │   ○ Floating     │           │
│  │                  │  │     Drawer       │  │     Modal        │           │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘           │
│                                                                              │
│  ☑ Remember my preference                                                   │
│  ☐ Show prompt suggestions                                                   │
│  ☐ Auto-expand panel on keyboard shortcut                                    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 12.4 Prompt Input Interface

### Text Input with Voice Toggle

```typescript
// components/ai/PromptInput.tsx
import { useState, useRef } from 'react';
import { Mic, MicOff, Send, Paperclip } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useVoiceRecording } from '@/hooks/useVoiceRecording';

interface PromptInputProps {
  onSubmit: (text: string) => void;
  isProcessing: boolean;
  placeholder?: string;
}

export function PromptInput({ onSubmit, isProcessing, placeholder }: PromptInputProps) {
  const [text, setText] = useState('');
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  
  const {
    isRecording,
    startRecording,
    stopRecording,
    transcription,
  } = useVoiceRecording({
    onTranscriptionComplete: (transcribed) => {
      setText((prev) => prev + (prev ? ' ' : '') + transcribed);
    },
  });

  const handleSubmit = () => {
    if (text.trim() && !isProcessing) {
      onSubmit(text.trim());
      setText('');
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit();
    }
  };

  return (
    <div className="relative">
      {/* Recording Indicator */}
      {isRecording && (
        <div className="absolute -top-12 left-0 right-0 flex items-center justify-center gap-2 p-2 bg-destructive/10 rounded-lg">
          <span className="h-2 w-2 rounded-full bg-destructive animate-pulse" />
          <span className="text-sm text-destructive">Recording...</span>
        </div>
      )}

      <div className="flex items-end gap-2 p-3 border rounded-xl bg-background">
        <Textarea
          ref={textareaRef}
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder ?? 'What would you like me to do?'}
          className="min-h-[60px] max-h-[200px] border-0 resize-none focus-visible:ring-0"
          disabled={isProcessing}
        />

        <div className="flex flex-col gap-1">
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                size="icon"
                variant={isRecording ? 'destructive' : 'ghost'}
                onClick={isRecording ? stopRecording : startRecording}
                disabled={isProcessing}
              >
                {isRecording ? <MicOff className="h-4 w-4" /> : <Mic className="h-4 w-4" />}
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              {isRecording ? 'Stop recording' : 'Voice input'}
            </TooltipContent>
          </Tooltip>

          <Button
            size="icon"
            onClick={handleSubmit}
            disabled={!text.trim() || isProcessing}
          >
            <Send className="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  );
}
```

---

## 12.5 Scope Selector

```typescript
// components/ai/ScopeSelector.tsx
import { useState } from 'react';
import { Globe, FileText, Files, ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { FileNode } from '@/types/file';

type ScopeType = 'global' | 'file' | 'multi';

interface ScopeSelectorProps {
  scope: ScopeType;
  currentFile?: FileNode;
  selectedFiles: FileNode[];
  onScopeChange: (scope: ScopeType) => void;
  onOpenFileSelector: () => void;
}

export function ScopeSelector({
  scope,
  currentFile,
  selectedFiles,
  onScopeChange,
  onOpenFileSelector,
}: ScopeSelectorProps) {
  const scopeLabel = {
    global: 'Global (Project-wide)',
    file: `Current File: ${currentFile?.name ?? 'None'}`,
    multi: `${selectedFiles.length} Files Selected`,
  }[scope];

  const ScopeIcon = {
    global: Globe,
    file: FileText,
    multi: Files,
  }[scope];

  return (
    <div className="flex items-center gap-2">
      <span className="text-sm text-foreground-muted">Scope:</span>
      
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" size="sm" className="gap-2">
            <ScopeIcon className="h-4 w-4" />
            <span className="max-w-[200px] truncate">{scopeLabel}</span>
            <ChevronDown className="h-3 w-3" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
          <DropdownMenuItem onClick={() => onScopeChange('global')}>
            <Globe className="h-4 w-4 mr-2" />
            Global (Project-wide)
          </DropdownMenuItem>
          
          {currentFile && (
            <DropdownMenuItem onClick={() => onScopeChange('file')}>
              <FileText className="h-4 w-4 mr-2" />
              Current File: {currentFile.name}
            </DropdownMenuItem>
          )}
          
          <DropdownMenuSeparator />
          
          <DropdownMenuItem onClick={onOpenFileSelector}>
            <Files className="h-4 w-4 mr-2" />
            Select Multiple Files...
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      {/* Selected Files Badges (Multi-mode) */}
      {scope === 'multi' && selectedFiles.length > 0 && (
        <div className="flex flex-wrap gap-1 ml-2">
          {selectedFiles.slice(0, 3).map((file) => (
            <Badge key={file.id} variant="secondary" className="text-xs">
              {file.name}
            </Badge>
          ))}
          {selectedFiles.length > 3 && (
            <Badge variant="outline" className="text-xs">
              +{selectedFiles.length - 3} more
            </Badge>
          )}
        </div>
      )}
    </div>
  );
}
```

---

## 12.6 Multi-File Selection Modal

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Select Files                                                          [×] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Search: [____________________________]                                     │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  ☐ Select All (42 files)                                              │ │
│  ├────────────────────────────────────────────────────────────────────────┤ │
│  │  📁 01-backend/                                                        │ │
│  │     ☑ 01-overview.md                                                  │ │
│  │     ☑ 02-database-schema.md                                           │ │
│  │     ☐ 03-api-endpoints.md                                             │ │
│  │     ☐ 04-file-operations.md                                           │ │
│  │  📁 02-frontend/                                                       │ │
│  │     ☑ 01-overview.md                                                  │ │
│  │     ☐ 02-theme-system.md                                              │ │
│  │     ☐ ...                                                             │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  Selected: 4 files                                                          │
│                                                                              │
│                                               [Cancel]  [Apply Selection]   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 12.7 Generation Preview (Multi-File)

When AI generates changes for multiple files:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Preview Changes                                                       [×] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  AI will modify 3 files:                                                     │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  📄 02-database-schema.md                              [View Changes]  │ │
│  │     + Add error handling table (12 lines added)                        │ │
│  ├────────────────────────────────────────────────────────────────────────┤ │
│  │  📄 03-api-endpoints.md                                [View Changes]  │ │
│  │     + Add error response codes section (28 lines added)                │ │
│  ├────────────────────────────────────────────────────────────────────────┤ │
│  │  📄 04-validation.md                              [NEW] [View Preview] │ │
│  │     New file: Validation error handling spec                          │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  ───────────────────────────────────────────────────────────────────────    │
│                                                                              │
│  ☑ Create snapshot before applying                                          │
│                                                                              │
│  [Cancel]  [Apply Selected]  [Apply All Changes]                            │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 12.8 Prompt Panel Component

```typescript
// components/ai/PromptPanel.tsx
import { useState, useCallback } from 'react';
import { X, Settings2, History } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PromptInput } from './PromptInput';
import { ScopeSelector } from './ScopeSelector';
import { ConversationThread } from './ConversationThread';
import { GenerationPreview } from './GenerationPreview';
import { PromptSuggestions } from './PromptSuggestions';
import { FileSelectionModal } from './FileSelectionModal';
import { useAIPrompt } from '@/hooks/useAIPrompt';
import { useUserPreferences } from '@/hooks/useUserPreferences';
import { FileNode } from '@/types/file';

type PanelView = 'chat' | 'preview' | 'history';
type ScopeType = 'global' | 'file' | 'multi';

interface PromptPanelProps {
  projectId: string;
  currentFile?: FileNode;
  onClose: () => void;
  onApplyChanges: (changes: FileChange[]) => Promise<void>;
}

export function PromptPanel({ projectId, currentFile, onClose, onApplyChanges }: PromptPanelProps) {
  const [view, setView] = useState<PanelView>('chat');
  const [scope, setScope] = useState<ScopeType>(currentFile ? 'file' : 'global');
  const [selectedFiles, setSelectedFiles] = useState<FileNode[]>([]);
  const [isFileModalOpen, setIsFileModalOpen] = useState(false);
  
  const { preferences } = useUserPreferences();
  
  const {
    messages,
    isProcessing,
    generatedChanges,
    submitPrompt,
    clearConversation,
  } = useAIPrompt({
    projectId,
    scope,
    currentFile,
    selectedFiles,
  });

  const handleSubmit = useCallback((text: string) => {
    submitPrompt(text);
  }, [submitPrompt]);

  const handleApply = useCallback(async () => {
    if (generatedChanges) {
      await onApplyChanges(generatedChanges);
      setView('chat');
    }
  }, [generatedChanges, onApplyChanges]);

  const handleFileSelection = useCallback((files: FileNode[]) => {
    setSelectedFiles(files);
    setScope('multi');
    setIsFileModalOpen(false);
  }, []);

  return (
    <div className="h-full flex flex-col bg-card border-l">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b">
        <h2 className="text-lg font-semibold">AI Assistant</h2>
        <div className="flex items-center gap-1">
          <Button variant="ghost" size="icon" onClick={() => setView('history')}>
            <History className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon">
            <Settings2 className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" onClick={onClose}>
            <X className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Scope Selector */}
      <div className="px-4 py-2 border-b bg-muted/30">
        <ScopeSelector
          scope={scope}
          currentFile={currentFile}
          selectedFiles={selectedFiles}
          onScopeChange={setScope}
          onOpenFileSelector={() => setIsFileModalOpen(true)}
        />
      </div>

      {/* Content Area */}
      <Tabs value={view} onValueChange={(v) => setView(v as PanelView)} className="flex-1 flex flex-col">
        <TabsList className="mx-4 mt-2 grid w-auto grid-cols-3">
          <TabsTrigger value="chat">Chat</TabsTrigger>
          <TabsTrigger value="preview" disabled={!generatedChanges}>
            Preview {generatedChanges && `(${generatedChanges.length})`}
          </TabsTrigger>
          <TabsTrigger value="history">History</TabsTrigger>
        </TabsList>

        <TabsContent value="chat" className="flex-1 flex flex-col m-0">
          <ScrollArea className="flex-1 p-4">
            {messages.length === 0 ? (
              <PromptSuggestions
                scope={scope}
                onSelect={handleSubmit}
              />
            ) : (
              <ConversationThread messages={messages} />
            )}
          </ScrollArea>
          
          <div className="p-4 border-t">
            <PromptInput
              onSubmit={handleSubmit}
              isProcessing={isProcessing}
              placeholder={
                scope === 'global'
                  ? 'Describe changes for the entire project...'
                  : scope === 'file'
                    ? `Describe changes for ${currentFile?.name}...`
                    : `Describe changes for ${selectedFiles.length} files...`
              }
            />
          </div>
        </TabsContent>

        <TabsContent value="preview" className="flex-1 m-0">
          {generatedChanges && (
            <GenerationPreview
              changes={generatedChanges}
              onApply={handleApply}
              onApplySelected={(ids) => {/* Apply subset */}}
              onDiscard={() => setView('chat')}
            />
          )}
        </TabsContent>

        <TabsContent value="history" className="flex-1 m-0">
          <PromptHistory
            projectId={projectId}
            onReuse={handleSubmit}
          />
        </TabsContent>
      </Tabs>

      {/* File Selection Modal */}
      <FileSelectionModal
        isOpen={isFileModalOpen}
        onClose={() => setIsFileModalOpen(false)}
        projectId={projectId}
        selectedFiles={selectedFiles}
        onSelect={handleFileSelection}
      />
    </div>
  );
}
```

---

## 12.9 Prompt Suggestions

```typescript
// components/ai/PromptSuggestions.tsx
import { Lightbulb, FileText, Code, CheckSquare, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface PromptSuggestionsProps {
  scope: 'global' | 'file' | 'multi';
  onSelect: (prompt: string) => void;
}

export function PromptSuggestions({ scope, onSelect }: PromptSuggestionsProps) {
  const suggestions = SUGGESTIONS_BY_SCOPE[scope];

  return (
    <div className="space-y-4">
      <div className="text-center py-4">
        <Lightbulb className="h-8 w-8 mx-auto mb-2 text-primary/60" />
        <h3 className="font-medium">How can I help?</h3>
        <p className="text-sm text-foreground-muted">
          Choose a suggestion or type your own prompt
        </p>
      </div>

      <div className="grid gap-2">
        {suggestions.map((suggestion) => (
          <Card
            key={suggestion.prompt}
            className="cursor-pointer hover:bg-muted/50 transition-colors"
            onClick={() => onSelect(suggestion.prompt)}
          >
            <CardHeader className="p-3 pb-1">
              <div className="flex items-center gap-2">
                <suggestion.icon className="h-4 w-4 text-primary" />
                <CardTitle className="text-sm">{suggestion.title}</CardTitle>
              </div>
            </CardHeader>
            <CardContent className="p-3 pt-0">
              <CardDescription className="text-xs">
                {suggestion.description}
              </CardDescription>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}

const SUGGESTIONS_BY_SCOPE = {
  global: [
    {
      icon: FileText,
      title: 'Generate Overview',
      description: 'Create a comprehensive project overview document',
      prompt: 'Generate a project overview document that summarizes the purpose, architecture, and key components of this specification',
    },
    {
      icon: Code,
      title: 'Add Database Schema',
      description: 'Create database tables and relationships spec',
      prompt: 'Generate a database schema specification with tables, relationships, and field definitions',
    },
    {
      icon: CheckSquare,
      title: 'Create Acceptance Criteria',
      description: 'Add testable requirements across all specs',
      prompt: 'Add acceptance criteria sections to all specification files that currently lack them',
    },
    {
      icon: AlertTriangle,
      title: 'Add Error Handling',
      description: 'Document error codes and recovery patterns',
      prompt: 'Create an error handling specification covering error codes, messages, and recovery procedures',
    },
  ],
  file: [
    {
      icon: FileText,
      title: 'Expand Section',
      description: 'Add more detail to existing content',
      prompt: 'Expand the current content with more detailed explanations and examples',
    },
    {
      icon: Code,
      title: 'Add Code Examples',
      description: 'Insert implementation examples',
      prompt: 'Add code examples to illustrate the concepts described in this specification',
    },
    {
      icon: CheckSquare,
      title: 'Add Acceptance Criteria',
      description: 'Create testable requirements',
      prompt: 'Add an acceptance criteria section with specific, testable requirements',
    },
    {
      icon: AlertTriangle,
      title: 'Review & Improve',
      description: 'Identify gaps and inconsistencies',
      prompt: 'Review this specification for gaps, inconsistencies, or missing information and suggest improvements',
    },
  ],
  multi: [
    {
      icon: FileText,
      title: 'Standardize Format',
      description: 'Apply consistent structure across files',
      prompt: 'Standardize the format and structure of all selected files to follow the same template',
    },
    {
      icon: Code,
      title: 'Cross-Reference',
      description: 'Add links between related sections',
      prompt: 'Add cross-references and links between related sections in the selected files',
    },
    {
      icon: CheckSquare,
      title: 'Consistency Check',
      description: 'Find and fix inconsistencies',
      prompt: 'Check for inconsistencies in terminology, naming, and conventions across the selected files',
    },
    {
      icon: AlertTriangle,
      title: 'Batch Update Pattern',
      description: 'Apply same change to all files',
      prompt: 'I want to apply the same pattern or change to all selected files. Here is what I need:',
    },
  ],
};
```

---

## 12.10 User Preference Storage

```typescript
// types/preferences.ts
interface AIPromptPreferences {
  panelDisplayMode: 'side' | 'bottom' | 'modal';
  showPromptSuggestions: boolean;
  autoExpandOnShortcut: boolean;
  defaultScope: 'global' | 'file';
  voiceInputEnabled: boolean;
  recentPrompts: string[];
  maxRecentPrompts: number;
}

// hooks/useAIPromptPreferences.ts
import { useUserPreferences } from './useUserPreferences';

export function useAIPromptPreferences() {
  const { preferences, updatePreferences } = useUserPreferences();
  
  const aiPrefs = preferences?.ai ?? DEFAULT_AI_PREFERENCES;

  const updateAIPrefs = (updates: Partial<AIPromptPreferences>) => {
    updatePreferences({
      ...preferences,
      ai: { ...aiPrefs, ...updates },
    });
  };

  const addRecentPrompt = (prompt: string) => {
    const recent = [prompt, ...aiPrefs.recentPrompts.filter((p) => p !== prompt)]
      .slice(0, aiPrefs.maxRecentPrompts);
    updateAIPrefs({ recentPrompts: recent });
  };

  return {
    ...aiPrefs,
    updateAIPrefs,
    addRecentPrompt,
  };
}

const DEFAULT_AI_PREFERENCES: AIPromptPreferences = {
  panelDisplayMode: 'side',
  showPromptSuggestions: true,
  autoExpandOnShortcut: true,
  defaultScope: 'global',
  voiceInputEnabled: true,
  recentPrompts: [],
  maxRecentPrompts: 20,
};
```

---

## 12.11 Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl/Cmd + K` | Open AI Prompt Panel (Global scope) |
| `Ctrl/Cmd + Shift + K` | Open AI Prompt Panel (Current file scope) |
| `Ctrl/Cmd + Enter` | Submit prompt |
| `Escape` | Close panel |
| `Ctrl/Cmd + M` | Toggle voice recording |

---

## 12.12 Acceptance Criteria

### Panel Display

- [ ] Panel available in 3 display modes (side, bottom, modal)
- [ ] Display mode preference persisted per user
- [ ] Keyboard shortcut opens panel in preferred mode
- [ ] Panel closes on Escape key

### Scope Selection

- [ ] Global scope operates on entire project
- [ ] File scope operates on current open file
- [ ] Multi-file scope allows selecting multiple files
- [ ] Scope indicator shows current mode and files
- [ ] File selection modal with search and checkboxes

### Input Interface

- [ ] Text input with multi-line support
- [ ] Voice recording with visual indicator
- [ ] Submit with button or Ctrl+Enter
- [ ] Prompt history accessible via tab
- [ ] Recent prompts shown as suggestions

### Generation & Preview

- [ ] Shows progress during generation
- [ ] Multi-file changes shown in list
- [ ] Individual file changes expandable
- [ ] Apply all or apply selected options
- [ ] Snapshot created before applying

### Voice Input

- [ ] Recording indicator visible during capture
- [ ] Transcription appended to text input
- [ ] Stop recording with button or shortcut
- [ ] Voice toggle remembers preference

---

## Related Specs

- [AI Integration Overview](./00-overview.md)
- [AI Chat UI](./08-ai-chat-ui.md)
- [Voice Input](../05-voice-input/00-overview.md)
