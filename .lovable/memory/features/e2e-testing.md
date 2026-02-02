# Memory: features/e2e-testing

> **Location:** `.lovable/memory/features/e2e-testing.md`  
> **Updated:** 2026-02-02

---

## Overview

The project includes a comprehensive Go-based end-to-end (E2E) testing suite that validates the complete plugin lifecycle against **real WordPress sites**. Tests follow the Split Database architecture for data isolation.

---

## Test Categories

| Category | Tests | Description |
|----------|-------|-------------|
| `plugin-crud` | 5 | Plugin registration, update, delete, scanning |
| `site-connections` | 4 | Site CRUD, WP REST API connectivity testing |
| `sync-operations` | 6 | File scanning, hash comparison, change detection |
| `publish-flow` | 5 | ZIP upload, file patches, backup/restore |

---

## Architecture

### Backend (`backend/internal/services/e2e/`)

- **types.go**: TestSuite, TestCase, TestRun, TestResult types
- **service.go**: Test runner with async execution
- Database tables: `test_suites`, `test_cases`, `test_runs`, `test_results`

### Frontend (`src/pages/Tests.tsx`)

- Suite selection UI
- Real-time run progress via WebSocket
- Run history with expandable results
- Error detail modal integration

### API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/e2e/suites` | List test suites |
| GET | `/api/v1/e2e/suites/{id}/cases` | List cases in suite |
| POST | `/api/v1/e2e/run` | Start test run |
| GET | `/api/v1/e2e/runs` | List past runs |
| GET | `/api/v1/e2e/runs/{id}` | Get run details |
| POST | `/api/v1/e2e/runs/{id}/abort` | Abort running test |
| DELETE | `/api/v1/e2e/runs/{id}` | Delete run |

### WebSocket Events

- `e2e:run:started` - Test run began
- `e2e:test:started` - Individual test started
- `e2e:test:completed` - Individual test finished
- `e2e:run:completed` - All tests finished

---

## Spec Document

Full specification at: `spec/wp-plugin-publish/04-testing/40-e2e-test-spec.md`

---

*Tests run against real WP sites configured in the application.*
