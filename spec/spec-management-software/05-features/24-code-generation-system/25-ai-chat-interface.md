# AI Chat Interface

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

ChatGPT-style AI interface with conversation history, questioning system for AI clarifications, voice/text input modes, and context-aware responses. Implements Lovable/Bolt-style clarifying questions when the AI needs more information before proceeding.

**Cross-References:**
- [Project Editor UI](./15-project-editor-ui.md) - Main interface
- [Instruction System](../06-ai-integration/03-instruction-system.md) - Task execution
- [Voice Processing Pipeline](../05-voice-input/04-voice-processing-pipeline.md) - Voice input
- [Suggestions System](./16-suggestions-system.md) - AI suggestions
- [Questioning System](./21-questioning-system.md) - Clarification questions

---

## Layout Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  [Project]    [AI ●]    [Settings ▼]                           [Spec/Code ▼]   │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │                        CHAT HISTORY AREA                                     ││
│  │                                                                              ││
│  │  ┌─────────────────────────────────────────────────────────────────────┐   ││
│  │  │ 🤖 AI                                                    10:30 AM  │   ││
│  │  │                                                                      │   ││
│  │  │ I understand you want to create a new authentication module.        │   ││
│  │  │ Before I proceed, I have a few questions:                           │   ││
│  │  │                                                                      │   ││
│  │  │ ┌─────────────────────────────────────────────────────────────────┐ │   ││
│  │  │ │ 🔵 Which authentication method should be used?                  │ │   ││
│  │  │ │                                                                  │ │   ││
│  │  │ │  ○ JWT with refresh tokens                                       │ │   ││
│  │  │ │  ○ Session-based authentication                                  │ │   ││
│  │  │ │  ○ OAuth 2.0 with external providers                            │ │   ││
│  │  │ │  ○ Other: _______________                                        │ │   ││
│  │  │ └─────────────────────────────────────────────────────────────────┘ │   ││
│  │  │                                                                      │   ││
│  │  │ ┌─────────────────────────────────────────────────────────────────┐ │   ││
│  │  │ │ 🔵 Should users be able to sign up themselves?                  │ │   ││
│  │  │ │                                                                  │ │   ││
│  │  │ │  ○ Yes, open registration                                        │ │   ││
│  │  │ │  ○ No, admin-only user creation                                  │ │   ││
│  │  │ │  ○ Invite-only registration                                      │ │   ││
│  │  │ └─────────────────────────────────────────────────────────────────┘ │   ││
│  │  │                                                                      │   ││
│  │  │                                      [Submit Answers]  [Skip All]   │   ││
│  │  └─────────────────────────────────────────────────────────────────────┘   ││
│  │                                                                              ││
│  │  ┌─────────────────────────────────────────────────────────────────────┐   ││
│  │  │ 👤 You                                                   10:28 AM  │   ││
│  │  │                                                                      │   ││
│  │  │ Create authentication for my application                            │   ││
│  │  └─────────────────────────────────────────────────────────────────────┘   ││
│  │                                                                              ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
│  CONTEXT FILES                                                                   │
│  [auth-spec.md ×] [user-schema.md ×]                              [+ Add]       │
│                                                                                  │
│  INPUT AREA                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────────┐│
│  │                                                                              ││
│  │  [🎤]  Type your message or use voice input...                     [Send]   ││
│  │                                                                              ││
│  └─────────────────────────────────────────────────────────────────────────────┘│
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Component Hierarchy

```
AITab/
├── AITabPage.tsx                 # Main AI tab page
├── components/
│   ├── ChatHistory/
│   │   ├── ChatHistory.tsx       # Scrollable message list
│   │   ├── MessageBubble.tsx     # Individual message
│   │   ├── UserMessage.tsx       # User message styling
│   │   ├── AIMessage.tsx         # AI message with markdown
│   │   ├── SystemMessage.tsx     # System notifications
│   │   └── TypingIndicator.tsx   # AI is thinking animation
│   │
│   ├── Questions/
│   │   ├── QuestionBlock.tsx     # Clarifying questions container
│   │   ├── SingleChoiceQuestion.tsx  # Radio button question
│   │   ├── MultiChoiceQuestion.tsx   # Checkbox question
│   │   ├── TextInputQuestion.tsx     # Free-text question
│   │   ├── RangeQuestion.tsx         # Slider/range question
│   │   └── QuestionActions.tsx       # Submit/Skip buttons
│   │
│   ├── Suggestions/
│   │   ├── SuggestionPanel.tsx       # Suggestions sidebar/section
│   │   ├── SuggestionCard.tsx        # Individual suggestion
│   │   └── SuggestionActions.tsx     # Accept/Reject/Defer
│   │
│   ├── Input/
│   │   ├── ChatInput.tsx             # Main input component
│   │   ├── VoiceInputButton.tsx      # Voice recording trigger
│   │   ├── VoiceRecordingOverlay.tsx # Recording UI
│   │   ├── FileAttachment.tsx        # Attach files
│   │   └── ContextFileSelector.tsx   # Memory file picker
│   │
│   ├── Actions/
│   │   ├── ActionBar.tsx             # Action buttons bar
│   │   ├── ModeToggle.tsx            # Spec/Code mode switch
│   │   ├── ValidateDropdown.tsx      # Validation actions
│   │   ├── GenerateDropdown.tsx      # Generation actions
│   │   └── RunButton.tsx             # Run code button
│   │
│   └── Status/
│       ├── StreamingStatus.tsx       # AI response streaming
│       ├── ProcessingIndicator.tsx   # Background task progress
│       └── CreditBalance.tsx         # Token/credit usage
│
├── hooks/
│   ├── useChatSession.ts             # Chat state management
│   ├── useAIStream.ts                # Streaming responses
│   ├── useVoiceInput.ts              # Voice recording
│   ├── useQuestions.ts               # Question handling
│   └── useSuggestions.ts             # Suggestion management
│
└── types/
    └── chat.ts                       # TypeScript interfaces
```

---

## Message Types

```typescript
type MessageRole = 'user' | 'assistant' | 'system';

interface ChatMessage {
  id: string;
  sessionId: string;
  role: MessageRole;
  content: string;
  timestamp: Date;
  
  // Optional metadata
  voiceId?: string;           // If from voice input
  attachments?: Attachment[];
  contextFiles?: string[];    // Files included in context
  
  // AI-specific
  model?: string;             // Which AI model responded
  tokenCount?: number;
  processingTime?: number;    // ms
  
  // Clarifying questions (if role === 'assistant')
  questions?: ClarifyingQuestion[];
  questionsAnswered?: boolean;
  
  // Suggestions (if role === 'assistant')
  suggestions?: SuggestionPreview[];
  
  // State
  isStreaming?: boolean;
  isError?: boolean;
  errorMessage?: string;
}

interface Attachment {
  id: string;
  type: 'file' | 'image' | 'voice';
  name: string;
  path: string;
  size: number;
  mimeType: string;
}
```

---

## Clarifying Questions System

### Question Types

```typescript
type QuestionType = 
  | 'single-choice'    // Radio buttons
  | 'multi-choice'     // Checkboxes
  | 'text'             // Free text input
  | 'range'            // Slider with min/max
  | 'boolean';         // Yes/No

interface ClarifyingQuestion {
  id: string;
  type: QuestionType;
  question: string;
  description?: string;
  required: boolean;
  
  // For choice questions
  options?: QuestionOption[];
  allowOther?: boolean;      // "Other" free-text option
  
  // For range questions
  min?: number;
  max?: number;
  step?: number;
  unit?: string;
  
  // For text questions
  placeholder?: string;
  maxLength?: number;
  
  // User response
  answer?: string | string[] | number | boolean;
  otherText?: string;
}

interface QuestionOption {
  id: string;
  label: string;
  description?: string;
  icon?: string;
}
```

### Question Block UI

```tsx
interface QuestionBlockProps {
  questions: ClarifyingQuestion[];
  onSubmit: (answers: QuestionAnswer[]) => void;
  onSkip: () => void;
  isSubmitting: boolean;
}

interface QuestionAnswer {
  questionId: string;
  answer: string | string[] | number | boolean;
  otherText?: string;
}
```

### Backend Question Generation

```go
// AI prompt for generating clarifying questions
const questionSystemPrompt = `When you need more information to complete a task, 
generate clarifying questions in the following JSON format:

{
  "needsClarification": true,
  "message": "Brief explanation of why you need more information",
  "questions": [
    {
      "id": "q1",
      "type": "single-choice",
      "question": "Which approach should be used?",
      "description": "This affects the overall architecture",
      "required": true,
      "options": [
        { "id": "opt1", "label": "Option A", "description": "Good for X" },
        { "id": "opt2", "label": "Option B", "description": "Good for Y" }
      ],
      "allowOther": true
    }
  ]
}

Guidelines:
- Ask 1-5 questions maximum
- Use single-choice for mutually exclusive options
- Use multi-choice when multiple selections are valid
- Prefer choices over free-text for common scenarios
- Include helpful descriptions for options
- Mark questions as required only when truly necessary`
```

---

## Suggestions Display

### In-Chat Suggestions

```typescript
interface SuggestionPreview {
  id: string;
  title: string;
  summary: string;
  priority: 'low' | 'medium' | 'high' | 'critical';
  category: string;
  estimatedTime?: string;
}

// After AI completes a task, show suggestions inline
const AIMessageWithSuggestions = () => (
  <div className="ai-message">
    <ReactMarkdown>{message.content}</ReactMarkdown>
    
    {message.suggestions && message.suggestions.length > 0 && (
      <div className="mt-4 border-t pt-4">
        <h4 className="text-sm font-medium text-muted-foreground mb-2">
          💡 Suggestions for improvement ({message.suggestions.length})
        </h4>
        
        <div className="space-y-2">
          {message.suggestions.map(suggestion => (
            <SuggestionCard
              key={suggestion.id}
              suggestion={suggestion}
              onAccept={() => handleAcceptSuggestion(suggestion.id)}
              onDefer={() => handleDeferSuggestion(suggestion.id)}
              onReject={() => handleRejectSuggestion(suggestion.id)}
            />
          ))}
        </div>
      </div>
    )}
  </div>
);
```

### Suggestion Card

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  ⚡ High Priority                                                                │
│                                                                                  │
│  Add Error Handling to API Endpoints                                           │
│                                                                                  │
│  The authentication endpoints lack proper error handling for edge cases.       │
│  This could lead to unclear error messages and debugging difficulties.         │
│                                                                                  │
│  Estimated: 2-4 hours                                      [Accept] [Later] [×]│
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Voice Input Integration

### Voice Recording States

```typescript
type VoiceState = 
  | 'idle'          // Ready to record
  | 'recording'     // Actively recording
  | 'processing'    // Uploading/transcribing
  | 'transcribed'   // Text ready
  | 'error';        // Recording failed

interface VoiceInputState {
  state: VoiceState;
  duration: number;         // Recording duration in seconds
  audioBlob?: Blob;
  transcription?: string;
  error?: string;
}
```

### Voice Input Overlay

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                                  │
│                                                                                  │
│                         ┌─────────────────────┐                                  │
│                         │                     │                                  │
│                         │    🎤               │                                  │
│                         │                     │                                  │
│                         │    Recording...     │                                  │
│                         │    00:15            │                                  │
│                         │                     │                                  │
│                         │  ████████████████   │ ← Audio waveform               │
│                         │                     │                                  │
│                         │  [Cancel]  [Done]   │                                  │
│                         │                     │                                  │
│                         └─────────────────────┘                                  │
│                                                                                  │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Run Button System

### Run Button (Code Mode)

When in Code Mode, the Run button executes frontend/backend code using brun CLI.

```typescript
interface RunConfig {
  target: 'frontend' | 'backend' | 'both';
  preset?: string;          // brun preset name
  autoGeneratePreset: boolean;
  watch: boolean;           // Watch mode for dev
}

interface RunState {
  status: 'idle' | 'starting' | 'running' | 'stopping' | 'error';
  frontendPort?: number;
  backendPort?: number;
  logs: LogEntry[];
  errors: string[];
}
```

### Run Button UI

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                                  │
│  [▶ Run ▼]                                                                       │
│      │                                                                           │
│      ▼ ← Dropdown on click                                                      │
│  ┌───────────────────────────┐                                                  │
│  │  ▶ Run All (Frontend+BE)  │                                                  │
│  │  ▶ Run Frontend Only      │                                                  │
│  │  ▶ Run Backend Only       │                                                  │
│  │  ─────────────────────    │                                                  │
│  │  ⚙ Configure Preset...    │                                                  │
│  │  📋 View Run Logs         │                                                  │
│  └───────────────────────────┘                                                  │
│                                                                                  │
│  When running:                                                                   │
│                                                                                  │
│  [■ Stop ▼]  FE: localhost:3000  BE: localhost:8080                             │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Auto-Generated Presets

```typescript
interface BrunPreset {
  name: string;
  description: string;
  frontend?: FrontendConfig;
  backend?: BackendConfig;
}

interface FrontendConfig {
  framework: 'react' | 'vue' | 'svelte';
  port: number;
  buildCommand: string;
  devCommand: string;
  outputDir: string;
}

interface BackendConfig {
  language: 'golang' | 'node' | 'python';
  port: number;
  buildCommand: string;
  runCommand: string;
  envFile?: string;
}

// AI generates preset based on project analysis
async function generatePreset(projectPath: string): Promise<BrunPreset> {
  // Analyze project structure
  const analysis = await analyzeProject(projectPath);
  
  return {
    name: 'auto-generated',
    description: 'Auto-generated by AI based on project structure',
    frontend: analysis.hasFrontend ? {
      framework: analysis.frontendFramework,
      port: 3000,
      buildCommand: analysis.frontendBuildCmd,
      devCommand: analysis.frontendDevCmd,
      outputDir: analysis.frontendOutput,
    } : undefined,
    backend: analysis.hasBackend ? {
      language: analysis.backendLanguage,
      port: 8080,
      buildCommand: analysis.backendBuildCmd,
      runCommand: analysis.backendRunCmd,
    } : undefined,
  };
}
```

---

## Chat Session State

```typescript
interface ChatSession {
  id: string;
  projectId: string;
  createdAt: Date;
  updatedAt: Date;
  
  // Messages
  messages: ChatMessage[];
  
  // Current state
  mode: 'spec' | 'code';
  contextFiles: string[];
  
  // AI state
  isProcessing: boolean;
  currentStreamId?: string;
  
  // Questions awaiting answer
  pendingQuestions?: ClarifyingQuestion[];
  
  // Active suggestions
  activeSuggestions: string[];  // Suggestion IDs
}

// Chat session context
interface ChatContextValue {
  session: ChatSession;
  
  // Actions
  sendMessage: (content: string, attachments?: Attachment[]) => Promise<void>;
  answerQuestions: (answers: QuestionAnswer[]) => Promise<void>;
  skipQuestions: () => Promise<void>;
  
  // Context management
  addContextFile: (path: string) => void;
  removeContextFile: (path: string) => void;
  
  // Suggestions
  acceptSuggestion: (id: string) => Promise<void>;
  deferSuggestion: (id: string) => void;
  rejectSuggestion: (id: string) => void;
  
  // Mode
  setMode: (mode: 'spec' | 'code') => void;
  
  // Run (code mode)
  runCode: (config: RunConfig) => Promise<void>;
  stopCode: () => Promise<void>;
}
```

---

## WebSocket Events

| Event | Direction | Description |
|-------|-----------|-------------|
| `chat:message` | → Server | Send user message |
| `chat:stream_start` | ← Server | AI starts responding |
| `chat:stream_token` | ← Server | Streaming token |
| `chat:stream_end` | ← Server | AI finished responding |
| `chat:questions` | ← Server | AI asks clarifying questions |
| `chat:answers` | → Server | User submits answers |
| `chat:suggestions` | ← Server | AI provides suggestions |
| `run:start` | → Server | Start code execution |
| `run:log` | ← Server | Execution log entry |
| `run:status` | ← Server | Execution status change |
| `run:stop` | → Server | Stop execution |

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/chat/sessions` | List chat sessions |
| POST | `/api/v1/chat/sessions` | Create new session |
| GET | `/api/v1/chat/sessions/{id}` | Get session with messages |
| DELETE | `/api/v1/chat/sessions/{id}` | Delete session |
| POST | `/api/v1/chat/sessions/{id}/messages` | Send message |
| POST | `/api/v1/chat/sessions/{id}/questions/answer` | Answer questions |
| POST | `/api/v1/chat/sessions/{id}/questions/skip` | Skip questions |
| POST | `/api/v1/run/start` | Start code execution |
| POST | `/api/v1/run/stop` | Stop code execution |
| GET | `/api/v1/run/status` | Get execution status |
| GET | `/api/v1/run/logs` | Get execution logs |

---

## Related Specifications

- [Questioning System](./21-questioning-system.md) - Detailed question logic
- [Suggestions System](./16-suggestions-system.md) - Suggestion management
- [Voice Processing Pipeline](../05-voice-input/04-voice-processing-pipeline.md)
- [Project Editor UI](./15-project-editor-ui.md)
- [Build Runner CLI](../23-build-runner-cli/00-overview.md)
