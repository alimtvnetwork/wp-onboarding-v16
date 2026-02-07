

# Corrective Plan: Universal Response Envelope Alignment

## Problem Statement

The current envelope implementation diverges from the intended specification in several structural ways. The corrected structure relocates Errors and MethodsStack inside the Navigation block, renames key fields, changes pagination links from integers to URL strings, and removes the separate top-level `Error` and `Additional` fields.

## Corrected Envelope Structure vs Current

```text
CORRECTED (what you want)                    CURRENT (what exists)
================================             ================================
Status { ... }                               Status { ... }              (same)
Attributes {                                 Attributes {
  RequestedAt                                   RequestedEndpoint        (wrong name)
  RequestDelegatedAt                            DelegatedEndpoint        (wrong name)
  HasAnyErrors              NEW                 TraversalSteps           (wrong location)
  IsSingle, IsMultiple, ...                     IsSingle, IsMultiple, ...
}                                            }
Results [ ... ]                              Results [ ... ]             (same)
use pointers for golang as these can be present or not
Navigation {                                 Navigation {
  NextPage (URL string)                        NextPage (integer)       (wrong type)
  PrevPage (URL string)                        PrevPage (integer)       (wrong type)
  CloserLinks [URL strings]                    Pages [integers]         (wrong name+type)

 
}

use pointers for golang as these can be present or not 
 Errors {                   MOVED HERE        ---
    BackendMessage                             Error { Code, Message }  (top-level, wrong)
    DelegatedServiceErrorStack                 Additional.DelegatedError(top-level, wrong)
    Backend []
    Frontend []
  }
use pointers for golang as these can be present or not
  MethodsStack {             MOVED HERE
    Backend [{ method, file, LineNumber }]     TraversalSteps (flat strings in Attributes)
    Frontend []
  }                                     Error { ... }              (REMOVE top-level)
 Additional { ... }                                             
```
I think there is a misunderstanding. The method stack and errors needs to be at the top level, okay? Uh, this needs to be corrected.


## Configurability Requirements

The following Navigation sub-sections are **conditionally included** based on backend settings:
- **Navigation links** (NextPage, PrevPage, CloserLinks) -- only for paginated responses, configurable defaults (perPage)
- **Errors** -- only when errors exist AND error reporting is enabled in config
- **MethodsStack** -- only when debug/traversal is enabled in config

---

## Phase-by-Phase Corrective Plan

### Phase C1: Create Root-Level Specification Folder

**Goal:** Establish a `spec/response-envelope/` folder as the single source of truth for the envelope format that all systems (Go, PHP, Frontend) must follow.

**Files to create:**
- `spec/response-envelope/README.md` -- Human-readable specification with rules, field descriptions, and configurability notes
- `spec/response-envelope/envelope-single.json` -- Sample: single item response
- `spec/response-envelope/envelope-multiple.json` -- Sample: paginated list response
- `spec/response-envelope/envelope-error.json` -- Sample: error response with Errors block
- `spec/response-envelope/envelope-debug.json` -- Sample: response with MethodsStack enabled
- `spec/response-envelope/envelope-minimal.json` -- Sample: minimal success (no Navigation)
- `spec/response-envelope/CONFIGURABILITY.md` -- Documents which sections are toggled by which settings

---

### Phase C2: Correct Go Backend Envelope Package

**Goal:** Restructure `backend/internal/envelope/envelope.go` to match the corrected spec.

**Changes:**
1. **Rename** `Attributes.RequestedEndpoint` to `RequestedAt`
2. **Rename** `Attributes.DelegatedEndpoint` to `RequestDelegatedAt`
3. **Remove** `TraversalSteps` from `Attributes`
4. **Add** `HasAnyErrors bool` to `Attributes`
5. **Remove** top-level `Error *ErrorDetail` field from `Response`
6. **Remove** top-level `Additional interface{}` field from `Response`
7. **Restructure** `Navigation` to include:
   - `NextPage` and `PrevPage` as URL strings (not integers)
   - `CloserLinks` (renamed from `Pages`) as URL strings
   - `Errors` sub-object: `BackendMessage`, `DelegatedServiceErrorStack`, `Backend`, `Frontend`
   - `MethodsStack` sub-object: `Backend` (array of `{Method, File, LineNumber}`), `Frontend`
8. **Update** all builder functions (`Success`, `Created`, `Error`, `List`, etc.)
9. **Update** `WithEndpoints()` to use new field names
10. **Replace** `WithTraversal()` and `WithDelegatedError()` with new Navigation-based methods
11. **Add** configurable URL generation for pagination links (requires knowing the request path)

**Files to change:**
- `backend/internal/envelope/envelope.go` -- Core struct and builder refactor
- `backend/internal/envelope/envelope_test.go` -- Update tests

---

### Phase C3: Correct Go Handler Utilities

**Goal:** Update `response.go` and all domain handlers that use `WithEndpoints`, `WithTraversal`, or `WithDelegatedError`.

**Changes:**
1. Update `respondList` to pass request path for URL-based pagination links
2. Update `respondError` to populate `Navigation.Errors` instead of top-level `Error`
3. Search all handler files for `.WithEndpoints(`, `.WithTraversal(`, `.WithDelegatedError(` calls and update to new API

**Files to change:**
- `backend/internal/api/handlers/response.go`
- All domain handler files (`site_handlers.go`, `plugin_handlers.go`, `publish_backup_handlers.go`, etc.)

---

### Phase C4: Correct OpenAPI Specification

**Goal:** Update `backend/api/openapi.json` schemas to reflect the corrected envelope structure.

**Changes:**
1. Update `EnvelopeAttributes` -- rename fields, add `HasAnyErrors`, remove `TraversalSteps`
2. Restructure `EnvelopeNavigation` -- URL strings for links, `CloserLinks`, nested `Errors` and `MethodsStack`
3. Remove top-level `Error` and `Additional` from `SuccessEnvelope` and `ErrorEnvelope`
4. Move `EnvelopeErrorDetail` and `DelegatedError` schemas to be sub-schemas of Navigation
5. Add `NavigationErrors` and `NavigationMethodsStack` component schemas

**Files to change:**
- `backend/api/openapi.json`

---

### Phase C5: Correct Frontend Envelope Parser

**Goal:** Update `src/lib/api.ts` types and `parseEnvelope<T>` to consume the corrected structure.

**Changes:**
1. **Update `EnvelopeAttributes`** -- rename `RequestedEndpoint` to `RequestedAt`, `DelegatedEndpoint` to `RequestDelegatedAt`, add `HasAnyErrors`, remove `TraversalSteps`
2. **Restructure `EnvelopeNavigation`** -- `NextPage`/`PrevPage` become `string | null` (URLs), `Pages` becomes `CloserLinks` (string array), add `Errors` and `MethodsStack` sub-types
3. **Remove** `EnvelopeErrorDetail` and `EnvelopeDelegatedError` as separate top-level envelope types (they now live inside Navigation)
4. **Update `RawEnvelope`** -- remove `Error` and `Additional` top-level fields
5. **Update `parseEnvelope<T>()`** -- extract errors from `Navigation.Errors`, extract method stack from `Navigation.MethodsStack`
6. **Update `EnvelopeMeta`** -- restructure to match new shape
7. **Update `isEnvelope()`** -- detection logic remains similar (check for `Status.IsSuccess`)

**Files to change:**
- `src/lib/api.ts` -- Types, parser, detection
- `src/lib/apiHelpers.ts` -- May need minor updates for pagination URL handling

---

### Phase C6: Correct Frontend Pagination Component

**Goal:** Update `EnvelopePagination` to work with URL-based navigation links instead of integer page numbers.

**Changes:**
1. Extract page number from URL strings (e.g., parse `?page=3` from `/api/v1/plugins?page=3`)
2. Rename `Pages` references to `CloserLinks` and parse page numbers from URL strings
3. Update `onPageChange` callback to work with either extracted page numbers or full URLs

**Files to change:**
- `src/components/shared/EnvelopePagination.tsx`

---

### Phase C7: Correct Error Modal and Error Store

**Goal:** Update the error store and GlobalErrorModal to consume errors from `Navigation.Errors` instead of top-level `Error` and `Additional.DelegatedError`.

**Changes:**
1. **Error Store (`errorStore.ts`):**
   - Update `captureError` to extract `BackendMessage`, `DelegatedServiceErrorStack`, `Backend`/`Frontend` error stacks from `Navigation.Errors`
   - Update `MethodsStack` extraction from `Navigation.MethodsStack` instead of `Attributes.TraversalSteps`
   - Rename `delegatedError` to match new structure
   - Rename `requestedEndpoint`/`delegatedEndpoint` to `requestedAt`/`requestDelegatedAt`

2. **GlobalErrorModal (`GlobalErrorModal.tsx`):**
   - Update the Traversal tab to read from new field locations
   - Update endpoint flow visualization to use `RequestedAt` / `RequestDelegatedAt`
   - Update delegated error display to use `Navigation.Errors.DelegatedServiceErrorStack`
   - Update method chain display to use `Navigation.MethodsStack.Backend`

**Files to change:**
- `src/stores/errorStore.ts`
- `src/components/errors/GlobalErrorModal.tsx`

---

### Phase C8: Correct Settings Page Debug Controls

**Goal:** Ensure the Settings page debug toggles align with the configurability of Navigation sub-sections.

**Changes:**
1. Add toggle for "Include Navigation Errors" (controls whether `Navigation.Errors` is populated)
2. Add toggle for "Include Methods Stack" (controls whether `Navigation.MethodsStack` is populated)
3. Existing "Include Stack Trace" toggle now controls `Navigation.Errors.Backend` / `Navigation.Errors.DelegatedServiceErrorStack`
4. Update `Settings` interface `responseDebug` block to reflect new toggle names

**Files to change:**
- `src/lib/api.ts` -- Update `Settings.responseDebug` interface
- `src/pages/Settings.tsx` -- Update toggle labels and state
- `src/hooks/useSettings.ts` -- May need minor updates

---

### Phase C9: Update Memory and Plan Documentation

**Goal:** Update all project documentation and memory entries to reflect the corrected envelope.

**Files to change:**
- `.lovable/plan/frontend-pages.md` -- Mark corrective phases
- `spec/response-envelope/README.md` -- Already created in C1

---

## Implementation Order

```text
C1 (Spec folder)        -- no dependencies, reference document
  |
C2 (Go envelope.go)     -- core struct changes
  |
C3 (Go handlers)        -- depends on C2
  |
C4 (OpenAPI spec)        -- depends on C2
  |
C5 (Frontend parser)     -- depends on C2 (matches new JSON shape)
  |
  +-- C6 (Pagination)    -- depends on C5
  |
  +-- C7 (Error modal)   -- depends on C5
  |
C8 (Settings)            -- depends on C5
  |
C9 (Documentation)       -- last
```

Phases C2, C4 can run in parallel. Phases C5, C6, C7 can partially overlap once C2 is done.


Correct and each time show the response sample, clear???
