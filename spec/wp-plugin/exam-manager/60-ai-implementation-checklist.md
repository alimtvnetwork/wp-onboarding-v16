# AI Implementation Checklist

> **Purpose:** Prevent common implementation mistakes when AI agents build features from these specifications.  
> **Last Updated:** 2026-01-26  
> **Version:** 1.8.1

---

## 🚨 CRITICAL: Database Column Naming

> **Database columns: PascalCase** (e.g., `UserId`, `CreatedAt`, `IsEnabled`)  
> **ORM properties: camelCase** (e.g., `userId`, `createdAt`, `isEnabled`)

```php
// ❌ WRONG - camelCase in SQL
$pdo->exec("CREATE TABLE participant (userId INTEGER, createdAt DATETIME)");

// ✅ CORRECT - PascalCase in SQL
$pdo->exec("CREATE TABLE Participant (UserId INTEGER, CreatedAt DATETIME)");

// ORM entity maps PascalCase columns to camelCase properties
class Participant extends Entity {
    public int $userId;      // Maps to UserId column
    public DateTime $createdAt; // Maps to CreatedAt column
}
```

---

## ⚠️ Pre-Flight Checklist

Before implementing ANY feature, verify:

### 1. Deadline Calculations

```php
// ❌ WRONG: Adding extension to current hard deadline
$newDeadline = $participant->HardDeadlineDate->modify("+{$days} days");

// ✅ CORRECT: Adding extension to ORIGINAL hard deadline
$newDeadline = $participant->OriginalHardDeadline->modify("+{$days} days");
```

**Why:** Extensions always calculate from the original deadline, not the current one. Multiple extensions stack from the same base.

### 2. Progress Calculation

```php
// ❌ WRONG: Round rounding
$progress = round($completed / $total * 100);

// ✅ CORRECT: Floor rounding
$progress = (int) floor($completed / $total * 100);
```

**Why:** Floor ensures 100% only when truly complete. Round could show 100% at 99.5%.

### 3. Cookie Naming

```php
// ❌ WRONG: Global cookies
setcookie('eqm_session', $value);
setcookie('eqm_anon', $trackingId);

// ✅ CORRECT: Exam-scoped cookies
setcookie("eqm_session_{$examSlug}", $value);
setcookie("eqm_anon_{$examSlug}", $trackingId);
```

**Why:** Participants can be enrolled in multiple exams simultaneously. Cookies must be exam-scoped.

### 4. Migration Safety

```php
// ❌ WRONG: Assume migration doesn't exist
Schema::addColumn('Participant', 'NewColumn', 'VARCHAR(100)');

// ✅ CORRECT: Check first
if (!Schema::hasColumn('Participant', 'NewColumn')) {
    Schema::addColumn('Participant', 'NewColumn', 'VARCHAR(100)');
}
```

**Why:** Migrations may run multiple times. Always check existence first.

---

## 📋 Feature-Specific Checklists

### Authentication & Signup

- [ ] Password hashed with `password_hash()` (bcrypt)
- [ ] Email validated format AND uniqueness per exam
- [ ] Phone required for invite-only exams
- [ ] Secret key validated against hash (not plaintext)
- [ ] Session created with exam-scoped cookie
- [ ] Rate limiting applied to login/signup endpoints

### Extension Requests

- [ ] Reason length: 50-1000 characters
- [ ] File size: max 5MB
- [ ] File types: PDF, DOC, DOCX, PNG, JPG only
- [ ] Days requested: 1-30
- [ ] Max requests per participant: configurable (default 3)
- [ ] New deadline = `OriginalHardDeadline + grantedDays`
- [ ] Email sent on approval/denial

### Progress Tracking

- [ ] Use `ParticipantChecklist` table (not deprecated `Progress`)
- [ ] Only count REQUIRED items in percentage
- [ ] Floor rounding for percentage
- [ ] Recalculate on every item change
- [ ] Check milestones on progress update

### Email Queue

- [ ] Emails queued, not sent synchronously
- [ ] Template variables substituted
- [ ] Retry with exponential backoff
- [ ] Max 3 attempts before marking failed
- [ ] Priority respected in processing order

### API Responses

- [ ] Standard envelope: `{ success, data, error, meta }`
- [ ] Never leak sensitive info in errors
- [ ] Rate limit headers on all responses
- [ ] 400 for validation, 401 for auth, 403 for permission, 404 for not found
- [ ] Include `Retry-After` on 429

---

## 🔧 Configuration Loading

### Three-Tier Hierarchy

```
Priority: Database > File > Code Constants
```

```php
// ❌ WRONG: Hardcoding values
$maxExtensionDays = 30;

// ❌ WRONG: Reading from JSON at runtime
$config = json_decode(file_get_contents('config.json'));
$maxExtensionDays = $config->maxExtensionDays;

// ✅ CORRECT: Using Settings service
$maxExtensionDays = Settings::get('extension.maxDays', 30);
```

### Settings Priority
1. **Database** (`Settings` table) - Highest priority, admin-editable
2. **Config File** (`config/settings.json`) - Seeded on install
3. **Code Constants** (`Consts.php`) - Fallback defaults

### Common Mistakes
- ❌ Hardcoding values that should be configurable
- ❌ Reading from JSON at runtime instead of DB
- ❌ Not seeding from JSON on plugin activation
- ❌ Hardcoding values instead of using Settings.get()

---

## ⚠️ Common Implementation Mistakes

### Database

| Mistake | Correct Approach |
|---------|------------------|
| Raw SQL queries | Use ORM Repository methods |
| `status = 'active'` | `status = ParticipantStatus::ACTIVE->value` |
| `snake_case` or `camelCase` columns | **PascalCase** columns (e.g., `UserId`, `CreatedAt`) |
| Missing audit log | Call `AuditLog::record()` on CRUD |

### Cookies

| Mistake | Correct Approach |
|---------|------------------|
| `eqm_session` | `eqm_session_{examSlug}` (exam-scoped) |
| `eqm_anon` | `eqm_anon_{examSlug}` (exam-scoped) |
| Storing user ID in cookie | Store hashed tracking ID |

### API Responses

| Mistake | Correct Approach |
|---------|------------------|
| `{ error: "Invalid key" }` | `{ error: "Not found", code: 404 }` (no info leak) |
| `500` for validation errors | `400` with field-specific errors |
| Missing rate limit headers | Include `Retry-After` on 429 |

### Status Transitions

| Mistake | Correct Approach |
|---------|------------------|
| Direct status update | Use `StatusManager::transition()` |
| Skip validation | Validate transition is allowed |
| No email on status change | Trigger notification per status config |

---

## 🔍 Verification Steps

After implementing a feature:

### 1. Database Verification
```sql
-- Check table exists
SELECT name FROM sqlite_master WHERE type='table' AND name='YourTable';

-- Check column names are PascalCase
PRAGMA table_info(Participant);
-- Should show: UserId, CreatedAt, IsEnabled, etc.
```

### 2. API Verification
```bash
# Test rate limiting
for i in {1..10}; do curl -X POST /api/auth/login; done

# Verify response envelope
curl /api/exams | jq '.success, .data, .meta'
```

### 3. Cookie Verification
```javascript
// In browser console
document.cookie.split(';').filter(c => c.includes('eqm_'))
// Should show exam-scoped cookies
```

---

## 📚 Quick Reference

### Required Imports
```php
use ExamQuestionsManager\Enums\ParticipantStatus;
use ExamQuestionsManager\Enums\ExtensionStatus;
use ExamQuestionsManager\Services\Settings;
use ExamQuestionsManager\Services\AuditLog;
use ExamQuestionsManager\Helpers\BooleanHelpers;
```

### Boolean Helpers (No Negation Operator)
```php
// ❌ WRONG
if (!$value) { ... }
if (!function_exists('func')) { ... }

// ✅ CORRECT
if (BooleanHelpers::isFalsy($value)) { ... }
if (BooleanHelpers::isNotFunctionExists('func')) { ... }
```

### Enum Usage
```php
// ❌ WRONG
$status = 'ACTIVE';
$phase = 'PRE';

// ✅ CORRECT
$status = ParticipantStatus::ACTIVE;
$phase = ChecklistPhase::PRE;
```

---

## 🎯 Final Verification Checklist

Before marking a feature complete:

- [ ] All database columns use PascalCase
- [ ] All ORM properties use camelCase
- [ ] No raw SQL queries (ORM only)
- [ ] No magic strings (enums/constants)
- [ ] No negation operator (use BooleanHelpers)
- [ ] Deadlines calculated from ORIGINAL values
- [ ] Progress uses floor rounding
- [ ] Cookies are exam-scoped
- [ ] API responses use standard envelope
- [ ] Rate limiting applied where needed
- [ ] Audit logging for all CRUD operations
- [ ] Error handling with proper codes
- [ ] Settings loaded from DB, not hardcoded

---

*Last Updated: 2026-01-26*
