# Changelog

All notable changes to the spec-management-software documentation structure.

---

## [2.4.0] - 2026-01-30

### File Safety & Persistence Enhancements

Critical updates for data protection, file operation safety, and Lovable-style input persistence.

### Added
- **`05-features/02-file-management/05-external-file-safety.md`** - External file operation consent system
  - Type-to-confirm dialogs for external file operations
  - Danger level classification (None → Critical)
  - Audit logging for all external operations
  - Integration with AI Code Generation execution engine
- **`05-features/02-file-management/06-trash-system.md`** - Recoverable trash bin
  - Soft delete with 30-day retention
  - Restore from trash functionality
  - Metadata preservation (.meta files)
  - Auto-cleanup via retention policy
- **`05-features/03-project-editor/06-input-state-persistence.md`** - Lovable-style input persistence
  - Never-lose-input philosophy
  - Tiered storage (localStorage < 1KB, IndexedDB for larger)
  - Cross-project state management
  - Draft recovery banners

### Changed
- **`05-features/02-file-management/00-overview.md`** updated
  - Added External File Safety and Trash System components
  - Updated Key Features section
- **`05-features/07-history-system/01-git-integration.md`** updated
  - Move/Rename operations now trigger **immediate commits** (bypass debounce)
  - Added "Commit Required" column to Action Types table
  - Enables full reversibility via git revert

### Summary
- **New files:** 3 specification documents
- **Safety features:** Consent dialogs, trash bin, input persistence
- **Reversibility:** All moves/renames create instant Git commits

---

## [2.3.0] - 2026-01-30

### Automation Pipeline Specification Suite

Comprehensive expansion of the Automation Pipeline feature with integration specs and full E2E test coverage.

### Added
- **`05-features/27-automation-pipeline/28-res-integration.md`** - Resilient Execution System integration
  - Self-correction, multi-model consensus, checkpoint/rollback mechanisms
  - Database schema for RES configuration and execution logging
  - React components for configuration and live monitoring
- **`05-features/27-automation-pipeline/29-prompt-import-tests.md`** - 12 E2E test cases
- **`05-features/27-automation-pipeline/30-pipeline-creation-tests.md`** - 18 E2E test cases
- **`05-features/27-automation-pipeline/31-variable-resolution-tests.md`** - 16 E2E test cases
- **`05-features/27-automation-pipeline/32-stage-execution-tests.md`** - 20 E2E test cases
- **`05-features/27-automation-pipeline/33-branching-tests.md`** - 18 E2E test cases
- **`05-features/27-automation-pipeline/34-parallel-execution-tests.md`** - 16 E2E test cases

### Changed
- **`05-features/27-automation-pipeline/00-overview.md`** updated to v1.5.0
  - Added Integration Specifications section
  - Added E2E Test Specifications section (6 phase-based test files)
  - Added Architecture Reference link to `99-architecture-diagram.md`
  - Total specification files: 27 → 36
- **`.lovable/memories/features/automation-pipeline.md`** updated with full file count
- **`00-master-index.md`** updated to v2.3.0
  - Total files: 292+ → 328+
  - Feature specs: 140+ → 176+
  - Full 27-automation-pipeline section (36 files) indexed

### Verified
- All 36 automation-pipeline file references validated against filesystem
- Cross-references corrected for actual file names (01-24 core specs)

### Summary
- **New files:** 7 specification documents
- **E2E test cases:** 100 total across 6 test files
- **Coverage:** Phases 1-6 (Prompt Import → Parallel Execution)

---

## [2.2.0] - 2026-01-29

### .lovable Directory Consolidation

Consolidated all historical reports and planning documents into two archive files for cleaner project structure.

### Added
- **`.lovable/audit-history.md`** - Consolidated archive of 6 consistency reports
- **`.lovable/standards-archive.md`** - Consolidated archive of 4 planning documents

### Removed
- `.lovable/cleanup-report-2026-01-29.md` → archived
- `.lovable/consistency-audit-report.md` → archived
- `.lovable/consistency-check-report-2026-01-29.md` → archived
- `.lovable/diagram-consistency-report-2026-01-29.md` → archived
- `.lovable/final-consistency-check-2026-01-29.md` → archived
- `.lovable/final-consistency-report.md` → archived
- `.lovable/improvement-plan-94-to-99.md` → archived
- `.lovable/standardization-plan.md` → archived
- `.lovable/reviewer-feedback.md` → archived
- `.lovable/plan.md` → archived

### Result
- **Before:** 11 files in `.lovable/`
- **After:** 3 items (2 archive files + memories folder)
- **Reduction:** 10 individual files → 2 consolidated archives

---

## [2.1.0] - 2026-01-29

### Features Overview Update

Comprehensive update to the features overview with accurate file counts and implementation roadmap.

### Added
- **`09-diagrams/08-feature-dependency-diagram.md`** - Complete 25-feature Mermaid dependency graph
- **Implementation Priority** section in `05-features/00-overview.md` - 8-phase roadmap
- **File Distribution** table showing category breakdowns
- **Summary Statistics** section with totals

### Changed
- **`05-features/00-overview.md`** updated to v1.4.0
  - Feature folders: 24 → 25 (added 25-ai-enhancements)
  - Total files: 119 → 140+
  - Added accurate per-folder file counts
  - Linked to Feature Dependency Diagram
- **`09-diagrams/00-overview.md`** updated to v1.1.0
  - Diagram count: 8 → 10
  - Added 07-folder-structure-diagram.md
  - Added 08-feature-dependency-diagram.md
- **`99-consistency-report.md`** updated to v7.4.0
  - Total files scanned: 290+ → 292+
  - Diagram files section added
  - Cross-references: 3,200+ validated
- **`00-master-index.md`** updated to v2.1.0
  - Added Changelog section to Quick Navigation
  - Diagrams count: 8 → 10
  - Total files: 290+ → 292+

### Verified
- All 25 feature folders indexed
- All 10 diagram files indexed
- Cross-references validated across overview files

---

## [2.0.0] - 2026-01-29

### Major Restructuring Release

This release represents a comprehensive audit and restructuring of the entire specification system.

### Added
- **`01-ideas/06-golang-search-cli.md`** - Golang CLI search tool concept documentation
- **`api/types.ts`** - TypeScript API client types (918 lines, auto-generated from OpenAPI)
- **`05-features/25-ai-enhancements/`** - Complete AI enhancements directory (33 files)
  - Offline storage, voice resilience, plan mode, cross-project memory
  - Files numbered 00-32 following code-generation system convention
- **`09-diagrams/06-feature-dependency-graph.md`** - Feature dependency visualization
- **`09-diagrams/07-folder-structure-diagram.md`** - Mermaid folder structure diagram
- **`.lovable/consistency-check-report-2026-01-29.md`** - Audit trail documentation

### Changed
- **Master Index (`00-master-index.md`)** updated to v2.0.0
  - Total file count: 172+ → 290+
  - Ideas section: 7 → 8 files
  - Feature Specs: 75+ → 140+ files
  - CLI Tools: 35+ → 39 files
  - Code Generation: 17 → 34 files
  - Diagrams: 7 → 8 files
  - Prompts: 15 → 21 files
  - API: 1 → 2 files

### Fixed
- Restored missing `05-features/25-ai-enhancements/` directory to master index
- Corrected broken link to `09-diagrams/06-feature-dependency-graph.md`
- Fixed missing reference to `05-features/22-golang-search-cli/18-full-site-crawler.md`

### Verified
- 100% link validity across all 290+ indexed files
- All cross-references validated
- Numeric prefix conventions confirmed (00-32 for code-generation, 00-25 for features)

---

## [1.8.0] - 2026-01-28

### Added
- Initial consistency check framework
- Cross-reference validation system

### Changed
- Standardized file naming conventions across all directories

---

## [1.0.0] - 2026-01-15

### Initial Release
- Core folder structure established
- 22 feature specification folders created
- Master index and overview files initialized

---

## Versioning

This project follows [Semantic Versioning](https://semver.org/):
- **MAJOR**: Breaking structural changes
- **MINOR**: New sections or significant additions
- **PATCH**: Content updates and fixes
