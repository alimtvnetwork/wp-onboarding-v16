# Memory: ui/ai-chat-interface

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/`

---

## Overview

ChatGPT-style AI interface with mode switching and execution capabilities.

---

## Modes

| Mode | Focus | Features |
|------|-------|----------|
| Spec Mode | Markdown/requirements | Specification drafting |
| Coding Mode | Implementation | Code generation, Run button |

---

## Coding Mode Features

- **Run Button:** Triggers BE/FE execution
- **brun Presets:** Auto-generated CLI configurations
- **Token Streaming:** Real-time AI output
- **Auto-fix Loops:** Iterative error correction

---

## Input Methods

- Text input
- Voice input (transcription)
- File upload

---

## Real-time Display

- File modification visualization (pending, applied, failed)
- Long Chain mode for reasoning steps
- Diff streaming via WebSocket
