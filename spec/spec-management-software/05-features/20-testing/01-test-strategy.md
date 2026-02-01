# 20.1 Test Strategy

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Comprehensive testing strategy covering unit tests, integration tests, and end-to-end tests for frontend components and user flows.

**Cross-References:**
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md) - Testing standards
- [API Client](../15-api-client/00-overview.md) - API mocking
- [Consistency Checker Tests](../08-consistency-checker/tests/) - Backend test examples

---

## 20.1.1 Testing Pyramid

```
         ╱╲
        ╱  ╲
       ╱ E2E ╲         ~10%  (Critical user flows)
      ╱────────╲
     ╱Integration╲     ~20%  (Component interactions)
    ╱──────────────╲
   ╱   Unit Tests    ╲ ~70%  (Functions, hooks, utils)
  ╱────────────────────╲
```

---

## 20.1.2 Testing Stack

| Tool | Purpose |
|------|---------|
| Vitest | Unit & integration tests |
| React Testing Library | Component testing |
| MSW (Mock Service Worker) | API mocking |
| Playwright | E2E testing |
| Testing Library User Event | User interaction simulation |

---

## 20.1.3 Unit Testing

### Utility Functions

```typescript
// utils/formatDate.test.ts
import { formatDate, formatRelativeTime } from './formatDate';

describe('formatDate', () => {
  it('formats date in default format', () => {
    const date = new Date('2026-01-28T10:30:00Z');
    expect(formatDate(date)).toBe('Jan 28, 2026');
  });
  
  it('formats date with custom format', () => {
    const date = new Date('2026-01-28T10:30:00Z');
    expect(formatDate(date, 'yyyy-MM-dd')).toBe('2026-01-28');
  });
});

describe('formatRelativeTime', () => {
  it('returns "just now" for recent dates', () => {
    const now = new Date();
    expect(formatRelativeTime(now)).toBe('just now');
  });
});
```

### Custom Hooks

```typescript
// hooks/useDebounce.test.ts
import { renderHook, act } from '@testing-library/react';
import { useDebounce } from './useDebounce';

describe('useDebounce', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });
  
  afterEach(() => {
    vi.useRealTimers();
  });
  
  it('debounces value changes', () => {
    const { result, rerender } = renderHook(
      ({ value }) => useDebounce(value, 500),
      { initialProps: { value: 'initial' } }
    );
    
    expect(result.current).toBe('initial');
    
    rerender({ value: 'updated' });
    expect(result.current).toBe('initial'); // Not yet updated
    
    act(() => vi.advanceTimersByTime(500));
    expect(result.current).toBe('updated');
  });
});
```

---

## 20.1.4 Component Testing

```typescript
// components/ProjectCard.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ProjectCard } from './ProjectCard';

const mockProject = {
  id: '1',
  name: 'Test Project',
  description: 'A test project',
  fileCount: 5,
  updatedAt: new Date(),
};

describe('ProjectCard', () => {
  it('renders project information', () => {
    render(<ProjectCard project={mockProject} />);
    
    expect(screen.getByText('Test Project')).toBeInTheDocument();
    expect(screen.getByText('A test project')).toBeInTheDocument();
    expect(screen.getByText('5 files')).toBeInTheDocument();
  });
  
  it('calls onOpen when clicked', async () => {
    const onOpen = vi.fn();
    render(<ProjectCard project={mockProject} onOpen={onOpen} />);
    
    await userEvent.click(screen.getByRole('article'));
    
    expect(onOpen).toHaveBeenCalledWith('1');
  });
  
  it('shows delete confirmation dialog', async () => {
    const onDelete = vi.fn();
    render(<ProjectCard project={mockProject} onDelete={onDelete} />);
    
    await userEvent.click(screen.getByRole('button', { name: /delete/i }));
    
    expect(screen.getByText('Are you sure?')).toBeInTheDocument();
  });
});
```

---

## 20.1.5 API Mocking with MSW

```typescript
// mocks/handlers.ts
import { rest } from 'msw';

export const handlers = [
  rest.get('/api/projects', (req, res, ctx) => {
    return res(
      ctx.json([
        { id: '1', name: 'Project 1' },
        { id: '2', name: 'Project 2' },
      ])
    );
  }),
  
  rest.post('/api/projects', async (req, res, ctx) => {
    const body = await req.json();
    return res(
      ctx.status(201),
      ctx.json({ id: '3', ...body })
    );
  }),
  
  rest.get('/api/projects/:id', (req, res, ctx) => {
    const { id } = req.params;
    if (id === 'not-found') {
      return res(ctx.status(404));
    }
    return res(ctx.json({ id, name: `Project ${id}` }));
  }),
];

// setupTests.ts
import { setupServer } from 'msw/node';
import { handlers } from './mocks/handlers';

export const server = setupServer(...handlers);

beforeAll(() => server.listen());
afterEach(() => server.resetHandlers());
afterAll(() => server.close());
```

---

## 20.1.6 E2E Testing

```typescript
// e2e/project-flow.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Project Management', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL('/');
  });
  
  test('creates a new project', async ({ page }) => {
    await page.click('text=New Project');
    await page.fill('[name="name"]', 'E2E Test Project');
    await page.fill('[name="description"]', 'Created by E2E test');
    await page.click('button:text("Create")');
    
    await expect(page.locator('text=E2E Test Project')).toBeVisible();
  });
  
  test('edits a spec file', async ({ page }) => {
    await page.click('text=E2E Test Project');
    await page.click('text=00-overview.md');
    
    const editor = page.locator('.cm-editor');
    await editor.fill('# Updated Title\n\nNew content');
    await page.click('button:text("Save")');
    
    await expect(page.locator('text=Saved')).toBeVisible();
  });
});
```

---

## 20.1.7 Test Coverage Targets

| Category | Target | Minimum |
|----------|--------|---------|
| Utilities | 95% | 90% |
| Hooks | 90% | 80% |
| Components | 80% | 70% |
| Pages | 70% | 60% |
| E2E flows | 100% critical | 80% |

---

## Related Specs

- [Coding Guidelines](../../04-coding-guidelines/00-overview.md)
- [AI Testing Strategy](../06-ai-integration/11-ai-testing.md)
- [Consistency Checker Tests](../08-consistency-checker/tests/)
- [Knowledge Memory Tests](../09-knowledge-memory/tests/)
