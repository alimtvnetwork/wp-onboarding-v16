# Phase 5.4: Mode Selector & Mode-Specific Behavior

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  
**Parent:** [Chat UI Redesign](./05-chat-ui-redesign.md)

---

## Overview

Mode selector with Spec, Coding, and Plan modes. Each mode adjusts system prompts, UI elements, available actions, and output rendering.

---

## 1. Mode Definitions

### 1.1 Mode Comparison

| Aspect | Spec Mode | Coding Mode | Plan Mode |
|--------|-----------|-------------|-----------|
| **Focus** | Drafting specs | Code generation | Execution plans |
| **System Prompt** | Spec writing assistant | Code generator | Plan architect |
| **Run Button** | Hidden | Visible | Hidden |
| **Output** | Markdown | Markdown + Code + Execution | Plan steps + Mermaid |
| **Auto-execute** | Never | Optional | After approval |
| **brun Presets** | N/A | Visible | N/A |

### 1.2 Mode Schema

```typescript
// types/modes.ts

export type AIMode = 'spec' | 'coding' | 'plan';

export interface ModeConfig {
  id: AIMode;
  label: string;
  shortLabel: string;
  description: string;
  icon: React.ComponentType<{ className?: string }>;
  
  // System prompt template
  systemPrompt: string;
  
  // UI configuration
  ui: {
    showRunButton: boolean;
    showBrunPresets: boolean;
    showExecutionStatus: boolean;
    showPlanView: boolean;
    inputPlaceholder: string;
    sendButtonLabel?: string;
  };
  
  // Keyboard shortcut
  shortcut: string;
  
  // Available actions in this mode
  actions: string[];
}

export const MODES: Record<AIMode, ModeConfig> = {
  spec: {
    id: 'spec',
    label: 'Spec Mode',
    shortLabel: 'Spec',
    description: 'Draft and refine specifications',
    icon: FileText,
    systemPrompt: `You are a specification writing assistant. Help users create clear, detailed, and well-structured product specifications. Focus on:
- User requirements and acceptance criteria
- Technical constraints and dependencies
- Edge cases and error handling
- Data models and API contracts

Format output as Markdown with appropriate headings and structure.`,
    ui: {
      showRunButton: false,
      showBrunPresets: false,
      showExecutionStatus: false,
      showPlanView: false,
      inputPlaceholder: 'Describe what you want to specify...',
    },
    shortcut: '⌘1',
    actions: ['screenshot', 'file', 'url', 'spec', 'project', 'memory'],
  },
  
  coding: {
    id: 'coding',
    label: 'Coding Mode',
    shortLabel: 'Code',
    description: 'Generate and execute code',
    icon: Code,
    systemPrompt: `You are a code generation assistant. Help users implement features based on specifications. Focus on:
- Clean, maintainable code following project conventions
- Proper error handling and edge cases
- Type safety and documentation
- Test considerations

When generating code:
1. Explain the approach briefly
2. Provide complete, runnable code
3. Note any dependencies or setup required`,
    ui: {
      showRunButton: true,
      showBrunPresets: true,
      showExecutionStatus: true,
      showPlanView: false,
      inputPlaceholder: 'Describe what you want to build...',
      sendButtonLabel: 'Generate',
    },
    shortcut: '⌘2',
    actions: ['screenshot', 'file', 'url', 'spec', 'project', 'memory', 'github'],
  },
  
  plan: {
    id: 'plan',
    label: 'Plan Mode',
    shortLabel: 'Plan',
    description: 'Create step-by-step execution plans',
    icon: GitBranch,
    systemPrompt: `You are an execution planning assistant. Help users break down complex tasks into actionable steps. For each plan:
1. Analyze the goal and requirements
2. Break down into atomic, verifiable steps
3. Identify dependencies between steps
4. Estimate complexity and risks

Always output:
- A Mermaid flowchart showing the plan
- Detailed steps with clear success criteria
- Required resources and prerequisites`,
    ui: {
      showRunButton: false,
      showBrunPresets: false,
      showExecutionStatus: false,
      showPlanView: true,
      inputPlaceholder: 'Describe the task to plan...',
      sendButtonLabel: 'Create Plan',
    },
    shortcut: '⌘3',
    actions: ['screenshot', 'file', 'url', 'spec', 'project', 'memory'],
  },
};
```

---

## 2. Mode Selector Component

### 2.1 Dropdown Selector

```typescript
// components/ai/ModeSelector.tsx

import { FileText, Code, GitBranch, Check, Keyboard } from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { MODES, type AIMode } from '@/types/modes';

interface ModeSelectorProps {
  value: AIMode;
  onChange: (mode: AIMode) => void;
  disabled?: boolean;
  variant?: 'default' | 'compact';
}

export function ModeSelector({
  value,
  onChange,
  disabled,
  variant = 'default',
}: ModeSelectorProps) {
  const currentMode = MODES[value];
  const Icon = currentMode.icon;
  
  if (variant === 'compact') {
    return (
      <Select value={value} onValueChange={(v) => onChange(v as AIMode)} disabled={disabled}>
        <SelectTrigger className="w-24 h-8">
          <Icon className="h-4 w-4 mr-1" />
          <span className="text-xs">{currentMode.shortLabel}</span>
        </SelectTrigger>
        <SelectContent>
          {Object.values(MODES).map((mode) => {
            const ModeIcon = mode.icon;
            return (
              <SelectItem key={mode.id} value={mode.id}>
                <div className="flex items-center gap-2">
                  <ModeIcon className="h-4 w-4" />
                  <span>{mode.shortLabel}</span>
                </div>
              </SelectItem>
            );
          })}
        </SelectContent>
      </Select>
    );
  }
  
  return (
    <Select value={value} onValueChange={(v) => onChange(v as AIMode)} disabled={disabled}>
      <SelectTrigger className="w-44">
        <SelectValue>
          <div className="flex items-center gap-2">
            <Icon className="h-4 w-4" />
            <span>{currentMode.label}</span>
          </div>
        </SelectValue>
      </SelectTrigger>
      
      <SelectContent className="w-72">
        {Object.values(MODES).map((mode) => {
          const ModeIcon = mode.icon;
          const isSelected = mode.id === value;
          
          return (
            <SelectItem
              key={mode.id}
              value={mode.id}
              className="py-3"
            >
              <div className="flex items-start gap-3 w-full">
                <div className={cn(
                  'p-2 rounded-lg',
                  isSelected ? 'bg-primary text-primary-foreground' : 'bg-muted'
                )}>
                  <ModeIcon className="h-4 w-4" />
                </div>
                
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-medium">{mode.label}</span>
                    {isSelected && <Check className="h-4 w-4 text-primary" />}
                  </div>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    {mode.description}
                  </p>
                </div>
                
                <Badge variant="outline" className="text-xs font-mono flex-shrink-0">
                  {mode.shortcut}
                </Badge>
              </div>
            </SelectItem>
          );
        })}
        
        <div className="px-2 py-2 border-t mt-2">
          <p className="text-xs text-muted-foreground flex items-center gap-1">
            <Keyboard className="h-3 w-3" />
            Use keyboard shortcuts to switch modes
          </p>
        </div>
      </SelectContent>
    </Select>
  );
}
```

### 2.2 Tab-Style Selector (Alternative)

```typescript
// components/ai/ModeTabSelector.tsx

import { FileText, Code, GitBranch } from 'lucide-react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { MODES, type AIMode } from '@/types/modes';

interface ModeTabSelectorProps {
  value: AIMode;
  onChange: (mode: AIMode) => void;
  disabled?: boolean;
}

export function ModeTabSelector({
  value,
  onChange,
  disabled,
}: ModeTabSelectorProps) {
  return (
    <Tabs value={value} onValueChange={(v) => onChange(v as AIMode)}>
      <TabsList className="grid grid-cols-3 w-full max-w-xs">
        {Object.values(MODES).map((mode) => {
          const Icon = mode.icon;
          
          return (
            <Tooltip key={mode.id}>
              <TooltipTrigger asChild>
                <TabsTrigger
                  value={mode.id}
                  disabled={disabled}
                  className="gap-1.5"
                >
                  <Icon className="h-4 w-4" />
                  <span className="hidden sm:inline">{mode.shortLabel}</span>
                </TabsTrigger>
              </TooltipTrigger>
              <TooltipContent side="bottom">
                <div className="text-center">
                  <p className="font-medium">{mode.label}</p>
                  <p className="text-xs text-muted-foreground">{mode.description}</p>
                  <p className="text-xs font-mono mt-1">{mode.shortcut}</p>
                </div>
              </TooltipContent>
            </Tooltip>
          );
        })}
      </TabsList>
    </Tabs>
  );
}
```

---

## 3. Mode Context Hook

```typescript
// hooks/useModeContext.ts

import { createContext, useContext, useState, useCallback, useMemo, ReactNode } from 'react';
import { MODES, type AIMode, type ModeConfig } from '@/types/modes';

interface ModeContextValue {
  mode: AIMode;
  config: ModeConfig;
  setMode: (mode: AIMode) => void;
  isActionEnabled: (action: string) => boolean;
}

const ModeContext = createContext<ModeContextValue | null>(null);

interface ModeProviderProps {
  children: ReactNode;
  defaultMode?: AIMode;
  onModeChange?: (mode: AIMode) => void;
}

export function ModeProvider({
  children,
  defaultMode = 'spec',
  onModeChange,
}: ModeProviderProps) {
  const [mode, setModeState] = useState<AIMode>(defaultMode);
  
  const setMode = useCallback((newMode: AIMode) => {
    setModeState(newMode);
    onModeChange?.(newMode);
    
    // Announce mode change for screen readers
    const announcement = document.createElement('div');
    announcement.setAttribute('role', 'status');
    announcement.setAttribute('aria-live', 'polite');
    announcement.className = 'sr-only';
    announcement.textContent = `Switched to ${MODES[newMode].label}`;
    document.body.appendChild(announcement);
    setTimeout(() => document.body.removeChild(announcement), 1000);
  }, [onModeChange]);
  
  const config = MODES[mode];
  
  const isActionEnabled = useCallback((action: string) => {
    return config.actions.includes(action);
  }, [config.actions]);
  
  const value = useMemo(() => ({
    mode,
    config,
    setMode,
    isActionEnabled,
  }), [mode, config, setMode, isActionEnabled]);
  
  return (
    <ModeContext.Provider value={value}>
      {children}
    </ModeContext.Provider>
  );
}

export function useMode() {
  const context = useContext(ModeContext);
  if (!context) {
    throw new Error('useMode must be used within a ModeProvider');
  }
  return context;
}
```

---

## 4. Coding Mode Components

### 4.1 Run Button

```typescript
// components/ai/coding/RunButton.tsx

import { useState, useCallback } from 'react';
import { Play, Square, Loader2, Check, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type RunStatus = 'idle' | 'running' | 'success' | 'error';

interface RunButtonProps {
  onRun: () => Promise<void>;
  onStop?: () => void;
  disabled?: boolean;
  className?: string;
}

export function RunButton({
  onRun,
  onStop,
  disabled,
  className,
}: RunButtonProps) {
  const [status, setStatus] = useState<RunStatus>('idle');
  
  const handleClick = useCallback(async () => {
    if (status === 'running' && onStop) {
      onStop();
      setStatus('idle');
      return;
    }
    
    setStatus('running');
    try {
      await onRun();
      setStatus('success');
      setTimeout(() => setStatus('idle'), 2000);
    } catch {
      setStatus('error');
      setTimeout(() => setStatus('idle'), 3000);
    }
  }, [status, onRun, onStop]);
  
  const icons = {
    idle: <Play className="h-4 w-4" />,
    running: onStop ? <Square className="h-4 w-4" /> : <Loader2 className="h-4 w-4 animate-spin" />,
    success: <Check className="h-4 w-4" />,
    error: <X className="h-4 w-4" />,
  };
  
  const labels = {
    idle: 'Run',
    running: onStop ? 'Stop' : 'Running...',
    success: 'Done',
    error: 'Failed',
  };
  
  const variants = {
    idle: 'default',
    running: 'secondary',
    success: 'default',
    error: 'destructive',
  } as const;
  
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          variant={variants[status]}
          size="sm"
          onClick={handleClick}
          disabled={disabled || (status === 'running' && !onStop)}
          className={cn(
            'gap-2 min-w-20 transition-all',
            status === 'success' && 'bg-green-500 hover:bg-green-600',
            className
          )}
        >
          {icons[status]}
          <span className="hidden sm:inline">{labels[status]}</span>
        </Button>
      </TooltipTrigger>
      <TooltipContent>
        {status === 'idle' && 'Execute generated code (⌘↵)'}
        {status === 'running' && (onStop ? 'Stop execution' : 'Executing...')}
        {status === 'success' && 'Execution completed'}
        {status === 'error' && 'Execution failed'}
      </TooltipContent>
    </Tooltip>
  );
}
```

### 4.2 brun Presets

```typescript
// components/ai/coding/BrunPresets.tsx

import { useState } from 'react';
import { ChevronDown, Terminal, Zap, Settings2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';

interface BrunPreset {
  id: string;
  name: string;
  description: string;
  command: string;
  autoGenerated?: boolean;
}

interface BrunPresetsProps {
  presets: BrunPreset[];
  selectedPreset?: string;
  onSelect: (presetId: string) => void;
  onConfigure?: () => void;
}

export function BrunPresets({
  presets,
  selectedPreset,
  onSelect,
  onConfigure,
}: BrunPresetsProps) {
  const selected = presets.find(p => p.id === selectedPreset);
  
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" className="gap-2">
          <Terminal className="h-4 w-4" />
          <span className="max-w-24 truncate">
            {selected?.name || 'Select preset'}
          </span>
          <ChevronDown className="h-3 w-3 opacity-50" />
        </Button>
      </DropdownMenuTrigger>
      
      <DropdownMenuContent align="start" className="w-64">
        <DropdownMenuLabel className="flex items-center gap-2">
          <Zap className="h-4 w-4" />
          brun Presets
        </DropdownMenuLabel>
        
        <DropdownMenuSeparator />
        
        {presets.map((preset) => (
          <DropdownMenuItem
            key={preset.id}
            onClick={() => onSelect(preset.id)}
            className="flex-col items-start gap-1"
          >
            <div className="flex items-center gap-2 w-full">
              <span className="font-medium">{preset.name}</span>
              {preset.autoGenerated && (
                <Badge variant="secondary" className="text-xs">
                  Auto
                </Badge>
              )}
              {preset.id === selectedPreset && (
                <span className="ml-auto text-primary">✓</span>
              )}
            </div>
            <span className="text-xs text-muted-foreground line-clamp-1">
              {preset.description}
            </span>
          </DropdownMenuItem>
        ))}
        
        {onConfigure && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={onConfigure}>
              <Settings2 className="h-4 w-4 mr-2" />
              Configure Presets
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
```

---

## 5. Plan Mode Components

### 5.1 Plan View Integration

```typescript
// components/ai/plan/PlanModeView.tsx

import { useState } from 'react';
import { PlanApprovalPanel } from './PlanApprovalPanel';
import { MermaidDiagram } from '../MermaidDiagram';
import { MessageList } from '../messages/MessageList';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePlan } from '@/hooks/usePlan';
import type { ExecutionPlan } from '@/types/plan';

interface PlanModeViewProps {
  sessionId: string;
  plan: ExecutionPlan | null;
  messages: any[];
  isGenerating: boolean;
}

export function PlanModeView({
  sessionId,
  plan,
  messages,
  isGenerating,
}: PlanModeViewProps) {
  const [activeTab, setActiveTab] = useState<'chat' | 'plan'>('chat');
  
  // Switch to plan tab when plan is generated
  useEffect(() => {
    if (plan && !isGenerating) {
      setActiveTab('plan');
    }
  }, [plan, isGenerating]);
  
  return (
    <div className="h-full flex flex-col">
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as 'chat' | 'plan')}>
        <div className="flex items-center justify-between px-4 py-2 border-b">
          <TabsList>
            <TabsTrigger value="chat">Chat</TabsTrigger>
            <TabsTrigger value="plan" disabled={!plan}>
              Plan
              {plan && (
                <span className="ml-1.5 px-1.5 py-0.5 rounded-full bg-primary/10 text-primary text-xs">
                  {plan.steps.length}
                </span>
              )}
            </TabsTrigger>
          </TabsList>
        </div>
        
        <TabsContent value="chat" className="flex-1 m-0 overflow-hidden">
          <MessageList
            messages={messages}
            isTyping={isGenerating}
          />
        </TabsContent>
        
        <TabsContent value="plan" className="flex-1 m-0 overflow-hidden">
          {plan && (
            <div className="h-full grid grid-cols-1 lg:grid-cols-2 gap-4 p-4 overflow-auto">
              {/* Mermaid diagram */}
              <div className="border rounded-lg p-4 bg-muted/30">
                <h3 className="text-sm font-medium mb-3">Execution Flow</h3>
                <MermaidDiagram
                  code={plan.mermaidCode}
                  activeStep={plan.currentStepId}
                />
              </div>
              
              {/* Plan steps */}
              <div>
                <PlanApprovalPanel plan={plan} />
              </div>
            </div>
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
```

---

## 6. Mode Persistence

```typescript
// hooks/useModePreference.ts

import { useCallback, useEffect } from 'react';
import { useLocalStorage } from '@/hooks/useLocalStorage';
import type { AIMode } from '@/types/modes';

const STORAGE_KEY = 'specmgmt_v1_ai_mode';

interface ModePreference {
  mode: AIMode;
  lastUsed: number;
  // Mode-specific settings
  settings: {
    coding: {
      autoExecute: boolean;
      selectedPreset?: string;
    };
    plan: {
      autoApprove: boolean;
    };
  };
}

const DEFAULT_PREFERENCE: ModePreference = {
  mode: 'spec',
  lastUsed: Date.now(),
  settings: {
    coding: {
      autoExecute: false,
    },
    plan: {
      autoApprove: false,
    },
  },
};

export function useModePreference() {
  const [preference, setPreference] = useLocalStorage<ModePreference>(
    STORAGE_KEY,
    DEFAULT_PREFERENCE
  );
  
  const setMode = useCallback((mode: AIMode) => {
    setPreference(prev => ({
      ...prev,
      mode,
      lastUsed: Date.now(),
    }));
  }, [setPreference]);
  
  const updateSettings = useCallback(<M extends 'coding' | 'plan'>(
    mode: M,
    settings: Partial<ModePreference['settings'][M]>
  ) => {
    setPreference(prev => ({
      ...prev,
      settings: {
        ...prev.settings,
        [mode]: {
          ...prev.settings[mode],
          ...settings,
        },
      },
    }));
  }, [setPreference]);
  
  return {
    mode: preference.mode,
    settings: preference.settings,
    setMode,
    updateSettings,
  };
}
```

---

## 7. Testing Requirements

| Test | Description | Priority |
|------|-------------|----------|
| Mode switching | Modes change via dropdown/tabs | Critical |
| Keyboard shortcuts | ⌘1/2/3 switch modes | Critical |
| UI adaptation | Correct elements show per mode | High |
| System prompts | Correct prompt sent per mode | High |
| Mode persistence | Mode survives page reload | Medium |
| Mode settings | Settings persist per mode | Medium |
| Accessibility | Mode changes announced | Medium |

---

## Related Specs

- [Chat Layout](./05-01-chat-layout.md)
- [Chat Input](./05-02-chat-input.md)
- [Plan Mode](./03-plan-mode.md)
