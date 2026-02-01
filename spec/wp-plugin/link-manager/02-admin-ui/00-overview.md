# 02 - Admin UI Overview

> **Status:** Complete  
> **Priority:** High  
> **Updated:** 2026-01-31

---

## Purpose

Defines the WordPress admin interface for the Link Manager plugin, providing a modern, user-friendly experience for managing links across posts, pages, and categories.

---

## Navigation Structure

```
Link Manager (Menu)
├── Overview (Default)      → 18-overview-page.md
├── Settings                → 20-settings-page.md
│   └── Notifications Tab   → 25-notification-settings-page.md
├── Internal Linking        → 21-internal-linking-page.md
├── Yoast SEO              → 28-yoast-seo-page.md (if Yoast active)
└── Snapshots              → (inline in overview)

From Overview → Content Detail → 19-content-detail-page.md
```

---

## Design Principles

1. **WordPress Native**: Use wp-admin styles and components
2. **Progressive Disclosure**: Show summary first, details on demand
3. **Non-Destructive**: All actions reversible via history
4. **Responsive**: Works on tablets (768px+)
5. **Accessible**: WCAG 2.1 AA compliant

---

## Shared Components

### Common UI Elements

| Component | Usage |
|-----------|-------|
| TabNavigation | Posts/Pages/Categories switching |
| DataTable | Sortable, paginated tables |
| ActionMenu | Dropdown action menus |
| Modal | Dialogs for edits and confirmations |
| Toast | Success/error notifications |
| ProgressBar | Scan progress indicator |

### Design Tokens

```css
/* WordPress Admin Colors */
--lm-primary: #2271b1;
--lm-primary-hover: #135e96;
--lm-success: #00a32a;
--lm-warning: #dba617;
--lm-error: #d63638;
--lm-text: #1d2327;
--lm-text-light: #50575e;
--lm-border: #c3c4c7;
--lm-bg: #f0f0f1;
```

---

## Spec Files

| File | Description |
|------|-------------|
| `18-overview-page.md` | Main dashboard with content list |
| `19-content-detail-page.md` | Individual content link management |
| `20-settings-page.md` | Plugin configuration |
| `21-internal-linking-page.md` | Internal linking with templates, variables, auto-link |
| `25-notification-settings-page.md` | Notification preferences, recipients, webhooks, history |
| `28-yoast-seo-page.md` | Yoast SEO optimization (keywords, descriptions) |

---

## Tech Stack

- **Rendering**: PHP + WordPress admin hooks
- **Interactivity**: Alpine.js or vanilla JS
- **Tables**: DataTables or custom implementation
- **Modals**: WordPress Thickbox or custom
- **API**: WordPress REST API (lm/v1)

---

## Related Specs

- `01-admin-backend/` - Backend services
- `66-shared-constants.md` - Shared enums
