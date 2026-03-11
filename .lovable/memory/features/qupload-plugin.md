# Memory: features/qupload-plugin
Updated: 2026-03-11

The 'Quick Upload' (QUpload) WordPress plugin (PHP 8.1+) is a minimal remote deployment system namespaced as `QUpload\`. It uses WordPress Application Passwords (Basic Auth) for security and provides a REST API (`qupload/v1`) for ZIP-based plugin deployment (`POST /upload`) and slug-based activation (`POST /activate`). The upload process includes automatic slug detection, forced replacement of existing versions, and OPcache resetting.

## Key Components

- **PowerShell script:** `upload-plugin-U-Q.ps1` for automated deployments
- **Logging:** Isolated file-based logging in `wp-content/uploads/qupload/logs/`
- **Management UI:** WordPress 'Tools' menu — status, API reference, and real-time logs
- **Versioning:** Automated via `wp-plugins/scripts/bump-version.ps1`

## Design Philosophy

QUpload is designed for maximum simplicity and runtime stability, focusing exclusively on ZIP-based deployments with minimal complexity to ensure high confidence in remote deployment operations.

## Data Cleanup

- **Deactivation:** Clears `qupload/temp/` directory
- **Uninstallation:** `uninstall.php` recursively removes `wp-content/uploads/qupload/` and deletes the `qupload_settings` option from WordPress database

## Cross-References

- **Plugin identity standard:** `.lovable/memory/architecture/php/plugin-identity-standard.md`
- **QUpload spec:** `spec/15-qupload-plugin/`
