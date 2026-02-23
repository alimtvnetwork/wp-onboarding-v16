## Result Guard Rule — Mandatory `hasError()`/`isSafe()` Before Value Access

### Problem

The codebase uses `DbResult`, `DbResultSet`, `DbExecResult` (PHP) and `apperror.Result[T]`, `ResultSlice[T]` (Go) as structured result wrappers. While the existing code largely follows best practices, there is **no formal spec rule** mandating that callers must check `hasError()` or `isSafe()` before calling `.value()` / `.Value()`. Without a codified rule, future code could silently swallow errors by accessing values from failed results.

### Audit Findings

**Good news:** The current codebase is well-guarded.

- All PHP call sites in `AgentCrudReadTrait` and `AgentCrudWriteTrait` check `hasError()` before accessing `.value()`.
- All Go call sites across 21+ files consistently check `HasError()` or `IsSafe()` before `.Value()`.
- The `DbResult::empty()` static factory is a constructor (creates an empty record), not a boolean check — this is correct and requires no change.

**What needs to happen:**

1. Add a formal **Result Guard Rule** to the master coding guidelines
2. Add it to the error management spec for Go
3. Add it to the PHP standards

### Plan

#### 1. Update `spec/01-coding-guidelines/00-master-coding-guidelines.md`

Add a new section **"6.1 — Result Guard Rule (All Languages)"** under the existing "6. Error Handling" section:

```
### 6.1 — Result Guard Rule (Zero Silent Failures)

Every Result/DbResult wrapper MUST have its error state checked before
accessing the contained value. Accessing `.value()` / `.Value()` without
a prior `hasError()` or `isSafe()` guard is a spec violation.

**Principle:** No error may ever be swallowed. If a result carries an error,
it must be explicitly handled — logged, returned, or propagated.

PHP:
  $result = $query->queryOne(...);
  // WRONG: $result->value()  (no guard)
  // CORRECT:
  if ($result->hasError()) {
      $this->logger->logException($result->error(), 'context');
      return null;
  }
  return $result->value();

Go:
  result := dbutil.QueryOne[T](ctx, db, query, scanner, id)
  // WRONG: result.Value()  (no guard)
  // CORRECT:
  if result.HasError() {
      return apperror.Fail[T](result.Error())
  }
  return apperror.Ok(result.Value())

TypeScript:
  // If Result pattern is adopted in TS, same rule applies.
  // Check .hasError before .value access.
```

I'm not sure what your result card is, but the idea of the result, uh, when we try to get the result, uh, it should log it, uh, immediately so that we can reduce the steps as much as possible. Okay? When we click on Get Value, um, it should already log that there is a error. Okay? And if it has any issues, then it will return empty. Okay, uh, but also, at the same time, it will log from the framework itself. That is the idea.

  
  
Also update the **Quick Checklist** at the bottom to add:

```
[ ] Results: hasError()/isSafe() checked before .value()/.Value() — no swallowed errors
```

#### 2. Update `spec/05-error-manage/06-apperror-package/readme.md`

Add a subsection reinforcing the guard rule for Go result types, with explicit examples of correct and incorrect usage patterns.

#### 3. Update `spec/01-coding-guidelines/boolean-principles.md` (clarification note)

Add a note clarifying that `DbResult::empty()` and `DbResultSet::empty()` are **static factory constructors** (not boolean checks) and are exempt from the `is`/`has` prefix rule. Boolean query methods like `isEmpty()`, `isDefined()`, `hasError()`, `isSafe()` on the same class do follow P1 correctly.

#### 4. No code changes needed

The audit confirmed all existing call sites are already compliant. This is purely a spec/guideline update to prevent future regressions.

### Files to Modify


| File                                                       | Change                                                       |
| ---------------------------------------------------------- | ------------------------------------------------------------ |
| `spec/01-coding-guidelines/00-master-coding-guidelines.md` | Add section 6.1 (Result Guard Rule) + update Quick Checklist |
| `spec/05-error-manage/06-apperror-package/readme.md`       | Add guard rule subsection with Go examples                   |
| `spec/01-coding-guidelines/boolean-principles.md`          | Add factory method exemption note for `::empty()`            |
