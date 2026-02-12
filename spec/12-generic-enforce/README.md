# Generic Enforce Specification

**Version**: 1.0.0  
**Status**: Active  
**Applies to**: TypeScript, Golang, C#, Rust, and any language with generics/parametric polymorphism

---

## 1. Principle

> **Every concrete instantiation of a generic type MUST produce a named type alias.**  
> Raw generic instantiations (e.g., `Record<string, unknown>`, `map[string]interface{}`, `Dictionary<string, object>`) are **PROHIBITED** in public APIs, props, function signatures, and struct/interface fields.

### Why

| Benefit | Explanation |
|---------|-------------|
| **DRY** | Generic parameters are defined once; refactoring touches one line |
| **Self-documenting** | `ErrorContext` conveys domain intent; `Record<string, unknown>` conveys nothing |
| **Grep-friendly** | Search by alias name to find all usages of a specific instantiation |
| **Prevents drift** | Without a named alias, teams use the same raw generic in subtly different ways |
| **Compiler as documentation** | The alias name encodes what the data *means*, not just its shape |

---

## 2. Core Rules

### Rule GE-1: Named Alias Required

Every use of a generic type with concrete type parameters MUST have a corresponding named type alias **when the generic parameters carry domain meaning**.

```
❌ BAD:  field: Record<string, unknown>
✅ GOOD: type ErrorContext = Record<string, string | number | boolean>
         field: ErrorContext
```

### Rule GE-2: Zero Loose Types

The following raw types are **PROHIBITED** in all languages:

| Language | Prohibited Types |
|----------|-----------------|
| TypeScript | `any`, `unknown` (except parse boundaries), `Record<string, unknown>`, `object` |
| Golang | `interface{}`, `any` (Go 1.18+), `map[string]interface{}` |
| C# | `object`, `dynamic`, `Dictionary<string, object>` |
| Rust | `Box<dyn Any>` (except where truly needed for type erasure) |

**Exception**: `unknown` is permitted ONLY at JSON parse boundaries and type guard functions where it is immediately narrowed. Never in public APIs, props, hooks, store state, or struct fields.

### Rule GE-3: Hierarchy via Composition

When a generic type is used with multiple concrete instantiations, create a **type hierarchy** using composition, not repetition.

```
// Base generic
Generic<T, TKey>

// Named instantiations (REQUIRED)
type SpecificA = Generic<DomainTypeA, number>
type SpecificB = Generic<DomainTypeB, string>
```

### Rule GE-4: Trivial Generics Exception

Simple collection types with primitive parameters (`string[]`, `number[]`, `Map<string, string>`) do NOT require aliases unless they carry domain meaning.

```
✅ OK:    names: string[]
✅ OK:    headers: Record<string, string>  // key-value pairs with known value type
❌ BAD:   context: Record<string, unknown>  // unknown = no domain meaning
✅ GOOD:  type HttpHeaders = Record<string, string>  // if used in 3+ places
```

---

## 3. Pattern: The Student-Teacher Example

### Problem

```typescript
// Generic base
interface Student<T, TKey> {
  id: TKey;
  rights: T;
  name: string;
}

// BAD: Raw generic usage scattered everywhere
function getTeacher(): Student<BasicRights, number> { ... }
function getTeacherV2(): Student<BasicRightsV2, number> { ... }
```

### Solution: Named Instantiations

```typescript
// Named aliases enforce the mapping ONCE
type TeacherBasicRights = Student<BasicRights, number>;
type TeacherBasicRightsV2 = Student<BasicRightsV2, number>;

// Clean, DRY usage
function getTeacher(): TeacherBasicRights { ... }
function getTeacherV2(): TeacherBasicRightsV2 { ... }
```

### Why This Works

1. **Single source of truth**: If `TKey` changes from `number` to `string`, update ONE alias
2. **Discoverable**: `TeacherBasicRights` appears in IDE autocomplete; `Student<BasicRights, number>` does not
3. **Refactor-safe**: Rename the alias → compiler catches all usages
4. **Domain language**: Code reads like the business domain, not like a type system puzzle

---

## 4. Language-Specific Implementations

See the language-specific guides in this folder:

- [`typescript.md`](./typescript.md) — TypeScript / React
- [`golang.md`](./golang.md) — Go 1.18+
- [`csharp.md`](./csharp.md) — C# / .NET
- [`rust.md`](./rust.md) — Rust

---

## 5. Enforcement Checklist

- [ ] Every `Record<string, unknown>` replaced with a named domain type
- [ ] Every `interface{}` / `any` in Go replaced with a concrete type or constrained generic
- [ ] Every `Dictionary<string, object>` in C# replaced with a typed dictionary or domain type
- [ ] Every `Box<dyn Any>` in Rust reviewed — keep only if truly needed for type erasure
- [ ] All named aliases are co-located with their base generic or in a shared `types` module
- [ ] No raw generic instantiation with 2+ type parameters appears in function signatures

---

## 6. Related Specifications

- [Type Safety Rules](../02-typescript-standards/README.md)
- [DRY Principles](../01-coding-guidelines/dry-principles.md)
- [Golang Standards](../03-golang-standards/README.md)
