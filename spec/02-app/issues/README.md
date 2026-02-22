# Issue Write-Ups

> **Purpose:** Permanent record of every mistake, its root cause, fix, and prevention rule.
> **Updated:** 2026-02-22

## Why This Exists

Every time a mistake is made and fixed, a write-up **must** be created here. This is the single most important documentation practice in the project — it prevents the same mistake from being repeated.

## File Naming

```
/spec/02-app/issues/{NN}-{issue-slug-name}.md
```

- `{NN}` — zero-padded sequential number (01, 02, 03…)
- `{issue-slug-name}` — lowercase, hyphen-separated, short, descriptive, stable. No spaces or special characters.

Examples:
- `01-hourly-frequency-missing-from-consumers.md`
- `02-option-name-migration-flag-collision.md`

## Required Sections (in this exact order)

1. **Issue Summary** — What happened, where, symptoms, impact, how discovered.
2. **Root Cause Analysis** — Direct cause, contributing factors, triggering conditions, why the spec didn't prevent it.
3. **Fix Description** — What was changed (spec-level, not code), new rules/constraints, why it resolves the root cause, config/default changes, logging/diagnostics.
4. **Iterations History** — (Only if multiple attempts.) Each iteration: what was tried and why it failed.
5. **Prevention and Non-Regression** — Prevention rule, acceptance criteria, guardrails/linting, references to updated spec sections.
6. **TODO and Follow-Ups** — Remaining tasks, owners/roles.
7. **Done Checklist** — Standardized checklist (see template).

## Post-Fix Checklist (mandatory after every fix)

1. [ ] Spec updated under `/spec/01-app/`
2. [ ] Issue write-up created under `/spec/02-app/issues/`
3. [ ] Memory updated with summary and prevention rule
4. [ ] Acceptance criteria updated or added
5. [ ] Iterations recorded if applicable

## Index

| # | Slug | Category | Summary |
|---|------|----------|---------|
| — | — | — | No issues recorded yet. |
