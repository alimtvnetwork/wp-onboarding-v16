# 04. React Guidelines

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Purpose

React-specific coding standards for the Spec Management Software frontend. These rules ensure component consistency, optimal performance, and maintainable state management.

**Cross-References:**
- [TypeScript Guidelines](./03-typescript-guidelines.md)
- [ESLint Enforcement](./06-eslint-enforcement.md)
- [Seedable Config Pattern](./05-seedable-config-pattern.md)

---

## Component Patterns

### 1. Functional Components Only

> ⚠️ **MANDATORY:** Use functional components with hooks. No class components.

```typescript
// ✅ CORRECT
interface UserCardProps {
  readonly user: User;
  readonly onSelect: (userId: string) => void;
}

export function UserCard({ user, onSelect }: UserCardProps): JSX.Element {
  return (
    <div onClick={() => onSelect(user.id)}>
      <h3>{user.name}</h3>
    </div>
  );
}

// ❌ INCORRECT - Class components
class UserCard extends React.Component { ... }
```

---

### 2. Props Interface Pattern

> ⚠️ **MANDATORY:** All components must have explicit props interfaces with `readonly` properties.

```typescript
// ✅ CORRECT - Explicit interface with readonly
interface TaskListProps {
  readonly tasks: readonly Task[];
  readonly onTaskComplete: (taskId: string) => void;
  readonly isLoading?: boolean;
  readonly emptyMessage?: string;
}

export function TaskList({
  tasks,
  onTaskComplete,
  isLoading = false,
  emptyMessage = "No tasks found",
}: TaskListProps): JSX.Element {
  // ...
}

// ❌ INCORRECT - Inline types
export function TaskList({ tasks, onComplete }: { tasks: Task[]; onComplete: () => void }) { ... }

// ❌ INCORRECT - Missing readonly
interface TaskListProps {
  tasks: Task[];  // Should be readonly
}
```

---

### 3. Component File Structure

Each component file should follow this structure:

```typescript
// 1. Imports (grouped: external, internal, types)
import { useState, useCallback } from "react";
import { Button } from "@/components/ui/button";
import type { Task, TaskStatus } from "@/types";

// 2. Enums (if component-specific)
enum ViewMode {
  LIST = "list",
  GRID = "grid",
}

// 3. Props Interface
interface TaskCardProps {
  readonly task: Task;
  readonly onStatusChange: (status: TaskStatus) => void;
}

// 4. Component Implementation
export function TaskCard({ task, onStatusChange }: TaskCardProps): JSX.Element {
  // Hooks first
  const [isExpanded, setIsExpanded] = useState(false);
  
  // Callbacks
  const handleToggle = useCallback((): void => {
    setIsExpanded((prev) => !prev);
  }, []);
  
  // Render
  return (
    <div>
      {/* JSX */}
    </div>
  );
}

// 5. Sub-components (if needed, otherwise separate file)
function TaskCardHeader({ title }: { readonly title: string }): JSX.Element {
  return <h3>{title}</h3>;
}
```

---

### 4. Component Size Guidelines

| Component Size | Guideline |
|----------------|-----------|
| < 50 lines | Ideal - single responsibility |
| 50-100 lines | Acceptable - consider extraction |
| 100-150 lines | Review for splitting |
| > 150 lines | **Must refactor** into smaller components |

---

## Hooks Patterns

### 1. Custom Hook Naming

> ⚠️ **MANDATORY:** Custom hooks must start with `use` prefix.

```typescript
// ✅ CORRECT
function useTaskList(projectId: string): UseTaskListReturn { ... }
function useLocalStorage<T>(key: string, initialValue: T): UseLocalStorageReturn<T> { ... }
function useDebounce<T>(value: T, delay: number): T { ... }

// ❌ INCORRECT
function getTaskList() { ... }  // Not a hook
function taskListHook() { ... } // Missing 'use' prefix
```

---

### 2. Custom Hook Return Types

> ⚠️ **MANDATORY:** All custom hooks must have explicit return type interfaces.

```typescript
// ✅ CORRECT - Explicit return interface
interface UseTaskListReturn {
  readonly tasks: readonly Task[];
  readonly isLoading: boolean;
  readonly error: Error | null;
  readonly refetch: () => Promise<void>;
  readonly createTask: (data: CreateTaskData) => Promise<Task>;
}

function useTaskList(projectId: string): UseTaskListReturn {
  // Implementation
  return { tasks, isLoading, error, refetch, createTask };
}

// ❌ INCORRECT - Inferred return type
function useTaskList(projectId: string) {
  // Return type is inferred - not allowed
  return { tasks, isLoading };
}
```

---

### 3. Hook Dependencies

> ⚠️ **MANDATORY:** All hook dependencies must be explicitly listed. Use ESLint `react-hooks/exhaustive-deps`.

```typescript
// ✅ CORRECT - All dependencies listed
const handleSubmit = useCallback((): void => {
  submitForm(formData);
}, [formData, submitForm]);

useEffect((): void => {
  fetchData(projectId);
}, [projectId, fetchData]);

// ❌ INCORRECT - Missing dependencies
const handleSubmit = useCallback(() => {
  submitForm(formData);  // formData missing from deps
}, []);
```

---

### 4. Memoization Guidelines

| Hook | Use When |
|------|----------|
| `useMemo` | Expensive computations, derived data |
| `useCallback` | Callbacks passed to child components |
| `React.memo` | Pure components receiving object/array props |

```typescript
// ✅ CORRECT - Memoize expensive computation
const sortedTasks = useMemo((): readonly Task[] => {
  return [...tasks].sort((a, b) => a.priority - b.priority);
}, [tasks]);

// ✅ CORRECT - Memoize callback for child
const handleSelect = useCallback((id: string): void => {
  onTaskSelect(id);
}, [onTaskSelect]);

// ❌ INCORRECT - Unnecessary memoization
const name = useMemo(() => user.name, [user.name]);  // Primitive - no need
```

---

## State Management

### 1. State Hierarchy

```
┌─────────────────────────────────────────────────────────────┐
│                     Global State                             │
│  (React Query for server state, Context for app state)      │
├─────────────────────────────────────────────────────────────┤
│                     Feature State                            │
│  (Context or zustand for feature-specific shared state)     │
├─────────────────────────────────────────────────────────────┤
│                     Local State                              │
│  (useState, useReducer for component-specific state)        │
└─────────────────────────────────────────────────────────────┘
```

---

### 2. React Query for Server State

> ⚠️ **MANDATORY:** Use React Query (TanStack Query) for all server state.

```typescript
// ✅ CORRECT - React Query for data fetching
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";

interface UseProjectReturn {
  readonly project: Project | undefined;
  readonly isLoading: boolean;
  readonly error: Error | null;
}

function useProject(projectId: string): UseProjectReturn {
  const { data, isLoading, error } = useQuery({
    queryKey: ["projects", projectId],
    queryFn: (): Promise<Project> => fetchProject(projectId),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
  
  return { project: data, isLoading, error };
}

// ✅ CORRECT - Mutation with cache invalidation
function useCreateTask(projectId: string): UseMutationResult<Task, Error, CreateTaskData> {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: CreateTaskData): Promise<Task> => createTask(projectId, data),
    onSuccess: (): void => {
      queryClient.invalidateQueries({ queryKey: ["tasks", projectId] });
    },
  });
}

// ❌ INCORRECT - useState for server data
const [tasks, setTasks] = useState<Task[]>([]);
useEffect(() => {
  fetchTasks().then(setTasks);  // Don't do this
}, []);
```

---

### 3. Local State with useState

Use for UI-only state that doesn't need to be shared:

```typescript
// ✅ CORRECT - Local UI state
function TaskCard({ task }: TaskCardProps): JSX.Element {
  const [isExpanded, setIsExpanded] = useState(false);
  const [activeTab, setActiveTab] = useState<TabType>(TabType.DETAILS);
  
  return (
    <div>
      <button onClick={() => setIsExpanded((prev) => !prev)}>
        {isExpanded ? "Collapse" : "Expand"}
      </button>
    </div>
  );
}
```

---

### 4. Context for Shared UI State

Use Context for UI state shared across component trees:

```typescript
// ✅ CORRECT - Context with explicit types
enum SidebarState {
  EXPANDED = "expanded",
  COLLAPSED = "collapsed",
}

interface SidebarContextValue {
  readonly state: SidebarState;
  readonly toggle: () => void;
  readonly expand: () => void;
  readonly collapse: () => void;
}

const SidebarContext = createContext<SidebarContextValue | null>(null);

function useSidebar(): SidebarContextValue {
  const context = useContext(SidebarContext);
  if (context === null) {
    throw new Error("useSidebar must be used within SidebarProvider");
  }
  return context;
}

function SidebarProvider({ children }: { readonly children: ReactNode }): JSX.Element {
  const [state, setState] = useState<SidebarState>(SidebarState.EXPANDED);
  
  const value = useMemo((): SidebarContextValue => ({
    state,
    toggle: () => setState((s) => 
      s === SidebarState.EXPANDED ? SidebarState.COLLAPSED : SidebarState.EXPANDED
    ),
    expand: () => setState(SidebarState.EXPANDED),
    collapse: () => setState(SidebarState.COLLAPSED),
  }), [state]);
  
  return (
    <SidebarContext.Provider value={value}>
      {children}
    </SidebarContext.Provider>
  );
}
```

---

### 5. useReducer for Complex State

Use for state with multiple related values or complex transitions:

```typescript
// ✅ CORRECT - useReducer with enum actions
enum FormActionType {
  SET_FIELD = "SET_FIELD",
  RESET = "RESET",
  SUBMIT_START = "SUBMIT_START",
  SUBMIT_SUCCESS = "SUBMIT_SUCCESS",
  SUBMIT_ERROR = "SUBMIT_ERROR",
}

interface FormState {
  readonly values: Record<string, string>;
  readonly errors: Record<string, string>;
  readonly isSubmitting: boolean;
  readonly isSubmitted: boolean;
}

type FormAction =
  | { readonly type: FormActionType.SET_FIELD; readonly field: string; readonly value: string }
  | { readonly type: FormActionType.RESET }
  | { readonly type: FormActionType.SUBMIT_START }
  | { readonly type: FormActionType.SUBMIT_SUCCESS }
  | { readonly type: FormActionType.SUBMIT_ERROR; readonly errors: Record<string, string> };

function formReducer(state: FormState, action: FormAction): FormState {
  switch (action.type) {
    case FormActionType.SET_FIELD:
      return {
        ...state,
        values: { ...state.values, [action.field]: action.value },
      };
    case FormActionType.RESET:
      return initialFormState;
    case FormActionType.SUBMIT_START:
      return { ...state, isSubmitting: true };
    case FormActionType.SUBMIT_SUCCESS:
      return { ...state, isSubmitting: false, isSubmitted: true };
    case FormActionType.SUBMIT_ERROR:
      return { ...state, isSubmitting: false, errors: action.errors };
    default:
      const _exhaustive: never = action;
      throw new Error(`Unhandled action: ${_exhaustive}`);
  }
}
```

---

## Form Handling

### 1. React Hook Form with Zod

> ⚠️ **MANDATORY:** Use react-hook-form with zod for all forms.

```typescript
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";

// Schema with validation
const createTaskSchema = z.object({
  title: z.string()
    .trim()
    .min(1, "Title is required")
    .max(100, "Title must be less than 100 characters"),
  description: z.string()
    .trim()
    .max(1000, "Description must be less than 1000 characters")
    .optional(),
  priority: z.nativeEnum(Priority),
});

type CreateTaskFormData = z.infer<typeof createTaskSchema>;

function CreateTaskForm({ onSubmit }: CreateTaskFormProps): JSX.Element {
  const form = useForm<CreateTaskFormData>({
    resolver: zodResolver(createTaskSchema),
    defaultValues: {
      title: "",
      description: "",
      priority: Priority.MEDIUM,
    },
  });
  
  const handleSubmit = form.handleSubmit((data: CreateTaskFormData): void => {
    onSubmit(data);
  });
  
  return (
    <form onSubmit={handleSubmit}>
      {/* Form fields */}
    </form>
  );
}
```

---

## Event Handlers

### 1. Handler Naming Convention

| Pattern | Usage |
|---------|-------|
| `handle{Event}` | Local event handlers |
| `on{Event}` | Props callback names |

```typescript
interface TaskItemProps {
  readonly task: Task;
  readonly onComplete: (taskId: string) => void;  // Prop uses 'on' prefix
  readonly onDelete: (taskId: string) => void;
}

function TaskItem({ task, onComplete, onDelete }: TaskItemProps): JSX.Element {
  // Local handlers use 'handle' prefix
  const handleCompleteClick = useCallback((): void => {
    onComplete(task.id);
  }, [task.id, onComplete]);
  
  const handleDeleteClick = useCallback((): void => {
    onDelete(task.id);
  }, [task.id, onDelete]);
  
  return (
    <div>
      <button onClick={handleCompleteClick}>Complete</button>
      <button onClick={handleDeleteClick}>Delete</button>
    </div>
  );
}
```

---

### 2. Event Type Annotations

```typescript
// ✅ CORRECT - Explicit event types
const handleInputChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
  setValue(event.target.value);
};

const handleFormSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
  event.preventDefault();
  submitForm();
};

const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>): void => {
  if (event.key === "Enter") {
    handleSubmit();
  }
};
```

---

## Styling with Tailwind

### 1. Use Semantic Design Tokens

> ⚠️ **MANDATORY:** Never use raw color values. Always use semantic tokens.

```typescript
// ✅ CORRECT - Semantic tokens
<div className="bg-background text-foreground border-border">
  <button className="bg-primary text-primary-foreground hover:bg-primary/90">
    Submit
  </button>
  <span className="text-muted-foreground">Helper text</span>
</div>

// ❌ INCORRECT - Raw color values
<div className="bg-white text-black border-gray-200">
  <button className="bg-blue-500 text-white">Submit</button>
</div>
```

---

### 2. Class Composition with cn()

```typescript
import { cn } from "@/lib/utils";

interface ButtonProps {
  readonly variant?: "default" | "destructive" | "outline";
  readonly size?: "sm" | "md" | "lg";
  readonly className?: string;
}

function Button({ variant = "default", size = "md", className }: ButtonProps): JSX.Element {
  return (
    <button
      className={cn(
        "rounded-md font-medium transition-colors",
        // Variant styles
        variant === "default" && "bg-primary text-primary-foreground",
        variant === "destructive" && "bg-destructive text-destructive-foreground",
        variant === "outline" && "border border-input bg-background",
        // Size styles
        size === "sm" && "h-8 px-3 text-sm",
        size === "md" && "h-10 px-4",
        size === "lg" && "h-12 px-6 text-lg",
        // Custom classes
        className
      )}
    >
      {/* ... */}
    </button>
  );
}
```

---

## Performance Patterns

### 1. Lazy Loading

```typescript
import { lazy, Suspense } from "react";
import { Skeleton } from "@/components/ui/skeleton";

// Lazy load heavy components
const MarkdownEditor = lazy(() => import("@/components/MarkdownEditor"));
const ChartDashboard = lazy(() => import("@/components/ChartDashboard"));

function EditorPage(): JSX.Element {
  return (
    <Suspense fallback={<Skeleton className="h-96 w-full" />}>
      <MarkdownEditor />
    </Suspense>
  );
}
```

---

### 2. Virtualization for Long Lists

```typescript
// Use virtualization for lists > 100 items
import { useVirtualizer } from "@tanstack/react-virtual";

function VirtualizedTaskList({ tasks }: { readonly tasks: readonly Task[] }): JSX.Element {
  const parentRef = useRef<HTMLDivElement>(null);
  
  const virtualizer = useVirtualizer({
    count: tasks.length,
    getScrollElement: () => parentRef.current,
    estimateSize: () => 64,
  });
  
  return (
    <div ref={parentRef} className="h-[500px] overflow-auto">
      <div style={{ height: virtualizer.getTotalSize() }}>
        {virtualizer.getVirtualItems().map((virtualItem) => (
          <TaskRow
            key={tasks[virtualItem.index].id}
            task={tasks[virtualItem.index]}
            style={{
              position: "absolute",
              top: virtualItem.start,
              height: virtualItem.size,
            }}
          />
        ))}
      </div>
    </div>
  );
}
```

---

## Quick Reference

| Requirement | Rule |
|-------------|------|
| Component type | Functional only |
| Props interface | `readonly` properties, explicit interface |
| Custom hooks | `use` prefix, explicit return type |
| Server state | React Query only |
| Forms | react-hook-form + zod |
| Styling | Semantic Tailwind tokens only |
| Event handlers | `handle*` for local, `on*` for props |
| Memoization | `useCallback` for child callbacks |
| Lists > 100 items | Virtualization required |

---

## Anti-Patterns Summary

| ❌ Anti-Pattern | ✅ Correct Pattern |
|----------------|-------------------|
| Class components | Functional components |
| Inline prop types | Named interfaces |
| `useState` for server data | React Query |
| Missing hook deps | Exhaustive deps |
| Raw color classes | Semantic tokens |
| Inline styles | Tailwind classes |
| `any` in event handlers | Typed event handlers |
| Large monolithic components | Small focused components |

---

## Related Specs

- [TypeScript Guidelines](./03-typescript-guidelines.md)
- [ESLint Enforcement](./06-eslint-enforcement.md)
- [Seedable Config Pattern](./05-seedable-config-pattern.md)
- [Chat UI Style](/.lovable/memories/style/chat-ui.md)
