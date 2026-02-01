# Feature: Project Management

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-28  

---

## Summary

Project lifecycle management including creation, organization, import/export, and visibility settings.

---

## User Stories

- As a user, I want to create new specification projects
- As a user, I want to import projects from ZIP, Markdown, or PRD files
- As a user, I want to export my projects for backup or sharing
- As a user, I want to set projects as private or global visibility

---

## Components

| # | Component | Type | Description |
|---|-----------|------|-------------|
| 01 | [Import/Export System](./01-import-export-system.md) | Backend | ZIP, MD, PRD import/export |
| 02 | [Import/Export UI](./02-import-export-ui.md) | Frontend | Import/export dialogs |

---

## Key Features

- **Import Formats:** ZIP archives, single Markdown, PRD documents
- **Export Formats:** ZIP with full structure
- **Auto-Detection:** Missing `spec.project.json` auto-generated
- **Visibility:** Private (user-only) or Global (read-only for others)

---

## Dependencies

- [File Management](../02-file-management/00-overview.md)
- [Database Design](../../07-database-design/00-overview.md)

---

## E2E Tests

| # | Test | Priority |
|---|------|----------|
| 01 | [Project CRUD](./tests/01-project-crud-e2e.md) | Critical |
| 02 | [Import/Export](./tests/02-import-export-e2e.md) | High |

---

## Related Specs

- [Import/Export UI](./02-import-export-ui.md)
