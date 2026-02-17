# Specifications Index

> **Updated:** 2026-02-09  
> **Purpose:** Central index of all specification folders in this project.

---

## Specification Folders

| Folder | Description | Key Files |
|--------|-------------|-----------|
| [coding-guidelines/](./coding-guidelines/) | DRY principles and general coding standards | `dry-principles.md` |
| [error-handling/](./error-handling/) | Cross-stack error chain (PHP → Go → React) | `readme.md` |
| [error-modal/](./error-modal/) | Frontend Global Error Modal specification with UI layout diagrams | `readme.md` |
| [error-resolution/](./error-resolution/) | Specific resolved error patterns and fixes | Per-issue files |
| [golang-standards/](./golang-standards/) | Go language coding standards (no `interface{}`, generics, error patterns) | `readme.md` |
| [logging-and-diagnostics/](./logging-and-diagnostics/) | Session-based logging, React execution logger | Per-feature files |
| [php-standards/](./php-standards/) | PHP coding standards (`Throwable`, `safe_execute`, constants) | `readme.md` |
| [powershell-integration/](./powershell-integration/) | PowerShell runner (`run.ps1`) for Go+React projects with pnpm PnP | `00-overview.md` |
| [response-envelope/](./response-envelope/) | Universal Response Envelope JSON Schema (v1.0.0) and samples | `envelope.schema.json`, `adr.md` |
| [typescript-standards/](./typescript-standards/) | TypeScript coding standards (no `any`/`unknown` in public APIs) | `readme.md` |
| [upload-scripts/](./upload-scripts/) | PowerShell upload scripts (V1, V2, V3) for WordPress plugin deployment | `readme.md` |
| [wordpress-plugin/](./wordpress-plugin/) | WordPress companion plugin architecture and API | Per-feature files |
| [wordpress-plugin-development/](./wordpress-plugin-development/) | Plugin development workflow and conventions | Per-topic files |
| [wp-plugin-publish/](./wp-plugin-publish/) | Publishing pipeline specification | Per-feature files |

---

## Standalone Documents

| File | Description |
|------|-------------|
| [dry-refactoring-summary.md](./dry-refactoring-summary.md) | Complete summary of the 10-phase DRY refactoring initiative |

---

## Reading Order (for new AI sessions)

1. **Project context:** `.lovable/memory/02-project-context.md`
2. **Active plan:** `.lovable/plan/active.md`
3. **This index** — then drill into relevant spec folders
4. **Coding standards:** `golang-standards/`, `typescript-standards/`, `php-standards/`
5. **Error system:** `error-modal/` → `error-handling/` → `response-envelope/`

---

*Maintain this index when adding or removing spec folders.*
