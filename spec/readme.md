# Specifications Index

> **Updated:** 2026-02-17  
> **Purpose:** Central index of all specification folders in this project.

---

## Specification Folders

| Folder | Description | Key Files |
|--------|-------------|-----------|
| [01-coding-guidelines/](./01-coding-guidelines/) | DRY principles and general coding standards | `dry-principles.md` |
| [02-typescript-standards/](./02-typescript-standards/) | TypeScript coding standards (no `any`/`unknown` in public APIs) | `readme.md` |
| [03-golang-standards/](./03-golang-standards/) | Go language coding standards (no `interface{}`, generics, error patterns) | `readme.md` |
| [04-php-standards/](./04-php-standards/) | PHP coding standards (`Throwable`, `safe_execute`, constants) | `readme.md` |
| [05-error-manage/](./05-error-manage/) | Error handling, modal, logging, response envelope | Per-feature files |
| [06-wordpress-plugin/](./06-wordpress-plugin/) | WordPress companion plugin features (snapshots, auto-update redirects) | `database-snapshots.md` |
| [07-wordpress-plugin-development/](./07-wordpress-plugin-development/) | Plugin development workflow and conventions | Per-topic files |
| [08-wp-plugin-publish/](./08-wp-plugin-publish/) | Publishing pipeline specification | Per-feature files |
| [09-upload-scripts/](./09-upload-scripts/) | PowerShell upload scripts (V1, V2, V3) for WordPress plugin deployment | `readme.md` |
| [10-powershell-integration/](./10-powershell-integration/) | PowerShell runner (`run.ps1`) for Go+React projects with pnpm PnP | `00-overview.md` |
| [11-e2-activity-feed/](./11-e2-activity-feed/) | Fleet-wide activity audit log (Feature E2) | `e2.1-go-endpoint-spec.md` |
| [12-generic-enforce/](./12-generic-enforce/) | Cross-language generic/type enforcement patterns | `readme.md` |

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
4. **Coding standards:** `03-golang-standards/`, `02-typescript-standards/`, `04-php-standards/`
5. **Error system:** `05-error-manage/02-error-modal/` → `05-error-manage/01-error-handling/` → `05-error-manage/05-response-envelope/`

---

*Maintain this index when adding or removing spec folders.*
