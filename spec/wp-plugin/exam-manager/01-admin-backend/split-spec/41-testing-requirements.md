# 41 - Testing Requirements

> **Phase:** Quality Assurance  
> **Dependencies:** All implementation specs  
> **Estimated Time:** Ongoing (parallel with development)

---

## 📋 Scope

Define comprehensive testing strategy including PHPUnit for backend, Vitest for frontend, and E2E tests. This spec provides test case templates for critical algorithms.

---

## 🔧 Testing Stack

| Layer | Tool | Purpose |
|-------|------|---------|
| **Backend Unit** | PHPUnit 10+ | PHP class/method testing |
| **Backend Integration** | PHPUnit + SQLite in-memory | Service layer testing |
| **Frontend Unit** | Vitest + Testing Library | Component and hook testing |
| **E2E** | Playwright | Full user journey testing |
| **API Contract** | PHPUnit + WP REST API | Endpoint validation |

---

## 📂 Test File Structure

```
/tests/
├── php/
│   ├── Unit/
│   │   ├── Helpers/
│   │   │   ├── BooleanHelpersTest.php
│   │   │   ├── ConditionalHelpersTest.php
│   │   │   └── FileLoaderHelpersTest.php
│   │   ├── Services/
│   │   │   ├── DeadlineEngineTest.php
│   │   │   ├── FeatureFlagServiceTest.php
│   │   │   ├── ProgressCalculatorTest.php
│   │   │   └── AnonymousMigrationTest.php
│   │   └── Utils/
│   │       └── LoggerTest.php
│   ├── Integration/
│   │   ├── ExamServiceIntegrationTest.php
│   │   ├── ParticipantServiceIntegrationTest.php
│   │   └── SecretKeyFlowTest.php
│   └── bootstrap.php
├── js/
│   ├── components/
│   ├── hooks/
│   └── setup.ts
└── e2e/
    ├── participant-journey.spec.ts
    └── admin-exam-crud.spec.ts
```

---

## 📄 Detailed Algorithm Test Specifications

| Spec File | Component | Coverage |
|-----------|-----------|----------|
| [41a-test-spec-conditional-helpers.md](41a-test-spec-conditional-helpers.md) | ConditionalHelpers | logIf, execIf, ifNotNull patterns |
| [41b-test-spec-file-loader.md](41b-test-spec-file-loader.md) | FileLoaderHelpers | Stack trace logging, batch loading |
| [41c-test-spec-feature-flags.md](41c-test-spec-feature-flags.md) | FeatureFlagService | Resolution hierarchy, rollout % |
| [41d-test-spec-deadline-engine.md](41d-test-spec-deadline-engine.md) | DeadlineEngine | Extension calculation, timezone |
| [41e-test-spec-progress-calculation.md](41e-test-spec-progress-calculation.md) | ProgressCalculator | floor(), SKIPPED, weights |

---

## ✅ Coverage Targets

| Area | Minimum | Target |
|------|---------|--------|
| **Critical Algorithms** | 95% | 100% |
| **Service Layer** | 80% | 90% |
| **Helpers/Utils** | 90% | 100% |
| **Entity Models** | 70% | 85% |
| **Overall** | 75% | 85% |

---

## 41.1 Unit Testing

### Coverage Requirements
- All ORM model methods
- All service class methods
- All validation utilities
- All enum helper methods

### Testing Framework
- PHPUnit for PHP testing
- WordPress test library integration
- Isolated database for tests

### Acceptance Criteria:
- [ ] Minimum 80% code coverage for core classes
- [ ] Tests run without WordPress installation (mocked)
- [ ] Each model has CRUD operation tests
- [ ] Edge cases documented and tested

---

## 41.2 Integration Testing

### Test Scenarios
- Database schema creation and migrations
- API endpoint request/response cycles
- Email sending with mock transport
- Cron job execution

### Acceptance Criteria:
- [ ] Tests use separate test database
- [ ] API tests verify response structure
- [ ] Email tests capture sent content
- [ ] Cron tests verify scheduled execution

---

## 41.3 Frontend Testing

### React Component Tests
- Unit tests for individual components
- Integration tests for component interactions
- Snapshot tests for UI consistency

### Testing Tools
- Jest for test runner
- React Testing Library for component testing
- MSW for API mocking

### Acceptance Criteria:
- [ ] All form components have validation tests
- [ ] Data display components test loading/error states
- [ ] User interactions trigger expected callbacks
- [ ] Accessibility tests for key components

---

## 41.4 End-to-End Testing

### Critical User Flows
1. Admin creates new exam
2. Admin adds participant
3. Participant accesses exam via secret key
4. Participant completes checklist items
5. Admin approves extension request
6. System locks participant at deadline

### Testing Tool
- Playwright or Cypress recommended
- Headless browser automation
- Screenshot comparison for visual regression

### Acceptance Criteria:
- [ ] All critical flows have E2E tests
- [ ] Tests run in CI/CD pipeline
- [ ] Visual regression detection
- [ ] Cross-browser testing (Chrome, Firefox)

---

## 41.5 Performance Testing

### Benchmarks
- Page load time < 2 seconds
- API response time < 500ms
- Database queries < 100ms each
- Handle 1000+ participants per exam

### Testing Approach
- Load testing with sample data generators
- Query profiling and optimization
- Memory usage monitoring

### Acceptance Criteria:
- [ ] Benchmark tests documented
- [ ] Performance baseline established
- [ ] Regression alerts for slowdowns
- [ ] Query optimization verified

---

## 41.6 Security Testing

### Test Areas
- SQL injection prevention (ORM validation)
- XSS prevention (output escaping)
- CSRF protection (nonce verification)
- Authentication bypass attempts
- Authorization (RBAC) enforcement

### Acceptance Criteria:
- [ ] Input sanitization tested with malicious payloads
- [ ] All user input escaped on output
- [ ] Nonce required for all state-changing operations
- [ ] Role-based access verified per endpoint
- [ ] Secret keys cryptographically secure

---

## 41.7 Test Data Generators

### Factories Required
- ExamFactory: Generate exam with configurable options
- ParticipantFactory: Generate participants with progress
- WikiFactory: Generate wiki pages with revisions
- UserFactory: Generate users with roles

### Seeder Commands
- Seed development environment
- Seed demo data for presentations
- Clear test data command

### Acceptance Criteria:
- [ ] Factories support attribute overrides
- [ ] Seeder creates realistic data relationships
- [ ] Performance testing uses large dataset seeder
- [ ] Test data clearly marked (not mixed with production)

---

## 📌 Related Specifications

| Spec | Relationship |
|------|--------------|
| `01-coding-spec.md` | BooleanHelpers, ConditionalHelpers |
| `29-deadline-engine.md` | Deadline calculation logic |
| `58-feature-flags.md` | Feature flag resolution |
| `28-participant-progress.md` | Progress calculation |
| `../../61-common-implementation-pitfalls.md` | Test case sources |

---

*Next: `41a-test-spec-conditional-helpers.md`*
