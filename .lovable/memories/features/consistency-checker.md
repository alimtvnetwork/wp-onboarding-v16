# Memory: features/consistency-checker

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/`

---

## Overview

Consistency checker for specification validation with health scoring and auto-fix capabilities.

---

## Core Features

| Feature | Description |
|---------|-------------|
| Link Validation | Levenshtein distance for fuzzy matching |
| Section Verification | Required headers, structure checks |
| Health Scoring | A-F grades, target 100/100 |
| Auto-fix | Suggested corrections for broken links |

---

## Frontend

- Dashboard at `/consistency` route
- Real-time report visualization
- Direct file/folder upload via `webkitdirectory`
- React hooks for real-time reporting

---

## Validation Loop

Specifications validated in loops until reaching 99%+ consistency score.
