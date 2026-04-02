# Category Generator — Plugin Architecture Spec

> **Plugin:** Category Generator for Area by Riseup Asia LLC  
> **Author:** MD Alim Ul Karim  
> **Version:** 2.3.0  
> **Created:** 2026-04-02  
> **Status:** Approved — reference document

---

## 1. High-Level Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                      WORDPRESS ADMIN UI                         │
│  Generate │ Snapshots │ History │ Templates │ Inner │ Business  │
│           │           │         │           │ Tmpls │ Profile   │
│           │           │         │           │       │ Settings  │
│           │           │         │           │       │ Tests     │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                     AJAX HANDLER LAYER                           │
│  Category_Generator_Pro (category-generator.php)                │
│  ├── wp_ajax_cg_generate_categories                             │
│  ├── wp_ajax_cg_preview_combinations                            │
│  ├── wp_ajax_cg_save_template / get / delete / duplicate        │
│  ├── wp_ajax_cg_save_inner_template / get / delete              │
│  ├── wp_ajax_cg_save_variable / get / delete                    │
│  ├── wp_ajax_cg_save_settings / get                             │
│  ├── wp_ajax_cg_export_data / import_data                       │
│  ├── wp_ajax_cg_create_snapshot / restore / delete / download   │
│  ├── wp_ajax_cg_inject_inner_template                           │
│  ├── wp_ajax_cg_bulk_delete_history(_and_categories)            │
│  └── wp_ajax_cg_download_database / restore_database            │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                   SERVICE / BUSINESS LOGIC                       │
│  CG_Settings       — AI providers, remote APIs, Yoast config   │
│  CG_Inner_Templates — Reusable snippets ({inner:id})           │
│  CG_Variables      — Dynamic placeholder values                 │
│  CG_Import_Export  — ZIP/CSV/SQLite import & export             │
│  CG_Tests          — Built-in validation test runner            │
│  CG_Snapshot       — WP category table backup/restore           │
└────────┬────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│                   DATA / PERSISTENCE LAYER                       │
│  CG_Database (SQLite3)                                          │
│  ├── Location: wp-content/uploads/category-generator/db/        │
│  ├── Legacy migration from: uploads/category-generator-db/      │
│  ├── Templates (HTML, Meta, Schema)                             │
│  ├── Template Categories (3-level hierarchy)                    │
│  ├── Inner Templates                                            │
│  ├── Variables                                                   │
│  ├── Business Profiles                                           │
│  ├── Category History (audit trail)                              │
│  └── Settings (key-value)                                        │
│                                                                  │
│  CG_Snapshot (SQLite per snapshot)                               │
│  └── Location: wp-content/category-generator-snapshots/          │
│      ├── YYYY-MM-DD_HHmmss_slug.db                              │
│      ├── .htaccess (deny from all)                               │
│      └── index.php (silence)                                     │
│                                                                  │
│  WordPress Tables (read/write via $wpdb)                        │
│  ├── wp_terms                                                    │
│  ├── wp_term_taxonomy                                            │
│  └── wp_termmeta                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Core Feature: Cross-Join Category Generation

### Flow

| Step | Action | Details |
|------|--------|---------|
| 1 | User inputs titles | One per line in textarea |
| 2 | User inputs areas | One per line in textarea |
| 3 | Optional: name format | Default: `{title} {area}` |
| 4 | Optional: HTML template | With `{title}`, `{area}`, `{category}` placeholders |
| 5 | Preview | AJAX `cg_preview_combinations` — returns all combos |
| 6 | Generate | AJAX `cg_generate_categories` — batch creates via `wp_insert_term()` |
| 7 | History logged | Each generation recorded with titles, areas, template used |

### Generation Workflow Diagram

> See visual flowchart: `category-generation-workflow.mmd`

### Detailed Generation Pipeline

#### Phase 0: Pre-flight

| Step | Action | Details |
|------|--------|---------|
| 0.1 | Nonce + capability check | `check_ajax_referer` + `manage_categories` |
| 0.2 | Parse inputs | Titles and areas split by newline, trimmed, empty lines removed |
| 0.3 | Validate | Error if titles or areas empty |
| 0.4 | Auto-snapshot | If `auto_snapshot_before_generate` setting is true, creates snapshot with type `auto` |
| 0.5 | Load business profile | `get_business_profile()` for placeholder data |

#### Phase 1: Parent Category Creation (Optional)

When `create_parents = true`, each **title** becomes a parent category:

| Step | Action | Details |
|------|--------|---------|
| 1.1 | For each title | Iterate titles array |
| 1.2 | `term_exists($title, $taxonomy)` | Check WordPress for existing term |
| 1.3a | **Exists** | Store `term_id` in `$parent_term_ids[$title]` |
| 1.3b | **New** | `wp_insert_term($title, $taxonomy, ['parent' => $static_parent_id])` |
| 1.4 | Log to history | `insert_category_history()` with empty area |

#### Phase 2: Cross-Join Loop

For each **title × area** combination:

| Step | Action | Details |
|------|--------|---------|
| 2.1 | `(S)` marker detection | If area ends with `(S)`, strip marker and force child mode |
| 2.2 | Resolve parent ID | Priority: `$parent_term_ids[$title]` → `term_exists($title)` → `$static_parent_id` |
| 2.3 | Generate category name | `str_replace(['{title}','{area}'], [$title,$area], $format)` |
| 2.4 | Generate slug | From `slug_pattern` or `sanitize_title($category_name)` |
| 2.5 | Generate meta fields | `meta_title_pattern` and `meta_description_pattern` through `generate_from_pattern()` |
| 2.6 | Generate HTML body | Template placeholders replaced via `generate_description()` |
| 2.7 | Process inner templates | `CG_Inner_Templates::process_content()` resolves `{inner:id}` and `{inner:name}` |
| 2.8 | Wrap HTML | `<div class="{wrapper_class}">...</div>` |
| 2.9 | Schema injection | If enabled: generate JSON-LD → append `<script type="application/ld+json">` in schema wrapper div |

#### Duplicate Handling

| Condition | Behavior |
|-----------|----------|
| `term_exists($category_name, $taxonomy)` returns truthy | **Skip creation** — category added to `categories_existed` array |
| `update_existing_meta = true` AND term exists | **Update Yoast meta** on existing term (title, description, focus keyword) |
| `update_existing_meta = false` AND term exists | **No changes** — silently skipped |
| Term does not exist | **Create** via `wp_insert_term()` with description, slug, parent |

> **Note:** There is no batch chunking — all combinations are processed in a single synchronous AJAX request. For large cross-joins (e.g., 50×50 = 2,500), the PHP execution time and memory limits apply.

#### Yoast Meta Writing (`update_yoast_meta()`)

Called for both new terms and existing terms (when `update_existing_meta` is enabled):

| Step | Target | Key | Value |
|------|--------|-----|-------|
| 1 | Check Yoast active | `WPSEO_VERSION` or `WPSEO_Taxonomy_Meta` | — |
| 2 | Term meta (primary) | `_yoast_wpseo_title` | Generated meta title |
| 3 | Term meta (primary) | `_yoast_wpseo_metadesc` | Generated meta description |
| 4 | Term meta (primary) | `_yoast_wpseo_focuskw` | From `yoast_focus_keyword_pattern` setting |
| 5 | Options (internal) | `wpseo_taxonomy_meta[$taxonomy][$term_id]` | `wpseo_title`, `wpseo_desc`, `wpseo_focuskw` |
| 6 | Fallback meta | `cg_meta_title` | Always written regardless of Yoast status |
| 7 | Fallback meta | `cg_meta_description` | Always written regardless of Yoast status |

#### Inner Template Resolution (During Generation)

`CG_Inner_Templates::process_content()` is called with full context:

```php
$context = [
    'title'            => $title,
    'area'             => $clean_area,
    'category'         => $category_name,
    'slug'             => $slug,
    'url'              => home_url('/' . $slug . '/'),
    'business_profile' => $business_profile
];
```

Resolution order:
1. `{inner:123}` — numeric ID → `get_template(123)` → replace content
2. `{inner:my-snippet}` — name ID → `get_template_by_name('my-snippet')` → replace content
3. Each resolved inner template's content is **recursively processed** for placeholders
4. Unresolved `{inner:...}` references are left as-is (no error)

#### Full Placeholder Map (Generation Context)

| Placeholder | Value | Source |
|-------------|-------|--------|
| `{title}` | Raw title | User input |
| `{area}` | Raw area (cleaned) | User input, `(S)` stripped |
| `{category}` / `{name}` | Generated category name | Format pattern |
| `{Title}` / `{Area}` | Title Case | `ucwords()` |
| `{TITLE}` / `{AREA}` | UPPERCASE | `strtoupper()` |
| `{title_lower}` / `{area_lower}` | lowercase | `strtolower()` |
| `{business_name}` | Company name | Business profile |
| `{business_type}` | Schema.org type | Business profile |
| `{phone}` / `{email}` / `{website}` | Contact info | Business profile |
| `{street_address}` / `{city}` / `{state}` | Address | Business profile |
| `{postal_code}` / `{country}` | Location | Business profile |
| `{opening_hours}` / `{price_range}` | Operations | Business profile |
| `{rating_value}` / `{rating_count}` | Reviews | Business profile |
| `{logo_url}` / `{image_url}` | Media | Business profile |
| `{slug}` | Category slug | Generated |
| `{url}` | Full URL path | `home_url('/' + slug + '/')` |
| `{meta_title}` / `{meta_desc}` | SEO fields | Generated from patterns |
| `{inner:ID}` / `{inner:name}` | Inner template | Resolved recursively |
| `{var:key}` | Variable | From variables table |

#### Result Structure

```json
{
  "success": true,
  "data": {
    "parents_created": [{"id": 1, "name": "Plumbing"}],
    "parents_existed": ["Electrical"],
    "categories_created": [{"id": 5, "name": "Plumbing Downtown", "slug": "plumbing-downtown", "parent": "Plumbing"}],
    "categories_existed": ["Plumbing Uptown"],
    "meta_updated": ["Plumbing Uptown"],
    "errors": []
  }
}
```

### Options

- **Taxonomy**: Default `category` or any registered custom taxonomy
- **Parent category**: Assign all generated terms under a parent (`static_parent_id`)
- **Create parents**: Each title becomes a parent category
- **Make children**: Generated categories nested under their title's parent
- **Update existing meta**: Write Yoast meta even on pre-existing terms
- **Include schema**: Append JSON-LD `<script>` to description
- **Use global schema**: Use business profile data in schema
- **Auto-snapshot**: Creates snapshot before generation (if enabled in settings)

---

## 3. Template System

### Template Types

| Type | Purpose | Tab |
|------|---------|-----|
| HTML | Category description body | Templates → HTML |
| Meta | Yoast SEO meta description | Templates → Meta |
| Schema | JSON-LD Local Business schema | Templates → Schema |

### Template Categories (3-Level Hierarchy)

```
Root Category
├── Subcategory A
│   ├── Template 1
│   └── Template 2
└── Subcategory B
    └── Template 3
```

- Stored in `template_categories` table (SQLite)
- Fields: `id`, `name`, `parent_id`, `template_type`
- Filterable per tab (HTML / Meta / Schema / all)

### Inner Templates

Reusable snippets embedded via `{inner:id}` or `{inner:name}`:

| Type Constant | Label |
|---------------|-------|
| `anchor` | Anchor Link |
| `header` | Header Block |
| `marketing` | Marketing Copy |
| `cta` | Call to Action |
| `snippet` | Text Snippet |
| `link_list` | Category Link List |

Inner templates support the same placeholder system and can be injected into existing category descriptions via the History page inject UI (see §3.1 below).

---

## 3.1. History Inject Workflow

### Purpose

Inject inner template content into **existing** WordPress category descriptions without regenerating the category. This allows post-generation enrichment — adding CTAs, anchor links, marketing blocks, or schema snippets to categories that were already created.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    HISTORY PAGE                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ Category Row: "Plumbing Downtown"  [View] [Inject]        │  │
│  └───────────────────────────────────────┬───────────────────┘  │
│                                          │ click [Inject]       │
│                                          ▼                      │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              INJECT MODAL (#cg-inject-modal)               │  │
│  │                                                            │  │
│  │  ┌─ Inner Template Select ──────────────────────────────┐ │  │
│  │  │  [▼ Select Inner Template ]                          │ │  │
│  │  │  Preview: <div class="cta">Call us today!</div>      │ │  │
│  │  └──────────────────────────────────────────────────────┘ │  │
│  │                                                            │  │
│  │  ┌─ Current Description (textarea) ─────────────────────┐ │  │
│  │  │  <div class="service">                               │ │  │
│  │  │    <h2>Plumbing Downtown</h2>                        │ │  │
│  │  │    <p>Professional plumbing in Downtown...</p>  ◄── cursor│
│  │  │  </div>                                              │ │  │
│  │  └──────────────────────────────────────────────────────┘ │  │
│  │                                                            │  │
│  │  [Cancel]  [Insert at Start]  [Insert at End]  [Inject at Cursor] │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Step-by-Step Flow

| Step | Component | Action | Details |
|------|-----------|--------|---------|
| 1 | History table | User clicks **[Inject]** link on a history row | Passes `history_id` |
| 2 | `openInjectModal()` | Resets modal state | Clears template select, preview, content |
| 3 | AJAX | `cg_get_history_item` | Fetches history record → gets `term_id` + `taxonomy` |
| 4 | AJAX | `cg_get_term_description` | Fetches **live** WordPress term description via `get_term()` |
| 5 | Fallback | If term fetch fails | Falls back to `meta_description` from history record |
| 6 | Modal | Populates textarea with current description | User can see and edit |
| 7 | User | Selects inner template from dropdown | Preview updates inline |
| 8 | User | Clicks position button | One of: Start, End, or Cursor |
| 9 | `performInject()` | Computes `newContent` based on position | See position modes below |
| 10 | AJAX | `cg_inject_inner_template` | Sends `history_id`, `inner_template_id`, `new_content` |
| 11 | Server | `wp_update_term()` | Updates WP term description in database |
| 12 | Server | `update_category_history()` | Updates history record's `meta_description` |
| 13 | UI | Success alert + modal close + history reload | |

### Position Modes

| Mode | Button | Behavior | Code |
|------|--------|----------|------|
| **Start** | `[Insert at Start]` | Prepends template content + `\n` before existing content | `templateContent + '\n' + content` |
| **End** | `[Insert at End]` | Appends `\n` + template content after existing content | `content + '\n' + templateContent` |
| **Cursor** | `[Inject at Cursor]` | Inserts template content at textarea cursor position (`selectionStart`) | `content.substring(0, cursorPos) + templateContent + content.substring(cursorPos)` |

**Cursor position detection:** Uses `textarea.selectionStart` (DOM property). If no cursor position is set (user hasn't clicked into textarea), defaults to position `0` (equivalent to Start).

### Data Flow Diagram

```
                        ┌──────────────┐
                        │ User clicks  │
                        │ [Inject]     │
                        └──────┬───────┘
                               │
                    ┌──────────▼──────────┐
                    │ cg_get_history_item │
                    │ → term_id, taxonomy │
                    └──────────┬──────────┘
                               │
                  ┌────────────▼────────────┐
                  │ cg_get_term_description │
                  │ → live WP description   │
                  └────────────┬────────────┘
                               │
                    ┌──────────▼──────────┐
                    │ User selects inner  │
                    │ template + position │
                    └──────────┬──────────┘
                               │
              ┌────────────────▼────────────────┐
              │         Position Logic           │
              │  start: prepend + \n             │
              │  end:   \n + append              │
              │  cursor: splice at selectionStart│
              └────────────────┬────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │ Merged newContent    │
                    └──────────┬──────────┘
                               │
               ┌───────────────▼───────────────┐
               │  cg_inject_inner_template     │
               │  ├── wp_update_term()         │
               │  │   → updates WP description │
               │  └── update_category_history()│
               │      → updates history record │
               └───────────────────────────────┘
```

### AJAX Endpoints Used

| Action | Method | Parameters | Response |
|--------|--------|------------|----------|
| `cg_get_history_item` | POST | `id` | `{ term_id, taxonomy, meta_description, ... }` |
| `cg_get_term_description` | POST | `term_id`, `taxonomy` | `{ description: "..." }` |
| `cg_inject_inner_template` | POST | `history_id`, `inner_template_id`, `new_content` | `{ message: "Injected successfully" }` |

### Inner Template Resolution

When injecting, the template content comes from the **`<select>` option's `data-content` attribute** — the raw HTML of the inner template. The content is inserted **as-is** without placeholder resolution at inject time. Placeholders (`{title}`, `{area}`, etc.) remain in the injected content and are only resolved when the category description is rendered on the frontend.

**Resolution chain:**
1. Inner template HTML stored in `inner_templates.content`
2. Loaded into `<option data-content="...">` on modal open
3. Selected template's `data-content` read by JavaScript
4. Spliced into existing description at chosen position
5. Full merged HTML sent to server as `new_content`
6. Server writes to WP via `wp_update_term()` — no placeholder processing

### Input Sanitization

| Layer | Sanitization |
|-------|-------------|
| Client → Server | `new_content` sent as POST body |
| Server | `wp_kses_post($new_content)` — allows safe HTML tags, strips scripts |
| WP Storage | Standard `wp_update_term()` handling |

### UI Components

| Element | ID | Purpose |
|---------|-----|---------|
| Modal overlay | `#cg-inject-modal` | Full-screen modal container |
| History ID | `#cg-inject-history-id` | Hidden input storing current history row ID |
| Template select | `#cg-inject-template-select` | Dropdown of all inner templates with `data-content` |
| Template preview | `#cg-inject-template-preview` | Shows selected template's HTML content |
| Content textarea | `#cg-inject-content` | Editable current description (click to set cursor) |
| Cancel button | `#cg-inject-cancel` | Closes modal |
| Start button | `#cg-inject-at-start` | Triggers `performInject('start')` |
| End button | `#cg-inject-at-end` | Triggers `performInject('end')` |
| Cursor button | `#cg-inject-at-cursor` | Triggers `performInject('cursor')` |

### Edge Cases

| Scenario | Behavior |
|----------|----------|
| Term deleted in WP but exists in history | `cg_get_term_description` returns error; falls back to history `meta_description` |
| No inner template selected | Alert: "Please select an inner template" — inject blocked |
| Cursor not placed in textarea | `selectionStart` defaults to `0` (same as Start mode) |
| Empty description (new term) | Template content becomes the entire description |
| Multiple sequential injects | Each reads fresh description from WP, preventing stale overwrites |

---

## 3.2. Variables System

### Purpose

Dynamic key-value placeholders (`{var:key}`) that can be used in any template — HTML, Meta, Schema, or Inner Templates. Variables can **reference other variables**, enabling composable, DRY content blocks.

### Storage

SQLite `variables` table:

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT |
| `name` | TEXT | NOT NULL UNIQUE |
| `value` | TEXT | NOT NULL |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP |

### Syntax

```
{var:variable_name}
```

Name pattern: `[a-zA-Z_][a-zA-Z0-9_]*` (letters, digits, underscores; must start with letter or underscore).

### CRUD Operations

| Operation | AJAX Action | Parameters | Handler |
|-----------|-------------|------------|---------|
| List all | `cg_get_variables` | — | Returns all variables as key-value pairs |
| Create/Update | `cg_save_variable` | `name`, `value` | Upserts by unique `name` |
| Delete | `cg_delete_variable` | `name` | Removes by name |

All endpoints require `check_ajax_referer('cg_nonce')` + `manage_categories` capability.

### Resolution Order

When `process_template()` or `compile_variables()` is called:

```
1. Load stored variables from SQLite
2. Merge with runtime context (title, area, etc.)
       Context takes precedence over stored variables
3. For each variable value:
   a. Scan for {var:name} references
   b. Recursively resolve referenced variables
   c. Max recursion depth: 10 (prevents infinite loops)
   d. Unresolved references left as literal {var:name}
4. Return compiled map
```

### Variable-to-Variable References

Variables can reference other variables, enabling composition:

```
company_tagline  = "Your trusted partner"
company_intro    = "{var:company_name} - {var:company_tagline}"
full_header      = "<h2>{var:company_intro} in {area}</h2>"
```

Resolution for `{var:full_header}`:
```
Step 1: {var:full_header}
     → "<h2>{var:company_intro} in {area}</h2>"

Step 2: {var:company_intro}
     → "{var:company_name} - {var:company_tagline}"

Step 3: {var:company_name} → "Acme Plumbing"
        {var:company_tagline} → "Your trusted partner"

Result: "<h2>Acme Plumbing - Your trusted partner in {area}</h2>"
```

> **Note:** `{area}` is not a variable reference — it's a generation-time placeholder resolved separately by `generate_from_pattern()`.

### Recursion Safety

| Guard | Value | Behavior |
|-------|-------|----------|
| Max depth | 10 | After 10 levels of `{var:X}` → `{var:Y}` → ..., stops resolving |
| Circular reference | Handled | A→B→A stops at depth limit, leaves `{var:A}` literal |
| Non-string values | Skipped | `resolve_value()` returns non-strings as-is |

### Expression Parsing

`parse_expression()` supports **string concatenation** with `+` operator:

```
"Hello " + {var:name} + " from " + {var:city}
```

- Quoted strings (`"..."` or `'...'`) are literal text
- Unquoted tokens are resolved as variable references
- Whitespace around `+` is trimmed

### Interaction with Inner Templates

Variables and inner templates are resolved at **different stages** during generation:

```
┌─────────────────────────────────────────────────┐
│           Generation Pipeline Order              │
│                                                  │
│  1. generate_from_pattern()                      │
│     └── Resolves: {title}, {area}, {category},  │
│         {business_*}, {slug}, etc.               │
│                                                  │
│  2. generate_description()                       │
│     └── Resolves: Same placeholders + {meta_*}  │
│         + {var:*} via process_template()         │
│                                                  │
│  3. CG_Inner_Templates::process_content()        │
│     └── Resolves: {inner:id}, {inner:name}      │
│         Each inner template's content gets       │
│         placeholder replacement (title, area,    │
│         business_profile) but NOT {var:*}        │
│                                                  │
│  4. Final HTML assembly                          │
│     └── Wrap in div + append schema              │
└─────────────────────────────────────────────────┘
```

**Key insight:** `{var:*}` placeholders are resolved in step 2 via `CG_Variables::process_template()`. Inner templates resolved in step 3 receive context placeholders (`{title}`, `{area}`, etc.) but do **not** re-run variable resolution. To use variables inside inner templates, embed the variable's **value** directly or use standard placeholders.

### Usage Examples

| Variable Name | Value | Use Case |
|---------------|-------|----------|
| `company_name` | `Acme Plumbing` | Reuse across all templates |
| `service_guarantee` | `100% satisfaction guaranteed` | Marketing consistency |
| `base_url` | `https://example.com` | Link building |
| `cta_text` | `Call {var:company_name} at {phone}` | Composable CTA |
| `footer_html` | `<p>{var:service_guarantee}. Serving {area}.</p>` | Multi-variable composition |

---

## 4. Snapshot System

### Purpose

Full backup of WordPress category tables (`wp_terms`, `wp_term_taxonomy`, `wp_termmeta`) stored as individual SQLite databases.

### Storage

```
wp-content/category-generator-snapshots/
├── 2026-01-09_143022_pre-generation.db
├── 2026-01-09_150000_auto.db
├── .htaccess          ← "Deny from all"
└── index.php          ← "Silence is golden"
```

### Filename Format

```
YYYY-MM-DD_HHmmss_slug.db
```

### Operations

| Operation | AJAX Action | Notes |
|-----------|-------------|-------|
| Create | `cg_create_snapshot` | Manual or auto (before generation) |
| Restore | `cg_restore_snapshot` | Merge mode — adds/updates, no deletes |
| Delete | `cg_delete_snapshot` | Removes .db file |
| Download | `cg_download_snapshot` | Direct file download |
| List recent | `cg_get_recent_snapshots` | For toolbar dropdown |

### Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Auto-snapshot before generate | On | Creates backup before each batch |
| Max auto-snapshots | 20 | Oldest auto-snapshots pruned beyond limit |

---

## 5. Database Architecture

### Engine

**SQLite3** — self-contained, no external DB dependency.

### Location

```
wp-content/uploads/category-generator/db/category_generator.db
```

Legacy migration: if `wp-content/uploads/category-generator-db/` exists and canonical path doesn't, auto-copies on init.

### Table Schemas

#### `category_history` — Audit trail of generated categories

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `term_id` | INTEGER | NOT NULL | WordPress term ID |
| `category_name` | TEXT | NOT NULL | Generated category name |
| `slug` | TEXT | NOT NULL | URL slug |
| `title` | TEXT | NOT NULL | Source title input |
| `area` | TEXT | NOT NULL | Source area input |
| `parent_id` | INTEGER | DEFAULT 0 | Parent term ID |
| `taxonomy` | TEXT | DEFAULT 'category' | WP taxonomy |
| `meta_title` | TEXT | | Yoast meta title |
| `meta_description` | TEXT | | Yoast meta description |
| `focus_keyword` | TEXT | | Yoast focus keyword |
| `has_schema` | INTEGER | DEFAULT 0 | 1 if schema was applied |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `created_by` | INTEGER | | WP user ID |

**Indexes:** `idx_history_term(term_id)`, `idx_history_name(category_name)`, `idx_history_created(created_at)`

#### `html_templates` — Category description templates

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `name` | TEXT | NOT NULL | Template display name |
| `description` | TEXT | | Short description |
| `content` | TEXT | NOT NULL | HTML body with placeholders |
| `category` | TEXT | DEFAULT '' | Template category reference |
| `is_default` | INTEGER | DEFAULT 0 | Default selection flag |
| `is_faq` | INTEGER | DEFAULT 0 | Contains FAQ schema |
| `faq_schema` | TEXT | | FAQ structured data JSON |
| `template_group` | TEXT | DEFAULT '' | Grouping label |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

#### `meta_templates` — Yoast SEO meta templates

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `name` | TEXT | NOT NULL | Template name |
| `meta_title_pattern` | TEXT | | Title pattern with placeholders |
| `meta_description_pattern` | TEXT | | Description pattern |
| `meta_title_variations` | TEXT | | JSON array of title variations |
| `meta_description_variations` | TEXT | | JSON array of desc variations |
| `slug_pattern` | TEXT | | Custom slug pattern |
| `focus_keyword_pattern` | TEXT | | Focus keyword pattern |
| `category` | TEXT | DEFAULT '' | Template category |
| `is_default` | INTEGER | DEFAULT 0 | |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

#### `schema_templates` — JSON-LD structured data templates

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `name` | TEXT | NOT NULL | Template name |
| `schema_type` | TEXT | DEFAULT 'LocalBusiness' | Schema.org type |
| `schema_content` | TEXT | NOT NULL | JSON-LD template body |
| `category` | TEXT | DEFAULT '' | Template category |
| `is_default` | INTEGER | DEFAULT 0 | |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

#### `inner_templates` — Reusable content blocks

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `name` | TEXT | NOT NULL | Display name |
| `name_id` | TEXT | NOT NULL UNIQUE | Slug for `{inner:name_id}` |
| `type` | TEXT | DEFAULT 'snippet' | One of: anchor, header, marketing, cta, snippet, link_list |
| `content` | TEXT | NOT NULL | HTML content |
| `category` | TEXT | DEFAULT '' | Template category |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

**Index:** `idx_inner_name_id(name_id)`

#### `variables` — Dynamic placeholder values

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `name` | TEXT | NOT NULL UNIQUE | Key for `{var:name}` |
| `value` | TEXT | NOT NULL | Replacement value |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

#### `settings` — Plugin configuration (key-value)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `setting_key` | TEXT | NOT NULL UNIQUE | Setting identifier |
| `setting_value` | TEXT | | JSON or scalar value |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

**Index:** `idx_settings_key(setting_key)`

#### `business_profile` — Local Business schema data

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `business_name` | TEXT | | Company name |
| `business_type` | TEXT | DEFAULT 'LocalBusiness' | Schema.org type |
| `street_address` | TEXT | | |
| `city` | TEXT | | |
| `state` | TEXT | | |
| `postal_code` | TEXT | | |
| `country` | TEXT | DEFAULT 'Australia' | |
| `phone` | TEXT | | |
| `email` | TEXT | | |
| `website` | TEXT | | |
| `opening_hours` | TEXT | | JSON or string |
| `price_range` | TEXT | | e.g., "$$ - $$$" |
| `price_range_min` | REAL | | |
| `price_range_max` | REAL | | |
| `price_note` | TEXT | DEFAULT 'subject to change' | |
| `service_areas` | TEXT | | JSON array |
| `services_offered` | TEXT | | JSON array |
| `rating_value` | REAL | | Aggregate rating |
| `rating_count` | INTEGER | | Number of reviews |
| `logo_url` | TEXT | | |
| `image_url` | TEXT | | |
| `social_profiles` | TEXT | | JSON array of URLs |
| `is_default` | INTEGER | DEFAULT 0 | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

#### `template_categories` — 3-level hierarchy

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `name` | TEXT | NOT NULL | Category name |
| `parent_id` | INTEGER | DEFAULT 0 | 0 = root |
| `level` | INTEGER | DEFAULT 0 | Depth (0/1/2) |
| `template_type` | TEXT | DEFAULT 'html' | html, meta, schema, all |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

**Index:** `idx_template_categories_parent(parent_id)`

#### `category_snapshots` — Snapshot metadata

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `title` | TEXT | NOT NULL | Snapshot name |
| `notes` | TEXT | | User notes |
| `type` | TEXT | DEFAULT 'manual' | manual or auto |
| `filename` | TEXT | NOT NULL | e.g., 2026-01-09_143022_slug.db |
| `filepath` | TEXT | NOT NULL | Full filesystem path |
| `terms_count` | INTEGER | DEFAULT 0 | Rows in wp_terms |
| `taxonomy_count` | INTEGER | DEFAULT 0 | Rows in wp_term_taxonomy |
| `termmeta_count` | INTEGER | DEFAULT 0 | Rows in wp_termmeta |
| `filesize` | INTEGER | DEFAULT 0 | Bytes |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `created_by` | INTEGER | | WP user ID |

**Indexes:** `idx_snapshots_type(type)`, `idx_snapshots_created(created_at)`

#### `import_export_history` — Operation log

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `operation` | TEXT | NOT NULL | 'import' or 'export' |
| `types` | TEXT | | JSON array of data types |
| `format` | TEXT | | sqlite, csv, zip, xml |
| `imported_count` | INTEGER | DEFAULT 0 | |
| `updated_count` | INTEGER | DEFAULT 0 | |
| `skipped_count` | INTEGER | DEFAULT 0 | |
| `error_count` | INTEGER | DEFAULT 0 | |
| `user_id` | INTEGER | | WP user ID |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

#### `area_postal_mapping` — Area geocoding data

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `area` | TEXT | NOT NULL UNIQUE | Area name |
| `postal_code` | TEXT | NOT NULL | |
| `state` | TEXT | | |
| `latitude` | REAL | | |
| `longitude` | REAL | | |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

#### `saved_titles` / `saved_areas` — Reusable input lists

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `name` | TEXT | NOT NULL | List name |
| `content` | TEXT | NOT NULL | Newline-separated values |
| `category` | TEXT | DEFAULT '' | Grouping label |
| `subcategory` | TEXT | DEFAULT '' | Sub-grouping |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

**Indexes:** `idx_saved_titles_name(name)`, `idx_saved_areas_name(name)`

### Backup & Restore (Danger Zone)

| Operation | AJAX Action |
|-----------|-------------|
| Download .db | `cg_download_database` |
| Upload & replace .db | `cg_restore_database` |
| Reset (with confirmation) | `cg_reset_database` |

File validation on restore: extension must be `.db`, `.sqlite`, or `.sqlite3`; file must pass `SQLite3::query('SELECT 1')`.

---

## 6. AI Integration

### Purpose

AI providers generate category descriptions and meta content when manual template authoring is insufficient. Users configure a provider, select a model, and the plugin sends prompts with category context to receive AI-generated content.

### Supported Providers

| Provider | Constant | Default Endpoint | Models |
|----------|----------|-----------------|--------|
| OpenAI | `openai` | `api.openai.com/v1/chat/completions` | GPT-5, GPT-4o, GPT-4o Mini, GPT-4 Turbo |
| Google Gemini | `gemini` | `generativelanguage.googleapis.com/v1beta/models` | Gemini 2.5 Pro, 2.5 Flash, 1.5 Pro |
| xAI Grok | `grok` | `api.x.ai/v1/chat/completions` | Grok 2, Grok Beta |
| DeepSeek | `deepseek` | `api.deepseek.com/v1/chat/completions` | DeepSeek Chat, DeepSeek Coder |
| Anthropic Claude | `claude` | `api.anthropic.com/v1/messages` | Claude Sonnet 4.5, Claude 3.5 Sonnet |
| Custom | `custom` | User-defined | User-defined |

### Configuration (Settings Keys)

| Key | Default | Description |
|-----|---------|-------------|
| `ai_provider` | `openai` | Active provider |
| `ai_model` | `gpt-4o-mini` | Default model |
| `ai_api_key` | `''` | Provider API key |
| `ai_api_url` | `''` | Override endpoint URL |
| `ai_html_model` | `gpt-4o` | Model for HTML content generation |
| `ai_meta_model` | `gpt-4o-mini` | Model for meta description generation |
| `custom_ai_url` | `''` | Custom provider endpoint |
| `custom_ai_token` | `''` | Custom provider auth token |
| `custom_ai_model` | `''` | Custom provider model name |

### Dual-Model Architecture

The plugin uses **two separate models** for different content types:
- **HTML model** (`ai_html_model`): Larger model for rich HTML descriptions (default: GPT-4o)
- **Meta model** (`ai_meta_model`): Smaller/faster model for short meta descriptions (default: GPT-4o Mini)

---

## 7. Yoast SEO Integration

### Field Mapping

When generating categories, the plugin writes to **Yoast meta fields** on each term:

| WP Meta Key | Source | Description |
|-------------|--------|-------------|
| `_yoast_wpseo_title` | `meta_title_pattern` from Meta Template | SEO title tag |
| `_yoast_wpseo_metadesc` | `meta_description_pattern` from Meta Template | SEO meta description |
| `_yoast_wpseo_focuskw` | `focus_keyword_pattern` (default: `{title} {area}`) | Yoast focus keyword |

### Meta Template Variations

Templates support **variation arrays** for A/B-style diversity:
- `meta_title_variations` — JSON array of alternative title patterns; one picked per category
- `meta_description_variations` — JSON array of alternative description patterns

### Yoast Data Read (Inbound)

`CG_Settings::get_yoast_data()` reads existing Yoast configuration for schema enrichment:

| Source | Fields Read |
|--------|------------|
| `wpseo_titles` option | `company_name` |
| `wpseo_social` option | `facebook_site`, `twitter_site`, `instagram_url`, `linkedin_url`, `youtube_url`, `pinterest_url` |
| Yoast Local SEO (`WPSEO_Local_Core`) | `street`, `city`, `state`, `postal_code`, `country`, `phone`, `email` |
| Theme / Site | `custom_logo`, `site_icon`, `bloginfo('name')`, `site_url` |

### Graceful Degradation

If Yoast SEO is **not installed**: `get_yoast_data()` returns `is_active: false`; meta templates are still saved but no WP term meta is written. No errors thrown.

---

## 8. Import / Export

### Export Flow

```
User selects types + format → export() → ZIP created → download
```

### Export Formats

| Format | ZIP Contents | Description |
|--------|-------------|-------------|
| SQLite | `manifest.json` + `category_generator.db` | Full database copy |
| CSV | `manifest.json` + `{type}.csv` per selected type | One CSV per data type |

### ZIP Manifest (`manifest.json`)

```json
{
  "version": "2.3.0",
  "exported_at": "2026-04-02 14:30:00",
  "format": "sqlite",
  "types": ["html_templates", "meta_templates", "variables"],
  "site_url": "https://example.com"
}
```

### Exportable Data Types

| Constant | Label | Source Table |
|----------|-------|-------------|
| `html_templates` | HTML Templates | `html_templates` |
| `meta_templates` | Meta Templates | `meta_templates` |
| `schema_templates` | Schema Templates | `schema_templates` |
| `inner_templates` | Inner Templates | `inner_templates` |
| `business_profiles` | Business Profiles | `business_profile` |
| `variables` | Variables | `variables` |
| `category_history` | Category History | `category_history` |
| `settings` | Settings | `settings` |
| `all` | Everything | All above |

### Import Flow

```
User uploads file → detect format by extension → import() → results summary
```

### Supported Import Formats

| Extension | Handler | Notes |
|-----------|---------|-------|
| `.zip` | `import_from_zip()` | Extracts, reads manifest, processes .db or .csv files inside |
| `.csv` | `import_from_csv()` | Headers = column names; `\n` escaped as `\\n` |
| `.db` / `.sqlite` | `import_from_sqlite()` | Opens as read-only, iterates tables |
| `.xml` | `import_from_xml()` | `simplexml_load_file()`, type nodes → record nodes |

### Import Options

| Option | Default | Description |
|--------|---------|-------------|
| `update_existing` | `false` | If true, overwrites records matched by name |
| `types` | `[]` (all) | Filter to specific data types |

### Import Result Structure

```json
{
  "success": true,
  "imported": ["Template A", "Template B"],
  "updated": ["Template C"],
  "skipped": ["Template D"],
  "errors": []
}
```

### CSV Column Format

Each CSV uses the **exact SQLite column names** as headers. Multi-line content has `\n` escaped to `\\n`. Example for `html_templates.csv`:

```csv
name,description,content,category,is_default,is_faq,faq_schema,template_group,created_at,updated_at
"My Template","A desc","<div>{title} in {area}</div>","",0,0,"","","2026-01-01","2026-01-01"
```

### Export Storage

```
wp-content/uploads/category-generator/exports/cg_export_YYYY-MM-DD_HH-mm-ss.zip
```

---

## 9. Remote Template APIs

### Purpose

Import templates from external servers (e.g., a central template library shared across multiple WordPress sites). This enables organizations to maintain a single source of truth for HTML, Meta, and Schema templates and distribute them across sites.

### Architecture

```
┌──────────────────────────────────────────────────────┐
│              SETTINGS → REMOTE TAB                    │
│  ┌─────────────────────────────────────────────────┐ │
│  │ API List                                         │ │
│  │  ┌─────────────────────────────────────────┐    │ │
│  │  │ Company Templates   [Fetch] [Remove]    │    │ │
│  │  │ URL: https://...    Enabled: ✓          │    │ │
│  │  └─────────────────────────────────────────┘    │ │
│  │  ┌─────────────────────────────────────────┐    │ │
│  │  │ Partner Templates   [Fetch] [Remove]    │    │ │
│  │  │ URL: https://...    Enabled: ✗          │    │ │
│  │  └─────────────────────────────────────────┘    │ │
│  │                                                  │ │
│  │  [+ Add New API]                                 │ │
│  └─────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────┘
```

### Storage

Stored as a **JSON array** in the `settings` table under key `remote_template_apis`:

```json
[
  {
    "id": "api_64f2a1b3c",
    "name": "Company Template Server",
    "url": "https://templates.example.com/api/templates",
    "api_key": "sk-abc123...",
    "oauth_token": "",
    "enabled": true
  }
]
```

### API Entry Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Auto | `uniqid('api_')` — unique identifier |
| `name` | string | Yes | Display name in UI |
| `url` | string | Yes | GET endpoint URL |
| `api_key` | string | No | Bearer token for Authorization header |
| `oauth_token` | string | No | OAuth token (alternative auth) |
| `enabled` | boolean | Yes | Toggle for active/inactive |

### Fetch Flow (Step by Step)

```
┌──────────┐    ┌────────────────┐    ┌───────────────┐    ┌──────────────┐
│ User     │    │ CG_Settings    │    │ wp_remote_get │    │ Remote       │
│ clicks   │───▶│ fetch_remote_  │───▶│ (30s timeout) │───▶│ Server       │
│ [Fetch]  │    │ templates()    │    │               │    │              │
└──────────┘    └────────────────┘    └───────────────┘    └──────────────┘
                       │                                          │
                       │◀─────────── JSON array response ─────────│
                       │
                       ▼
              ┌─────────────────┐
              │ Validate:       │
              │ • Is array?     │
              │ • Not empty?    │
              └────────┬────────┘
                       │
              ┌────────▼────────┐
              │ Return          │
              │ { success: true │
              │   templates: [] │
              │ }               │
              └─────────────────┘
```

| Step | Component | Action | Output |
|------|-----------|--------|--------|
| 1 | UI | User clicks Fetch on an API entry | `$api_id` |
| 2 | `fetch_remote_templates($api_id)` | Looks up API config in stored JSON by ID | API config or error |
| 3 | Validation | Checks `$api` exists and `url` is non-empty | Error if missing |
| 4 | Headers | If `api_key` set: `Authorization: Bearer {api_key}` | Headers array |
| 5 | HTTP | `wp_remote_get($url, ['headers' => ..., 'timeout' => 30])` | WP response |
| 6 | Error check | `is_wp_error($response)` → return error message | — |
| 7 | Parse | `json_decode($body, true)` | Decoded data |
| 8 | Type check | `gettype($data) === 'array'` | Error if not array |
| 9 | Return | `['success' => true, 'templates' => $data]` | Templates array |

### Expected Remote Server Response

The remote server **must** return a JSON array of template objects. The plugin does not enforce a strict schema — it passes the raw array to the UI for the user to select and import.

**Recommended response format:**

```json
[
  {
    "name": "Service Area Landing",
    "type": "html",
    "content": "<div class=\"service\">{title} in {area}</div>",
    "category": "Landing Pages",
    "description": "Standard service-area landing page template"
  },
  {
    "name": "Local Business Meta",
    "type": "meta",
    "meta_title_pattern": "{Title} {Area} | {var:business_name}",
    "meta_description_pattern": "Professional {title} services in {area}.",
    "focus_keyword_pattern": "{title} {area}"
  },
  {
    "name": "LocalBusiness Schema",
    "type": "schema",
    "schema_type": "LocalBusiness",
    "schema_content": "{\"@context\":\"https://schema.org\",\"@type\":\"LocalBusiness\",...}"
  }
]
```

### Error Handling

| Condition | Response |
|-----------|----------|
| API ID not found in stored config | `{ success: false, message: "API not found or URL is empty" }` |
| URL is empty | `{ success: false, message: "API not found or URL is empty" }` |
| Network error / timeout (30s) | `{ success: false, message: "<WP_Error message>" }` |
| Non-JSON or non-array response | `{ success: false, message: "Invalid response from API" }` |
| HTTP 4xx/5xx | Body parsed; if not valid JSON array → error |

### CRUD Operations

| Operation | Method | Description |
|-----------|--------|-------------|
| **Add** | `add_remote_api($config)` | Appends to JSON array, auto-generates `id` via `uniqid('api_')` |
| **Remove** | `remove_remote_api($api_id)` | Filters out by ID, re-indexes with `array_values()` |
| **List** | `get_remote_apis()` | Decodes JSON, validates is array, returns `[]` on invalid |
| **Fetch** | `fetch_remote_templates($api_id)` | GET request, returns templates array |
| **Update** | Via `save_all()` | Full settings save includes serialized API list |

### Settings Tab UI

Managed in **Settings → Remote** tab (`templates/partials/settings-tab-remote.php`):

- **API list**: Each entry shows name, URL, enabled status, with Fetch and Remove buttons
- **Add form**: Name + URL + API Key + OAuth Token fields
- **Enable/Disable toggle**: Per-API, persisted in JSON
- **Fetch results**: Displayed inline after clicking Fetch, with option to import selected templates

### Security Considerations

| Concern | Mitigation |
|---------|------------|
| API key exposure | Keys stored in SQLite (server-side only), never sent to browser in plain text |
| SSRF risk | `wp_remote_get()` follows WP's HTTP API restrictions |
| Malicious content | Templates are HTML; same sanitization as manually-entered templates applies on use |
| Timeout | 30-second hard timeout prevents hanging requests |

---

## 10. Admin Pages & Menu Structure

| Menu Item | Slug | Capability | Handler |
|-----------|------|------------|---------|
| Generate (top-level) | `category-generator` | `manage_categories` | `render_admin_page` |
| Snapshots | `cg-snapshots` | `manage_categories` | `render_snapshots_page` |
| History | `cg-history` | `manage_categories` | `render_history_page` |
| Templates | `cg-templates` | `manage_categories` | `render_templates_page` |
| Inner Templates | `cg-inner-templates` | `manage_categories` | `render_inner_templates_page` |
| Business Profile | `cg-business-profile` | `manage_categories` | `render_business_profile_page` |
| Settings | `cg-settings` | `manage_categories` | `render_settings_page` |
| Test Cases | `cg-tests` | `manage_categories` | `render_tests_page` |

---

## 11. UI Architecture

### Template Files

```
templates/
├── admin-page.php              ← Generate page
├── history-page.php            ← Category history
├── templates-page.php          ← Template manager
├── inner-templates-page.php    ← Inner templates
├── business-profile-page.php   ← Business profile
├── settings-page.php           ← Settings
├── tests-page.php              ← Test runner
├── snapshots-page.php          ← Snapshots manager
└── partials/
    ├── settings-tabs.php
    ├── settings-tab-general.php
    ├── settings-tab-classes.php
    ├── settings-tab-ai.php
    ├── settings-tab-remote.php
    ├── settings-tab-yoast.php
    ├── settings-tab-danger.php
    ├── settings-modals.php
    ├── settings-styles.php
    ├── settings-scripts.php
    ├── templates-tabs.php
    ├── templates-tab-html.php
    ├── templates-tab-meta.php
    ├── templates-tab-schema.php
    ├── templates-tab-categories.php
    ├── templates-modal-edit.php
    ├── templates-modal-category.php
    ├── templates-styles.php
    └── templates-scripts.php
```

### CSS & JS

- `assets/css/admin.css` — Plugin admin styles
- `assets/js/admin.js` — AJAX handlers, tab switching, preview, modals
- External: Font Awesome 5 (CDN)

### Design System Classes

Centralized in `CG_CSS` (static constants) and `CG_Constants` (spacing, sizing).

### Default Settings Reference

| Key | Default | Category |
|-----|---------|----------|
| `wrapper_class` | `riseup-category-generator` | CSS Classes |
| `header_class` | `category-header` | CSS Classes |
| `paragraph_class` | `seo-container-para` | CSS Classes |
| `schema_wrapper_class` | `category-schema-wrapper` | CSS Classes |
| `auto_save_templates` | `true` | General |
| `confirm_before_generate` | `true` | General |
| `default_business_profile_id` | `1` | Business |
| `use_dynamic_location` | `true` | Business |
| `yoast_use_default_title` | `false` | Yoast |
| `yoast_focus_keyword_pattern` | `{title} {area}` | Yoast |

---

## 12. Security

| Measure | Implementation |
|---------|---------------|
| Nonce verification | `check_ajax_referer('cg_nonce', 'nonce')` on all AJAX |
| Capability check | `manage_categories` (Editors + Admins) |
| Input sanitization | `sanitize_text_field()`, `intval()` on all inputs |
| Output escaping | `esc_attr()`, `esc_html()` in templates |
| Direct access guard | `if (!defined('ABSPATH')) exit;` on all PHP files |
| Snapshot directory | `.htaccess` deny + `index.php` silence |
| DB restore validation | Extension whitelist + SQLite3 query test |

---

## 13. File Structure

```
category-generator/
├── category-generator.php      ← Main plugin file (1823 lines)
├── .ai-instructions            ← AI coding rules (QUpload compat)
├── README.md                   ← User documentation
├── spec/
│   └── plugin-architecture.md  ← This document
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── includes/
│   ├── class-constants.php     ← CG_Constants (spacing, sizing)
│   ├── class-css.php           ← CG_CSS (class name constants)
│   ├── class-database.php      ← CG_Database (SQLite3, 1737 lines)
│   ├── class-import-export.php ← CG_Import_Export (677 lines)
│   ├── class-inner-templates.php ← CG_Inner_Templates (298 lines)
│   ├── class-settings.php      ← CG_Settings (385 lines)
│   ├── class-snapshot.php      ← CG_Snapshot (509 lines)
│   ├── class-tests.php         ← CG_Tests
│   └── class-variables.php     ← CG_Variables
└── templates/
    ├── *.php                   ← Page templates
    └── partials/*.php          ← Tab/modal/style/script partials
```

---

## 13.1 Business Profile System

The Business Profile provides structured company data used for Schema.org markup generation, placeholder resolution in templates, and dynamic location features during category generation.

### Multi-Profile Support

The plugin supports **multiple business profiles** stored in the `business_profile` SQLite table (see §5 schema). The table supports an `is_default` column, but the current `get_business_profile()` implementation simply returns the **first row** (`LIMIT 1`) when no `id` is specified.

#### AJAX Endpoints

| Action | Method | Description |
|--------|--------|-------------|
| `cg_get_business_profiles` | POST | List all profiles (id, business_name, is_default) via `get_all_business_profiles()` |
| `cg_get_business_profile` | POST | Full profile by `id`, or first profile if no `id` |
| `cg_save_business_profile` | POST | Upsert — updates first existing row, or inserts if none |
| `cg_delete_business_profile` | POST | Delete by `id` |

> **Note:** There is no dedicated `cg_set_default_profile` endpoint. The `is_default` column exists in the schema but is not actively managed by the current AJAX layer.

#### Profile Selection at Generation Time

1. If `profile_id` is sent in the generate request → use that profile via `get_business_profile($id)`.
2. Otherwise → `get_business_profile()` returns the first row (`SELECT * FROM business_profile LIMIT 1`).
3. If no rows exist → use an empty context (all business placeholders resolve to `""`).

### Schema.org JSON-LD Generation

The profile data maps directly to a `LocalBusiness` (or subtype) JSON-LD block injected into generated category descriptions when a Schema template is active.

#### Schema Type Mapping

The `business_type` field stores the Schema.org `@type` value directly (not a separate key). Available types are defined in `CG_Constants::get_business_types()`:

| business_type value | UI Label |
|---------------------|----------|
| `LocalBusiness` | Local Business |
| `ProfessionalService` | Professional Service |
| `HomeAndConstructionBusiness` | Home & Construction |
| `CleaningService` | Cleaning Service |
| `Plumber` | Plumber |
| `Electrician` | Electrician |
| `RealEstateAgent` | Real Estate Agent |
| `FinancialService` | Financial Service |
| `HealthAndBeautyBusiness` | Health & Beauty |
| `LegalService` | Legal Service |
| `Restaurant` | Restaurant |
| `Store` | Store |

#### Generated JSON-LD Structure

```json
{
  "@context": "https://schema.org",
  "@type": "{business_type}",
  "name": "{business_name}",
  "url": "{website}",
  "telephone": "{phone}",
  "email": "{email}",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "{street_address}",
    "addressLocality": "{city}",        // ← dynamic location override
    "addressRegion": "{state}",
    "postalCode": "{postal_code}",      // ← dynamic location override
    "addressCountry": "{country}"
  },
  "openingHours": ["{opening_hours}"],
  "priceRange": "{price_range}",
  "areaServed": ["{service_areas}"],
  "hasOfferCatalog": {
    "@type": "OfferCatalog",
    "name": "Services",
    "itemListElement": ["{services_offered}"]
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "{rating_value}",
    "reviewCount": "{rating_count}"
  },
  "logo": "{logo_url}",
  "image": "{image_url}",
  "sameAs": ["{social_profiles}"]
}
```

**Omission rule:** Any top-level property whose resolved value is empty (`""`, `[]`, `null`) is **omitted** from the output to produce valid, minimal JSON-LD.

### Dynamic Location Feature

When the setting `use_dynamic_location` is `true` (default), the Schema.org address fields are **overridden per generated category** using the current `{area}` value:

| JSON-LD Field | Static Value | Dynamic Override |
|---------------|-------------|-----------------|
| `addressLocality` | Profile `city` | Current `{area}` value |
| `postalCode` | Profile `postal_code` | Lookup from `area_postal_mapping` table |
| `areaServed` | Profile `service_areas` | Replaced with `["{area}"]` |

#### Postal Code Lookup Flow

```
1. Area name from current generation context   → e.g., "Werribee"
2. Query: SELECT postal_code FROM area_postal_mapping WHERE area = ?
3. If found → use mapped postal_code (e.g., "3030")
4. If not found → keep profile's postal_code as fallback
```

This ensures each generated category page gets location-specific Schema.org markup without manual editing.

### Business Profile Placeholder Mapping

Business profile fields use **direct `{field_name}` syntax** (no prefix) in HTML, Meta, and Schema templates. They share the same namespace as input placeholders like `{title}` and `{area}`.

#### HTML & Meta Template Placeholders

These are available in the HTML description and Yoast meta placeholder contexts:

| Placeholder | Source Field | Default Fallback | Available In |
|-------------|-------------|-----------------|--------------|
| `{business_name}` | `business_name` | `""` | HTML, Meta, Schema |
| `{business_type}` | `business_type` | `"LocalBusiness"` | HTML, Meta, Schema |
| `{phone}` | `phone` | `""` | HTML, Meta, Schema |
| `{email}` | `email` | `""` | HTML, Meta, Schema |
| `{website}` | `website` | `""` | HTML, Meta, Schema |
| `{contact_url}` | Derived | `home_url('/contact/')` | HTML, Meta |
| `{street_address}` | `street_address` | `""` | HTML, Meta, Schema |
| `{city}` | `city` | `""` | HTML, Meta, Schema |
| `{state}` | `state` | `""` | HTML, Meta, Schema |
| `{postal_code}` | `postal_code` | `""` | HTML, Meta, Schema |
| `{country}` | `country` | `"Australia"` | HTML, Meta, Schema |
| `{opening_hours}` | `opening_hours` | `""` / `"Mo-Fr 08:00-17:00"` | HTML, Schema |
| `{price_range}` | `price_range` | `""` / `"$$"` | HTML, Schema |
| `{rating_value}` | `rating_value` | `"5.0"` | HTML, Meta, Schema |
| `{rating_count}` | `rating_count` | `"100"` | HTML, Meta, Schema |
| `{logo_url}` | `logo_url` | `""` | HTML, Schema |
| `{image_url}` | `image_url` | `""` | HTML, Schema |
| `{latitude}` | Derived | `""` | Schema |
| `{longitude}` | Derived | `""` | Schema |

> **Note on `{contact_url}`:** Derived at generation time as `$business_profile['website']` if set, otherwise `home_url('/contact/')`.

> **Note on defaults:** Some placeholders have different fallback values depending on the template context (HTML vs Schema). The table shows both where they differ.

#### Fields Stored but Not Individually Mapped as Placeholders

The following fields exist in the `business_profiles` SQLite table and are used in Schema.org JSON-LD generation (assembled programmatically), but are **not** available as standalone `{field}` placeholders in templates:

| Field | Used In |
|-------|---------|
| `price_range_min` | Schema JSON-LD `priceRange` |
| `price_range_max` | Schema JSON-LD `priceRange` |
| `price_note` | Schema JSON-LD custom annotation |
| `service_areas` | Schema JSON-LD `areaServed` array |
| `services_offered` | Schema JSON-LD `hasOfferCatalog` |
| `social_profiles` | Schema JSON-LD `sameAs` array |

#### Dynamic Location Overrides

When `use_dynamic_location` is enabled, the `{city}` and `{postal_code}` placeholders are **overridden** per-category with the current area's values. No separate `{dynamic_city}` placeholder exists — the override is transparent:

| Placeholder | Static Mode | Dynamic Mode (`use_dynamic_location = true`) |
|-------------|------------|----------------------------------------------|
| `{city}` | Profile `city` | Current `{area}` value |
| `{postal_code}` | Profile `postal_code` | `area_postal_mapping` lookup (fallback: profile value) |

#### Resolution Order in Generation Pipeline

```
Step 1: Load business profile (default or specified)
Step 2: Build placeholder context map from profile fields
Step 3: If use_dynamic_location → override {city}/{postal_code} with area data
Step 4: Resolve {var:*} placeholders (Variables system)
Step 5: Resolve business profile placeholders ({business_name}, {phone}, etc.)
Step 6: Resolve {title}, {area}, {slug}, {url}, and other input placeholders
Step 7: Resolve {inner:*} placeholders (Inner Templates)
Step 8: Final output
```

### UI Form Sections

The Business Profile admin page (`templates/business-profile-page.php`) groups fields into six cards:

1. **Basic Information** — business_name, business_type (dropdown), website
2. **Contact Information** — phone, email
3. **Address** — street_address, city, state, postal_code, country
4. **Business Details** — opening_hours, price_range (dropdown), service_areas, services_offered
5. **Ratings & Reviews** — rating_value (step 0.1, range 0–5), rating_count
6. **Media** — logo_url, image_url, social_profiles (one URL per line)

The form submits via AJAX to `cg_save_business_profile` with a single serialized payload.

---

## 13.3 Area Postal Mapping

The `area_postal_mapping` table provides geocoding data that links area names to postal codes, states, and coordinates. This data powers the **Dynamic Location** feature (§13.1) by overriding Schema.org address fields per generated category.

### SQLite Schema

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `area` | TEXT | NOT NULL UNIQUE | Area name (must match generation input exactly) |
| `postal_code` | TEXT | NOT NULL | e.g., `"3030"` |
| `state` | TEXT | | e.g., `"VIC"` |
| `latitude` | REAL | | Decimal latitude |
| `longitude` | REAL | | Decimal longitude |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

**Unique constraint on `area`** — each area name maps to exactly one postal code. Saving a duplicate area performs an **upsert** (update existing row).

### Database API

The `CG_Database` class exposes three methods:

| Method | Signature | Behaviour |
|--------|-----------|-----------|
| `save_area_postal` | `($area, $postal_code, $state='', $lat=null, $lng=null)` | Upsert — checks `SELECT id` first, then `UPDATE` or `INSERT` |
| `get_area_postal` | `($area)` | Returns single row as associative array, or `false`/`null` if not found |
| `get_all_area_postals` | `()` | Returns all rows ordered by `area ASC` |

> **Note:** There are currently **no dedicated AJAX endpoints** for area postal mapping. Data is managed through the `CG_Database` API, populated via import operations or programmatic calls during generation setup.

### Data Population Methods

#### 1. Manual via `save_area_postal()`

Called programmatically to seed or update individual mappings:

```php
$db = CG_Database::get_instance();
$db->save_area_postal('Werribee', '3030', 'VIC', -37.8986, 144.6631);
$db->save_area_postal('Point Cook', '3030', 'VIC', -37.9202, 144.7474);
$db->save_area_postal('Melbourne CBD', '3000', 'VIC', -37.8136, 144.9631);
```

#### 2. Bulk via Import/Export System

The `area_postal_mapping` table is included in the plugin's import/export scope (`CG_Import_Export`). When importing a `.db` or ZIP backup, any `area_postal_mapping` records are restored alongside other plugin data.

#### 3. During Generation (Auto-populate)

When generating categories with `use_dynamic_location` enabled, the system queries `area_postal_mapping` for each area. If not found, it falls back to the business profile's static postal code — but does **not** auto-create missing mappings.

### Lookup Fallback Logic

During category generation with `use_dynamic_location = true`:

```
┌─────────────────────────────────────────┐
│ Current area from generation context    │
│ e.g., "Werribee"                        │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ Query: get_area_postal("Werribee")      │
└──────┬──────────────────┬───────────────┘
       │ Found             │ Not Found
       ▼                   ▼
┌──────────────┐   ┌──────────────────────┐
│ Use mapped   │   │ Fall back to         │
│ postal_code  │   │ business_profile     │
│ "3030"       │   │ postal_code field    │
│              │   │ (e.g., "3000")       │
│ Use mapped   │   │                      │
│ state "VIC"  │   │ Keep profile state   │
└──────────────┘   └──────────────────────┘
       │                   │
       ▼                   ▼
┌─────────────────────────────────────────┐
│ Override Schema.org JSON-LD fields:     │
│ • addressLocality = current {area}      │
│ • postalCode = resolved postal code     │
│ • areaServed = [current {area}]         │
└─────────────────────────────────────────┘
```

### Interaction with Placeholders

| Placeholder | Without mapping | With mapping |
|-------------|----------------|--------------|
| `{city}` | Overridden to current `{area}` | Same — always `{area}` |
| `{postal_code}` | Business profile value | Mapped `postal_code` from table |
| `{latitude}` | `""` (empty) | Mapped `latitude` if present |
| `{longitude}` | `""` (empty) | Mapped `longitude` if present |

### Area Name Matching

Lookups use **exact string match** (`WHERE area = :area`). The area value from the generation input must match the stored `area` column precisely — matching is **case-sensitive** and **whitespace-sensitive**. No normalisation is applied.

> **Recommendation:** When populating the mapping table, use area names identical to those entered in the generation Titles/Areas input fields.

---

## 13.4 Saved Titles & Areas

The plugin provides a reusable list system for storing frequently-used title sets and area sets. Users can save, load, update, and delete named lists — then populate the Generate page's input textareas with a single dropdown selection.

### Purpose

Instead of retyping or pasting the same title/area combinations across generation runs, users save them as named presets. Each list stores its content as **newline-separated values** (one title or area per line), matching the format of the generation input textareas.

### SQLite Schema

Both `saved_titles` and `saved_areas` share an identical schema:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PK AUTOINCREMENT | |
| `name` | TEXT | NOT NULL | Display name for the list |
| `content` | TEXT | NOT NULL | Newline-separated values |
| `category` | TEXT | DEFAULT `''` | Grouping label (for future optgroup UI) |
| `subcategory` | TEXT | DEFAULT `''` | Sub-grouping label |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

**Indexes:** `idx_saved_titles_name(name)`, `idx_saved_areas_name(name)`

**Ordering:** Both tables are queried with `ORDER BY category ASC, name ASC`, grouping by category first.

### Category & Subcategory Grouping

The `category` and `subcategory` columns allow logical grouping of saved lists:

```
category: "Cleaning"
├── subcategory: "Commercial"
│   ├── "Office Cleaning Titles"
│   └── "Industrial Cleaning Titles"
├── subcategory: "Residential"
│   └── "Home Cleaning Titles"
category: "Property"
├── "Real Estate Titles"
└── "Property Management Titles"
```

Currently the UI renders a flat `<select>` dropdown sorted by category + name. The grouping fields are stored but not yet surfaced as `<optgroup>` elements.

### AJAX Endpoints

| Action | Method | Parameters | Response |
|--------|--------|-----------|----------|
| `cg_save_titles` | POST | `nonce`, `id` (0=new), `name`, `content` | `{ success: true, data: { id } }` |
| `cg_save_areas` | POST | `nonce`, `id` (0=new), `name`, `content` | `{ success: true, data: { id } }` |
| `cg_get_saved_titles` | POST | `nonce`, `id` | `{ success: true, data: { ...row } }` |
| `cg_get_saved_areas` | POST | `nonce`, `id` | `{ success: true, data: { ...row } }` |

#### Save Logic (Upsert Pattern)

```
if id > 0 → update_saved_titles(id, name, content)
else      → save_titles(name, content) → returns new id
```

Same pattern for areas. The `category` and `subcategory` fields are accepted by the database methods but are **not currently passed** from the AJAX handlers.

### Database API

| Method | Table | Description |
|--------|-------|-------------|
| `save_titles($name, $content, $category, $subcategory)` | `saved_titles` | INSERT, returns `lastInsertRowID()` |
| `update_saved_titles($id, $name, $content, $category, $subcategory)` | `saved_titles` | UPDATE by id, sets `updated_at` |
| `delete_saved_titles($id)` | `saved_titles` | DELETE by id |
| `get_saved_titles()` | `saved_titles` | All rows, ordered by category + name |
| `get_saved_titles_item($id)` | `saved_titles` | Single row by id |
| `save_areas(...)` | `saved_areas` | Same as above, mirrored |
| `update_saved_areas(...)` | `saved_areas` | Same as above, mirrored |
| `delete_saved_areas(...)` | `saved_areas` | Same as above, mirrored |
| `get_saved_areas()` | `saved_areas` | Same as above, mirrored |
| `get_saved_areas_item($id)` | `saved_areas` | Same as above, mirrored |

### UI Integration (Generate Page)

The saved lists are surfaced on `templates/admin-page.php` as dropdown selectors above the Titles and Areas textareas:

```
┌──────────────────────────────────────────┐
│ Titles                                    │
│ ┌──────────────────────────────────────┐ │
│ │ — Load saved titles —            ▾   │ │
│ └──────────────────────────────────────┘ │
│ ┌──────────────────────────────────────┐ │
│ │ Commercial Cleaning                  │ │
│ │ Office Cleaning                      │ │
│ │ Carpet Cleaning                      │ │
│ └──────────────────────────────────────┘ │
├──────────────────────────────────────────┤
│ Areas                                     │
│ ┌──────────────────────────────────────┐ │
│ │ — Load saved areas —             ▾   │ │
│ └──────────────────────────────────────┘ │
│ ┌──────────────────────────────────────┐ │
│ │ Werribee                             │ │
│ │ Point Cook                           │ │
│ │ Tarneit                              │ │
│ └──────────────────────────────────────┘ │
└──────────────────────────────────────────┘
```

#### Load Flow

1. User selects a saved list from the `<select>` dropdown
2. JS fires AJAX `cg_get_saved_titles` (or `cg_get_saved_areas`) with the selected `id`
3. Response contains the `content` field (newline-separated)
4. JS populates the corresponding `<textarea>` with the content

#### Save Flow

1. User types or edits titles/areas in the textarea
2. User clicks "Save" and provides a name
3. JS fires AJAX `cg_save_titles` (or `cg_save_areas`) with `id=0` for new, or existing `id` for update
4. Dropdown is refreshed with the new/updated entry

### Content Format

Content is stored as raw newline-separated text, matching the generation input format:

```
Commercial Cleaning
Office Cleaning
Carpet Cleaning
Window Cleaning
```

Empty lines and whitespace-only lines are preserved in storage but filtered out during generation parsing (see §3 Generation Pipeline, Area List Parsing).

---

## 13.2 Built-in Test Runner


The plugin ships with a comprehensive in-admin test suite (`CG_Tests`, 1777 lines) that validates all subsystems without requiring PHPUnit or WP-CLI. Tests run inside the WordPress runtime with full access to the SQLite database, WordPress APIs, and plugin services.

### Architecture

```
┌─────────────────────────────────────┐
│         tests-page.php (UI)         │
│  [Run All Tests ▾] [Group Select]   │
│  [Download PHPUnit Tests]           │
└──────────────┬──────────────────────┘
               │ AJAX POST
               ▼
┌─────────────────────────────────────┐
│  wp_ajax_cg_run_tests               │
│  ├─ group = "all" → run_all_tests() │
│  └─ group = "xxx" → run_test_group()│
└──────────────┬──────────────────────┘
               ▼
┌─────────────────────────────────────┐
│  CG_Tests (Singleton)               │
│  ├── run_test($name, $callback)     │
│  │   ├── true/null  → passed        │
│  │   ├── false      → "returned false"│
│  │   ├── string     → failed + msg  │
│  │   └── Exception  → failed + msg  │
│  └── Timing: microtime(true) per test│
└─────────────────────────────────────┘
```

### AJAX Endpoint

| Action | Method | Parameters | Response |
|--------|--------|-----------|----------|
| `cg_run_tests` | POST | `nonce`, `group` (default `"all"`) | JSON result object |

### Response Format

```json
{
  "success": true,
  "data": {
    "total": 72,
    "passed": 71,
    "failed": 1,
    "tests": [
      {
        "name": "Database Connection",
        "status": "passed",
        "message": "",
        "time": 0.12
      },
      {
        "name": "Meta Title Length",
        "status": "failed",
        "message": "Title exceeds 60 chars: 'Very Long Title...'",
        "time": 0.45
      }
    ]
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `total` | int | Total tests executed |
| `passed` | int | Count of passed tests |
| `failed` | int | Count of failed tests |
| `tests[].name` | string | Human-readable test name |
| `tests[].status` | string | `"passed"` or `"failed"` |
| `tests[].message` | string | Empty on pass; error detail on fail |
| `tests[].time` | float | Execution time in milliseconds |

### Test Groups & Scenarios

Tests are organized into 13 groups, selectable from the admin UI dropdown or via the `group` AJAX parameter.

#### `database` — SQLite Foundation (6 tests)

| Test | Validates |
|------|-----------|
| Database Connection | `CG_Database::get_instance()` returns non-null and `is_connected()` is true |
| Table Creation | All required tables exist: `category_history`, `html_templates`, `meta_templates`, `schema_templates`, `business_profile` |
| Database Insert | `insert_html_template()` returns a valid ID |
| Database Update | `update_html_template()` persists changed `description` field |
| Database Delete | `delete_html_template()` removes the record; subsequent `get` returns null/false |
| Database Transaction | Two sequential inserts both succeed (IDs are truthy) |

> All database tests perform **self-cleanup** — test records are deleted after assertions.

#### `variables` — Variable System (7 tests)

| Test | Validates |
|------|-----------|
| Variable Basic | `compile_variables()` passes through context values unchanged |
| Variable Concatenation | `parse_expression('"Hello" + " " + "World"')` → `"Hello World"` |
| Variable Reference | `{var:base}` resolves to the value of `base` in context |
| Variable Nested Reference | `{var:level2}` → `{var:level1}B` → `"AB"`, chain resolves to `"ABC"` |
| Variable Math Operations | `parse_expression('5 + 3')` → `8` |
| Variable Empty Handling | Empty string and null values compile without errors |
| Variable Special Chars | `& " < >` characters survive compilation intact |

#### `templates` — Inner Templates & HTML Templates (6 tests)

| Test | Validates |
|------|-----------|
| Inner Template Create | `save_template()` returns ID > 0 |
| Inner Template Process | `process_content('Before {title} After', ['title' => 'TEST'])` contains `"TEST"` |
| HTML Template Save | Insert + retrieve round-trip |
| Meta Template Save | Meta template insert returns valid ID |
| Placeholder Replacement | All `{title}`, `{area}`, `{business_name}` placeholders resolve |
| Template Category Hierarchy | Parent/child category assignment validates correctly |

#### `categories` — Generation Logic (5 tests)

| Test | Validates |
|------|-----------|
| Category Name Format | Title + Area produce correct combined name |
| Slug Generation | Name → slug transformation (lowercase, hyphens) |
| Parent/Child Logic | Child categories reference correct parent term ID |
| (S) Notation Parsing | `"Cleaning(S)"` expands to both singular and plural forms |
| Area List Parsing | Newline-separated area text parses into correct array |

#### `yoast` — SEO Integration (5 tests)

| Test | Validates |
|------|-----------|
| Yoast Meta Generation | `_yoast_wpseo_metadesc` key populated correctly |
| Focus Keyword Generation | Pattern `{title} {area}` resolves to actual values |
| Meta Title Length | Generated title ≤ 60 characters |
| Meta Description Min Length | Generated description meets minimum length |
| Yoast Score Thresholds | Score class constants map to correct CSS classes |

#### `saved` — Saved Titles & Areas (4 tests)

| Test | Validates |
|------|-----------|
| Saved Titles Create | Insert into `saved_titles` returns valid ID |
| Saved Titles Retrieve | Retrieved record matches inserted content |
| Saved Areas Create | Insert into `saved_areas` returns valid ID |
| Saved Areas Retrieve | Retrieved record matches inserted content |

#### `snapshots` — Backup System (3 tests)

| Test | Validates |
|------|-----------|
| Snapshot Create | Snapshot file created at expected path |
| Snapshot Types | Both `"manual"` and `"auto"` types persist correctly |
| Snapshot Limit Enforcement | Old snapshots pruned when count exceeds configured maximum |

#### `utility` — Helpers & Constants (7 tests)

| Test | Validates |
|------|-----------|
| String Sanitization | `sanitize_text_field()` strips unsafe content |
| Array Filtering | Empty values removed from arrays |
| Empty Input Handling | Null/empty inputs produce safe defaults |
| Unicode Support | Multi-byte characters (CJK, emoji) survive round-trip |
| Date Format Constants | `CG_Constants` date format values are valid |
| Spacing Constants | Spacing pixel values are positive integers |
| Icon Size Constants | Icon dimensions are within expected range |

#### `ajax` — Handler Validation (6 tests)

| Test | Validates |
|------|-----------|
| AJAX Actions Defined | All `wp_ajax_cg_*` hooks are registered |
| AJAX Nonce Validation | Requests without valid nonce are rejected |
| AJAX Response Format | All handlers return `wp_send_json_success/error` format |
| AJAX Save Template Handler | Template save round-trip via handler |
| AJAX Get Template Handler | Template retrieval returns expected structure |
| AJAX Snapshot Handler | Snapshot create/list via AJAX works correctly |

#### `javascript` — JS Data Contract (5 tests)

| Test | Validates |
|------|-----------|
| JS Constants Export | `wp_localize_script()` passes constants to `cgAdmin` |
| JS CSS Classes Export | CSS class constants available in JS context |
| JS DOM Element IDs | Expected element IDs referenced in JS match template output |
| JS Localized Strings | i18n strings passed to JS are non-empty |
| JS Template Type Validation | Template types (`html`, `meta`, `schema`) validated client-side |

#### `validation` — Security (3 tests)

| Test | Validates |
|------|-----------|
| Input XSS Prevention | `<script>alert(1)</script>` stripped from saved data |
| Input SQL Injection Prevention | SQL fragments (`'; DROP TABLE`) neutralized by parameterized queries |
| Input Max Length Validation | Oversized inputs truncated or rejected |

#### Unlisted Groups (run in "All Tests" only)

| Group | Tests | Coverage |
|-------|-------|----------|
| Inner Template extended | Inner Template By ID, By Name, Nested, With Variables | `{inner:id}` and `{inner:name}` resolution + nesting |
| Import/Export | CSV Export, CSV Escaping, Import Validation, JSON Export, Import Merge | Round-trip data integrity |
| HTML Wrappers | Div Wrapper, Schema in Div, Class Names, Custom Classes, HTML Sanitization | Output HTML structure |
| Business Profile | Profile Save, Update, Multiple Profiles, Area Postal Mapping, Profile Schema | Multi-profile + Schema.org output |
| Settings | Settings Save/Load, Defaults, AI Provider Config, CSS Class Settings | Configuration persistence |
| Constants | Constants Defined, CSS Class Constants, Filesize Formatter, Yoast Score Classes | `CG_Constants` + `CG_CSS` coverage |
| Remote API | Remote API URL Validation | URL format check for external template server |

### Test Runner Behaviour

- **Singleton pattern:** `CG_Tests::get_instance()` — one instance per request.
- **Database pre-check:** If `CG_Database` is not connected, all tests are skipped and a single `"Database Connection"` failure is returned.
- **Self-cleaning:** All tests that create records (templates, variables, profiles) delete them after assertions.
- **Timing:** Each test is individually timed via `microtime(true)` — `time` field is in **milliseconds**.
- **Pass semantics:** `true` or `null` return = pass. `false` = generic fail. String return = fail with that string as message. Thrown `Exception` = fail with exception message.

### Admin UI

The test page (`templates/tests-page.php`) provides:

1. **Run All Tests** button — executes every test across all groups
2. **Group dropdown** — 11 selectable groups (database, variables, templates, categories, yoast, saved, snapshots, utility, ajax, javascript, validation)
3. **Download PHPUnit Tests** button — exports the test suite as a PHPUnit-compatible file for CI integration
4. **Results panel** — colour-coded pass/fail rows with test name, status badge, message (on failure), and execution time

---

## 14. QUpload Compatibility Rules

See `.ai-instructions` for full details. Key constraints:

- **Never** use `is_array()`, `is_string()`, `is_int()`, etc.
- **Always** use `gettype($var) === 'array'` pattern
- **Never** use `array()` — use `[]` short syntax
- `in_array()`, `array_merge()`, `array_filter()`, etc. are **safe**

---

## 15. Version Policy

- Any code change bumps **at least minor version**
- Version defined in: plugin header + `CG_PLUGIN_VERSION` constant
- Changelog maintained in `README.md`

---

© 2024 Riseup Asia LLC. All rights reserved.
