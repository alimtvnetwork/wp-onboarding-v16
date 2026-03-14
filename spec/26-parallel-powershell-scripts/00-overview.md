# 26 — Parallel PowerShell Scripts: Overview

> **Created:** 2026-03-14  
> **Status:** Implemented  
> **Relates to:** [spec/12-powershell-integration](../12-powershell-integration/), [parallel-work-sync-output](../12-powershell-integration/parallel-work-sync-output.md)

---

## Purpose

Redesign the `-uas` (Upload All Sites) pipeline into a fully modular, parallel-first architecture where **all uploadable plugins** (including QUpload itself) are zipped and deployed to **all enabled sites** simultaneously.

## Current Limitations

1. **QUpload is hardcoded as excluded** — `Get-UploadablePlugins` always skips the `defaultQUploader` slug. QUpload can and should update itself via its own API.
2. **Single monolithic module** — `mode-upload-all-sites.ps1` (460 lines) handles ZIP, upload, sync, parallel, and summary logic in one file.
3. **No atomic scripts** — ZIP and upload operations are embedded in orchestrators, making them untestable and unreusable.
4. **Plugin-serial within sites** — Current parallel mode launches one job per `plugin×site` pair but doesn't leverage the fact that plugins are independent workstreams.

## New Architecture (Summary)

```
run.ps1 (-uas)
  │
  ├── Phase 1: Plugin Discovery
  │     └── Get-UploadablePlugins (reads powershell.json, respects skipPlugins)
  │
  ├── Phase 2: Parallel ZIP
  │     ├── Invoke-SinglePluginZip (atomic, one plugin)
  │     └── Invoke-ParallelPluginZip (orchestrator, fans out Phase 2 jobs)
  │
  ├── Phase 3: Parallel Upload
  │     ├── Invoke-SinglePluginUpload (atomic, one plugin → one site)
  │     └── Invoke-ParallelPluginUpload (orchestrator, fans out all plugin×site jobs)
  │
  └── Phase 4: Summary
        └── Write-UploadSummary (structured table, grouped by site)
```

### Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| QUpload included by default | QUpload can self-update via its own API; only `skipPlugins` should exclude it |
| Atomic scripts return structured results | Enables testing, reuse, and clean orchestration |
| `skipPlugins` is the sole exclusion mechanism | No more hardcoded slug filtering; all exclusion via config |
| Summary is a separate function | Can be reused by `-ua`, `-uas`, future modes |
| Parallel-by-default with `-sync` fallback | Matches current behavior but extends to multi-plugin |

## Spec Files

| File | Contents |
|------|----------|
| [01-architecture.md](./01-architecture.md) | Module structure, data flow, file layout |
| [02-zip-scripts.md](./02-zip-scripts.md) | Single ZIP + parallel ZIP orchestrator |
| [03-upload-scripts.md](./03-upload-scripts.md) | Single upload + parallel upload orchestrator |
| [04-summary-output.md](./04-summary-output.md) | Summary printer, table format, grouping |
| [05-flags-and-config.md](./05-flags-and-config.md) | powershell.json changes, CLI flags, exclusion logic |

## Scope

- **In scope:** `-uas` mode refactor, new module files, powershell.json schema updates
- **Out of scope:** Upload script internals (`upload-plugin-U-Q.ps1`), credential management, `-ua` single-site mode (but benefits from shared summary function)
