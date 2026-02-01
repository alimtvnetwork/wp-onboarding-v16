# Spec Remediation Plan

> **Version:** 1.0  
> **Updated:** 2026-01-31  
> **Status:** Approved for execution

---

## Overview

This plan addresses the ~100+ under-specified "Planned" features that pose high risk for AI implementation errors. Features are tiered by criticality and implementation risk.

---

## Priority Tiers

### Tier 1: Critical (High AI Hallucination Risk)
Must be specified before any implementation attempt.

| Feature | Risk | Why Critical |
|---------|------|--------------|
| Authentication System | 🔴 Extreme | Security, token flows, session management |
| AI Integration (RAG) | 🔴 Extreme | Embedding pipelines, prompt assembly, model switching |
| Knowledge Memory System | 🔴 High | Vector storage, semantic search, context assembly |
| Automation Pipeline | 🔴 High | Multi-step orchestration, error recovery |

### Tier 2: High Priority
Complex state management and real-time features.

| Feature | Risk | Why |
|---------|------|-----|
| File Management | 🟠 High | CRUD + versioning + conflict resolution |
| History System | 🟠 High | Undo/redo, state snapshots |
| State Management | 🟠 Medium | Cross-component sync |
| Realtime Collaboration | 🟠 High | WebSocket, OT/CRDT |

### Tier 3: Medium Priority
UI-heavy features with moderate complexity.

| Feature | Risk |
|---------|------|
| Spec Editor | 🟡 Medium |
| Voice Input | 🟡 Medium |
| Theme System | 🟡 Low |
| Dashboard | 🟡 Low |
| Routing | 🟡 Low |

### Tier 4: Low Priority
Can be implemented with minimal specification.

| Feature | Risk |
|---------|------|
| Monitoring | 🟢 Low |
| Performance | 🟢 Low |
| i18n | 🟢 Low |

---

## Execution Order

1. **Immediate**: Fix HealthScore duplicate (DONE - consolidated to 6-field version)
2. **Week 1**: Tier 1 features (Auth, AI Integration, Knowledge Memory, Automation)
3. **Week 2**: Tier 2 features (File Management, History, State, Realtime)
4. **Week 3**: Tier 3-4 features (remaining specs)

---

## Success Metrics

- All "Planned" specs → "Draft" or "Complete"
- Each spec includes: Purpose, Interfaces, API, Acceptance Criteria, Security
- AI implementation success rate: 85%+ for Tier 1-2 features

---

## Decision Log

| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-01-31 | Adopted 6-field HealthScore (with RAGFormat) | 5-field version deprecated; RAGFormat critical for AI workflows |
| 2026-01-31 | Tier 1-4 priority approved | Risk-based ordering ensures critical specs done first |
| 2026-01-31 | Minimum Viable Spec template adopted | Prevents empty "Planned" specs from blocking AI implementation |
| 2026-01-31 | No chat history in memory | Structured specs capture decisions; chat adds noise |
| 2026-01-31 | Consolidated folder-structure files | Removed `project/folder-structure.md`; canonical in `training/02-folder-structure.md` |

---

## Related Memories

- [Spec Template](../patterns/spec-template.md) — Minimum viable spec format
- [HealthScore Formula](../logic/health-score-formula.md) — Canonical 6-field version
- [Coding Guidelines](../constraints/coding-guidelines.md) — TypeScript/Go standards
- [Folder Structure](../training/02-folder-structure.md) — Canonical directory reference
