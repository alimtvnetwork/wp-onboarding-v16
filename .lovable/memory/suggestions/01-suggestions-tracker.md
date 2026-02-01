# Suggestions Tracker

> **Version:** 3.0.0  
> **Updated:** 2026-02-01  
> **Purpose:** Single consolidated tracker for ALL Lovable suggestions  
> **Policy:** All suggestions go here. Completed items stay with `done` status for reference.

---

## Summary

| Status | Count |
|--------|-------|
| 📋 Open | 2 |
| 🔄 In Progress | 0 |
| ✅ Done | 9 |
| **Total** | 11 |

---

## 📋 Active Suggestions

### P-001: Register 9xxx Error Range for AI Bridge

| Field | Value |
|-------|-------|
| **suggestionId** | P-001 |
| **createdAt** | 2026-01-31 |
| **source** | Lovable |
| **affectedProject** | Spec Management Software |
| **status** | 📋 open |
| **priority** | Medium |

**Description:** Add AI Bridge error codes (9000-9499) to the central error code registry.

**Rationale:** The AI Bridge module defines error codes but they are not registered in `spec/error-code-registry/01-registry.md`.

**Proposed Change:**
- Add Module 30 (AI Bridge) to `spec/error-code-registry/01-registry.md`
- Sub-ranges: General (9000-9099), Input parsing (9100-9199), Backend (9200-9299), Execution (9300-9399), Config (9400-9499)

**Acceptance Criteria:**
- [ ] Error codes 9000-9499 registered
- [ ] Registry cross-references 30-ai-bridge/

**Completion Notes:** —

---

### P-002: Spec Management Implementation Roadmap

| Field | Value |
|-------|-------|
| **suggestionId** | P-002 |
| **createdAt** | 2026-01-31 |
| **source** | Lovable |
| **affectedProject** | Spec Management Software |
| **status** | 📋 open |
| **priority** | Low |

**Description:** Track implementation tasks for the specification set.

**Rationale:** All specifications are complete (97% readiness). Implementation can begin.

**Proposed Change:** Implement these in order:

| Task ID | Description | Dependencies | Spec Ready |
|---------|-------------|--------------|------------|
| SM-010 | Golang Backend | SM-001-008 | ✅ Yes |
| SM-011 | React Frontend | SM-010 | ✅ Yes |
| SM-012 | RAG System | SM-003, SM-010 | ✅ Yes |
| SM-013 | Automation Pipeline | SM-004, SM-010 | ✅ Yes |

**Optional Polish (99%):**
| Task | Description | Expected Gain |
|------|-------------|---------------|
| SM-021 | Monaco config | +1% |
| SM-022 | Error recovery patterns | +1% |

**Acceptance Criteria:**
- [ ] SM-010 implemented and tested
- [ ] SM-011 implemented and tested
- [ ] SM-012 implemented and tested
- [ ] SM-013 implemented and tested

**Completion Notes:** —

---

## ✅ Completed Suggestions

| ID | Summary | Completed |
|----|---------|-----------|
| C-001 | Established standardized filesystem convention for tracking suggestions | 2026-01-31 |
| C-002 | Created 18 PHP entity models and 10 enums for Link Manager | 2026-01-31 |
| C-003 | Updated RAG System spec to Complete with TypeScript interfaces | 2026-01-31 |
| C-004 | Updated reliability scores to 95%/97% after spec completion | 2026-01-31 |
| C-005 | Created guide for Link Manager separation | 2026-01-31 |
| C-006 | Fresh analysis of all specs; updated reliability report | 2026-01-31 |
| C-007 | Extracted Link Manager to self-contained folder | 2026-01-31 |
| C-008 | Completed fresh analysis; 95% success probability | 2026-01-31 |
| C-009 | Created 8-file AI Bridge specification | 2026-01-31 |

**Archive:** `completed/C-XXX-<slug>.md` for full details.

---

## Workflow

### Adding Suggestions
1. Add to **Active Suggestions** section with next `P-XXX` ID
2. Include: suggestionId, createdAt, source, affectedProject, description, rationale, proposed change, acceptance criteria, status, priority
3. Status options: `📋 open`, `🔄 inProgress`, `✅ done`

### Completing Suggestions
1. Update status to `✅ done`
2. Fill completion notes
3. Move entry to **Completed Suggestions** table
4. (Optional) Create detailed archive file in `completed/`

### Removing Completed Items
After 30+ days, completed items may be removed from this file. Archive files in `completed/` serve as permanent record.

---

## Statistics

| Metric | Value |
|--------|-------|
| Total Active | 2 |
| Completed This Week | 9 |
| Oldest Pending | P-001 (2026-01-31) |
| Last Updated | 2026-02-01 |

---

*This is the SINGLE source of truth for all suggestions. Do not create individual files for new suggestions.*
