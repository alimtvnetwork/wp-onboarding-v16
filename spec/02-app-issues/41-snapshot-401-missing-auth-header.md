# Issue #41: Snapshot Endpoints Return 401 "Missing Authorization Header"

## Status: Resolved

## Symptoms

- PHP error logs show 401 on `/wp-json/riseup-asia-api/v1/snapshots/settings` and `/snapshots/providers`
- Stack trace originates from `AuthCredentialTrait.php:178` → `resolveAndAuthenticate` → `checkAuthenticatedCapability`
- User-Agent in the failing requests is Chrome browser, NOT the Go backend's custom UA
- Dashboard snapshot settings tab shows loading state indefinitely with no error feedback

## Root Cause Analysis

### Two distinct failure paths:

**Path 1 — Direct Browser Requests (the PHP logs)**
The 401 errors in the PHP logs come from direct browser visits to the WordPress REST API
endpoints. The user agent `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36...`
confirms these are NOT proxied through the Go backend. The Go client always sets
`Authorization: Basic ...` via `Client.setStandardHeaders()` (Client.go:92) and uses a
custom user agent via `header.UserAgentValue`. Direct browser hits to authenticated endpoints
are expected to fail with 401 — this is correct behavior from the PHP plugin.

**Path 2 — Frontend Error Swallowing (the actual UI bug)**
When the Go backend proxies snapshot requests and the remote site returns 401 (e.g., due to
revoked application passwords or credential mismatch), the `handleSiteActionById` handler
correctly forwards the 401 via `resolveHttpStatus()`. However, the frontend's
`useRemoteSnapshots` hook has `suppressGlobalError: true` on all queries and only calls
`captureException` in a passive `useEffect` — it never shows a toast or opens the error
modal for query-level failures. Users see empty/loading states with no indication of
what went wrong.

## Affected Files

| File | Role |
|------|------|
| `backend/internal/wordpress/Client.go:92` | Sets Authorization header — working correctly |
| `backend/internal/api/handlers/HandlerFactory.go:144-151` | Forwards 401 via `respondErrorWithDelegated` — working correctly |
| `backend/internal/api/handlers/Response.go:274-299` | `resolveHttpStatus` extracts PHP status — working correctly |
| `src/hooks/useRemoteSnapshots.ts:113-135` | **BUG**: Passive error capture, no user-facing feedback for query errors |

## Fix

### Frontend (`src/hooks/useRemoteSnapshots.ts`)
- Add toast notifications with "View Error" action for failed snapshot queries
  (settings, providers, snapshots list)
- Show specific messaging for 401 errors: "Authentication failed — check site credentials"
- Reuse existing `handleSnapshotError` pattern from mutations

### No Go backend changes required
The Go backend correctly:
1. Sends `Authorization: Basic` on all requests (`setStandardHeaders`)
2. Forwards PHP-side HTTP status codes via `resolveHttpStatus`
3. Includes delegated error context via `respondErrorWithDelegated`

## Prevention
- All `useQuery` hooks with `suppressGlobalError: true` MUST include explicit
  error feedback (toast/inline diagnostic) — passive `captureException` alone is
  insufficient for user-visible operations.
