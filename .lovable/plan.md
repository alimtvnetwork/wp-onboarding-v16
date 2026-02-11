

## Plan: Improve Request Sessions UI, Store responseBody as Object, and Add `IsEmpty` to Envelope

This plan covers three areas: (1) enhanced request session visualization with copy functionality, (2) storing `responseBody` as a parsed object instead of a JSON string, and (3) adding an `IsEmpty` field to the envelope `Attributes`.

---

### 1. Store `responseBody` (and `requestBody`) as parsed objects

**Problem:** Currently `RequestSessionRecord.responseBody` and `requestBody` are typed as `string`. The backend returns JSON as a string, and the frontend re-parses it for display.

**Changes:**
- **`src/lib/api/types.ts`** -- Change `requestBody` and `responseBody` from `string` to `unknown` (object or null). This means the data is stored as a proper JS object, no re-parsing needed.
- **`src/pages/RequestSessions.tsx`** -- Update the `JsonViewer` component to accept `unknown` instead of `string`. If the value is already an object, stringify it for display. Remove the try/catch JSON.parse since data is already an object.
- **`src/lib/api/methods.ts`** (if needed) -- Ensure the API layer does not double-stringify response bodies.

### 2. Enhanced Request Sessions UI with Copy Button

**Changes to `src/pages/RequestSessions.tsx`:**

- **Copy button on detail panel header** -- Add a "Copy" icon button next to the existing Download and Delete buttons. Clicking it copies the full session detail (formatted JSON) to clipboard with a toast confirmation.
- **Copy button per tab** -- Add a small "Copy" button inside each tab (Response, Request, Headers) to copy just that section's content.
- **Full path display** -- Confirm all paths render as absolute URLs using `toAbsoluteUrl()` (already done, will verify consistency).
- **Improved `JsonViewer`** -- Add syntax-highlighted JSON display using the existing `highlight.js` dependency. Add line numbers and a copy button in the top-right corner of the code block.
- **Headers tab** -- Add a copy button to copy all headers as formatted text.

### 3. Add `IsEmpty` field to Envelope Attributes

**Problem:** When Results is empty/null, there's no explicit `IsEmpty` flag. The user wants `IsEmpty: true` when `TotalRecords` is 0 or Results is empty.

**Changes:**

- **`spec/response-envelope/envelope.schema.json`** -- Add `IsEmpty` boolean to the Attributes definition: `"IsEmpty": { "type": "boolean", "description": "true when Results is empty (TotalRecords is 0 or Results array has no items)." }`
- **`spec/response-envelope/README.md`** -- Document the `IsEmpty` field in the Attributes table.
- **All sample JSON files** -- Add `"IsEmpty": true/false` to Attributes in:
  - `envelope-minimal.json` (IsEmpty: true, since Results is empty)
  - `envelope-single.json` (IsEmpty: false)
  - `envelope-multiple.json` (IsEmpty: false)
  - `envelope-error.json` (IsEmpty: true)
  - `envelope-debug.json` (IsEmpty: false)
- **`src/lib/api/types.ts`** -- Add `IsEmpty?: boolean` to `EnvelopeAttributes`.
- **`src/lib/api/envelope.ts`** -- In `parseEnvelope`, auto-derive `IsEmpty` if not present from the backend: `env.Attributes.IsEmpty = env.Attributes.IsEmpty ?? (!Array.isArray(env.Results) || env.Results.length === 0)`. Also set `TotalRecords: 0` when IsEmpty is true and TotalRecords is undefined.

---

### Technical Summary

| File | Change |
|---|---|
| `src/lib/api/types.ts` | `requestBody`/`responseBody` to `unknown`; add `IsEmpty` to `EnvelopeAttributes` |
| `src/lib/api/envelope.ts` | Auto-derive `IsEmpty` in `parseEnvelope` |
| `src/pages/RequestSessions.tsx` | Revamp `JsonViewer` (syntax highlighting, copy button); add copy buttons to detail panel; handle object-typed bodies |
| `spec/response-envelope/envelope.schema.json` | Add `IsEmpty` to Attributes |
| `spec/response-envelope/README.md` | Document `IsEmpty` |
| `spec/response-envelope/envelope-*.json` (5 files) | Add `IsEmpty` field to all samples |

