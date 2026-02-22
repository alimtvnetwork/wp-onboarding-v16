# Memory: workflow/post-fix-issue-writeup-workflow

Updated: 2026-02-22

## Mandatory Post-Fix Workflow

Every time a mistake is identified and fixed, the following steps are **mandatory** before the fix is considered complete:

1. **Create issue write-up** at `/spec/02-app/issues/{NN}-{issue-slug-name}.md` using the template at `/spec/02-app/issues/TEMPLATE.md`.
2. **Update the relevant spec** under `/spec/01-app/` with corrected behavior, explicit constraints, and acceptance criteria.
3. **Update memory** — add the mistake summary and prevention rule to the relevant memory file, and update the issue index at `/spec/02-app/issues/README.md`.
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

## Prevention Rules Registry

| Rule | Source Issue | Spec Reference |
|------|-------------|----------------|
| When adding a new enum case, update ALL consumers in the same changeset (validation, UI, JS constants, switch statements, cron, migration helper, memory). | S-024 learning | `/spec/01-app/enum-consumer-checklist.md` |
