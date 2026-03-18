# Log Retrieval Endpoint Specification

> **Version:** 1.0.0  
> **Created:** 2026-03-18  
> **Status:** Draft  
> **Applies to:** QUpload, RiseUp Asia Uploader

---

## Document Structure

| File | Description |
|------|-------------|
| [00-overview.md](./00-overview.md) | This file — purpose, scope, background |
| [01-endpoint-spec.md](./01-endpoint-spec.md) | REST API endpoint definition, request/response schema |
| [02-php-implementation.md](./02-php-implementation.md) | PHP trait, enum changes, route registration |
| [03-react-integration.md](./03-react-integration.md) | React frontend — site detail log viewer tab |
| [04-go-backend-proxy.md](./04-go-backend-proxy.md) | Go backend proxy endpoint for React→WordPress |

---

## Purpose

Provide a unified REST endpoint (`GET /logs/retrieve`) on both the **QUpload** and **RiseUp Asia Uploader** WordPress plugins that returns actual log file contents (info log, error log, stack trace) with pagination support. The React frontend will display these logs in a tabbed viewer within each site's detail page.

## Background

### Current State

| Plugin | Log Status (metadata) | Log Content Retrieval |
|--------|----------------------|----------------------|
| **RiseUp Asia** | ✅ `GET /logs/status` | ✅ `GET /error-logs` (via `ErrorLogHandlerTrait`) |
| **QUpload** | ✅ `GET /logs/status` | ❌ Not implemented |

RiseUp Asia already has a working `ErrorLogHandlerTrait` that reads log tails with configurable `max_lines` and `include_*` flags. QUpload lacks any log content retrieval endpoint.

### Problem

1. **QUpload** has no way to remotely view log file contents — only metadata via `/logs/status`.
2. **RiseUp Asia's** existing `/error-logs` endpoint works but uses a different pattern than the newer `/logs/*` namespace.
3. The **React dashboard** has no UI for viewing remote log contents from either plugin.

### Solution

1. Add `GET /logs/retrieve` to **both** plugins with a consistent request/response schema.
2. Add a **Logs tab** to the site detail page in the React dashboard.
3. Route through the Go backend as a proxy (same pattern as other WordPress API calls).

## Scope

### In Scope

- New `GET /logs/retrieve` endpoint for QUpload
- New `GET /logs/retrieve` endpoint for RiseUp Asia (alongside existing `/error-logs`)
- PHP trait (`LogRetrievalTrait`) shared pattern for both plugins
- `EndpointType::LogsRetrieve` enum case added to both plugins
- Route registration in both plugins
- Go backend proxy endpoint
- React log viewer tab on site detail page

### Out of Scope

- Replacing RiseUp Asia's existing `/error-logs` endpoint (kept for backward compatibility)
- Real-time log streaming / WebSocket-based tailing
- Log search or filtering within file contents
- Admin UI changes in WordPress (already has local viewer)
