# Memory: architecture/specification-structure

**Updated:** 2026-02-01  
**Status:** Active

---

## Structure

Core modules are organized as standalone root-level specifications to facilitate portability:

| Module | Location | Status |
|--------|----------|--------|
| GSearch CLI | `spec/gsearch-cli/` | ✅ Extracted |
| AI Bridge | `spec/ai-bridge/` | ✅ Extracted |
| Nexus Flow | `spec/nexus-flow/` | ⏳ Pending |
| BRun CLI | `spec/brun-cli/` | ⏳ Pending |

These modules are integrated into the **Spec Management Software** via centralized reference files at:
- `spec/spec-management-software/15-external-tools/`

---

## Reference Pattern

Each original location retains a `REFERENCE.md` file pointing to the extracted spec.

---

## WordPress Plugin

The WordPress plugin specification folder is maintained at `spec/wp-plugin/` (root of spec directory).

---

*Memory updated 2026-02-01 after Phase 1 & 2 extraction*
