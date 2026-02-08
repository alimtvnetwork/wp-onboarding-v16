# ADR: Universal Response Envelope — Architecture Decision Record

> **Status:** Accepted  
> **Date:** 2026-01-20  
> **Authors:** Platform Engineering Team  
> **Supersedes:** Legacy flat-object API responses

---

## Context

The platform spans three runtime stacks — a **Go API backend**, a **React SPA frontend**, and a **WordPress/PHP companion plugin**. Prior to this specification, each stack returned ad-hoc JSON shapes, leading to:

- Inconsistent error surfacing across services.
- Fragile, per-endpoint parsing logic on the frontend.
- No structured way to carry diagnostics (stack traces, method chains) from PHP through Go to the browser.

A unified response contract was needed to eliminate these problems without breaking existing consumers during migration.

---

## Decisions

### 1. PascalCase Key Convention

**Decision:** All envelope keys use PascalCase (`IsSuccess`, `TotalRecords`, `NextPage`).

**Rationale:**
- Go's `encoding/json` natively marshals exported struct fields to PascalCase, making it the zero-config default for the primary backend.
- PHP's `RiseupEnvelopeBuilder` explicitly constructs associative arrays with PascalCase keys, so no runtime transformation is required.
- A single, unambiguous casing convention eliminates the need for camelCase ↔ snake_case mapping layers that introduce bugs and cognitive overhead.
- The frontend consumes PascalCase directly via `parseEnvelope<T>()`, keeping the contract transparent end-to-end.

**Alternatives considered:**
- *camelCase* — natural for JavaScript but would require `json:"camelKey"` tags on every Go struct field.
- *snake_case* — conventional in PHP/Ruby ecosystems but alien to both Go and TypeScript interfaces.

---

### 2. Results as a Typed Array (Never a Bare Object)

**Decision:** The `Results` field is always `T[]` — an array, even for single-item responses.

**Rationale:**
- **Uniform parsing:** One code path handles every endpoint. The consumer always iterates or indexes into an array; there is no `if (typeof data === 'object' && !Array.isArray(data))` branching.
- **OpenAPI expressiveness:** `minItems: 1, maxItems: 1` on the schema precisely communicates single-item semantics without inventing a separate wrapper type.
- **Forward compatibility:** An endpoint returning a single resource today can evolve to return multiple resources without a breaking contract change.
- **Empty-state clarity:** An empty `Results: []` is unambiguous — it means "no records matched," not "the field was omitted or null."

**Alternatives considered:**
- *Bare object for singles, array for lists* — requires runtime type-checking, doubles parsing logic, and makes OpenAPI schemas inconsistent.
- *Wrapper with `result` (singular) and `results` (plural)* — adds naming ambiguity and doubles the number of response schemas.

---

### 3. Absolute URL Strings for Pagination

**Decision:** `Navigation.NextPage`, `Navigation.PrevPage`, and `Navigation.CloserLinks` are fully qualified, absolute URL strings.

**Rationale:**
- **Zero client-side URL construction:** The frontend does not need to know the base URL, path structure, or query parameter format. It follows the link or extracts the page number with a single regex.
- **Delegation transparency:** When the Go backend proxies a paginated response from a remote WordPress site, the absolute URLs already point to the correct origin. The frontend doesn't need to distinguish between local and delegated pagination.
- **HATEOAS alignment:** Absolute links are the standard mechanism in hypermedia APIs (RFC 8288). Adopting this convention keeps the door open for further hypermedia controls without a structural change.

**Alternatives considered:**
- *Page numbers only* — forces the client to reconstruct URLs and know endpoint paths, creating tight coupling.
- *Relative URLs* — ambiguous when responses pass through a proxy layer; the client must resolve them against an assumed base.

---

### 4. Top-Level Errors and MethodsStack Blocks

**Decision:** `Errors` and `MethodsStack` are top-level envelope fields, not nested inside `Status` or `Results`.

**Rationale:**
- **Separation of concerns:** Status tells *what happened* (success/failure); Errors tells *why* it failed; MethodsStack tells *where* in the code it failed. Mixing these conflates metadata with diagnostics.
- **Conditional inclusion:** The backend can omit `Errors` and `MethodsStack` entirely in production by toggling `includeErrors` / `includeMethodsStack` in the developer settings — without affecting the shape of `Status` or `Results`.
- **Cross-stack tracing:** The `Errors.DelegatedServiceErrorStack` carries the remote PHP stack, while `MethodsStack` carries the Go-side call chain. Keeping them at the top level lets the frontend's Traversal tab render both without deep-nesting traversal.

---

### 5. Attributes Block for Request Metadata

**Decision:** A dedicated `Attributes` block carries request-scoped metadata (`RequestedAt`, `RequestDelegatedAt`, `IsSingle`, `IsMultiple`, pagination counters).

**Rationale:**
- **Introspection without parsing Results:** The consumer can determine whether the response is paginated, single, or empty by reading `Attributes` alone — before touching `Results`.
- **Delegation tracking:** `RequestedAt` records the original endpoint; `RequestDelegatedAt` records the downstream WordPress endpoint if the request was proxied. This pair powers the Traversal tab's request-chain visualisation.
- **Pagination math stays server-side:** `TotalRecords`, `TotalPages`, `CurrentPage`, and `PerPage` are computed once by the backend, preventing drift between frontend and backend calculations.

---

### 6. Dual-Format Runtime Detection

**Decision:** The Go backend auto-detects whether a WordPress response uses the envelope format or the legacy flat format, and normalises both into the same internal representation.

**Rationale:**
- **Zero-downtime migration:** Not all remote WordPress sites upgrade simultaneously. The backend must handle both formats during the transition window.
- **Single consumer interface:** Callers of `UnwrapResults[T]` and `UnwrapSingleResult[T]` never need to know which format the remote site returned — the detection is fully encapsulated.
- **Deprecation path:** Once all WordPress sites are confirmed on v1.34.0+, the legacy branch can be removed with a single flag change, leaving no dead code.

---

## Consequences

| Positive | Trade-off |
|---|---|
| One parsing path across all endpoints and stacks | PascalCase is unconventional for JavaScript consumers (mitigated by direct property access) |
| Diagnostics travel end-to-end without custom plumbing | Envelope adds ~120 bytes of overhead to minimal responses |
| Pagination "just works" via URL following | Absolute URLs are longer than page-number integers |
| OpenAPI schemas are self-documenting with examples | Initial migration required touching 31+ endpoint schemas |

---

## References

- [spec/response-envelope/README.md](./README.md) — Canonical specification
- [spec/response-envelope/CHANGELOG.md](./CHANGELOG.md) — Migration timeline
- [spec/response-envelope/configurability-rules.md](./configurability-rules.md) — Debug toggle rules
- RFC 8288 — Web Linking (absolute URL convention)

---

_This ADR is maintained alongside the specification in `spec/response-envelope/`._
