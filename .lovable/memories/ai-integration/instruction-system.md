# Memory: ai-integration/instruction-system

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/06-ai-integration/`

---

## Overview

Instruction system using repository-level 'Prompts' folder with YAML-frontmatter Markdown presets.

---

## Pipeline

```
Voice/Text → Transcribe → Proofread → Plan (Reasoning) → Execute
```

---

## Preset Categories

| Category | Purpose |
|----------|---------|
| idea | Initial concepts |
| feature | Feature specs |
| task | Task breakdowns |
| codingGuideline | Code standards |
| instruction | Execution instructions |

---

## Key Features

- Voice-to-text transcription
- Long-chain reasoning for planning
- Inconsistency detection with clarification questions
- Dual-format artifacts (Markdown + JSON)
- Configurable execution mode (automatic vs. approval)
- Presets seeded into SQLite with user override support
