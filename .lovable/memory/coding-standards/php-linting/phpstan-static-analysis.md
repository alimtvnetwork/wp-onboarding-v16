# Memory: coding-standards/php-linting/phpstan-static-analysis

**Rule:** All WordPress plugin directories under `wp-plugins/` that contain a `phpstan.neon` config are subject to PHPStan level-6 static analysis before upload and on pre-commit.

**Integration points:**
1. **Pre-upload (PowerShell):** `Test-PhpStanAnalysis` in `upload-plugin-v2.ps1` runs PHPStan at step 2.5 after syntax check and backed enum lint. Blocks upload on failure.
2. **Pre-commit (Bash):** `scripts/lint-php-phpstan.sh` runs PHPStan for all plugins with `phpstan.neon`. Integrated into `scripts/pre-commit.sh`.

**Setup per plugin:**
- `phpstan.neon` — config at level 6 with WordPress function ignores
- `phpstan-bootstrap.php` — stubs for WP_User, WP_Error, WP_REST_Request, WP_REST_Response, and WordPress constants
- `composer.json` — `phpstan/phpstan` in `require-dev`

**What it catches:**
- Return type mismatches (e.g., `WP_User` returned from `true|WP_Error` method)
- Undefined method/property access
- Incorrect argument types
- Missing return statements

**Graceful degradation:** If PHPStan or PHP CLI is not installed, the check is skipped with a warning — never blocks deployment due to missing tooling.

**Reference:** `spec/02-app-issues/22-auth-return-type-fatal-error.md` (TODO item that triggered this)
