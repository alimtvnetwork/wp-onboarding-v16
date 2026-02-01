# 21 - Internal Linking Page

> **Phase:** UI Implementation  
> **Dependencies:** `01-admin-backend/split-spec/21-internal-linking-service.md`  
> **Estimated Time:** 6-8 hours  
> **Last Updated:** 2026-01-31

---

## 📋 Scope

Create the WordPress admin UI for the Internal Linking feature, providing tabs for link target management, template configuration, variable setup, and bulk linking operations.

---

## 🎯 Purpose

The Internal Linking page provides:
- **Link Targets Tab**: Import/manage URLs for auto-linking
- **Templates Tab**: Configure HTML link templates with variable placeholders
- **Variables Tab**: Manage dynamic variables from CSV/JSON files
- **Auto-Link Tab**: Run bulk internal linking on orphan content
- **Reports Tab**: View internal linking statistics and orphan content

---

## 🔗 Navigation

```
Link Manager (Menu)
├── Overview           → 18-overview-page.md
├── Settings           → 20-settings-page.md
└── Internal Linking   → THIS PAGE (21-internal-linking-page.md)
    ├── Link Targets Tab
    ├── Templates Tab
    ├── Variables Tab
    ├── Auto-Link Tab
    └── Reports Tab
```

**Menu Registration:**
```php
add_submenu_page(
    'link-manager',                    // Parent slug
    'Internal Linking',                // Page title
    'Internal Linking',                // Menu title
    'manage_options',                  // Capability
    'link-manager-internal-linking',   // Menu slug
    [$this, 'renderInternalLinkingPage']
);
```

---

## 📐 Page Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Link Manager > Internal Linking                                         │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌──────────────┬────────────┬────────────┬────────────┬──────────────┐ │
│  │ Link Targets │  Templates │  Variables │  Auto-Link │   Reports    │ │
│  └──────────────┴────────────┴────────────┴────────────┴──────────────┘ │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  [Tab Content Area - changes based on selected tab]                      │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 📑 Tab 1: Link Targets

### Purpose
Manage the pool of URLs available for internal linking, imported from CSV/JSON or added manually.

### Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Link Targets                                                            │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ Import Targets                                                       ││
│  ├─────────────────────────────────────────────────────────────────────┤│
│  │ [Choose File] carpet-links.csv           [Import CSV] [Import JSON] ││
│  │                                                                      ││
│  │ ○ Auto-detect columns  ○ Manual mapping                             ││
│  │                                                                      ││
│  │ URL Column:   [url           ▼]                                     ││
│  │ Title Column: [title         ▼]                                     ││
│  │ Category:     [category      ▼] (optional)                          ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ + Add Target Manually                                                ││
│  ├─────────────────────────────────────────────────────────────────────┤│
│  │ URL:      [https://example.com/carpet-cleaning                    ] ││
│  │ Title:    [Professional Carpet Cleaning Services                  ] ││
│  │ Category: [cleaning            ▼]  Priority: [5    ]  [Add Target] ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  Targets (127 total)                        [Search...        ] 🔍      │
│  ┌───────┬────────────────────────────────┬──────────────────┬────────┐│
│  │ ID    │ URL                            │ Title            │Actions ││
│  ├───────┼────────────────────────────────┼──────────────────┼────────┤│
│  │ 1     │ /carpet-cleaning-guide         │ Carpet Cleaning  │ ✏️ 🗑️  ││
│  │ 2     │ /steam-cleaning-101            │ Steam Cleaning   │ ✏️ 🗑️  ││
│  │ 3     │ /stain-removal-tips            │ Stain Removal    │ ✏️ 🗑️  ││
│  └───────┴────────────────────────────────┴──────────────────┴────────┘│
│                                                                          │
│  [◀ Prev] Page 1 of 7 [Next ▶]              Show: [20 ▼] per page       │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Actions

| Action | Endpoint | Description |
|--------|----------|-------------|
| Import CSV | `POST /lm/v1/internal-linking/import/csv` | Upload and import CSV file |
| Import JSON | `POST /lm/v1/internal-linking/import/json` | Upload and import JSON file |
| Add Target | `POST /lm/v1/internal-linking/targets` | Add single target manually |
| Edit Target | `PUT /lm/v1/internal-linking/targets/{id}` | Update existing target |
| Delete Target | `DELETE /lm/v1/internal-linking/targets/{id}` | Remove target |
| List Targets | `GET /lm/v1/internal-linking/targets` | Paginated list with search |

---

## 📑 Tab 2: Templates

### Purpose
Create and manage HTML templates for link generation with variable placeholders.

### Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Link Templates                                                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ + Create New Template                                                ││
│  ├─────────────────────────────────────────────────────────────────────┤│
│  │ Name: [Bold Heading Link                                          ] ││
│  │                                                                      ││
│  │ Template HTML:                                                       ││
│  │ ┌───────────────────────────────────────────────────────────────────┐│
│  │ │ <{{heading_tag}}><strong><a href="{{url}}"                        ││
│  │ │   title="{{title_attr}}">{{anchor_text}}</a></strong>             ││
│  │ │ </{{heading_tag}}>                                                ││
│  │ └───────────────────────────────────────────────────────────────────┘│
│  │                                                                      ││
│  │ Available Placeholders:                                              ││
│  │ {{url}} - Target URL (required)                                     ││
│  │ {{anchor_text}} - Matched phrase from content                       ││
│  │ {{title}} - Target title                                            ││
│  │ {{title_attr}} - Variable: title attribute text                     ││
│  │ {{heading_tag}} - Variable: h2, h3, etc. (randomized)               ││
│  │                                                                      ││
│  │ ☑ Set as default template                                           ││
│  │                                                    [Create Template] ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  Existing Templates                                                      │
│  ┌───────────────────────┬────────────────────────────────┬────────────┐│
│  │ Name                  │ Template Preview               │ Actions    ││
│  ├───────────────────────┼────────────────────────────────┼────────────┤│
│  │ ⭐ Basic Link (default)│ <a href="{{url}}">...</a>     │ ✏️         ││
│  │ Bold Link             │ <strong><a href...>...</strong>│ ✏️ 🗑️ ⭐   ││
│  │ H2 Wrapped            │ <h2><a href...>...</a></h2>   │ ✏️ 🗑️ ⭐   ││
│  │ Random Heading        │ <{{heading_tag}}>...</...>    │ ✏️ 🗑️ ⭐   ││
│  └───────────────────────┴────────────────────────────────┴────────────┘│
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Validation Rules

- Template MUST contain `{{url}}` placeholder
- Template MUST contain `{{anchor_text}}` placeholder
- Template should be valid HTML
- Name must be unique

---

## 📑 Tab 3: Variables

### Purpose
Define dynamic variables that can be used in templates, with values loaded from CSV/JSON files.

### Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Template Variables                                                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ + Create Variable from File                                          ││
│  ├─────────────────────────────────────────────────────────────────────┤│
│  │ Variable Name: [title_attr                                         ] ││
│  │                Use in templates as: {{title_attr}}                   ││
│  │                                                                      ││
│  │ Source File: [Choose File] title-variations.csv                     ││
│  │ Column/Key:  [title_text     ▼]                                     ││
│  │                                                                      ││
│  │ Selection Mode:                                                      ││
│  │ ○ Sequential (cycle through in order, loop at end)                  ││
│  │ ● Random (random selection each time)                               ││
│  │ ○ Weighted (use 'weight' column for probability)                    ││
│  │                                                    [Create Variable] ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ + Create Variable with Manual Values                                 ││
│  ├─────────────────────────────────────────────────────────────────────┤│
│  │ Variable Name: [heading_tag                                        ] ││
│  │ Values (one per line):                                              ││
│  │ ┌───────────────────────────────────────────────────────────────────┐│
│  │ │ h2                                                                ││
│  │ │ h3                                                                ││
│  │ │ h4                                                                ││
│  │ └───────────────────────────────────────────────────────────────────┘│
│  │ Selection Mode: [Random           ▼]           [Create Variable]    ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  Existing Variables                                                      │
│  ┌────────────────┬───────────────┬───────────┬─────────────┬──────────┐│
│  │ Variable       │ Source        │ Values    │ Mode        │ Actions  ││
│  ├────────────────┼───────────────┼───────────┼─────────────┼──────────┤│
│  │ {{title_attr}} │ title-var.csv │ 15 values │ Sequential  │ 🔄 ✏️ 🗑️ ││
│  │ {{heading_tag}}│ Manual        │ 3 values  │ Random      │ 🔄 ✏️ 🗑️ ││
│  │ {{cta_text}}   │ cta.json      │ 8 values  │ Random      │ 🔄 ✏️ 🗑️ ││
│  └────────────────┴───────────────┴───────────┴─────────────┴──────────┘│
│                                                                          │
│  🔄 = Refresh values from source file                                   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Variable Behavior

| Mode | Description |
|------|-------------|
| Sequential | Uses values in order (0, 1, 2, ..., n, 0, 1, ...) - loops at end |
| Random | Picks random value each time |
| Weighted | Uses `weight` field to determine probability (higher = more likely) |

---

## 📑 Tab 4: Auto-Link

### Purpose
Run bulk internal linking operations on orphan content with configurable settings.

### Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Auto-Link Generator                                                     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ Configuration                                                        ││
│  ├─────────────────────────────────────────────────────────────────────┤│
│  │ Content Type:    ☑ Posts  ☑ Pages  ☐ Categories                     ││
│  │                                                                      ││
│  │ Target Orphan Content:                                               ││
│  │ Link content with fewer than [5   ▼] internal links                 ││
│  │                                                                      ││
│  │ Links per Content: [5   ▼] (1-20)                                   ││
│  │                                                                      ││
│  │ Link Template: [Random Heading Link     ▼]                          ││
│  │                                                                      ││
│  │ Insertion Mode:                                                      ││
│  │ ● First match only (one link per target URL)                        ││
│  │ ○ All matches (link every occurrence)                               ││
│  │ ○ Distributed (spread evenly through content)                       ││
│  │                                                                      ││
│  │ Category Filter: [All Categories        ▼]                          ││
│  │                                                                      ││
│  │ ☑ Preview changes before applying                                   ││
│  │ ☑ Create history backup for each modified content                   ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  Eligible Content (47 items found)                                       │
│  ┌────────────────────────────────────────────┬─────────────┬──────────┐│
│  │ Content                                    │ Current Links│ Select  ││
│  ├────────────────────────────────────────────┼─────────────┼──────────┤│
│  │ ☑ How to Clean Carpets at Home            │ 2 links     │ Preview  ││
│  │ ☑ Steam Cleaning vs Dry Cleaning          │ 0 links     │ Preview  ││
│  │ ☑ Removing Pet Stains from Carpet         │ 1 link      │ Preview  ││
│  │ ☐ DIY Carpet Cleaning Solutions           │ 3 links     │ Preview  ││
│  └────────────────────────────────────────────┴─────────────┴──────────┘│
│                                                                          │
│  ☑ Select All (47)  ☐ Deselect All                                      │
│                                                                          │
│  [Run Auto-Link on Selected (3)]  [Run on All Orphan Content]           │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Progress Modal

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Auto-Linking in Progress                                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Processing: How to Clean Carpets at Home                                │
│                                                                          │
│  ████████████████████░░░░░░░░░░░░░░░░░░░░  45%                           │
│                                                                          │
│  Processed: 21 / 47 items                                                │
│  Links Created: 89                                                       │
│  Skipped (no matches): 3                                                 │
│  Errors: 0                                                               │
│                                                                          │
│                                                    [Cancel]              │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Preview Modal

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Preview: How to Clean Carpets at Home                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Proposed Links (5)                                                      │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ 1. "carpet cleaning tips" → /carpet-cleaning-guide                  ││
│  │    Template: <h2><a href="..." title="Learn more">...</a></h2>      ││
│  │                                                                      ││
│  │ 2. "steam cleaning" → /steam-cleaning-101                           ││
│  │    Template: <h3><a href="..." title="Discover">...</a></h3>        ││
│  │                                                                      ││
│  │ 3. "stain removal" → /stain-removal-tips                            ││
│  │    Template: <h2><a href="..." title="Read about">...</a></h2>      ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  Content Preview (diff view)                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ ...professional █carpet cleaning tips█ that will help...            ││
│  │ ...consider using █steam cleaning█ for deep stains...               ││
│  │ ...our guide to █stain removal█ covers everything...                ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  █ = New link will be added                                             │
│                                                                          │
│                              [Cancel]  [Apply These Links]              │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 📑 Tab 5: Reports

### Purpose
View internal linking statistics and identify content that needs more links.

### Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Internal Linking Report                                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐        │
│  │ 234         │ │ 187         │ │ 47          │ │ 3.2         │        │
│  │ Total       │ │ With Links  │ │ Orphan      │ │ Avg Links   │        │
│  │ Content     │ │             │ │ Content     │ │ Per Content │        │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘        │
│                                                                          │
│  Internal Link Distribution                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │ 0 links  ████████████████████  47 (20%)                             ││
│  │ 1-2      ██████████████  34 (15%)                                   ││
│  │ 3-5      ████████████████████████████  68 (29%)                     ││
│  │ 6-10     ██████████████████████  52 (22%)                           ││
│  │ 10+      █████████████  33 (14%)                                    ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  Orphan Content (0 internal links)                                       │
│  ┌───────────────────────────────────────────┬────────────┬────────────┐│
│  │ Content                                   │ Type       │ Actions    ││
│  ├───────────────────────────────────────────┼────────────┼────────────┤│
│  │ About Our Company                         │ Page       │ [Add Links]││
│  │ Privacy Policy                            │ Page       │ [Add Links]││
│  │ Terms of Service                          │ Page       │ [Add Links]││
│  │ New Blog Post Draft                       │ Post       │ [Add Links]││
│  └───────────────────────────────────────────┴────────────┴────────────┘│
│                                                                          │
│  Top Linked Targets                                                      │
│  ┌───────────────────────────────────────────┬────────────────────────┐ │
│  │ Target URL                                │ Times Linked           │ │
│  ├───────────────────────────────────────────┼────────────────────────┤ │
│  │ /carpet-cleaning-guide                    │ 23                     │ │
│  │ /steam-cleaning-101                       │ 18                     │ │
│  │ /contact-us                               │ 15                     │ │
│  │ /stain-removal-tips                       │ 12                     │ │
│  └───────────────────────────────────────────┴────────────────────────┘ │
│                                                                          │
│  History Summary                                                         │
│  ┌───────────────────────────────────────────┬────────────────────────┐ │
│  │ Content with History                      │ 89 items               │ │
│  │ Total Versions Stored                     │ 312 versions           │ │
│  │ Last Auto-Link Run                        │ 2026-01-30 14:32       │ │
│  └───────────────────────────────────────────┴────────────────────────┘ │
│                                                                          │
│  [Export Report CSV]  [Run Auto-Link on Orphan Content]                 │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 🔌 API Endpoints

### Link Targets
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/lm/v1/internal-linking/targets` | List targets (paginated) |
| POST | `/lm/v1/internal-linking/targets` | Add single target |
| PUT | `/lm/v1/internal-linking/targets/{id}` | Update target |
| DELETE | `/lm/v1/internal-linking/targets/{id}` | Delete target |
| POST | `/lm/v1/internal-linking/import/csv` | Import from CSV |
| POST | `/lm/v1/internal-linking/import/json` | Import from JSON |

### Templates
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/lm/v1/internal-linking/templates` | List templates |
| POST | `/lm/v1/internal-linking/templates` | Create template |
| PUT | `/lm/v1/internal-linking/templates/{id}` | Update template |
| DELETE | `/lm/v1/internal-linking/templates/{id}` | Delete template |
| POST | `/lm/v1/internal-linking/templates/{id}/default` | Set as default |

### Variables
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/lm/v1/internal-linking/variables` | List variables |
| POST | `/lm/v1/internal-linking/variables` | Create variable |
| PUT | `/lm/v1/internal-linking/variables/{id}` | Update variable |
| DELETE | `/lm/v1/internal-linking/variables/{id}` | Delete variable |
| POST | `/lm/v1/internal-linking/variables/{id}/refresh` | Refresh from source |
| POST | `/lm/v1/internal-linking/variables/{id}/reset` | Reset sequential index |

### Auto-Linking
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/lm/v1/internal-linking/orphans` | List orphan content |
| POST | `/lm/v1/internal-linking/generate` | Generate links for content |
| POST | `/lm/v1/internal-linking/generate/bulk` | Bulk generate links |
| POST | `/lm/v1/internal-linking/generate/preview` | Preview proposed links |
| DELETE | `/lm/v1/internal-linking/links/{contentType}/{id}` | Remove links from content |

### Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/lm/v1/internal-linking/report` | Get site-wide report |
| GET | `/lm/v1/internal-linking/report/export` | Export report as CSV |

---

## 🎨 Styling

Uses WordPress admin design tokens from `../00-overview.md`:

```css
/* Tab navigation */
.lm-tabs {
    border-bottom: 1px solid var(--lm-border);
    margin-bottom: 20px;
}

.lm-tab {
    padding: 10px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--lm-text-light);
}

.lm-tab.active {
    color: var(--lm-primary);
    border-bottom: 2px solid var(--lm-primary);
}

/* Stats cards */
.lm-stats-card {
    background: white;
    border: 1px solid var(--lm-border);
    border-radius: 4px;
    padding: 15px;
    text-align: center;
}

.lm-stats-value {
    font-size: 32px;
    font-weight: 600;
    color: var(--lm-text);
}

.lm-stats-label {
    font-size: 12px;
    color: var(--lm-text-light);
    text-transform: uppercase;
}

/* Progress bar */
.lm-progress {
    height: 20px;
    background: var(--lm-bg);
    border-radius: 4px;
    overflow: hidden;
}

.lm-progress-bar {
    height: 100%;
    background: var(--lm-primary);
    transition: width 0.3s ease;
}
```

---

## ♿ Accessibility

- All tabs keyboard navigable (arrow keys)
- Focus indicators on interactive elements
- ARIA labels for buttons with icon-only display
- Progress announcements via aria-live region
- Form labels properly associated with inputs
- Color not used as only indicator (icons + text)

---

## ✅ Acceptance Criteria

| Requirement | Done When |
|-------------|-----------|
| Tab navigation | All 5 tabs accessible and switch content |
| CSV import | File upload, column detection, import works |
| JSON import | File upload with variables extraction works |
| Template CRUD | Create, edit, delete, set default works |
| Variable cycling | Sequential/random selection demonstrable |
| Preview | Shows proposed links before applying |
| Progress | Real-time progress during bulk operations |
| Reports | Statistics and orphan list displayed |
| History | Links to content history visible |
| Responsive | Works on tablet (768px+) |

---

## 📝 Cross-References

- Backend service: `../../01-admin-backend/split-spec/21-internal-linking-service.md`
- Database schema: `../../01-admin-backend/split-spec/04-database-schema.md`
- Shared constants: `../../66-shared-constants.md`
- Design tokens: `../00-overview.md`

---

*This UI enables complete management of internal linking with visual feedback and preview capabilities.*
