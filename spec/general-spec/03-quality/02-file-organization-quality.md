# File Organization Standards

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines directory structures, module patterns, and file organization conventions across all codebases. Consistent organization enables faster navigation and reduces cognitive overhead.

---

## 1. Core Directory Structure

### 1.1 Backend Project Layout

```
project-root/
├── src/                          # Source code
│   ├── Core/                     # Framework/bootstrap code
│   │   ├── Bootstrap.php
│   │   ├── Container.php
│   │   └── Kernel.php
│   ├── Config/                   # Configuration files
│   │   ├── app.php
│   │   ├── database.php
│   │   └── services.php
│   ├── Constants/                # Enums and constants
│   │   ├── Enums/
│   │   │   ├── UserRole.php
│   │   │   └── Status.php
│   │   └── Consts.php
│   ├── Entities/                 # Domain models/entities
│   │   ├── User.php
│   │   ├── Exam.php
│   │   └── Participant.php
│   ├── Services/                 # Business logic
│   │   ├── UserService.php
│   │   ├── ExamService.php
│   │   └── NotificationService.php
│   ├── Repositories/             # Data access layer
│   │   ├── UserRepository.php
│   │   └── ExamRepository.php
│   ├── Http/                     # HTTP layer
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Events/                   # Event classes
│   │   └── UserCreated.php
│   ├── Listeners/                # Event handlers
│   │   └── SendWelcomeEmail.php
│   ├── Jobs/                     # Background jobs
│   │   └── ProcessExamResults.php
│   ├── Exceptions/               # Custom exceptions
│   │   ├── BaseException.php
│   │   └── ValidationException.php
│   ├── Helpers/                  # Utility functions
│   │   ├── BooleanHelpers.php
│   │   ├── ConditionalHelpers.php
│   │   └── StringHelpers.php
│   └── Traits/                   # Reusable traits
│       ├── HasTimestamps.php
│       └── Loggable.php
├── tests/                        # Test files (see Testing Standards)
├── config/                       # External config files
│   ├── defaults.json
│   └── environment.php
├── storage/                      # Generated files
│   ├── logs/
│   ├── cache/
│   └── uploads/
├── public/                       # Web-accessible files
│   └── index.php
└── vendor/                       # Dependencies (gitignored)
```

### 1.2 Frontend Project Layout

```
project-root/
├── src/
│   ├── assets/                   # Static assets
│   │   ├── images/
│   │   ├── fonts/
│   │   └── icons/
│   ├── components/               # React components
│   │   ├── ui/                   # Base UI components
│   │   │   ├── Button.tsx
│   │   │   ├── Input.tsx
│   │   │   └── Modal.tsx
│   │   ├── forms/                # Form components
│   │   │   ├── LoginForm.tsx
│   │   │   └── RegisterForm.tsx
│   │   ├── layout/               # Layout components
│   │   │   ├── Header.tsx
│   │   │   ├── Footer.tsx
│   │   │   └── Sidebar.tsx
│   │   └── features/             # Feature-specific components
│   │       ├── exam/
│   │       │   ├── ExamCard.tsx
│   │       │   ├── ExamList.tsx
│   │       │   └── ExamProgress.tsx
│   │       └── user/
│   │           ├── UserProfile.tsx
│   │           └── UserSettings.tsx
│   ├── pages/                    # Page/route components
│   │   ├── Home.tsx
│   │   ├── Dashboard.tsx
│   │   └── NotFound.tsx
│   ├── hooks/                    # Custom React hooks
│   │   ├── useAuth.ts
│   │   ├── useLocalStorage.ts
│   │   └── useDebounce.ts
│   ├── services/                 # API service layer
│   │   ├── api.ts
│   │   ├── authService.ts
│   │   └── examService.ts
│   ├── store/                    # State management
│   │   ├── index.ts
│   │   ├── authSlice.ts
│   │   └── examSlice.ts
│   ├── types/                    # TypeScript types
│   │   ├── user.ts
│   │   ├── exam.ts
│   │   └── api.ts
│   ├── utils/                    # Utility functions
│   │   ├── formatters.ts
│   │   ├── validators.ts
│   │   └── helpers.ts
│   ├── constants/                # Constants and enums
│   │   ├── routes.ts
│   │   └── config.ts
│   ├── styles/                   # Global styles
│   │   ├── globals.css
│   │   └── variables.css
│   ├── lib/                      # Third-party integrations
│   │   └── supabase.ts
│   ├── App.tsx
│   ├── main.tsx
│   └── index.css
├── public/                       # Static public files
├── tests/                        # Test files
└── package.json
```

---

## 2. Module Organization

### 2.1 Feature-Based Modules

For larger applications, organize by feature/domain:

```
src/
├── modules/
│   ├── auth/
│   │   ├── components/
│   │   │   ├── LoginForm.tsx
│   │   │   └── RegisterForm.tsx
│   │   ├── hooks/
│   │   │   └── useAuth.ts
│   │   ├── services/
│   │   │   └── authService.ts
│   │   ├── types/
│   │   │   └── auth.types.ts
│   │   └── index.ts              # Public exports
│   ├── exam/
│   │   ├── components/
│   │   ├── hooks/
│   │   ├── services/
│   │   ├── types/
│   │   └── index.ts
│   └── user/
│       ├── components/
│       ├── hooks/
│       ├── services/
│       ├── types/
│       └── index.ts
└── shared/                       # Cross-module shared code
    ├── components/
    ├── hooks/
    └── utils/
```

### 2.2 Module Index Pattern

Each module exports its public interface:

```typescript
// src/modules/auth/index.ts
// Components
export { LoginForm } from './components/LoginForm';
export { RegisterForm } from './components/RegisterForm';

// Hooks
export { useAuth } from './hooks/useAuth';
export { useSession } from './hooks/useSession';

// Services
export { authService } from './services/authService';

// Types
export type { User, AuthState, LoginCredentials } from './types/auth.types';
```

---

## 3. File Naming Conventions

### 3.1 Naming Patterns by Type

| File Type | Pattern | Example |
|-----------|---------|---------|
| React Component | `PascalCase.tsx` | `UserProfile.tsx` |
| Hook | `use{Name}.ts` | `useLocalStorage.ts` |
| Utility | `camelCase.ts` | `formatDate.ts` |
| Service | `{name}Service.ts` | `authService.ts` |
| Type definitions | `{name}.types.ts` | `user.types.ts` |
| Constants | `{name}.constants.ts` | `routes.constants.ts` |
| Test file | `{Name}.test.tsx` | `UserProfile.test.tsx` |
| Styles (CSS Module) | `{Name}.module.css` | `UserProfile.module.css` |
| PHP Class | `PascalCase.php` | `UserService.php` |
| PHP Interface | `{Name}Interface.php` | `EmailServiceInterface.php` |
| Python Module | `snake_case.py` | `user_service.py` |

### 3.2 Index Files

Use `index.ts` for barrel exports, never for component logic:

```typescript
// ✅ CORRECT - index.ts for exports only
// src/components/ui/index.ts
export { Button } from './Button';
export { Input } from './Input';
export { Modal } from './Modal';

// ❌ WRONG - logic in index.ts
// src/components/ui/index.ts
export const Button = () => { ... }; // Don't define here!
```

---

## 4. Component File Structure

### 4.1 Single Component per File

```typescript
// src/components/ui/Button.tsx
import { forwardRef, type ButtonHTMLAttributes } from 'react';
import { cn } from '@/utils/cn';

// Types at top
export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'ghost';
  size?: 'sm' | 'md' | 'lg';
  isLoading?: boolean;
}

// Component
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = 'primary', size = 'md', isLoading, className, children, ...props }, ref) => {
    return (
      <button
        ref={ref}
        className={cn(
          'inline-flex items-center justify-center rounded-md font-medium',
          variantStyles[variant],
          sizeStyles[size],
          isLoading && 'opacity-50 cursor-not-allowed',
          className
        )}
        disabled={isLoading}
        {...props}
      >
        {isLoading ? <Spinner /> : children}
      </button>
    );
  }
);

Button.displayName = 'Button';

// Private helpers/constants at bottom
const variantStyles = {
  primary: 'bg-primary text-primary-foreground hover:bg-primary/90',
  secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
  ghost: 'hover:bg-accent hover:text-accent-foreground',
};

const sizeStyles = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-10 px-4',
  lg: 'h-12 px-6 text-lg',
};
```

### 4.2 Co-located Files

Keep related files together:

```
components/
└── ExamCard/
    ├── ExamCard.tsx           # Main component
    ├── ExamCard.test.tsx      # Tests
    ├── ExamCard.module.css    # Styles (if not using Tailwind)
    ├── ExamCardSkeleton.tsx   # Loading state
    └── index.ts               # Export
```

---

## 5. Import Organization

### 5.1 Import Order

Imports should be grouped and ordered:

```typescript
// 1. External packages (React first)
import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';

// 2. Internal aliases (@/)
import { Button, Input } from '@/components/ui';
import { useAuth } from '@/hooks/useAuth';
import { examService } from '@/services/examService';

// 3. Relative imports (parent first, then siblings, then children)
import { ExamContext } from '../ExamContext';
import { ExamHeader } from './ExamHeader';

// 4. Types (always last, with 'type' keyword)
import type { Exam, ExamStatus } from '@/types/exam';
```

### 5.2 Path Aliases

Configure path aliases for clean imports:

```json
// tsconfig.json
{
  "compilerOptions": {
    "baseUrl": ".",
    "paths": {
      "@/*": ["src/*"],
      "@components/*": ["src/components/*"],
      "@hooks/*": ["src/hooks/*"],
      "@utils/*": ["src/utils/*"]
    }
  }
}
```

---

## 6. Backend Service Organization

### 6.1 PHP Service Class Structure

```php
<?php
// src/Services/ExamService.php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Exam;
use App\Repositories\ExamRepository;
use App\Events\ExamCreated;
use App\Exceptions\ExamNotFoundException;
use App\Helpers\ConditionalHelpers;

final class ExamService
{
    // Dependencies via constructor
    public function __construct(
        private readonly ExamRepository $repository,
        private readonly EventDispatcher $events,
        private readonly Logger $logger,
    ) {}
    
    // Public methods first
    public function create(array $data): Exam
    {
        $exam = $this->repository->create($data);
        
        $this->events->dispatch(new ExamCreated($exam));
        $this->logger->info('Exam created', ['exam_id' => $exam->id]);
        
        return $exam;
    }
    
    public function findById(int $id): Exam
    {
        $exam = $this->repository->find($id);
        
        return ConditionalHelpers::throwIf(
            isNull($exam),
            new ExamNotFoundException($id)
        ) ?? $exam;
    }
    
    // Private helpers at bottom
    private function validateExamData(array $data): array
    {
        // validation logic
    }
}
```

### 6.2 Repository Pattern

```php
<?php
// src/Repositories/ExamRepository.php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Exam;

final class ExamRepository
{
    public function __construct(
        private readonly Database $db
    ) {}
    
    public function find(int $id): ?Exam
    {
        return $this->db->table('exams')
            ->where('id', $id)
            ->first();
    }
    
    public function findBySlug(string $slug): ?Exam
    {
        return $this->db->table('exams')
            ->where('slug', $slug)
            ->first();
    }
    
    public function create(array $data): Exam
    {
        $id = $this->db->table('exams')->insert($data);
        return $this->find($id);
    }
    
    public function update(int $id, array $data): bool
    {
        return $this->db->table('exams')
            ->where('id', $id)
            ->update($data) > 0;
    }
    
    public function delete(int $id): bool
    {
        return $this->db->table('exams')
            ->where('id', $id)
            ->delete() > 0;
    }
}
```

---

## 7. File Size Guidelines

### 7.1 Maximum Line Counts

| File Type | Soft Limit | Hard Limit | Action Required |
|-----------|------------|------------|-----------------|
| Component | 150 lines | 250 lines | Split into subcomponents |
| Service | 200 lines | 300 lines | Extract to separate services |
| Utility | 100 lines | 150 lines | Group related functions in separate files |
| Types | 150 lines | 200 lines | Split by domain |
| Test | 300 lines | 500 lines | Split by test suite |

### 7.2 When to Split

Split a file when:
- It exceeds line limits above
- It handles multiple unrelated concerns
- Multiple developers frequently edit it (merge conflicts)
- It has more than 3-4 public exports

---

## 8. Configuration Files

### 8.1 Root-Level Config Files

```
project-root/
├── .env                    # Environment variables (gitignored)
├── .env.example            # Environment template (committed)
├── .gitignore
├── .prettierrc             # Code formatting
├── .eslintrc.js            # Linting rules
├── tsconfig.json           # TypeScript config
├── vite.config.ts          # Build tool config
├── vitest.config.ts        # Test config
├── tailwind.config.ts      # Tailwind config
└── package.json
```

### 8.2 Environment Files

```bash
# .env.example - Template with all required vars
DATABASE_URL=
API_URL=
SUPABASE_URL=
SUPABASE_ANON_KEY=

# Never commit actual values
# .env should be in .gitignore
```

---

## 9. Documentation Files

### 9.1 Documentation Structure

```
docs/
├── README.md               # Project overview
├── CONTRIBUTING.md         # Contribution guidelines
├── CHANGELOG.md            # Version history
├── architecture/
│   ├── overview.md
│   └── decisions/          # Architecture Decision Records
│       ├── 001-use-react.md
│       └── 002-api-design.md
├── api/
│   ├── endpoints.md
│   └── authentication.md
└── guides/
    ├── getting-started.md
    └── deployment.md
```

### 9.2 In-Code Documentation

```typescript
/**
 * Calculates the progress percentage for an exam participant.
 * 
 * Uses floor-based calculation where each section must be fully
 * completed to count toward progress.
 * 
 * @param participant - The participant entity
 * @param exam - The exam entity with sections
 * @returns Progress as integer percentage (0-100)
 * 
 * @example
 * const progress = calculateProgress(participant, exam);
 * // Returns: 75 (if 3 of 4 sections complete)
 */
export function calculateProgress(participant: Participant, exam: Exam): number {
  // implementation
}
```

---

## 10. Anti-Patterns

### ❌ DON'T

```
src/
├── components.tsx          # Multiple components in one file
├── utils.ts                # Catch-all utilities file
├── api.ts                  # All API calls in one file
└── types.ts                # All types in one file
```

### ✅ DO

```
src/
├── components/
│   ├── Button.tsx
│   ├── Input.tsx
│   └── Modal.tsx
├── utils/
│   ├── formatters.ts
│   ├── validators.ts
│   └── dates.ts
├── services/
│   ├── authService.ts
│   ├── examService.ts
│   └── userService.ts
└── types/
    ├── auth.types.ts
    ├── exam.types.ts
    └── user.types.ts
```

---

## Quick Reference

| Aspect | Standard |
|--------|----------|
| Components | PascalCase, one per file, co-located tests |
| Hooks | `use{Name}.ts` in `/hooks` |
| Services | `{name}Service.ts` in `/services` |
| Types | `{name}.types.ts` in `/types` or co-located |
| Import order | External → Aliases → Relative → Types |
| Max file size | Components: 250 lines, Services: 300 lines |
| Barrel exports | `index.ts` for exports only, never logic |
| Feature modules | Group by domain with public index |
