# 01. Coding Standards

> **Applies To:** All languages (PHP, TypeScript, Python)  
> **Priority:** CRITICAL - Must be followed for all code

---

## 1. Naming Conventions

### 1.1 General Rules

| Element | Convention | Examples |
|---------|------------|----------|
| Variables | `camelCase` | `userId`, `examStatus`, `isActive` |
| Functions/Methods | `camelCase` | `getUserById()`, `calculateDeadline()` |
| Classes | `PascalCase` | `UserService`, `ExamRepository` |
| Interfaces | `PascalCase` with prefix | `IUserService`, `IRepository` |
| Constants | `SCREAMING_SNAKE_CASE` | `MAX_RETRY_COUNT`, `DEFAULT_TIMEOUT` |
| Enums | `PascalCase` | `ParticipantStatus`, `LogLevel` |
| Enum Values | `PascalCase` or `SCREAMING_SNAKE` | `Active`, `SOFT_DEADLINE_REACHED` |
| Database Columns | `PascalCase` | `CreatedAt`, `UserId`, `ExamId` |
| Database Tables | `PascalCase` (singular) | `User`, `ExamParticipant` |
| ORM Properties | `camelCase` | `createdAt`, `userId`, `examId` |
| File Names | `PascalCase` for classes | `UserService.php`, `UserService.ts` |

### 1.2 Boolean Naming

> ⚠️ **CRITICAL:** All booleans MUST be prefixed with `is`, `has`, `can`, `should`, or `was`.

| ❌ INCORRECT | ✅ CORRECT |
|--------------|------------|
| `active` | `isActive` |
| `deleted` | `isDeleted` |
| `permissions` | `hasPermissions` |
| `editable` | `canEdit` |
| `notify` | `shouldNotify` |
| `processed` | `wasProcessed` |

### 1.3 Function Naming

| Action | Prefix | Example |
|--------|--------|---------|
| Retrieve single | `get` | `getUser()`, `getExamById()` |
| Retrieve multiple | `list`, `getAll` | `listUsers()`, `getAllExams()` |
| Check boolean | `is`, `has`, `can` | `isValid()`, `hasAccess()` |
| Create | `create` | `createUser()`, `createExam()` |
| Update | `update` | `updateUser()`, `updateStatus()` |
| Delete | `delete`, `remove` | `deleteUser()`, `removeParticipant()` |
| Validate | `validate` | `validateEmail()`, `validateInput()` |
| Transform | `to`, `from`, `parse` | `toJson()`, `fromArray()`, `parseDate()` |

---

## 2. Function Size Limits

### 2.1 The 15-Line Rule

> ⚠️ **MANDATORY:** No function may exceed 15 lines of logic (excluding blank lines, comments, and closing braces).

**Rationale:**
- Forces single responsibility
- Improves testability
- Enhances readability
- Reduces cognitive load

### 2.2 Examples

#### ❌ INCORRECT - Too Long

```php
// PHP - 25+ lines, multiple responsibilities
function processExamSubmission($examId, $userId, $answers) {
    $exam = $this->examRepo->find($examId);
    if ($exam === null) {
        throw new NotFoundException("Exam not found");
    }
    
    $participant = $this->participantRepo->findByExamAndUser($examId, $userId);
    if ($participant === null) {
        throw new NotFoundException("Participant not found");
    }
    
    if ($participant->status !== 'active') {
        throw new ValidationException("Participant is not active");
    }
    
    $score = 0;
    foreach ($answers as $answer) {
        $question = $this->questionRepo->find($answer['questionId']);
        if ($question->correctAnswer === $answer['value']) {
            $score++;
        }
    }
    
    $percentage = ($score / count($answers)) * 100;
    
    $participant->score = $percentage;
    $participant->status = 'completed';
    $participant->completedAt = new DateTime();
    
    $this->participantRepo->save($participant);
    $this->notificationService->sendCompletionEmail($participant);
    
    return $participant;
}
```

#### ✅ CORRECT - Split Into Focused Functions

```php
// PHP - Each function under 15 lines
function processExamSubmission(int $examId, int $userId, array $answers): Participant {
    $exam = $this->getExamOrFail($examId);
    $participant = $this->getActiveParticipantOrFail($examId, $userId);
    
    $score = $this->calculateScore($answers);
    
    return $this->completeParticipation($participant, $score);
}

private function getExamOrFail(int $examId): Exam {
    $exam = $this->examRepo->find($examId);
    return $exam ?? throw new NotFoundException("Exam not found");
}

private function getActiveParticipantOrFail(int $examId, int $userId): Participant {
    $participant = $this->participantRepo->findByExamAndUser($examId, $userId);
    
    if (isNull($participant)) {
        throw new NotFoundException("Participant not found");
    }
    
    if (isNotEqual($participant->status, 'active')) {
        throw new ValidationException("Participant is not active");
    }
    
    return $participant;
}

private function calculateScore(array $answers): float {
    $correct = 0;
    
    foreach ($answers as $answer) {
        $question = $this->questionRepo->find($answer['questionId']);
        $correct += $this->isCorrectAnswer($question, $answer) ? 1 : 0;
    }
    
    return ($correct / count($answers)) * 100;
}

private function completeParticipation(Participant $participant, float $score): Participant {
    $participant->score = $score;
    $participant->status = 'completed';
    $participant->completedAt = new DateTime();
    
    $this->participantRepo->save($participant);
    $this->notificationService->sendCompletionEmail($participant);
    
    return $participant;
}
```

---

## 3. Positive Boolean Logic (If-Avoidance)

### 3.1 The Problem With Negations

Negation operators (`!`, `not`) are a leading source of logic bugs:
- Double negatives confuse readers
- Easy to miss the `!` during code review
- Mental overhead to parse "not not valid"

### 3.2 Boolean Helper Functions

> ⚠️ **MANDATORY:** Never use `!` operator. Use positive helper functions instead.

#### PHP Implementation

```php
class BooleanHelpers {
    public static function isNull(mixed $value): bool {
        return $value === null;
    }
    
    public static function isNotNull(mixed $value): bool {
        return $value !== null;
    }
    
    public static function isEmpty(mixed $value): bool {
        return empty($value);
    }
    
    public static function isNotEmpty(mixed $value): bool {
        return !empty($value);
    }
    
    public static function isTrue(mixed $value): bool {
        return $value === true;
    }
    
    public static function isFalse(mixed $value): bool {
        return $value === false;
    }
    
    public static function isEqual(mixed $a, mixed $b): bool {
        return $a === $b;
    }
    
    public static function isNotEqual(mixed $a, mixed $b): bool {
        return $a !== $b;
    }
    
    public static function isZero(int|float $value): bool {
        return $value === 0 || $value === 0.0;
    }
    
    public static function isPositive(int|float $value): bool {
        return $value > 0;
    }
    
    public static function isNegative(int|float $value): bool {
        return $value < 0;
    }
    
    public static function hasKey(array $array, string|int $key): bool {
        return array_key_exists($key, $array);
    }
    
    public static function hasNoKey(array $array, string|int $key): bool {
        return !array_key_exists($key, $array);
    }
    
    public static function isInArray(mixed $needle, array $haystack): bool {
        return in_array($needle, $haystack, true);
    }
    
    public static function isNotInArray(mixed $needle, array $haystack): bool {
        return !in_array($needle, $haystack, true);
    }
    
    // Function existence checks (PHP-specific)
    public static function isFunctionExists(string $name): bool {
        return function_exists($name);
    }
    
    public static function isClassExists(string $name): bool {
        return class_exists($name);
    }
}
```

#### TypeScript Implementation

```typescript
export const BooleanHelpers = {
  isNull: (value: unknown): value is null => value === null,
  isNotNull: <T>(value: T | null): value is T => value !== null,
  
  isUndefined: (value: unknown): value is undefined => value === undefined,
  isNotUndefined: <T>(value: T | undefined): value is T => value !== undefined,
  
  isNullish: (value: unknown): value is null | undefined => value == null,
  isNotNullish: <T>(value: T | null | undefined): value is T => value != null,
  
  isEmpty: (value: string | unknown[]): boolean => value.length === 0,
  isNotEmpty: (value: string | unknown[]): boolean => value.length > 0,
  
  isTrue: (value: unknown): value is true => value === true,
  isFalse: (value: unknown): value is false => value === false,
  
  isEqual: <T>(a: T, b: T): boolean => a === b,
  isNotEqual: <T>(a: T, b: T): boolean => a !== b,
  
  isZero: (value: number): boolean => value === 0,
  isPositive: (value: number): boolean => value > 0,
  isNegative: (value: number): boolean => value < 0,
  
  hasKey: <T extends object>(obj: T, key: PropertyKey): boolean => key in obj,
  hasNoKey: <T extends object>(obj: T, key: PropertyKey): boolean => !(key in obj),
  
  isInArray: <T>(needle: T, haystack: T[]): boolean => haystack.includes(needle),
  isNotInArray: <T>(needle: T, haystack: T[]): boolean => !haystack.includes(needle),
};
```

#### Python Implementation

```python
from typing import Any, TypeVar, Optional

T = TypeVar('T')

class BooleanHelpers:
    @staticmethod
    def is_none(value: Any) -> bool:
        return value is None
    
    @staticmethod
    def is_not_none(value: Any) -> bool:
        return value is not None
    
    @staticmethod
    def is_empty(value: Any) -> bool:
        return len(value) == 0 if hasattr(value, '__len__') else not value
    
    @staticmethod
    def is_not_empty(value: Any) -> bool:
        return not BooleanHelpers.is_empty(value)
    
    @staticmethod
    def is_true(value: Any) -> bool:
        return value is True
    
    @staticmethod
    def is_false(value: Any) -> bool:
        return value is False
    
    @staticmethod
    def is_equal(a: Any, b: Any) -> bool:
        return a == b
    
    @staticmethod
    def is_not_equal(a: Any, b: Any) -> bool:
        return a != b
    
    @staticmethod
    def is_zero(value: int | float) -> bool:
        return value == 0
    
    @staticmethod
    def is_positive(value: int | float) -> bool:
        return value > 0
    
    @staticmethod
    def is_negative(value: int | float) -> bool:
        return value < 0
    
    @staticmethod
    def has_key(dictionary: dict, key: Any) -> bool:
        return key in dictionary
    
    @staticmethod
    def has_no_key(dictionary: dict, key: Any) -> bool:
        return key not in dictionary
    
    @staticmethod
    def is_in_list(needle: T, haystack: list[T]) -> bool:
        return needle in haystack
    
    @staticmethod
    def is_not_in_list(needle: T, haystack: list[T]) -> bool:
        return needle not in haystack
```

### 3.3 Usage Comparison

#### ❌ INCORRECT - Using Negations

```php
// PHP
if (!$user) { ... }
if (!is_null($value)) { ... }
if (!in_array($item, $list)) { ... }
if (!$participant->isActive) { ... }
```

```typescript
// TypeScript
if (!user) { ... }
if (value !== null) { ... }
if (!list.includes(item)) { ... }
if (!participant.isActive) { ... }
```

```python
# Python
if not user: ...
if value is not None: ...
if item not in list: ...
if not participant.is_active: ...
```

#### ✅ CORRECT - Using Positive Helpers

```php
// PHP
if (isNull($user)) { ... }
if (isNotNull($value)) { ... }
if (isNotInArray($item, $list)) { ... }
if (isFalse($participant->isActive)) { ... }
```

```typescript
// TypeScript
if (isNull(user)) { ... }
if (isNotNull(value)) { ... }
if (isNotInArray(item, list)) { ... }
if (isFalse(participant.isActive)) { ... }
```

```python
# Python
if is_none(user): ...
if is_not_none(value): ...
if is_not_in_list(item, items): ...
if is_false(participant.is_active): ...
```

---

## 4. Early Returns

### 4.1 The Pattern

Always check failure conditions first and return/throw immediately. Avoid deep nesting.

### 4.2 Examples

#### ❌ INCORRECT - Deep Nesting

```php
function processUser($userId) {
    $user = $this->userRepo->find($userId);
    if ($user !== null) {
        if ($user->isActive) {
            if ($user->hasPermission('edit')) {
                // Actual logic buried 3 levels deep
                return $this->doSomething($user);
            } else {
                throw new ForbiddenException("No permission");
            }
        } else {
            throw new ValidationException("User inactive");
        }
    } else {
        throw new NotFoundException("User not found");
    }
}
```

#### ✅ CORRECT - Early Returns

```php
function processUser(int $userId): Result {
    $user = $this->userRepo->find($userId);
    
    if (isNull($user)) {
        throw new NotFoundException("User not found");
    }
    
    if (isFalse($user->isActive)) {
        throw new ValidationException("User inactive");
    }
    
    if (isFalse($user->hasPermission('edit'))) {
        throw new ForbiddenException("No permission");
    }
    
    // Happy path at the end, no nesting
    return $this->doSomething($user);
}
```

---

## 5. Single Responsibility

### 5.1 Functions

Each function should do ONE thing. If you can't describe what a function does without using "and", split it.

| ❌ INCORRECT | ✅ CORRECT |
|--------------|------------|
| `validateAndSaveUser()` | `validateUser()` + `saveUser()` |
| `fetchAndTransformData()` | `fetchData()` + `transformData()` |
| `parseAndLogError()` | `parseError()` + `logError()` |

### 5.2 Classes

Each class should have one reason to change. Follow the Service/Repository pattern:

| Layer | Responsibility |
|-------|---------------|
| Controller | HTTP request/response handling |
| Service | Business logic orchestration |
| Repository | Data access abstraction |
| Model/Entity | Data structure definition |
| Helper | Stateless utility functions |

---

## 6. Comments

### 6.1 When to Comment

| Situation | Action |
|-----------|--------|
| Complex algorithm | Add explanation comment |
| Non-obvious business rule | Document the "why" |
| Workaround for bug | Link to issue tracker |
| TODO items | Use `// TODO:` format |
| Public API | Add doc blocks |

### 6.2 When NOT to Comment

| Situation | Action |
|-----------|--------|
| What the code does | Let code speak for itself |
| Obvious operations | No comment needed |
| Outdated information | Delete the comment |

### 6.3 Comment Format

```php
/**
 * Calculates the effective deadline for a participant.
 * 
 * Priority chain: Admin Override > Extensions > Exam Default
 * 
 * @param Participant $participant The participant entity
 * @return DateTime The calculated deadline
 * @throws InvalidStateException If participant has no exam
 */
public function calculateEffectiveDeadline(Participant $participant): DateTime
```

---

## 7. Import Organization

### 7.1 Order

1. Built-in/Standard library
2. External packages (third-party)
3. Internal packages (your codebase)
4. Relative imports (same module)

### 7.2 Examples

```typescript
// TypeScript
// 1. Built-in
import { useState, useEffect } from 'react';

// 2. External packages
import { z } from 'zod';
import { format } from 'date-fns';

// 3. Internal packages
import { UserService } from '@/services/UserService';
import { BooleanHelpers } from '@/utils/BooleanHelpers';

// 4. Relative
import { UserCard } from './UserCard';
import type { UserProps } from './types';
```

```python
# Python
# 1. Standard library
from datetime import datetime
from typing import Optional

# 2. Third-party
from pydantic import BaseModel
from fastapi import HTTPException

# 3. Internal
from app.services.user_service import UserService
from app.utils.boolean_helpers import is_none

# 4. Relative
from .models import User
from .schemas import UserCreate
```

---

## 8. Type Safety

### 8.1 Always Use Types

- **PHP:** Use strict types and type hints
- **TypeScript:** NEVER use `any` or `unknown` (except type guards). See [TypeScript Guidelines](../../spec-management-software/04-coding-guidelines/03-typescript-guidelines.md)
- **Python:** Use type hints throughout
- **Golang:** Use explicit types for all variables

### 8.2 TypeScript Strict Rules

> ⚠️ **MANDATORY:** The following rules apply to ALL TypeScript code:

| Rule | Requirement |
|------|-------------|
| No `any` | ❌ Never use `any` type |
| No `unknown` | ⚠️ Only in type guards |
| `const` by default | Use `const` unless reassignment needed |
| Enums for switches | All switch statements must use enum types |
| Explicit types | All object shapes must have interfaces |
| `readonly` | Use for immutable properties |
| Return types | Explicit return types on all functions |

### 8.3 Examples

```php
<?php
declare(strict_types=1);

function calculateTotal(float $price, int $quantity): float {
    return $price * $quantity;
}
```

```typescript
// ❌ INCORRECT - Using 'any'
function processData(data: any): any {
    return data.value;
}

// ❌ INCORRECT - String literals in switch
function handleAction(action: string) {
    switch (action) {
        case "create": return create();
    }
}

// ✅ CORRECT - Explicit interface, readonly, enum
interface DataPayload {
    readonly value: string;
    readonly timestamp: number;
}

enum TaskAction {
    Create = "create",
    Update = "update",
    Delete = "delete",
}

function processData(data: DataPayload): string {
    return data.value;
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
            const _exhaustive: never = action;
            throw new Error(`Unhandled: ${_exhaustive}`);
    }
}
```

```python
from typing import Optional

def get_user_name(user_id: int) -> Optional[str]:
    user = find_user(user_id)
    return user.name if user else None
```

---

## 9. Anti-Patterns Summary

| Anti-Pattern | Correct Pattern |
|--------------|-----------------|
| Negation operators (`!`, `not`) | Positive helper functions |
| Functions > 15 lines | Split into smaller functions |
| Deep nesting (3+ levels) | Early returns |
| Generic names (`data`, `temp`) | Descriptive names |
| Missing type hints | Always use types |
| Magic numbers | Named constants |
| God classes | Single responsibility |
| Commented-out code | Delete it |
| `any` types | Specific types or `unknown` |

---

## Mandatory Implementation Checklist

Before considering any implementation complete, verify:

- [ ] All functions are under 15 lines of logic
- [ ] No negation operators (`!`, `not`) - only positive helpers
- [ ] All variables use `camelCase`
- [ ] All classes use `PascalCase`
- [ ] All constants use `SCREAMING_SNAKE_CASE`
- [ ] All booleans prefixed with `is`, `has`, `can`, `should`, `was`
- [ ] All functions use early returns (no deep nesting)
- [ ] All types are explicit (no `any` in TypeScript)
- [ ] Imports are organized by category
- [ ] Doc comments on all public methods

---

*This document establishes foundational coding standards. See [02-error-management-foundation.md](./02-error-management-foundation.md) for exception handling patterns.*
