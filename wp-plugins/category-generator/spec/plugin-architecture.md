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

### Placeholder System

| Placeholder | Value | Example |
|-------------|-------|---------|
| `{title}` | Raw title | `plumbing` |
| `{area}` | Raw area | `downtown` |
| `{category}` | `{title} {area}` | `plumbing downtown` |
| `{TITLE}` | UPPERCASE | `PLUMBING` |
| `{AREA}` | UPPERCASE | `DOWNTOWN` |
| `{Title}` | Title Case | `Plumbing` |
| `{Area}` | Title Case | `Downtown` |
| `{inner:ID}` | Inner template by ID | Resolved recursively |
| `{inner:name}` | Inner template by name | Resolved recursively |
| `{var:key}` | Variable value | From variables table |

### Options

- **Taxonomy**: Default `category` or any registered custom taxonomy
- **Parent category**: Assign all generated terms under a parent
- **Auto-snapshot**: Creates snapshot before generation (if enabled)

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

Inner templates support the same placeholder system and can be injected into existing category descriptions via the History page inject UI.

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

Import templates from external servers (e.g., a central template library shared across WordPress sites).

### Configuration

Stored as JSON array in `settings` table under key `remote_template_apis`:

```json
[
  {
    "id": "api_abc123",
    "name": "Company Template Server",
    "url": "https://templates.example.com/api/templates",
    "api_key": "Bearer token",
    "oauth_token": "",
    "enabled": true
  }
]
```

### Fetch Flow

1. `fetch_remote_templates($api_id)` called
2. Resolves API config from stored JSON
3. `wp_remote_get()` with `Authorization: Bearer {api_key}` header, 30s timeout
4. Expects JSON array response
5. Returns `{ success: true, templates: [...] }` or error

### Settings Tab

Managed in **Settings → Remote** tab. Operations: add, remove, toggle enable/disable.

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
