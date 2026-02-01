# AI Chat Panel

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

The AI Chat Panel provides an interactive interface for the AI reasoning chain, displaying questions, collecting answers, showing generation progress, and previewing/accepting generated content.

---

## 8.1 Layout Structure

```
┌─────────────────────────────────────────────────────────────────────────┐
│  AI Assistant                                            [Settings] [×] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  🤖 I've analyzed your request. To create the best specification, │ │
│  │     I have a few questions:                                        │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  Q1: Which OAuth providers should be supported?              [1/3] │ │
│  │  ──────────────────────────────────────────────────────────────── │ │
│  │  ○ Google                                                         │ │
│  │  ○ GitHub                                                         │ │
│  │  ○ Microsoft                                                      │ │
│  │  ● All of the above                                               │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  Q2: Should password authentication remain as fallback?      [2/3] │ │
│  │  ──────────────────────────────────────────────────────────────── │ │
│  │  [Yes]  [No]                                                       │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  Q3: Any additional requirements?                            [3/3] │ │
│  │  ──────────────────────────────────────────────────────────────── │ │
│  │  ┌─────────────────────────────────────────────────────────────┐  │ │
│  │  │ Support for role-based access after OAuth login...        │  │ │
│  │  └─────────────────────────────────────────────────────────────┘  │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Generate: [Idea Document]  [Full Specification]                │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 8.2 Generation Progress State

```
┌─────────────────────────────────────────────────────────────────────────┐
│  AI Assistant                                            [Settings] [×] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  🔄 Generating specification...                                    │ │
│  │                                                                    │ │
│  │  ━━━━━━━━━━━━━━━━━━━━━━░░░░░░░░░░░░░░░░░░░░░░░░░░ 45%              │ │
│  │                                                                    │ │
│  │  Currently: Writing API endpoints section...                       │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  Live Preview:                                                     │ │
│  │  ──────────────────────────────────────────────────────────────── │ │
│  │  # OAuth Integration                                               │ │
│  │                                                                    │ │
│  │  ## 1.1 Overview                                                   │ │
│  │                                                                    │ │
│  │  This specification defines the OAuth 2.0 integration...          │ │
│  │  █                                                                 │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  [Cancel]                                                               │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 8.3 Preview/Accept State

```
┌─────────────────────────────────────────────────────────────────────────┐
│  AI Assistant                                            [Settings] [×] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  ✅ Specification generated successfully!                          │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  Preview:                                                [Scroll] │ │
│  │  ──────────────────────────────────────────────────────────────── │ │
│  │  │ # OAuth Integration                                           │ │
│  │  │                                                               │ │
│  │  │ ## 1.1 Overview                                               │ │
│  │  │                                                               │ │
│  │  │ This specification defines the OAuth 2.0 integration          │ │
│  │  │ for the authentication system, supporting Google, GitHub,     │ │
│  │  │ and Microsoft providers while maintaining password auth       │ │
│  │  │ as a fallback option.                                         │ │
│  │  │                                                               │ │
│  │  │ ## 1.2 Supported Providers                                    │ │
│  │  │                                                               │ │
│  │  │ | Provider | Client ID | Scopes |                             │ │
│  │  │ |----------|-----------|--------|                             │ │
│  │  │ | Google   | env.GOOGLE_CLIENT_ID | profile, email |          │ │
│  │  │ ...                                                           │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  Save as: [03-oauth-integration.md        ]  in [spec/auth  ▼]         │
│                                                                         │
│  [Accept & Save]  [Edit First]  [Regenerate]  [Discard]                 │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 8.4 ChatPanel Component

```typescript
// components/ai/ChatPanel.tsx
import { useState, useCallback } from 'react';
import { X, Settings, Loader2, Check, RefreshCw, Edit2, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { VoiceInput } from './VoiceInput';
import { QuestionList } from './QuestionList';
import { GenerationProgress } from './GenerationProgress';
import { ContentPreview } from './ContentPreview';
import { AISettingsModal } from './AISettingsModal';
import { useAIChain } from '@/hooks/useAIChain';
import { Question, GenerationResult } from '@/types/ai';

type ChatState = 'input' | 'analyzing' | 'questions' | 'generating' | 'preview';

interface ChatPanelProps {
  projectId: string;
  onClose: () => void;
  onSave: (filename: string, content: string, folder: string) => Promise<void>;
}

export function ChatPanel({ projectId, onClose, onSave }: ChatPanelProps) {
  const [state, setState] = useState<ChatState>('input');
  const [inputText, setInputText] = useState('');
  const [questions, setQuestions] = useState<Question[]>([]);
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [generatedContent, setGeneratedContent] = useState<GenerationResult | null>(null);
  const [isSettingsOpen, setIsSettingsOpen] = useState(false);
  
  const {
    analyze,
    generate,
    isAnalyzing,
    isGenerating,
    generationProgress,
    streamedContent,
    cancel,
  } = useAIChain(projectId);

  const handleTranscriptionComplete = useCallback(async (text: string) => {
    setInputText(text);
    setState('analyzing');
    
    try {
      const result = await analyze(text);
      setQuestions(result.questions);
      setAnswers({});
      setState('questions');
    } catch (error) {
      console.error('Analysis failed:', error);
      setState('input');
    }
  }, [analyze]);

  const handleAnswerChange = useCallback((questionId: string, value: string) => {
    setAnswers((prev) => ({ ...prev, [questionId]: value }));
  }, []);

  const handleGenerate = useCallback(async (outputType: 'idea' | 'spec') => {
    setState('generating');
    
    try {
      const result = await generate({
        intent: inputText,
        answers,
        outputType,
      });
      setGeneratedContent(result);
      setState('preview');
    } catch (error) {
      console.error('Generation failed:', error);
      setState('questions');
    }
  }, [inputText, answers, generate]);

  const handleAccept = useCallback(async (filename: string, folder: string) => {
    if (!generatedContent) return;
    
    await onSave(filename, generatedContent.content, folder);
    
    // Reset state
    setState('input');
    setInputText('');
    setQuestions([]);
    setAnswers({});
    setGeneratedContent(null);
  }, [generatedContent, onSave]);

  const handleRegenerate = useCallback(() => {
    setState('questions');
    setGeneratedContent(null);
  }, []);

  const handleDiscard = useCallback(() => {
    setState('input');
    setInputText('');
    setQuestions([]);
    setAnswers({});
    setGeneratedContent(null);
  }, []);

  const allQuestionsAnswered = questions
    .filter((q) => q.required)
    .every((q) => answers[q.id]);

  return (
    <div className="h-full flex flex-col bg-card border-l">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b">
        <h2 className="text-lg font-semibold">AI Assistant</h2>
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="icon" onClick={() => setIsSettingsOpen(true)}>
            <Settings className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" onClick={onClose}>
            <X className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Content */}
      <ScrollArea className="flex-1 p-4">
        {/* Input State */}
        {state === 'input' && (
          <div className="space-y-4">
            <p className="text-sm text-foreground-muted">
              Describe what you want to create. Use voice input or type below.
            </p>
            <VoiceInput onTranscriptionComplete={handleTranscriptionComplete} />
            <div className="relative">
              <textarea
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                placeholder="Or type your request here..."
                className="w-full h-32 p-3 rounded-lg border bg-background resize-none"
              />
              {inputText && (
                <Button
                  size="sm"
                  className="absolute bottom-3 right-3"
                  onClick={() => handleTranscriptionComplete(inputText)}
                >
                  Analyze
                </Button>
              )}
            </div>
          </div>
        )}

        {/* Analyzing State */}
        {state === 'analyzing' && (
          <div className="flex flex-col items-center justify-center py-12 gap-4">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
            <p className="text-sm">Analyzing your request...</p>
          </div>
        )}

        {/* Questions State */}
        {state === 'questions' && (
          <div className="space-y-4">
            <div className="p-3 rounded-lg bg-muted">
              <p className="text-sm">
                🤖 I've analyzed your request. To create the best specification,
                I have a few questions:
              </p>
            </div>

            <QuestionList
              questions={questions}
              answers={answers}
              onAnswerChange={handleAnswerChange}
            />

            <div className="flex gap-2 pt-4 border-t">
              <Button
                onClick={() => handleGenerate('idea')}
                disabled={!allQuestionsAnswered}
                variant="outline"
              >
                Generate Idea
              </Button>
              <Button
                onClick={() => handleGenerate('spec')}
                disabled={!allQuestionsAnswered}
              >
                Generate Specification
              </Button>
            </div>
          </div>
        )}

        {/* Generating State */}
        {state === 'generating' && (
          <GenerationProgress
            progress={generationProgress}
            streamedContent={streamedContent}
            onCancel={cancel}
          />
        )}

        {/* Preview State */}
        {state === 'preview' && generatedContent && (
          <ContentPreview
            content={generatedContent.content}
            outputType={generatedContent.outputType}
            projectId={projectId}
            onAccept={handleAccept}
            onEdit={() => {/* Open in editor */}}
            onRegenerate={handleRegenerate}
            onDiscard={handleDiscard}
          />
        )}
      </ScrollArea>

      {/* Settings Modal */}
      <AISettingsModal
        isOpen={isSettingsOpen}
        onClose={() => setIsSettingsOpen(false)}
      />
    </div>
  );
}
```

---

## 8.5 QuestionList Component

```typescript
// components/ai/QuestionList.tsx
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Question } from '@/types/ai';

interface QuestionListProps {
  questions: Question[];
  answers: Record<string, string>;
  onAnswerChange: (questionId: string, value: string) => void;
}

export function QuestionList({ questions, answers, onAnswerChange }: QuestionListProps) {
  return (
    <div className="space-y-4">
      {questions.map((question, index) => (
        <div key={question.id} className="p-4 rounded-lg border">
          <div className="flex items-center justify-between mb-3">
            <h4 className="font-medium text-sm">
              Q{index + 1}: {question.text}
            </h4>
            <span className="text-xs text-foreground-muted">
              [{index + 1}/{questions.length}]
            </span>
          </div>

          {question.type === 'text' && (
            <Textarea
              value={answers[question.id] || ''}
              onChange={(e) => onAnswerChange(question.id, e.target.value)}
              placeholder="Type your answer..."
              rows={3}
            />
          )}

          {question.type === 'choice' && question.options && (
            <RadioGroup
              value={answers[question.id]}
              onValueChange={(value) => onAnswerChange(question.id, value)}
            >
              {question.options.map((option) => (
                <div key={option} className="flex items-center space-x-2">
                  <RadioGroupItem value={option} id={`${question.id}-${option}`} />
                  <Label htmlFor={`${question.id}-${option}`}>{option}</Label>
                </div>
              ))}
            </RadioGroup>
          )}

          {question.type === 'confirm' && (
            <div className="flex gap-2">
              <Button
                variant={answers[question.id] === 'true' ? 'default' : 'outline'}
                size="sm"
                onClick={() => onAnswerChange(question.id, 'true')}
              >
                Yes
              </Button>
              <Button
                variant={answers[question.id] === 'false' ? 'default' : 'outline'}
                size="sm"
                onClick={() => onAnswerChange(question.id, 'false')}
              >
                No
              </Button>
            </div>
          )}

          {question.required && !answers[question.id] && (
            <p className="text-xs text-destructive mt-2">* Required</p>
          )}
        </div>
      ))}
    </div>
  );
}
```

---

## 8.6 GenerationProgress Component

```typescript
// components/ai/GenerationProgress.tsx
import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import ReactMarkdown from 'react-markdown';

interface GenerationProgressProps {
  progress: number;
  streamedContent: string;
  onCancel: () => void;
}

export function GenerationProgress({
  progress,
  streamedContent,
  onCancel,
}: GenerationProgressProps) {
  return (
    <div className="space-y-4">
      <div className="p-4 rounded-lg bg-muted">
        <div className="flex items-center gap-3 mb-3">
          <Loader2 className="h-5 w-5 animate-spin text-primary" />
          <span className="font-medium">Generating specification...</span>
        </div>
        <Progress value={progress} className="mb-2" />
        <p className="text-xs text-foreground-muted">
          {progress < 30 && 'Analyzing context...'}
          {progress >= 30 && progress < 60 && 'Writing content sections...'}
          {progress >= 60 && progress < 90 && 'Adding code examples...'}
          {progress >= 90 && 'Finalizing document...'}
        </p>
      </div>

      {streamedContent && (
        <div className="border rounded-lg">
          <div className="px-3 py-2 border-b bg-muted/50 text-sm font-medium">
            Live Preview
          </div>
          <ScrollArea className="h-64">
            <div className="p-4 prose prose-sm dark:prose-invert max-w-none">
              <ReactMarkdown>{streamedContent}</ReactMarkdown>
              <span className="inline-block w-2 h-4 bg-primary animate-pulse" />
            </div>
          </ScrollArea>
        </div>
      )}

      <Button variant="outline" onClick={onCancel}>
        Cancel
      </Button>
    </div>
  );
}
```

---

## 8.7 ContentPreview Component

```typescript
// components/ai/ContentPreview.tsx
import { useState } from 'react';
import { Check, Edit2, RefreshCw, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import ReactMarkdown from 'react-markdown';
import { useFiles } from '@/hooks/useFiles';

interface ContentPreviewProps {
  content: string;
  outputType: 'idea' | 'spec';
  projectId: string;
  onAccept: (filename: string, folder: string) => void;
  onEdit: () => void;
  onRegenerate: () => void;
  onDiscard: () => void;
}

export function ContentPreview({
  content,
  outputType,
  projectId,
  onAccept,
  onEdit,
  onRegenerate,
  onDiscard,
}: ContentPreviewProps) {
  const { folders } = useFiles(projectId);
  const [filename, setFilename] = useState(
    outputType === 'idea' ? 'new-idea.md' : '00-new-spec.md'
  );
  const [selectedFolder, setSelectedFolder] = useState('');

  return (
    <div className="space-y-4">
      <div className="p-3 rounded-lg bg-success/10 text-success flex items-center gap-2">
        <Check className="h-4 w-4" />
        <span className="text-sm font-medium">
          {outputType === 'idea' ? 'Idea' : 'Specification'} generated successfully!
        </span>
      </div>

      {/* Preview */}
      <div className="border rounded-lg">
        <div className="px-3 py-2 border-b bg-muted/50 flex items-center justify-between">
          <span className="text-sm font-medium">Preview</span>
          <span className="text-xs text-foreground-muted">Scroll to view</span>
        </div>
        <ScrollArea className="h-80">
          <div className="p-4 prose prose-sm dark:prose-invert max-w-none">
            <ReactMarkdown>{content}</ReactMarkdown>
          </div>
        </ScrollArea>
      </div>

      {/* Save Options */}
      <div className="space-y-3">
        <div className="grid grid-cols-2 gap-3">
          <div>
            <Label htmlFor="filename" className="text-xs">Filename</Label>
            <Input
              id="filename"
              value={filename}
              onChange={(e) => setFilename(e.target.value)}
              placeholder="filename.md"
            />
          </div>
          <div>
            <Label htmlFor="folder" className="text-xs">Folder</Label>
            <Select value={selectedFolder} onValueChange={setSelectedFolder}>
              <SelectTrigger id="folder">
                <SelectValue placeholder="Select folder" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">Root</SelectItem>
                {folders.map((folder) => (
                  <SelectItem key={folder.id} value={folder.path}>
                    {folder.path}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>

      {/* Actions */}
      <div className="flex flex-wrap gap-2">
        <Button onClick={() => onAccept(filename, selectedFolder)}>
          <Check className="h-4 w-4 mr-1" />
          Accept & Save
        </Button>
        <Button variant="outline" onClick={onEdit}>
          <Edit2 className="h-4 w-4 mr-1" />
          Edit First
        </Button>
        <Button variant="outline" onClick={onRegenerate}>
          <RefreshCw className="h-4 w-4 mr-1" />
          Regenerate
        </Button>
        <Button variant="ghost" onClick={onDiscard}>
          <Trash2 className="h-4 w-4 mr-1" />
          Discard
        </Button>
      </div>
    </div>
  );
}
```

---

## 8.8 useAIChain Hook

```typescript
// hooks/useAIChain.ts
import { useState, useCallback, useRef } from 'react';
import { aiApi } from '@/api/ai';
import { AnalyzeResult, GenerateRequest, GenerateResult } from '@/types/ai';

export function useAIChain(projectId: string) {
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);
  const [generationProgress, setGenerationProgress] = useState(0);
  const [streamedContent, setStreamedContent] = useState('');
  const abortControllerRef = useRef<AbortController | null>(null);

  const analyze = useCallback(async (text: string): Promise<AnalyzeResult> => {
    setIsAnalyzing(true);
    try {
      const result = await aiApi.analyze({
        text,
        existingSpecs: [], // Could fetch from project context
      });
      return result;
    } finally {
      setIsAnalyzing(false);
    }
  }, []);

  const generate = useCallback(async (request: GenerateRequest): Promise<GenerateResult> => {
    setIsGenerating(true);
    setGenerationProgress(0);
    setStreamedContent('');

    abortControllerRef.current = new AbortController();

    try {
      // Use streaming endpoint
      const response = await fetch('/api/v1/ai/generate/stream', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(request),
        signal: abortControllerRef.current.signal,
      });

      const reader = response.body?.getReader();
      if (!reader) throw new Error('No response body');

      const decoder = new TextDecoder();
      let content = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        const chunk = decoder.decode(value);
        const lines = chunk.split('\n');

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const data = JSON.parse(line.slice(6));
            
            if (data.type === 'progress') {
              setGenerationProgress(data.progress);
            } else if (data.type === 'content') {
              content += data.content;
              setStreamedContent(content);
            } else if (data.type === 'done') {
              return {
                content,
                outputType: request.outputType,
              };
            }
          }
        }
      }

      return { content, outputType: request.outputType };
    } finally {
      setIsGenerating(false);
      abortControllerRef.current = null;
    }
  }, []);

  const cancel = useCallback(() => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
  }, []);

  return {
    analyze,
    generate,
    cancel,
    isAnalyzing,
    isGenerating,
    generationProgress,
    streamedContent,
  };
}
```

---

## 8.9 AI Settings Modal

```typescript
// components/ai/AISettingsModal.tsx
import { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { useAIConfig } from '@/hooks/useAIConfig';

interface AISettingsModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function AISettingsModal({ isOpen, onClose }: AISettingsModalProps) {
  const { config, models, updateConfig, isLoading } = useAIConfig();
  const [localConfig, setLocalConfig] = useState(config);

  useEffect(() => {
    setLocalConfig(config);
  }, [config]);

  const handleSave = async () => {
    await updateConfig(localConfig);
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>AI Settings</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div>
            <Label>Voice Model</Label>
            <Select
              value={localConfig?.voiceModel}
              onValueChange={(v) => setLocalConfig((c) => ({ ...c!, voiceModel: v }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select model" />
              </SelectTrigger>
              <SelectContent>
                {models
                  .filter((m) => m.name.includes('whisper'))
                  .map((model) => (
                    <SelectItem key={model.name} value={model.name}>
                      {model.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>

          <div>
            <Label>Reasoning Model</Label>
            <Select
              value={localConfig?.reasoningModel}
              onValueChange={(v) => setLocalConfig((c) => ({ ...c!, reasoningModel: v }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select model" />
              </SelectTrigger>
              <SelectContent>
                {models
                  .filter((m) => !m.name.includes('whisper'))
                  .map((model) => (
                    <SelectItem key={model.name} value={model.name}>
                      {model.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>

          <div>
            <Label>Context Size</Label>
            <Input
              type="number"
              value={localConfig?.contextSize}
              onChange={(e) =>
                setLocalConfig((c) => ({ ...c!, contextSize: parseInt(e.target.value) }))
              }
            />
          </div>

          <div>
            <Label>GPU Layers</Label>
            <Input
              type="number"
              value={localConfig?.gpuLayers}
              onChange={(e) =>
                setLocalConfig((c) => ({ ...c!, gpuLayers: parseInt(e.target.value) }))
              }
            />
          </div>
        </div>

        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={isLoading}>
            Save Settings
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
```

---

## 8.10 Model Selector Component

The Model Selector provides UI for selecting AI models at different hierarchy levels.

### Model Selection Hierarchy UI

```
┌─────────────────────────────────────────────────────────────────────────┐
│  AI Settings                                                      [×]   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Your Default Models                                             │   │
│  │  ──────────────────────────────────────────────────────────────  │   │
│  │                                                                   │   │
│  │  Reasoning Model:  [Llama 3 70B Instruct           ▼]            │   │
│  │                    System default: Mixtral 8x7B                   │   │
│  │                                                                   │   │
│  │  Voice Model:      [Whisper Large V3               ▼]            │   │
│  │                    System default: Whisper Large V3               │   │
│  │                                                                   │   │
│  │  ☐ Use system defaults                                           │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Active Model Slots                                        [⟳]   │   │
│  │  ──────────────────────────────────────────────────────────────  │   │
│  │                                                                   │   │
│  │  ● Slot 0 (:8080)  Whisper Large V3      active   2h 15m        │   │
│  │  ● Slot 1 (:8081)  Llama 3 70B           active   45m           │   │
│  │  ○ Slot 2 (:8082)  —                     idle                   │   │
│  │                                                                   │   │
│  │  Max concurrent: 3                                                │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  [Cancel]                                              [Save Settings]  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Project AI Settings Panel

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Project: Exam Manager                                                   │
│  AI Settings                                                      [×]   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Project Default Models                                          │   │
│  │  ──────────────────────────────────────────────────────────────  │   │
│  │                                                                   │   │
│  │  Reasoning Model:  [Use user default               ▼]            │   │
│  │                    Options: Use user default, Mixtral, Llama3... │   │
│  │                                                                   │   │
│  │  Voice Model:      [Whisper Medium (Faster)        ▼]            │   │
│  │                    Overrides user default for this project       │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Instruction Execution Mode                                       │   │
│  │  ──────────────────────────────────────────────────────────────  │   │
│  │                                                                   │   │
│  │  ○ Automatic — AI executes tasks immediately after generation    │   │
│  │  ● Requires Approval — User must review and approve each task    │   │
│  │                                                                   │   │
│  │  ℹ️ When approval is required, generated tasks are saved as      │   │
│  │     draft instructions for review before execution.              │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  [Cancel]                                              [Save Settings]  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Per-Instruction Model Override

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Generate Specification                                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Intent: Add OAuth 2.0 authentication providers                         │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │  Model Selection                                    [Advanced ▼]  │ │
│  │                                                                    │ │
│  │  Using: Llama 3 70B Instruct (project default)                    │ │
│  │                                                                    │ │
│  │  ☐ Override for this generation only                              │ │
│  │     └─ Model: [Mixtral 8x7B                         ▼]            │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  [Generate Idea Document]  [Generate Full Specification]                 │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### ModelSelector Component

```typescript
// components/ai/ModelSelector.tsx
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { useModels } from '@/hooks/useModels';
import { formatFileSize } from '@/lib/utils';

interface ModelSelectorProps {
  modelType: 'reasoning' | 'voice';
  value: string | null;
  onChange: (modelId: string | null) => void;
  showDefault?: boolean;
  defaultLabel?: string;
  disabled?: boolean;
}

export function ModelSelector({
  modelType,
  value,
  onChange,
  showDefault = true,
  defaultLabel = 'Use default',
  disabled = false,
}: ModelSelectorProps) {
  const { models, isLoading } = useModels({ type: modelType, enabledOnly: true });

  return (
    <div className="space-y-2">
      <Label>{modelType === 'reasoning' ? 'Reasoning Model' : 'Voice Model'}</Label>
      <Select
        value={value ?? 'default'}
        onValueChange={(v) => onChange(v === 'default' ? null : v)}
        disabled={disabled || isLoading}
      >
        <SelectTrigger>
          <SelectValue placeholder="Select model" />
        </SelectTrigger>
        <SelectContent>
          {showDefault && (
            <SelectItem value="default">
              <span className="flex items-center gap-2">
                {defaultLabel}
                <Badge variant="secondary" className="text-xs">inherited</Badge>
              </span>
            </SelectItem>
          )}
          {models.map((model) => (
            <SelectItem key={model.id} value={model.id}>
              <span className="flex items-center justify-between w-full">
                <span>{model.displayName}</span>
                <span className="text-xs text-muted-foreground ml-2">
                  {formatFileSize(model.fileSizeBytes)}
                </span>
              </span>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}
```

### SlotStatusDisplay Component

```typescript
// components/ai/SlotStatusDisplay.tsx
import { useModelSlots } from '@/hooks/useModelSlots';
import { RefreshCw, Circle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';

export function SlotStatusDisplay() {
  const { slots, refresh, isLoading, maxConcurrent, activeCount } = useModelSlots();

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-medium">Active Model Slots</h3>
        <Button variant="ghost" size="icon" onClick={refresh} disabled={isLoading}>
          <RefreshCw className={cn('h-4 w-4', isLoading && 'animate-spin')} />
        </Button>
      </div>

      <div className="space-y-2">
        {slots.map((slot) => (
          <div
            key={slot.id}
            className="flex items-center gap-3 text-sm p-2 rounded-lg bg-muted/50"
          >
            <Circle
              className={cn(
                'h-3 w-3',
                slot.status === 'active' && 'fill-green-500 text-green-500',
                slot.status === 'loading' && 'fill-yellow-500 text-yellow-500',
                slot.status === 'idle' && 'fill-gray-300 text-gray-300',
                slot.status === 'error' && 'fill-red-500 text-red-500'
              )}
            />
            <span className="font-mono text-xs">Slot {slot.slotIndex} (:{slot.port})</span>
            <span className="flex-1 truncate">{slot.modelName ?? '—'}</span>
            <Badge variant={slot.status === 'active' ? 'default' : 'secondary'}>
              {slot.status}
            </Badge>
            {slot.startedAt && (
              <span className="text-xs text-muted-foreground">
                {formatDistanceToNow(new Date(slot.startedAt))}
              </span>
            )}
          </div>
        ))}
      </div>

      <p className="text-xs text-muted-foreground">
        Max concurrent: {maxConcurrent} • Active: {activeCount}
      </p>
    </div>
  );
}
```

---

## 8.11 Types

```typescript
// types/ai.ts
export interface Question {
  id: string;
  text: string;
  type: 'text' | 'choice' | 'confirm';
  options?: string[];
  required: boolean;
}

export interface AnalyzeResult {
  intent: string;
  ambiguities: string[];
  questions: Question[];
  validated: boolean;
}

export interface GenerateRequest {
  intent: string;
  answers: Record<string, string>;
  outputType: 'idea' | 'spec';
  projectContext?: string;
  modelOverrideId?: string;  // Per-instruction model override
}

export interface GenerateResult {
  content: string;
  outputType: 'idea' | 'spec';
}

export interface Model {
  id: string;
  displayName: string;
  fileName: string;
  modelType: 'reasoning' | 'voice';
  modelPath: string;
  fileSizeBytes: number;
  tags: string[] | null;
  isEnabled: boolean;
  contextSize: number | null;
  gpuLayers: number | null;
}

export interface ModelSlot {
  id: string;
  slotIndex: number;
  port: number;
  modelId: string | null;
  modelName: string | null;
  status: 'idle' | 'loading' | 'active' | 'error' | 'unloading';
  startedAt: string | null;
  lastAccessedAt: string | null;
}

export interface ModelDefaults {
  systemDefaults: {
    reasoningModelId: string | null;
    voiceModelId: string | null;
  };
  userDefaults: {
    reasoningModelId: string | null;
    voiceModelId: string | null;
  };
  resolved: {
    reasoningModelId: string;
    reasoningModelName: string;
    voiceModelId: string;
    voiceModelName: string;
  };
}

export interface ProjectAISettings {
  projectId: string;
  defaultReasoningModelId: string | null;
  defaultVoiceModelId: string | null;
  instructionApprovalRequired: boolean;
}
```

---

## 8.12 Hooks

### useModels Hook

```typescript
// hooks/useModels.ts
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import { Model } from '@/types/ai';

interface UseModelsOptions {
  type?: 'reasoning' | 'voice';
  enabledOnly?: boolean;
}

export function useModels(options: UseModelsOptions = {}) {
  const { type, enabledOnly = true } = options;

  const query = useQuery({
    queryKey: ['models', type, enabledOnly],
    queryFn: async () => {
      const response = await apiClient.get<{ items: Model[] }>('/api/v1/ai/models');
      let models = response.data.items;

      if (type) {
        models = models.filter((m) => m.modelType === type);
      }
      if (enabledOnly) {
        models = models.filter((m) => m.isEnabled);
      }

      return models;
    },
  });

  return {
    models: query.data ?? [],
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch,
  };
}
```

### useModelSlots Hook

```typescript
// hooks/useModelSlots.ts
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import { ModelSlot } from '@/types/ai';

interface SlotsResponse {
  items: ModelSlot[];
  maxConcurrentModels: number;
  activeCount: number;
}

export function useModelSlots() {
  const query = useQuery({
    queryKey: ['model-slots'],
    queryFn: () => apiClient.get<SlotsResponse>('/api/v1/ai/slots').then((r) => r.data),
    refetchInterval: 10000, // Refresh every 10s
  });

  return {
    slots: query.data?.items ?? [],
    maxConcurrent: query.data?.maxConcurrentModels ?? 0,
    activeCount: query.data?.activeCount ?? 0,
    isLoading: query.isLoading,
    refresh: query.refetch,
  };
}
```

### useModelDefaults Hook

```typescript
// hooks/useModelDefaults.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import { ModelDefaults } from '@/types/ai';

export function useModelDefaults() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['model-defaults'],
    queryFn: () => apiClient.get<ModelDefaults>('/api/v1/ai/defaults').then((r) => r.data),
  });

  const mutation = useMutation({
    mutationFn: (updates: { reasoningModelId?: string | null; voiceModelId?: string | null }) =>
      apiClient.put('/api/v1/ai/defaults/user', updates),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['model-defaults'] });
    },
  });

  return {
    defaults: query.data,
    isLoading: query.isLoading,
    updateUserDefaults: mutation.mutateAsync,
    isUpdating: mutation.isPending,
  };
}
```

### useProjectAISettings Hook

```typescript
// hooks/useProjectAISettings.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import { ProjectAISettings } from '@/types/ai';

export function useProjectAISettings(projectId: string) {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['project-ai-settings', projectId],
    queryFn: () =>
      apiClient.get<ProjectAISettings>(`/api/v1/projects/${projectId}/ai-settings`).then((r) => r.data),
    enabled: !!projectId,
  });

  const mutation = useMutation({
    mutationFn: (updates: Partial<Omit<ProjectAISettings, 'projectId'>>) =>
      apiClient.put(`/api/v1/projects/${projectId}/ai-settings`, updates),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['project-ai-settings', projectId] });
    },
  });

  return {
    settings: query.data,
    isLoading: query.isLoading,
    updateSettings: mutation.mutateAsync,
    isUpdating: mutation.isPending,
  };
}
```

---

## 8.13 Acceptance Criteria

### Functional Requirements

- [ ] Voice input or text input accepted
- [ ] Analyze button triggers intent analysis
- [ ] Questions display with appropriate input types (radio, checkbox, text)
- [ ] Required questions marked with indicator
- [ ] Generate buttons disabled until required questions answered
- [ ] Idea Document generates shorter output
- [ ] Full Specification generates comprehensive output
- [ ] Live preview shows streamed content during generation
- [ ] Cancel button stops generation mid-stream
- [ ] Accept saves file to specified location
- [ ] Edit First opens content in editor before saving
- [ ] Regenerate returns to questions with preserved answers
- [ ] Discard clears all state and returns to input

### Visual Requirements

- [ ] Analysis loading shows spinner
- [ ] Generation progress shows percentage bar
- [ ] Current section displayed during generation
- [ ] Markdown preview renders generated content
- [ ] File path input with folder selector

### Question Types

- [ ] Single choice (radio buttons) works
- [ ] Multiple choice (checkboxes) works  
- [ ] Text input (textarea) works
- [ ] Boolean confirm (yes/no buttons) works
- [ ] Question counter shows progress (1/3, 2/3)

### Settings

- [ ] Settings modal accessible from header
- [ ] Model selection dropdown updates configuration
- [ ] Settings persist across sessions

### Error Handling

- [ ] Analysis failure shows error message
- [ ] Generation failure allows retry
- [ ] Network timeout handled gracefully

### Accessibility Requirements

- [ ] Panel closable with Escape key
- [ ] Questions navigable with Tab key
- [ ] Screen reader announces state changes

---

## Related Specs

- [AI Integration Overview](./00-overview.md)
- [Voice Input](../05-voice-input/00-overview.md)
- [AI Prompt Panel](./10-ai-prompt-panel.md)
