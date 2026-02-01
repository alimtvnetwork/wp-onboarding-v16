# Memory: spec-management/cross-reference-validation

**Updated:** 2026-01-29  
**Purpose:** Ensure 100% consistency score through systematic validation

---

## Critical Reviewer Prompt

When validating or updating spec files, act as a **critical reviewer** with the following mandate:

> "I validate all cross-references, links, and structural consistency to achieve a 100/100 health score. No broken links, no orphaned references, no inconsistent naming."

---

## Validation Checklist

### ✅ Pre-Change Validation

- [ ] **Read target files** before editing (never edit blind)
- [ ] **Identify all incoming references** to files being modified
- [ ] **Note current file numbers** and paths

### ✅ Link Validation

- [ ] **Relative paths are correct** (e.g., `./01-file.md`, `../folder/file.md`)
- [ ] **File numbers match actual filenames** after renumbering
- [ ] **No dead links** to non-existent files
- [ ] **Anchor links valid** if using `#section-name`

### ✅ Structural Consistency

- [ ] **00-overview.md updated** with correct document index
- [ ] **99-consistency-report.md updated** with new version
- [ ] **Master index (00-master-index.md)** reflects changes
- [ ] **Error code registry** updated if error codes changed

### ✅ Naming Conventions

- [ ] **Lowercase with hyphens** (no underscores, no spaces)
- [ ] **Sequential numbering** maintained (no gaps)
- [ ] **Consistent prefix format** within each folder

### ✅ Post-Change Validation

- [ ] **Search for old paths** across all spec files
- [ ] **Update external references** in other folders
- [ ] **Verify bidirectional links** work both ways
- [ ] **Check memory files** for outdated references

---

## Health Score Target

| Score | Grade | Status |
|-------|-------|--------|
| 100/100 | A+ | ✅ Target |
| 95-99 | A | Acceptable |
| 90-94 | A- | Needs attention |
| < 90 | B or below | ❌ Requires remediation |

---

## When to Run Full Validation

1. **After renumbering** any folder's files
2. **After moving/renaming** spec files
3. **After major structural changes** to folder hierarchy
4. **Before releasing** a new spec version
5. **Periodically** as part of maintenance