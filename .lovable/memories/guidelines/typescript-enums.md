# Memory: guidelines/typescript-enums
Updated: 2026-01-29

## Coding Standard: Use Proper TypeScript Enums

**Rule:** Always use proper TypeScript `enum` declarations instead of union type literals for categorical constants.

### ❌ Avoid This Pattern
```typescript
export type EventType = 
  | 'CLICK'
  | 'TYPE'
  | 'SCROLL';

export type Status = 'ACTIVE' | 'INACTIVE' | 'PENDING';
```

### ✅ Use This Pattern
```typescript
export enum EventType {
  CLICK = 'CLICK',
  TYPE = 'TYPE',
  SCROLL = 'SCROLL',
}

export enum Status {
  ACTIVE = 'ACTIVE',
  INACTIVE = 'INACTIVE',
  PENDING = 'PENDING',
}
```

## Benefits
- Better IDE autocompletion and refactoring support
- Runtime access to enum values (iteration, validation)
- Clearer intent and self-documenting code
- Easier to extend and maintain
- Consistent with ESLint enforcement rules

## ESLint Rules Enforcing This Standard

The following rules are configured in `eslint.config.js`:

| Rule | Purpose |
|------|---------|
| `@typescript-eslint/prefer-enum-initializers` | Require explicit values for all enum members |
| `@typescript-eslint/prefer-literal-enum-member` | Ensure enum values are string/number literals |
| `@typescript-eslint/prefer-as-const` | Prefer const assertions when enums aren't used |
| `@typescript-eslint/consistent-type-definitions` | Enforce `interface` over `type` for object shapes |

## Applies To
- Event types (`EventType`, `MessageType`)
- Status codes (`RecordingStatus`, `TaskStatus`)
- Strategy identifiers (`SelectorStrategy`)
- Reliability levels (`SelectorReliability`)
- Any categorical or enumerable constants

## When Union Types Are Acceptable
- Discriminated unions with different object shapes
- Generic type parameters
- Utility types (Partial, Pick, Omit, etc.)
- True union of unrelated types

```typescript
// This is fine - discriminated union of different shapes
type Action = 
  | { type: 'increment'; amount: number }
  | { type: 'reset' };
```
