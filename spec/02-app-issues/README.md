# Issue Write-Ups

> **Purpose:** Permanent record of every mistake, its root cause, fix, and prevention rule.
> **Updated:** 2026-03-12

## Why This Exists

Every time a mistake is made and fixed, a write-up **must** be created here. This is the single most important documentation practice in the project — it prevents the same mistake from being repeated.

## File Naming

```
./{NN}-{issue-slug-name}.md
```

- `{NN}` — zero-padded sequential number (01, 02, 03…)
- `{issue-slug-name}` — lowercase, hyphen-separated, short, descriptive, stable. No spaces or special characters.

## Required Sections (in this exact order)

1. **Issue Summary** — What happened, where, symptoms, impact, how discovered.
2. **Root Cause Analysis** — Direct cause, contributing factors, triggering conditions, why the spec didn't prevent it.
3. **Fix Description** — What was changed (spec-level, not code), new rules/constraints, why it resolves the root cause, config/default changes, logging/diagnostics.
4. **Iterations History** — (Only if multiple attempts.) Each iteration: what was tried and why it failed.
5. **Prevention and Non-Regression** — Prevention rule, acceptance criteria, guardrails/linting, references to updated spec sections.
6. **TODO and Follow-Ups** — Remaining tasks, owners/roles.
7. **Done Checklist** — Standardized checklist (see template).

## Post-Fix Checklist (mandatory after every fix)

1. [ ] Spec updated under `../01-app/`
2. [ ] Issue write-up created under `./`
3. [ ] Memory updated with summary and prevention rule
4. [ ] Acceptance criteria updated or added
5. [ ] Iterations recorded if applicable

## Index

| # | Slug | Category | Summary |
|---|------|----------|---------|
| 03a | hardcoded-color-remediation | UI / Design System | Hardcoded color remediation plan |
| 03b | hourly-frequency-missing-from-consumers | Enum/Consumer Sync | New enum case requires updating all consumers simultaneously |
| 04 | r9c-array-literal-formatting | Code Style | PHP arrays with >2 items must be line-by-line |
| 05 | r10-activation-handler-formatting | Code Style | Blank line required before control structures after assignments |
| 06 | r9-multi-file-array-formatting | Code Style | Arrays/calls with >2 items must be one-per-line across all files |
| 07 | response-key-type-expansion | Enum/Consumer Sync | Added 7 missing ResponseKeyType cases and migrated ~15 PHP consumers |
| 08 | i18n-text-domain-literal-requirement | i18n / WordPress Tooling | Text domain in i18n calls must be literal string — never enum/constant |
| 09 | snake-case-db-column-references-after-v13 | Database / Naming Convention | PHP code used snake_case column names after V13 PascalCase migration |
| 10 | php-audit-report | Audit | PHP codebase audit report |
| 11 | r10-audit-report | Audit | R10 blank-line-before-control-structure audit report |
| 12 | namespace-before-abspath-fatal-error | PHP/Syntax | ABSPATH guard before namespace in 9 Logging files causes fatal ParseError |
| 13 | wpreset-class-name-case-mismatch | PHP/Naming Convention | SnapshotProviderWPReset class name didn't match filename on case-sensitive FS |
| 14 | php-full-audit-structural-violations | PHP/Audit | ErrorResponse ABSPATH-before-namespace, DbResult duplicate guard, 4 missing PHPDoc headers |
| 15 | r10-full-codebase-audit | Code Style / R10 | 19 missing blank lines before control structures across 8 trait files |
| 16 | unused-php-enum-cases | PHP/Audit | Unused enum cases identified across codebase |
| 17 | coverage-report-wrong-package-filtering | Tooling / Coverage | Coverage reported test packages instead of source packages |
| 18 | php-enum-duplicate-value-fatal | PHP / Enum | Duplicate backing value in PluginConfigType caused site-crashing fatal error |
| 19 | missing-phpdoc-opening-trait-parse-error | PHP / Parse Error | Missing `/**` opening in PHPDoc caused silent trait load failure and site crash |
| 20 | broken-cross-references-in-memory-files | Documentation / Cross-References | Broken "System memory" references to non-existent entries in 3 memory files |
| 21 | wrong-qupload-api-namespace-in-script | PowerShell / REST API | Upload script used `qupload/v1` instead of `qupload-api/v1` — every upload 404'd |
