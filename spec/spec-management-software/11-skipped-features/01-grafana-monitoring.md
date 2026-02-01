# Skipped: Grafana Monitoring

**Status:** ⏸️ Skipped  
**Complexity:** High  
**Updated:** 2026-01-29  

---

## Why Skipped

For simplicity, we are not using Grafana monitoring in the initial implementation. The application is designed to run locally as a simple Go backend + React frontend without external monitoring infrastructure.

---

## What This Would Include

If implemented, Grafana monitoring would provide:

### Components Required

| Component | Purpose | Installation Complexity |
|-----------|---------|------------------------|
| Prometheus | Metrics collection | Separate binary/service |
| Grafana | Dashboard visualization | Separate binary/service |
| Node Exporter | System metrics | Additional service |
| AlertManager | Alert routing | Additional service |

### Prometheus Scrape Config

```yaml
# Would require /etc/prometheus/prometheus.yml
scrape_configs:
  - job_name: 'gsearch'
    static_configs:
      - targets: ['localhost:9090']
    scrape_interval: 15s
    metrics_path: /metrics
```

### Grafana Dashboard Panels

| Panel | Query |
|-------|-------|
| Request Rate | `rate(gsearch_requests_total[5m])` |
| Error Rate | `rate(gsearch_requests_total{status="error"}[5m])` |
| P95 Latency | `histogram_quantile(0.95, rate(gsearch_latency_seconds_bucket[5m]))` |
| Cache Hit Rate | `rate(gsearch_cache_hits_total{type="hit"}[5m]) / rate(gsearch_cache_hits_total[5m])` |
| Engine Status | `gsearch_engine_health` |

### Alerting Rules

```yaml
# Would require /etc/prometheus/rules/gsearch.yml
groups:
  - name: gsearch
    rules:
      - alert: HighSearchErrorRate
        expr: rate(gsearch_requests_total{status="error"}[5m]) / rate(gsearch_requests_total[5m]) > 0.1
        for: 5m
        labels:
          severity: warning
          
      - alert: AllEnginesBlocked
        expr: sum(gsearch_engine_health) == 0
        for: 2m
        labels:
          severity: critical
```

---

## Simple Alternative (What We Use Instead)

```bash
# CLI-based health check
gsearch health --verbose

# Console logging
gsearch search "test" --log-level info

# File-based logs (no external tools)
tail -f ./logs/gsearch.log

# SQLite metrics query
sqlite3 search.db.sqlite "SELECT * FROM search_stats ORDER BY created_at DESC LIMIT 10;"
```

---

## Revisit Criteria

Consider implementing if:
- Production deployment with SLA requirements
- Multiple team members need shared dashboards
- Historical trend analysis becomes important
- Alerting to external systems (Slack, PagerDuty) is needed

---

## Cross-References

- [Observability Spec](../05-features/22-golang-search-cli/16-observability.md) — Full specification (reference)
- [Skipped Features Overview](./00-overview.md) — Why features are skipped
