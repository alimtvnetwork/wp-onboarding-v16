# AI Prompt Presets

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Overview

This folder contains AI prompt presets organized by category. These presets define system prompts and instructions for various AI-assisted workflows.

---

## Folder Structure

```
12-prompts/
├── 00-overview.md              # This file
├── 01-coding-guideline/        # Language-specific coding standards
├── 02-feature/                 # Feature specification prompts
├── 03-idea/                    # Idea capture and brainstorming
├── 04-instruction/             # Instruction generation
└── 05-task/                    # Task-related prompts
```

---

## Category Index

| # | Category | Description | Presets |
|---|----------|-------------|---------|
| 01 | [coding-guideline](./01-coding-guideline/) | Language-specific coding standards | 3 |
| 02 | [feature](./02-feature/) | Feature specification generation | 3 |
| 03 | [idea](./03-idea/) | Idea capture and brainstorming | 3 |
| 04 | [instruction](./04-instruction/) | Instruction document generation | 3 |
| 05 | [task](./05-task/) | Implementation task creation | 3 |

---

## Preset Format

All presets use YAML frontmatter:

```yaml
---
name: Preset Name
description: What this preset does
isDefault: true|false
version: 1
---

{Prompt content in markdown}
```

---

## Cross-References

- [AI Integration](../05-features/06-ai-integration/00-overview.md)
- [Instruction System](../05-features/06-ai-integration/03-instruction-system.md)
