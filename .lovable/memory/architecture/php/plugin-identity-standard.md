# Memory: architecture/php/plugin-identity-standard
Updated: 2026-03-03

Hardcoded plugin identity strings (e.g., `[Riseup Asia]`, `Riseup Asia Uploader`, `[QUpload]`) are prohibited in log prefixes, user-facing email/notice strings, generated file headers, and **template files**. These values must be retrieved from the `PluginConfigType` enum to prevent repetition and ensure consistency.

## Required PluginConfigType Cases

Every plugin's `PluginConfigType` enum MUST define at least these three identity cases:

| Case | Purpose | Example (RiseupAsia) | Example (QUpload) |
|---|---|---|---|
| `Name` | Full display name for UI titles, emails | `'Riseup Asia Uploader'` | `'Quick Upload'` |
| `ShortName` | Compact reference for code/class contexts | `'RiseupAsia'` | `'QUpload'` |
| `LogPrefix` | Bracketed prefix for all log entries | `'[Riseup Asia]'` | `'[QUpload]'` |

## Template Files

Template files (e.g., `admin-settings.php`, `admin-agents.php`) must define `$pluginName` and `$pluginSlug` variables at the top using `PluginConfigType` enum values, then use those variables throughout — including in WordPress i18n text domain parameters (`__()`, `esc_html_e()`, etc.).

**I18n trade-off acknowledged:** Using `$pluginSlug` as the text domain prevents `wp i18n make-pot` from auto-extracting strings. The domain must be passed manually during POT generation. This trade-off is accepted in favor of eliminating magic strings.

## Log Prefix Usage

In PHP classes, a static `logPrefix()` method is preferred over a class constant for log prefixes, because PHP does not allow constants to reference enum property values at compile time. All `error_log()` calls must use `PluginConfigType::LogPrefix->value` — never hardcoded bracket strings.

## Scope — Applies to All Plugins

This standard applies equally to **Riseup Asia Uploader** (`RiseupAsia\Enums`) and **QUpload** (`QUpload\Enums`), and any future plugins in the `wp-plugins/` directory.

## Phase 9 — Completed 2026-03-03

- Added `ShortName` case to both `PluginConfigType` enums
- Replaced all hardcoded `[QUpload]` log prefixes in `qupload.php` with `PluginConfigType::LogPrefix->value`
- Replaced hardcoded plugin name and slug magic strings in all five Riseup Asia template files with `$pluginName`/`$pluginSlug` variables sourced from `PluginConfigType`
- Replaced text domain literals (`'riseup-asia-uploader'`) with `$pluginSlug` in template i18n calls

## Phase 8 — Completed 2026-02-23

The following five files were fixed to replace hardcoded identity strings with `PluginConfigType` enum references:

| File | Change |
|---|---|
| `Notification/AdminMailer.php` | Replaced `LOG_PREFIX` constant with `logPrefix()` static method; email subjects/body now use `PluginConfigType::Name` |
| `Admin/Traits/AdminErrorStateTrait.php` | Admin notice heading now uses `PluginConfigType::Name->value` |
| `Helpers/Traits/PathHelperDirTrait.php` | `.htaccess` generated header uses enum value |
| `Helpers/Traits/InitDirTrait.php` | `.htaccess` generated header uses enum value |
| `Activation/ActivationHandler.php` | Stacktrace log header uses `PluginConfigType::Name->value` |

## Exempt Categories

- **PluginConfigType enum** — source of truth (not hardcoded duplication)
- **PHPDoc headers** — static documentation, exempt by convention
- **Autoloader.php** — must remain self-contained, exempt by convention
- **External scripts** (PowerShell uploaders) — out of scope
- **Data files** (`endpoints.json`) — external-facing, exempt

## Cross-References
- **Enum usage constraints:** System memory `architecture/php/enum-usage-constraints`
- **Persistence naming exemptions:** `.lovable/memory/architecture/coding-standards/persistence-naming-exemptions.md`
- **camelCase migration status:** `.lovable/memory/architecture/php/naming-conventions.md`
- **QUpload plugin overview:** `.lovable/memory/features/qupload-plugin.md`
