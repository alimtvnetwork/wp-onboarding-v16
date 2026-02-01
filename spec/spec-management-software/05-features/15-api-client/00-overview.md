# API Client

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-28  

---

## Overview

HTTP client configuration, request/response interceptors, and React Query integration.

**Cross-References:**
- [State Management](../16-state-management/00-overview.md)
- [Error UI](../13-error-ui/00-overview.md)

---

## Components

| # | Component | Description |
|---|-----------|-------------|
| 01 | [HTTP Client](./01-http-client.md) | Axios config, interceptors, React Query |

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Request Interceptors | Auth token injection, logging | High |
| Response Interceptors | Error handling, token refresh | High |
| React Query Integration | Caching, optimistic updates | High |
| Retry Logic | Exponential backoff for failures | Medium |

---

## Related Specs

- [State Management](../16-state-management/00-overview.md)
- [Realtime Communication](../18-realtime/00-overview.md)

---

## Source Reference

Migrated from: `02-frontend/21-api-client.md`
