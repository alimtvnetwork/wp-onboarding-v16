# Skipped Features

**Version:** 1.0.0  
**Status:** Deferred  
**Updated:** 2026-01-29  

---

## Overview

This folder contains specifications for features that are **intentionally skipped** for the initial implementation. These features add complexity that is not necessary for a simple local development setup.

**Design Philosophy:** Keep the application simple — a Go backend and React frontend running locally without external infrastructure dependencies.

---

## Reason for Skipping

| Reason | Description |
|--------|-------------|
| **Simplicity** | The application should be easy to run locally without installing additional infrastructure |
| **Local-First** | No cloud services, containers, or external monitoring systems required |
| **Minimal Dependencies** | Avoid introducing tools that require separate installation and configuration |
| **Future-Ready** | These specs are preserved for potential future use if scaling requirements change |

---

## Skipped Features

| # | Feature | Complexity | Why Skipped |
|---|---------|------------|-------------|
| 01 | [Grafana Monitoring](./01-grafana-monitoring.md) | High | Requires separate Grafana installation, Prometheus, and dashboard setup |
| 02 | [Kubernetes Deployment](./02-kubernetes-deployment.md) | High | Overkill for local development; adds containerization complexity |
| 03 | [Centralized Logging](./03-centralized-logging.md) | Medium | Filebeat/Fluentd/Elasticsearch stack not needed for local use |

---

## When to Revisit

Consider implementing these features if:

- Deploying to production with multiple users
- Scaling to distributed infrastructure
- Compliance/audit requirements mandate centralized logging
- Team size grows and shared monitoring becomes necessary

---

## Alternative: Simple Local Monitoring

For local development, the application provides:

- **Console logging** — Direct stdout/stderr output
- **File logging** — Simple log files in `/var/log/gsearch/` or `./logs/`
- **CLI health check** — `gsearch health` command
- **SQLite-based metrics** — Query stats stored in database

No external tools required.

---

## Cross-References

- [Deployment Guide](../05-features/22-golang-search-cli/17-deployment-guide.md) — Simplified local deployment
- [Observability](../05-features/22-golang-search-cli/16-observability.md) — Full observability spec (reference only)
