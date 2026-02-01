# Coding Guidelines

**Version:** 2.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Overview

Project-specific coding standards for the Spec Management Software. These guidelines complement the general coding standards and provide project-specific conventions.

**Cross-References:**
- [General Coding Standards](../../general-spec/01-foundation/01-coding-standards-foundation.md)
- [Error Management](../06-error-management/00-overview.md)

---

## Document Index

| # | Document | Description |
|---|----------|-------------|
| 00 | [Overview](./00-overview.md) | This file |
| 01 | [Helper Naming Guidelines](./01-helper-naming-guidelines.md) | Function/variable naming |
| 02 | [Configuration Manifest](./02-configuration-manifest.md) | Config key conventions |
| 03 | [TypeScript Guidelines](./03-typescript-guidelines.md) | Strict TypeScript rules (no any, enums, readonly) |
| 04 | [React Guidelines](./04-react-guidelines.md) | Component patterns, hooks, state management |
| 05 | [Seedable Config Pattern](./05-seedable-config-pattern.md) | Version-gated config seeding process |
| 06 | [ESLint Enforcement](./06-eslint-enforcement.md) | ESLint rules for enum and type safety |

---

## Key Principles

### 1. Consistency Over Preference

Follow established patterns even if you prefer alternatives. Consistency aids AI comprehension and team collaboration.

### 2. Explicit Over Implicit

Favor explicit declarations, type annotations, and clear naming over clever shortcuts.

### 3. Error Handling First

Always handle errors explicitly. See [Error Management](../06-error-management/00-overview.md) for patterns.

### 4. Test-Driven Specifications

All features must have E2E test specifications before implementation.

### 5. Seedable Configuration

Configuration values that may change at runtime follow the [Seedable Configuration Pattern](./05-seedable-config-pattern.md):
- Base values in JSON seed files with version
- Seed to Settings database on version change
- Editable via Settings UI
- Accessible throughout application

---

## Language-Specific Guidelines

### Backend (Go)

- See [01-go-guidelines.md](./01-go-guidelines.md)
- Uses GORM for database operations
- SQLite as primary database
- Error codes follow `ERR_` prefix convention

### Frontend (React/TypeScript)

- See [02-react-guidelines.md](./02-react-guidelines.md)
- TypeScript strict mode required
- Functional components with hooks
- TailwindCSS for styling
- **NO `any` or `unknown` types** — see [TypeScript Guidelines](./03-typescript-guidelines.md)

---

## Quick Reference

| Aspect | Convention |
|--------|------------|
| File names | kebab-case (`user-service.go`, `user-card.tsx`) |
| Components | PascalCase (`UserCard`, `FileTree`) |
| Functions | camelCase (`getUserById`, `handleSubmit`) |
| Constants | SCREAMING_SNAKE_CASE (`MAX_FILE_SIZE`) |
| Database tables | PascalCase (`UserSessions`, `SpecFiles`) |
| API endpoints | kebab-case (`/api/v1/user-sessions`) |
| Error codes | ERR_CATEGORY_NAME (`ERR_AUTH_FAILED`) |
| Config seeds | `seeding-{category}.json` |

---

## Seedable Config Categories

| Category | Seed File | Purpose |
|----------|-----------|---------|
| Model Routing | `seeding-models.json` | LLM model selection, thresholds |
| Authority Scores | `seeding-authority-scores.json` | Domain authority values |
| Source Weights | `seeding-source-weights.json` | Weight formula coefficients |
| Credibility | `seeding-credibility.json` | Classification thresholds |

See [Seedable Configuration Pattern](./05-seedable-config-pattern.md) for full documentation.

---

## Related Specs

- [Error Management](../06-error-management/00-overview.md)
- [Database Design](../07-database-design/00-overview.md)
- [General Spec Foundation](../../general-spec/01-foundation/01-coding-standards-foundation.md)
- [AI Code Generation](../05-features/26-ai-code-generation/00-overview.md)
