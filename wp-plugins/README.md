# WordPress Plugins

This directory contains WordPress plugins developed for remote plugin management and publishing.

---

## Plugins

### 1. Riseup Asia Uploader

**Folder:** `riseup-asia-uploader/`

A lightweight WordPress plugin providing a secure REST API for remote plugin management, delta file synchronization, blog post publishing, and comprehensive audit logging.

| Property | Value |
|----------|-------|
| **Plugin Name** | Riseup Asia Uploader |
| **Plugin URI** | https://rasia.pro/alim-r-profile-v1 |
| **Version** | 1.3.0 |
| **Author** | MD ALIM UL KARIM |
| **Author URI** | https://rasia.pro/alim-r-profile-v1 |
| **License** | GPL v2 or later |
| **Requires PHP** | 7.4+ |
| **Requires WordPress** | 5.6+ |

**Features:**
- Remote plugin upload, enable/disable, and delete via REST API
- Delta file synchronization with `.uploadignore` support
- Blog post and category management
- Media library uploads
- Transaction audit logging with SQLite
- Application Password authentication

**REST API Namespace:** `riseup-asia-uploader/v1`

---

### 2. Plugins Onboard

**Folder:** `plugins-onboard/`

Enterprise-grade REST API access to manage WordPress plugins remotely with OAuth 2.0 authentication, ephemeral mutation tokens, automatic backups, and comprehensive audit logging.

| Property | Value |
|----------|-------|
| **Plugin Name** | Plugins Onboard |
| **Plugin URI** | https://rasia.pro/alim-r-profile-v1 |
| **Version** | 1.0.8 |
| **Author** | MD ALIM UL KARIM |
| **Author URI** | https://rasia.pro/alim-r-profile-v1 |
| **License** | GPL-2.0+ |
| **Requires PHP** | 7.4+ |
| **Requires WordPress** | 5.9+ |

**Features:**
- OAuth 2.0 Authentication with JWT tokens
- Ephemeral mutation tokens (one-time, IP-bound)
- Automatic pre-action backups
- One-click snapshot restore
- IP whitelist with admin approval workflow
- Rate limiting per endpoint
- Token encryption (AES-256-CBC)
- Comprehensive audit logging

**REST API Namespace:** `onboard-plugin/v1`

---

## Author

**MD ALIM UL KARIM**

- Profile: https://rasia.pro/alim-r-profile-v1
- Company: Riseup Asia

---

## License

All plugins are licensed under GPL v2 or later.
