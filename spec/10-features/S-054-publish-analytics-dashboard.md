# S-054 — Publish Analytics Dashboard

> **Status:** Spec Complete  
> **Priority:** Low  
> **Dependencies:** Publish History Go service, React frontend

---

## Overview

Enhance the existing Publish History page with a dedicated analytics dashboard. The page already has a `PublishAnalyticsTab` component (`src/components/publish-history/PublishAnalyticsTab.tsx`) with 4 charts, date range selection, and CSV/PDF export. This spec defines enhancements and missing features.

## Current State

- **PublishHistory page** has History tab + Analytics tab
- **PublishAnalyticsTab** (490 lines) includes:
  - Daily publishes bar chart
  - Success rate trend area chart
  - Duration heatmap (day × hour)
  - Per-site breakdown pie chart
  - Date range picker (7/30/90 days + custom)
  - CSV and PDF export
- **Go backend** has publish history recording + stats endpoint
- **hooks**: `usePublishAnalytics` provides aggregated data

## Features to Add

### 1. Plugin-Level Analytics

- **Per-plugin publish frequency** — bar chart showing which plugins are published most
- **Plugin success rate** — table with success/fail ratio per plugin
- Filter by plugin in the existing charts

### 2. Deployment Speed Trends

- **Average duration over time** — line chart showing if deploys are getting faster/slower
- **Duration by stage** — stacked bar chart breaking down time spent in each pipeline stage (backup, upload, activation)
- **P95 duration** — highlight slow outliers

### 3. Failure Analysis

- **Failure reasons breakdown** — categorize error messages into groups (network, activation, timeout, etc.)
- **Failure correlation** — show if failures cluster around specific sites, times, or plugins
- **Mean time to recovery** — average time between a failure and next successful publish to same site

### 4. Comparison Mode

- **Site vs Site** — compare two sites' publish metrics side by side
- **Period vs Period** — compare current period with previous period (e.g., this week vs last week)
- Show delta indicators (↑ ↓) for key metrics

### 5. Backend Endpoints Required

| Method | Path | Description |
|--------|------|-------------|
| GET | `/publish-history/analytics/plugins` | Per-plugin aggregated stats |
| GET | `/publish-history/analytics/stages` | Duration breakdown by stage |
| GET | `/publish-history/analytics/failures` | Categorized failure analysis |
| GET | `/publish-history/analytics/compare` | Period-over-period comparison |

### 6. Go Service Changes

Add to publish history service:
- `PluginAnalytics(days int) []PluginStat` — group by plugin slug
- `StageAnalytics(days int) []StageDuration` — average duration per stage
- `FailureAnalytics(days int) []FailureCategory` — categorized errors
- `CompareAnalytics(period1, period2 DateRange) ComparisonResult`

## Implementation Order

1. Per-plugin analytics (backend + frontend)
2. Stage duration breakdown chart
3. Failure analysis panel
4. Comparison mode
5. P95/outlier detection

## UI Notes

- Extend existing `PublishAnalyticsTab` or create sub-tabs within it
- Use recharts (already used in existing component)
- Follow the existing date range picker pattern
- Use `hsl(var(--...))` color tokens throughout
