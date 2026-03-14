# User Management System — Overview

## Purpose

Provide full CRUD and bulk import/export for WordPress users via the Riseup Asia
Uploader REST API, including core WordPress profile fields, social links, and
Yoast SEO Schema metadata. Managed from the Go backend and React dashboard.

## Scope

| Area                  | In Scope | Notes                                       |
|-----------------------|----------|---------------------------------------------|
| Create user           | ✅       | WP password + optional app password          |
| Get user (by ID)      | ✅       | All fields including Yoast meta              |
| List users            | ✅       | Paginated, filterable by role                |
| Update user           | ✅       | Partial updates (PATCH semantics via PUT)    |
| Delete user           | ✅       | With reassign option                         |
| Create app password   | ✅       | Per-user application passwords               |
| Revoke app password   | ✅       | By app password UUID                         |
| Import users (CSV)    | ✅       | Bulk create/update with hashed passwords     |
| Export users (CSV)    | ✅       | All fields, passwords as bcrypt hashes       |
| Export users (SQLite) | ✅       | Full backup as ZIP with SQLite DB            |
| Import users (SQLite) | ✅       | Restore from SQLite ZIP backup               |
| Yoast SEO metadata    | ✅       | Optional — silently skipped if Yoast absent  |
| User role assignment  | ✅       | All standard WP roles                        |

## Architecture

```
React Dashboard ──► Go Backend ──► PHP REST API (Riseup Asia Uploader)
                                         │
                                         ├── wp_insert_user / wp_update_user
                                         ├── WP_Application_Passwords API
                                         └── update_user_meta (Yoast fields)
```

## Security Constraints

1. **Passwords are never returned** in any GET response
2. **Export hashing**: CSV/SQLite exports include passwords only as bcrypt
   hashes (WordPress PHPass format) — never plaintext
3. **Import passwords**: Accepted as plaintext (hashed by WordPress on insert)
   or pre-hashed (stored directly)
4. **Permission**: All user endpoints require `create_users` / `edit_users` /
   `delete_users` capabilities (administrator-level)
5. **App passwords**: Created via `WP_Application_Passwords::create_new_application_password()`

## Yoast SEO Dependency

Yoast SEO metadata fields are **optional**. If the Yoast SEO plugin is not
active on the target site:

- GET responses omit the `yoast` object entirely
- PUT/POST requests silently ignore any `yoast.*` fields
- No error or warning is returned

Detection: `is_plugin_active('wordpress-seo/wp-seo.php')` or class existence
check for `WPSEO_Meta`.
