# Testing Strategy

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-28  

---

## Overview

Frontend testing approach including unit tests, integration tests, and E2E tests.

**Cross-References:**
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)

---

## Components

| # | Component | Description |
|---|-----------|-------------|
| 01 | [Test Strategy](./01-test-strategy.md) | Unit, integration, and E2E testing |

---

## Test Types

| Type | Framework | Coverage Target |
|------|-----------|-----------------|
| Unit Tests | Vitest | 80% utilities/hooks |
| Component Tests | Testing Library | Key components |
| Integration Tests | Vitest + MSW | API interactions |
| E2E Tests | Playwright | Critical user flows |

---

## Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Unit Testing | Vitest for utilities and hooks | High |
| Component Testing | React Testing Library | High |
| Mock Service Worker | API mocking for tests | Medium |
| Visual Regression | Screenshot comparison | Low |

---

## Related Specs

- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)
- [API Client](../15-api-client/00-overview.md)

---

## Source Reference

Migrated from: `02-frontend/20-testing-strategy.md`
