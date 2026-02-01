# Memory: features/telemetry-dashboard

**Updated:** 2026-01-30  
**Spec Location:** `spec/spec-management-software/05-features/06-ai-integration/14-telemetry-dashboard.md`

---

## Overview

Real-time monitoring dashboard for AI execution success rates, failure patterns, and escalation metrics.

---

## Key Metrics

| Metric | Target | Description |
|--------|--------|-------------|
| Success Rate | ≥ 98% | Tasks completed successfully |
| Avg Attempts | ≤ 1.5 | Average retries per task |
| Escalation Rate | ≤ 2% | Tasks requiring human input |

---

## Dashboard Sections

1. **Summary Cards** — Success rate, avg attempts, escalation rate, active tasks
2. **Success Rate Chart** — Time series with 98% target line (Recharts)
3. **Failure Distribution** — Pie chart by category
4. **Recovery Path Analysis** — Bar chart (retry, self-fix, consensus, escalate)
5. **Model Performance Table** — Sortable by success rate, latency, tasks
6. **Recent Events Table** — Failures/escalations with priority badges

---

## Real-Time Features

- WebSocket stream for live updates
- Auto-refresh toggle (30s default)
- Instant status change propagation
- Reconnection on disconnect

---

## React Components

- `TelemetryDashboard` — Main page layout
- `SummaryCards` — KPI cards with trends
- `SuccessRateChart` — Line chart with target line
- `FailureDistributionChart` — Donut chart
- `RecoveryPathChart` — Horizontal bar chart
- `ModelPerformanceTable` — Sortable data table
- `RecentEventsTable` — Clickable event rows
- `useTelemetryStream` — WebSocket hook

---

## Alerting

- `success_rate_critical` — Below 95% → all channels
- `success_rate_warning` — Below 98% → in-app
- `escalation_spike` — 2x increase → email
- `model_degradation` — Model < 90% → email
