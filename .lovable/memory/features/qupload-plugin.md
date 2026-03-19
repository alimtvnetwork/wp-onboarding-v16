# Memory: features/qupload-plugin
Updated: 2026-03-19

The 'Quick Upload' (QUpload) WordPress plugin (PHP 8.1+) is a minimal remote deployment system namespaced as `QUpload\`. It uses WordPress Application Passwords (Basic Auth) for security and provides a REST API (`qupload-api/v1`) for ZIP-based plugin deployment (`POST /upload`) and slug-based activation (`PUT /activate`). The upload process includes automatic slug detection, forced replacement of existing versions, and OPcache resetting.

## Key Components

- **PowerShell script:** `upload-plugin-U-Q.ps1` for automated deployments
- **Logging:** Isolated file-based logging in `wp-content/uploads/qupload/logs/`
- **Management UI:** WordPress 'Tools' menu — status, API reference, and real-time logs
- **Versioning:** Automated via `wp-plugins/scripts/bump-version.ps1`

## REST Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/status` | Health check |
| `POST` | `/upload` | Upload plugin ZIP |
| `PUT` | `/activate` | Activate plugin by slug |
| `PUT` | `/deactivate` | Deactivate plugin by slug |
| `GET` | `/plugins` | List installed plugins |
| `GET` | `/logs/status` | Log file status |
| `POST` | `/logs/clear` | Clear logs |
| `POST` | `/logs/clear/confirm` | Confirm log clearing |
| `POST` | `/logs/email` | Email logs |
| `PUT` | `/machines/approve` | Approve machine for restricted operations |

## Design Philosophy

QUpload is designed for maximum simplicity and runtime stability, focusing exclusively on ZIP-based deployments with minimal complexity to ensure high confidence in remote deployment operations.

## Error Handling

- **Handler boundaries** use `safeExecute()` which catches Throwable, emits to PHP `error_log()` with full trace, logs via FileLogger, and returns a structured error envelope
- **Boot/load catch blocks** (autoloader, route registration, enum priming) always **re-throw after logging** — silent failure in infrastructure code is a critical defect
- **`errorResponse()`** calls `logErrorWithBacktrace()` which captures a 15-frame `debug_backtrace()` for non-exception errors
- **All errors surface in PHP debug** (`wp-content/debug.log`, server `php-error.log`) via native `error_log()` emission
- **Self-update resilience:** `buildEnvelopeResponse()` uses `class_exists(EnvelopeBuilder::class)` before using helper classes; falls back to inline JSON envelope via `buildFallbackResponse()` when classes are unavailable

## Machine Authorization

Sensitive or destructive remote operations (e.g., log clearing, ZIP deletion) require the source machine's name to be present in an `approved_machines` allowlist persisted in the WordPress database. The `PUT /machines/approve` endpoint manages this list remotely. PowerShell command: `.\run.ps1 -am [NAME]`.

The `-am` command includes a **preflight readiness check** that queries each site's `/status` endpoint and only attempts approval on sites running v2.17.0+. Sites with older versions are skipped with clear "NOT READY" messaging.

## Data Cleanup

- **Deactivation:** Clears `qupload/temp/` directory
- **Uninstallation:** `uninstall.php` recursively removes `wp-content/uploads/qupload/` and deletes the `qupload_settings` option from WordPress database

## Cross-References

- **Plugin identity standard:** `.lovable/memory/architecture/php/plugin-identity-standard.md`
- **PHP exception handling standards:** `.lovable/memory/coding-standards/php-exception-handling.md`
- **QUpload spec:** `spec/15-qupload-plugin/`
- **EnvelopeBuilder crash fix:** `.lovable/memory/issues/006-envelopebuilder-class-not-found-on-self-update.md`
