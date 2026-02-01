# Memory: wp-plugins/link-manager

> **Updated:** 2026-01-31  
> **Status:** Specification Complete

---

## Plugin Summary

**Link Manager** is a WordPress plugin for comprehensive link management across posts, pages, and categories. It scans content for hyperlinks (including JSON-LD/schema), categorizes by status and context, and provides tools for bulk editing with full history tracking.

---

## Key Features

1. **Link Scanning**: Parallel scanning of posts/pages, broken link detection, CSV import
2. **Link Categorization**: Status (broken/working), word count, wrapper context (H1-H6, strong, em)
3. **Modifications**: Remove links, change URLs, manage title attributes, remove wrapper tags
4. **History System**: Per-content SQLite databases, full versioning, rollback to any point
5. **Snapshots**: Full database backups with named restore points
6. **Elementor Support**: Deep integration with Elementor widget data structures
7. **Internal Linking**: Template-based auto-link creation with variable injection
8. **Health Monitoring**: Periodic link validation with alerts and notifications
9. **Notification System**: Multi-channel alerts (email, webhooks, admin notices)
10. **Yoast SEO Integration**: Focus keyword optimization and meta description management
11. **AI Provider Settings**: Seedable config for OpenAI, Gemini, Anthropic, Mistral, Groq, Ollama with OAuth support

---

## Architecture Summary

| Metric | Count |
|--------|-------|
| **Spec Files** | 30 |
| **Database Tables** | 29 |
| **Entity Classes** | 34 |
| **REST Endpoints** | 110+ |
| **Core Services** | 13 |
| **UI Pages** | 7 |
| **Error Codes** | 81 (14000-14999) |
| **Enums** | 30 |

---

## Database Architecture

- **Main DB**: `wp-content/uploads/link-manager/link-manager.db` (29 tables)
- **History DBs**: `history-manage/{posts|pages|categories}/{id}-{slug}.db`
- **Snapshots**: `snapshots/{number}-{name}-{date}.db`

### Table Categories
| Category | Tables | Count |
|----------|--------|-------|
| Core | Posts, Pages, Categories, Links, Settings | 5 |
| Scan | ScanHistory, ScanJobs, ScanQueue | 3 |
| History | ContentVersions, ModificationLog | 2 |
| Cron | CronJobs, CronLogs | 2 |
| Internal Linking | LinkTargets, LinkTemplates, InternalLinks, VariableFiles, AutoLinkJobs, AutoLinkQueue, AutoLinkSchedules | 7 |
| Health Monitor | LinkHealthChecks, HealthAlerts, HealthCheckJobs, HealthExclusions | 4 |
| Notifications | NotificationQueue, NotificationRecipients, WebhookEndpoints, NotificationLog, NotificationSettings | 5 |
| Yoast SEO | YoastSettings, YoastAuditLog, YoastOptimizationQueue | 3 |
| AI Providers | AiProviders, AiProviderCredentials, AiModels, AiOAuthSessions | 4 |
| **Total** | | **29** |

---

## Error Code Range

**14xxx** - Link Manager specific errors (14000-14999)

| Range | Category |
|-------|----------|
| 14000-14099 | General/Plugin errors |
| 14100-14199 | Scan errors |
| 14200-14299 | Parser errors |
| 14300-14399 | Modification errors |
| 14400-14499 | History/Rollback errors |
| 14500-14599 | Snapshot errors |
| 14600-14699 | CSV Import errors |
| 14700-14799 | Cron/Background errors |
| 14800-14829 | AI Provider errors |
| 14830-14849 | API errors |
| 14850-14899 | Health Monitor/Notifications |
| 14900-14999 | Internal Linking errors |

---

## Spec Location

`spec/wp-plugin/link-manager/`

### Spec Files (30 total)

**Root Level (4 files)**
| # | File | Description |
|---|------|-------------|
| 00 | `00-overview.md` | Master index and overview |
| 66 | `66-shared-constants.md` | SSOT for enums/errors |
| 98 | `98-consistency-report.md` | Cross-reference validation |
| 99 | `99-architecture-diagram.md` | Visual architecture |

**Backend - 01-admin-backend/split-spec (19 files)**
| # | File | Description |
|---|------|-------------|
| 00 | `00-overview.md` | Backend index |
| 03 | `03-plugin-structure.md` | PSR-4 file organization |
| 04 | `04-database-schema.md` | SQLite table definitions (29 tables) |
| 08 | `08-entity-models.md` | PHP entity classes (34 entities) |
| 09 | `09-scan-service.md` | Parallel scanning engine |
| 10 | `10-link-parser.md` | HTML/JSON-LD parsing |
| 11 | `11-elementor-integration.md` | Elementor widget handling |
| 12 | `12-history-service.md` | Version tracking system |
| 13 | `13-snapshot-service.md` | Backup/restore system |
| 14 | `14-modification-service.md` | Link modification operations |
| 15 | `15-csv-import.md` | CSV broken link import |
| 16 | `16-cron-system.md` | Background scanning via WP-Cron |
| 17 | `17-rest-api-endpoints.md` | Complete API reference (110+ endpoints) |
| 22 | `22-internal-linking-service.md` | Auto-linking engine |
| 23 | `23-link-health-monitor.md` | Periodic link validation |
| 24 | `24-notification-service.md` | Multi-channel alerts |
| 26 | `26-cron-auto-linking.md` | Background auto-linking |
| 27 | `27-yoast-seo-integration.md` | Yoast SEO optimization |
| 29 | `29-ai-provider-settings.md` | AI provider configuration |

**Admin UI - 02-admin-ui/split-spec (7 files)**
| # | File | Description |
|---|------|-------------|
| 00 | `00-overview.md` | UI index |
| 18 | `18-overview-page.md` | Main dashboard with tabs |
| 19 | `19-content-detail-page.md` | Individual link management |
| 20 | `20-settings-page.md` | Plugin configuration |
| 21 | `21-internal-linking-page.md` | Internal linking UI |
| 25 | `25-notification-settings-page.md` | Notification configuration |
| 28 | `28-yoast-seo-page.md` | Yoast SEO UI |
| 30 | `30-ai-provider-settings-page.md` | AI provider UI |

---

## Implementation Status

| Phase | Status |
|-------|--------|
| Specification | ✅ Complete (30 files) |
| Foundation | ⬜ Not Started |
| Core Engine | ⬜ Not Started |
| History System | ⬜ Not Started |
| Internal Linking | ⬜ Not Started |
| Health Monitor | ⬜ Not Started |
| Notifications | ⬜ Not Started |
| Yoast Integration | ⬜ Not Started |
| AI Providers | ⬜ Not Started |
| Admin UI | ⬜ Not Started |

---

## Related Memories

- [Link Manager Architecture](./link-manager-architecture.md)
- [Link Manager Plan](./link-manager-plan.md)
- [WordPress Plugin Patterns](../constraints/wordpress-patterns.md)
