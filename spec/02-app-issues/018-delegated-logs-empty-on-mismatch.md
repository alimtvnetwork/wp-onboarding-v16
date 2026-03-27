# Issue 018 — Delegated Logs Empty on Retrieve Mismatch Error

## Symptom

When the Remote Logs Panel detects a data mismatch (status reports files but retrieve returns no readable content), the Global Error Modal opens with error code **E9003** but the **Delegated Logs** tab shows "No delegated server data available."

## Root Cause

### Error Construction (Frontend — `RemoteLogsPanel.tsx:297-302`)

The mismatch check creates a **plain `new Error(...)`**:

```typescript
const mismatchError = new Error(
  "Remote logs status reported files, but retrieve returned no readable log content."
);
surfaceError(mismatchError, endpoint, "GET");
```

### Error Store Path (`errorStore.ts:541-610`)

`surfaceError` dispatches to `captureException` (not `captureError`) because the error is not an `ApiClientError`. `captureException` checks `isApiClientError(error)` to extract envelope context — a plain `Error` has none.

### Missing Data Flow

```
RemoteLogsPanel
  └─ api.retrieveRemoteLogs()  → SUCCESS (200 OK)
  └─ requireSuccess(response)  → returns data, discards response.envelope
  └─ mismatch detected
  └─ new Error(...)            → no envelope, no context, no delegated data
  └─ surfaceError()
       └─ captureException()   → no ApiClientError → no delegatedRequestServer
            └─ buildCapturedError() → envelopeErrors = undefined
                 └─ Delegated Logs tab = empty
```

The retrieve call **succeeded** — the backend returned 200 OK with plugin data. But the data had no readable log content. The response envelope (which may contain `Attributes.RequestDelegatedAt`) and the raw response body are discarded by `requireSuccess`.

### Two Sub-Issues

1. **Response data not preserved**: `requireSuccess` discards the envelope metadata on success.
2. **Mismatch error lacks context**: The plain `Error` carries no API response data for the error modal.

## Additional Root Cause Found

### API Key Casing Mismatch (Frontend boundary)

The `/sites/:id/remote-logs/retrieve` response is a **non-envelope JSON** payload, so `fetchRequest()` passes it through `transformKeys()`. That converts nested file objects from PascalCase to camelCase:

```json
{
  "infoLog": {
    "exists": true,
    "content": "...",
    "lines": 200
  }
}
```

But `RemoteLogsPanel` and `LogContentViewer` were still reading `Exists`, `Content`, `Lines`, etc. As a result:

- the viewer treated real files as missing
- the content score stayed zero
- the mismatch detector incorrectly raised **E9003** even though the response body already contained logs

This is why the error modal showed a populated delegated response body while the UI still claimed no readable log content.

## Solution

### Fix: Attach response data to the mismatch error

Instead of a plain `Error`, use `captureError` (the `ApiError`-aware path) with a synthetic `ApiError` that includes:
- The retrieve response body as `remoteResponseBody` context
- The endpoint and method as delegated request info
- `RequestDelegatedAt` from the envelope attributes (if present)

This ensures the Delegated Logs tab shows:
- The actual response body from the backend
- The delegated endpoint that was called
- Any envelope-level delegated metadata

### Files Modified

| File | Change |
|------|--------|
| `src/components/plugins/RemoteLogsPanel.tsx` | Capture mismatch with delegated response context and read camelCase retrieve fields |
| `src/components/plugins/LogContentViewer.tsx` | Read camelCase file fields from transformed API payload |
| `src/lib/api/types.ts` | Update log retrieve types to camelCase to match API boundary |
