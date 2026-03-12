# Memory: workflow/post-fix-issue-writeup-workflow

Updated: 2026-02-23

## Mandatory Post-Fix Workflow

Every time a mistake is identified and fixed, the following steps are **mandatory** before the fix is considered complete:

1. **Create issue write-up** at `/spec/02-app-issues/{NN}-{issue-slug-name}.md` using the template at `/spec/02-app-issues/TEMPLATE.md`.
2. **Update the relevant spec** under `/spec/01-app/` with corrected behavior, explicit constraints, and acceptance criteria.
3. **Update memory** — add the mistake summary and prevention rule to this file's Prevention Rules Registry, and update the issue index at `/spec/02-app-issues/README.md`.
4. **Record iterations** — if multiple attempts were needed, document each in the Iterations History section.

## File Naming Rules for Issue Slugs

- Lowercase only, hyphen-separated, short, descriptive, stable.
- No spaces or special characters.
- Example: `01-hourly-frequency-missing-from-consumers`

## Key Locations

- App specs: `/spec/01-app/`
- Issue write-ups: `/spec/02-app-issues/`
- Issue template: `/spec/02-app-issues/TEMPLATE.md`
- Enum consumer checklist: `/spec/01-app/enum-consumer-checklist.md`
- Formatting rules reference: `/spec/01-app/formatting-rules-reference.md`

## Prevention Rules Registry

| Rule | Source Issue | Spec Reference |
|------|-------------|----------------|
| When adding a new enum case, update ALL consumers in the same changeset (validation, UI, JS constants, switch statements, cron, migration helper, memory). | `03-hourly-frequency-missing-from-consumers` | `/spec/01-app/enum-consumer-checklist.md` |
| PHP array literals with >2 items must be written line-by-line (R9c). | `04-r9c-array-literal-formatting` | `/spec/01-app/formatting-rules-reference.md` |
| Blank line mandatory before `if`/`foreach`/`switch`/`match` after assignments (R10). | `05-r10-activation-handler-formatting` | `/spec/01-app/formatting-rules-reference.md` |
| Arrays/calls with >2 items must be one item per line with trailing comma (R9). Applies to all files retroactively. | `06-r9-multi-file-array-formatting` | `/spec/01-app/formatting-rules-reference.md` |
| Any new structured array key must be added to `ResponseKeyType` before use. No raw strings for domain-level keys. Fixed bug: `$analysis['seed_order']` used wrong snake_case key. | `07-response-key-type-expansion` | `/spec/01-app/enum-consumer-checklist.md` |
| WordPress i18n text domain must be a literal string — never replace with enum/constant. `make-pot` requires static analysis. | `08-i18n-text-domain-literal-requirement` | `/spec/01-app/enum-consumer-checklist.md` |
| When a DB migration renames columns, ALL consumer code reading from those tables must be updated in the same commit. Search for old column names with grep. | `09-snake-case-db-column-references-after-v13` | `/spec/01-app/enum-consumer-checklist.md` |
| Every namespaced PHP file must follow strict statement ordering: `<?php` → PHPDoc → `namespace` → ABSPATH guard → `use` → class body. Placing ABSPATH guard before `namespace` is a fatal ParseError. | `12-namespace-before-abspath-fatal-error` | `.lovable/memory/architecture/php/coding-standards-semantic-and-safety.md` |
| All abbreviations in PHP identifiers must use PascalCase (`Wp` not `WP`, `Api` not `API`). Class names must exactly match filenames for PSR-4. | `13-wpreset-class-name-case-mismatch` | `.lovable/memory/coding-standards/php-modernization` |
| Coverage tools must parse `coverage.out` profile file for package identification. Never derive package names from `go test` stdout (which shows test package names). Packages ending in `tests` must be excluded. | `17-coverage-report-wrong-package-filtering` | `tools/coverage/README.md` |
| PHP backed enums must never have two cases with the same backing value. Use a static method alias instead. | `18-php-enum-duplicate-value-fatal` | `spec/02-app-issues/18-php-enum-duplicate-value-fatal.md` |
| Every PHPDoc block must have complete `/** ... */` delimiters. Run `php -l` on all modified files before deployment to catch parse errors that the autoloader silently swallows. | `19-missing-phpdoc-opening-trait-parse-error` | `spec/02-app-issues/19-missing-phpdoc-opening-trait-parse-error.md` |
