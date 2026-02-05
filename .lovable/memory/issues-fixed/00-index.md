# Issues Fixed - Learning Repository

> **Purpose:** Document resolved issues with root causes and solutions for AI learning continuity.  
> **Updated:** 2026-02-02

---

## Quick Reference

| Issue | Category | File |
|-------|----------|------|
| pnpm PnP module resolution failures | Build/Dependencies | [01-pnpm-pnp-resolution.md](./01-pnpm-pnp-resolution.md) |
| pnpm v10 ignored build scripts / PnP ESM loader | Build/Dependencies | [05-pnpm-v10-build-scripts-and-esm.md](./05-pnpm-v10-build-scripts-and-esm.md) |
| SPA static file 404 errors | Backend/Routing | [02-spa-static-serving.md](./02-spa-static-serving.md) |
| WebSocket upgrade failures in middleware | Backend/WebSocket | [03-websocket-middleware.md](./03-websocket-middleware.md) |
| Global error modal not capturing API failures | Frontend/UX | [04-global-error-reporting.md](./04-global-error-reporting.md) |
| SQLite datetime scanning issues | Backend/Database | [06-sqlite-datetime-scanning.md](./06-sqlite-datetime-scanning.md) |
| Health endpoint format mismatch | Frontend/Backend | [See spec/error-resolution/01-health-endpoint-mismatch.md](../../../spec/error-resolution/01-health-endpoint-mismatch.md) |
| ZIP finalization race condition | Backend/Publish | [10-zip-finalization-race.md](./10-zip-finalization-race.md) |

---

## How to Use This Folder

1. **Before implementing:** Check if a similar issue was previously fixed
2. **When debugging:** Search for error patterns or symptoms
3. **After fixing:** Document new issues following the template in each file

---

## Issue Categories

- **Build/Dependencies** - npm, pnpm, Go module issues
- **Backend/Routing** - API routes, static file serving, SPA fallback
- **Backend/WebSocket** - Connection upgrades, middleware interference
- **Frontend/UX** - Error handling, state management, UI bugs
- **Frontend/Build** - Vite, Rollup, TypeScript compilation
