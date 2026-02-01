# Memory: project/folder-rename-history

**Updated:** 2026-01-29  
**Category:** Project Structure

---

## Summary

The primary feature specification folder was renamed from `05-split-spec` to `05-features` for improved clarity and semantic meaning.

---

## Rename Details

| Aspect | Before | After |
|--------|--------|-------|
| Folder Name | `05-split-spec/` | `05-features/` |
| Path | `spec/spec-management-software/05-split-spec/` | `spec/spec-management-software/05-features/` |
| Date | 2026-01-29 | — |
| Files Updated | 50+ | — |

---

## Rationale

- "Features" is more descriptive than "split-spec"
- Aligns with industry-standard terminology
- Improves discoverability for new contributors
- Better semantic meaning for AI context

---

## Migration Scope

### Updated References

- All `00-overview.md` index files
- Cross-reference links in 07-database-design, 08-roadmap-overview, 09-diagrams
- Memory files in `.lovable/memories/`
- Test fixtures in `10-research/01a-e2e-integration-tests.md`
- Consistency checker naming rules
- CONTEXT-FOR-AI.md documentation

### Preserved Historical References

- `99-session-changelog-2026-01-28.md` — Historical changelog (read-only)
- `CONTEXT-FOR-AI.md` — Contains "(Formerly Split-Spec)" note
- References to `wp-plugin/exam-manager/.../split-spec/` — Different project

---

## AI Instructions

When working with this project:

1. **Always use `05-features/`** for new feature specifications
2. **Never use `05-split-spec/`** — this path no longer exists
3. **Historical documents** may reference old path — do not "fix" changelogs
4. **Cross-project references** to wp-plugin's split-spec folder are valid

---

## Related

- [File Structure Conventions](./../spec-management/file-structure-conventions.md)
- [CONTEXT-FOR-AI](../../spec/spec-management-software/CONTEXT-FOR-AI.md)
