# Link Manager - Service Architecture Diagram

> **Version:** 2.0.0  
> **Updated:** 2026-01-31  
> **Purpose:** Visual reference for service interactions

---

## 📊 Architecture Summary

| Category | Count |
|----------|-------|
| **Spec Files** | 28 |
| **Database Tables** | 25 |
| **Entity Classes** | 30 |
| **REST Endpoints** | 90+ |
| **Core Services** | 12 |
| **UI Pages** | 6 |

---

## 🏗️ Complete Service Architecture

```mermaid
graph TB
    subgraph "WordPress Admin UI"
        UI_Overview[Overview Page<br/>18-overview-page.md]
        UI_Detail[Content Detail Page<br/>19-content-detail-page.md]
        UI_Settings[Settings Page<br/>20-settings-page.md]
        UI_Internal[Internal Linking Page<br/>21-internal-linking-page.md]
        UI_Notify[Notification Settings Page<br/>25-notification-settings-page.md]
        UI_Yoast[Yoast SEO Page<br/>28-yoast-seo-page.md]
    end

    subgraph "REST API Layer"
        API[REST API Controller<br/>lm/v1<br/>17-rest-api-endpoints.md]
    end

    subgraph "Core Services"
        ScanSvc[ScanService<br/>09-scan-service.md]
        ModSvc[ModificationService<br/>14-modification-service.md]
        HistSvc[HistoryService<br/>12-history-service.md]
        SnapSvc[SnapshotService<br/>13-snapshot-service.md]
        IntLinkSvc[InternalLinkingService<br/>22-internal-linking-service.md]
    end

    subgraph "Parser & Integration"
        LinkParser[LinkParser<br/>10-link-parser.md]
        ElementorSvc[ElementorService<br/>11-elementor-integration.md]
        CSVImport[CSVImportService<br/>15-csv-import.md]
    end

    subgraph "Health & Monitoring"
        HealthSvc[HealthMonitorService<br/>23-link-health-monitor.md]
        NotifySvc[NotificationService<br/>24-notification-service.md]
    end

    subgraph "SEO Integration"
        YoastSvc[YoastSeoService<br/>27-yoast-seo-integration.md]
    end

    subgraph "Background Processing"
        CronSvc[CronService<br/>16-cron-system.md]
    end

    subgraph "Database Layer"
        MainDB[(Main DB<br/>link-manager.db<br/>25 tables)]
        HistDB[(History DBs<br/>per-content)]
        SnapDB[(Snapshot DBs<br/>point-in-time)]
    end

    subgraph "External"
        WP[WordPress<br/>Posts/Pages/Categories]
        HTTP[External URLs<br/>Link Validation]
        Yoast[Yoast SEO Plugin]
        Email[Email/Webhooks]
    end

    %% UI to API
    UI_Overview --> API
    UI_Detail --> API
    UI_Settings --> API
    UI_Internal --> API
    UI_Notify --> API
    UI_Yoast --> API

    %% API to Services
    API --> ScanSvc
    API --> ModSvc
    API --> HistSvc
    API --> SnapSvc
    API --> IntLinkSvc
    API --> CSVImport
    API --> HealthSvc
    API --> NotifySvc
    API --> YoastSvc

    %% Service Interactions
    ScanSvc --> LinkParser
    ScanSvc --> ElementorSvc
    ScanSvc --> HTTP
    
    ModSvc --> HistSvc
    ModSvc --> ElementorSvc
    
    IntLinkSvc --> ModSvc
    IntLinkSvc --> HistSvc
    IntLinkSvc --> LinkParser

    CSVImport --> ScanSvc
    
    HealthSvc --> HTTP
    HealthSvc --> NotifySvc
    
    NotifySvc --> Email

    YoastSvc --> Yoast
    YoastSvc --> HistSvc

    CronSvc --> ScanSvc
    CronSvc --> IntLinkSvc
    CronSvc --> HealthSvc
    CronSvc --> NotifySvc

    %% Database Access
    ScanSvc --> MainDB
    ModSvc --> MainDB
    HistSvc --> HistDB
    SnapSvc --> SnapDB
    SnapSvc --> MainDB
    IntLinkSvc --> MainDB
    HealthSvc --> MainDB
    NotifySvc --> MainDB
    YoastSvc --> MainDB

    %% WordPress Access
    LinkParser --> WP
    ElementorSvc --> WP
    ModSvc --> WP
    YoastSvc --> WP
```

---

## 📊 Service Dependency Matrix

| Service | Depends On | Used By |
|---------|------------|---------|
| **ScanService** | LinkParser, ElementorService | API, CronService, CSVImport |
| **LinkParser** | WordPress | ScanService, InternalLinkingService |
| **ElementorService** | WordPress | ScanService, ModificationService |
| **ModificationService** | HistoryService, ElementorService | API, InternalLinkingService |
| **HistoryService** | WordPress | ModificationService, InternalLinkingService, YoastSeoService |
| **SnapshotService** | MainDB | API |
| **InternalLinkingService** | ModificationService, HistoryService, LinkParser | API, CronService |
| **CSVImportService** | ScanService | API |
| **CronService** | ScanService, InternalLinkingService, HealthMonitor, NotificationService | WP-Cron |
| **HealthMonitorService** | HTTP, NotificationService | API, CronService |
| **NotificationService** | Email, Webhooks | API, CronService, HealthMonitor |
| **YoastSeoService** | Yoast SEO Plugin, HistoryService | API |

---

## 🔄 Data Flow Diagrams

### Scan Flow

```mermaid
sequenceDiagram
    participant UI as Admin UI
    participant API as REST API
    participant Scan as ScanService
    participant Parser as LinkParser
    participant Elem as ElementorService
    participant DB as Database
    participant WP as WordPress

    UI->>API: POST /scan/start
    API->>Scan: startScan()
    Scan->>WP: getContent()
    WP-->>Scan: posts/pages
    
    loop Each Content
        Scan->>Parser: parseLinks(content)
        alt Is Elementor
            Scan->>Elem: parseElementorData()
            Elem-->>Scan: links
        end
        Parser-->>Scan: links
        Scan->>DB: saveLinks()
    end
    
    Scan-->>API: scanResult
    API-->>UI: progress/complete
```

### Internal Linking Flow

```mermaid
sequenceDiagram
    participant UI as Admin UI
    participant API as REST API
    participant IL as InternalLinkingService
    participant Hist as HistoryService
    participant Mod as ModificationService
    participant DB as Database
    participant WP as WordPress

    UI->>API: POST /internal-linking/generate
    API->>IL: generateLinks()
    IL->>DB: getTargets()
    IL->>WP: getContent()
    
    IL->>Hist: createVersion(before)
    Hist->>DB: saveVersion()
    
    loop Each Target
        IL->>IL: findMatchingPhrase()
        IL->>IL: buildLinkHtml(template)
        IL->>IL: insertLink()
    end
    
    IL->>WP: updateContent()
    IL->>Hist: completeVersion(after)
    IL->>DB: recordInternalLink()
    
    IL-->>API: linkingResult
    API-->>UI: success + links
```

### Health Monitor Flow

```mermaid
sequenceDiagram
    participant Cron as WP-Cron
    participant Health as HealthMonitorService
    participant HTTP as HTTP Client
    participant DB as Database
    participant Notify as NotificationService
    participant Email as Email/Webhook

    Cron->>Health: runScheduledCheck()
    Health->>DB: getNextBatch(25)
    
    loop Each Link (Parallel)
        Health->>HTTP: checkUrl()
        HTTP-->>Health: status/timing
        Health->>DB: saveHealthCheck()
        
        alt Link Broken/Slow
            Health->>DB: createAlert()
            Health->>Notify: queueNotification()
        end
    end
    
    Notify->>DB: processBatch(50)
    Notify->>Email: sendNotifications()
    
    Health-->>Cron: complete
```

### Yoast SEO Optimization Flow

```mermaid
sequenceDiagram
    participant UI as Admin UI
    participant API as REST API
    participant Yoast as YoastSeoService
    participant Plugin as Yoast SEO Plugin
    participant Hist as HistoryService
    participant DB as Database
    participant WP as WordPress

    UI->>API: POST /yoast/content/{id}/optimize
    API->>Yoast: optimizeContent()
    
    Yoast->>Plugin: getSeoData()
    Plugin-->>Yoast: focus_keyword, meta
    
    Yoast->>WP: getContent()
    Yoast->>Hist: createVersion(before)
    
    alt Missing Keyword
        Yoast->>Yoast: injectKeywordLinks()
    end
    alt Oversized Meta
        Yoast->>Yoast: trimMetaDescription()
    end
    
    Yoast->>Plugin: updateSeoData()
    Yoast->>Hist: completeVersion(after)
    Yoast->>DB: logAuditEntry()
    
    Yoast-->>API: optimizationResult
    API-->>UI: success + changes
```

### Modification with History Flow

```mermaid
sequenceDiagram
    participant UI as Admin UI
    participant API as REST API
    participant Mod as ModificationService
    participant Hist as HistoryService
    participant Elem as ElementorService
    participant WP as WordPress

    UI->>API: PUT /links/{id}
    API->>Mod: modifyLink()
    
    Mod->>WP: getContent()
    Mod->>Hist: createVersion(before)
    
    alt Is Elementor Content
        Mod->>Elem: updateElementorData()
    else Standard Content
        Mod->>Mod: applyModification()
    end
    
    Mod->>WP: saveContent()
    Mod->>Hist: completeVersion(after)
    
    Mod-->>API: modificationResult
    API-->>UI: success + history_id
```

---

## 🗄️ Database Relationship Diagram

```mermaid
erDiagram
    Post ||--o{ Link : contains
    Page ||--o{ Link : contains
    Category ||--o{ Link : contains
    
    Post ||--o| HistoryDB : has_history
    Page ||--o| HistoryDB : has_history
    Category ||--o| HistoryDB : has_history
    
    ScanHistory ||--o{ Link : scanned_in
    
    LinkTarget ||--o{ InternalLink : target_of
    LinkTemplate ||--o{ InternalLink : uses
    
    Link ||--o{ LinkHealthCheck : monitored_by
    LinkHealthCheck ||--o{ HealthAlert : triggers
    
    YoastSettings ||--|| Plugin : configures
    YoastOptimizationQueue ||--o{ YoastAuditLog : generates
    
    NotificationQueue ||--o{ NotificationLog : logged_in
    NotificationRecipients ||--o{ NotificationQueue : receives
    WebhookEndpoints ||--o{ NotificationQueue : delivers_to
    
    Post {
        int Id PK
        int WpPostId UK
        string Title
        int TotalLinks
        int BrokenLinks
        bool HasHistory
    }
    
    Link {
        int Id PK
        string ContentType
        int ContentId FK
        string Url
        string Status
        string WrapperTags
    }
    
    LinkTarget {
        int Id PK
        string Url UK
        string Title
        string Category
        int Priority
    }
    
    LinkTemplate {
        int Id PK
        string Name UK
        string Template
        bool IsDefault
    }
    
    InternalLink {
        int Id PK
        string ContentType
        int WpContentId
        string TargetUrl
        string AnchorText
        int TemplateId FK
    }
    
    LinkHealthCheck {
        int Id PK
        int LinkId FK
        int HttpStatus
        int ResponseTimeMs
        datetime CheckedAt
    }
    
    HealthAlert {
        int Id PK
        int HealthCheckId FK
        string AlertType
        string Severity
        bool Acknowledged
    }
    
    YoastSettings {
        int Id PK
        string SettingKey UK
        string Value
        string ValueType
    }
    
    YoastAuditLog {
        int Id PK
        int ContentId
        string ChangeType
        string OldValue
        string NewValue
        bool Reverted
    }
    
    YoastOptimizationQueue {
        int Id PK
        int ContentId
        string ContentType
        string Status
        int Priority
    }
    
    NotificationQueue {
        int Id PK
        string Channel
        string Status
        int RetryCount
    }
    
    HistoryDB {
        ContentVersion versions
        ModificationLog logs
    }
```

---

## 📋 Service Categories

### Core Link Management (5 services)
| Service | Spec | Tables | Endpoints |
|---------|------|--------|-----------|
| ScanService | 09 | 3 | 8 |
| LinkParser | 10 | - | - |
| ModificationService | 14 | 1 | 6 |
| HistoryService | 12 | 2 | 5 |
| SnapshotService | 13 | 1 | 4 |

### Content Integration (3 services)
| Service | Spec | Tables | Endpoints |
|---------|------|--------|-----------|
| ElementorService | 11 | - | - |
| CSVImportService | 15 | 1 | 3 |
| InternalLinkingService | 22 | 5 | 12 |

### Monitoring & Notifications (2 services)
| Service | Spec | Tables | Endpoints |
|---------|------|--------|-----------|
| HealthMonitorService | 23 | 4 | 25+ |
| NotificationService | 24 | 5 | 20+ |

### SEO Integration (1 service)
| Service | Spec | Tables | Endpoints |
|---------|------|--------|-----------|
| YoastSeoService | 27 | 3 | 16 |

### Background Processing (1 service)
| Service | Spec | Description |
|---------|------|-------------|
| CronService | 16 | WP-Cron scheduler for all async operations |

---

## 📝 Cross-References

- All service specs: `01-admin-backend/split-spec/`
- All UI specs: `02-admin-ui/split-spec/`
- Constants: `66-shared-constants.md`
- Entity models: `08-entity-models.md` (30 classes)
- Database schema: `04-database-schema.md` (25 tables)
- Memory files: `.lovable/memories/wp-plugins/`

---

*This diagram is the visual companion to the textual architecture in 00-overview.md*
