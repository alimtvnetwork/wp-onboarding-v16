# Application Specifications

> **Purpose:** Centralized home for all application-level specifications.
> **Updated:** 2026-02-22

All application specs, feature definitions, and behavioral requirements live here.

## Structure

- Feature specs, behavioral rules, and acceptance criteria go directly in this folder.
- Cross-cutting concerns (coding guidelines, language standards) remain in their existing `spec/` subfolders.

## Known Pitfalls and Prevention

When a recurring mistake is identified and fixed, the relevant spec in this folder must be updated with:

1. **Corrected behavior** — what the spec now says.
2. **Explicit constraints** — rules that prevent the old mistake.
3. **Failure modes** — how to detect if the mistake recurs.
4. **Acceptance criteria** — testable conditions for regression detection.
5. **Reference** — a link to the issue write-up under `/spec/02-app/issues/`.

See the [Issue Write-Up Process](../02-app/issues/README.md) for the mandatory post-fix workflow.
