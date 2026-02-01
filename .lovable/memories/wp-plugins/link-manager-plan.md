# Link Manager - Implementation Plan

> **Version:** 2.1.0  
> **Updated:** 2026-01-31  
> **Purpose:** Track future work and provide training context for AI models

---

## 📋 Overview

This plan documents pending work items, completed features, and architectural decisions for the Link Manager WordPress plugin. It serves as a training reference for AI models working on implementation.

---

## ✅ Completed Specifications (30 files)

### Root Level (4 files)
| # | File | Status |
|---|------|--------|
| 00 | `00-overview.md` | ✅ Complete |
| 66 | `66-shared-constants.md` | ✅ Complete |
| 98 | `98-consistency-report.md` | ✅ Complete |
| 99 | `99-architecture-diagram.md` | ✅ Complete |

### Backend - 01-admin-backend (19 files)
| # | File | Status |
|---|------|--------|
| 00 | `00-overview.md` | ✅ Complete |
| 03 | `03-plugin-structure.md` | ✅ Complete |
| 04 | `04-database-schema.md` | ✅ Complete (29 tables) |
| 08 | `08-entity-models.md` | ✅ Complete (34 entities) |
| 09 | `09-scan-service.md` | ✅ Complete |
| 10 | `10-link-parser.md` | ✅ Complete |
| 11 | `11-elementor-integration.md` | ✅ Complete |
| 12 | `12-history-service.md` | ✅ Complete |
| 13 | `13-snapshot-service.md` | ✅ Complete |
| 14 | `14-modification-service.md` | ✅ Complete |
| 15 | `15-csv-import.md` | ✅ Complete |
| 16 | `16-cron-system.md` | ✅ Complete |
| 17 | `17-rest-api-endpoints.md` | ✅ Complete (110+ endpoints) |
| 22 | `22-internal-linking-service.md` | ✅ Complete |
| 23 | `23-link-health-monitor.md` | ✅ Complete |
| 24 | `24-notification-service.md` | ✅ Complete |
| 26 | `26-cron-auto-linking.md` | ✅ Complete |
| 27 | `27-yoast-seo-integration.md` | ✅ Complete |
| 29 | `29-ai-provider-settings.md` | ✅ Complete |

### UI - 02-admin-ui (7 files)
| # | File | Status |
|---|------|--------|
| 00 | `00-overview.md` | ✅ Complete |
| 18 | `18-overview-page.md` | ✅ Complete |
| 19 | `19-content-detail-page.md` | ✅ Complete |
| 20 | `20-settings-page.md` | ✅ Complete |
| 21 | `21-internal-linking-page.md` | ✅ Complete |
| 25 | `25-notification-settings-page.md` | ✅ Complete |
| 28 | `28-yoast-seo-page.md` | ✅ Complete |
| 30 | `30-ai-provider-settings-page.md` | ✅ Complete |

---

## 📊 Current Metrics

| Metric | Count |
|--------|-------|
| Spec Files | 30 |
| Database Tables | 29 |
| Entity Classes | 34 |
| REST Endpoints | 110+ |
| Core Services | 13 |
| UI Pages | 7 |
| Error Codes | 81 (14000-14999) |
| Enums | 30 |

---

## 🔮 Future Enhancements (Planned)

### Priority 1: High Value
| Feature | Description | Spec Status |
|---------|-------------|-------------|
| AI Keyword Suggestions | Use AI to suggest anchor text matches | 📋 Planned |
| Link Velocity Reports | Track linking over time | 📋 Planned |

### Priority 2: Medium Value
| Feature | Description | Spec Status |
|---------|-------------|-------------|
| External Link Tracking | Track outbound links separately | 📋 Planned |
| Redirect Chain Detection | Identify multi-hop redirects | 📋 Planned |

### Priority 3: Nice to Have
| Feature | Description | Spec Status |
|---------|-------------|-------------|
| Competitor Link Analysis | Compare internal linking to competitors | 📋 Planned |
| A/B Testing for Anchors | Test different anchor text variations | 📋 Planned |
| GraphQL API | Alternative to REST API | 📋 Planned |

---

## 🏗️ Implementation Order

When implementing the plugin, follow this order:

### Phase 1: Foundation
1. `03-plugin-structure.md` - Set up file structure
2. `04-database-schema.md` - Create all 29 tables
3. `08-entity-models.md` - Create all 34 entity classes
4. `66-shared-constants.md` - Define all constants/enums

### Phase 2: Core Services
5. `10-link-parser.md` - Link extraction
6. `09-scan-service.md` - Content scanning
7. `11-elementor-integration.md` - Elementor support

### Phase 3: Modification & History
8. `14-modification-service.md` - Link changes
9. `12-history-service.md` - Version tracking
10. `13-snapshot-service.md` - Backup system

### Phase 4: Import & Background
11. `15-csv-import.md` - CSV import
12. `16-cron-system.md` - Background jobs

### Phase 5: Internal Linking
13. `22-internal-linking-service.md` - Auto-linking engine
14. `26-cron-auto-linking.md` - Background auto-linking

### Phase 6: Health & Notifications
15. `23-link-health-monitor.md` - Link validation
16. `24-notification-service.md` - Multi-channel alerts

### Phase 7: SEO Integration
17. `27-yoast-seo-integration.md` - Yoast SEO optimization

### Phase 8: AI Provider Integration
18. `29-ai-provider-settings.md` - AI provider service

### Phase 9: API & UI
19. `17-rest-api-endpoints.md` - REST API (110+ endpoints)
20. `18-overview-page.md` - Main dashboard
21. `19-content-detail-page.md` - Detail UI
22. `20-settings-page.md` - Settings UI
23. `21-internal-linking-page.md` - Internal linking UI
24. `25-notification-settings-page.md` - Notification settings UI
25. `28-yoast-seo-page.md` - Yoast SEO UI
26. `30-ai-provider-settings-page.md` - AI provider UI

---

## 🔧 Technical Decisions

### Database
- **SQLite** for portability (no MySQL dependency)
- **PascalCase** for column names
- **WAL mode** for concurrency
- **Per-content history DBs** for isolation
- **29 tables** across 9 categories

### Error Handling
- **14xxx error range** (14000-14999)
- **81 error codes** defined
- **Stack traces** in error logs
- **Function/file names** in all logs

### Logging Pattern
```php
Logger::info('Message', [
    'function' => __FUNCTION__,
    'file' => __FILE__,
    // context...
]);

Logger::error('Error', [
    'function' => __FUNCTION__,
    'file' => __FILE__,
    'error' => $e->getMessage(),
    'stack_trace' => $e->getTraceAsString()
]);
```

### Internal Linking
- **2-5 word phrases** for anchor text matching
- **Sequential/Random/Weighted** variable modes
- **Template placeholders**: `{{url}}`, `{{anchor_text}}`, `{{title}}`, custom variables
- **History backup** before every modification
- **AUTO_LINK_BATCH_SIZE**: 10 items
- **AUTO_LINK_CRON_INTERVAL**: 120 seconds

### Health Monitoring
- **HEALTH_CHECK_BATCH_SIZE**: 25 items
- **Priority-based scheduling** with exponential backoff
- **Multi-channel notifications**: Email, Webhooks, Admin Notices

### Yoast SEO Integration
- **Focus keyword optimization** with link injection
- **Meta description trimming** (word/char/sentence modes)
- **Audit logging** with revert capability

### AI Provider Settings
- **Seedable configuration** from config.json
- **6 default providers**: OpenAI, Gemini, Anthropic, Mistral, Groq, Ollama
- **Authentication types**: Bearer, OAuth 2.0 (Client/Code), Custom Headers
- **Customizable model names** per provider
- **Encrypted credential storage** with AES-256-GCM

---

## 📝 Training Notes for AI Models

### When Implementing PHP Code
1. Always use constants from `66-shared-constants.md`
2. Follow logging pattern with function/file names
3. Create history entry before any content modification
4. Use enums for all status/type values
5. Validate inputs using defined limits (MAX_URL_LENGTH, etc.)
6. Use entity classes from `08-entity-models.md`

### When Implementing JavaScript
1. Use REST API from `17-rest-api-endpoints.md`
2. Handle all error codes from 14xxx range
3. Show progress for batch operations
4. Implement preview before destructive actions

### When Adding New Features
1. Create spec file with standard template
2. Add to overview file index
3. Add error codes to 66-shared-constants.md
4. Add database tables to 04-database-schema.md
5. Add entity classes to 08-entity-models.md
6. Add REST endpoints to 17-rest-api-endpoints.md
7. Add UI page/tab if needed
8. Update 99-architecture-diagram.md
9. Update memory files

---

*Last updated: 2026-01-31*
