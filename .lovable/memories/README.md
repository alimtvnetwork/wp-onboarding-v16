# .lovable/memories - Memory Index

**Updated:** 2026-01-31  
**Purpose:** Central navigation for all Lovable AI memories

---

## Directory Structure

```
.lovable/memories/
├── README.md                    # This file
├── training/                    # 🎯 AI TRAINING PACKAGE (start here)
│   ├── 00-onboarding.md         # Entry point for AI agents
│   ├── 01-conventions.md        # Naming and structure rules
│   ├── 02-folder-structure.md   # Directory organization
│   ├── 03-spec-patterns.md      # Example specifications
│   ├── 04-feature-template.md   # New feature boilerplate
│   ├── 05-training-package.md   # 📦 SUBSYSTEM TRAINING PACKAGES (NEW)
│   ├── AI-COMPREHENSION-QUIZ.md # Training verification quiz
│   └── AI-TRAINING-COMPLETE.md  # Training completion marker
├── constraints/                 # ⚠️ CRITICAL RULES & PATTERNS
│   ├── coding-guidelines.md     # TypeScript/Go standards, enums
│   └── error-management.md      # Error codes (347 total), handling patterns
├── spec-management/             # Project-level documentation
│   ├── project-overview.md
│   ├── file-structure-conventions.md
│   └── cross-reference-validation.md
├── features/                    # Feature specifications
│   ├── code-generation-system-architecture.md
│   ├── build-runner-cli.md
│   ├── golang-search-cli.md
│   ├── consistency-checker.md
│   ├── automation-pipeline.md
│   ├── escalation-notifications.md
│   ├── mermaid-diagrams.md
│   ├── resilient-execution-system.md
│   ├── ai-bridge.md             # 🆕 AI Bridge adapter (NEW)
│   └── telemetry-dashboard.md
├── ai-integration/              # AI system documentation
│   ├── instruction-system.md
│   ├── rag-system.md
│   ├── knowledge-memory-system.md
│   └── model-management.md
├── logic/                       # 🧮 FORMULAS & ALGORITHMS
│   └── health-score-formula.md  # Canonical 6-field HealthScore
├── wp-plugins/                  # 🔌 WORDPRESS PLUGIN SPECS
│   └── link-manager.md          # Link management plugin (14xxx errors)
├── ui/                          # UI component documentation
│   ├── project-editor.md
│   ├── ai-chat-interface.md
│   └── code-generation-dashboard.md
└── guidelines/                  # Standards and conventions
    ├── ai-output-standard.md
    ├── quality-standards.md
    └── typescript-enums.md
```

---

## 🎯 AI Training (Start Here)

To train an external AI model to write specs for this project:

**Feed these files in order:**
1. `.lovable/memories/training/00-onboarding.md` — Entry point
2. `.lovable/memories/training/01-conventions.md` — Naming rules
3. `.lovable/memories/training/02-folder-structure.md` — Directory organization
4. `.lovable/memories/training/03-spec-patterns.md` — Example templates
5. `.lovable/memories/training/04-feature-template.md` — New feature boilerplate

**Minimum viable package:** Just feed the entire `training/` folder.

---

## Quick Reference

| Domain | Key Files |
|--------|-----------|
| **🎯 Training** | `training/00-onboarding.md` (start here) |
| **📦 Subsystem Packages** | `training/05-training-package.md` (AI Bridge, gsearch, brun, full backend) |
| **⚠️ Constraints** | `constraints/coding-guidelines.md`, `constraints/error-management.md` |
| **📋 Remediation** | `project/spec-remediation-plan.md` (Tier 1-4 priorities) |
| **📝 Patterns** | `patterns/spec-template.md` (Minimum Viable Spec format) |
| **🧮 Logic** | `logic/health-score-formula.md` (canonical 6-field formula) |
| **🔌 AI Bridge** | `features/ai-bridge.md`, `05-features/30-ai-bridge/` |
| **Project** | `spec-management/project-overview.md` |
| **Code Gen** | `features/code-generation-system-architecture.md` |
| **AI** | `ai-integration/instruction-system.md`, `model-management.md` |
| **UI** | `ui/project-editor.md`, `ai-chat-interface.md` |

---

## Quick Context Entry Point

For rapid AI onboarding, see [`CONTEXT-FOR-AI.md`](../../CONTEXT-FOR-AI.md) in the project root — a single-file summary covering:
- Split Database System (4-tier SQLite architecture)
- Seedable Configuration Pattern (versioned config lifecycle)
- Key directory structure and coding standards

---

## Bidirectional Sync

All project memories are consolidated in this folder (`.lovable/memories/`).

---

## Usage

Reference memories when:
1. **Quick start** — Read `CONTEXT-FOR-AI.md` for essential patterns
2. **Full training** — Use `training/` folder as comprehensive context
3. Understanding project structure
4. Following naming conventions
5. Validating cross-references
6. Implementing features
7. Generating new specifications
