# 28 - Yoast SEO Optimization Page

> **Status:** Complete  
> **Priority:** Medium  
> **Updated:** 2026-01-31

---

## Purpose

Admin interface for the Yoast SEO integration, providing tools to find and fix missing focus keywords, manage multiple keywords (Premium), and optimize oversized meta descriptions.

---

## Navigation

```
Link Manager (Menu)
├── Overview
├── Settings
├── Internal Linking
├── Snapshots
└── Yoast SEO ← New Tab (only visible if Yoast is active)
    URL: /wp-admin/admin.php?page=link-manager-yoast
```

---

## Tab Visibility Logic

```php
// Only show tab if Yoast is active
if ($yoastDetector->isYoastActive()) {
    add_submenu_page(
        'link-manager',
        'Yoast SEO Optimization',
        'Yoast SEO',
        'manage_options',
        'link-manager-yoast',
        [$this, 'renderYoastPage']
    );
}
```

---

## Page Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│ Yoast SEO Optimization                              [⚙ Settings]   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─ Status Banner ────────────────────────────────────────────────┐ │
│  │ ✅ Yoast SEO v23.1 Active    [Premium Badge if applicable]     │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌─ Quick Stats ──────────────────────────────────────────────────┐ │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────────────┐   │ │
│  │  │   47    │  │   12    │  │    8    │  │       23        │   │ │
│  │  │ Posts   │  │ Pages   │  │ Cats    │  │ Long Desc       │   │ │
│  │  │ Missing │  │ Missing │  │ Missing │  │ (>140 chars)    │   │ │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────────────┘   │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  [Missing Keywords] [Meta Descriptions] [Optimization Log]         │
│                                                                     │
│  ┌─ Tab Content ──────────────────────────────────────────────────┐ │
│  │                                                                 │ │
│  │  (Dynamic content based on selected tab)                       │ │
│  │                                                                 │ │
│  └─────────────────────────────────────────────────────────────────┘ │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Tab: Missing Keywords

### Content Type Filter

```
┌────────────────────────────────────────────────────────────────────┐
│ Content Type: [All ▼] [Posts] [Pages] [Categories]                 │
│                                                     [Search...]    │
└────────────────────────────────────────────────────────────────────┘
```

### Content Table

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ ☑ │ Title                    │ Type     │ Date       │ Suggested Keyword    │
├───┼──────────────────────────┼──────────┼────────────┼──────────────────────┤
│ ☑ │ How to Optimize Your...  │ Post     │ Jan 28     │ optimize your websi… │
│ ☑ │ Contact Us               │ Page     │ Jan 15     │ contact us           │
│ ☑ │ Marketing Tips           │ Category │ -          │ marketing tips       │
│ ☐ │ About Our Company His... │ Page     │ Dec 10     │ about our company    │
└───┴──────────────────────────┴──────────┴────────────┴──────────────────────┘
│ Selected: 3                                                                  │
│                                                                              │
│ [Preview Selected] [Apply Focus Keywords] [Add to Queue]                     │
└──────────────────────────────────────────────────────────────────────────────┘
│ Showing 1-4 of 67                                    [< Prev] [1] [Next >]   │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Actions

| Action | Description |
|--------|-------------|
| Preview Selected | Show preview of what keywords will be set |
| Apply Focus Keywords | Immediately apply to selected items |
| Add to Queue | Add to background processing queue |

### Keyword Preview Modal

```
┌─────────────────────────────────────────────────────────────────┐
│ Preview Focus Keywords                                     [×]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  The following focus keywords will be applied:                  │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Title: "How to Optimize Your Website for Better..."     │   │
│  │ ↳ Keyword: "optimize your website"                      │   │
│  │   ☑ Also set multiple keywords (Premium)                │   │
│  │     └ optimize, website, better, performance            │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Title: "Contact Us"                                      │   │
│  │ ↳ Keyword: "contact us"                                  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Settings Applied:                                              │
│  • Max length: 60 characters                                    │
│  • Trim mode: Word boundary                                     │
│  • Stop words removed: Yes                                      │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [Cancel] [Apply to All Selected]   │
└─────────────────────────────────────────────────────────────────┘
```

### Row Actions Menu

| Action | Description |
|--------|-------------|
| Edit Post | Open in WordPress editor |
| View | View on frontend |
| Set Custom Keyword | Manual keyword entry |
| Skip | Mark as intentionally skipped |

### Custom Keyword Modal

```
┌─────────────────────────────────────────────────────────────────┐
│ Set Custom Focus Keyword                                   [×]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Post: "How to Optimize Your Website for Better Performance"   │
│                                                                 │
│  Focus Keyword *                                                │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ website optimization tips                               │   │
│  └─────────────────────────────────────────────────────────┘   │
│  32/60 characters                                               │
│                                                                 │
│  {{#if isPremium}}                                              │
│  Additional Keywords (one per line)                             │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ seo optimization                                        │   │
│  │ website performance                                     │   │
│  │ page speed                                              │   │
│  └─────────────────────────────────────────────────────────┘   │
│  3/5 keywords                                                   │
│  {{/if}}                                                        │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                      [Cancel] [Save Keyword]    │
└─────────────────────────────────────────────────────────────────┘
```

---

## Tab: Meta Descriptions

### Filter Options

```
┌────────────────────────────────────────────────────────────────────┐
│ Show descriptions longer than: [140 ▼] characters                  │
│ Content Type: [All ▼]                               [Search...]    │
└────────────────────────────────────────────────────────────────────┘
```

### Descriptions Table

```
┌────────────────────────────────────────────────────────────────────────────────────┐
│ ☑ │ Title              │ Type │ Current Description                    │ Length   │
├───┼────────────────────┼──────┼────────────────────────────────────────┼──────────┤
│ ☑ │ Ultimate Guide ... │ Post │ This comprehensive guide covers eve... │ 187 chars│
│ ☑ │ Product Features   │ Page │ Our product includes many amazing f... │ 156 chars│
│ ☐ │ Services Overview  │ Page │ We offer a wide range of profession... │ 149 chars│
└───┴────────────────────┴──────┴────────────────────────────────────────┴──────────┘
│ Selected: 2                                                                        │
│                                                                                    │
│ [Preview Trimmed] [Apply Trimming] [Add to Queue]                                  │
└────────────────────────────────────────────────────────────────────────────────────┘
```

### Trim Preview Modal

```
┌─────────────────────────────────────────────────────────────────┐
│ Preview Trimmed Descriptions                               [×]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Trim Settings:                                                 │
│  • Max length: 140 characters                                   │
│  • Trim mode: Remove last word                                  │
│  • Add ellipsis: Yes                                            │
│                                                                 │
│  ┌─ Post: "Ultimate Guide to..." ──────────────────────────┐   │
│  │                                                          │   │
│  │ BEFORE (187 chars):                                      │   │
│  │ "This comprehensive guide covers everything you need     │   │
│  │  to know about optimizing your website for search        │   │
│  │  engines and improving your online visibility."          │   │
│  │                                                          │   │
│  │ AFTER (138 chars):                                       │   │
│  │ "This comprehensive guide covers everything you need     │   │
│  │  to know about optimizing your website for search..."    │   │
│  │                                                          │   │
│  │ ⚠ 49 characters removed                                  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [Cancel] [Apply to All Selected]   │
└─────────────────────────────────────────────────────────────────┘
```

### Row Actions Menu

| Action | Description |
|--------|-------------|
| Edit Post | Open in WordPress editor |
| Preview Trim | Show what trimmed version looks like |
| Edit Description | Manual description editing |
| Skip | Mark as intentionally skipped |

### Edit Description Modal

```
┌─────────────────────────────────────────────────────────────────┐
│ Edit Meta Description                                      [×]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Post: "Ultimate Guide to SEO"                                  │
│                                                                 │
│  Meta Description *                                             │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ This comprehensive guide covers everything you need     │   │
│  │ to know about optimizing your website for search...     │   │
│  │                                                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│  138/140 characters ✓                                           │
│                                                                 │
│  [Auto-trim to limit]                                           │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                   [Cancel] [Save Description]   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Tab: Optimization Log

### Log Table

```
┌────────────────────────────────────────────────────────────────────────────────┐
│ Filter: [All Actions ▼] [Last 7 Days ▼]                        [Search...]    │
├────────────────────────────────────────────────────────────────────────────────┤
│ Time        │ Content           │ Action            │ Field    │ Status       │
├─────────────┼───────────────────┼───────────────────┼──────────┼──────────────┤
│ 2 hours ago │ Ultimate Guide... │ Set Focus Keyword │ focuskw  │ ● Applied    │
│ 2 hours ago │ Product Features  │ Trim Description  │ metadesc │ ● Applied    │
│ Yesterday   │ Contact Us        │ Set Focus Keyword │ focuskw  │ ↩ Reverted   │
│ Yesterday   │ About Page        │ Set Multi Keywords│ focuskws │ ● Applied    │
└─────────────┴───────────────────┴───────────────────┴──────────┴──────────────┘
│ Showing 1-4 of 89                                    [< Prev] [1] [Next >]    │
└────────────────────────────────────────────────────────────────────────────────┘
```

### Log Detail Modal

```
┌─────────────────────────────────────────────────────────────────┐
│ Optimization Detail                                        [×]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Content: "Ultimate Guide to SEO"                               │
│  Type: Post                                                     │
│  Action: Set Focus Keyword                                      │
│  Applied: January 31, 2026 at 2:34 PM                          │
│  Applied By: Auto-generation                                    │
│                                                                 │
│  ┌─ Changes ───────────────────────────────────────────────┐   │
│  │                                                          │   │
│  │ Field: _yoast_wpseo_focuskw                             │   │
│  │                                                          │   │
│  │ Before: (empty)                                          │   │
│  │ After: "ultimate guide seo"                              │   │
│  │                                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                      [Revert Change] [Close]    │
└─────────────────────────────────────────────────────────────────┘
```

---

## Settings Modal

Accessed via [⚙ Settings] button in page header.

```
┌─────────────────────────────────────────────────────────────────┐
│ Yoast SEO Integration Settings                             [×]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─ Focus Keyword Settings ─────────────────────────────────┐   │
│ │                                                           │   │
│ │ Auto-generate from title          [Toggle: ON]            │   │
│ │                                                           │   │
│ │ Maximum keyword length            [60    ] characters     │   │
│ │                                                           │   │
│ │ Maximum words                     [5     ] words          │   │
│ │                                                           │   │
│ │ Trim mode                         [Word Boundary    ▼]    │   │
│ │   Options: Hard Cut, Word Boundary                        │   │
│ │                                                           │   │
│ │ Exclude stop words               [Toggle: ON]             │   │
│ │   (a, an, the, and, or, but, in, on, at, to, for, etc.)  │   │
│ │                                                           │   │
│ └───────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─ Multiple Keywords (Premium Only) ───────────────────────┐   │
│ │                                                           │   │
│ │ Enable multiple keywords          [Toggle: ON]            │   │
│ │                                                           │   │
│ │ Maximum keywords                  [5     ] keywords       │   │
│ │                                                           │   │
│ │ Minimum word length               [3     ] characters     │   │
│ │                                                           │   │
│ │ Exclude numbers                   [Toggle: ON]            │   │
│ │                                                           │   │
│ └───────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─ Meta Description Settings ──────────────────────────────┐   │
│ │                                                           │   │
│ │ Maximum description length        [140   ] characters     │   │
│ │   Common values: 135, 140, 150, 160                       │   │
│ │                                                           │   │
│ │ Minimum description length        [50    ] characters     │   │
│ │                                                           │   │
│ │ Trim mode                         [Remove Last Word ▼]    │   │
│ │   Options: Hard Cut, Remove Last Word, Sentence Boundary  │   │
│ │                                                           │   │
│ │ Add ellipsis (...)               [Toggle: ON]             │   │
│ │                                                           │   │
│ └───────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─ Content Types ──────────────────────────────────────────┐   │
│ │                                                           │   │
│ │ Include in optimization:                                  │   │
│ │ ☑ Posts                                                   │   │
│ │ ☑ Pages                                                   │   │
│ │ ☑ Categories                                              │   │
│ │ ☐ Tags                                                    │   │
│ │ ☐ Custom Post Types: [                              ]     │   │
│ │                                                           │   │
│ └───────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─ Batch Processing ───────────────────────────────────────┐   │
│ │                                                           │   │
│ │ Batch size                        [25    ] items          │   │
│ │                                                           │   │
│ │ Delay between batches             [500   ] ms             │   │
│ │                                                           │   │
│ └───────────────────────────────────────────────────────────┘   │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│   [Reset to Defaults]               [Cancel] [Save Settings]   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Processing Queue Indicator

When items are being processed in background:

```
┌─────────────────────────────────────────────────────────────────┐
│ ⏳ Processing 12 of 47 items...                    [Cancel All] │
│ ████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 25%                    │
│                                                                 │
│ Current: "How to Optimize Your Website..."                      │
│ Estimated time remaining: ~2 minutes                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Toast Notifications

| Action | Message |
|--------|---------|
| Keywords applied | "✓ Focus keywords set for 3 items" |
| Descriptions trimmed | "✓ Meta descriptions trimmed for 5 items" |
| Added to queue | "📋 12 items added to processing queue" |
| Revert success | "↩ Change reverted for 'Ultimate Guide...'" |
| Settings saved | "✓ Yoast settings saved" |
| Error | "⚠ Failed to set keyword: [error message]" |

---

## Empty States

### No Missing Keywords

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│                          ✅                                     │
│                                                                 │
│            All content has focus keywords set!                  │
│                                                                 │
│    Great job! Your SEO is in good shape.                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Yoast Not Installed

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│                          ⚠️                                     │
│                                                                 │
│               Yoast SEO Not Detected                           │
│                                                                 │
│    This feature requires Yoast SEO plugin to be installed      │
│    and activated.                                               │
│                                                                 │
│    [Install Yoast SEO]                                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## API Endpoints Used

| Endpoint | Usage |
|----------|-------|
| `GET /lm/v1/yoast/status` | Check Yoast installation |
| `GET /lm/v1/yoast/settings` | Load settings |
| `PUT /lm/v1/yoast/settings` | Save settings |
| `GET /lm/v1/yoast/content/missing-keywords` | List missing keywords |
| `GET /lm/v1/yoast/content/oversized-descriptions` | List long descriptions |
| `POST /lm/v1/yoast/content/{id}/optimize` | Optimize single item |
| `POST /lm/v1/yoast/batch/focus-keywords` | Batch apply keywords |
| `POST /lm/v1/yoast/batch/trim-descriptions` | Batch trim descriptions |
| `GET /lm/v1/yoast/audit-log` | Get optimization history |
| `POST /lm/v1/yoast/audit-log/{id}/revert` | Revert a change |

---

## Accessibility

| Element | Requirement |
|---------|-------------|
| Tables | Proper headers, sortable columns announced |
| Modals | Focus trap, escape to close, ARIA labels |
| Progress | Live region for queue updates |
| Actions | Keyboard accessible, clear focus states |
| Status | Color + icon + text for all states |

---

## Related Specs

- `27-yoast-seo-integration.md` - Backend service
- `20-settings-page.md` - Main settings integration
- `00-overview.md` - Navigation structure

---

## Acceptance Criteria

- [ ] Tab only visible when Yoast is active
- [ ] Premium badge shown when Yoast Premium active
- [ ] Stats cards show accurate counts
- [ ] Content tables paginate and filter correctly
- [ ] Keyword preview shows accurate transformations
- [ ] Batch operations work with progress indicator
- [ ] Settings persist and apply correctly
- [ ] Audit log shows all changes with revert option
- [ ] Empty states display appropriately
- [ ] All actions provide feedback via toasts
- [ ] Accessible via keyboard navigation
