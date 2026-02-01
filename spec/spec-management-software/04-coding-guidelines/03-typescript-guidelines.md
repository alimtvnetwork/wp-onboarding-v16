# 03. TypeScript Guidelines

**Version:** 2.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Purpose

Strict TypeScript coding standards for the Spec Management Software. These rules ensure type safety, maintainability, and consistency across all frontend and orchestration code.

**Cross-References:**
- [General Coding Standards](../../general-spec/01-foundation/01-coding-standards-foundation.md)
- [React Guidelines](./04-react-guidelines.md)
- [ESLint Configuration](./06-eslint-enforcement.md)

---

## ESLint Enforcement

All TypeScript rules are enforced via ESLint with `typescript-eslint` plugin:

| Rule | Setting | Purpose |
|------|---------|---------|
| `@typescript-eslint/no-explicit-any` | `error` | Disallow `any` type |
| `@typescript-eslint/explicit-function-return-type` | `warn` | Require return types |
| `@typescript-eslint/switch-exhaustiveness-check` | `warn` | Exhaustive switch checks |
| `@typescript-eslint/consistent-type-imports` | `warn` | Use `type` imports |
| `@typescript-eslint/prefer-optional-chain` | `warn` | Use `?.` syntax |
| `@typescript-eslint/no-non-null-assertion` | `warn` | Avoid `!` assertions |
| `prefer-const` | `error` | Use `const` by default |
| `no-var` | `error` | Disallow `var` keyword |

---

## Core Rules

### 1. No `any` or `unknown` Types

> ⚠️ **MANDATORY:** Never use `any` or `unknown` (except in type guards).

Every type must be explicitly defined with proper interfaces or type aliases.

#### ❌ INCORRECT

```typescript
function processData(data: any) {
  return data.value;
}

function handleResponse(response: unknown) {
  // No type safety
  console.log(response);
}

const config = {} as any;
```

#### ✅ CORRECT

```typescript
interface DataPayload {
  readonly value: string;
  readonly timestamp: number;
}

function processData(data: DataPayload): string {
  return data.value;
}

// Type guard is the only valid use of unknown
function isDataPayload(value: unknown): value is DataPayload {
  return (
    typeof value === 'object' &&
    value !== null &&
    'value' in value &&
    typeof (value as DataPayload).value === 'string'
  );
}

interface SystemConfig {
  readonly timeoutMs: number;
  readonly maxRetries: number;
}

const config: SystemConfig = {
  timeoutMs: 5000,
  maxRetries: 3,
};
```

---

### 2. Use `const` by Default

> ⚠️ **MANDATORY:** Use `const` unless reassignment is required.

#### ❌ INCORRECT

```typescript
let userId = 123;  // Never reassigned
let config = { timeout: 5000 };  // Never reassigned
var items = [];  // Never use var
```

#### ✅ CORRECT

```typescript
const userId = 123;
const config = { timeout: 5000 };
const items: string[] = [];

// Only use let when reassignment is needed
let counter = 0;
counter++;
```

---

### 3. Enums for Switch Statements

> ⚠️ **MANDATORY:** All switch statements must use enum types, never string literals.

#### ❌ INCORRECT

```typescript
function handleAction(action: string) {
  switch (action) {
    case "create":
      return create();
    case "update":
      return update();
    case "delete":
      return deleteItem();
    default:
      throw new Error(`Unknown action: ${action}`);
  }
}
```

#### ✅ CORRECT

```typescript
enum TaskAction {
  Create = "create",
  Update = "update",
  Delete = "delete",
}

function handleAction(action: TaskAction): Result {
  switch (action) {
    case TaskAction.Create:
      return create();
    case TaskAction.Update:
      return update();
    case TaskAction.Delete:
      return deleteItem();
    default:
      // Exhaustive check enforced by TypeScript
      const _exhaustive: never = action;
      throw new Error(`Unhandled action: ${_exhaustive}`);
  }
}
```

---

### 4. Explicit Type Definitions

> ⚠️ **MANDATORY:** All object shapes must have interfaces. No implicit typing for complex objects.

#### ❌ INCORRECT

```typescript
// Implicit typing - not allowed
const user = {
  id: 1,
  name: "John",
  email: "john@example.com",
};

// Inline object types - avoid for reusable shapes
function processUser(user: { id: number; name: string }) {
  // ...
}
```

#### ✅ CORRECT

```typescript
interface User {
  readonly id: number;
  readonly name: string;
  readonly email: string;
}

const user: User = {
  id: 1,
  name: "John",
  email: "john@example.com",
};

function processUser(user: User): void {
  // ...
}
```

---

### 5. Use `readonly` for Immutable Data

> ⚠️ **MANDATORY:** Use `readonly` modifier for properties that should not change after creation.

#### ❌ INCORRECT

```typescript
interface Config {
  timeout: number;
  retries: number;
}

interface User {
  id: number;
  name: string;
}
```

#### ✅ CORRECT

```typescript
interface Config {
  readonly timeout: number;
  readonly retries: number;
}

interface User {
  readonly id: number;
  readonly name: string;
  readonly createdAt: Date;
}

// For arrays that shouldn't be modified
interface TaskList {
  readonly items: readonly Task[];
}
```

---

### 6. Type Guards Over Type Assertions

> ⚠️ **MANDATORY:** Use type guards for runtime type checking, not type assertions.

#### ❌ INCORRECT

```typescript
// Dangerous type assertion
const user = response as User;
const data = JSON.parse(text) as Config;
```

#### ✅ CORRECT

```typescript
// Type guard function
function isUser(value: unknown): value is User {
  return (
    typeof value === 'object' &&
    value !== null &&
    'id' in value &&
    'name' in value &&
    typeof (value as User).id === 'number' &&
    typeof (value as User).name === 'string'
  );
}

// Usage
const parsed: unknown = JSON.parse(text);
if (isUser(parsed)) {
  // TypeScript now knows parsed is User
  console.log(parsed.name);
} else {
  throw new Error('Invalid user data');
}
```

---

### 7. Strict Null Checking

> ⚠️ **MANDATORY:** Explicitly handle `null` and `undefined` in types.

#### ❌ INCORRECT

```typescript
function getUser(id: number): User {
  // Might return undefined, but type says User
  return users.find(u => u.id === id);
}
```

#### ✅ CORRECT

```typescript
function getUser(id: number): User | null {
  const user = users.find(u => u.id === id);
  return user ?? null;
}

// Or throw if not found
function getUserOrThrow(id: number): User {
  const user = users.find(u => u.id === id);
  if (user === undefined) {
    throw new NotFoundError(`User ${id} not found`);
  }
  return user;
}
```

---

### 8. Function Return Types

> ⚠️ **MANDATORY:** Explicitly declare return types for all functions.

#### ❌ INCORRECT

```typescript
function calculateTotal(items: Item[]) {
  return items.reduce((sum, item) => sum + item.price, 0);
}

const getUser = (id: number) => {
  return users.find(u => u.id === id);
};
```

#### ✅ CORRECT

```typescript
function calculateTotal(items: readonly Item[]): number {
  return items.reduce((sum, item) => sum + item.price, 0);
}

const getUser = (id: number): User | undefined => {
  return users.find(u => u.id === id);
};
```

---

## Enum Patterns

### String Enums (Preferred)

```typescript
enum TaskStatus {
  Pending = "pending",
  InProgress = "in_progress",
  Completed = "completed",
  Failed = "failed",
}

enum OperationType {
  Create = "create",
  Read = "read",
  Update = "update",
  Delete = "delete",
  Rename = "rename",
  Move = "move",
  Copy = "copy",
}

enum ExecutionPath {
  Direct = "direct",
  CodeGeneration = "code_generation",
}
```

### Exhaustive Switch Pattern

```typescript
function getStatusLabel(status: TaskStatus): string {
  switch (status) {
    case TaskStatus.Pending:
      return "Waiting to start";
    case TaskStatus.InProgress:
      return "Currently running";
    case TaskStatus.Completed:
      return "Successfully completed";
    case TaskStatus.Failed:
      return "Execution failed";
    default:
      // This ensures all cases are handled
      const _exhaustiveCheck: never = status;
      throw new Error(`Unhandled status: ${_exhaustiveCheck}`);
  }
}
```

---

## Type Definition Templates

### API Response Types

```typescript
interface ApiResponse<T> {
  readonly success: boolean;
  readonly data: T | null;
  readonly error: ApiError | null;
  readonly timestamp: Date;
}

interface ApiError {
  readonly code: string;
  readonly message: string;
  readonly details: Record<string, unknown> | null;
}

interface PaginatedResponse<T> {
  readonly items: readonly T[];
  readonly total: number;
  readonly page: number;
  readonly pageSize: number;
  readonly hasMore: boolean;
}
```

### Entity Types

```typescript
interface BaseEntity {
  readonly id: number;
  readonly createdAt: Date;
  readonly updatedAt: Date;
}

interface Task extends BaseEntity {
  readonly name: string;
  readonly description: string | null;
  readonly status: TaskStatus;
  readonly tags: readonly string[];
}
```

### Function Types

```typescript
type TaskHandler = (task: Task) => Promise<TaskResult>;
type ErrorHandler = (error: Error) => void;
type Validator<T> = (value: unknown) => value is T;
```

---

## Quick Reference

| Requirement | Rule |
|-------------|------|
| `any` type | ❌ Never use |
| `unknown` type | ⚠️ Only in type guards |
| `const` vs `let` | ✅ Default to `const` |
| `var` keyword | ❌ Never use |
| Switch statements | ✅ Must use enums |
| Object shapes | ✅ Must use interfaces |
| Immutable properties | ✅ Use `readonly` |
| Type assertions | ⚠️ Avoid, use type guards |
| Null handling | ✅ Explicit `| null` |
| Return types | ✅ Always explicit |

---

## Anti-Patterns Summary

| ❌ Anti-Pattern | ✅ Correct Pattern |
|----------------|-------------------|
| `data: any` | `data: SpecificType` |
| `response: unknown` | Type guard function |
| `let x = value` (no reassign) | `const x = value` |
| `switch (str)` with literals | `switch (enum)` with enum values |
| `obj as Type` | Type guard validation |
| `{ inline: types }` | Named interface |
| Missing return type | Explicit `: ReturnType` |
| `value!` non-null assertion | Proper null check |

---

## Related Specs

- [General Coding Standards](../../general-spec/01-foundation/01-coding-standards-foundation.md)
- [React Guidelines](./04-react-guidelines.md)
- [AI Code Generation](../05-features/26-ai-code-generation/00-overview.md)
