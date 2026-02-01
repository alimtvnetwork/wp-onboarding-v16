# 19 - Content Detail Page (Admin UI)

> **Status:** Complete  
> **Priority:** High  
> **Updated:** 2026-01-31

---

## Purpose

Displays all links within a single content item (post/page/category) with detailed information about each link's context, status, and wrapper tags. Provides tools for individual and bulk link modifications.

---

## Page Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Back to Posts    "Getting Started Guide"    [View Post] [Edit Post] │
├─────────────────────────────────────────────────────────────────────────┤
│  Slug: getting-started-guide                                            │
│  Meta: Learn how to get started with our platform in 5 minutes...       │
│  Last Scanned: 2 hours ago    Histories: 3    [Rescan] [Snapshot]       │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐│
│  │ All (12)    │ │ Working (10)│ │ Broken (2)  │ │ JSON-LD (3)         ││
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────────────┘│
├─────────────────────────────────────────────────────────────────────────┤
│  Bulk: [Remove Title Attrs ▼]  Filter: [All Wrappers ▼] [1-word ▼]      │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ □ │ Anchor Text    │ URL              │ Words │ Wrapper │ Actions  ││
│  ├───┼────────────────┼──────────────────┼───────┼─────────┼──────────┤│
│  │ □ │ click here     │ example.com/page │   2   │ strong  │ [⋮]      ││
│  │   │ title="Visit"  │ ✗ 404            │       │         │          ││
│  ├───┼────────────────┼──────────────────┼───────┼─────────┼──────────┤│
│  │ □ │ Learn more     │ docs.example.com │   2   │ H2 > em │ [⋮]      ││
│  │   │ title="Docs"   │ ✓ 200            │       │         │          ││
│  ├───┼────────────────┼──────────────────┼───────┼─────────┼──────────┤│
│  │ □ │ our privacy... │ /privacy-policy  │   3   │ —       │ [⋮]      ││
│  │   │ (no title)     │ ✓ 200            │       │         │          ││
│  └─────────────────────────────────────────────────────────────────────┘│
├─────────────────────────────────────────────────────────────────────────┤
│  History: 3 modifications    [View History] [Restore Original]          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Component Structure

```typescript
// Component hierarchy
ContentDetailPage
├── Breadcrumb
├── ContentHeader
│   ├── Title
│   ├── ViewPostButton
│   └── EditPostButton
├── ContentMeta
│   ├── SlugDisplay
│   ├── MetaDescription
│   └── StatsBar
│       ├── LastScanned
│       ├── HistoryCount
│       ├── RescanButton
│       └── SnapshotButton
├── LinkTabs
│   ├── AllLinksTab
│   ├── WorkingLinksTab
│   ├── BrokenLinksTab
│   └── JsonLdLinksTab
├── LinkFilterBar
│   ├── BulkActionSelect
│   ├── WrapperFilter
│   └── WordCountFilter
├── LinksTable
│   └── LinkRow
│       ├── Checkbox
│       ├── AnchorTextCell
│       │   └── TitleAttribute (subtitle)
│       ├── UrlCell
│       │   └── StatusIndicator
│       ├── WordCount
│       ├── WrapperStack
│       └── ActionsMenu
│           ├── EditLink
│           ├── RemoveLink
│           ├── RemoveWrapper
│           └── RemoveTitle
└── HistoryBar
    ├── HistoryCount
    ├── ViewHistoryButton
    └── RestoreOriginalButton
```

---

## State Management

```typescript
interface ContentDetailState {
  // Content info
  content: ContentDetail | null;
  isLoading: boolean;
  
  // Links
  links: LinkDetail[];
  activeTab: 'all' | 'working' | 'broken' | 'jsonld';
  
  // Filters
  wrapperFilter: LinkWrapperType | 'all';
  wordCountFilter: 'all' | '1' | '2' | '3+';
  
  // Selection
  selectedLinkIds: string[];
  
  // Modals
  editModalOpen: boolean;
  editingLink: LinkDetail | null;
  historyModalOpen: boolean;
  confirmModalOpen: boolean;
  confirmAction: PendingAction | null;
}

interface ContentDetail {
  id: number;
  type: 'post' | 'page' | 'category';
  title: string;
  slug: string;
  url: string;
  wpEditUrl: string;
  metaDescription: string | null;
  lastScanned: string | null;
  historyCount: number;
  linkStats: {
    total: number;
    working: number;
    broken: number;
    jsonLd: number;
  };
}

interface LinkDetail {
  id: string;
  url: string;
  anchorText: string;
  wordCount: number;
  titleAttribute: string | null;
  status: 'working' | 'broken' | 'unknown';
  statusCode: number | null;
  wrapperStack: LinkWrapperType[];
  sourceType: 'html' | 'json_ld';
  jsonLdPath: string | null;
  elementorElementId: string | null;
  position: { start: number; end: number };
  outerHtml: string;
}

type LinkWrapperType = 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'strong' | 'em';
```

---

## Link Row Visualization

### Wrapper Stack Display

Shows hierarchical wrapper tags as a path:

```
H2 > strong        (link inside <h2><strong>...</strong></h2>)
em                 (link inside <em>...</em>)
H3 > em            (link inside <h3><em>...</em></h3>)
—                  (no wrapper, direct link)
```

### Status Indicators

```
✓ 200    Green checkmark, working
✗ 404    Red X, not found
✗ 500    Red X, server error
⚠ 301    Yellow warning, redirect
? —      Gray question, not checked
```

---

## Actions Menu

Each link row has a dropdown menu with context-aware actions:

```
┌──────────────────────────┐
│ Edit Link URL            │
│ Edit Title Attribute     │
│ ─────────────────────── │
│ Remove Link (keep text)  │
│ Remove Link (keep href)  │
│ ─────────────────────── │
│ Remove <strong> wrapper  │  ← Only if wrapper exists
│ Remove <h2> wrapper      │  ← Only if wrapper exists
│ ─────────────────────── │
│ Remove Title Attribute   │  ← Only if title exists
│ ─────────────────────── │
│ Copy URL                 │
│ Open in New Tab          │
└──────────────────────────┘
```

---

## Edit Link Modal

```
┌─────────────────────────────────────────┐
│  Edit Link                           [×]│
├─────────────────────────────────────────┤
│                                         │
│  Anchor Text:                           │
│  "click here"                (readonly) │
│                                         │
│  URL:                                   │
│  [https://example.com/page___________]  │
│                                         │
│  Title Attribute:                       │
│  [Visit our example page______________] │
│  [ ] Remove title attribute             │
│                                         │
│  Current Status: ✗ 404 Not Found        │
│  [Check URL] (validates URL live)       │
│                                         │
│  Preview:                               │
│  <a href="..." title="...">click here</a>│
│                                         │
│            [Cancel]  [Save Changes]     │
└─────────────────────────────────────────┘
```

---

## Bulk Actions

| Action | Description | Confirmation |
|--------|-------------|--------------|
| Remove All Title Attributes | Strips title attr from selected links | Yes |
| Remove All Wrappers | Removes wrapper tags (H1-H6, strong, em) | Yes |
| Remove Links (keep text) | Unwraps anchor tags | Yes |
| Change URL | Replace URL for all selected | Yes + input |
| Export Selected | Download CSV of selected links | No |

---

## Bulk Title Attribute Replacement

Special modal for CSV-based title replacement:

```
┌─────────────────────────────────────────┐
│  Bulk Update Title Attributes        [×]│
├─────────────────────────────────────────┤
│                                         │
│  Upload CSV:                            │
│  [Choose File] keywords.csv             │
│                                         │
│  CSV Format:                            │
│  keyword,title_attribute                │
│  "click here","Visit our main page"    │
│  "learn more","Read documentation"      │
│                                         │
│  ─── OR ───                             │
│                                         │
│  Random from list:                      │
│  [Upload TXT file with one title/line]  │
│                                         │
│  Match by: [◉ Anchor Text] [○ URL]      │
│                                         │
│  Preview: 5 links will be updated       │
│                                         │
│            [Cancel]  [Apply Changes]    │
└─────────────────────────────────────────┘
```

---

## History Panel (Slide-out)

```
┌─────────────────────────────────────────┐
│  Modification History                [×]│
├─────────────────────────────────────────┤
│                                         │
│  Version 3 • Today 2:30 PM              │
│  ├─ Removed title from 2 links          │
│  └─ [View] [Restore to this version]    │
│                                         │
│  Version 2 • Yesterday 4:15 PM          │
│  ├─ Changed URL: example.com → docs.com │
│  ├─ Removed <strong> wrapper            │
│  └─ [View] [Restore to this version]    │
│                                         │
│  Version 1 (Original) • Jan 15, 2026    │
│  ├─ Initial scan                        │
│  └─ [View] [Restore to this version]    │
│                                         │
│  ────────────────────────────────────   │
│  [Restore Original] [Compare Versions]  │
└─────────────────────────────────────────┘
```

---

## JSON-LD Links View

Special display for schema markup links:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  JSON-LD Links (Schema Markup)                                          │
├─────────────────────────────────────────────────────────────────────────┤
│  Path                          │ URL                     │ Actions     │
├────────────────────────────────┼─────────────────────────┼─────────────┤
│  @graph[0].mainEntityOfPage    │ https://example.com/... │ [Edit] [×]  │
│  @graph[0].author.url          │ https://example.com/... │ [Edit] [×]  │
│  @graph[0].image.url           │ https://cdn.example...  │ [Edit] [×]  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Acceptance Criteria

**Done when:**
- [ ] All links displayed with correct context
- [ ] Tab counts match actual link counts
- [ ] Wrapper stack shows correct hierarchy
- [ ] Actions menu shows context-appropriate options
- [ ] Edit modal validates URL and shows live status
- [ ] Bulk actions apply to all selected links
- [ ] CSV import correctly matches and updates titles
- [ ] History panel shows all modifications
- [ ] Restore from any history version works
- [ ] JSON-LD links editable with path context

---

## Dependencies

- `10-link-parser.md` - Link context extraction
- `12-history-service.md` - History data
- `14-modification-service.md` - Link modifications
