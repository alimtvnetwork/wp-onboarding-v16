# Rule 10 Sweep Plan — Blank Line Before Control Structures

> **Created:** 2026-02-21
> **Rule:** Blank line before `if`/`for`/`foreach`/`while` when preceded by non-brace statements

## Status

- [x] AgentRemoteActionTrait.php — Fixed (3 violations)
- [ ] **Snapshot/Traits/** — ~15 files, high density of `$stmt->execute(); if(...)` patterns
- [ ] **Admin/Traits/** — ~9 files, likely `$result = ...; if(is_wp_error(...))` patterns
- [ ] **Logging/Traits/** — ~7 files
- [ ] **Helpers/Traits/** — ~3 files
- [ ] **Agent/Traits/** — remaining files (AgentRemoteCoreTrait, AgentCrudTrait, AgentCrudReadTrait, AgentLoggingTrait, AgentRemoteTrait)
- [ ] **Database/Traits/** — ~14 files
- [ ] **Database/*.php** — root DB classes (Orm, RootDb, Database, etc.)
- [ ] **ErrorHandling/*.php** — 4 files
- [ ] **Core/*.php** — Plugin.php and others
- [ ] **Templates/** — PHP templates (admin-*.php)
- [ ] **Root files** — riseup-asia-uploader.php, Autoloader.php

## Pattern to Search

```
[non-blank, non-brace line]
[if|for|foreach|while] (
```

Any line that is NOT blank and NOT ending in `}` followed directly by a control structure keyword is a violation.

## Also Applies To

- **TypeScript** files in `src/` (if any contain this pattern)
- **Go** files (if any exist in the project)
