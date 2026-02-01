# 22 - Secret Key Service

## Overview

PHP service for managing Secret Keys that enable anonymous access to exams. Handles key generation, validation, usage tracking, and expiration management. Secret Keys allow participants to view exams without requiring user registration.

---

## Dependencies

- `06-entity-models.md` (SecretKey entity)
- `10-exam-service.md` (exam association)
- `24-secret-key-analytics.md` (usage tracking) [OPTIONAL]
- `44-audit-logging.md` (access logging)

---

## Functional Requirements

### 22.1 Secret Key Entity Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | Integer | Auto-increment PK |
| `examId` | Integer | FK to Exam |
| `keyValue` | String(64) | Unique key string |
| `keyHash` | String(64) | SHA-256 for secure lookup |
| `label` | String(255)/null | Admin-friendly name |
| `isActive` | Boolean | Enabled/disabled |
| `usageLimit` | Integer/null | Max uses (null = unlimited) |
| `usageCount` | Integer | Current use count |
| `expiresAt` | DateTime/null | Expiration timestamp |
| `allowedIpPattern` | String(255)/null | IP whitelist pattern |
| `metadata` | JSON/null | Custom tracking data |
| `createdAt` | DateTime | Creation timestamp |
| `createdBy` | Integer | User who created |
| `lastUsedAt` | DateTime/null | Last access timestamp |

### 22.2 Secret Key Service Methods

```
CLASS SecretKeyService:
  
  PROPERTIES:
    - repository: SecretKeyRepository
    - examService: ExamService
    - analyticsService: SecretKeyAnalyticsService (optional)
  
  METHODS:
    # Key Generation
    + generate(examId: int, options: KeyOptions): SecretKey
    + generateBatch(examId: int, count: int, options: KeyOptions): array<SecretKey>
    + regenerate(keyId: int): SecretKey
    
    # Validation & Access
    + validate(keyValue: string): ValidationResult
    + validateForExam(keyValue: string, examSlug: string): ValidationResult
    + recordUsage(keyId: int, request: RequestContext): bool
    + checkRateLimit(keyValue: string, ip: string): bool
    
    # CRUD
    + create(data: SecretKeyCreateDTO): SecretKey
    + update(id: int, data: SecretKeyUpdateDTO): SecretKey
    + deactivate(id: int): bool
    + delete(id: int): bool
    + findByKey(keyValue: string): SecretKey|null
    + findByKeyHash(hash: string): SecretKey|null
    + listByExam(examId: int): array<SecretKey>
    
    # Expiration & Limits
    + extendExpiration(id: int, newDate: DateTime): bool
    + increaseLimit(id: int, additionalUses: int): bool
    + checkExpiration(key: SecretKey): bool
    + checkUsageLimit(key: SecretKey): bool
    
    # Bulk Operations
    + deactivateExpired(): int
    + deactivateExhausted(): int
    + pruneInactive(olderThan: DateTime): int
```

### 22.3 Key Generation Algorithm

```
KEY_GENERATION:
  - Format: [prefix]-[random]-[checksum]
  - Prefix: Configurable (default: "EQM")
  - Random: 32 characters, cryptographically secure
  - Checksum: Last 4 chars for quick validation
  - Example: EQM-a7b3c9d2e1f4g5h6i7j8k9l0m1n2o3p4-x9y2
  
  COLLISION PREVENTION:
  - Check uniqueness before insert
  - Retry up to 3 times on collision
  - Log warning if collision occurs
```

---

## Business Rules

### 22.4 Validation Rules

- [ ] Key must exist in database
- [ ] Key must be active (`isActive: true`)
- [ ] Key must not be expired (`expiresAt > now` or null)
- [ ] Usage count must be under limit (`usageCount < usageLimit` or null)
- [ ] IP must match pattern if specified
- [ ] Exam must be in accessible status

### 22.5 Usage Tracking

- [ ] Increment `usageCount` on each valid access
- [ ] Update `lastUsedAt` timestamp
- [ ] Record access details in analytics (if enabled)
- [ ] Rate limit: Max 10 accesses per minute per IP

### 22.6 Security Measures

- [ ] Keys stored hashed, plain value only returned on creation
- [ ] Rate limiting prevents brute force
- [ ] IP logging uses SHA-256 hashing
- [ ] Expired/exhausted keys auto-deactivated daily
- [ ] Audit log for all key operations

### 22.7 URL Structure

```
PUBLIC ACCESS URL:
  /{exam-slug}/{secret-key}
  
EXAMPLES:
  /advanced-mathematics/EQM-a7b3c9d2e1f4g5h6i7j8k9l0m1n2o3p4-x9y2
  /intro-physics/EQM-z1y2x3w4v5u6t7s8r9q0p1o2n3m4l5k6-a1b2

NOTE: Path-based format (not query parameter). 
Do NOT use: /{exam-slug}?key={secret-key}
```

### 22.8 Anonymous Participant Creation (Auto-Signup)

When a valid secret key is accessed, the system automatically creates an anonymous participant:

**First-Time Visitor (no tracking cookie):**
1. Validate secret key
2. Generate anonymous credentials:
   - Email: `anon-{timestamp}-{random6}@exam.local`
   - Password: Secure random (32 chars, stored hashed)
3. Create participant record:
   - Status: ACTIVE
   - Deadlines: Calculated from exam settings
   - Source: SECRET_KEY
4. Create session with exam-specific cookie
5. Set tracking cookie linking to participant
6. Redirect to dashboard

**Returning Visitor (tracking cookie exists):**
1. Validate tracking cookie
2. Look up existing anonymous participant
3. Validate secret key still active
4. Resume existing session
5. Redirect to dashboard

**Tracking Cookie Format:**
- Name: `eqm_track_{examSlug}`
- Value: Hashed participant identifier
- Expires: 90 days
- HttpOnly: true

### Acceptance Criteria (Auto-Signup):
- [ ] Anonymous email format: `anon-{timestamp}-{random}@exam.local`
- [ ] Password securely generated and hashed
- [ ] Tracking cookie persists across sessions
- [ ] Returning visitors resume existing progress
- [ ] No signup form shown for secret key access

---

## UI Components

### 22.9 Secret Key Manager (Admin)

**Key List View**
- Table with columns: Label, Key (masked), Status, Usage, Expiration, Actions
- Status badges: Active, Expired, Exhausted, Disabled
- Quick copy button for key
- Bulk selection for batch operations

**Key Creation Modal**
- Exam selector (if not in exam context)
- Label input
- Usage limit (optional, number input)
- Expiration date (optional, date picker)
- IP restriction pattern (optional, advanced)
- Generate single or batch

**Key Details Panel**
- Full key display (click to reveal)
- Usage statistics graph
- Recent access log
- Edit label/limits
- Regenerate key option
- Deactivate/Delete actions

### 22.10 Generated Key Display

**After Generation**
- Large, copyable key display
- QR code for mobile access
- Direct URL with exam slug (path-based: `/{slug}/{key}`)
- Email key option
- "Key shown once" warning

---

## Acceptance Criteria

### Key Generation
- [ ] Keys are cryptographically secure
- [ ] Batch generation works (up to 100)
- [ ] Collision detection functions
- [ ] Keys stored hashed
- [ ] Plain key shown only on creation

### Validation
- [ ] Active keys grant access
- [ ] Inactive keys rejected
- [ ] Expired keys rejected
- [ ] Exhausted keys rejected
- [ ] IP restrictions enforced
- [ ] Rate limiting works

### Usage Tracking
- [ ] Usage count increments
- [ ] Last used timestamp updates
- [ ] Analytics recorded (if enabled)
- [ ] Rate limit enforced

### Management
- [ ] Create/update/delete works
- [ ] Bulk operations function
- [ ] Expiration extension works
- [ ] Limit increase works
- [ ] Deactivation immediate

### URL Access
- [ ] Valid key shows exam content
- [ ] Invalid key shows error page
- [ ] Tracking cookie set on access
- [ ] Progress persisted per key

### Security
- [ ] Brute force protection active
- [ ] IP hashed in logs
- [ ] Audit trail complete
- [ ] No key enumeration possible

---

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Invalid key format | 404 (no info leak) |
| Key not found | 404 with generic message |
| Key expired | 403 with expiration notice |
| Usage limit reached | 403 with limit notice |
| IP not allowed | 403 with access denied |
| Rate limit exceeded | 429 with retry-after header |
| Exam not accessible | 404 (no info leak) |

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/exams/{id}/secret-keys` | List keys for exam |
| POST | `/exams/{id}/secret-keys` | Generate new key(s) |
| GET | `/secret-keys/{id}` | Get key details |
| PUT | `/secret-keys/{id}` | Update key |
| DELETE | `/secret-keys/{id}` | Delete key |
| POST | `/secret-keys/{id}/deactivate` | Deactivate key |
| POST | `/secret-keys/{id}/regenerate` | Generate new value |
| POST | `/secret-keys/validate` | Validate key (internal) |

---

## Notes

- Consider implementing key groups for batch management
- QR codes generated client-side for security
- Export keys as CSV for distribution
- Webhook on key usage threshold (optional)
- Keys can be associated with participant records for tracking

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Secret Key Admin UI** | [25-secret-key-admin-ui](25-secret-key-admin-ui.md) | Key management interface |
| **Secret Key Analytics** | [26-secret-key-analytics](26-secret-key-analytics.md) | Usage tracking and reporting |
| **Auth Flow Diagram** | [diagrams/03-secret-key-auth-flow](../diagrams/03-secret-key-auth-flow.md) | Visual 7-phase sequence |
| **Participant Service** | [27-participant-service](27-participant-service.md) | Anonymous participant creation (§25.7) |
| **Exam Service** | [12-exam-service](12-exam-service.md) | Exam association |
| **Rate Limiting** | [48-rate-limiting](48-rate-limiting.md) | Key validation rate limits |
| **Database Schema** | [04-database-schema](04-database-schema.md) | `secretKey`, `secretKeyAccess` tables |
| **Shared Constants** | [66-shared-constants](../../66-shared-constants.md) | Cookie naming patterns (`eqm_track_{examSlug}`) |
| **Audit Logging** | [46-audit-logging](46-audit-logging.md) | Access logging |
| **Public Exam View** | [43-public-exam-view](43-public-exam-view.md) | Anonymous exam display |

### Key Algorithm References
- **Key Generation**: Section 22.3 collision-safe generation
- **Validation Rules**: Section 22.4 all checks
- **Auto-Signup**: Section 22.8 anonymous participant creation flow

---

*Next: `25-secret-key-admin-ui.md`*
