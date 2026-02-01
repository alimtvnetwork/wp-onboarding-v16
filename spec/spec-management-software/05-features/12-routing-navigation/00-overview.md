# Routing & Navigation

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-28  

---

## Overview

Client-side routing, URL state management, keyboard shortcuts, and command palette.

**Cross-References:**
- [Route Configuration](./02-route-config.md) - Shared constants (breaks circular dependency)
- [State Management](../16-state-management/00-overview.md)
- [Dashboard](../11-dashboard/00-overview.md)

---

## Components

| # | Component | Description |
|---|-----------|-------------|
| 01 | [Route Definitions](./01-route-definitions.md) | React Router setup and protected routes |
| 02 | [Route Configuration](./02-route-config.md) | Centralized route constants and path utilities |

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Route Guards | Authentication-protected routes | High |
| Deep Linking | URL state for filters, search, selections | High |
| Keyboard Navigation | Global shortcuts for common actions | Medium |
| Command Palette | Fuzzy search for commands and files | Medium |

---

## Related Specs

- [Dashboard](../11-dashboard/00-overview.md)
- [File Management](../02-file-management/00-overview.md)

---

## Source Reference

Migrated from: `02-frontend/14-routing-navigation.md`, `02-frontend/16-keyboard-shortcuts.md`, `02-frontend/17-command-palette.md`
