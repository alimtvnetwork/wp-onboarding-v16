# 18 - Overview Page (Admin UI)

> **Status:** Complete  
> **Priority:** High  
> **Updated:** 2026-01-31

---

## Purpose

The main dashboard view showing all scanned content (posts, pages, categories) with link statistics. Provides filtering, pagination, search, and quick actions for navigating to detailed link views.

---

## Page Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Link Manager                                              [Snapshot ▼] │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────────────────────┐│
│  │   Posts  │ │  Pages   │ │Categories│ │ [Scan All] [Scan Broken] [⚙]││
│  └──────────┘ └──────────┘ └──────────┘ └──────────────────────────────┘│
├─────────────────────────────────────────────────────────────────────────┤
│  Search: [________________] [By Title ▼]     Show: [20 ▼] per page      │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ □ │ Title              │ Slug       │ Links │ Broken │ History │ → ││
│  ├───┼────────────────────┼────────────┼───────┼────────┼─────────┼───┤│
│  │ □ │ Getting Started    │ getting-s… │  12   │   2    │   3     │ → ││
│  │ □ │ About Our Company  │ about      │   8   │   0    │   1     │ → ││
│  │ □ │ Contact Us         │ contact    │   4   │   1    │   0     │ → ││
│  │ □ │ Privacy Policy     │ privacy    │   6   │   0    │   2     │ → ││
│  └─────────────────────────────────────────────────────────────────────┘│
├─────────────────────────────────────────────────────────────────────────┤
│  ◀ 1 2 3 ... 15 ▶                            Showing 1-20 of 294 posts  │
├─────────────────────────────────────────────────────────────────────────┤
│  Bulk Actions: [Select Action ▼] [Apply]     Selected: 0 items          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Component Structure

```typescript
// Component hierarchy
OverviewPage
├── PageHeader
│   ├── Title
│   └── SnapshotButton
├── TabNavigation
│   ├── PostsTab
│   ├── PagesTab
│   └── CategoriesTab
├── ActionBar
│   ├── ScanAllButton
│   ├── ScanBrokenButton
│   └── SettingsButton
├── FilterBar
│   ├── SearchInput
│   ├── SearchTypeSelect (title/slug)
│   └── PerPageSelect
├── ContentTable
│   ├── TableHeader (sortable columns)
│   └── TableRows
│       └── ContentRow
│           ├── Checkbox
│           ├── TitleCell (with edit link)
│           ├── SlugCell (with external link)
│           ├── LinksCount (clickable)
│           ├── BrokenCount (clickable, red if > 0)
│           ├── HistoryCount
│           └── ViewDetailsButton
├── Pagination
│   ├── PageNumbers
│   └── ItemCount
└── BulkActionsBar
    ├── ActionSelect
    └── ApplyButton
```

---

## State Management

```typescript
interface OverviewPageState {
  // Active tab
  activeTab: 'posts' | 'pages' | 'categories';
  
  // Table data
  items: ContentItem[];
  totalItems: number;
  isLoading: boolean;
  
  // Pagination
  currentPage: number;
  perPage: 20 | 30 | 50 | 100;
  
  // Filters
  searchQuery: string;
  searchType: 'title' | 'slug';
  sortColumn: 'title' | 'links' | 'broken' | 'updated';
  sortDirection: 'asc' | 'desc';
  
  // Selection
  selectedIds: number[];
  selectAll: boolean;
  
  // Modals
  snapshotModalOpen: boolean;
  scanProgressOpen: boolean;
  scanProgress: ScanProgress | null;
}

interface ContentItem {
  id: number;
  title: string;
  slug: string;
  url: string;
  metaDescription: string | null;
  totalLinks: number;
  brokenLinks: number;
  workingLinks: number;
  historyCount: number;
  lastScanned: string | null;
  lastModified: string | null;
}

interface ScanProgress {
  type: 'all' | 'broken';
  total: number;
  completed: number;
  current: string; // Current item being scanned
  errors: string[];
}
```

---

## API Integration

```typescript
// Fetch content list
async function fetchContent(
  type: 'posts' | 'pages' | 'categories',
  params: {
    page: number;
    perPage: number;
    search?: string;
    searchType?: 'title' | 'slug';
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
  }
): Promise<{ items: ContentItem[]; total: number }> {
  const response = await fetch(
    `${apiBase}/lm/v1/${type}?` + new URLSearchParams({
      page: String(params.page),
      per_page: String(params.perPage),
      search: params.search || '',
      search_type: params.searchType || 'title',
      sort_by: params.sortBy || 'title',
      sort_dir: params.sortDir || 'asc'
    })
  );
  return response.json();
}

// Trigger scan
async function startScan(
  type: 'all' | 'broken',
  contentType?: 'posts' | 'pages' | 'categories'
): Promise<{ jobId: string }> {
  const response = await fetch(`${apiBase}/lm/v1/scan`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
      scanType: type,
      contentType 
    })
  });
  return response.json();
}

// Poll scan progress
async function getScanProgress(jobId: string): Promise<ScanProgress> {
  const response = await fetch(`${apiBase}/lm/v1/scan/${jobId}/progress`);
  return response.json();
}
```

---

## Table Row Actions

Each row provides quick actions:

| Action | Trigger | Navigation |
|--------|---------|------------|
| View Details | Click row or → button | `/link-manager/content/{type}/{id}` |
| View Links | Click link count | `/link-manager/content/{type}/{id}#links` |
| View Broken | Click broken count | `/link-manager/content/{type}/{id}#broken` |
| Edit in WP | Click title edit icon | WordPress edit URL |
| View Frontend | Click slug external icon | Frontend URL |

---

## Bulk Actions

Available bulk actions when items are selected:

| Action | Description |
|--------|-------------|
| Rescan Selected | Re-scan links for selected items |
| Export Links CSV | Export all links from selected items |
| Remove All Title Attrs | Remove title attributes from all links |
| View Combined History | Show combined history for selected |

---

## Snapshot Modal

```
┌─────────────────────────────────────────┐
│  Create Snapshot                     [×]│
├─────────────────────────────────────────┤
│                                         │
│  Snapshot Name:                         │
│  [pre-cleanup_________________]         │
│                                         │
│  Include:                               │
│  [✓] Posts (294 items)                  │
│  [✓] Pages (45 items)                   │
│  [✓] Categories (12 items)              │
│                                         │
│  [ ] Include history databases          │
│                                         │
│  ────────────────────────────────────   │
│  Preview: 003-pre-cleanup-2026-01-31.db │
│                                         │
│            [Cancel]  [Create Snapshot]  │
└─────────────────────────────────────────┘
```

---

## Scan Progress Modal

```
┌─────────────────────────────────────────┐
│  Scanning Links...                   [×]│
├─────────────────────────────────────────┤
│                                         │
│  ████████████░░░░░░░░░  45%             │
│                                         │
│  Scanning: "Getting Started Guide"      │
│                                         │
│  Completed: 132 of 294                  │
│  Links found: 1,247                     │
│  Broken: 23                             │
│                                         │
│  ⚠ 2 errors occurred (view log)        │
│                                         │
│                           [Run in BG]   │
└─────────────────────────────────────────┘
```

---

## Responsive Behavior

| Breakpoint | Behavior |
|------------|----------|
| Desktop (>1200px) | Full table with all columns |
| Tablet (768-1200px) | Hide meta description, compact counts |
| Mobile (<768px) | Card layout, stacked info |

---

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `j` / `k` | Move selection up/down |
| `x` | Toggle checkbox |
| `Enter` | Open selected item |
| `s` | Focus search |
| `/` | Focus search (alternative) |
| `Ctrl+A` | Select all visible |
| `Esc` | Clear selection / close modal |

---

## Acceptance Criteria

**Done when:**
- [ ] Tabs switch between posts/pages/categories
- [ ] Search filters by title or slug
- [ ] Pagination works with configurable page size
- [ ] Sorting by column headers
- [ ] Bulk selection with shift+click range select
- [ ] Snapshot modal creates named snapshots
- [ ] Scan progress shows real-time updates
- [ ] Broken link count displays in red when > 0
- [ ] External links open in new tab
- [ ] Keyboard navigation works

---

## Dependencies

- `09-scan-service.md` - Scan API
- `13-snapshot-service.md` - Snapshot API
- WordPress admin styles (wp-admin CSS classes)
