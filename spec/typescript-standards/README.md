# TypeScript Coding Standards

> **Version:** 1.0.0  
> **Updated:** 2026-02-09  
> **Applies to:** All frontend TypeScript/React code

---

## Type Safety — Zero Tolerance for `any` and `unknown`

### Rule: Never use `any`

The `any` type disables TypeScript's type checker entirely. It is **prohibited** in all code.

```typescript
// ❌ FORBIDDEN
function processData(data: any): any { ... }
const result: any = apiCall();
catch (error: any) { ... }

// ✅ REQUIRED: Use specific types
function processData(data: PluginDetails): PluginSummary { ... }
const result: ApiResponse<Plugin> = apiCall();
catch (error) {
  const message = error instanceof Error ? error.message : String(error);
}
```

### Rule: Avoid `unknown` in public APIs

`unknown` is safer than `any` but should be narrowed immediately. It must **never** appear in:
- Component props
- Hook return types
- Store state types
- Exported function signatures

```typescript
// ❌ Leaking unknown to consumers
export function usePlugins(): { data: unknown } { ... }

// ✅ Typed through the boundary
export function usePlugins(): { data: Plugin[] | undefined } { ... }
```

**Acceptable use of `unknown`:** Only in internal error boundaries or JSON parsing boundaries where you immediately narrow:

```typescript
// ✅ Acceptable: unknown narrowed immediately
function parseResponse(raw: unknown): EnvelopeResponse {
  if (!isEnvelopeResponse(raw)) throw new Error('Invalid envelope');
  return raw;
}
```

---

## Type Narrowing Patterns

### Error Handling

```typescript
// ✅ Pattern: instanceof narrowing
try {
  await apiCall();
} catch (error) {
  const message = error instanceof Error ? error.message : String(error);
  errorStore.report({ message, component: 'PluginsPage', action: 'fetch' });
}
```

### API Response Types

```typescript
// ✅ Pattern: Generic envelope types
interface EnvelopeResponse<T> {
  Status: StatusBlock;
  Attributes: AttributesBlock;
  Results: T[];
  Navigation?: NavigationBlock | null;
  Errors?: ErrorsBlock | null;
  MethodsStack?: MethodsStackBlock | null;
}
```

### Discriminated Unions

```typescript
// ✅ Pattern: Discriminated union for action results
type PublishResult =
  | { status: 'success'; version: string; activated: boolean }
  | { status: 'error'; message: string; stage: string }
  | { status: 'rollback'; reason: string; restoredVersion: string };
```

---

## React-Specific Rules

### Component Props

Every component must have explicitly typed props. Never use `React.FC` (it adds implicit `children`).

```typescript
// ✅ Explicit props interface
interface PluginCardProps {
  plugin: Plugin;
  onActivate: (slug: string) => void;
  isLoading?: boolean;
}

function PluginCard({ plugin, onActivate, isLoading = false }: PluginCardProps) { ... }
```

### Hook Return Types

All custom hooks must declare return types explicitly:

```typescript
// ✅ Explicit return type
function usePluginStatus(slug: string): {
  status: PluginStatus;
  isLoading: boolean;
  refetch: () => void;
} { ... }
```

### Event Handlers

```typescript
// ✅ Typed event handlers
const handleClick = (e: React.MouseEvent<HTMLButtonElement>) => { ... };
const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => { ... };
const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => { ... };
```

---

## Import/Export Rules

- Use **named exports** (not default exports) for components and hooks
- Use **barrel exports** (`index.ts`) only at module boundaries
- Import types with `import type` when only used as types

```typescript
// ✅ Named export
export function PluginCard({ ... }: PluginCardProps) { ... }

// ✅ Type-only import
import type { Plugin, PluginStatus } from '@/lib/api/types';
```

---

## Forbidden Patterns

| Pattern | Why | Alternative |
|---------|-----|-------------|
| `as any` | Disables type checking | Fix the type or use type narrowing |
| `@ts-ignore` | Hides errors | Fix the underlying type issue |
| `@ts-expect-error` | Only acceptable in tests | Use proper mocking with typed utilities |
| `Record<string, any>` | `any` in disguise | Use specific interfaces |
| `Function` type | Untyped callable | Use specific function signatures |
| `Object` type | Too broad | Use specific interfaces |
| `{}` as type | Matches anything except null/undefined | Use `Record<string, never>` or specific type |

---

## Enforcement

- **ESLint:** `@typescript-eslint/no-explicit-any` must be set to `error`
- **Code review:** Any PR introducing `any` or `unknown` in public APIs must be rejected
- **Exceptions:** Must be documented with a comment explaining why and a TODO for removal

---

*TypeScript standards specification created: 2026-02-09*
