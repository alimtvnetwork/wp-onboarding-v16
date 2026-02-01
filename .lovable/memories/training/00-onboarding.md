# AI Training: Onboarding Guide

**Version:** 1.0.0  
**Updated:** 2026-01-29  
**Purpose:** Primary entry point for AI agents to understand and write specs

---

## Project Identity

**Spec Management Software** is a local-first specification authoring and validation system.

- **Backend:** Golang + SQLite (GORM)
- **Frontend:** React + TypeScript + Tailwind CSS
- **Architecture:** Local-first with hybrid storage

---

## Core Principles

1. **100% Health Score Target** — All specs must pass consistency checks
2. **Dual-Format Artifacts** — Markdown for humans, JSON for machines
3. **Bidirectional Cross-References** — All links must work both ways
4. **Iterative Quality Loops** — Auto-fix until 99%+ target reached

---

## Key Capabilities

| Capability | Description |
|------------|-------------|
| Spec Authoring | Markdown templates with YAML frontmatter |
| AI Drafting | Voice-to-text → Proofread → Plan → Execute |
| Consistency Checking | Link validation, naming enforcement |
| Code Generation | Three-phase pipeline (Writing → Consistency → Build) |
| CLI Tools | `gsearch` (search), `brun` (build runner) |

---

## AI Authorization

AI models are explicitly authorized to:
- Access, read, write, and rewrite files
- Follow the established folder conventions
- Maintain cross-reference integrity
- Generate new specs following patterns

---

## Next Steps

Read the following files in order:
1. [Conventions](./01-conventions.md) — Naming and structure rules
2. [Folder Structure](./02-folder-structure.md) — Directory organization
3. [Spec Patterns](./03-spec-patterns.md) — Example specifications
4. [Feature Template](./04-feature-template.md) — New feature boilerplate

---

## Quick Context Alternative

For rapid onboarding, see [CONTEXT-FOR-AI.md](../../../CONTEXT-FOR-AI.md) in the project root — a single-file summary of critical architecture patterns including:
- **Split Database System** (4-tier SQLite isolation)
- **Seedable Configuration Pattern** (versioned config with user-modification protection)
