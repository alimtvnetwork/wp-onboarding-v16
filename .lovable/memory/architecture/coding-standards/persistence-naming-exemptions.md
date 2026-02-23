# Memory: architecture/coding-standards/persistence-naming-exemptions
Updated: 2026-02-23

The following identifiers are strictly exempt from camelCase and PascalCase naming standards to ensure compatibility with external systems. These exemptions were formally validated during the Batches A–E camelCase migration (completed 2026-02-23; see `architecture/php/naming-conventions.md` for full migration status).

## Exemption Categories

### 1. WordPress Globals & Hooks
- `$wpdb`, `$wp_reset`, and native hook names (e.g., `'admin_notices'`, `'wp_mail'`).
- Logic-level variables derived from these must be converted to camelCase immediately upon ingestion (e.g., `$this->wpReset = $wp_reset`).

### 2. Infrastructure & Persistence Keys
- **WP-Cron arguments:** `'snapshot_type'`, `'master_snapshot_id'`, and other keys passed through `wp_schedule_single_event`.
- **wp_options keys:** Internal settings storage keys (e.g., `'retention_type'`).
- **WordPress transients:** Core transient names (e.g., `'riseup_last_error_email'`, `'riseup_boot_diagnostics'`).
- **Internal SQLite metadata:** Schema-level keys managed by the database layer.
- **Manifest JSON keys:** External-facing keys (e.g., `'format_version'`).

### 3. Boundary Identifiers
- **PHP superglobals:** `$_GET`, `$_POST`, `$_SERVER`, etc.
- **HTML form `name` attributes** and **URL query parameters.**

### 4. PHP/Env & Documentation
- **Autoloader.php:** Constants and bootstrapping logic exempt as it must remain self-contained.
- **PHPDoc headers:** `@package`, `@since`, and file-level doc comments are static documentation.

### 5. i18n / Gettext
- The `'riseup-asia-uploader'` plugin slug remains hardcoded within translation calls (`__()`, `_e()`, `_n()`, `esc_html__()`) to maintain compatibility with WordPress `make-pot` extraction tools.

## Cross-References
- **camelCase migration status:** `.lovable/memory/architecture/php/naming-conventions.md`
- **Enum naming standard:** System memory `architecture/coding-standards/enums-standard`
- **Plugin identity standard:** System memory `architecture/php/plugin-identity-standard`
- **Enum usage constraints (PHP):** System memory `architecture/php/enum-usage-constraints`
