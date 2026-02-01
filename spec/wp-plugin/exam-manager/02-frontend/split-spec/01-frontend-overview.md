# 01. Frontend Overview

## Overview
Introduction to the Exam Questions Manager frontend architecture, conventions, and logging approach.

---

## 01.1 Document Information

| Property | Value |
|----------|-------|
| Version | 2.0.1 FINAL |
| Status | Frontend/Public-Facing Requirements (COMPLETE) |
| Target Platform | WordPress 6.0+ with PHP 8.0+ |
| Last Updated | January 2026 |

---

## 01.2 API Path Convention

> **IMPORTANT:** Throughout frontend specs, API endpoints use the shorthand `/api/` prefix for readability.

| This Document | Actual WordPress Endpoint |
|---------------|---------------------------|
| `POST /api/login` | `POST /wp-json/eqm/v1/login` |
| `POST /api/signup` | `POST /wp-json/eqm/v1/signup` |
| `POST /api/log-event` | `POST /wp-json/eqm/v1/log-event` |
| `POST /api/participate` | `POST /wp-json/eqm/v1/participate` |
| `POST /api/request-extension` | `POST /wp-json/eqm/v1/request-extension` |

### Acceptance Criteria:
- [ ] All frontend API calls use full path: `/wp-json/eqm/v1/{endpoint}`
- [ ] API base URL configurable via environment variable
- [ ] CORS headers properly configured in backend

---

## 01.3 Frontend Logging Approach

Frontend makes lightweight API calls to backend for server-side logging.

### Architecture
- **Endpoint**: `POST /api/log-event`
- **Storage**: `/wp-content/uploads/exam-questions-manager/logs/plugin.log`
- **Pattern**: Fire-and-forget (non-blocking)

### Logged Actions
- Page views (landing, dashboard, section)
- Form submissions (signup, login, extension request)
- Exam actions (section marked done, prerequisite completed)
- Errors (validation, API, network)
- Navigation (previous/next section, breadcrumb clicks)

### Acceptance Criteria:
- [ ] Logging never blocks UI interactions
- [ ] Failed log calls silently ignored
- [ ] All user actions logged with timestamp
- [ ] Participant ID and exam ID included when available

---

## 01.4 Core Pages

| Route | Page | Protection |
|-------|------|------------|
| `/{slug}` | Public Landing Page | None |
| `/{slug}/login` | Login Page | None |
| `/{slug}/dashboard` | Participant Dashboard | Requires session |
| `/{slug}/section/{n}` | Exam Section View | Requires session + not locked |
| `/{slug}/extend-deadline` | Extension Request | Requires session + exam locked |
| `/{slug}/{secretKey}` | Secret Key Auto-Signup | None |

---

## 01.5 Session Cookie Convention

Cookies are exam-scoped to prevent cross-exam interference:

| Cookie | Pattern | Purpose |
|--------|---------|---------|
| Session | `eqm_session_{examSlug}` | Authenticated session |
| Anonymous | `eqm_anon_{examSlug}` | Secret key anonymous tracking |
| Tracking | `eqm_track_{examSlug}` | Analytics tracking |

### Acceptance Criteria:
- [ ] All cookies use `eqm_{purpose}_{examSlug}` pattern
- [ ] Cookies are HttpOnly and Secure (in production)
- [ ] Session cookie extends on each request (sliding expiration)
- [ ] "Remember me" sets 30-day expiration

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Backend REST API** | [36-rest-api-endpoints](../../01-admin-backend/split-spec/36-rest-api-endpoints.md) | API endpoint definitions |
| **Shared Constants** | [66-shared-constants](../../66-shared-constants.md) | Cookie patterns, API paths |
| **Backend Logging** | [07-logging-system](../../01-admin-backend/split-spec/07-logging-system.md) | Server-side log format |
| **Rate Limiting** | [48-rate-limiting](../../01-admin-backend/split-spec/48-rate-limiting.md) | API protection |

---

*Next: `02-public-landing-page.md`*
