# 18. Secret Key Access

## Overview
Auto-signup flow when accessing exam via secret key URL, creating anonymous participant with tracking.

---

## 18.1 URL Format

**Path-based (preferred)**: `/{exam-slug}/{secret-key}`  
**Query-based (deprecated)**: `/{exam-slug}?key={secret-key}`

---

## 18.2 Auto-Signup Flow

1. User visits secret key URL
2. Frontend detects key in URL
3. POST to `/api/validate-secret-key`
4. If valid:
   - Auto-create anonymous participant
   - Email: `anon-{timestamp}-{random}@exam.local`
   - Set tracking cookie `eqm_anon_{examSlug}`
   - Redirect to dashboard
5. If invalid:
   - Show: "Invalid or expired link"
   - Log error

---

## 18.3 Returning Anonymous User

1. User returns with tracking cookie
2. Validate cookie against participant record
3. Resume existing session
4. Continue from previous progress

---

## 18.4 Acceptance Criteria

- [ ] Valid secret key creates anonymous participant
- [ ] No signup form shown
- [ ] Tracking cookie persists progress
- [ ] Invalid key shows clear error
- [ ] Returning users resume progress

---

*Related: [24-secret-key-service](../../01-admin-backend/split-spec/24-secret-key-service.md), [27-participant-service](../../01-admin-backend/split-spec/27-participant-service.md) §25.7*
