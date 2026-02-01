# 20 - Settings Page (Admin UI)

> **Status:** Complete  
> **Priority:** Medium  
> **Updated:** 2026-01-31

---

## Purpose

Configuration page for Link Manager plugin settings including scan options, auto-snapshot behavior, database management, and import/export capabilities.

---

## Page Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Link Manager Settings                                                  │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐            │
│  │   General  │ │   Scans    │ │  Database  │ │   Import   │            │
│  └────────────┘ └────────────┘ └────────────┘ └────────────┘            │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  GENERAL SETTINGS                                                       │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Auto Snapshot                                                          │
│  [✓] Create snapshot before first modification                          │
│      Automatically backs up database before any changes.                │
│                                                                         │
│  Snapshot Retention                                                     │
│  Keep last [50 ▼] snapshots                                             │
│      Older snapshots will be automatically deleted.                     │
│                                                                         │
│  First Modification Warning                                             │
│  [✓] Show "Take Snapshot" reminder on first edit                        │
│                                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  DISPLAY OPTIONS                                                        │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Default Items Per Page                                                 │
│  [20 ▼] items                                                           │
│                                                                         │
│  Default Tab                                                            │
│  [◉ Posts] [○ Pages] [○ Categories]                                     │
│                                                                         │
│                                                [Reset Defaults] [Save]  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Settings Tabs

### General Tab
- Auto-snapshot toggle
- Snapshot retention limit
- First modification warning
- Display preferences

### Scans Tab
```
┌─────────────────────────────────────────────────────────────────────────┐
│  SCAN SETTINGS                                                          │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Link Validation                                                        │
│  [✓] Check link status (HTTP response)                                  │
│  [✓] Follow redirects (detect 301/302)                                  │
│  [ ] Validate SSL certificates                                          │
│                                                                         │
│  Timeout: [10 ▼] seconds per link                                       │
│                                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  PARALLEL SCANNING                                                      │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Concurrent Requests: [5 ▼]                                             │
│  ⚠ Higher values may trigger rate limits on external sites.            │
│                                                                         │
│  Batch Size: [20 ▼] posts per batch                                     │
│                                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  SCAN SCOPE                                                             │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Include:                                                               │
│  [✓] Post content (post_content)                                        │
│  [✓] Elementor data (_elementor_data)                                   │
│  [✓] JSON-LD / Schema markup                                            │
│  [✓] Category descriptions                                              │
│                                                                         │
│  Post Types:                                                            │
│  [✓] post  [✓] page  [ ] product  [ ] custom_type                       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Database Tab
```
┌─────────────────────────────────────────────────────────────────────────┐
│  DATABASE MANAGEMENT                                                    │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Main Database                                                          │
│  Location: wp-content/uploads/link-manager/link-manager.db              │
│  Size: 2.4 MB                                                           │
│  Records: 294 posts, 45 pages, 12 categories, 1,847 links               │
│                                                                         │
│  [Optimize Database] [Export Database] [View in Detail]                 │
│                                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  HISTORY DATABASES                                                      │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Location: wp-content/uploads/link-manager/history-manage/              │
│  Posts: 47 history files (12.3 MB)                                      │
│  Pages: 8 history files (1.2 MB)                                        │
│  Categories: 3 history files (0.4 MB)                                   │
│                                                                         │
│  [Clean Orphaned Files] [Export All Histories]                          │
│                                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  SNAPSHOTS                                                              │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Location: wp-content/uploads/link-manager/snapshots/                   │
│  Count: 7 snapshots (45.2 MB total)                                     │
│                                                                         │
│  [Manage Snapshots] [Export All Snapshots]                              │
│                                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  DANGER ZONE                                                            │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  ⚠ These actions are irreversible!                                     │
│                                                                         │
│  [Reset Main Database]    Clears all scanned data                       │
│  [Delete All Histories]   Removes all history files                     │
│  [Factory Reset]          Removes all plugin data                       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Import Tab
```
┌─────────────────────────────────────────────────────────────────────────┐
│  IMPORT BROKEN LINKS                                                    │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Upload a CSV file with broken link data from external tools.           │
│                                                                         │
│  CSV File:                                                              │
│  [Choose File] broken-links-export.csv                                  │
│                                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  COLUMN MAPPING                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  Detected columns: url, source_page, status_code, anchor_text          │
│                                                                         │
│  Broken Link URL:    [url ▼]                                            │
│  Source Article:     [source_page ▼]                                    │
│  Status Code:        [status_code ▼] (optional)                         │
│                                                                         │
│  Match source by:    [◉ URL] [○ Slug] [○ Post ID]                       │
│                                                                         │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  PREVIEW                                                                │
│  ─────────────────────────────────────────────────────────────────────  │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │ Broken URL              │ Source Article      │ Status │ Match   │ │
│  ├─────────────────────────┼─────────────────────┼────────┼─────────┤ │
│  │ example.com/old-page    │ /getting-started    │  404   │ ✓ Found │ │
│  │ example.com/removed     │ /about-us           │  404   │ ✓ Found │ │
│  │ example.com/broken      │ /unknown-page       │  404   │ ✗ N/A   │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  Matched: 2 of 3 rows                                                   │
│                                                                         │
│                                   [Cancel] [Import Matched Links]       │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Settings Schema

```typescript
interface LinkManagerSettings {
  // General
  autoSnapshotEnabled: boolean;
  snapshotRetentionLimit: number;
  showFirstModificationWarning: boolean;
  defaultItemsPerPage: 20 | 30 | 50 | 100;
  defaultTab: 'posts' | 'pages' | 'categories';
  
  // Scans
  validateLinkStatus: boolean;
  followRedirects: boolean;
  validateSsl: boolean;
  requestTimeout: number;
  concurrentRequests: number;
  batchSize: number;
  
  // Scan scope
  scanPostContent: boolean;
  scanElementorData: boolean;
  scanJsonLd: boolean;
  scanCategoryDescriptions: boolean;
  enabledPostTypes: string[];
}

// Default values
const DEFAULT_SETTINGS: LinkManagerSettings = {
  autoSnapshotEnabled: true,
  snapshotRetentionLimit: 50,
  showFirstModificationWarning: true,
  defaultItemsPerPage: 20,
  defaultTab: 'posts',
  
  validateLinkStatus: true,
  followRedirects: true,
  validateSsl: false,
  requestTimeout: 10,
  concurrentRequests: 5,
  batchSize: 20,
  
  scanPostContent: true,
  scanElementorData: true,
  scanJsonLd: true,
  scanCategoryDescriptions: true,
  enabledPostTypes: ['post', 'page']
};
```

---

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `lm/v1/settings` | Get all settings |
| PUT | `lm/v1/settings` | Update settings |
| POST | `lm/v1/settings/reset` | Reset to defaults |
| GET | `lm/v1/database/stats` | Get database statistics |
| POST | `lm/v1/database/optimize` | Optimize SQLite |
| POST | `lm/v1/database/reset` | Reset main database |
| DELETE | `lm/v1/database/histories` | Delete all histories |
| POST | `lm/v1/import/preview` | Preview CSV import |
| POST | `lm/v1/import/execute` | Execute CSV import |

---

## Confirmation Dialogs

### Reset Database
```
┌─────────────────────────────────────────┐
│  ⚠ Reset Main Database               [×]│
├─────────────────────────────────────────┤
│                                         │
│  This will delete all scanned data:     │
│  • 294 posts                            │
│  • 45 pages                             │
│  • 12 categories                        │
│  • 1,847 links                          │
│                                         │
│  History files will NOT be deleted.     │
│                                         │
│  Type "RESET" to confirm:               │
│  [______________]                       │
│                                         │
│            [Cancel]  [Reset Database]   │
└─────────────────────────────────────────┘
```

### Factory Reset
```
┌─────────────────────────────────────────┐
│  ⚠ Factory Reset                     [×]│
├─────────────────────────────────────────┤
│                                         │
│  This will PERMANENTLY delete:          │
│  • All scanned data                     │
│  • All history files (58 files)         │
│  • All snapshots (7 snapshots)          │
│  • All plugin settings                  │
│                                         │
│  This action CANNOT be undone!          │
│                                         │
│  Type "DELETE EVERYTHING" to confirm:   │
│  [________________________]             │
│                                         │
│            [Cancel]  [Factory Reset]    │
└─────────────────────────────────────────┘
```

---

## Acceptance Criteria

**Done when:**
- [ ] All settings persist correctly
- [ ] Reset defaults works
- [ ] Database stats display accurate information
- [ ] Optimize database runs VACUUM on SQLite
- [ ] CSV import correctly maps columns
- [ ] Import preview shows match status
- [ ] Destructive actions require typed confirmation
- [ ] Post type selection updates scan scope
- [ ] Settings validation prevents invalid values

---

## Dependencies

- `04-database-schema.md` - Database structure
- `09-scan-service.md` - Scan configuration
- `13-snapshot-service.md` - Snapshot settings
- WordPress Options API
