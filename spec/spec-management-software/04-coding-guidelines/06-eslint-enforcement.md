# 06. ESLint Enforcement

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-29  

---

## Purpose

ESLint configuration for enforcing TypeScript coding standards, including mandatory enum usage and prohibition of string union types for type definitions.

**Cross-References:**
- [TypeScript Guidelines](./03-typescript-guidelines.md)
- [Memory: coding-guidelines](/.lovable/memories/constraints/coding-guidelines.md)

---

## Configuration Overview

The project uses `typescript-eslint` with strict rules configured in `eslint.config.js`.

---

## Rule Categories

### 1. Type Safety Rules

| Rule | Setting | Description |
|------|---------|-------------|
| `@typescript-eslint/no-explicit-any` | `error` | Disallow `any` type usage |
| `@typescript-eslint/explicit-function-return-type` | `warn` | Require explicit return types |
| `@typescript-eslint/consistent-type-imports` | `warn` | Use `type` keyword for type-only imports |

---

### 2. Enum Enforcement Rules

| Rule | Setting | Description |
|------|---------|-------------|
| `@typescript-eslint/switch-exhaustiveness-check` | `warn` | Require exhaustive switch statements with enums |

**Pattern Enforcement:**

❌ **Disallowed - String Union Types:**
```typescript
// This pattern should be avoided
export type EventType = 'CLICK' | 'TYPE' | 'SCROLL';
```

✅ **Required - Proper Enums:**
```typescript
export enum EventType {
  CLICK = 'CLICK',
  TYPE = 'TYPE',
  SCROLL = 'SCROLL',
}
```

---

### 3. Immutability Rules

| Rule | Setting | Description |
|------|---------|-------------|
| `prefer-const` | `error` | Use `const` unless reassignment needed |
| `no-var` | `error` | Prohibit `var` keyword |

---

### 4. Null Safety Rules

| Rule | Setting | Description |
|------|---------|-------------|
| `@typescript-eslint/prefer-optional-chain` | `warn` | Use `?.` instead of `&&` chains |
| `@typescript-eslint/no-non-null-assertion` | `warn` | Avoid `!` non-null assertions |

---

## Full ESLint Configuration

```javascript
// eslint.config.js
import js from "@eslint/js";
import globals from "globals";
import reactHooks from "eslint-plugin-react-hooks";
import reactRefresh from "eslint-plugin-react-refresh";
import tseslint from "typescript-eslint";

export default tseslint.config(
  { ignores: ["dist"] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ["**/*.{ts,tsx}"],
    languageOptions: {
      ecmaVersion: 2020,
      globals: globals.browser,
    },
    plugins: {
      "react-hooks": reactHooks,
      "react-refresh": reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      "react-refresh/only-export-components": ["warn", { allowConstantExport: true }],
      "@typescript-eslint/no-unused-vars": "off",
      
      // Type Safety
      "@typescript-eslint/no-explicit-any": "error",
      "@typescript-eslint/explicit-function-return-type": ["warn", {
        allowExpressions: true,
        allowTypedFunctionExpressions: true,
        allowHigherOrderFunctions: true,
        allowDirectConstAssertionInArrowFunctions: true,
      }],
      
      // Immutability
      "prefer-const": "error",
      "no-var": "error",
      
      // Type Imports
      "@typescript-eslint/consistent-type-imports": ["warn", {
        prefer: "type-imports",
        disallowTypeAnnotations: false,
      }],
      
      // Enum/Switch Enforcement
      "@typescript-eslint/switch-exhaustiveness-check": "warn",
      
      // Null Safety
      "@typescript-eslint/prefer-optional-chain": "warn",
      "@typescript-eslint/no-non-null-assertion": "warn",
    },
  },
);
```

---

## Exhaustive Switch Pattern

All switch statements must use enums and handle all cases:

```typescript
enum TaskStatus {
  PENDING = 'pending',
  IN_PROGRESS = 'in_progress',
  COMPLETED = 'completed',
  FAILED = 'failed',
}

function getStatusLabel(status: TaskStatus): string {
  switch (status) {
    case TaskStatus.PENDING:
      return "Waiting to start";
    case TaskStatus.IN_PROGRESS:
      return "Currently running";
    case TaskStatus.COMPLETED:
      return "Successfully completed";
    case TaskStatus.FAILED:
      return "Execution failed";
    default:
      // Exhaustive check - TypeScript error if case is missing
      const _exhaustive: never = status;
      throw new Error(`Unhandled status: ${_exhaustive}`);
  }
}
```

---

## When to Use Enums vs Const Objects

### Use Enums For:
- Status workflows (TaskStatus, InstructionStatus)
- Type discriminators (EventType, ActionType)
- Category classifications (ModelCategory, Priority)
- Any value used in switch statements

### Use Const Objects For:
- Configuration mappings
- Route definitions
- API paths
- Display labels

```typescript
// Enum for type safety
enum Priority {
  LOW = 'low',
  MEDIUM = 'medium',
  HIGH = 'high',
  CRITICAL = 'critical',
}

// Const object for configuration
const PRIORITY_COLORS = {
  [Priority.LOW]: 'text-muted',
  [Priority.MEDIUM]: 'text-warning',
  [Priority.HIGH]: 'text-destructive',
  [Priority.CRITICAL]: 'text-destructive font-bold',
} as const;
```

---

## Migration Guide

### Converting String Unions to Enums

**Before:**
```typescript
export type UserRole = 'user' | 'admin';

interface User {
  role: UserRole;
}

function checkAdmin(role: UserRole): boolean {
  return role === 'admin';
}
```

**After:**
```typescript
export enum UserRole {
  USER = 'user',
  ADMIN = 'admin',
}

interface User {
  role: UserRole;
}

function checkAdmin(role: UserRole): boolean {
  return role === UserRole.ADMIN;
}
```

---

## Acceptance Criteria

- [ ] No `any` types in codebase (enforced by ESLint)
- [ ] All functions have explicit return types
- [ ] All switch statements use enums
- [ ] No string union types for status/type definitions
- [ ] `const` used by default, `let` only when reassignment needed
- [ ] No `var` usage anywhere in codebase
- [ ] Exhaustive switch checks implemented

---

## Related Specs

- [TypeScript Guidelines](./03-typescript-guidelines.md)
- [React Guidelines](./04-react-guidelines.md)
- [Seedable Config Pattern](./05-seedable-config-pattern.md)
