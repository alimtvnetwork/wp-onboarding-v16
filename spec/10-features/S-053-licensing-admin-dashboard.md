# S-053 — Licensing Admin Dashboard

> **Status:** Spec Complete  
> **Priority:** Low  
> **Dependencies:** Licensing Go server, React frontend

---

## Overview

Enhance the existing Licensing page (`src/pages/Licensing.tsx`) with dashboard-level analytics and management features. The page already has license CRUD, audit logs, and health badge. This spec adds visual analytics, batch operations, and expiration alerts.

## Current State

- **Licensing page** exists with Licenses tab (table) + Audit Log tab
- **Go backend** has `LicenseService` (CRUD), `AuditService` (log/list), key generation
- **React components**: `LicenseTable`, `CreateLicenseDialog`, `LicenseDetailPanel`, `AuditLogList`, `LicensingHealthBadge`, status/type badges
- **Types**: `License`, `AuditLog` in `src/types/licensing.ts`

## Features to Add

### 1. Analytics Summary Cards (enhance existing stats row)

Expand the current 4-stat row to include:
- **Total Licenses** (existing)
- **Active** (existing)
- **Expired** (existing)
- **Revoked** — count of revoked licenses
- **Expiring Soon** — licenses expiring within 30 days
- **Total Activations** — sum of current activations across all licenses

### 2. License Distribution Charts

Add a new "Analytics" tab alongside Licenses and Audit Log:
- **By Product** — pie chart showing license count per product type
- **By Type** — pie chart (perpetual vs subscription vs trial)
- **By Status** — bar chart (active / expired / revoked / suspended)
- **Creation Timeline** — area chart showing licenses created over time (last 90 days)

### 3. Expiration Alerts Panel

In the Analytics tab or as a sidebar:
- List licenses expiring within 7, 14, and 30 days
- Color-coded urgency (red < 7d, amber < 14d, yellow < 30d)
- Quick action: Extend / Revoke from the alert row

### 4. Batch Operations

Add to the Licenses tab:
- Multi-select checkboxes on the license table
- Batch actions: Revoke, Extend 30 days, Export CSV
- Confirmation dialog for destructive actions

### 5. Backend Endpoints Required

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/licenses/stats` | Aggregated stats (counts by status/product/type, expiring soon) |
| POST | `/admin/licenses/batch/revoke` | Batch revoke by IDs |
| POST | `/admin/licenses/batch/extend` | Batch extend by IDs + duration |
| GET | `/admin/licenses/export` | CSV export of all licenses |

### 6. Go Service Changes

Add to `LicenseService`:
- `Stats() apperror.Result[LicenseStats]` — aggregated queries
- `BatchRevoke(ids []int64) apperror.Result[int]` — bulk status update
- `BatchExtend(ids []int64, days int) apperror.Result[int]` — bulk expiry update

## Implementation Order

1. Add Go `Stats` endpoint + service method
2. Add React Analytics tab with charts (use recharts, already installed)
3. Add expiration alerts panel
4. Add batch operations to license table
5. Add CSV export

## UI Notes

- Use recharts (already in project) for all charts
- Follow existing design patterns from `PublishAnalyticsTab.tsx`
- Use semantic color tokens from the design system
