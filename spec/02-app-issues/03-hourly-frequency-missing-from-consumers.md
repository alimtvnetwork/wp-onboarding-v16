# Issue: Hourly Frequency Case Missing From All Consumers

> **ID:** 03-hourly-frequency-missing-from-consumers
> **Date:** 2026-02-22
> **Category:** Enum/Consumer Synchronization
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** When `SnapshotFrequencyType::Hourly` was added as a new enum case, all consumer files (validation allow-lists, UI selects, JS constant maps, cron registration, timing helpers, recurrence mapping, migration helper, UI visibility logic) needed updating simultaneously.
2. **Where it happened:** `SnapshotFrequencyType` enum and 7 consumer files across `Snapshot/Traits/`, `templates/`, and `Helpers/`.
3. **Symptoms and impact:** Without a checklist, any missed consumer would silently reject the new value (validation), hide it from the UI (selects), or fall through to a `default` branch (switch statements), producing incorrect scheduling behavior.
4. **How it was discovered:** Proactively identified during S-024 implementation. No production bug occurred because all consumers were updated in a single changeset — but the lack of a formal checklist meant future additions could easily miss a consumer.

## Root Cause Analysis

1. **Direct cause:** No documented checklist existed for adding a new enum case to a scheduling-related enum.
2. **Contributing factors:** Consumers are spread across 7+ files in different directories (traits, templates, helpers). There is no automated tooling to detect incomplete enum wiring.
3. **Triggering conditions:** Any new case added to `SnapshotFrequencyType` (or similar domain enums with UI + cron + validation consumers).
4. **Why the existing spec did not prevent it:** The enum standard (`enums-standard` memory) defined naming and method conventions but did not include a consumer-update checklist.

## Fix Description

1. **What was changed in the spec:** Created `/spec/01-app/enum-consumer-checklist.md` — a mandatory 10-point checklist for adding any new enum case.
2. **New rules or constraints added:** Every new enum case must update: enum file, validation allow-lists, UI selects, JS constant maps, switch/match statements, cron registration (if scheduling), timing helpers (if scheduling), migration helper, UI visibility logic, and memory/inventory docs.
3. **Why the fix resolves the root cause:** The checklist explicitly enumerates every consumer type, preventing omission by oversight.
4. **Config changes or defaults affected:** None.
5. **Logging or diagnostics required:** None.

## Prevention and Non-Regression

1. **Prevention rule:** When adding a new enum case, apply the 10-point checklist at `/spec/01-app/enum-consumer-checklist.md` before marking the task complete.
2. **Acceptance criteria:** Search for all references to the enum name across the codebase. Every file that references the enum must handle the new case. No `default` branch should silently swallow a new scheduling frequency.
3. **Guardrails or linting policies:** `grep -r 'SnapshotFrequencyType' --include='*.php'` must return the new case in every match.
4. **References to updated spec sections:** `/spec/01-app/enum-consumer-checklist.md`

## TODO and Follow-Ups

1. None — all consumers were updated in S-024.

## Done Checklist

- [x] Spec updated under `/spec/01-app/`
- [x] Issue write-up created under `/spec/02-app-issues/`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable — N/A (single-pass fix)
