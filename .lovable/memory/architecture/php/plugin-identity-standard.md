# Memory: architecture/php/plugin-identity-standard
Updated: 2026-02-23

Hardcoded plugin identity strings (e.g., `[Riseup Asia]`, `Riseup Asia Uploader`) are prohibited in log prefixes, user-facing email/notice strings, and generated file headers (such as `.htaccess` or log file headers). These values must be retrieved from the `PluginConfigType` enum to prevent repetition and ensure consistency.

In PHP classes, a static `logPrefix()` method is preferred over a class constant for log prefixes, because PHP does not allow constants to reference enum property values at compile time.

## Phase 8 — Completed 2026-02-23

The following five files were fixed to replace hardcoded identity strings with `PluginConfigType` enum references:

| File | Change |
|---|---|
| `Notification/AdminMailer.php` | Replaced `LOG_PREFIX` constant with `logPrefix()` static method; email subjects/body now use `PluginConfigType::Name` |
| `Admin/Traits/AdminErrorStateTrait.php` | Admin notice heading now uses `PluginConfigType::Name->value` |
| `Helpers/Traits/PathHelperDirTrait.php` | `.htaccess` generated header uses enum value |
| `Helpers/Traits/InitDirTrait.php` | `.htaccess` generated header uses enum value |
| `Activation/ActivationHandler.php` | Stacktrace log header uses `PluginConfigType::Name->value` |

## Codebase Audit — Confirmed Clean

A full audit (2026-02-23) confirmed zero remaining actionable violations. All other occurrences fall into exempt categories:

- **PluginConfigType enum** — source of truth (not hardcoded duplication)
- **PHPDoc headers** — static documentation, exempt by convention
- **i18n `__()` / `esc_html__()` calls** — require literal text domain for `make-pot` compatibility
- **Autoloader.php** — must remain self-contained, exempt by convention
- **External scripts** (PowerShell uploaders) — out of scope
- **Data files** (`endpoints.json`) — external-facing, exempt

## Cross-References
- **Enum usage constraints:** System memory `architecture/php/enum-usage-constraints`
- **Persistence naming exemptions:** `.lovable/memory/architecture/coding-standards/persistence-naming-exemptions.md`
- **camelCase migration status:** `.lovable/memory/architecture/php/naming-conventions.md`
