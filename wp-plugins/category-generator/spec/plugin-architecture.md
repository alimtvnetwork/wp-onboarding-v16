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

### Core Tables

| Table | Purpose |
|-------|---------|
| `templates` | HTML/Meta/Schema templates with category_id |
| `template_categories` | 3-level hierarchy for organizing templates |
| `inner_templates` | Reusable content blocks |
| `variables` | Dynamic key-value placeholders |
| `business_profiles` | Local Business schema data |
| `category_history` | Audit trail of generated categories |
| `settings` | Plugin configuration (key-value) |
| `import_history` | Import operation log |

### Backup & Restore (Danger Zone)

| Operation | AJAX Action |
|-----------|-------------|
| Download .db | `cg_download_database` |
| Upload & replace .db | `cg_restore_database` |
| Reset (with confirmation) | `cg_reset_database` |

File validation on restore: extension must be `.db`, `.sqlite`, or `.sqlite3`; file must pass `SQLite3::query('SELECT 1')`.

---

## 6. AI Integration

### Supported Providers

| Provider | Constant | Default Endpoint |
|----------|----------|-----------------|
| OpenAI | `openai` | `api.openai.com/v1/chat/completions` |
| Google Gemini | `gemini` | `generativelanguage.googleapis.com/v1beta/models` |
| Grok | `grok` | Custom |
| DeepSeek | `deepseek` | Custom |
| Claude | `claude` | Custom |
| Custom | `custom` | User-defined |

### Configuration

Per-provider: API key, model selection, endpoint URL.  
Stored in SQLite `settings` table.

---

## 7. Yoast SEO Integration

- Meta templates for SEO descriptions
- Schema templates for JSON-LD structured data
- Yoast data accessible via `CG_Settings::get_yoast_data()`
- Dedicated Settings → Yoast tab

---

## 8. Import / Export

### Formats

| Format | Export | Import |
|--------|--------|--------|
| ZIP (full) | ✅ | ✅ |
| CSV | ✅ | ✅ |
| SQLite (.db) | ✅ | ✅ |

### History

All imports logged in `import_history` table with timestamp, format, and result.

---

## 9. Admin Pages & Menu Structure

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

## 10. UI Architecture

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

---

## 11. Security

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

## 12. File Structure

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
│   ├── class-import-export.php ← CG_Import_Export
│   ├── class-inner-templates.php ← CG_Inner_Templates
│   ├── class-settings.php      ← CG_Settings (AI, Remote, Yoast)
│   ├── class-snapshot.php      ← CG_Snapshot (509 lines)
│   ├── class-tests.php         ← CG_Tests
│   └── class-variables.php     ← CG_Variables
└── templates/
    ├── *.php                   ← Page templates
    └── partials/*.php          ← Tab/modal/style/script partials
```

---

## 13. QUpload Compatibility Rules

See `.ai-instructions` for full details. Key constraints:

- **Never** use `is_array()`, `is_string()`, `is_int()`, etc.
- **Always** use `gettype($var) === 'array'` pattern
- **Never** use `array()` — use `[]` short syntax
- `in_array()`, `array_merge()`, `array_filter()`, etc. are **safe**

---

## 14. Version Policy

- Any code change bumps **at least minor version**
- Version defined in: plugin header + `CG_PLUGIN_VERSION` constant
- Changelog maintained in `README.md`

---

© 2024 Riseup Asia LLC. All rights reserved.
