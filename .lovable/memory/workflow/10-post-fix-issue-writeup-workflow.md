# Memory: workflow/post-fix-issue-writeup-workflow

Updated: 2026-02-23

## Mandatory Post-Fix Workflow

Every time a mistake is identified and fixed, the following steps are **mandatory** before the fix is considered complete:

1. **Create issue write-up** at `/spec/02-app/issues/{NN}-{issue-slug-name}.md` using the template at `/spec/02-app/issues/TEMPLATE.md`.
2. **Update the relevant spec** under `/spec/01-app/` with corrected behavior, explicit constraints, and acceptance criteria.
3. **Update memory** — add the mistake summary and prevention rule to this file's Prevention Rules Registry, and update the issue index at `/spec/02-app/issues/README.md`.
4. **Record iterations** — if multiple attempts were needed, document each in the Iterations History section.

## File Naming Rules for Issue Slugs

- Lowercase only, hyphen-separated, short, descriptive, stable.
- No spaces or special characters.
- Example: `01-hourly-frequency-missing-from-consumers`

## Key Locations

- App specs: `/spec/01-app/`
- Issue write-ups: `/spec/02-app/issues/`
- Issue template: `/spec/02-app/issues/TEMPLATE.md`
- Enum consumer checklist: `/spec/01-app/enum-consumer-checklist.md`
- Formatting rules reference: `/spec/01-app/formatting-rules-reference.md`

## Prevention Rules Registry

| Rule | Source Issue | Spec Reference |
|------|-------------|----------------|
| When adding a new enum case, update ALL consumers in the same changeset (validation, UI, JS constants, switch statements, cron, migration helper, memory). | `01-hourly-frequency-missing-from-consumers` | `/spec/01-app/enum-consumer-checklist.md` |
| PHP array literals with >2 items must be written line-by-line (R9c). | `02-r9c-array-literal-formatting` | `/spec/01-app/formatting-rules-reference.md` |
| Blank line mandatory before `if`/`foreach`/`switch`/`match` after assignments (R10). | `03-r10-activation-handler-formatting` | `/spec/01-app/formatting-rules-reference.md` |
| Arrays/calls with >2 items must be one item per line with trailing comma (R9). Applies to all files retroactively. | `04-r9-multi-file-array-formatting` | `/spec/01-app/formatting-rules-reference.md` |
| Any new structured array key must be added to `ResponseKeyType` before use. No raw strings for domain-level keys. Fixed bug: `$analysis['seed_order']` used wrong snake_case key. | `05-response-key-type-expansion` | `/spec/01-app/enum-consumer-checklist.md` |
