# Issue #33 — Missing Delegated Section in Error Modal

> **Created:** 2026-03-23  
> **Status:** ✅ Resolved  
> **Severity:** Medium

---

## Problem

The Global Error Modal had only two top-level sections: **Backend** and **Frontend**. Delegated server logs (PHP stack traces, remote response bodies, DelegatedRequestServer metadata) were buried inside the Backend section as a nested sub-tab, making them hard to discover. The user requested a dedicated top-level "Delegated" section multiple times.

## Root Cause

The original implementation treated delegated/PHP logs as a subset of backend data, hiding them behind a nested tab within BackendSection. This was architecturally incorrect — delegated logs represent a distinct error source (the downstream WordPress PHP server), not a subset of the Go backend.

## Resolution

1. Created `src/components/errors/DelegatedSection.tsx` as a standalone top-level component
2. Added "Delegated" as a third top-level button (alongside Backend/Frontend) in GlobalErrorModal
3. The Delegated button is **always visible** (orange-themed Globe icon), showing an empty-state message when no delegated data exists. Responsive label: "Delegated Logs" (desktop) / "Delegated" (mobile).
4. Removed the delegated sub-tab from BackendSection to avoid duplication
5. The compact and full error reports already included delegated logs via `buildDelegatedLogsSection()` — no changes needed there

## Files Changed

- `src/components/errors/DelegatedSection.tsx` — New standalone delegated section
- `src/components/errors/GlobalErrorModal.tsx` — Third top-level section button
- `src/components/errors/BackendSection.tsx` — Removed nested delegated sub-tab
- `spec/01-app/error-manage/delegated-error-logs.md` — Updated spec
