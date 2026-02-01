# Memory: constraints/coding-guidelines

**Updated:** 2026-01-30  
**Purpose:** Strict coding standards enforced across the project

---

## TypeScript Rules

### Type Safety

| Rule | Requirement |
|------|-------------|
| No `any` | Prohibited - use explicit types |
| No `unknown` | Prohibited - define proper interfaces |
| Object shapes | Explicit interfaces required |
| Function returns | Explicit return types mandatory |

### Enums (CRITICAL)

**Always use proper TypeScript enums instead of string union types.**

❌ **Incorrect - String Union Types:**
```typescript
export type EventType = 
  | 'CLICK'
  | 'TYPE'
  | 'SCROLL'
  | 'TAB_SWITCH';
```

✅ **Correct - Proper Enum:**
```typescript
export enum EventType {
  CLICK = 'CLICK',
  TYPE = 'TYPE',
  SCROLL = 'SCROLL',
  TAB_SWITCH = 'TAB_SWITCH',
}
```

**Rationale:**
- Enums provide better IntelliSense and refactoring support
- Runtime value access for serialization/logging
- Consistent with switch statement enforcement
- All switch statements must use Enums

---

## ESLint Enforcement

The following ESLint rules are configured to enforce these standards:

| Rule | Setting | Purpose |
|------|---------|---------|
| `@typescript-eslint/no-explicit-any` | error | Disallow `any` type |
| `@typescript-eslint/explicit-function-return-type` | warn | Require return types |
| `@typescript-eslint/switch-exhaustiveness-check` | warn | Exhaustive switch checks |
| `@typescript-eslint/prefer-optional-chain` | warn | Use `?.` syntax |
| `@typescript-eslint/no-non-null-assertion` | warn | Avoid `!` assertions |
| `prefer-const` | error | Use `const` by default |
| `no-var` | error | Disallow `var` keyword |

---

## Immutability

| Pattern | Usage |
|---------|-------|
| `readonly` | Preferred for object properties |
| `const` | Required for variable declarations |
| `as const` | Use for literal type inference |

---

## Database Schema

| Element | Convention |
|---------|------------|
| Table names | PascalCase |
| Field names | camelCase |
| Enum fields | PascalCase values |

---

## Error Code Management

**Total: 347 error codes** across 13 ranges (1xxx-13xxx).

| Range | Category | Owner |
|-------|----------|-------|
| 1xxx | Validation | Shared |
| 2xxx | Authentication | Backend |
| 3xxx | Database | Backend |
| 4xxx | External Services | Backend |
| 5xxx | Business Logic | Shared |
| 6xxx | File System/Git | Backend |
| 7xxx | LLM/Config/CLI | Backend |
| 8xxx | RAG/Knowledge | Backend |
| 9xxx | System/Consistency | Backend |
| 10xxx | Context Window | Backend |
| 11xxx | Instructions | Backend |
| 12xxx | Code Generation | Backend |
| 13xxx | Project Editor | Frontend |

**Reference:** [Error Code Registry](../../../spec/spec-management-software/06-error-management/error-code-registry.md)

---

## Switch Statements

- All switch statements **must** use Enums
- Exhaustive checks required (use `never` for default)
- No magic strings in case clauses

---

## Exhaustive Switch Pattern

```typescript
function handleStatus(status: TaskStatus): string {
  switch (status) {
    case TaskStatus.PENDING:
      return "Waiting";
    case TaskStatus.IN_PROGRESS:
      return "Running";
    case TaskStatus.COMPLETED:
      return "Done";
    default:
      const _exhaustive: never = status;
      throw new Error(`Unhandled: ${_exhaustive}`);
  }
}
```

---

## React Patterns

### Components

| Rule | Requirement |
|------|-------------|
| Style | Functional components only |
| Props | `readonly` interface with explicit types |
| Naming | PascalCase, descriptive names |

```typescript
interface TaskCardProps {
  readonly task: Task;
  readonly onComplete: (id: string) => void;
}

export function TaskCard({ task, onComplete }: TaskCardProps): JSX.Element {
  // ...
}
```

### Custom Hooks

| Rule | Requirement |
|------|-------------|
| Prefix | Must start with `use` |
| Return type | Explicit interface required |
| Dependencies | Explicit in dependency arrays |

```typescript
interface UseTaskListReturn {
  readonly tasks: readonly Task[];
  readonly isLoading: boolean;
  readonly error: Error | null;
}

export function useTaskList(): UseTaskListReturn {
  // ...
}
```

### State Management

| State Type | Solution |
|------------|----------|
| Server state | React Query (mandatory) |
| Complex local | `useReducer` with exhaustive switch |
| Simple local | `useState` |
| Global UI | React Context |

### Forms

- Use `react-hook-form` with Zod schema validation
- Never use uncontrolled forms for data submission

```typescript
const schema = z.object({
  title: z.string().min(1),
  priority: z.nativeEnum(Priority),
});

const form = useForm<z.infer<typeof schema>>({
  resolver: zodResolver(schema),
});
```

### Styling

| Rule | Requirement |
|------|-------------|
| Framework | Tailwind CSS only |
| Colors | Semantic tokens (`bg-primary`, not hex) |
| Composition | Use `cn()` utility |
| No inline | Avoid `style` prop |

### Performance

| Threshold | Requirement |
|-----------|-------------|
| Lists > 100 items | Virtualization mandatory |
| Routes | Lazy loading with `React.lazy` |
| Heavy components | `React.memo` with custom comparator |
