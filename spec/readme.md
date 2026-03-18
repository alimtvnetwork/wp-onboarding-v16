# Specifications Index

> **Updated:** 2026-03-18  
> **Purpose:** Central index of all specification folders in this project.

---

## Specification Folders

| # | Folder | Description | Key Files |
|---|--------|-------------|-----------|
| 01 | [01-app/](./01-app/) | Application specs, feature definitions, behavioral requirements | `README.md` |
| 02 | [02-app-issues/](./02-app-issues/) | Issue write-ups — root cause, fix, prevention for every mistake | `README.md` |
| 03 | [03-audits/ → 11-audits/](./11-audits/) | Audit reports and findings | — |
| 04 | [04-coding-guidelines/](./04-coding-guidelines/) | DRY principles and general coding standards | `00-master-coding-guidelines.md` |
| 05 | [05-typescript-standards/](./05-typescript-standards/) | TypeScript coding standards (no `any`/`unknown` in public APIs) | `readme.md` |
| 06 | [06-golang-standards/](./06-golang-standards/) | Go language coding standards (no `interface{}`, generics, error patterns) | `readme.md` |
| 07 | [07-php-standards/](./07-php-standards/) | PHP coding standards (`Throwable`, `safe_execute`, constants) | `readme.md` |
| 08 | [08-error-manage/](./08-error-manage/) | Error handling, modal, logging, response envelope | Per-feature files |
| 09 | [09-wordpress/](./09-wordpress/) | **All WordPress plugin specs** (features, development, publishing, QUpload, cloud storage, log retrieval) | `readme.md` |
| 10 | [10-features/](./10-features/) | Feature specifications | — |
| 11 | [11-audits/](./11-audits/) | Audit reports | — |
| 12 | [12-feedback-report-feature/](./12-feedback-report-feature/) | Feedback report feature specification | `01-overview.md` |
| 13 | [13-powershell-integration/](./13-powershell-integration/) | PowerShell runner (`run.ps1`) for Go+React projects with pnpm PnP | `00-overview.md` |
| 14 | [14-e2-activity-feed/](./14-e2-activity-feed/) | Fleet-wide activity audit log (Feature E2) | `e2.1-go-endpoint-spec.md` |
| 15 | [15-generic-enforce/](./15-generic-enforce/) | Cross-language generic/type enforcement patterns | `readme.md` |
| 16 | [16-user-management/](./16-user-management/) | User management features | — |
| 17 | [17-parallel-powershell-scripts/](./17-parallel-powershell-scripts/) | Parallel PowerShell script execution | — |

### WordPress Subfolder Structure (`09-wordpress/`)

| Subfolder | Description |
|-----------|-------------|
| [01-plugin-features/](./09-wordpress/01-plugin-features/) | Plugin features (snapshots, auto-update redirects, diagnostics) |
| [02-development/](./09-wordpress/02-development/) | Development workflow, conventions, coding standards |
| [03-publishing/](./09-wordpress/03-publishing/) | Publishing pipeline — backend, frontend, testing |
| [04-upload-scripts/](./09-wordpress/04-upload-scripts/) | PowerShell upload scripts (V1, V2, V3) |
| [05-qupload-plugin/](./09-wordpress/05-qupload-plugin/) | QUpload (Quick Upload) plugin specification |
| [06-cloud-storage-providers/](./09-wordpress/06-cloud-storage-providers/) | Cloud storage integration (GitHub, GitLab, Google Drive) |
| [07-log-retrieval/](./09-wordpress/07-log-retrieval/) | Log retrieval endpoints and React frontend integration |

---

## Standalone Documents

| File | Description |
|------|-------------|
| [dry-refactoring-summary.md](./dry-refactoring-summary.md) | Complete summary of the 10-phase DRY refactoring initiative |
| [licensing-strategy.md](./licensing-strategy.md) | Licensing strategy and implementation plan |

---

## Reading Order (for new AI sessions)

1. **Project context:** `.lovable/memory/02-project-context.md`
2. **Active plan:** `.lovable/plan/active.md`
3. **This index** — then drill into relevant spec folders
4. **Coding standards:** `06-golang-standards/`, `05-typescript-standards/`, `07-php-standards/`
5. **Error system:** `08-error-manage/02-error-modal/` → `08-error-manage/01-error-handling/` → `08-error-manage/05-response-envelope/`

---

*Maintain this index when adding or removing spec folders.*
