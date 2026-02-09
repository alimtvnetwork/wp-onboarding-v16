# Universal Response Envelope — Changelog

All notable milestones in the design, migration, and adoption of the Universal Response Envelope are documented here.

---

## v1.0.0 — 2026-01-20 · Initial Specification

- Defined the six top-level envelope blocks: **Status**, **Attributes**, **Results**, **Navigation**, **Errors**, **MethodsStack**.
- Established PascalCase key convention across all stacks.
- Published reference JSON samples (`envelope-single.json`, `envelope-multiple.json`, `envelope-error.json`, `envelope-debug.json`, `envelope-minimal.json`).
- Added `spec/response-envelope/README.md` as the canonical specification document.

## v1.1.0 — 2026-01-22 · PHP Envelope Builder

- Introduced `RiseupEnvelopeBuilder` class in the WordPress companion plugin (v1.34.0).
- Fluent API with PHPStan/Psalm `@template T of array` annotations for static analysis.
- Migrated all PHP endpoints (status, lifecycle, diagnostics) to the builder.

## v1.2.0 — 2026-01-25 · Go Backend — Dual-Format Compatibility

- Added `backend/internal/wordpress/envelope.go` with `IsEnvelope()`, `ParseEnvelope()`.
- Runtime auto-detection enables backward compatibility with legacy (flat) WordPress responses.

## v1.3.0 — 2026-01-27 · Go Generics — Typed Envelope Parsing

- Introduced `TypedEnvelope[T any]`, `UnwrapResults[T]`, `UnwrapSingleResult[T]` (Go 1.22+).
- Replaced all `interface{}`-based unwrapping with compile-time type-safe extraction.
- Concrete struct targets: `UploaderStatus`, `UploaderPluginInfo`, and others.

## v1.4.0 — 2026-01-30 · Frontend Integration

- Implemented `parseEnvelope<T>()` in `src/lib/api.ts` with auto-detection of PascalCase structure.
- Global Error Modal extracts **Errors** and **MethodsStack** from the envelope.
- Added **Traversal** tab for request-chain and method-stack visualisation.

## v1.5.0 — 2026-02-01 · OpenAPI Alignment

- Migrated 31+ endpoint schemas in `backend/api/openapi.json` to the typed `Results` array pattern.
- Added `minItems`/`maxItems` constraints for single-item responses.
- Comprehensive `example` blocks for Status, Attributes, and Results.

## v1.6.0 — 2026-02-03 · Error Handling & Diagnostics

- MD5-based deduplication for `error.log.txt` (action + siteID + plugin + endpoint + status + body).
- Configurable stack depth and **Clear Dedup Hashes** endpoint.
- PHP safe-execution and shutdown handlers for structured error reporting.
- Settings → Developer tab: toggles for `includeErrors`, `includeStackTrace`, `includeMethodsStack`, `defaultPerPage`.

## v1.7.0 — 2026-02-05 · Pagination & Navigation

- `Navigation` block provides absolute URL strings (`NextPage`, `PrevPage`, `CloserLinks`).
- Frontend parses URL strings to extract page numbers for seamless pagination controls.

## v1.8.0 — 2026-02-07 · Configurability Rules

- Published configurability rules document in `spec/response-envelope/`.
- Finalised all phases (1–14) of the envelope migration plan.

## v1.9.0 — 2026-02-09 · JSON Schema & DRY Refactoring Complete

- **Created `envelope.schema.json` (v1.0.0)** — canonical JSON Schema (Draft 2020-12) as single source of truth for all envelope types across Go, TypeScript, and PHP.
- Schema covers all 6 top-level blocks with full `$defs`: `Status`, `Attributes`, `Results`, `Navigation`, `Errors`, `MethodsStack` (including `StackFrame`).
- Added `@schema` / version comments referencing the schema in all three implementations:
  - Go: `backend/internal/wordpress/envelope.go`
  - TypeScript: `src/lib/api/types.ts`
  - PHP: `wp-plugins/riseup-asia-uploader/includes/class-envelope-builder.php`
- **Completed all 10 DRY refactoring phases:**
  1. Go uploader lifecycle method dedup + stdlib usage
  2. Envelope unwrap helper + PHP stack extraction
  3. API error diagnostic context dedup
  4. Error store capture dedup (`buildCapturedError`)
  5. `api.ts` split into `src/lib/api/` modules
  6. `useApiQuery` factory hook
  7. PHP `RiseupSnapshotFactory` for snapshot class construction
  8. PHP logger context consolidation (`prepare_context`)
  9. `GlobalErrorModal.tsx` decomposition (2,164 → 195 lines, 7 sub-components)
  10. Cross-stack envelope JSON schema alignment (this entry)

---

_This changelog is maintained alongside the specification in `spec/response-envelope/`._
