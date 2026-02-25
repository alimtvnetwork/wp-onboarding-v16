# Issue 08 — i18n Text Domain Literal Requirement

> **Created:** 2026-02-23

## Issue Summary

1. **What happened:** A request was made to replace the repeated text domain string `'riseup-asia-uploader'` in WordPress i18n functions (`__()`, `esc_html_e()`, `esc_html__()`, `esc_attr__()`, etc.) with a constant from `PluginConfigType`.
2. **Where it happened:** All template files (`admin-logs.php`, `admin-settings.php`, `admin-agents.php`, `admin-snapshots.php`, `admin-errors.php`) and any PHP file using WordPress translation functions.
3. **Symptoms and impact:** If the replacement had been made, WordPress's `make-pot` CLI tool would silently fail to extract any translatable strings, breaking all internationalization support without any runtime error.
4. **How it was discovered:** During review — the request was correctly refused because the existing memory (`plugin-identity-standard.md`) already documents this exemption.

## Root Cause Analysis

1. **Direct cause:** The text domain string `'riseup-asia-uploader'` appears hundreds of times across template files, making it look like a magic string violation.
2. **Contributing factors:** The project's strict "no hardcoded identity strings" policy (from `PluginConfigType` enum standard) creates an expectation that *all* repeated plugin-identity strings should use the enum. The i18n exemption was documented but not prominent enough.
3. **Triggering conditions:** Any review or audit that flags repeated string literals without checking the exemption list.
4. **Why the existing spec did not prevent it:** The exemption was listed in `.lovable/memory/architecture/php/plugin-identity-standard.md` under "Codebase Audit — Confirmed Clean" but was not called out as a top-level rule in the spec files under `../01-app/`.

## Fix Description

1. **Spec change:** Added explicit "WordPress i18n Text Domain Constraint" section to `../01-app/enum-consumer-checklist.md` stating that i18n text domains are permanently exempt from enum replacement.
2. **New rule:** `RULE-I18N-LITERAL`: WordPress i18n function calls (`__()`, `_e()`, `esc_html__()`, `esc_html_e()`, `esc_attr__()`, `esc_attr_e()`, `_n()`, `_x()`) **must** use a literal string for the text domain parameter. Constants, variables, or enum values are prohibited.
3. **Why it resolves the root cause:** Makes the exemption a first-class, searchable rule rather than a footnote in an audit summary.
4. **Config changes:** None.
5. **Logging/diagnostics:** None required — `make-pot` will silently produce an empty `.pot` file if violated, which is the diagnostic signal.

## Prevention and Non-Regression

1. **Prevention rule:** `RULE-I18N-LITERAL` — Never replace text domain literals in i18n calls with constants or enum values. WordPress `make-pot` requires literal strings for extraction.
2. **Acceptance criteria:** All i18n calls across the codebase use the literal string `'riseup-asia-uploader'` as the text domain. Running `wp i18n make-pot` produces a `.pot` file containing all translatable strings.
3. **Guardrails:** Any future audit or refactoring tool must skip i18n text domain parameters when flagging "magic strings."
4. **Spec references:**
   - `../01-app/enum-consumer-checklist.md` — "WordPress i18n Text Domain Constraint" section
   - `.lovable/memory/architecture/php/plugin-identity-standard.md` — existing exemption list

## TODO and Follow-Ups

1. None — no code changes were made; the request was correctly refused.

## Done Checklist

- [x] Spec updated under `../01-app/`
- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable — N/A (single iteration, no fix needed)
