# Exam Questions Manager - Specification Overview

> **Version:** 3.1.0  
> **Last Updated:** 2026-01-26  
> **Status:** Production-Ready  
> **Total Files:** 112 specification files

---

## 📁 Complete File Index

### Root Level Documents

| File | Purpose |
|------|---------|
| `00-overview.md` | This file - Master index with all file descriptions |
| `66-shared-constants.md` | **Single source of truth** for cookies, API paths, validation limits, colors, error codes |
| `63-cross-references.md` | Navigation guide linking related specifications |
| `60-ai-implementation-checklist.md` | Quick reference for AI/developers with critical algorithms |
| `61-common-implementation-pitfalls.md` | 50+ anti-patterns and correct patterns for all algorithms |
| `65-final-review-and-action-plan.md` | Technical audit, grading, and implementation recommendations |
| `64-final-consistency-audit.md` | Final consistency check and AI success rate prediction |
| `62-critical-review-honest-assessment.md` | Honest assessment of spec strengths and weaknesses |
| `67-spec-review-and-improvement-plan.md` | Improvement roadmap and gap analysis |

---

## 📂 01-admin-backend/split-spec/ (64 Files)

### Phase 0: Coding Standards

| # | File | Description |
|---|------|-------------|
| 01 | `01-coding-spec.md` | PHP coding standards, BooleanHelpers, 15-line function limit, early returns |
| 02 | `02-error-management.md` | BaseException hierarchy, categorized error codes (1xxx-9xxx), stack trace logging |

### Phase 1: Core Infrastructure

| # | File | Description |
|---|------|-------------|
| 03 | `03-plugin-structure.md` | WordPress plugin bootstrap, PSR-4 autoloading, file organization |
| 04 | `04-database-schema.md` | SQLite schema, **27 tables**, relationships, indexes |
| 05 | `05-orm-base-classes.md` | BaseModel, QueryBuilder, Repository pattern, migrations |
| 06 | `06-enums-constants.md` | **20+ PHP 8.0+ enums**, Consts.php, no magic strings |
| 07 | `07-logging-system.md` | Dual-file logging (plugin.log, error.txt), rotation policies |
| 08 | `08-entity-models.md` | All entity model classes with relationships |
| 09 | `09-validation-utilities.md` | Input validation, sanitization helpers, regex patterns |

### Phase 2: Access Control

| # | File | Description |
|---|------|-------------|
| 10 | `10-rbac-system.md` | Role-based access control: Admin, Exam Editor, Examinee |
| 11 | `11-rbac-admin-ui.md` | Role management React components, permission matrix |

### Phase 3: Exam Management

| # | File | Description |
|---|------|-------------|
| 12 | `12-exam-service.md` | Exam CRUD operations, H2 section extraction algorithm |
| 13 | `13-exam-hierarchy.md` | Parent-child exam relationships, HierarchyService, circular reference prevention |
| 14 | `14-exam-editor-ui.md` | 6-tab exam editor interface structure |
| 15 | `15-exam-content-tab.md` | Markdown editor with live preview, [[Wiki Link]] autocomplete |
| 16 | `16-exam-metadata-tab.md` | Slug generation, visibility rules, deadline settings |
| 17 | `17-exam-subexams-tab.md` | Sub-exam management, drag-drop reordering |
| 18 | `18-exam-prerequisites-tab.md` | Videos, links, access gates configuration |
| 19 | `19-exam-checklists-tab.md` | PRE/IN_EXAM/POST checklists, submission types |

### Phase 4: Wiki System

| # | File | Description |
|---|------|-------------|
| 20 | `20-wiki-service.md` | Wiki backend, visibility controls, search |
| 21 | `21-wiki-categories.md` | Category management, nested hierarchy |
| 22 | `22-wiki-editor-ui.md` | Wiki Markdown editor with preview |
| 23 | `23-wiki-revisions.md` | Revision history, diff view, rollback |

### Phase 5: Secret Keys

| # | File | Description |
|---|------|-------------|
| 24 | `24-secret-key-service.md` | Key generation, SHA-256 hashing, validation flow |
| 25 | `25-secret-key-admin-ui.md` | Key management interface, bulk generation |
| 26 | `26-secret-key-analytics.md` | Usage tracking, geo-analytics, IP hashing |

### Phase 6: Participants

| # | File | Description |
|---|------|-------------|
| 27 | `27-participant-service.md` | Participant CRUD, enrollment, anonymous migration |
| 28 | `28-participant-progress.md` | Section tracking, weighted progress calculation |
| 29 | `29-deadline-engine.md` | Two-tier deadline system, extension calculation, cron enforcement |
| 30 | `30-extension-system.md` | Extension requests, approval workflow, file attachments |

### Phase 7: Communications

| # | File | Description |
|---|------|-------------|
| 31 | `31-email-queue.md` | Queued email processing, retry logic, priority handling |
| 32 | `32-notification-service.md` | In-app notifications, read/unread tracking |
| 33 | `33-email-templates.md` | 11 seeded email templates with placeholders |
| 34 | `34-cron-system.md` | WP-Cron job scheduling, deadline enforcement |

### Phase 8: Admin Interface

| # | File | Description |
|---|------|-------------|
| 35 | `35-plugin-settings.md` | Admin settings page, 3-tier config hierarchy |
| 36 | `36-rest-api-endpoints.md` | eqm/v1 REST API documentation, 30+ endpoints |
| 37 | `37-admin-dashboard.md` | Main dashboard widgets, statistics |
| 38 | `38-exam-list-view.md` | Exam list with filters, bulk actions |
| 39 | `39-participant-management.md` | CSV import, bulk actions, status management |
| 40 | `40-import-export-system.md` | JSON import/export, data migration |

### Phase 9: Testing & Security

| # | File | Description |
|---|------|-------------|
| 41 | `41-testing-requirements.md` | PHPUnit, Vitest, Playwright specs, coverage targets |
| 41a | `41a-test-spec-conditional-helpers.md` | logIf, execIf, ifNotNull test cases |
| 41b | `41b-test-spec-file-loader.md` | FileLoaderHelpers stack trace tests |
| 41c | `41c-test-spec-feature-flags.md` | Feature flag resolution tests |
| 41d | `41d-test-spec-deadline-engine.md` | Deadline calculation algorithm tests |
| 41e | `41e-test-spec-progress-calculation.md` | Progress percentage, SKIPPED handling tests |
| 42 | `42-deployment-checklist.md` | Pre-launch verification, security checklist |
| 43 | `43-public-exam-view.md` | Frontend exam display backend APIs |
| 44 | `44-certificate-generation.md` | PDF certificate creation, templates |
| 45 | `45-notifications-panel.md` | Notification center UI, mark all read |
| 46 | `46-audit-logging.md` | 28 audit event types, full change tracking |

### Phase 10: Analytics & Advanced

| # | File | Description |
|---|------|-------------|
| 47 | `47-reporting-dashboard.md` | Analytics, charts, export reports |
| 48 | `48-rate-limiting.md` | Sliding window rate limiting, 9 categories, lockouts |
| 49 | `49-test-fixtures.md` | Sample data, seed scripts, edge case data |
| 50 | `50-exam-invite-management.md` | Invite-only exams, email+phone validation |
| 51 | `51-exam-preset-settings.md` | Reusable exam configuration templates |
| 52 | `52-admin-review-queue.md` | Submission review workflow, bulk approval |
| 53 | `53-monitoring-alerting.md` | Health checks, alerting rules, admin health panel |
| 54 | `54-gdpr-data-privacy.md` | Data retention, right to erasure, consent management |
| 55 | `55-webhooks-integrations.md` | Event hooks for LMS, CRM, Zapier |
| 56 | `56-theming-system.md` | Theme seeding, admin UI, CSS variable generation |
| 57 | `57-caching-system.md` | Multi-layer caching, Memcached/Redis, page cache |
| 58 | `58-feature-flags.md` | Feature flag seeding, rollout percentages, per-user/exam overrides |

### Support Documents

| File | Purpose |
|------|---------|
| `exam-questions-manager-full-spec.md` | Complete monolithic backend specification |
| `59-consistency-report.md` | Validation report for cross-spec consistency |

---

## 📂 02-frontend/split-spec/ (33 Files)

### Foundation

| # | File | Description |
|---|------|-------------|
| 01 | `01-frontend-overview.md` | Frontend architecture, API conventions, cookie patterns |
| 02 | `02-public-landing-page.md` | Landing page layout, CTA buttons, responsive design |
| 03 | `03-signup-flow.md` | Registration form, validation, error handling |
| 04 | `04-login-flow.md` | Authentication flow, remember me, session creation |
| 05 | `05-participate-flow.md` | Authenticated user joins new exam flow |

### Dashboard & Content

| # | File | Description |
|---|------|-------------|
| 06 | `06-dashboard-page.md` | Participant dashboard, exam cards, progress display |
| 07 | `07-section-view.md` | Section content display, navigation, completion |
| 08 | `08-markdown-rendering.md` | Markdown to HTML, syntax highlighting, [[Wiki Links]] |
| 09 | `09-prerequisites-display.md` | Prerequisite list, completion checkboxes |
| 10 | `10-sub-exams-display.md` | Sub-exam navigation, hierarchy visualization |

### Deadlines & Extensions

| # | File | Description |
|---|------|-------------|
| 11 | `11-deadline-countdown.md` | Real-time countdown, color-coded urgency, timezone display |
| 12 | `12-extension-request.md` | Extension form, file upload, validation |
| 13 | `13-session-management.md` | Session isolation per exam, cookie handling |
| 14 | `14-exam-completion-flow.md` | Completion celebration, certificate access |
| 15 | `15-locked-state.md` | Locked participant UI, read-only mode |

### Error Handling & Edge Cases

| # | File | Description |
|---|------|-------------|
| 16 | `16-error-handling.md` | Error display patterns, retry logic, offline handling |
| 17 | `17-edge-cases.md` | Tab switching, network failures, concurrent sessions |
| 18 | `18-secret-key-access.md` | Anonymous access flow, migration prompts |

### Forms & Validation

| # | File | Description |
|---|------|-------------|
| 19 | `19-form-validation.md` | Client-side validation, real-time feedback, patterns |
| 20 | `20-loading-states.md` | Button loading, skeleton loaders, progress indicators |
| 21 | `21-frontend-logging.md` | Fire-and-forget analytics logging via API |

### Design System

| # | File | Description |
|---|------|-------------|
| 22 | `22-ui-design-system.md` | Complete design tokens, dark mode, accessibility |
| 23 | `23-responsive-design.md` | Breakpoints, mobile/tablet/desktop layouts |
| 24 | `24-tech-stack.md` | Recommended technologies, libraries |
| 25 | `25-acceptance-criteria.md` | Feature acceptance criteria checklist |

### Advanced Features

| # | File | Description |
|---|------|-------------|
| 26 | `26-internationalization.md` | i18n patterns, RTL support, locale detection |
| 27 | `27-performance-targets.md` | Core Web Vitals, API SLAs, bundle budgets |
| 28 | `28-invite-signup-flow.md` | Invite-only signup with email+phone validation |
| 29 | `29-participant-submission-ui.md` | Evidence submission forms, file upload UI |
| 30 | `30-browser-compatibility.md` | Browser matrix, polyfills, feature detection |
| 31 | `31-theme-application.md` | CSS variable injection, form/markdown styling |

### Support Documents

| File | Purpose |
|------|---------|
| `frontend-full-spec.md` | Complete monolithic frontend specification |
| `32-consistency-report.md` | Frontend spec consistency validation |
| `33-split-plan.md` | Frontend spec splitting methodology |

---

## 📂 diagrams/ (6 Files)

| File | Description |
|------|-------------|
| `01-database-er-diagram.md` | 27-table ER diagram with relationships |
| `02-participant-status-states.md` | 9-state status machine, transitions, triggers |
| `03-secret-key-auth-flow.md` | 7-phase authentication sequence diagram |
| `04-deadline-calculation-flow.md` | Calculation, extension, cron enforcement flowchart |
| `05-system-architecture.md` | Complete component connections, data flow, timeline |
| `06-submission-lifecycle.md` | Participant input → validation → review → status |

---

## 📊 Statistics Summary

| Category | Count |
|----------|-------|
| **Backend Specifications** | 64 files |
| **Frontend Specifications** | 33 files |
| **Mermaid Diagrams** | 6 files |
| **Cross-Cutting Documents** | 9 files |
| **Total Specification Files** | 112 files |
| **Database Tables** | 27 tables |
| **PHP Enums** | 20+ enums |
| **REST API Endpoints** | 30+ endpoints |
| **Email Templates** | 11 templates |
| **Audit Event Types** | 28 types |
| **Error Code Categories** | 9 ranges |
| **Feature Flags** | 18 seeded flags |
| **Algorithm Test Cases** | 80+ test cases |

---

## 🔄 Implementation Order

### Phase 1: Foundation (Week 1)
```
01-coding-spec → 02-error-management → 03-plugin-structure
    → 04-database-schema → 05-orm-base-classes → 06-enums-constants
    → 07-logging-system → 08-entity-models → 09-validation-utilities
```

### Phase 2: Access Control (Week 1-2)
```
10-rbac-system → 11-rbac-admin-ui
```

### Phase 3: Exam Core (Week 2)
```
12-exam-service → 13-exam-hierarchy → 14-22 (Editor tabs)
```

### Phase 4: Wiki System (Week 2-3)
```
20-wiki-service → 21-wiki-categories → 22-wiki-editor-ui → 23-wiki-revisions
```

### Phase 5: Secret Keys (Week 3)
```
24-secret-key-service → 25-secret-key-admin-ui → 26-secret-key-analytics
```

### Phase 6: Participants (Week 3-4)
```
27-participant-service → 28-participant-progress → 29-deadline-engine → 30-extension-system
```

### Phase 7: Communications (Week 4)
```
31-email-queue → 32-notification-service → 33-email-templates → 34-cron-system
```

### Phase 8: Admin Interface (Week 4-5)
```
35-plugin-settings → 36-rest-api-endpoints → 37-47 (Admin UI)
```

### Phase 9: Security & Advanced (Week 5-6)
```
48-rate-limiting → 49-57 (Advanced features)
```

### Frontend (Parallel after Phase 8)
```
All 31 frontend specs can be implemented in parallel
```

---

## 🎯 Quick Start for Implementers

1. **Read 66-shared-constants.md** - All cross-cutting values
2. **Read 60-ai-implementation-checklist.md** - Critical algorithms
3. **Read 61-common-implementation-pitfalls.md** - What NOT to do
4. **Follow numbered order** - Dependencies are handled
5. **Check 63-cross-references.md** - For related specs
6. **Use diagrams/** - Visual understanding

---

## ✅ Validation Status

- **Enum Consistency**: ✅ All 20+ enums validated across specs
- **Table Count**: ✅ 27 tables consistent across schema and ERD
- **API Namespace**: ✅ Standardized as `eqm/v1`
- **Cookie Pattern**: ✅ `eqm_{purpose}_{examSlug}` enforced
- **Error Codes**: ✅ Categorized ranges (1xxx-9xxx)
- **Cross-References**: ✅ All links validated
- **Feature Flags**: ✅ 18 seeded flags with rollout control

---

**🎉 SPECIFICATION SUITE COMPLETE - PRODUCTION READY**

*112 files providing comprehensive guidance for AI tools and WordPress developers.*
