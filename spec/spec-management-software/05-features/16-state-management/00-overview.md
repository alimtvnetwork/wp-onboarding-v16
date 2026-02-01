# State Management

**Version:** 2.0.0  
**Status:** Complete  
**Updated:** 2026-01-31  

---

## Overview

Application state architecture using React Query for server state, Context API for global state, and URL state for deep linking.

**Cross-References:**
- [API Client](../15-api-client/00-overview.md)
- [Routing & Navigation](../12-routing-navigation/00-overview.md)

---

## Components

| # | Component | Description |
|---|-----------|-------------|
| 01 | [State Architecture](./01-state-architecture.md) | Zustand, React Query, Context patterns |

---

## State Layers

| Layer | Technology | Use Case |
|-------|------------|----------|
| Server State | React Query | API data, caching, sync |
| Global State | Context API | Auth, theme, sidebar |
| Local State | useReducer | Complex component state |
| URL State | URLSearchParams | Filters, search, selections |

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Server State Caching | React Query with staleTime/gcTime | High |
| Optimistic Updates | Immediate UI feedback on mutations | High |
| URL State Sync | Deep linking for shareable URLs | Medium |
| State Persistence | LocalStorage for user preferences | Medium |

---

## Related Specs

- [API Client](../15-api-client/00-overview.md)
- [Routing & Navigation](../12-routing-navigation/00-overview.md)

---

## Source Reference

Migrated from: `02-frontend/18-state-management.md`
