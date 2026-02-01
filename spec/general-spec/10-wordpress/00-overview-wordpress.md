# WordPress Plugin Development Guidelines - Overview

> **Version:** 1.0.0  
> **Purpose:** WordPress-specific coding guidelines for plugin development  
> **Documents:** 6 (01-06)  
> **Prerequisite:** Familiarity with `spec/general-spec/` foundation documents (01-foundation, 02-systems) recommended  
> **Last Updated:** 2026-01-27

---

## 1. What This Is

This folder contains **WordPress-specific coding guidelines** for plugin development. These guidelines build upon the foundation patterns in `spec/general-spec/` and adapt them to WordPress conventions, APIs, and best practices.

Use this folder when:
- Building a new WordPress plugin
- Refactoring an existing WordPress plugin
- Pairing with AI for WordPress development

---

## 2. How to Use With AI

### Option A: WordPress-Only (Quick Start)

```
I'm building a WordPress plugin. Please follow these guidelines:

[Paste contents of spec/general-spec/10-wordpress/ folder]

Now let's build: [your plugin description]
```

### Option B: Full Stack (Recommended)

```
PART 1 - FOUNDATION (read first):
[Paste spec/general-spec/01-foundation/ and spec/general-spec/02-systems/]

PART 2 - WORDPRESS SPECIFIC (read second):
[Paste spec/general-spec/10-wordpress/]

PART 3 - PROJECT REQUIREMENTS:
[Your plugin specification]
```

---

## 3. Document Index

| # | File | Description | Key Concepts |
|---|------|-------------|--------------|
| 01 | `01-plugin-structure-wordpress.md` | Plugin lifecycle, file organization | Activation, deactivation, PSR-4, uninstall |
| 02 | `02-rest-api-wordpress.md` | REST API integration | Nonce verification, permissions, response envelope |
| 03 | `03-cron-system-wordpress.md` | Background jobs, scheduling | WP-Cron, job registration, locks, external triggers |
| 04 | `04-admin-ui-wordpress.md` | Admin menus, settings pages | Asset enqueueing, notices, screen options, help tabs |
| 05 | `05-sanitization-wordpress.md` | Input/output security | Sanitize functions, escaping, validation, capabilities |
| 06 | `06-configuration-wordpress.md` | Settings management, seeding | 3-tier hierarchy, version-triggered seeding, Options API |

---

## 4. Core Principles

### 4.1 Security First

Every WordPress plugin MUST implement:

| Layer | Requirement | Document |
|-------|-------------|----------|
| Input | Sanitize ALL user input | 05-sanitization |
| Output | Escape ALL output by context | 05-sanitization |
| Auth | Verify nonces for all actions | 02-rest-api, 05-sanitization |
| Authz | Check capabilities before actions | 05-sanitization |

### 4.2 WordPress Functions Over Custom

✅ **Use WordPress functions:**
- `sanitize_text_field()` not custom regex
- `wp_kses_post()` not strip_tags
- `$wpdb->prepare()` not string concatenation
- `wp_schedule_event()` not custom cron

### 4.3 Hook-Based Architecture

✅ **Use WordPress hooks:**
- `register_activation_hook()` for setup
- `add_action()` / `add_filter()` for extensibility
- `do_action()` for custom extension points

### 4.4 Configuration Hierarchy

Same 3-tier pattern from `spec/general-spec/`, adapted for WordPress:

```
Tier 1: wp_options (Options API)     ← Highest priority
Tier 2: config/*.json (Seed files)   ← Installation defaults
Tier 3: Consts.php (Class constants) ← Fallback only
```

---

## 5. Quick Reference Card

### WordPress Sanitization Functions

| Function | Use Case |
|----------|----------|
| `sanitize_text_field()` | Single-line text |
| `sanitize_textarea_field()` | Multi-line text |
| `sanitize_email()` | Email addresses |
| `sanitize_url()` | URLs |
| `absint()` | Positive integers |
| `wp_kses_post()` | Rich HTML content |

### WordPress Escape Functions

| Function | Context |
|----------|---------|
| `esc_html()` | HTML content (between tags) |
| `esc_attr()` | HTML attributes |
| `esc_url()` | URLs (href, src) |
| `esc_js()` | Inline JavaScript |
| `wp_json_encode()` | JSON in script tags |

### Nonce Pattern

```php
// Generate
wp_nonce_field('action_name', 'nonce_field');

// Verify
wp_verify_nonce($_POST['nonce_field'], 'action_name');
```

### REST API Envelope

```json
{
    "success": true,
    "data": { },
    "error": null,
    "meta": {
        "requestId": "abc123",
        "timestamp": "2026-01-26T10:00:00Z",
        "version": "1.0.0"
    }
}
```

### Cron Hook Naming

```
{plugin_slug}_{frequency}_{action}

Example: eqm_daily_cleanup
```

### Settings Option Naming

```
{plugin_slug}_{setting_name}

Example: eqm_items_per_page
```

---

## 6. File Organization

### Recommended Plugin Structure

```
plugin-slug/
├── plugin-slug.php              # Bootstrap
├── uninstall.php                # Cleanup
├── composer.json                # PSR-4 autoloading
│
├── config/
│   ├── defaults.json            # Seed data
│   └── constants.php            # Fallbacks
│
├── src/
│   ├── Admin/                   # Admin UI
│   ├── API/                     # REST endpoints
│   ├── Core/                    # Plugin, Activator, Deactivator
│   ├── Database/                # Migrator, Seeder, Models
│   ├── Services/                # Business logic
│   └── Utils/                   # Helpers, Logger, Sanitizer
│
├── assets/
│   ├── css/
│   └── js/
│
├── languages/                   # i18n
├── logs/                        # app.log, error.log
└── tests/
```

---

## 7. Error Handling Pattern

All WordPress operations MUST use try-catch with full stack trace logging:

```php
try {
    // WordPress operation
} catch (\Throwable $e) {
    Logger::error('Operation failed', [
        'file' => __FILE__,
        'action' => 'methodName',
        'error' => $e->getMessage(),
        'stack_trace' => $e->getTraceAsString()
    ]);
    throw $e; // Re-throw if critical
}
```

---

## 8. Version-Triggered Seeding

When plugin version changes:

```
1. Update config/defaults.json → _meta.version
2. Update Consts.php → PLUGIN_VERSION
3. Update CHANGELOG.md
4. On activation: VersionManager detects mismatch → triggers Seeder
```

---

## 9. Checklist Before Release

### Security
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] Nonces verified for all forms/actions
- [ ] Capabilities checked before operations
- [ ] SQL uses `$wpdb->prepare()`

### Lifecycle
- [ ] Activation hook creates tables/seeds data
- [ ] Deactivation hook clears cron jobs
- [ ] Uninstall.php removes ALL plugin data
- [ ] Version change triggers seeding

### Code Quality
- [ ] PSR-4 autoloading configured
- [ ] Functions under 15 lines
- [ ] Try-catch with stack trace logging
- [ ] No direct `$_POST`/`$_GET` without sanitization

### Admin UI
- [ ] Assets loaded only on plugin pages
- [ ] Scripts localized with nonces
- [ ] Settings use Settings API
- [ ] Admin notices for feedback

---

## 10. Cross-References to Foundation

These WordPress guidelines extend the following `spec/general-spec/` documents:

| WordPress Doc | Extends | Foundation Concept |
|---------------|---------|-------------------|
| 01-plugin-structure-wordpress | 01-foundation/01-coding-standards-foundation | PSR-4, naming conventions |
| 02-rest-api-wordpress | 03-quality/03-api-conventions-quality | Response envelope, error codes |
| 03-cron-system-wordpress | 02-systems/01-logging-system-systems | Error logging with stack traces |
| 04-admin-ui-wordpress | 03-quality/02-file-organization-quality | Asset organization |
| 05-sanitization-wordpress | 04-advanced/01-security-patterns-advanced | Input validation, XSS prevention |
| 06-configuration-wordpress | 02-systems/02-configuration-hierarchy-systems | 3-tier config pattern |

---

## 11. Success Metrics

When these guidelines are properly followed:

| Metric | Target |
|--------|--------|
| Plugin review approval (wp.org) | First submission |
| Security vulnerabilities | Zero |
| AI first-attempt success rate | 95%+ |
| Code review rejections | <5% |

---

*Read this overview first, then proceed to individual documents as needed.*
