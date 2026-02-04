
## What’s actually wrong (root cause)
- The backend API **is** running at:
  - **Base:** `http://localhost:8080/api/v1`
  - **Health:** `GET http://localhost:8080/api/v1/health`
  - **WebSocket:** `ws://localhost:8080/ws`
  - (Confirmed in `backend/config.json` port `8080` and `backend/internal/api/router.go` with `PathPrefix("/api/v1")`.)
- `http://localhost:8080/api/v1` returning **404 is expected today** because there is **no route** registered at `/api/v1` (only `/api/v1/health`, `/api/v1/sites`, etc.).
- The frontend is falsely reporting “Backend disconnected” because `BackendStatus.tsx` currently treats a healthy response as “not connected”:
  - Backend returns `{"status":"healthy", ...}`
  - Frontend only accepts `success: true` or `status: "ok"`
  - Result: it flags disconnected and generates an E9005 report even when the backend is up.

## Goals
1. Make backend health checks and frontend connectivity detection consistent and correct.
2. Ensure the error modal and copied diagnostics show:
   - raw `VITE_API_URL` / `VITE_WS_URL` (even if ignored in hosted preview)
   - resolved/effective API + WS URLs (with protocol + host + port)
3. Remove the confusion around `/api/v1` by adding an API index endpoint (optional but recommended).
4. Update specs so future contributors don’t reintroduce the mismatch.

---

## Implementation plan

### 1) Backend: standardize `/api/v1/health` response format (match spec)
**Files**
- `backend/internal/api/handlers/handlers.go`

**Change**
- Update `Health()` to return the project’s standard envelope:
  - `{"success": true, "data": { "status": "ok", "timestamp": "..." }}`
- This matches `spec/wp-plugin-publish/01-backend/11-rest-api-endpoints.md` “Response Format” section and prevents frontend ambiguity.

**Why**
- Today, `/health` is the only endpoint that does not follow the standard `{success, data}` shape.

---

### 2) Backend: add a friendly API index route so `/api/v1` is not 404
**Files**
- `backend/internal/api/router.go`
- `backend/internal/api/handlers/handlers.go`

**Change**
- Register `GET /api/v1` (and `/api/v1/`) returning a small JSON document like:
  - API name/version
  - links: `health`, `ws`, etc.
Example payload:
```json
{
  "success": true,
  "data": {
    "name": "WP Plugin Publish API",
    "version": "v1",
    "health": "/api/v1/health",
    "ws": "/ws"
  }
}
```

**Why**
- Users naturally test the base URL in a browser; this prevents the “API not running” false alarm.

---

### 3) Frontend: fix `BackendStatus` connectivity logic so it can’t mis-diagnose JSON as disconnected
**Files**
- `src/components/shared/BackendStatus.tsx`

**Change**
- Replace the current “connected iff success===true OR status===ok” rule with:
  1. If response body is HTML → `E9005` (misrouting / hosted preview / wrong base)
  2. If fetch throws → `E9003` (network/unreachable)
  3. If response is JSON:
     - If HTTP status is 2xx → connected
     - Else → disconnected/unhealthy with a message including status code and parsed JSON preview

**UI improvement**
- Make the banner message reflect the actual reason (HTML vs network vs non-2xx JSON), instead of always claiming “HTML instead of JSON”.

**Outcome**
- When backend returns `{"success":true,"data":...}` from `/health`, the banner will disappear and E9005 will no longer be generated incorrectly.

---

### 4) Frontend: make diagnostics + error modal show both “raw env” and “effective resolved” values (with port/protocol)
Right now, several places incorrectly label **resolved** origin as “VITE_API_URL”, which hides the real env var state (and can be especially confusing when loopback envs are intentionally ignored in hosted previews).

**Files**
- `src/lib/diagnostics.ts`
- `src/lib/api.ts`
- `src/components/errors/GlobalErrorModal.tsx`
- (optional) `src/components/shared/CopyDiagnosticsButton.tsx` (only if types change propagate)

**Changes**
- In `diagnostics.ts`, include:
  - `envViteApiUrl` = raw `import.meta.env.VITE_API_URL` (or “(not set)”)
  - `envViteWsUrl` = raw `import.meta.env.VITE_WS_URL` (or “(not set)”)
  - `resolvedApiOrigin` = `resolveApiOrigin()` (may be null if ignored)
  - `effectiveUiOrigin` = `window.location.origin`
  - `apiBase` and `apiBaseAbsolute` (already present)
  - `resolvedWsUrl` = `resolveWsUrl()` (already present)
- Update formatting output so the copied diagnostics clearly shows:
  - Raw env values
  - Resolved/effective URLs (including port)
  - UI origin

- In `src/lib/api.ts` (E9005 generation), add raw env fields into `error.context` so the Global Error Modal always has them even if the backend is unreachable.

- In `GlobalErrorModal` “Request Info” tab:
  - Show `VITE_API_URL (raw)`, `Resolved API origin`, `Effective API base (absolute)`
  - Show `VITE_WS_URL (raw)`, `Resolved WS URL`
  - Keep “Requested URL” and “Configured API base” as today, but add the absolute version to address your “include host + port” requirement.

---

### 5) Specs: update docs so implementation and expected behavior match
**Files**
- `spec/wp-plugin-publish/01-backend/11-rest-api-endpoints.md`
  - Add `/api/v1/health` endpoint documentation with the standardized `{success:true,data:{...}}` response
  - Add `/api/v1` index endpoint (if implemented)
- `spec/wp-plugin-publish/02-frontend/26-ui-patterns.md`
  - Update BackendStatus “Detection Logic” to reflect the real implementation rules (HTML vs network vs non-2xx)
  - Update Error Modal requirements to explicitly require:
    - raw env vars
    - resolved/effective URLs

---

### 6) Versioning + changelog
**Files**
- `public/version.json`
- `CHANGELOG.md`

**Change**
- Bump app version (e.g., `1.2.1`) since this fixes production-facing diagnostics and connectivity detection.

---

## How you’ll verify it (end-to-end)
1. Run locally: `.\run.ps1 -r`
2. Open:
   - `http://localhost:8080/api/v1` → should return JSON (no 404) if we add index
   - `http://localhost:8080/api/v1/health` → should return `{success:true,...}` JSON
3. In the UI:
   - BackendStatus banner should NOT appear when backend is running
   - “Copy Diagnostics” should include:
     - UI origin with port
     - API base absolute URL
     - raw VITE env vars (even if not set)
     - resolved WS URL
4. In hosted Lovable preview:
   - Banner should appear (expected)
   - Error modal should clearly show:
     - raw env vars
     - resolved values (and if loopback is ignored, it should be obvious why the effective base is not localhost)

## Notes / expectations management (important)
- If you are serving the UI from the Go backend at `http://localhost:8080`, it is valid for `VITE_API_URL` to be **unset**. Requests should work as relative `/api/v1/...`.
- The key fix is: the app must not treat a healthy JSON response as “disconnected,” and diagnostics must report both raw env inputs and effective resolved outputs so there’s no ambiguity.


## Additional Strict Insturctions to follow

Hey, I have seen this mistake to be happen by many times, Lovable and other AI. So make sure that you update your, uh, memory regarding this type of API retrieval and two-way verification. Always before running the code or any endpoint confirmation, just do both ways verification, do some analysis, and also, if you are reaching out to any API and thinking like it's going to give the result, first check the backend. That should be your goal that it's going to have an endpoint there and it's going to resolve some JSON or not. So even with all these specifications where I was very clear, you made several mistake and wasted one hour. That's kind of bad. So for this reason, I want this to have a- have a, like, memory inside your Lovable, but also as a documentation, as a spec file. Like, never do this in terms of frontend and backend endpoint communication. I want a folder like, uh, error resolution, okay? Inside this you write all this things that happened that wasted time, how it could waste people's time, and that could be a retrospective that NoAI should make, and the details should be very clear so that they can fix the issues. If you have any more con- confusion and questions, feel free to let me know.
