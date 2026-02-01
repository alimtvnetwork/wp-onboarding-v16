# Frontend Spec Split Plan

> **Created:** January 25, 2026  
> **Status:** Planning Phase  
> **Source:** frontend-full-spec.md (1208 lines)

---

## 📋 Task Breakdown

The work is divided into **3 major phases**:

### Phase A: Frontend Spec Splitting (5 batches × 5 specs = ~25 specs)
### Phase B: Consistency Check - Frontend vs Backend
### Phase C: Consistency Check - Backend Full Spec vs Split Specs

---

## Phase A: Frontend Spec Splitting

### Proposed Split Structure (25 files)

#### Batch 1: Foundation & Authentication (Specs 01-05)
| # | File | Source Lines | Description |
|---|------|--------------|-------------|
| 01 | `01-frontend-overview.md` | 1-50 | Version, TOC, logging approach, stack |
| 02 | `02-public-landing-page.md` | 53-98, 275-329 | Landing page layout, states, behavior |
| 03 | `03-signup-flow.md` | 64-72, 582-623 | Form fields, validation, API, flow |
| 04 | `04-login-flow.md` | 69-76, 332-371, 628-665 | Login page, form, remember me |
| 05 | `05-participate-flow.md` | 77-157 | Authenticated user joining new exam |

#### Batch 2: Dashboard & Exam Content (Specs 06-10)
| # | File | Source Lines | Description |
|---|------|--------------|-------------|
| 06 | `06-dashboard-page.md` | 374-448 | Dashboard layout, status badges, cards |
| 07 | `07-section-view.md` | 451-521 | Section page, markdown rendering, navigation |
| 08 | `08-markdown-rendering.md` | 169-188, 509-521 | Markdown display, section cards, states |
| 09 | `09-prerequisites-display.md` | 158-168, 722-755 | Video embeds, links, checklist items |
| 10 | `10-sub-exams-display.md` | 189-202 | Hierarchical display, breadcrumbs |

#### Batch 3: Deadlines & Extensions (Specs 11-15)
| # | File | Source Lines | Description |
|---|------|--------------|-------------|
| 11 | `11-deadline-countdown.md` | 222-240 | Soft/hard/extension countdown UI |
| 12 | `12-extension-request.md` | 524-577, 803-854 | Extension form, validation, status |
| 13 | `13-session-management.md` | 212-221 | Cookies, sliding expiration, remember me |
| 14 | `14-exam-completion-flow.md` | 759-799 | Mark sections, completion state |
| 15 | `15-locked-state.md` | 431-432, 468-470, 891-904 | Hard deadline lockout behavior |

#### Batch 4: Edge Cases & Error Handling (Specs 16-20)
| # | File | Source Lines | Description |
|---|------|--------------|-------------|
| 16 | `16-error-handling.md` | 241-268, 909-924, 1136-1141 | API errors, network errors, validation |
| 17 | `17-edge-cases.md` | 857-991 | Session expiry, deadline during session, etc. |
| 18 | `18-secret-key-access.md` | 327-328, 945-970 | Auto-signup via secret key |
| 19 | `19-form-validation.md` | 353-371, 557-569 | Client-side validation rules |
| 20 | `20-loading-states.md` | 1129-1133 | Button loading, skeleton loaders |

#### Batch 5: Logging, UI/UX & Tech (Specs 21-25)
| # | File | Source Lines | Description |
|---|------|--------------|-------------|
| 21 | `21-frontend-logging.md` | 26-35, 255-268, 994-1087 | API-based logging, events table |
| 22 | `22-ui-design-system.md` | 1091-1148 | Colors, typography, accessibility |
| 23 | `23-responsive-design.md` | 1114-1128, 1205-1208 | Mobile/tablet/desktop layouts |
| 24 | `24-tech-stack.md` | 1151-1164 | Dependencies, libraries, frameworks |
| 25 | `25-acceptance-criteria.md` | 1167-1208 | Master checklist for frontend |

---

## Phase B: Frontend vs Backend Consistency Check

### Identified Inconsistencies

| # | Issue | Frontend Spec | Backend Spec | Severity | Resolution |
|---|-------|---------------|--------------|----------|------------|
| 1 | **Session cookie naming** | `eqm_session_{examSlug}` | Not explicitly defined in split specs | Medium | Add to backend session spec or standardize |
| 2 | **API endpoint prefix** | `/api/...` (e.g., `/api/login`) | `/wp-json/eqm/v1/...` | High | Frontend uses shorthand; should document full path |
| 3 | **Logging endpoint** | `POST /api/log-event` | Not in Spec 36 REST endpoints | High | Add to REST API spec |
| 4 | **Participate endpoint** | `POST /api/participate` | Not in Spec 36 REST endpoints | High | Add to REST API spec |
| 5 | **Video prerequisite types** | YouTube, Vimeo embeds | Backend has `VIDEO` type only | Low | Frontend display detail, not schema issue |
| 6 | **Extension request file upload** | PDF/DOC/DOCX, 5MB max | Backend Spec 30 has `attachmentPath` but no size/type validation | Medium | Add validation rules to Spec 30 |
| 7 | **Secret key auto-signup** | Creates temp email like `secret-user-{ts}@exam.local` | Backend Spec 24 tracks analytics but no auto-signup logic | Medium | Add auto-signup behavior to Spec 24 |
| 8 | **Remember me duration** | 30 days | Backend Spec 27 mentions session but not duration | Low | Document in session management |
| 9 | **LinkedIn field in participate** | Required for participation confirmation | Backend participant table has `linkedIn` field | ✅ Aligned | N/A |
| 10 | **Progress percentage display** | Shows "X of Y sections completed" | Backend caches `progressPercent` in participant table | ✅ Aligned | N/A |

### Action Items for Phase B
- [ ] B1: Add `/api/log-event` to Spec 36-rest-api-endpoints.md
- [ ] B2: Add `/api/participate` to Spec 36-rest-api-endpoints.md
- [ ] B3: Add session cookie naming convention to backend specs
- [ ] B4: Add file upload validation (size, type) to Spec 30-extension-system.md
- [ ] B5: Add auto-signup via secret key logic to Spec 24-secret-key-service.md
- [ ] B6: Document API prefix mapping (`/api/` → `/wp-json/eqm/v1/`)

---

## Phase C: Backend Full Spec vs Split Specs Consistency

### Methodology
Compare `exam-questions-manager-full-spec.md` (2773 lines) against the 47 split spec files.

### Identified Discrepancies

| # | Issue | Full Spec | Split Spec | Severity |
|---|-------|-----------|------------|----------|
| 1 | **Certificate generation** | Not in full spec TOC | Spec 44 exists | Low | Split spec has more features |
| 2 | **Audit logging** | Basic mention in logging section | Spec 46 fully detailed | Low | Split spec is authoritative |
| 3 | **Reporting dashboard** | Not explicitly detailed | Spec 47 fully detailed | Low | Split spec is authoritative |
| 4 | **Notifications panel** | Basic email templates only | Spec 45 adds in-app notifications | Low | Split spec expands scope |
| 5 | **Email queue system** | Mentioned but basic | Spec 31 fully detailed with status/priority | Low | Split spec is authoritative |
| 6 | **BooleanHelpers class** | Not in full spec | Spec 01 defines it | Low | Split spec adds coding standards |
| 7 | **Error management hierarchy** | Basic dual-file logging | Spec 02 adds config hierarchy, rotation | Low | Split spec enhanced |
| 8 | **Exam list view** | Admin UI basic mention | Spec 38 detailed with filters, bulk actions | Low | Split spec expands scope |
| 9 | **Import/export system** | Basic mention | Spec 40 fully detailed | Low | Split spec is authoritative |

### Conclusion for Phase C
The split specs are **MORE COMPLETE** than the full spec. The full spec serves as the original requirements document, while split specs are the authoritative implementation reference.

**Recommendation:** Mark full spec as "Original Requirements - Superseded by Split Specs" and use split specs as source of truth.

---

## 📊 Execution Plan

### Batch Execution Schedule

| Batch | Specs | Estimated Effort | Dependencies |
|-------|-------|------------------|--------------|
| Batch 1 | 01-05 | 1 session | None |
| Batch 2 | 06-10 | 1 session | Batch 1 |
| Batch 3 | 11-15 | 1 session | Batch 2 |
| Batch 4 | 16-20 | 1 session | Batch 3 |
| Batch 5 | 21-25 | 1 session | Batch 4 |
| Phase B | Fixes | 1 session | Batch 5 |
| Phase C | Archive | 1 session | Phase B |

### Commands for Each Batch
When user says "Do Batch X":
1. Create the 5 spec files for that batch
2. Use checklist/acceptance criteria format (no code)
3. Cross-reference backend spec numbers where applicable
4. Report completion

---

## 📝 Spec File Template

Each split spec will follow this structure:

```markdown
# XX. [Topic Name]

## Overview
Brief description of this feature/page.

---

## XX.1 [Sub-section]

### Description
What this covers.

### Acceptance Criteria
- [ ] Criterion 1
- [ ] Criterion 2

### UI Elements
| Element | Type | Behavior |
|---------|------|----------|

### API Dependencies
| Endpoint | Method | Backend Spec |
|----------|--------|--------------|

---

## XX.2 [Next Sub-section]
...
```

---

## ✅ Ready for Execution

User can now say:
- **"Do Batch 1"** → Creates specs 01-05
- **"Do Batch 2"** → Creates specs 06-10
- **"Do Batch 3"** → Creates specs 11-15
- **"Do Batch 4"** → Creates specs 16-20
- **"Do Batch 5"** → Creates specs 21-25
- **"Do Phase B"** → Fixes backend spec inconsistencies
- **"Do Phase C"** → Archives full spec, marks split as authoritative
