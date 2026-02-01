# Project Context

> **Location:** `.lovable/memory/02-project-context.md`  
> **Updated:** 2026-02-01

---

## Overview

This repository contains specifications for WordPress plugin development tooling and WordPress plugins themselves.

---

## Spec Projects

### 1. WP Plugin Builder (`spec/wp-plugin-builder/`)

**Purpose:** Golang CLI tool for AI-assisted WordPress plugin development using RAG and AI Bridge.

| Attribute | Value |
|-----------|-------|
| Binary Name | `wpb` |
| Language | Go 1.21+ |
| Database | Dual SQLite (root + per-project) |
| Error Range | 10000-10999 |
| Spec Files | 15 |

**Key Features:**
- RAG-powered code generation
- Preset learning from markdown
- Spec-driven PHP generation
- CLI + Server modes
- Project import/export

**Implementation Phases:** 8 phases, ~5-6 weeks estimated

---

### 2. Exam Manager (`spec/wp-plugin/exam-manager/`)

**Purpose:** WordPress plugin for managing exam questions with participant tracking, deadlines, and progress.

| Attribute | Value |
|-----------|-------|
| API Namespace | `eqm/v1` |
| Database Tables | 27 |
| REST Endpoints | 30+ |
| Spec Files | 112 |
| Status | Production-Ready |

**Key Features:**
- Role-based access control (Admin, Exam Editor, Examinee)
- Exam hierarchy (parent/child)
- Wiki system with revisions
- Secret key authentication
- Deadline engine with extensions
- Email queue and notifications
- Certificate generation
- GDPR compliance

**Critical Algorithms:**
- Deadline calculation (extension from ORIGINAL deadline)
- Progress calculation (floor rounding, exclude SKIPPED)
- H2 section extraction (ignore code blocks)
- Anonymous migration (check existing, preserve audit)

---

### 3. Link Manager (`spec/wp-plugin/link-manager/`)

**Purpose:** WordPress plugin for comprehensive link management—scanning, categorization, modification, and health monitoring.

| Attribute | Value |
|-----------|-------|
| API Namespace | `lm/v1` |
| Database Tables | 29 |
| REST Endpoints | 110+ |
| Spec Files | 30 |
| Error Range | 14000-14999 |

**Key Features:**
- Parallel link scanning (posts, pages, JSON-LD)
- Link categorization (status, word count, wrapper context)
- Modification capabilities (remove/change links, attributes)
- History and rollback per post
- Snapshot system
- Internal linking engine
- Health monitoring with alerts
- Yoast SEO integration
- AI provider integration

---

### 4. PowerShell Integration (`spec/powershell-integration/`)

**Purpose:** PowerShell scripts for project building, running, and deployment.

**Templates:** Project configuration JSON templates for build automation.

---

## Learning Materials

### Exam Manager as Reference

The Exam Manager spec is a comprehensive example of:
- Complete spec organization (112 files)
- Backend/frontend split architecture
- Shared constants (SSOT) pattern
- AI implementation checklist
- Common pitfalls documentation
- Consistency reports

**Files to study:**
- `00-overview.md` - Master index and statistics
- `60-ai-implementation-checklist.md` - Critical algorithms
- `61-common-implementation-pitfalls.md` - 50+ anti-patterns
- `66-shared-constants.md` - Single source of truth

---

## Cross-Cutting Patterns

### Error Code Ranges

| Range | Project |
|-------|---------|
| 10000-10999 | WP Plugin Builder |
| 14000-14999 | Link Manager |
| 1xxx-9xxx | Exam Manager (internal) |

### Shared Conventions

1. **SSOT Pattern:** All constants, enums, error codes in `66-shared-constants.md`
2. **PascalCase DB Columns:** SQL uses PascalCase, ORM uses camelCase
3. **Exam-Scoped Cookies:** `{prefix}_{purpose}_{examSlug}`
4. **Floor Rounding:** Progress never shows 100% unless truly complete
5. **Extension from Original:** Deadlines extend from original, not current

---

*Update this file when project context changes.*
