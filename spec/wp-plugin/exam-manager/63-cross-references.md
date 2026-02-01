# Specification Cross-Reference Index

> **Version:** 1.0.0  
> **Last Updated:** 2026-01-26  
> **Status:** Active  
> **Purpose:** Navigation guide linking related specifications for easier implementation

---

## Quick Navigation by Feature

### 🔐 Authentication & Access Control

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| User Roles | [10-rbac-system](01-admin-backend/split-spec/10-rbac-system.md) | [11-rbac-admin-ui](01-admin-backend/split-spec/11-rbac-admin-ui.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (UserRoleType) |
| Secret Key Access | [24-secret-key-service](01-admin-backend/split-spec/24-secret-key-service.md) | [25-secret-key-admin-ui](01-admin-backend/split-spec/25-secret-key-admin-ui.md), [26-secret-key-analytics](01-admin-backend/split-spec/26-secret-key-analytics.md), [diagrams/03-secret-key-auth-flow](diagrams/03-secret-key-auth-flow.md) |
| Anonymous Migration | [27-participant-service](01-admin-backend/split-spec/27-participant-service.md) §25.7-25.8 | [24-secret-key-service](01-admin-backend/split-spec/24-secret-key-service.md) §22.8 |
| **Invite-Only Access** | [50-exam-invite-management](01-admin-backend/split-spec/50-exam-invite-management.md) | [28-invite-signup-flow](02-frontend/split-spec/28-invite-signup-flow.md), [36-rest-api-endpoints](01-admin-backend/split-spec/36-rest-api-endpoints.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (InviteStatus) |

### 📝 Exam Management

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| Exam CRUD | [12-exam-service](01-admin-backend/split-spec/12-exam-service.md) | [13-exam-hierarchy](01-admin-backend/split-spec/13-exam-hierarchy.md), [14-exam-editor-ui](01-admin-backend/split-spec/14-exam-editor-ui.md) |
| Exam Editor Tabs | [14-exam-editor-ui](01-admin-backend/split-spec/14-exam-editor-ui.md) | [15-exam-content-tab](01-admin-backend/split-spec/15-exam-content-tab.md), [16-exam-metadata-tab](01-admin-backend/split-spec/16-exam-metadata-tab.md), [17-exam-subexams-tab](01-admin-backend/split-spec/17-exam-subexams-tab.md), [18-exam-prerequisites-tab](01-admin-backend/split-spec/18-exam-prerequisites-tab.md), [19-exam-checklists-tab](01-admin-backend/split-spec/19-exam-checklists-tab.md) |
| Exam Presets | [51-exam-preset-settings](01-admin-backend/split-spec/51-exam-preset-settings.md) | [04-database-schema](01-admin-backend/split-spec/04-database-schema.md) (examPreset), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (PresetCategory), [35-plugin-settings](01-admin-backend/split-spec/35-plugin-settings.md) |
| H2 Section Extraction | [12-exam-service](01-admin-backend/split-spec/12-exam-service.md) §10.6 | [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (Regex in Consts.php), [28-participant-progress](01-admin-backend/split-spec/28-participant-progress.md) |
| Checklist Submissions | [19-exam-checklists-tab](01-admin-backend/split-spec/19-exam-checklists-tab.md) | [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (SubmissionType, SubmissionValidationMode), [04-database-schema](01-admin-backend/split-spec/04-database-schema.md) (participantChecklist) |
| Sub-Exams | [13-exam-hierarchy](01-admin-backend/split-spec/13-exam-hierarchy.md) | [17-exam-subexams-tab](01-admin-backend/split-spec/17-exam-subexams-tab.md), [29-deadline-engine](01-admin-backend/split-spec/29-deadline-engine.md) (inheritDeadline) |
| Public View | [43-public-exam-view](01-admin-backend/split-spec/43-public-exam-view.md) | [24-secret-key-service](01-admin-backend/split-spec/24-secret-key-service.md), [27-participant-service](01-admin-backend/split-spec/27-participant-service.md) |

### 👥 Participant Lifecycle

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| Participant CRUD | [27-participant-service](01-admin-backend/split-spec/27-participant-service.md) | [39-participant-management](01-admin-backend/split-spec/39-participant-management.md), [diagrams/02-participant-status-states](diagrams/02-participant-status-states.md) |
| Status Transitions | [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (ParticipantStatus) | [27-participant-service](01-admin-backend/split-spec/27-participant-service.md) §25.3, [diagrams/02-participant-status-states](diagrams/02-participant-status-states.md) |
| Progress Tracking | [28-participant-progress](01-admin-backend/split-spec/28-participant-progress.md) | [12-exam-service](01-admin-backend/split-spec/12-exam-service.md) (H2 extraction), [19-exam-checklists-tab](01-admin-backend/split-spec/19-exam-checklists-tab.md) |
| **Participant Submissions** | [29-participant-submission-ui](02-frontend/split-spec/29-participant-submission-ui.md) | [19-exam-checklists-tab](01-admin-backend/split-spec/19-exam-checklists-tab.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (SubmissionType) |
| Anonymous Tracking | [27-participant-service](01-admin-backend/split-spec/27-participant-service.md) §25.7 | [24-secret-key-service](01-admin-backend/split-spec/24-secret-key-service.md), [SHARED-CONSTANTS](SHARED-CONSTANTS.md) (Cookie naming) |

### ⏰ Deadline System

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| Deadline Types | [29-deadline-engine](01-admin-backend/split-spec/29-deadline-engine.md) §27.1 | [diagrams/04-deadline-calculation-flow](diagrams/04-deadline-calculation-flow.md) |
| Deadline Calculation | [29-deadline-engine](01-admin-backend/split-spec/29-deadline-engine.md) §27.3 | [27-participant-service](01-admin-backend/split-spec/27-participant-service.md), [30-extension-system](01-admin-backend/split-spec/30-extension-system.md) |
| Extensions | [30-extension-system](01-admin-backend/split-spec/30-extension-system.md) | [29-deadline-engine](01-admin-backend/split-spec/29-deadline-engine.md) §27.3, [SHARED-CONSTANTS](SHARED-CONSTANTS.md) (File limits) |
| Cron Enforcement | [29-deadline-engine](01-admin-backend/split-spec/29-deadline-engine.md) §27.6 | [34-cron-system](01-admin-backend/split-spec/34-cron-system.md), [diagrams/04-deadline-calculation-flow](diagrams/04-deadline-calculation-flow.md) |
| Notifications | [29-deadline-engine](01-admin-backend/split-spec/29-deadline-engine.md) §27.7 | [32-notification-service](01-admin-backend/split-spec/32-notification-service.md), [33-email-templates](01-admin-backend/split-spec/33-email-templates.md) |

### 📚 Wiki System

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| Wiki CRUD | [20-wiki-service](01-admin-backend/split-spec/20-wiki-service.md) | [22-wiki-editor-ui](01-admin-backend/split-spec/22-wiki-editor-ui.md) |
| Categories | [21-wiki-categories](01-admin-backend/split-spec/21-wiki-categories.md) | [20-wiki-service](01-admin-backend/split-spec/20-wiki-service.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (WikiVisibility) |
| Revisions | [23-wiki-revisions](01-admin-backend/split-spec/23-wiki-revisions.md) | [20-wiki-service](01-admin-backend/split-spec/20-wiki-service.md), [46-audit-logging](01-admin-backend/split-spec/46-audit-logging.md) |

### 📧 Email & Notifications

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| Email Queue | [31-email-queue](01-admin-backend/split-spec/31-email-queue.md) | [34-cron-system](01-admin-backend/split-spec/34-cron-system.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (EmailStatus, EmailPriority) |
| Templates | [33-email-templates](01-admin-backend/split-spec/33-email-templates.md) | [31-email-queue](01-admin-backend/split-spec/31-email-queue.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (EmailTemplateKey) |
| In-App Notifications | [32-notification-service](01-admin-backend/split-spec/32-notification-service.md) | [45-notifications-panel](01-admin-backend/split-spec/45-notifications-panel.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (NotificationType) |

### 🗄️ Data Layer

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| Database Schema | [04-database-schema](01-admin-backend/split-spec/04-database-schema.md) | [diagrams/01-database-er-diagram](diagrams/01-database-er-diagram.md), [05-orm-base-classes](01-admin-backend/split-spec/05-orm-base-classes.md) |
| Entity Models | [08-entity-models](01-admin-backend/split-spec/08-entity-models.md) | [04-database-schema](01-admin-backend/split-spec/04-database-schema.md), [05-orm-base-classes](01-admin-backend/split-spec/05-orm-base-classes.md) |
| Enums | [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) | All service specs (use enums) |
| Validation | [09-validation-utilities](01-admin-backend/split-spec/09-validation-utilities.md) | [02-error-management](01-admin-backend/split-spec/02-error-management.md), [SHARED-CONSTANTS](SHARED-CONSTANTS.md) |

### ⚙️ System Infrastructure

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| Plugin Structure | [03-plugin-structure](01-admin-backend/split-spec/03-plugin-structure.md) | [01-coding-spec](01-admin-backend/split-spec/01-coding-spec.md) |
| Error Handling | [02-error-management](01-admin-backend/split-spec/02-error-management.md) | [07-logging-system](01-admin-backend/split-spec/07-logging-system.md), [SHARED-CONSTANTS](SHARED-CONSTANTS.md) (Error codes) |
| Settings | [35-plugin-settings](01-admin-backend/split-spec/35-plugin-settings.md) | [02-error-management](01-admin-backend/split-spec/02-error-management.md) (3-tier config) |
| Rate Limiting | [48-rate-limiting](01-admin-backend/split-spec/48-rate-limiting.md) | [24-secret-key-service](01-admin-backend/split-spec/24-secret-key-service.md), [36-rest-api-endpoints](01-admin-backend/split-spec/36-rest-api-endpoints.md) |
| Cron Jobs | [34-cron-system](01-admin-backend/split-spec/34-cron-system.md) | [29-deadline-engine](01-admin-backend/split-spec/29-deadline-engine.md), [31-email-queue](01-admin-backend/split-spec/31-email-queue.md) |
| REST API | [36-rest-api-endpoints](01-admin-backend/split-spec/36-rest-api-endpoints.md) | All service specs (expose endpoints) |
| Audit Logging | [46-audit-logging](01-admin-backend/split-spec/46-audit-logging.md) | [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (AuditAction), [07-logging-system](01-admin-backend/split-spec/07-logging-system.md) |
| **Theming System** | [56-theming-system](01-admin-backend/split-spec/56-theming-system.md) | [31-theme-application](02-frontend/split-spec/31-theme-application.md), [SHARED-CONSTANTS](SHARED-CONSTANTS.md), [35-plugin-settings](01-admin-backend/split-spec/35-plugin-settings.md) |
| **Caching System** | [57-caching-system](01-admin-backend/split-spec/57-caching-system.md) | [27-performance-targets](02-frontend/split-spec/27-performance-targets.md), [53-monitoring-alerting](01-admin-backend/split-spec/53-monitoring-alerting.md) |
| **Feature Flags** | [58-feature-flags](01-admin-backend/split-spec/58-feature-flags.md) | [35-plugin-settings](01-admin-backend/split-spec/35-plugin-settings.md), [56-theming-system](01-admin-backend/split-spec/56-theming-system.md), [46-audit-logging](01-admin-backend/split-spec/46-audit-logging.md) |

### 📊 Admin UI

| Topic | Primary Spec | Related Specs |
|-------|-------------|---------------|
| Dashboard | [37-admin-dashboard](01-admin-backend/split-spec/37-admin-dashboard.md) | [47-reporting-dashboard](01-admin-backend/split-spec/47-reporting-dashboard.md) |
| Exam List | [38-exam-list-view](01-admin-backend/split-spec/38-exam-list-view.md) | [14-exam-editor-ui](01-admin-backend/split-spec/14-exam-editor-ui.md) |
| Participant Management | [39-participant-management](01-admin-backend/split-spec/39-participant-management.md) | [27-participant-service](01-admin-backend/split-spec/27-participant-service.md), [30-extension-system](01-admin-backend/split-spec/30-extension-system.md) |
| Import/Export | [40-import-export-system](01-admin-backend/split-spec/40-import-export-system.md) | [27-participant-service](01-admin-backend/split-spec/27-participant-service.md), [12-exam-service](01-admin-backend/split-spec/12-exam-service.md) |
| **Exam Invites** | [50-exam-invite-management](01-admin-backend/split-spec/50-exam-invite-management.md) | [04-database-schema](01-admin-backend/split-spec/04-database-schema.md) (examInvite), [33-email-templates](01-admin-backend/split-spec/33-email-templates.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (InviteStatus) |
| **Exam Presets** | [51-exam-preset-settings](01-admin-backend/split-spec/51-exam-preset-settings.md) | [04-database-schema](01-admin-backend/split-spec/04-database-schema.md) (examPreset), [35-plugin-settings](01-admin-backend/split-spec/35-plugin-settings.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (PresetCategory) |
| **Review Queue** | [52-admin-review-queue](01-admin-backend/split-spec/52-admin-review-queue.md) | [19-exam-checklists-tab](01-admin-backend/split-spec/19-exam-checklists-tab.md), [29-participant-submission-ui](02-frontend/split-spec/29-participant-submission-ui.md), [06-enums-constants](01-admin-backend/split-spec/06-enums-constants.md) (SubmissionReviewStatus) |
| **Theme Manager** | [56-theming-system](01-admin-backend/split-spec/56-theming-system.md) §6 | [35-plugin-settings](01-admin-backend/split-spec/35-plugin-settings.md), [SHARED-CONSTANTS](SHARED-CONSTANTS.md) |
| **Cache Manager** | [57-caching-system](01-admin-backend/split-spec/57-caching-system.md) §10 | [53-monitoring-alerting](01-admin-backend/split-spec/53-monitoring-alerting.md) |

---

## Dependency Graph

### Core Foundation (Implement First)
```
01-coding-spec
    └── 02-error-management
    └── 03-plugin-structure
            └── 04-database-schema ──► 05-orm-base-classes ──► 08-entity-models
            └── 06-enums-constants (used by ALL specs)
            └── 07-logging-system
            └── 09-validation-utilities
```

### Service Layer (Implement Second)
```
08-entity-models
    ├── 10-rbac-system ──► 11-rbac-admin-ui
    ├── 12-exam-service ──► 13-exam-hierarchy
    │                   └── 14-22 (Editor tabs)
    ├── 20-wiki-service ──► 21-wiki-categories ──► 23-wiki-revisions
    │                   └── 22-wiki-editor-ui
    ├── 24-secret-key-service ──► 25-secret-key-admin-ui ──► 26-secret-key-analytics
    ├── 27-participant-service ──► 28-participant-progress
    ├── 29-deadline-engine ──► 30-extension-system
    ├── 31-email-queue ──► 32-notification-service ──► 33-email-templates
    └── 34-cron-system
```

### Integration Layer (Implement Third)
```
All Services
    ├── 35-plugin-settings (3-tier config)
    ├── 36-rest-api-endpoints
    ├── 46-audit-logging
    ├── 48-rate-limiting
    ├── 53-monitoring-alerting
    ├── 54-gdpr-data-privacy
    └── 55-webhooks-integrations
```

### UI Layer (Implement Fourth)
```
36-rest-api-endpoints
    ├── 37-admin-dashboard ──► 47-reporting-dashboard
    ├── 38-exam-list-view
    ├── 39-participant-management
    ├── 40-import-export-system
    ├── 43-public-exam-view
    ├── 44-certificate-generation
    └── 45-notifications-panel
```

---

## Diagrams Reference

| Diagram | File | Shows |
|---------|------|-------|
| Database ERD | [diagrams/01-database-er-diagram](diagrams/01-database-er-diagram.md) | All **27 tables** and relationships |
| Participant States | [diagrams/02-participant-status-states](diagrams/02-participant-status-states.md) | 9 status transitions, cron triggers |
| Secret Key Auth | [diagrams/03-secret-key-auth-flow](diagrams/03-secret-key-auth-flow.md) | 7-phase authentication sequence |
| Deadline Flow | [diagrams/04-deadline-calculation-flow](diagrams/04-deadline-calculation-flow.md) | Calculation, extension, cron enforcement |
| System Architecture | [diagrams/05-system-architecture](diagrams/05-system-architecture.md) | Complete component connections, data flow, implementation timeline |
| Submission Lifecycle | [diagrams/06-submission-lifecycle](diagrams/06-submission-lifecycle.md) | Participant input → validation → admin review → final status |

---

## New Specifications (Production-Ready)

| Spec | File | Purpose |
|------|------|---------|
| UI Design System | [22-ui-design-system](02-frontend/split-spec/22-ui-design-system.md) | Complete design tokens, dark mode, accessibility |
| Internationalization | [26-internationalization](02-frontend/split-spec/26-internationalization.md) | i18n patterns, RTL, locale detection |
| Performance Targets | [27-performance-targets](02-frontend/split-spec/27-performance-targets.md) | Core Web Vitals, API SLAs, bundle budgets |
| Browser Compatibility | [30-browser-compatibility](02-frontend/split-spec/30-browser-compatibility.md) | Browser matrix, polyfills, feature detection |
| **Theme Application** | [31-theme-application](02-frontend/split-spec/31-theme-application.md) | Frontend CSS variable injection, form/markdown styling |
| Test Fixtures | [49-test-fixtures](01-admin-backend/split-spec/49-test-fixtures.md) | Sample data, seed scripts, edge cases |
| Monitoring & Alerting | [53-monitoring-alerting](01-admin-backend/split-spec/53-monitoring-alerting.md) | Health checks, alerting rules, dashboards |
| GDPR & Data Privacy | [54-gdpr-data-privacy](01-admin-backend/split-spec/54-gdpr-data-privacy.md) | Data retention, RTBF, consent management |
| Webhooks & Integrations | [55-webhooks-integrations](01-admin-backend/split-spec/55-webhooks-integrations.md) | Event hooks, LMS/CRM integrations |
| **Theming System** | [56-theming-system](01-admin-backend/split-spec/56-theming-system.md) | Theme seeding, admin UI, CSS variable generation |
| **Caching System** | [57-caching-system](01-admin-backend/split-spec/57-caching-system.md) | Multi-layer caching, Memcached/Redis, page cache |
| Common Pitfalls | [61-common-implementation-pitfalls](61-common-implementation-pitfalls.md) | Comprehensive anti-patterns and correct patterns |

---

## Shared Constants

The [66-shared-constants.md](66-shared-constants.md) file is the authoritative source for:

- **Cookie naming**: `eqm_{purpose}_{examSlug}` pattern
- **API endpoints**: Full namespace paths
- **Validation limits**: Character counts, file sizes
- **Color schemes**: Deadline status colors
- **Error codes**: Categorized 1xxx-9xxx ranges

**Specs that MUST reference 66-shared-constants.md:**
- 24-secret-key-service (cookie naming)
- 27-participant-service (cookie naming, anonymous tracking)
- 29-deadline-engine (color schemes)
- 30-extension-system (file limits)
- 36-rest-api-endpoints (API paths)
- 02-error-management (error codes)

---

## Implementation Checklist by Phase

### Phase 1: Foundation
- [ ] 01-coding-spec
- [ ] 02-error-management
- [ ] 03-plugin-structure
- [ ] 04-database-schema
- [ ] 05-orm-base-classes
- [ ] 06-enums-constants
- [ ] 07-logging-system
- [ ] 08-entity-models
- [ ] 09-validation-utilities

### Phase 2: Core Services
- [ ] 10-rbac-system
- [ ] 12-exam-service
- [ ] 20-wiki-service
- [ ] 24-secret-key-service
- [ ] 27-participant-service
- [ ] 28-participant-progress
- [ ] 29-deadline-engine
- [ ] 30-extension-system

### Phase 3: Communication
- [ ] 31-email-queue
- [ ] 32-notification-service
- [ ] 33-email-templates
- [ ] 34-cron-system

### Phase 4: Infrastructure
- [ ] 35-plugin-settings
- [ ] 36-rest-api-endpoints
- [ ] 46-audit-logging
- [ ] 48-rate-limiting

### Phase 5: Admin UI
- [ ] 11-rbac-admin-ui
- [ ] 13-22 (Exam editor tabs)
- [ ] 25-secret-key-admin-ui
- [ ] 37-admin-dashboard
- [ ] 38-exam-list-view
- [ ] 39-participant-management
- [ ] 40-import-export-system

### Phase 6: Public Features
- [ ] 43-public-exam-view
- [ ] 44-certificate-generation
- [ ] 45-notifications-panel
- [ ] 47-reporting-dashboard
- [ ] 26-secret-key-analytics

---

## Consistency Reports

- [Backend Consistency Report](01-admin-backend/split-spec/59-consistency-report.md)
- [Frontend Consistency Report](02-frontend/split-spec/32-consistency-report.md)

---

## Statistics Summary

| Category | Count |
|----------|-------|
| Backend Specifications | 63 files |
| Frontend Specifications | 31 files |
| Mermaid Diagrams | 6 files |
| Cross-Cutting Documents | 9 files |
| **Total Files** | **104 files** |
| Database Tables | 27 |
| PHP Enums | 20+ |
| REST API Endpoints | 30+ |
| Documented Pitfalls | 50+ |
