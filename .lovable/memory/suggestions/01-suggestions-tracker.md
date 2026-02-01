# Suggestions Tracker

> **Version:** 2.0.0  
> **Updated:** 2026-02-01  
> **Purpose:** Single consolidated tracker for all project suggestions  
> **Naming Convention:** Individual files use `YYYYMMDD-HHMMSS-suggestion-<slug>.md`

---

## Summary

| Status | Count |
|--------|-------|
| ✅ Completed | 9 |
| 📋 Pending | 2 |
| **Total** | 11 |

---

## 📋 Pending Suggestions

### P-001: Register 9xxx Error Range for AI Bridge

| Field | Value |
|-------|-------|
| **suggestionId** | P-001 |
| **createdAt** | 2026-01-31 |
| **source** | Lovable |
| **affectedProject** | Spec Management Software |
| **status** | open |

**Description:** Add AI Bridge error codes (9000-9499) to the central error code registry.

**Rationale:** The AI Bridge module (30-ai-bridge/) defines error codes in the 9xxx range but they are not yet registered in the central error code registry.

**Proposed Change:**
- Add entry for AI Bridge (Module 30) to `spec/error-code-registry/01-registry.md`
- Sub-ranges: 
  - General (9000-9099)
  - Input parsing (9100-9199)
  - Backend connection (9200-9299)
  - Request execution (9300-9399)
  - Configuration (9400-9499)

**Acceptance Criteria:**
- [ ] Error codes 9000-9499 registered in central registry
- [ ] Registry cross-references 30-ai-bridge/

**Completion Notes:** (To be filled when done)

---

### P-002: Spec Management Future Implementation Roadmap

| Field | Value |
|-------|-------|
| **suggestionId** | P-002 |
| **createdAt** | 2026-01-31 |
| **source** | Lovable |
| **affectedProject** | Spec Management Software |
| **status** | open |

**Description:** Track remaining implementation tasks for Spec Management Software.

**Rationale:** All specifications are complete; now actual implementation needs to begin.

**Proposed Change:** Implement the following tasks in order:

| Task ID | Description | Dependencies | Status |
|---------|-------------|--------------|--------|
| SM-010 | Implement Golang Backend | SM-001-SM-008 | 📋 Spec Ready |
| SM-011 | Implement React Frontend | SM-010 | 📋 Planned |
| SM-012 | Implement RAG System | SM-003, SM-010 | 📋 Planned |
| SM-013 | Implement Automation Pipeline | SM-004, SM-010 | 📋 Planned |

**Optional Polish (for 99%):**
| Task ID | Description | Expected Gain |
|---------|-------------|---------------|
| SM-021 | Add Monaco config to Spec Editor | +1% |
| SM-022 | Add error recovery patterns | +1% |

**Acceptance Criteria:**
- [ ] SM-010 implemented and tested
- [ ] SM-011 implemented and tested
- [ ] SM-012 implemented and tested
- [ ] SM-013 implemented and tested

**Completion Notes:** (To be filled when done)

---

## ✅ Completed Suggestions

All completed suggestions have been archived to `completed/` folder. Summary below:

| ID | Summary | Date Completed |
|----|---------|----------------|
| C-001 | Established standardized filesystem convention for tracking suggestions | 2026-01-31 |
| C-002 | Created 18 PHP entity models and 10 enums for Link Manager | 2026-01-31 |
| C-003 | Updated RAG System spec to Complete status with TypeScript interfaces | 2026-01-31 |
| C-004 | Updated reliability scores to 95%/97% after completing specs | 2026-01-31 |
| C-005 | Created guide for Link Manager separation | 2026-01-31 |
| C-006 | Fresh analysis of all specs; updated reliability report | 2026-01-31 |
| C-007 | Extracted Link Manager to self-contained folder | 2026-01-31 |
| C-008 | Completed fresh analysis; 95% success probability | 2026-01-31 |
| C-009 | Created 8-file AI Bridge specification | 2026-01-31 |

---

## Workflow

### Adding New Suggestions

1. **Add to Pending section** with next P-XXX ID
2. **Include required fields:**
   - suggestionId
   - createdAt
   - source (Lovable)
   - affectedProject
   - description
   - rationale
   - proposed change
   - acceptance criteria
   - status (open, inProgress, done)
3. **For individual files** (optional): Use naming `YYYYMMDD-HHMMSS-suggestion-<slug>.md`

### Updating Suggestions

1. **Work begins:** Update status to `inProgress`
2. **Progress notes:** Add notes in the suggestion entry
3. **Work complete:** 
   - Update status to `done`
   - Fill completion notes
   - Move summary to Completed section
   - Create detailed archive file in `completed/`

### Completion Handling

- When a suggestion is completed, update status to `done`
- Create archive file: `completed/C-XXX-<slug>.md`
- Summary stays in this tracker for reference

---

## Archive Reference

Full details of completed suggestions are in:
```
.lovable/memory/suggestions/completed/
├── C-001-workflow-convention.md
├── C-002-lm003-entity-models.md
├── C-003-sm003-rag-system.md
├── C-004-reliability-95-percent.md
├── C-005-lm-cleanup-guide.md
├── C-006-report-refresh.md
├── C-007-lm-extracted.md
├── C-008-fresh-analysis.md
└── C-009-ai-bridge-implemented.md
```

---

## Statistics

| Metric | Value |
|--------|-------|
| Total Pending | 2 |
| Completed This Week | 9 |
| Oldest Pending | P-001 (2026-01-31) |
| Last Updated | 2026-02-01 |

---

*This tracker consolidates all individual suggestion files for easier management.*
*Updated 2026-02-01 per user request to follow new workflow convention.*