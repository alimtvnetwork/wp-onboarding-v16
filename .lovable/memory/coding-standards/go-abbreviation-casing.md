# Memory: coding-standards/go-abbreviation-casing
Updated: 2026-02-28

## Rule: Abbreviations Use PascalCase, Not ALL-CAPS

All abbreviations in Go identifiers MUST use PascalCase (`Api` not `API`, `Url` not `URL`, `Http` not `HTTP`), overriding Go's default convention. This matches the PHP rule already in place.

```go
// ❌ FORBIDDEN — ALL-CAPS abbreviations
type APIError struct { ... }
func ExtractAPIError(err error) *APIError { ... }
const GoAPIPrefix = "/api/v1"
const WPCoreAPIRoot = "/wp-json"
func decodeAPIResponse[T any](...) { ... }

// ✅ REQUIRED — PascalCase abbreviations
type ApiError struct { ... }
func ExtractApiError(err error) *ApiError { ... }
const GoApiPrefix = "/api/v1"
const WPCoreApiRoot = "/wp-json"
func decodeApiResponse[T any](...) { ... }
```

### Applies To

| Abbreviation | ❌ Wrong | ✅ Correct |
|---|---|---|
| API | `APIError`, `GoAPIPrefix` | `ApiError`, `GoApiPrefix` |
| URL | `URL` (as field) | `Url` |
| HTTP | `HTTPClient` | `HttpClient` |
| JSON | `JSONResponse` | `JsonResponse` |
| SQL | `SQLQuery` | `SqlQuery` |
| ID | `UserID` | `UserId` |

### Scope

- Go: All exported and unexported identifiers
- PHP: Already enforced (`Api` not `API`, `Wp` not `WP`)
- TypeScript: Follow same convention in type names

### Exceptions

- External library types (e.g., `http.Request`) are not renamed
- JSON struct tags remain as-is (they're external keys)
- Enum variant values (e.g., `uploadsourcetype.RestAPI`) — the `.Value()` string stays, but the variant name follows PascalCase
