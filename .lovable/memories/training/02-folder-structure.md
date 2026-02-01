# AI Training: Folder Structure

**Version:** 1.2.0  
**Updated:** 2026-01-31  
**Purpose:** Directory organization reference (canonical source)

---

## Root Structure

```
spec/spec-management-software/
├── 00-master-index.md           # Project navigation hub (v2.9.0+)
├── CHANGELOG.md                 # Version history
├── 01-ideas/                    # Raw concepts (8 files)
├── 02-instructions/             # Refined directives (1 file)
├── 03-project-overview/         # Architecture docs (2 files)
├── 04-coding-guidelines/        # Standards (3 files)
├── 05-features/                 # Feature specs (200+ files, 30 subfolders)
│   ├── 00-overview.md           # Feature index
│   ├── 01-authentication/       # Auth system
│   ├── 02-file-management/      # File ops
│   ├── ...
│   ├── 24-code-generation-system/  # AI code gen (34 files)
│   ├── 25-ai-enhancements/      # Advanced AI (33 files)
│   ├── 27-automation-pipeline/  # Automation pipelines (36 files)
│   ├── 28-project-editor/       # Editor specs (6 files, 40 test cases)
│   └── 29-trigger-event-system/ # Unified event bus (18 files)
├── 06-error-management/         # Error codes (5 files)
├── 07-database-design/          # Schema (7 files)
├── 08-roadmap-overview/         # Timeline (10 files)
├── 09-diagrams/                 # Visual flows (10 files)
├── 10-research/                 # Investigations (6 files)
├── 11-skipped-features/         # Deferred items
├── 12-prompts/                  # AI presets (21 files)
├── 14-microservices/            # Service specs (21 canonical after dedup)
└── 99-consistency-report.md     # Overall health (100/100 target)
```

**Total:** 375+ files across 53 folders following numeric-prefixed naming (00-32 range).

---

## Feature Folder Pattern

Each feature folder follows this structure:

```
{nn}-{feature-name}/
├── 00-overview.md              # Feature summary + navigation
├── 01-{component-a}.md         # First component spec
├── 02-{component-b}.md         # Second component spec
├── ...
├── {nn}-api-endpoints.md       # API specs (if applicable)
├── {nn}-data-models.md         # Data structures
├── {nn}-error-codes.md         # Feature-specific errors
├── tests/
│   ├── 01-{test-scenario}.md
│   └── 02-{test-scenario}.md
└── 99-consistency-report.md    # Feature health score
```

---

## Example: Code Generation System (34 files)

```
24-code-generation-system/
├── 00-overview.md
├── 01-architecture.md
├── 02-guideline-hierarchy.md
├── 03-parallel-code-generation.md
├── 04-plan-generator.md
├── 05-parallel-executor.md
├── 06-build-verification.md
├── 07-git-integration.md
├── 08-configuration.md
├── ...
├── 32-url-context-system.md
└── 99-consistency-report.md
```

---

## CLI Tool Documentation

| Tool | Docs | Purpose |
|------|------|---------|
| `gsearch` | 19 files | Golang-based search CLI |
| `brun` | 16 files | Build runner CLI |

---

## Memories Structure

```
.lovable/memories/
├── README.md                    # Memory navigation
├── training/                    # AI training package (start here)
│   ├── 00-onboarding.md
│   ├── 01-conventions.md
│   ├── 02-folder-structure.md   # ← This file (canonical)
│   ├── 03-spec-patterns.md
│   └── 04-feature-template.md
├── constraints/                 # Coding standards, error codes
├── logic/                       # Formulas (HealthScore)
├── patterns/                    # Spec templates
├── project/                     # Remediation plans, archives
├── spec-management/             # Project memories
├── features/                    # Feature summaries
├── ai-integration/              # AI system docs
├── guidelines/                  # Standards
└── ui/                          # UI docs
```

**Archives:** Historical data consolidated in `.lovable/audit-history.md` and `.lovable/standards-archive.md`.

---

## Navigation Rules

1. Start at `00-master-index.md` for project-wide navigation
2. Use `05-features/00-overview.md` for feature discovery
3. Each folder's `00-overview.md` provides local navigation
4. Cross-references link related concepts bidirectionally
