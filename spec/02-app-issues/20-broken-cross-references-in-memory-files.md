# Issue #20 — Broken Cross-References in Memory Files

> **Created:** 2026-03-12
> **Category:** Documentation / Cross-References
> **Severity:** Low (no runtime impact, confuses AI agents)

---

## 1. Issue Summary

Three `.lovable/memory/` files contained cross-references to non-existent "System memory" entries:

| File | Broken Reference |
|------|-----------------|
| `architecture/php/plugin-identity-standard.md` | `architecture/php/enum-usage-constraints` |
| `architecture/coding-standards/persistence-naming-exemptions.md` | `architecture/coding-standards/enums-standard`, `architecture/php/enum-usage-constraints`, `architecture/php/plugin-identity-standard` (unlinked) |
| `architecture/php/naming-conventions.md` | `architecture/coding-standards/enums-standard` |

**Impact:** AI agents following these references hit dead ends, losing context during implementation tasks.

## 2. Root Cause Analysis

- **Direct cause:** Cross-references used a "System memory" shorthand format (`System memory \`path\``) that pointed to knowledge-base entries that were never created as actual files.
- **Contributing factors:** No validation step existed to verify that referenced targets resolve to real files.
- **Why the spec didn't prevent it:** The post-fix workflow (`10-post-fix-issue-writeup-workflow.md`) requires updating memory but doesn't mandate verifying outbound links from the updated files.

## 3. Fix Description

1. Replaced all 5 broken `System memory` references with actual file paths:
   - `enum-usage-constraints` → `.lovable/memory/architecture/php/core-enum-inventory.md`
   - `enums-standard` → `spec/06-php-standards/enums.md`
   - `plugin-identity-standard` (bare) → `.lovable/memory/architecture/php/plugin-identity-standard.md`
2. Added a prevention rule to the workflow.

## 4. Prevention and Non-Regression

**Prevention rule:** Every cross-reference in a `.md` file must use one of these formats:
- Relative markdown link: `[label](./path.md)` or `[label](../path/file.md)`
- Absolute project-root path: `` `spec/06-php-standards/enums.md` ``

**Prohibited:** `System memory \`path\`` format — these are not verifiable.

**Acceptance criteria:**
- `grep -rn 'System memory' .lovable/memory/ spec/` returns zero matches.
- All paths in cross-reference sections resolve to existing files.

## 5. Done Checklist

- [x] Broken references fixed in 3 files
- [x] Issue write-up created (`spec/02-app-issues/20-broken-cross-references-in-memory-files.md`)
- [x] Memory updated with prevention rule
- [x] Prevention Rules Registry updated in `10-post-fix-issue-writeup-workflow.md`
