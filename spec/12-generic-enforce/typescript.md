# Generic Enforce — TypeScript

**Applies to**: All `.ts` and `.tsx` files in the project.

---

## Mechanism: `type` Aliases

TypeScript supports first-class type aliases for generic instantiations:

```typescript
// Base generic
interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: ApiError;
}

// ✅ Named instantiations
type PluginResponse = ApiResponse<Plugin>;
type SiteListResponse = ApiResponse<Site[]>;
type SettingsResponse = ApiResponse<Settings>;
```

---

## Prohibited Patterns

```typescript
// ❌ NEVER: Raw Record with unknown
context?: Record<string, unknown>;

// ❌ NEVER: any
catch (err: any) { ... }
const data = response as any;

// ❌ NEVER: unknown in public fields (except parse boundaries)
metadata?: unknown;

// ❌ NEVER: Bare object type
function process(data: object): void { ... }
```

## Required Replacements

### `Record<string, unknown>` → Named Domain Type

```typescript
// BEFORE (prohibited)
interface ApiError {
  context?: Record<string, unknown>;
}

// AFTER (required)
/** Contextual data attached to an API error for diagnostics. */
interface ErrorContext {
  endpoint?: string;
  statusCode?: number;
  requestId?: string;
  pluginId?: number;
  siteId?: number;
  sessionId?: string;
  [key: string]: string | number | boolean | undefined;
}

interface ApiError {
  context?: ErrorContext;
}
```

### `Record<string, unknown>` for request/response bodies

```typescript
// BEFORE (prohibited)
body?: Record<string, unknown>;

// AFTER: Use the actual request/response shape
interface SessionRequestInfo {
  url: string;
  method: string;
  headers?: HttpHeaders;
  body?: RequestPayload;  // Define what the body actually contains
}
```

### Catch blocks

```typescript
// BEFORE (prohibited)
catch (err: any) {
  console.error(err.message);
}

// AFTER (required)
catch (err) {
  if (err instanceof Error) {
    console.error(err.message);
  }
  throw err;
}
```

---

## The Student-Teacher Pattern in TypeScript

```typescript
// Base generic
interface Student<TRights, TKey extends string | number> {
  id: TKey;
  rights: TRights;
  name: string;
  enrolledAt: string;
}

// Rights types
interface BasicRights {
  canRead: boolean;
  canWrite: boolean;
}

interface BasicRightsV2 extends BasicRights {
  canAdmin: boolean;
  canExport: boolean;
}

// ✅ Named instantiations (REQUIRED)
type TeacherBasicRights = Student<BasicRights, number>;
type TeacherBasicRightsV2 = Student<BasicRightsV2, number>;
type StudentByUUID = Student<BasicRights, string>;

// ✅ Usage — clean, DRY, discoverable
function getTeacher(id: number): TeacherBasicRights { ... }
function getTeacherV2(id: number): TeacherBasicRightsV2 { ... }
function getStudentByUUID(uuid: string): StudentByUUID { ... }
```

---

## Placement Rules

1. **Co-locate with base type**: If the generic is in `types.ts`, the named aliases go there too
2. **Domain grouping**: Group aliases by domain area (errors, sessions, plugins)
3. **Export all aliases**: Named aliases MUST be exported for reuse
4. **Document the alias**: A one-line JSDoc comment explains what this specific instantiation represents

```typescript
/** API response containing a single Plugin entity. */
export type PluginResponse = ApiResponse<Plugin>;

/** API response containing paginated list of error history records. */
export type ErrorHistoryResponse = ApiResponse<ErrorHistoryListResponse>;
```
