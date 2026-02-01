# Idea: Instruction Builder and Prompt Preset System

**Created**: 2026-01-27  
**Status**: Draft  
**Author**: User Voice Input  

---

## Executive Summary

Build an Instruction Builder system that allows users to start from **voice or text input** at the project level, classify their input into content types (idea, feature, task, coding guideline, or instruction), apply **preset base prompts with optional user customization**, generate structured instruction artifacts, **detect inconsistencies**, and produce **UI-driven clarification questions** for iterative refinement.

---

## Problem Statement

Currently, users must manually structure their thoughts into specifications. This is time-consuming and error-prone. Users need:

1. A natural way to express ideas via voice or text
2. Automatic classification and enhancement of raw input
3. Consistent prompt templates that ensure quality output
4. Automated detection of gaps, ambiguities, and contradictions
5. Interactive questioning to resolve issues before finalizing specs

---

## Scope

### In Scope

- Voice and text input at project and file level
- Content type classification (idea, feature, task, codingGuideline, instruction)
- Prompt preset management with seeding from filesystem
- User customization layers on top of base prompts
- Instruction generation pipeline (proofread → enhance → generate)
- Inconsistency detection with phased issue grouping
- Clarification question generation and interactive UI
- Answer-driven spec regeneration
- Full history and audit trail

### Out of Scope

- Real-time collaboration on instructions
- Third-party LLM integrations (uses internal models only)
- Mobile-specific voice input handling

---

## Core Concepts

### 1. Content Types

When a user provides input, the system classifies or the user selects one of:

| Content Type      | Purpose                                      | Example Base Prompt Focus              |
|-------------------|----------------------------------------------|----------------------------------------|
| `idea`            | Early-stage, unstructured concept            | Expand, clarify scope, identify goals  |
| `feature`         | Specific functionality requirement           | Define user stories, acceptance criteria|
| `task`            | Actionable work item                         | Break down steps, estimate complexity  |
| `codingGuideline` | Technical standard or convention             | Formalize rules, provide examples      |
| `instruction`     | Direct command for AI or system              | Proofread, enhance clarity, structure  |

### 2. Prompt Presets and Prompts Folder

A repository-level folder `Prompts/` contains category subfolders:

```
Prompts/
├── idea/
│   ├── base-idea-expander.md
│   └── creative-brainstorm.md
├── feature/
│   ├── base-feature-spec.md
│   └── user-story-focus.md
├── task/
│   └── base-task-breakdown.md
├── codingGuideline/
│   └── base-coding-standard.md
└── instruction/
    └── base-instruction-enhancer.md
```

**Seeding Behavior**:
- On first run, presets from `Prompts/` are loaded into the database as `PromptPreset` records
- Each preset tracks its origin file path for traceability
- Users can create new presets or modify existing ones (creating new versions)

**Customization Rules**:
1. **Append Mode**: User adds a custom prompt layer that is appended to the base prompt
2. **Override Mode**: User modifies the base prompt itself to create a new preset version
3. **Clone Mode**: User clones an existing preset and makes changes to the copy

### 3. Instruction Creation Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                     USER INPUT PHASE                            │
├─────────────────────────────────────────────────────────────────┤
│  1. User provides voice OR text input                           │
│  2. Voice → transcription via voice model                       │
│  3. System infers or user selects content type                  │
│  4. User selects preset OR uses default for content type        │
│  5. User optionally adds custom prompt layer                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   PROCESSING PHASE                              │
├─────────────────────────────────────────────────────────────────┤
│  6. Reasoning model: PROOFREAD stage                            │
│     - Fix grammar, spelling, unclear phrasing                   │
│  7. Reasoning model: ENHANCEMENT stage                          │
│     - Expand abbreviations, add context, improve structure      │
│  8. Reasoning model: STRUCTURED INSTRUCTION stage               │
│     - Generate final instruction in defined format              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    OUTPUT PHASE                                 │
├─────────────────────────────────────────────────────────────────┤
│  9. Instruction artifact saved to project's instructions area   │
│ 10. Record created in SQLite with full metadata                 │
│ 11. Trigger inconsistency detection                             │
└─────────────────────────────────────────────────────────────────┘
```

### 4. Inconsistency Detection and Question Generation

After spec/instruction output is produced, the reasoning model analyzes for:

- **Ambiguities**: Vague terms, undefined acronyms
- **Inconsistencies**: Contradictory requirements
- **Missing Fields**: Required sections not present
- **Conflicting Constraints**: Mutually exclusive conditions

**Phased Issue Grouping**:

| Phase | Category                | Priority |
|-------|-------------------------|----------|
| A     | Critical missing data   | High     |
| B     | Conflicting decisions   | High     |
| C     | Ambiguous terminology   | Medium   |
| D     | Optional enhancements   | Low      |

**Question Generation**:

For each issue, generate one or more clarification questions with:

```typescript
interface ClarificationQuestion {
  id: string;
  issueId: string;
  phase: 'A' | 'B' | 'C' | 'D';
  questionText: string;
  whyItMatters: string;
  recommendedAnswer?: string;
  answerType: 'radio' | 'checkbox' | 'text' | 'dropdown' | 'multiSelect';
  answerOptions?: { value: string; label: string }[];
  isRequired: boolean;
}
```

### 5. Question UI Requirements

The UI must present questions using structured controls (similar to Lovable's approach):

- **Checkboxes**: Multiple selection from options
- **Radio buttons**: Single selection from options
- **Dropdowns**: Single selection with search
- **Text inputs**: Free-form answers
- **Multi-select chips**: Tag-style multiple selection

**Question Card Layout**:
```
┌──────────────────────────────────────────────────────────────┐
│ [Phase Badge: A]  [Required Badge]                           │
│                                                              │
│ Question: What is the primary user role for this feature?   │
│                                                              │
│ Why it matters: Defines permission requirements and UI flow │
│                                                              │
│ ○ Admin                                                      │
│ ○ Editor                                                     │
│ ○ Viewer                                                     │
│ ○ Guest                                                      │
│                                                              │
│ 💡 Recommended: Editor                                       │
│                                                              │
│ [Skip] [Answer]                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Goals

1. **Reduce friction**: Users can start with unstructured voice/text and get structured specs
2. **Ensure consistency**: Base prompts guarantee minimum quality standards
3. **Enable customization**: Users adapt prompts without losing defaults
4. **Catch errors early**: Inconsistency detection before finalization
5. **Iterate efficiently**: Question-driven refinement loop
6. **Maintain traceability**: Full history from voice input to final spec

---

## Success Metrics

| Metric                          | Target                |
|---------------------------------|-----------------------|
| Time from idea to first spec    | < 5 minutes           |
| Inconsistency detection rate    | > 90% of known issues |
| User preset customization rate  | > 30% of users        |
| Question answer completion rate | > 80%                 |
| Regeneration success rate       | > 95%                 |

---

## Dependencies

- Voice transcription model (existing)
- Reasoning model for enhancement (existing)
- SQLite database (existing)
- Markdown file system operations (existing)

---

## Open Questions

1. Should content type inference be automatic with user override, or user-first with suggestions?
2. How many preset versions should be retained in history?
3. Should unanswered questions block spec finalization or allow with warnings?
4. Should presets be project-scoped, global, or both?

---

## Next Steps

1. Create detailed backend API specification
2. Create detailed frontend UI specification
3. Define database schema extensions
4. Design Mermaid diagrams for all flows
5. Write acceptance criteria for each module
