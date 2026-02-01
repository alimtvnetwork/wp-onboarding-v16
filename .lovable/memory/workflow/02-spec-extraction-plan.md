# Spec Extraction Plan

> **Version:** 1.1.0  
> **Created:** 2026-02-01  
> **Status:** ✅ Complete (All Phases)  
> **Purpose:** Extract standalone specs from spec-management-software

---

## Overview

Extract four tools/systems from `spec/spec-management-software/05-features/` into standalone root-level specifications, while maintaining references from the parent spec.

---

## Extraction Targets

| # | Source Location | Target Location | Files | Status |
|---|-----------------|-----------------|-------|--------|
| 1 | `05-features/22-golang-search-cli/` | `spec/gsearch-cli/` | 26 files | ✅ Complete |
| 2 | `05-features/30-ai-bridge/` | `spec/ai-bridge/` | 8 files | ✅ Complete |
| 3 | `14-microservices/` (Nexus files) | `spec/nexus-flow/` | 6 files | ✅ Complete |
| 4 | `05-features/23-build-runner-cli/` | `spec/brun-cli/` | 18 files | ✅ Complete |

---

## Completed Phases

### Phase 1: GSearch CLI Extraction ✅
- Copied 26 files to `spec/gsearch-cli/`
- Created reference file at original location
- Error range: 7000-7999

### Phase 2: AI Bridge Extraction ✅
- Copied 8 files to `spec/ai-bridge/`
- Created reference file at original location
- Error range: 9000-9999

### Phase 3: Nexus Flow Extraction ✅
- Copied 5 files from `14-microservices/` to `spec/nexus-flow/`
- Created new `00-overview.md` and `05-error-codes.md`
- Updated reference file at `14-microservices/NEXUS-FLOW-REFERENCE.md`
- Error range: 8000-8399

### Phase 4: BRun CLI Extraction ✅
- Copied 18 files to `spec/brun-cli/`
- Reference file already existed at original location
- Error range: 7100-7599

### Phase 5: Integration References ✅
Updated in `spec/spec-management-software/15-external-tools/`:
- `00-overview.md` - All tools marked complete
- `01-gsearch-reference.md` - Complete
- `02-ai-bridge-reference.md` - Complete
- `03-nexus-flow-reference.md` - Complete
- `04-brun-reference.md` - Complete

### Phase 6: Consistency Check ✅
- Created `99-consistency-report.md` in external-tools
- Verified all cross-references valid
- No broken links detected
- No error code range conflicts
- Updated master index with `15-External-Tools` section
- Health Score: 100/100 (A+)

---

## Completion Summary

All 6 phases completed successfully on 2026-02-01:

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | GSearch CLI Extraction | ✅ Complete |
| 2 | AI Bridge Extraction | ✅ Complete |
| 3 | Nexus Flow Extraction | ✅ Complete |
| 4 | BRun CLI Extraction | ✅ Complete |
| 5 | Integration References | ✅ Complete |
| 6 | Consistency Check | ✅ Complete |

**Total Files:** 60 extracted to 4 standalone specifications

---

## Current Root Spec Structure

```
spec/
├── error-code-registry/      # Existing
├── general-spec/             # Existing
├── powershell-integration/   # Existing
├── spec-management-software/ # Existing (parent)
├── wp-plugin/                # Existing ✅
├── gsearch-cli/              # ✅ Phase 1
├── ai-bridge/                # ✅ Phase 2
├── nexus-flow/               # ✅ Phase 3
├── brun-cli/                 # ✅ Phase 4
└── 00-folder-structure-guideline.md
```

---

## Error Code Allocation

| Range | Tool |
|-------|------|
| 7000-7099 | GSearch CLI |
| 7100-7599 | BRun CLI |
| 8000-8399 | Nexus Flow |
| 9000-9999 | AI Bridge |

---

## Dependencies

| Tool | Depends On | Used By |
|------|------------|---------|
| GSearch CLI | None | RAG System, Scout |
| AI Bridge | None | AI Integration, Automation |
| Nexus Flow | AI Bridge, BRun | Automation Pipeline |
| BRun CLI | None | Nexus Flow, Build System |

---

*Updated 2026-02-01 — Phases 1-4 complete, awaiting Phase 6 consistency check*
