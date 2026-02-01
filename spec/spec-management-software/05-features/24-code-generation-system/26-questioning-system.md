# AI Questioning System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Backend and frontend implementation for AI-driven clarifying questions. When the AI model detects ambiguity or needs additional information to complete a task, it generates structured questions that users can answer through an interactive UI before the AI proceeds.

**Cross-References:**
- [AI Chat Interface](./20-ai-chat-interface.md) - Frontend integration
- [Instruction System](../06-ai-integration/03-instruction-system.md) - Task execution
- [LLM Integration](../06-ai-integration/02-llm-configuration.md) - Model configuration

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          QUESTIONING SYSTEM FLOW                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌──────────┐    ┌────────────────┐    ┌──────────────┐    ┌──────────────┐     │
│  │  User    │    │  Question      │    │  AI Model    │    │   Task       │     │
│  │  Input   │───▶│  Detector      │───▶│  (Analysis)  │───▶│  Executor    │     │
│  └──────────┘    └────────────────┘    └──────┬───────┘    └──────────────┘     │
│                                                │                                 │
│                         ┌──────────────────────┘                                │
│                         │ Needs clarification?                                  │
│                         │                                                       │
│                    ┌────┴────┐                                                  │
│                    │   No    │   Yes                                            │
│                    │         │    │                                             │
│                    ▼         │    ▼                                             │
│            ┌──────────┐      │  ┌──────────────────┐                           │
│            │ Execute  │      │  │ Generate         │                           │
│            │ Task     │      │  │ Questions        │                           │
│            └──────────┘      │  └────────┬─────────┘                           │
│                              │           │                                      │
│                              │           ▼                                      │
│                              │  ┌──────────────────┐                           │
│                              │  │ Send to Frontend │                           │
│                              │  │ (WebSocket)      │                           │
│                              │  └────────┬─────────┘                           │
│                              │           │                                      │
│                              │           ▼                                      │
│                              │  ┌──────────────────┐                           │
│                              │  │ User Answers     │                           │
│                              │  │ Questions        │                           │
│                              │  └────────┬─────────┘                           │
│                              │           │                                      │
│                              │           ▼                                      │
│                              │  ┌──────────────────┐                           │
│                              └─▶│ Resume with      │                           │
│                                 │ Context          │                           │
│                                 └──────────────────┘                           │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Question Types

### Single Choice

User selects exactly one option from a list.

```typescript
interface SingleChoiceQuestion {
  id: string;
  type: 'single-choice';
  question: string;
  description?: string;
  required: boolean;
  options: {
    id: string;
    label: string;
    description?: string;
  }[];
  allowOther: boolean;
  defaultOptionId?: string;
}
```

### Multi Choice

User selects one or more options.

```typescript
interface MultiChoiceQuestion {
  id: string;
  type: 'multi-choice';
  question: string;
  description?: string;
  required: boolean;
  options: {
    id: string;
    label: string;
    description?: string;
  }[];
  minSelections?: number;
  maxSelections?: number;
  allowOther: boolean;
}
```

### Text Input

User provides free-form text.

```typescript
interface TextQuestion {
  id: string;
  type: 'text';
  question: string;
  description?: string;
  required: boolean;
  placeholder?: string;
  minLength?: number;
  maxLength?: number;
  multiline: boolean;
  pattern?: string;  // Regex validation
}
```

### Boolean

Simple yes/no question.

```typescript
interface BooleanQuestion {
  id: string;
  type: 'boolean';
  question: string;
  description?: string;
  required: boolean;
  yesLabel?: string;  // Default: "Yes"
  noLabel?: string;   // Default: "No"
  defaultValue?: boolean;
}
```

### Range

Numeric value within a range.

```typescript
interface RangeQuestion {
  id: string;
  type: 'range';
  question: string;
  description?: string;
  required: boolean;
  min: number;
  max: number;
  step: number;
  unit?: string;
  defaultValue?: number;
  showLabels: boolean;
  labels?: {
    min: string;
    max: string;
  };
}
```

---

## Backend Implementation

### Question Generation Prompt

```go
package questioning

const questionGenerationPrompt = `You are an expert software architect. Analyze the user's request and determine if you need clarification before proceeding.

When to ask questions:
1. The request is ambiguous about implementation details
2. Multiple valid approaches exist with significant tradeoffs
3. Critical decisions affect security, performance, or architecture
4. The request lacks necessary technical specifications
5. User preferences would significantly impact the outcome

When NOT to ask questions:
1. The task is straightforward and unambiguous
2. Reasonable defaults can be applied
3. The choice is trivial or easily reversible
4. You already have sufficient context from prior messages

If clarification is needed, respond with JSON:
{
  "needsClarification": true,
  "explanation": "Brief explanation of why you need more information",
  "questions": [
    {
      "id": "unique_id",
      "type": "single-choice|multi-choice|text|boolean|range",
      "question": "Clear, concise question",
      "description": "Additional context (optional)",
      "required": true|false,
      "options": [...],  // For choice types
      "allowOther": true|false,  // For choice types
      "min": 0, "max": 100, "step": 1, "unit": "string"  // For range type
    }
  ]
}

If no clarification needed, respond with:
{
  "needsClarification": false,
  "proceed": true
}

Guidelines:
- Ask 1-5 questions maximum
- Prioritize the most impactful decisions
- Provide helpful option descriptions
- Use appropriate question types
- Set required=true only when essential`
```

### Question Service

```go
package questioning

import (
    "context"
    "encoding/json"
    "fmt"
)

type QuestioningService struct {
    aiService     AIService
    sessionStore  SessionStore
    eventBus      EventBus
}

type ClarificationResult struct {
    NeedsClarification bool                `json:"needsClarification"`
    Explanation        string              `json:"explanation"`
    Questions          []Question          `json:"questions"`
    Proceed            bool                `json:"proceed"`
}

type Question struct {
    Id           string           `json:"id"`
    Type         string           `json:"type"`
    Question     string           `json:"question"`
    Description  string           `json:"description,omitempty"`
    Required     bool             `json:"required"`
    Options      []QuestionOption `json:"options,omitempty"`
    AllowOther   bool             `json:"allowOther,omitempty"`
    Min          *float64         `json:"min,omitempty"`
    Max          *float64         `json:"max,omitempty"`
    Step         *float64         `json:"step,omitempty"`
    Unit         string           `json:"unit,omitempty"`
    MinLength    *int             `json:"minLength,omitempty"`
    MaxLength    *int             `json:"maxLength,omitempty"`
    Multiline    bool             `json:"multiline,omitempty"`
    DefaultValue interface{}      `json:"defaultValue,omitempty"`
}

type QuestionOption struct {
    Id          string `json:"id"`
    Label       string `json:"label"`
    Description string `json:"description,omitempty"`
}

type AnswerSet struct {
    SessionId string            `json:"sessionId"`
    Answers   []QuestionAnswer  `json:"answers"`
}

type QuestionAnswer struct {
    QuestionId string      `json:"questionId"`
    Answer     interface{} `json:"answer"`
    OtherText  string      `json:"otherText,omitempty"`
}

// AnalyzeForQuestions checks if clarification is needed
func (s *QuestioningService) AnalyzeForQuestions(
    ctx context.Context,
    sessionId string,
    userMessage string,
    context []ChatMessage,
) (*ClarificationResult, error) {
    
    // Build context from recent messages
    contextPrompt := buildContextFromMessages(context)
    
    // Call AI to analyze
    prompt := fmt.Sprintf(`Previous context:
%s

Current user request:
%s

Analyze this request and determine if clarification is needed.`,
        contextPrompt,
        userMessage,
    )
    
    response, err := s.aiService.GenerateStructured(ctx, GenerateRequest{
        SystemPrompt: questionGenerationPrompt,
        UserPrompt:   prompt,
        Temperature:  0.3, // Lower temperature for consistent analysis
    })
    if err != nil {
        return nil, fmt.Errorf("AI analysis failed: %w", err)
    }
    
    var result ClarificationResult
    if err := json.Unmarshal([]byte(response.Json), &result); err != nil {
        // If parsing fails, assume no clarification needed
        return &ClarificationResult{
            NeedsClarification: false,
            Proceed:            true,
        }, nil
    }
    
    // Store questions in session if clarification needed
    if result.NeedsClarification && len(result.Questions) > 0 {
        s.sessionStore.SetPendingQuestions(ctx, sessionId, result.Questions)
        
        // Emit WebSocket event
        s.eventBus.Emit("chat:questions", map[string]interface{}{
            "sessionId":   sessionId,
            "explanation": result.Explanation,
            "questions":   result.Questions,
        })
    }
    
    return &result, nil
}

// ProcessAnswers handles user's answers and resumes execution
func (s *QuestioningService) ProcessAnswers(
    ctx context.Context,
    sessionId string,
    answers []QuestionAnswer,
) error {
    
    // Validate answers
    questions, err := s.sessionStore.GetPendingQuestions(ctx, sessionId)
    if err != nil {
        return fmt.Errorf("no pending questions: %w", err)
    }
    
    if err := validateAnswers(questions, answers); err != nil {
        return fmt.Errorf("invalid answers: %w", err)
    }
    
    // Format answers for AI context
    answerContext := formatAnswersForContext(questions, answers)
    
    // Clear pending questions
    s.sessionStore.ClearPendingQuestions(ctx, sessionId)
    
    // Resume task execution with answer context
    return s.resumeWithAnswers(ctx, sessionId, answerContext)
}

// SkipQuestions allows user to proceed without answering
func (s *QuestioningService) SkipQuestions(
    ctx context.Context,
    sessionId string,
) error {
    
    s.sessionStore.ClearPendingQuestions(ctx, sessionId)
    
    // Resume with note that user skipped questions
    skipContext := "Note: User chose to skip clarifying questions. Proceed with reasonable defaults."
    
    return s.resumeWithAnswers(ctx, sessionId, skipContext)
}

// Helper functions

func validateAnswers(questions []Question, answers []QuestionAnswer) error {
    answerMap := make(map[string]QuestionAnswer)
    for _, a := range answers {
        answerMap[a.QuestionId] = a
    }
    
    for _, q := range questions {
        answer, exists := answerMap[q.Id]
        
        if q.Required && !exists {
            return fmt.Errorf("required question %s not answered", q.Id)
        }
        
        if exists {
            if err := validateAnswer(q, answer); err != nil {
                return fmt.Errorf("invalid answer for %s: %w", q.Id, err)
            }
        }
    }
    
    return nil
}

func formatAnswersForContext(questions []Question, answers []QuestionAnswer) string {
    var result strings.Builder
    result.WriteString("User provided the following clarifications:\n\n")
    
    answerMap := make(map[string]QuestionAnswer)
    for _, a := range answers {
        answerMap[a.QuestionId] = a
    }
    
    for _, q := range questions {
        answer, exists := answerMap[q.Id]
        if !exists {
            continue
        }
        
        result.WriteString(fmt.Sprintf("Q: %s\n", q.Question))
        result.WriteString(fmt.Sprintf("A: %v", formatAnswer(q, answer)))
        if answer.OtherText != "" {
            result.WriteString(fmt.Sprintf(" (%s)", answer.OtherText))
        }
        result.WriteString("\n\n")
    }
    
    return result.String()
}
```

---

## Database Schema

### PendingQuestion Table

```sql
CREATE TABLE PendingQuestion (
    Id TEXT PRIMARY KEY,
    SessionId TEXT NOT NULL,
    
    -- Question data (JSON)
    QuestionData TEXT NOT NULL,
    
    -- Tracking
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    ExpiresAt TEXT,  -- Auto-cleanup stale questions
    
    FOREIGN KEY (SessionId) REFERENCES ChatSession(Id) ON DELETE CASCADE
);

CREATE INDEX IX_PendingQuestion_SessionId ON PendingQuestion(SessionId);
CREATE INDEX IX_PendingQuestion_ExpiresAt ON PendingQuestion(ExpiresAt);
```

### QuestionHistory Table

```sql
CREATE TABLE QuestionHistory (
    Id TEXT PRIMARY KEY,
    SessionId TEXT NOT NULL,
    MessageId TEXT NOT NULL,
    
    -- Question snapshot
    Questions TEXT NOT NULL,  -- JSON array
    
    -- Answers
    Answers TEXT,             -- JSON array (null if skipped)
    WasSkipped INTEGER NOT NULL DEFAULT 0,
    
    -- Timestamps
    AskedAt TEXT NOT NULL,
    AnsweredAt TEXT,
    
    FOREIGN KEY (SessionId) REFERENCES ChatSession(Id) ON DELETE CASCADE,
    FOREIGN KEY (MessageId) REFERENCES ChatMessage(Id) ON DELETE CASCADE
);

CREATE INDEX IX_QuestionHistory_SessionId ON QuestionHistory(SessionId);
```

---

## Frontend Components

### Question Block Component

```typescript
interface QuestionBlockProps {
  questions: Question[];
  onSubmit: (answers: QuestionAnswer[]) => void;
  onSkip: () => void;
  isSubmitting: boolean;
  sessionId: string;
}

const QuestionBlock: React.FC<QuestionBlockProps> = ({
  questions,
  onSubmit,
  onSkip,
  isSubmitting,
}) => {
  const [answers, setAnswers] = useState<Record<string, QuestionAnswer>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  
  const updateAnswer = (questionId: string, value: any, otherText?: string) => {
    setAnswers(prev => ({
      ...prev,
      [questionId]: { questionId, answer: value, otherText },
    }));
    
    // Clear error when user answers
    if (errors[questionId]) {
      setErrors(prev => {
        const next = { ...prev };
        delete next[questionId];
        return next;
      });
    }
  };
  
  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    questions.forEach(q => {
      if (q.required && !answers[q.id]) {
        newErrors[q.id] = 'This question requires an answer';
      }
    });
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };
  
  const handleSubmit = () => {
    if (!validate()) return;
    onSubmit(Object.values(answers));
  };
  
  return (
    <div className="question-block bg-muted/50 rounded-lg p-4 my-4 space-y-4">
      <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
        <HelpCircle className="h-4 w-4" />
        AI needs some clarification
      </div>
      
      <div className="space-y-4">
        {questions.map((question) => (
          <QuestionRenderer
            key={question.id}
            question={question}
            value={answers[question.id]?.answer}
            otherText={answers[question.id]?.otherText}
            onChange={(value, other) => updateAnswer(question.id, value, other)}
            error={errors[question.id]}
          />
        ))}
      </div>
      
      <div className="flex justify-end gap-2 pt-2 border-t">
        <Button
          variant="ghost"
          onClick={onSkip}
          disabled={isSubmitting}
        >
          Skip (use defaults)
        </Button>
        <Button
          onClick={handleSubmit}
          disabled={isSubmitting}
        >
          {isSubmitting ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              Submitting...
            </>
          ) : (
            'Submit Answers'
          )}
        </Button>
      </div>
    </div>
  );
};
```

### Individual Question Renderers

```typescript
const QuestionRenderer: React.FC<{
  question: Question;
  value: any;
  otherText?: string;
  onChange: (value: any, otherText?: string) => void;
  error?: string;
}> = ({ question, value, otherText, onChange, error }) => {
  const Component = {
    'single-choice': SingleChoiceQuestion,
    'multi-choice': MultiChoiceQuestion,
    'text': TextQuestion,
    'boolean': BooleanQuestion,
    'range': RangeQuestion,
  }[question.type];
  
  if (!Component) {
    return <div>Unknown question type: {question.type}</div>;
  }
  
  return (
    <div className="space-y-2">
      <Component
        question={question}
        value={value}
        otherText={otherText}
        onChange={onChange}
      />
      {error && (
        <p className="text-sm text-destructive">{error}</p>
      )}
    </div>
  );
};

// Single Choice Question
const SingleChoiceQuestion: React.FC<QuestionComponentProps> = ({
  question,
  value,
  otherText,
  onChange,
}) => {
  const [showOther, setShowOther] = useState(value === 'other');
  
  return (
    <div className="space-y-3">
      <div>
        <Label className="text-base font-medium">
          {question.question}
          {question.required && <span className="text-destructive ml-1">*</span>}
        </Label>
        {question.description && (
          <p className="text-sm text-muted-foreground mt-1">
            {question.description}
          </p>
        )}
      </div>
      
      <RadioGroup
        value={value}
        onValueChange={(v) => {
          onChange(v);
          setShowOther(v === 'other');
        }}
      >
        {question.options.map((option) => (
          <div key={option.id} className="flex items-start space-x-3">
            <RadioGroupItem value={option.id} id={option.id} />
            <Label htmlFor={option.id} className="font-normal cursor-pointer">
              <span>{option.label}</span>
              {option.description && (
                <span className="text-muted-foreground ml-2 text-sm">
                  — {option.description}
                </span>
              )}
            </Label>
          </div>
        ))}
        
        {question.allowOther && (
          <div className="flex items-start space-x-3">
            <RadioGroupItem value="other" id="other" />
            <div className="flex-1">
              <Label htmlFor="other" className="font-normal cursor-pointer">
                Other
              </Label>
              {showOther && (
                <Input
                  className="mt-2"
                  placeholder="Please specify..."
                  value={otherText || ''}
                  onChange={(e) => onChange('other', e.target.value)}
                />
              )}
            </div>
          </div>
        )}
      </RadioGroup>
    </div>
  );
};
```

---

## WebSocket Events

### Questions Event (Server → Client)

```typescript
interface QuestionsEvent {
  type: 'chat:questions';
  data: {
    sessionId: string;
    messageId: string;
    explanation: string;
    questions: Question[];
  };
}
```

### Answers Event (Client → Server)

```typescript
interface AnswersEvent {
  type: 'chat:answers';
  data: {
    sessionId: string;
    answers: QuestionAnswer[];
  };
}
```

### Skip Event (Client → Server)

```typescript
interface SkipEvent {
  type: 'chat:skip_questions';
  data: {
    sessionId: string;
  };
}
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/chat/{sessionId}/questions` | Get pending questions |
| POST | `/api/v1/chat/{sessionId}/questions/answer` | Submit answers |
| POST | `/api/v1/chat/{sessionId}/questions/skip` | Skip questions |

---

## Related Specifications

- [AI Chat Interface](./20-ai-chat-interface.md)
- [Instruction System](../06-ai-integration/03-instruction-system.md)
- [WebSocket Protocol](../14-realtime/01-websocket-protocol.md)
