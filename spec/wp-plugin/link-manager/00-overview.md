# Link Manager - Specification Overview

> **Version:** 2.1.0  
> **Last Updated:** 2026-01-31  
> **Status:** ✅ Specification Complete  
> **Slug:** `link-manager`  
> **API Namespace:** `lm/v1`  
> **Error Code Range:** 14xxx (14000-14999)

---

## 📊 Architecture Summary

| Metric | Count |
|--------|-------|
| **Spec Files** | 30 |
| **Database Tables** | 29 |
| **Entity Classes** | 34 |
| **REST Endpoints** | 110+ |
| **Core Services** | 13 |
| **UI Pages** | 7 |
| **Error Codes** | 81 |
| **Enums** | 30 |

---

## 🎯 Purpose

A WordPress plugin for comprehensive link management across blog posts, pages, and categories. The plugin scans content for all hyperlinks (including JSON-LD/schema markup), categorizes them by status (broken, working), wrapper context (H1-H6, strong, em), and provides tools for bulk editing, removal, and attribute management with full history tracking and rollback capabilities.

---

## 📁 Directory Structure

```
spec/wp-plugin/link-manager/
├── 00-overview.md                    # This file - Master index
├── 66-shared-constants.md            # SSOT for enums and error codes
├── 98-consistency-report.md          # Cross-reference validation
├── 99-architecture-diagram.md        # Visual architecture diagrams
├── 01-admin-backend/
│   ├── 00-overview.md                # Backend architecture summary
│   └── split-spec/
│       ├── 03-plugin-structure.md    # Bootstrap, PSR-4, lifecycle
│       ├── 04-database-schema.md     # SQLite schema (29 tables)
│       ├── 08-entity-models.md       # PHP entity classes (34 entities)
│       ├── 09-scan-service.md        # Parallel scanning engine
│       ├── 10-link-parser.md         # HTML/JSON-LD parsing + wrapper detection
│       ├── 11-elementor-integration.md # Elementor widget handling
│       ├── 12-history-service.md     # Version history management
│       ├── 13-snapshot-service.md    # Database snapshots
│       ├── 14-modification-service.md # Link editing/removal
│       ├── 15-csv-import.md          # CSV broken link import
│       ├── 16-cron-system.md         # Background scanning
│       ├── 17-rest-api-endpoints.md  # API reference (110+ endpoints)
│       ├── 22-internal-linking-service.md # Auto-linking engine
│       ├── 23-link-health-monitor.md # Periodic link validation
│       ├── 24-notification-service.md # Multi-channel alerts
│       ├── 26-cron-auto-linking.md   # Background auto-linking
│       ├── 27-yoast-seo-integration.md # Yoast SEO optimization
│       └── 29-ai-provider-settings.md # AI provider configuration
└── 02-admin-ui/
    ├── 00-overview.md                # UI architecture summary
    └── split-spec/
        ├── 18-overview-page.md       # Main dashboard with tabs
        ├── 19-content-detail-page.md # Single post/page link management
        ├── 20-settings-page.md       # Plugin configuration
        ├── 21-internal-linking-page.md # Internal linking UI
        ├── 25-notification-settings-page.md # Notification config
        ├── 28-yoast-seo-page.md      # Yoast SEO UI
        └── 30-ai-provider-settings-page.md # AI provider UI
```

---

## 🔑 Key Features

### 1. Link Scanning Engine
- **Parallel scanning** of posts and pages
- **Scan modes**: All links, Broken links only, CSV import
- **Content sources**: Post content, JSON-LD, Schema markup
- **Cron support**: Background scanning without page stay

### 2. Link Categorization
- **Status**: Broken, Working, Unknown
- **Word count**: 1-word, 2-word, 3+ words
- **Wrapper context**: H1-H6, strong, em, nested combinations
- **Has title attribute**: Yes/No

### 3. Modification Capabilities
- **Remove A tag, keep text**: Removes hyperlink, preserves anchor text
- **Remove A tag, remove href only**: Keeps `<a>` element without href
- **Remove title attribute**: Bulk removal from links
- **Change link URL**: Replace href values
- **Remove wrapper tags**: Remove H tags, strong, em while keeping content
- **Bulk operations**: Apply to multiple links at once

### 4. History & Rollback
- **Per-post history database**: `{id}-{slug}.db` files
- **Full content versioning**: Before/after snapshots
- **Rollback to any version**: Restore previous states
- **Modification audit trail**: Who, when, what changed

### 5. Snapshot System
- **Database snapshots**: Full SQLite backup
- **Named snapshots**: User-defined names with timestamps
- **Auto-snapshot**: Optional on first modification
- **Restore capability**: Full or selective table restoration

### 6. Internal Linking
- **Template-based links**: Variable injection from CSV/JSON
- **Anchor text matching**: 2-5 word phrase detection
- **Selection modes**: Sequential, Random, Weighted
- **Auto-linking**: Background WP-Cron processing

### 7. Health Monitoring
- **Periodic validation**: HTTP status, response times, SSL expiry
- **Priority scheduling**: Exponential backoff for failures
- **Alert system**: Broken/slow link notifications
- **Batch processing**: 25 links per cron run

### 8. Notification System
- **Multi-channel**: Email, Webhooks, Admin Notices
- **Digest reports**: Daily/weekly summaries
- **Webhook auth**: HMAC-SHA256, Bearer, Basic
- **Batch processing**: 50 notifications per run

### 9. Yoast SEO Integration
- **Focus keyword optimization**: Auto-inject keyword links
- **Meta description trimming**: Word/char/sentence modes
- **Audit logging**: Full change history with revert
- **Batch optimization**: Queue-based processing

---

## 🗄️ Database Architecture

### Main Database (`link-manager.db`) - 25 Tables

#### Core Tables (5)
| Table | Purpose |
|-------|---------|
| `Posts` | Scanned posts with link counts |
| `Pages` | Scanned pages with link counts |
| `Categories` | Category information |
| `Links` | All discovered links with metadata |
| `Settings` | Plugin configuration |

#### Scan Tables (3)
| Table | Purpose |
|-------|---------|
| `ScanHistory` | Scan operation records |
| `ScanJobs` | Pending scan jobs |
| `ScanQueue` | Items queued for scanning |

#### History Tables (2)
| Table | Purpose |
|-------|---------|
| `Snapshots` | Snapshot metadata |
| `RestoreHistory` | Restore operation audit log |

#### Cron Tables (2)
| Table | Purpose |
|-------|---------|
| `CronJobs` | Scheduled background jobs |
| `CronLogs` | Cron execution history |

#### Internal Linking Tables (7)
| Table | Purpose |
|-------|---------|
| `LinkTargets` | Target URLs for auto-linking |
| `LinkTemplates` | HTML templates for links |
| `InternalLinks` | Created internal links |
| `VariableFiles` | CSV/JSON variable sources |
| `AutoLinkJobs` | Auto-linking job records |
| `AutoLinkQueue` | Pending auto-link items |
| `AutoLinkSchedules` | Scheduled auto-linking |

#### Health Monitor Tables (4)
| Table | Purpose |
|-------|---------|
| `LinkHealthChecks` | Health check results |
| `HealthAlerts` | Active health alerts |
| `HealthCheckJobs` | Pending health jobs |
| `HealthExclusions` | Excluded URLs |

#### Notification Tables (5)
| Table | Purpose |
|-------|---------|
| `NotificationQueue` | Pending notifications |
| `NotificationRecipients` | Recipient configuration |
| `WebhookEndpoints` | Webhook URLs and auth |
| `NotificationLog` | Sent notification history |
| `NotificationSettings` | Channel settings |

#### Yoast SEO Tables (3)
| Table | Purpose |
|-------|---------|
| `YoastSettings` | SEO optimization settings |
| `YoastAuditLog` | SEO change audit trail |
| `YoastOptimizationQueue` | Pending optimizations |

#### AI Provider Tables (4)
| Table | Purpose |
|-------|---------|
| `AiProviders` | Provider configurations (seedable) |
| `AiProviderCredentials` | Encrypted API keys/tokens |
| `AiModels` | Model definitions with custom names |
| `AiOAuthSessions` | OAuth token storage |

### History Databases (`history-manage/{type}/{id}-{slug}.db`)
| Table | Purpose |
|-------|---------|
| `ContentVersions` | Content snapshots per modification |
| `ModificationLog` | Detailed change records |

---

## 🔧 Services (13 total)

| Category | Service | Spec |
|----------|---------|------|
| **Core** | ScanService | 09 |
| **Core** | LinkParser | 10 |
| **Core** | ModificationService | 14 |
| **Core** | HistoryService | 12 |
| **Core** | SnapshotService | 13 |
| **Integration** | ElementorService | 11 |
| **Integration** | CSVImportService | 15 |
| **Integration** | InternalLinkingService | 22 |
| **Monitoring** | HealthMonitorService | 23 |
| **Monitoring** | NotificationService | 24 |
| **SEO** | YoastSeoService | 27 |
| **AI** | AiProviderService | 29 |
| **Background** | CronService | 16, 26 |

---

## 📊 Error Code Range

**14xxx - Link Manager Errors (81 codes)**

| Range | Category |
|-------|----------|
| 14000-14099 | General plugin errors |
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

## 🔄 Implementation Order

### Phase 1: Foundation
```
03-plugin-structure → 04-database-schema → 08-entity-models → 66-shared-constants
```

### Phase 2: Core Engine
```
10-link-parser → 09-scan-service → 11-elementor-integration
```

### Phase 3: History System
```
14-modification-service → 12-history-service → 13-snapshot-service
```

### Phase 4: Import & Background
```
15-csv-import → 16-cron-system
```

### Phase 5: Internal Linking
```
22-internal-linking-service → 26-cron-auto-linking
```

### Phase 6: Health & Notifications
```
23-link-health-monitor → 24-notification-service
```

### Phase 7: SEO Integration
```
27-yoast-seo-integration
```

### Phase 8: AI Provider Integration
```
29-ai-provider-settings
```

### Phase 9: API & UI
```
17-rest-api-endpoints → 18-overview-page → 19-content-detail-page → 
20-settings-page → 21-internal-linking-page → 25-notification-settings-page → 
28-yoast-seo-page → 30-ai-provider-settings-page
```

---

## ✅ Specification Status

### Root Level (4 files)
| # | Spec File | Status |
|---|-----------|--------|
| 00 | `00-overview.md` | ✅ Complete |
| 66 | `66-shared-constants.md` | ✅ Complete |
| 98 | `98-consistency-report.md` | ✅ Complete |
| 99 | `99-architecture-diagram.md` | ✅ Complete |

### Backend (19 files)
| # | Spec File | Status |
|---|-----------|--------|
| 00 | `00-overview.md` | ✅ Complete |
| 03 | `03-plugin-structure.md` | ✅ Complete |
| 04 | `04-database-schema.md` | ✅ Complete |
| 08 | `08-entity-models.md` | ✅ Complete |
| 09 | `09-scan-service.md` | ✅ Complete |
| 10 | `10-link-parser.md` | ✅ Complete |
| 11 | `11-elementor-integration.md` | ✅ Complete |
| 12 | `12-history-service.md` | ✅ Complete |
| 13 | `13-snapshot-service.md` | ✅ Complete |
| 14 | `14-modification-service.md` | ✅ Complete |
| 15 | `15-csv-import.md` | ✅ Complete |
| 16 | `16-cron-system.md` | ✅ Complete |
| 17 | `17-rest-api-endpoints.md` | ✅ Complete |
| 22 | `22-internal-linking-service.md` | ✅ Complete |
| 23 | `23-link-health-monitor.md` | ✅ Complete |
| 24 | `24-notification-service.md` | ✅ Complete |
| 26 | `26-cron-auto-linking.md` | ✅ Complete |
| 27 | `27-yoast-seo-integration.md` | ✅ Complete |
| 29 | `29-ai-provider-settings.md` | ✅ Complete |

### UI (7 files)
| # | Spec File | Status |
|---|-----------|--------|
| 00 | `00-overview.md` | ✅ Complete |
| 18 | `18-overview-page.md` | ✅ Complete |
| 19 | `19-content-detail-page.md` | ✅ Complete |
| 20 | `20-settings-page.md` | ✅ Complete |
| 21 | `21-internal-linking-page.md` | ✅ Complete |
| 25 | `25-notification-settings-page.md` | ✅ Complete |
| 28 | `28-yoast-seo-page.md` | ✅ Complete |
| 30 | `30-ai-provider-settings-page.md` | ✅ Complete |

---

## 📋 Related Documentation

| Reference | Location |
|-----------|----------|
| Memory Index | `.lovable/memories/wp-plugins/link-manager.md` |
| Architecture Memory | `.lovable/memories/wp-plugins/link-manager-architecture.md` |
| Implementation Plan | `.lovable/memories/wp-plugins/link-manager-plan.md` |
| Architecture Diagram | `99-architecture-diagram.md` |
| Consistency Report | `98-consistency-report.md` |
