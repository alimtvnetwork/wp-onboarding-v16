# Testing Standards

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines testing patterns, structure, and coverage requirements for all codebases. Tests are first-class citizens—every feature requires corresponding test coverage.

---

## 1. Test Categories

### 1.1 Test Pyramid

```
        ╱╲
       ╱  ╲        E2E Tests (10%)
      ╱────╲       - Critical user journeys
     ╱      ╲      - Cross-service flows
    ╱────────╲     
   ╱          ╲    Integration Tests (30%)
  ╱────────────╲   - API endpoints
 ╱              ╲  - Database operations
╱────────────────╲ 
        ▓▓▓▓▓▓▓▓   Unit Tests (60%)
                   - Pure functions
                   - Business logic
```

### 1.2 Category Definitions

| Category | Scope | Speed | Dependencies |
|----------|-------|-------|--------------|
| Unit | Single function/class | <10ms | None (mocked) |
| Integration | Multiple components | <500ms | Real DB/services |
| E2E | Full user journey | <30s | Full stack |

---

## 2. Test File Organization

### 2.1 Directory Structure

```
project/
├── src/
│   ├── services/
│   │   └── UserService.php
│   └── utils/
│       └── Validator.php
├── tests/
│   ├── Unit/
│   │   ├── Services/
│   │   │   └── UserServiceTest.php
│   │   └── Utils/
│   │       └── ValidatorTest.php
│   ├── Integration/
│   │   └── Api/
│   │       └── UserApiTest.php
│   ├── E2E/
│   │   └── UserJourneyTest.php
│   ├── Fixtures/
│   │   ├── users.json
│   │   └── Factory.php
│   └── bootstrap.php
```

### 2.2 Naming Conventions

| Element | Pattern | Example |
|---------|---------|---------|
| Test file | `{ClassName}Test.{ext}` | `UserServiceTest.php` |
| Test method | `test_{action}_{scenario}_{expected}` | `test_create_withValidData_returnsUser` |
| Fixture file | `{entity}.json` or `{entity}.fixture.ts` | `users.json` |
| Mock class | `Mock{ClassName}` | `MockEmailService` |

---

## 3. Test Structure Pattern

### 3.1 Arrange-Act-Assert (AAA)

Every test follows the AAA pattern with clear visual separation:

#### PHP (PHPUnit)

```php
class UserServiceTest extends TestCase
{
    private UserService $service;
    private MockEmailService $emailMock;
    
    protected function setUp(): void
    {
        $this->emailMock = new MockEmailService();
        $this->service = new UserService($this->emailMock);
    }
    
    public function test_create_withValidData_returnsUser(): void
    {
        // Arrange
        $data = [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];
        
        // Act
        $result = $this->service->create($data);
        
        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('test@example.com', $result->email);
        $this->assertTrue($this->emailMock->wasCalledWith('welcome'));
    }
    
    public function test_create_withDuplicateEmail_throwsException(): void
    {
        // Arrange
        $existingUser = UserFactory::create(['email' => 'exists@example.com']);
        $data = ['email' => 'exists@example.com', 'name' => 'Duplicate'];
        
        // Assert (expectation before act for exceptions)
        $this->expectException(DuplicateEmailException::class);
        $this->expectExceptionCode(ERR_1002);
        
        // Act
        $this->service->create($data);
    }
}
```

#### TypeScript (Vitest/Jest)

```typescript
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { UserService } from '@/services/UserService';
import { MockEmailService } from '@/tests/mocks/MockEmailService';

describe('UserService', () => {
  let service: UserService;
  let emailMock: MockEmailService;
  
  beforeEach(() => {
    emailMock = new MockEmailService();
    service = new UserService(emailMock);
  });
  
  describe('create', () => {
    it('returns user with valid data', async () => {
      // Arrange
      const data = {
        email: 'test@example.com',
        name: 'Test User',
      };
      
      // Act
      const result = await service.create(data);
      
      // Assert
      expect(result).toBeInstanceOf(User);
      expect(result.email).toBe('test@example.com');
      expect(emailMock.wasCalledWith('welcome')).toBe(true);
    });
    
    it('throws on duplicate email', async () => {
      // Arrange
      await UserFactory.create({ email: 'exists@example.com' });
      const data = { email: 'exists@example.com', name: 'Duplicate' };
      
      // Act & Assert
      await expect(service.create(data)).rejects.toThrow(DuplicateEmailException);
    });
  });
});
```

#### Python (pytest)

```python
import pytest
from services.user_service import UserService
from tests.mocks.mock_email_service import MockEmailService
from tests.factories.user_factory import UserFactory

class TestUserService:
    @pytest.fixture(autouse=True)
    def setup(self):
        self.email_mock = MockEmailService()
        self.service = UserService(self.email_mock)
    
    def test_create_with_valid_data_returns_user(self):
        # Arrange
        data = {
            'email': 'test@example.com',
            'name': 'Test User',
        }
        
        # Act
        result = self.service.create(data)
        
        # Assert
        assert isinstance(result, User)
        assert result.email == 'test@example.com'
        assert self.email_mock.was_called_with('welcome')
    
    def test_create_with_duplicate_email_raises_exception(self):
        # Arrange
        UserFactory.create(email='exists@example.com')
        data = {'email': 'exists@example.com', 'name': 'Duplicate'}
        
        # Act & Assert
        with pytest.raises(DuplicateEmailException) as exc_info:
            self.service.create(data)
        
        assert exc_info.value.code == ERR_1002
```

---

## 4. Test Fixtures

### 4.1 Fixture Patterns

#### Static Fixtures (JSON)

```json
// tests/Fixtures/users.json
{
  "valid_user": {
    "id": 1,
    "email": "user@example.com",
    "name": "Test User",
    "role": "member",
    "created_at": "2025-01-01T00:00:00Z"
  },
  "admin_user": {
    "id": 2,
    "email": "admin@example.com",
    "name": "Admin User",
    "role": "admin",
    "created_at": "2025-01-01T00:00:00Z"
  }
}
```

#### Factory Pattern

```php
// PHP Factory
class UserFactory
{
    private static array $defaults = [
        'email' => 'test@example.com',
        'name' => 'Test User',
        'role' => 'member',
        'status' => 'active',
    ];
    
    public static function make(array $overrides = []): array
    {
        return array_merge(self::$defaults, $overrides);
    }
    
    public static function create(array $overrides = []): User
    {
        $data = self::make($overrides);
        return User::create($data);
    }
    
    public static function createMany(int $count, array $overrides = []): array
    {
        return array_map(
            fn($i) => self::create(array_merge($overrides, [
                'email' => "user{$i}@example.com"
            ])),
            range(1, $count)
        );
    }
}
```

```typescript
// TypeScript Factory
export class UserFactory {
  private static defaults: Partial<User> = {
    email: 'test@example.com',
    name: 'Test User',
    role: 'member',
    status: 'active',
  };
  
  static make(overrides: Partial<User> = {}): Partial<User> {
    return { ...this.defaults, ...overrides };
  }
  
  static async create(overrides: Partial<User> = {}): Promise<User> {
    const data = this.make(overrides);
    return await User.create(data);
  }
  
  static async createMany(count: number, overrides: Partial<User> = {}): Promise<User[]> {
    return Promise.all(
      Array.from({ length: count }, (_, i) =>
        this.create({ ...overrides, email: `user${i + 1}@example.com` })
      )
    );
  }
}
```

### 4.2 Database Fixtures

```php
// Transaction rollback pattern for database tests
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }
    
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }
}
```

---

## 5. Mocking Standards

### 5.1 Mock Implementation

```php
// PHP Mock
class MockEmailService implements EmailServiceInterface
{
    private array $calls = [];
    private bool $shouldFail = false;
    
    public function send(string $template, string $to, array $data): bool
    {
        $this->calls[] = compact('template', 'to', 'data');
        
        if ($this->shouldFail) {
            throw new EmailDeliveryException('Mock failure');
        }
        
        return true;
    }
    
    public function wasCalledWith(string $template): bool
    {
        return collect($this->calls)->contains('template', $template);
    }
    
    public function getCallCount(): int
    {
        return count($this->calls);
    }
    
    public function simulateFailure(): self
    {
        $this->shouldFail = true;
        return $this;
    }
    
    public function reset(): void
    {
        $this->calls = [];
        $this->shouldFail = false;
    }
}
```

```typescript
// TypeScript Mock with vi.fn()
const createMockEmailService = () => ({
  send: vi.fn().mockResolvedValue(true),
  wasCalledWith: function(template: string) {
    return this.send.mock.calls.some(([t]) => t === template);
  },
  simulateFailure: function() {
    this.send.mockRejectedValue(new EmailDeliveryException('Mock failure'));
    return this;
  },
});
```

### 5.2 Spy Pattern

```typescript
// Spying on real implementations
describe('UserService with real email service', () => {
  it('sends welcome email on creation', async () => {
    const emailService = new RealEmailService();
    const sendSpy = vi.spyOn(emailService, 'send');
    
    const service = new UserService(emailService);
    await service.create({ email: 'new@example.com', name: 'New User' });
    
    expect(sendSpy).toHaveBeenCalledWith(
      'welcome',
      'new@example.com',
      expect.objectContaining({ name: 'New User' })
    );
  });
});
```

---

## 6. Coverage Requirements

### 6.1 Minimum Thresholds

| Metric | Minimum | Target |
|--------|---------|--------|
| Line Coverage | 80% | 90% |
| Branch Coverage | 75% | 85% |
| Function Coverage | 85% | 95% |
| Critical Paths | 100% | 100% |

### 6.2 Critical Path Definition

These MUST have 100% coverage:

1. **Authentication flows** - Login, logout, token refresh
2. **Payment processing** - Charges, refunds, webhooks
3. **Data mutations** - Create, update, delete operations
4. **Security checks** - Permission validation, input sanitization

### 6.3 Coverage Configuration

```javascript
// vitest.config.ts
export default defineConfig({
  test: {
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      exclude: [
        'node_modules/',
        'tests/',
        '**/*.d.ts',
        '**/*.config.*',
      ],
      thresholds: {
        lines: 80,
        branches: 75,
        functions: 85,
        statements: 80,
      },
    },
  },
});
```

---

## 7. Test Data Isolation

### 7.1 Unique Identifiers

```php
// Generate unique test data to prevent collisions
class TestHelper
{
    public static function uniqueEmail(): string
    {
        return sprintf('test_%s_%d@example.com', uniqid(), time());
    }
    
    public static function uniqueSlug(string $prefix = 'test'): string
    {
        return sprintf('%s-%s', $prefix, Str::random(8));
    }
}
```

### 7.2 Test Database Seeding

```php
// Seed only what's needed for each test context
trait SeedsTestData
{
    protected function seedBasicData(): void
    {
        // Minimal data for most tests
        UserFactory::create(['role' => 'admin']);
    }
    
    protected function seedFullData(): void
    {
        // Complete dataset for integration tests
        $this->seedBasicData();
        ExamFactory::createMany(5);
        ParticipantFactory::createMany(20);
    }
}
```

---

## 8. Async Testing

### 8.1 Promise/Async Patterns

```typescript
describe('async operations', () => {
  it('handles successful async operation', async () => {
    const result = await service.asyncOperation();
    expect(result).toBeDefined();
  });
  
  it('handles async rejection', async () => {
    await expect(service.failingOperation()).rejects.toThrow('Expected error');
  });
  
  it('waits for all promises', async () => {
    const results = await Promise.all([
      service.operation1(),
      service.operation2(),
      service.operation3(),
    ]);
    
    expect(results).toHaveLength(3);
    results.forEach(r => expect(r.success).toBe(true));
  });
});
```

### 8.2 Timeout Handling

```typescript
it('completes within timeout', async () => {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 5000);
  
  try {
    const result = await service.longOperation({ signal: controller.signal });
    expect(result).toBeDefined();
  } finally {
    clearTimeout(timeout);
  }
}, 10000); // Jest/Vitest timeout
```

---

## 9. Component Testing (Frontend)

### 9.1 React Component Tests

```typescript
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LoginForm } from '@/components/LoginForm';

describe('LoginForm', () => {
  it('renders email and password inputs', () => {
    render(<LoginForm onSubmit={vi.fn()} />);
    
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
  });
  
  it('shows validation errors for empty submission', async () => {
    const user = userEvent.setup();
    render(<LoginForm onSubmit={vi.fn()} />);
    
    await user.click(screen.getByRole('button', { name: /sign in/i }));
    
    expect(await screen.findByText(/email is required/i)).toBeInTheDocument();
    expect(await screen.findByText(/password is required/i)).toBeInTheDocument();
  });
  
  it('calls onSubmit with form data', async () => {
    const user = userEvent.setup();
    const handleSubmit = vi.fn();
    render(<LoginForm onSubmit={handleSubmit} />);
    
    await user.type(screen.getByLabelText(/email/i), 'test@example.com');
    await user.type(screen.getByLabelText(/password/i), 'password123');
    await user.click(screen.getByRole('button', { name: /sign in/i }));
    
    await waitFor(() => {
      expect(handleSubmit).toHaveBeenCalledWith({
        email: 'test@example.com',
        password: 'password123',
      });
    });
  });
});
```

### 9.2 Hook Testing

```typescript
import { renderHook, act, waitFor } from '@testing-library/react';
import { useAuth } from '@/hooks/useAuth';

describe('useAuth', () => {
  it('provides initial unauthenticated state', () => {
    const { result } = renderHook(() => useAuth());
    
    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.user).toBeNull();
  });
  
  it('updates state after login', async () => {
    const { result } = renderHook(() => useAuth());
    
    await act(async () => {
      await result.current.login('test@example.com', 'password');
    });
    
    await waitFor(() => {
      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.user?.email).toBe('test@example.com');
    });
  });
});
```

---

## 10. E2E Testing

### 10.1 Playwright Pattern

```typescript
import { test, expect } from '@playwright/test';

test.describe('User Registration Flow', () => {
  test('completes full registration journey', async ({ page }) => {
    // Navigate to registration
    await page.goto('/register');
    
    // Fill form
    await page.getByLabel('Email').fill('newuser@example.com');
    await page.getByLabel('Password').fill('SecurePass123!');
    await page.getByLabel('Confirm Password').fill('SecurePass123!');
    
    // Submit
    await page.getByRole('button', { name: 'Create Account' }).click();
    
    // Verify redirect to dashboard
    await expect(page).toHaveURL('/dashboard');
    await expect(page.getByText('Welcome, newuser@example.com')).toBeVisible();
  });
  
  test('shows error for existing email', async ({ page }) => {
    await page.goto('/register');
    
    await page.getByLabel('Email').fill('existing@example.com');
    await page.getByLabel('Password').fill('SecurePass123!');
    await page.getByLabel('Confirm Password').fill('SecurePass123!');
    await page.getByRole('button', { name: 'Create Account' }).click();
    
    await expect(page.getByText('Email already registered')).toBeVisible();
  });
});
```

---

## 11. Anti-Patterns

### ❌ DON'T

```php
// Testing implementation details
public function test_user_uses_bcrypt(): void
{
    $user = UserFactory::create(['password' => 'test']);
    $this->assertTrue(password_verify('test', $user->password)); // Implementation detail!
}

// Multiple assertions testing different behaviors
public function test_user_creation(): void
{
    $user = UserFactory::create();
    $this->assertNotNull($user->id);
    $this->assertTrue($user->isActive()); // Different behavior
    $this->assertEquals(0, $user->loginCount()); // Yet another behavior
}

// Tests depending on execution order
private static $sharedUser;

public function test_01_create_user(): void
{
    self::$sharedUser = UserFactory::create();
}

public function test_02_update_user(): void
{
    self::$sharedUser->update(['name' => 'Updated']); // Depends on test_01!
}
```

### ✅ DO

```php
// Test observable behavior
public function test_password_verification_succeeds(): void
{
    $user = UserFactory::create(['password' => 'test']);
    $this->assertTrue($user->verifyPassword('test')); // Public API
}

// One behavior per test
public function test_created_user_has_id(): void
{
    $user = UserFactory::create();
    $this->assertNotNull($user->id);
}

public function test_created_user_is_active(): void
{
    $user = UserFactory::create();
    $this->assertTrue($user->isActive());
}

// Independent tests
public function test_update_user(): void
{
    $user = UserFactory::create(); // Creates its own user
    $user->update(['name' => 'Updated']);
    $this->assertEquals('Updated', $user->name);
}
```

---

## Quick Reference

| Aspect | Standard |
|--------|----------|
| Test naming | `test_{action}_{scenario}_{expected}` |
| Structure | Arrange-Act-Assert with blank line separators |
| Mocking | Interface-based with call tracking |
| Coverage | 80% line, 75% branch, 100% critical paths |
| Fixtures | Factory pattern with unique generators |
| Database | Transaction rollback or truncate between tests |
| Async | Always await, explicit timeout handling |
