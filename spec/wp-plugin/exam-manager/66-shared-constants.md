# Shared Constants Specification

> **Version:** 1.0.0  
> **Last Updated:** 2026-01-26  
> **Status:** Authoritative Source of Truth

This document defines all values that MUST be consistent between frontend and backend implementations.

---

## 1. Cookie Names

---

### ⚠️ IMPLEMENTATION WARNING - MEDIUM RISK AREA: Cookie Naming

> **AI IMPLEMENTATION ALERT**: Cookies MUST include the exam slug for isolation.
> 
> **THE WRONG WAY**:
> ```php
> // ❌ WRONG: Cookie shared across ALL exams
> setcookie('eqm_session', $value, ...);
> setcookie('eqm_anon', $trackingId, ...);
> ```
> 
> **THE CORRECT WAY**:
> ```php
> // ✅ CORRECT: Cookie scoped to specific exam
> setcookie('eqm_session_' . $examSlug, $value, ...);
> setcookie('eqm_anon_' . $examSlug, $trackingId, ...);
> ```
> 
> **WHY THIS MATTERS**: Without exam slug, users participating in multiple exams will have cross-exam session leakage, wrong progress shown, and security issues.

---

### Cookie Pattern (MANDATORY)

All cookies MUST follow the pattern: `eqm_{purpose}_{examSlug}`

| Cookie Name Pattern | Purpose | Duration |
|---------------------|---------|----------|
| `eqm_session_{examSlug}` | Authenticated user session | 7 days (default) / 30 days (remember me) |
| `eqm_anon_{examSlug}` | Anonymous user tracking | 30 days |
| `eqm_track_{examSlug}` | Progress persistence for anonymous | 90 days |

### Pre-Implementation Checklist

- [ ] ✅ Cookie name includes `_{examSlug}` suffix
- [ ] ✅ Exam slug is URL-safe (lowercase, hyphens only)
- [ ] ❌ You do NOT use global cookies like `eqm_session` without slug

### Session Duration Rules
- `isRememberMe: false` → 7 days
- `isRememberMe: true` → 30 days
- Anonymous tracking → 30 days minimum

---

## 2. API Endpoints

Base path: `/wp-json/eqm/v1/`

### Authentication Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/signup` | Register new participant |
| POST | `/login` | Authenticate existing participant |
| POST | `/logout` | End session |
| POST | `/forgot-password` | Request password reset email |
| POST | `/reset-password` | Complete password reset with token |
| POST | `/validate-secret-key` | Validate secret key and get exam access |
| POST | `/participate` | Logged-in user joins new exam |

### Exam Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/exams` | List all accessible exams |
| GET | `/exams/{slug}` | Get exam details |
| GET | `/exams/{slug}/content` | Get exam markdown content |
| GET | `/exams/{slug}/prerequisites` | Get prerequisite list |
| GET | `/exams/{slug}/checklists` | Get checklist items by phase |

### Participant Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/participants/{id}` | Get participant details |
| GET | `/participants/{id}/progress` | Get current progress state |
| POST | `/participants/{id}/sections/{sectionNumber}/complete` | Mark section complete |
| POST | `/participants/{id}/prerequisites/{prerequisiteId}/complete` | Mark prerequisite complete |
| POST | `/participants/{id}/items/{itemId}/complete` | Mark checklist item complete |
| POST | `/participants/{id}/extensions` | Request deadline extension |

### Utility Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/log-event` | Log frontend analytics event |
| GET | `/wiki/{slug}` | Get wiki article content |

---

## 3. URL Patterns

### Secret Key Access
```
/{examSlug}/{secretKey}
```
Example: `/advanced-exam/ABC123XYZ`

### Exam Direct Access
```
/{examSlug}
```
Example: `/advanced-exam`

---

## 4. Validation Limits

### User Input Limits
| Field | Min | Max | Notes |
|-------|-----|-----|-------|
| Email | 5 | 255 | RFC 5322 compliant |
| Password | 8 | 128 | At least 1 uppercase, 1 number |
| Name | 2 | 100 | No special characters except hyphen, apostrophe |
| LinkedIn URL | 20 | 255 | Must contain `linkedin.com` |

### Extension Request Limits
| Field | Min | Max | Notes |
|-------|-----|-----|-------|
| Reason text | 50 | 1000 | Required justification |
| Additional days | 1 | 30 | Whole numbers only |

### File Upload Limits
| Context | Allowed Types | Max Size | Max Files |
|---------|---------------|----------|-----------|
| Extension request | `PDF`, `DOC`, `DOCX`, `PNG`, `JPG`, `JPEG` | 5 MB | 3 |
| Evidence (FILE) | `PDF`, `DOC`, `DOCX`, `XLS`, `XLSX`, `PPT`, `PPTX`, `TXT`, `ZIP` | 10 MB | 5 |
| Evidence (IMAGE) | `JPG`, `JPEG`, `PNG`, `GIF`, `WEBP` | 5 MB | 10 |

> **Note**: Extension requests now accept images (PNG, JPG) in addition to documents.

### Content Limits
| Field | Max |
|-------|-----|
| Exam title | 200 chars |
| Exam slug | 50 chars |
| Wiki article | 100,000 chars |
| Checklist item label | 255 chars |

---

## 5. Deadline Color Scheme

Unified color scheme for countdown displays. Both soft and hard deadlines use the same base colors, with hard deadline using darker variants for critical states.

### Time-Based Colors
| Time Remaining | Status | CSS Class | Hex Color |
|----------------|--------|-----------|-----------|
| > 7 days | Safe | `deadline-safe` | `#22C55E` (Green) |
| 3-7 days | Warning | `deadline-warning` | `#EAB308` (Yellow) |
| 1-3 days | Urgent | `deadline-urgent` | `#F97316` (Orange) |
| < 24 hours (soft) | Critical | `deadline-critical` | `#F87171` (Light Red) |
| < 24 hours (hard) | Critical Hard | `deadline-critical-hard` | `#DC2626` (Dark Red) |
| Overdue/Locked | Overdue | `deadline-overdue` | `#000000` (Black) |

### Tailwind CSS Variables
```css
:root {
  --deadline-safe: 142 71% 45%;
  --deadline-warning: 48 96% 47%;
  --deadline-urgent: 25 95% 53%;
  --deadline-critical: 0 91% 71%;
  --deadline-critical-hard: 0 72% 51%;
  --deadline-overdue: 0 0% 0%;
}
```

---

## 6. Error Codes

All errors use format: `ERR_{category}_{number}`

### Authentication Errors (1xxx)
| Code | Message | HTTP Status |
|------|---------|-------------|
| ERR_AUTH_1001 | Invalid credentials | 401 |
| ERR_AUTH_1002 | Session expired | 401 |
| ERR_AUTH_1003 | Account locked | 403 |
| ERR_AUTH_1004 | Email not verified | 403 |
| ERR_AUTH_1005 | Invalid reset token | 400 |
| ERR_AUTH_1006 | Reset token expired | 400 |
| ERR_AUTH_1007 | Password requirements not met | 400 |

### Validation Errors (2xxx)
| Code | Message | HTTP Status |
|------|---------|-------------|
| ERR_VAL_2001 | Required field missing | 400 |
| ERR_VAL_2002 | Invalid email format | 400 |
| ERR_VAL_2003 | Value exceeds maximum length | 400 |
| ERR_VAL_2004 | Value below minimum length | 400 |
| ERR_VAL_2005 | Invalid file type | 400 |
| ERR_VAL_2006 | File size exceeds limit | 400 |
| ERR_VAL_2007 | Too many files | 400 |

### Access Errors (3xxx)
| Code | Message | HTTP Status |
|------|---------|-------------|
| ERR_ACC_3001 | Exam not found | 404 |
| ERR_ACC_3002 | Invalid secret key | 403 |
| ERR_ACC_3003 | Secret key expired | 403 |
| ERR_ACC_3004 | Secret key usage limit reached | 403 |
| ERR_ACC_3005 | Prerequisite not completed | 403 |
| ERR_ACC_3006 | Deadline passed | 403 |
| ERR_ACC_3007 | Participant locked | 403 |
| ERR_ACC_3008 | Not invited to exam | 403 |
| ERR_ACC_3009 | Phone number doesn't match invitation | 403 |
| ERR_ACC_3010 | Invite expired | 403 |

### Deadline Errors (4xxx)
| Code | Message | HTTP Status |
|------|---------|-------------|
| ERR_DL_4001 | Soft deadline reached | 200 (warning) |
| ERR_DL_4002 | Hard deadline reached | 403 |
| ERR_DL_4003 | Extension request denied | 403 |
| ERR_DL_4004 | Extension limit exceeded | 400 |

### Server Errors (9xxx)
| Code | Message | HTTP Status |
|------|---------|-------------|
| ERR_SRV_9001 | Internal server error | 500 |
| ERR_SRV_9002 | Database connection failed | 500 |
| ERR_SRV_9003 | Email service unavailable | 503 |
| ERR_SRV_9004 | Rate limit exceeded | 429 |

---

## 7. Participant Status Values

| Status | Description | Can Access Exam |
|--------|-------------|-----------------|
| `INVITED` | Initial state, not yet started | No |
| `ACTIVE` | Full access granted | Yes |
| `PAUSED` | Temporarily on hold | No |
| `SOFT_DEADLINE_REACHED` | Past soft deadline | Yes (with warning) |
| `HARD_DEADLINE_REACHED` | Past hard deadline | No |
| `EXTENDED` | Extension granted, back to active | Yes |
| `COMPLETED` | All requirements met | Read-only |
| `LOCKED` | Access revoked after deadline | No |
| `WITHDRAWN` | Dropped out voluntarily | No |

### Status Transition Rules
| From | Allowed Transitions |
|------|---------------------|
| `INVITED` | `ACTIVE`, `WITHDRAWN` |
| `ACTIVE` | `PAUSED`, `SOFT_DEADLINE_REACHED`, `COMPLETED`, `LOCKED`, `WITHDRAWN` |
| `PAUSED` | `ACTIVE`, `WITHDRAWN` |
| `SOFT_DEADLINE_REACHED` | `ACTIVE`, `HARD_DEADLINE_REACHED`, `EXTENDED`, `COMPLETED`, `LOCKED`, `WITHDRAWN` |
| `HARD_DEADLINE_REACHED` | `EXTENDED`, `LOCKED` |
| `EXTENDED` | `ACTIVE`, `SOFT_DEADLINE_REACHED`, `COMPLETED`, `LOCKED`, `WITHDRAWN` |
| `COMPLETED` | (terminal) |
| `LOCKED` | (terminal) |
| `WITHDRAWN` | (terminal) |

---

## 8. Checklist Phases

| Phase | Description | When Visible |
|-------|-------------|--------------|
| `PRE` | Before exam access | After signup, before starting |
| `IN_EXAM` | During exam | While exam content is accessible |
| `POST` | After completion | After all IN_EXAM items done |

---

## 9. Extension Request Status

| Status | Description |
|--------|-------------|
| `PENDING` | Awaiting admin review |
| `APPROVED` | Extension granted |
| `DENIED` | Extension rejected |
| `EXPIRED` | Request no longer valid |

---

## 10. Rate Limits

| Endpoint Category | Limit | Window |
|-------------------|-------|--------|
| Authentication | 5 attempts | 15 minutes |
| Password reset | 3 requests | 1 hour |
| API general | 100 requests | 1 minute |
| File upload | 10 uploads | 1 hour |
| Extension request | 3 requests | 24 hours |

---

## 11. Token Expiration

| Token Type | Duration |
|------------|----------|
| Password reset | 1 hour |
| Email verification | 24 hours |
| Secret key (default) | No expiration (configurable) |
| Session (default) | 7 days |
| Session (remember me) | 30 days |

---

## 12. Theme Configuration

### Default Theme Slugs
| Slug | Name | Scope | Description |
|------|------|-------|-------------|
| `default` | Default Light | SHARED | Light theme, default on install |
| `dark` | Dark Mode | SHARED | Dark theme |
| `high-contrast` | High Contrast | SHARED | Accessibility-focused theme |
| `minimal` | Minimal | SHARED | Clean, reduced visual noise |

### Theme Scopes
| Scope | Description |
|-------|-------------|
| `ADMIN` | WordPress admin panel only |
| `FRONTEND` | Participant-facing pages only |
| `SHARED` | Applied to both admin and frontend |

### Core CSS Variables
```css
:root {
  /* Colors - Primary */
  --primary: 222.2 47.4% 11.2%;
  --primary-foreground: 210 40% 98%;
  --primary-hover: 222.2 47.4% 15%;
  
  /* Background */
  --background: 0 0% 100%;
  --foreground: 222.2 84% 4.9%;
  --card: 0 0% 100%;
  --muted: 210 40% 96.1%;
  --muted-foreground: 215.4 16.3% 46.9%;
  
  /* Border */
  --border: 214.3 31.8% 91.4%;
  --ring: 222.2 84% 4.9%;
  --radius: 0.5rem;
  
  /* Typography */
  --font-sans: Inter, system-ui, sans-serif;
  --font-mono: JetBrains Mono, monospace;
  --text-base: 1rem;
  --prose-max-width: 65ch;
  --prose-line-height: 1.75;
  
  /* Forms */
  --input-height: 2.5rem;
  --input-radius: 0.5rem;
}
```

### Theme Seed File Location
```
config/
├── themes.json          # Theme index
└── themes/
    ├── default.json     # Light theme config
    ├── dark.json        # Dark theme config
    └── high-contrast.json
```

---

## 13. Cache Configuration

### Cache Backend Priority
| Priority | Backend | Check |
|----------|---------|-------|
| 1 | Memcached | `extension_loaded('memcached')` |
| 2 | Redis | `extension_loaded('redis')` |
| 3 | APCu | `extension_loaded('apcu')` |
| 4 | File | Always available (fallback) |

### Cache Key Pattern
```
eqm:{scope}:{type}:{identifier}:{version}

Examples:
- eqm:user:123:profile:v1
- eqm:exam:certification-2024:content:v3
- eqm:page:dashboard:user_123:theme_abc123
```

### Cache TTL Defaults
| Data Type | TTL | Description |
|-----------|-----|-------------|
| Page Cache | 300s (5 min) | Pre-rendered HTML |
| User Profile | 3600s (1 hour) | Basic user data |
| Exam Content | 3600s (1 hour) | Markdown content |
| Settings | 3600s (1 hour) | Plugin settings |
| Participant | 300s (5 min) | Progress, deadlines |
| Theme Config | 86400s (24 hours) | Full theme JSON |

### Cache Invalidation Tags
| Tag Pattern | Scope |
|-------------|-------|
| `user:{id}` | All data for specific user |
| `exam:{slug}` | All data for specific exam |
| `theme:{slug}` | Theme-related caches |
| `settings` | Plugin settings |
| `global` | All caches |

### Cacheable Pages
| Page | Cacheable | Notes |
|------|-----------|-------|
| Dashboard | Yes | User-specific key |
| Exam View | Yes | Per-exam key |
| Section View | Yes | Per-section key |
| Profile | Yes | User-specific |
| Login | No | Security |
| Signup | No | Security |
| Admin/* | No | Dynamic content |

### Session Cache Keys
```php
$_SESSION['eqm'] = [
    'user' => [...],      // TTL: 1 hour
    'theme' => [...],     // TTL: 24 hours
    'exams' => [...],     // TTL: 5 minutes
    'lastActivity' => int
];
```

---

## Implementation Notes

### Frontend
- Import constants from a shared `constants.ts` file
- Use TypeScript enums for status values
- Color scheme defined in `index.css` as CSS variables
- Theme changes via CSS variables injection

### Backend
- Constants defined in `Consts.php`
- Enums defined in `Enums/` directory
- Error codes in `ErrorCodes.php`
- Theme/Cache services in `Services/` directory

### Cross-Reference
- Frontend Spec: `02-frontend/`
- Backend Spec: `01-admin-backend/split-spec/`
- Error Management: `01-admin-backend/split-spec/02-error-management.md`
- REST API: `01-admin-backend/split-spec/36-rest-api-endpoints.md`
- Theme System: `01-admin-backend/split-spec/56-theming-system.md`
- Caching System: `01-admin-backend/split-spec/57-caching-system.md`
- Frontend Theme: `02-frontend/split-spec/31-theme-application.md`
