# Formatting Sweep Plan — Rules 1, 4, 5, 9, 10, 11

> **Created:** 2026-02-21
> **Last updated:** 2026-02-21
> **Rules:**
> - Rule 1: Mandatory curly braces for all control structures
> - Rule 4: Blank line before `return`/`throw` (unless sole statement)
> - Rule 5: Blank line after `}` when more code follows
> - Rule 9: Multi-line args for signatures (9a), calls >2 args (9b), and arrays >2 items (9c)
> - Rule 10: Blank line before `if`/`for`/`foreach`/`while` when preceded by non-brace statements
> - Rule 11: Long string concatenations broken line-by-line

## Completed

- [x] **AgentRemoteActionTrait.php** — Rule 9: 11 calls, Rule 10: 3 violations
- [x] **Database/Traits/** — 14 files — Rule 9: 14 violations, Rule 10: 1 violation
- [x] **Go Handlers** (Phase 1) — 8 files — `respondError` expanded to multi-line, multi-arg vars fixed
- [x] **Snapshot/Traits/** — 67 files across 5 sweeps:
  - Orchestrator*, Exporter*, Manager*, Detector*, NativeSnapshot*, NativeTableExport*, IncrementalDiscovery*, RestoreValidation* (Phase 4a)
  - Restore*, Worker*, NativeSnapshotCreate*, OrchestratorPlugin*, OrchestratorRegistration* (Phase 4b)
  - Worker*, Cleaner*, Scheduler*, Restore* (Worker/Cleaner/Scheduler sweep)
  - Analyzer*, Detector*, ExporterBuild*, ExporterPublicApi*, Import*, IncrementalCore*, IncrementalDelta*, IncrementalExport*, IncrementalRegistration*, Manager*, NativeSnapshotCrud*, NativeSnapshotExec* (final sweep)
- [x] **Traits/Route/** — 2 files — already clean, no violations found
- [ ] **Admin/Traits/** — ~9 files — likely `$result = ...; if(is_wp_error(...))` patterns
- [ ] **Logging/Traits/** — ~7 files
- [ ] **Helpers/Traits/** — ~3 files
- [ ] **Agent/Traits/** — remaining files (AgentRemoteCoreTrait, AgentCrudTrait, AgentCrudReadTrait, AgentLoggingTrait, AgentRemoteTrait)
- [ ] **Database/*.php** — root DB classes (Orm, RootDb, Database, etc.)
- [ ] **ErrorHandling/*.php** — 4 files
- [ ] **Core/*.php** — Plugin.php and others
- [ ] **Templates/** — PHP templates (admin-*.php)
- [ ] **Root files** — riseup-asia-uploader.php, Autoloader.php
- [ ] **Go Services** — `dbutil.QueryOne`, `dbutil.Exec` with 4+ args across 14 service subdirectories
- [ ] **TypeScript** files in `src/` (if any contain these patterns)

## Rule Patterns to Search

### Rule 9
```
functionCall($arg1, $arg2, $arg3, ...);  // >2 args on one line
array(item1, item2, item3, ...)          // >2 items on one line
```

### Rule 10
```
[non-blank, non-brace line]
[if|for|foreach|while] (
```
