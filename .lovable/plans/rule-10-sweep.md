# Formatting Sweep Plan — Rules 9 & 10

> **Created:** 2026-02-21
> **Rules:**
> - Rule 9: Multi-line args for signatures, calls (>2 args), and arrays (>2 items)
> - Rule 10: Blank line before `if`/`for`/`foreach`/`while` when preceded by non-brace statements

## Status

- [x] AgentRemoteActionTrait.php — Fixed (Rule 9: 11 calls, Rule 10: 3 violations)
- [ ] **Snapshot/Traits/** — ~15 files, high density of `$stmt->execute(); if(...)` and multi-arg calls
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

## Rule 9 Patterns to Search

```
functionCall($arg1, $arg2, $arg3, ...);  // >2 args on one line
array(item1, item2, item3, ...)          // >2 items on one line
```

## Rule 10 Patterns to Search

```
[non-blank, non-brace line]
[if|for|foreach|while] (
```

## Also Applies To

- **TypeScript** files in `src/` (if any contain these patterns)
- **Go** files (if any exist in the project)
