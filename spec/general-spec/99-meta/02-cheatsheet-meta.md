# General-Spec Quick Reference Cheatsheet

> **Version:** 1.0 | **Last Updated:** 2026-01-26  
> **Documents:** 26 specifications (00-26)

---

## 📁 Document Index

| # | Document | Key Topic |
|---|----------|-----------|
| 00 | overview | Master index and navigation |
| 01 | coding-standards | Naming, 15-line rule, boolean helpers |
| 02 | error-management | ERR_xxxx codes, exception hierarchy |
| 03 | logging-system | app.log/error.log, log levels |
| 04 | configuration-hierarchy | JSON → DB → Constants |
| 05 | conditional-helpers | execIf, logIf patterns |
| 06 | testing-standards | AAA pattern, 80% coverage |
| 07 | file-organization | Feature-based directories |
| 08 | api-conventions | REST envelope, HTTP codes |
| 09 | security-patterns | Input validation, auth |
| 10 | caching-patterns | TTL, invalidation |
| 11 | database-conventions | PascalCase, indexes |
| 12 | internationalization | i18n keys, pluralization |
| 13 | accessibility-standards | WCAG 2.1 AA |
| 14 | performance-optimization | Lazy loading, bundle size |
| 15 | documentation-standards | PHPDoc/TSDoc |
| 16 | version-control | Git Flow, Conventional Commits |
| 17 | deployment-cicd | 5-stage pipeline |
| 18 | monitoring-observability | Metrics, alerting |
| 19 | incident-management | Severity levels, SLAs |
| 20 | oncall-runbooks | Escalation procedures |
| 21 | data-classification | Confidential, PII |
| 22 | retention-policies | Data lifecycle |
| 23 | backup-recovery | RPO/RTO targets |
| 24 | graphql-conventions | Relay connections |
| 25 | websocket-patterns | Message format |
| 26 | message-queue-standards | DLQ, Outbox pattern |

---

## 🏷️ Naming Conventions

### Code Naming

| Element | Convention | Example |
|---------|------------|---------|
| Variables | `camelCase` | `userId`, `isActive` |
| Functions | `camelCase` | `getUserById()` |
| Classes | `PascalCase` | `UserService` |
| Interfaces | `PascalCase` + `I` | `IUserService` |
| Constants | `SCREAMING_SNAKE` | `MAX_RETRIES` |
| Enums | `PascalCase` | `ParticipantStatus` |

### Database Naming

| Element | Convention | Example |
|---------|------------|---------|
| Tables | `PascalCase` (singular) | `User`, `ExamParticipant` |
| Columns | `PascalCase` | `UserId`, `CreatedAt` |
| Primary Keys | `Id` | `Id` |
| Foreign Keys | `{Table}Id` | `UserId`, `ExamId` |
| Indexes | `IX_{Table}_{Column}` | `IX_User_Email` |
| Unique | `UQ_{Table}_{Column}` | `UQ_User_Email` |
| Foreign Key | `FK_{Table}_{Ref}` | `FK_Order_User` |

### Boolean Prefixes (MANDATORY)

```
is   → isActive, isDeleted, isValid
has  → hasPermissions, hasAccess
can  → canEdit, canDelete
should → shouldNotify
was  → wasProcessed
```

---

## 📏 Function Rules

### The 15-Line Rule

> ⚠️ **MANDATORY**: No function exceeds 15 lines of logic

**Count**: Logic lines only (exclude blanks, comments, closing braces)

### Early Returns Pattern

```php
// ✅ CORRECT
function processUser(int $userId): Result {
    if (isNull($user)) {
        throw new NotFoundException("User not found");
    }
    
    if (isFalse($user->isActive)) {
        throw new ValidationException("User inactive");
    }
    
    return $this->doSomething($user);
}
```

### Boolean Helpers (No `!` Operator)

```php
// ❌ WRONG
if (!$user) { ... }
if (!in_array($item, $list)) { ... }

// ✅ CORRECT
if (isNull($user)) { ... }
if (isNotInArray($item, $list)) { ... }
```

---

## 🚨 Error Code Registry

### Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| `ERR_1xxx` | Validation | Input validation, format errors |
| `ERR_2xxx` | Auth | Authentication/authorization |
| `ERR_3xxx` | Database | Query failures, connections |
| `ERR_4xxx` | External | Third-party API failures |
| `ERR_5xxx` | Business | Business rule violations |
| `ERR_6xxx` | File | File I/O, upload issues |
| `ERR_7xxx` | Config | Missing/invalid configuration |
| `ERR_9xxx` | System | Fatal, unrecoverable |

### Common Codes

| Code | Name | Use Case |
|------|------|----------|
| `ERR_1001` | VALIDATION_FAILED | General validation |
| `ERR_1002` | RESOURCE_NOT_FOUND | 404 errors |
| `ERR_2001` | AUTHENTICATION_FAILED | Login failures |
| `ERR_2002` | AUTHORIZATION_DENIED | Permission denied |
| `ERR_3001` | DB_CONNECTION_FAILED | Database errors |
| `ERR_9001` | SYSTEM_ERROR | Unhandled errors |

---

## 📨 API Response Envelope

### Standard Format

```json
{
  "success": true,
  "data": { ... },
  "error": null,
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 100
  }
}
```

### Error Format

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERR_1002",
    "message": "User not found",
    "context": { "userId": 123 }
  }
}
```

### HTTP Status Codes

| Status | Use Case |
|--------|----------|
| 200 | Success (GET, PUT, PATCH) |
| 201 | Created (POST) |
| 204 | No Content (DELETE) |
| 400 | Bad Request (validation) |
| 401 | Unauthorized (auth required) |
| 403 | Forbidden (no permission) |
| 404 | Not Found |
| 429 | Rate Limited |
| 500 | Server Error |

---

## 🧪 Testing Standards

### AAA Pattern (Arrange-Act-Assert)

```typescript
describe('UserService', () => {
  it('should create user with valid data', () => {
    // Arrange
    const userData = { email: 'test@example.com' };
    
    // Act
    const result = service.createUser(userData);
    
    // Assert
    expect(result.email).toBe(userData.email);
  });
});
```

### Coverage Targets

| Category | Target |
|----------|--------|
| General | 80% |
| Critical (Auth, Data) | 100% |
| UI Components | 60% |

### Test File Naming

```
Component.test.ts    // Unit tests
Component.spec.ts    // Integration tests
Component.e2e.ts     // End-to-end tests
```

---

## 🗄️ Database Patterns

### Required Columns (Every Table)

```sql
Id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
CreatedAt TIMESTAMPTZ NOT NULL DEFAULT now(),
UpdatedAt TIMESTAMPTZ NOT NULL DEFAULT now()
```

### Soft Delete Pattern

```sql
DeletedAt TIMESTAMPTZ DEFAULT NULL
```

### Audit Columns

```sql
CreatedBy UUID REFERENCES User(Id),
UpdatedBy UUID REFERENCES User(Id)
```

---

## 📝 Logging Pattern

### Dual-File Strategy

| File | Purpose | Content |
|------|---------|---------|
| `app.log` | General events | Info, warnings |
| `error.log` | Error details | Full stack traces |

### Log Levels

| Level | Use Case |
|-------|----------|
| DEBUG | Development only |
| INFO | Normal operations |
| WARNING | Recoverable issues |
| ERROR | Failures with stack trace |
| CRITICAL | System-breaking issues |

---

## ⚙️ Configuration Hierarchy

### Priority Order (Highest → Lowest)

```
1. Database (eqm_settings table) → Runtime overrides
2. JSON Seed (config/defaults.json) → Installation defaults
3. Class Constants (Consts.php) → Code fallbacks
```

### Version/Changelog/Seeding Trigger

```
Update Version → Update CHANGELOG → Trigger Seeder
```

---

## 🔗 Cross-References

| Topic | Document |
|-------|----------|
| Error handling | [02-error-management-foundation.md](../01-foundation/02-error-management-foundation.md) |
| Database design | [03-database-conventions-advanced.md](../04-advanced/03-database-conventions-advanced.md) |
| API design | [03-api-conventions-quality.md](../03-quality/03-api-conventions-quality.md) |
| Testing | [01-testing-standards-quality.md](../03-quality/01-testing-standards-quality.md) |
| Security | [01-security-patterns-advanced.md](../04-advanced/01-security-patterns-advanced.md) |
| Logging | [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) |

---

## ⚠️ Common Pitfalls

| Pitfall | Solution |
|---------|----------|
| Using `!` operator | Use `isNull()`, `isNotEmpty()` |
| Functions > 15 lines | Split into smaller functions |
| Missing error context | Always include `context` array |
| snake_case columns | Use `PascalCase` for DB |
| Swallowing exceptions | Always log or re-throw |
| Missing timestamps | Include `CreatedAt`, `UpdatedAt` |

---

*Generated from 26 specification documents. See individual docs for full details.*
