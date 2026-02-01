# 01 - Admin Backend Overview

> **Status:** Complete  
> **Priority:** Critical  
> **Updated:** 2026-01-31

---

## Purpose

Backend services for the Link Manager WordPress plugin, handling link scanning, parsing, modifications, history tracking, and snapshot management.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        REST API (lm/v1)                             │
├─────────────────────────────────────────────────────────────────────┤
│  ScanController  │  ContentController  │  SnapshotController       │
│  HistoryController  │  SettingsController  │  ImportController     │
│  InternalLinkingController  │  AutoLinkController                   │
├─────────────────────────────────────────────────────────────────────┤
│                         Services Layer                              │
├────────────┬────────────┬────────────┬────────────┬────────┬───────┤
│ Scan       │ LinkParser │ Modification│ History   │Snapshot│Internal│
│ Service    │            │ Service     │ Service   │Service │Linking │
├────────────┴────────────┴────────────┴────────────┴────────┴───────┤
│                      Elementor Integration                          │
├─────────────────────────────────────────────────────────────────────┤
│                      Database Layer (SQLite)                        │
│  ┌─────────────┐  ┌──────────────────┐  ┌─────────────────────┐    │
│  │link-manager │  │ history-manage/  │  │    snapshots/       │    │
│  │    .db      │  │  {id}-{slug}.db  │  │ {seq}-{name}.db     │    │
│  └─────────────┘  └──────────────────┘  └─────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Service Responsibilities

| Service | Responsibility |
|---------|----------------|
| **ScanService** | Orchestrates parallel content scanning |
| **LinkParser** | Extracts links from HTML and JSON-LD |
| **ModificationService** | Applies link changes with validation |
| **HistoryService** | Tracks all modifications per content |
| **SnapshotService** | Full database backup and restore |
| **ElementorService** | Handles Elementor-specific data structures |
| **InternalLinkingService** | Auto-link creation, templates, variables |
| **CronAutoLinkingService** | Scheduled background auto-linking |
| **LinkHealthMonitorService** | Periodic link health checks and alerts |
| **NotificationService** | Email/webhook alerts and digests |
| **YoastIntegrationService** | Yoast SEO keyword/description optimization |

---

## Spec Files

| File | Description |
|------|-------------|
| `03-plugin-structure.md` | PSR-4 file organization |
| `04-database-schema.md` | SQLite table definitions (25 tables) |
| `08-entity-models.md` | PHP entity classes (27 entities) |
| `09-scan-service.md` | Parallel scanning engine |
| `10-link-parser.md` | HTML/JSON-LD parsing |
| `11-elementor-integration.md` | Elementor widget handling |
| `12-history-service.md` | Version tracking system |
| `13-snapshot-service.md` | Backup/restore system |
| `14-modification-service.md` | Link modification operations |
| `15-csv-import.md` | CSV import functionality |
| `16-cron-system.md` | Background scanning with WP-Cron |
| `17-rest-api-endpoints.md` | REST API (lm/v1) endpoints |
| `21-internal-linking-service.md` | Auto-linking with templates and variables |
| `22-cron-auto-linking.md` | Scheduled internal linking via WP-Cron |
| `23-link-health-monitor.md` | Periodic link health monitoring and alerts |
| `24-notification-service.md` | Email/webhook notification delivery |
| `26-email-templates.md` | HTML/plain text email templates with variables |
| `27-yoast-seo-integration.md` | Yoast SEO keyword/description optimization |

---

## Error Code Range

**14xxx** - Link Manager errors (14000-14999)

| Range | Category |
|-------|----------|
| 14000-14099 | General/Plugin errors |
| 14100-14199 | Scan errors |
| 14200-14299 | Parsing errors |
| 14300-14399 | Modification errors |
| 14400-14499 | History/Rollback errors |
| 14500-14599 | Snapshot errors |
| 14600-14699 | CSV Import errors |
| 14700-14799 | Cron errors |
| 14800-14849 | API errors |
| 14850-14859 | Health Monitor errors |
| 14860-14879 | Notification errors |
| 14900-14949 | Internal Linking errors |
| 14950-14999 | Yoast SEO Integration errors |

---

## Related Specs

- `../66-shared-constants.md` - Enums, error codes, and constants (SSOT)
- `../02-admin-ui/` - Frontend interface
