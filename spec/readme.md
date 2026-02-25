# Specifications Index

> **Updated:** 2026-02-25  
> **Purpose:** Central index of all specification folders in this project.

---

## Specification Folders

| Folder | Description | Key Files |
|--------|-------------|-----------|
| [01-app/](./01-app/) | Application specs, feature definitions, behavioral requirements | `README.md` |
| [02-app-issues/](./02-app-issues/) | Issue write-ups — root cause, fix, prevention for every mistake | `README.md` |
| [03-coding-guidelines/](./03-coding-guidelines/) | DRY principles and general coding standards | `dry-principles.md` |
| [04-typescript-standards/](./04-typescript-standards/) | TypeScript coding standards (no `any`/`unknown` in public APIs) | `readme.md` |
| [05-golang-standards/](./05-golang-standards/) | Go language coding standards (no `interface{}`, generics, error patterns) | `readme.md` |
| [06-php-standards/](./06-php-standards/) | PHP coding standards (`Throwable`, `safe_execute`, constants) | `readme.md` |
| [07-error-manage/](./07-error-manage/) | Error handling, modal, logging, response envelope | Per-feature files |
| [08-wordpress-plugin/](./08-wordpress-plugin/) | WordPress companion plugin features (snapshots, auto-update redirects) | `database-snapshots.md` |
| [09-wordpress-plugin-development/](./09-wordpress-plugin-development/) | Plugin development workflow and conventions | Per-topic files |
| [10-wp-plugin-publish/](./10-wp-plugin-publish/) | Publishing pipeline specification | Per-feature files |
| [11-upload-scripts/](./11-upload-scripts/) | PowerShell upload scripts (V1, V2, V3) for WordPress plugin deployment | `readme.md` |
| [12-powershell-integration/](./12-powershell-integration/) | PowerShell runner (`run.ps1`) for Go+React projects with pnpm PnP | `00-overview.md` |
| [13-e2-activity-feed/](./13-e2-activity-feed/) | Fleet-wide activity audit log (Feature E2) | `e2.1-go-endpoint-spec.md` |
| [14-generic-enforce/](./14-generic-enforce/) | Cross-language generic/type enforcement patterns | `readme.md` |

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
4. **Coding standards:** `05-golang-standards/`, `04-typescript-standards/`, `06-php-standards/`
5. **Error system:** `07-error-manage/02-error-modal/` → `07-error-manage/01-error-handling/` → `07-error-manage/05-response-envelope/`

---

*Maintain this index when adding or removing spec folders.*
