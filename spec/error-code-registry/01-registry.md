# Error Code Registry - Master List

> **Last Updated:** 2026-01-31  
> **Maintainer:** AI/Human collaboration

---

## Registered Project Prefixes

| Prefix | Project | Range | Spec Location | Status |
|--------|---------|-------|---------------|--------|
| `GEN` | General/Shared | 1000-1999 | (embedded) | ✅ Active |
| `SM` | Spec Management Software | 2000-2999 | `spec/spec-management-software/` | ✅ Active |
| `LM` | Link Manager | 3000-3999 | `spec/link-manager/` | ✅ Active |
| `CLI` | CLI Tools (legacy) | 4000-4999 | (deprecated) | ⚠️ Deprecated |
| `GS` | GSearch CLI | 7000-7099 | `spec/gsearch-cli/` | ✅ Active |
| `BR` | BRun CLI | 7100-7599 | `spec/brun-cli/` | ✅ Active |
| `NF` | Nexus Flow | 8000-8399 | `spec/nexus-flow/` | ✅ Active |
| `AB` | AI Bridge | 9000-9999 | `spec/ai-bridge/` | ✅ Active |
| `PS` | PowerShell Integration | 9500-9599 | `spec/powershell-integration/` | ✅ Active |
| `WPB` | WP Plugin Builder | 10000-10999 | `spec/wp-plugin-builder/` | ✅ Active |

---

## Standalone Specification Error Ranges

The following modules are extracted as standalone specifications with dedicated error code ranges:

| Module | Prefix | Range | Error Codes Doc |
|--------|--------|-------|-----------------|
| GSearch CLI | `GS` | 7000-7099 | `spec/gsearch-cli/05-error-codes.md` |
| BRun CLI | `BR` | 7100-7599 | `spec/brun-cli/06-error-handling.md` |
| Nexus Flow | `NF` | 8000-8399 | `spec/nexus-flow/05-error-codes.md` |
| AI Bridge | `AB` | 9000-9999 | `spec/ai-bridge/05-error-codes.md` |
| WP Plugin Builder | `WPB` | 10000-10999 | `spec/wp-plugin-builder/10-error-handling.md` |

---

## GEN: General/Shared Errors (1000-1999)

### GEN-000: Initialization

| Code | Name | Message |
|------|------|---------|
| GEN-000-01 | CONFIG_MISSING | Configuration file not found |
| GEN-000-02 | CONFIG_INVALID | Configuration file is malformed |
| GEN-000-03 | ENV_MISSING | Required environment variable not set |

### GEN-100: Authentication

| Code | Name | Message |
|------|------|---------|
| GEN-100-01 | AUTH_REQUIRED | Authentication required |
| GEN-100-02 | TOKEN_EXPIRED | Authentication token has expired |
| GEN-100-03 | TOKEN_INVALID | Authentication token is invalid |
| GEN-100-04 | CREDENTIALS_INVALID | Invalid username or password |

### GEN-200: Authorization

| Code | Name | Message |
|------|------|---------|
| GEN-200-01 | ACCESS_DENIED | Access denied to this resource |
| GEN-200-02 | ROLE_REQUIRED | Insufficient role privileges |
| GEN-200-03 | PERMISSION_DENIED | Permission not granted |

### GEN-300: Validation

| Code | Name | Message |
|------|------|---------|
| GEN-300-01 | FIELD_REQUIRED | Required field is missing |
| GEN-300-02 | FIELD_INVALID | Field value is invalid |
| GEN-300-03 | FORMAT_INVALID | Input format is invalid |
| GEN-300-04 | LENGTH_EXCEEDED | Input exceeds maximum length |

### GEN-500: Database

| Code | Name | Message |
|------|------|---------|
| GEN-500-01 | DB_CONNECTION | Database connection failed |
| GEN-500-02 | DB_QUERY | Database query failed |
| GEN-500-03 | DB_TRANSACTION | Transaction failed |
| GEN-500-04 | RECORD_NOT_FOUND | Record not found |
| GEN-500-05 | DUPLICATE_RECORD | Record already exists |

### GEN-800: Network

| Code | Name | Message |
|------|------|---------|
| GEN-800-01 | NETWORK_ERROR | Network request failed |
| GEN-800-02 | TIMEOUT | Request timed out |
| GEN-800-03 | SERVICE_UNAVAILABLE | Service temporarily unavailable |

---

## SM: Spec Management Software (2000-2999)

### SM-000: Initialization

| Code | Name | Message |
|------|------|---------|
| SM-000-01 | SPEC_ROOT_MISSING | Spec root directory not found |
| SM-000-02 | INDEX_MISSING | Master index file not found |

### SM-400: Business Logic

| Code | Name | Message |
|------|------|---------|
| SM-400-01 | SPEC_PARSE_ERROR | Failed to parse specification file |
| SM-400-02 | CIRCULAR_DEPENDENCY | Circular dependency detected |
| SM-400-03 | VERSION_CONFLICT | Version conflict detected |
| SM-400-04 | TEMPLATE_ERROR | Template rendering failed |

### SM-500: Database

| Code | Name | Message |
|------|------|---------|
| SM-500-01 | MIGRATION_FAILED | Database migration failed |
| SM-500-02 | SEED_FAILED | Database seeding failed |

### SM-600: External Services

| Code | Name | Message |
|------|------|---------|
| SM-600-01 | AI_API_ERROR | AI service request failed |
| SM-600-02 | EMBEDDING_FAILED | Vector embedding generation failed |
| SM-600-03 | RAG_SEARCH_FAILED | RAG search query failed |

---

## LM: Link Manager (3000-3999)

### LM-000: Initialization

| Code | Name | Message |
|------|------|---------|
| LM-000-01 | WP_NOT_DETECTED | WordPress environment not detected |
| LM-000-02 | PLUGIN_CONFLICT | Plugin conflict detected |

### LM-400: Business Logic

| Code | Name | Message |
|------|------|---------|
| LM-400-01 | LINK_INVALID | Invalid link format |
| LM-400-02 | REDIRECT_LOOP | Redirect loop detected |
| LM-400-03 | DOMAIN_BLOCKED | Domain is blocked |

---

## CLI: CLI Tools (4000-4999)

### CLI-000: Initialization

| Code | Name | Message |
|------|------|---------|
| CLI-000-01 | BINARY_NOT_FOUND | Required binary not found |
| CLI-000-02 | PATH_NOT_SET | PATH environment not configured |

### CLI-400: gsearch

| Code | Name | Message |
|------|------|---------|
| CLI-400-01 | PATTERN_INVALID | Invalid search pattern |
| CLI-400-02 | NO_RESULTS | No results found |
| CLI-400-03 | INDEX_STALE | Search index is stale |

### CLI-500: brun

| Code | Name | Message |
|------|------|---------|
| CLI-500-01 | BUILD_FAILED | Build step failed |
| CLI-500-02 | RUN_FAILED | Run step failed |
| CLI-500-03 | DEPS_MISSING | Dependencies not installed |

---

## PS: PowerShell Integration (9500-9599)

> See `spec/powershell-integration/04-error-codes.md` for full list.

| Code | Name | Message |
|------|------|---------|
| PS-9500-00 | SUCCESS | Operation completed successfully |
| PS-9501-01 | GO_MISSING | Go runtime not found |
| PS-9502-01 | NODE_MISSING | Node.js not found |
| PS-9510-01 | CONFIG_MISSING | powershell.json not found |
| PS-9520-01 | BUILD_FAILED | Frontend build failed |
| PS-9530-01 | BACKEND_FAILED | Backend failed to start |

---

## Adding New Codes

1. Find your project section (or create one)
2. Use the next available number in the category
3. Follow naming convention: `UPPERCASE_WITH_UNDERSCORES`
4. Provide clear, actionable message
5. Update this registry
