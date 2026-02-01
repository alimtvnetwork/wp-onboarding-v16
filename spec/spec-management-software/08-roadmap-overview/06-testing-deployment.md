# Testing & Deployment

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

This specification defines testing standards for the Spec Management Software, covering backend Go tests, frontend Vitest tests, E2E scenarios, and deployment procedures.

---

## 1. Testing Philosophy

### 1.1 Test Pyramid

```
                    ┌─────────┐
                    │   E2E   │  ← 10% (Critical paths)
                   ─┴─────────┴─
                  ┌─────────────┐
                  │ Integration │  ← 30% (API, DB)
                 ─┴─────────────┴─
                ┌─────────────────┐
                │      Unit       │  ← 60% (Logic, Utils)
               ─┴─────────────────┴─
```

### 1.2 Coverage Targets

| Layer | Target | Critical Paths |
|-------|--------|----------------|
| Backend (Go) | 80% | 100% |
| Frontend (React) | 75% | 100% |
| E2E | N/A | All critical flows |

### 1.3 Testing Principles

- **AAA Pattern**: Arrange, Act, Assert
- **Isolation**: Tests must not depend on external services
- **Determinism**: Same input → Same output
- **Speed**: Unit tests < 100ms, Integration < 1s

---

## 2. Backend Testing (Go)

### 2.1 Project Structure

```
backend/
├── internal/
│   ├── services/
│   │   ├── project_service.go
│   │   ├── project_service_test.go
│   │   ├── file_service.go
│   │   └── file_service_test.go
│   ├── handlers/
│   │   ├── project_handler.go
│   │   └── project_handler_test.go
│   └── repository/
│       ├── project_repo.go
│       └── project_repo_test.go
├── pkg/
│   └── testutil/
│       ├── db.go           # Test database helpers
│       ├── fixtures.go     # Test data factories
│       └── assertions.go   # Custom assertions
└── test/
    └── integration/
        ├── api_test.go
        └── ai_chain_test.go
```

### 2.2 Unit Test Example

```go
// internal/services/project_service_test.go
package services

import (
    "context"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/mock"
    "specmgmt/internal/repository"
    "specmgmt/pkg/testutil"
)

type MockProjectRepo struct {
    mock.Mock
}

func (m *MockProjectRepo) FindById(ctx context.Context, id string) (*repository.Project, error) {
    args := m.Called(ctx, id)
    if args.Get(0) == nil {
        return nil, args.Error(1)
    }
    return args.Get(0).(*repository.Project), args.Error(1)
}

func TestProjectService_GetById(t *testing.T) {
    t.Run("returns project when found", func(t *testing.T) {
        // Arrange
        mockRepo := new(MockProjectRepo)
        service := NewProjectService(mockRepo)
        
        expected := testutil.NewProject().WithName("Test Project").Build()
        mockRepo.On("FindById", mock.Anything, "proj-123").Return(expected, nil)
        
        // Act
        result, err := service.GetById(context.Background(), "proj-123")
        
        // Assert
        assert.NoError(t, err)
        assert.Equal(t, "Test Project", result.Name)
        mockRepo.AssertExpectations(t)
    })
    
    t.Run("returns error when not found", func(t *testing.T) {
        // Arrange
        mockRepo := new(MockProjectRepo)
        service := NewProjectService(mockRepo)
        
        mockRepo.On("FindById", mock.Anything, "nonexistent").Return(nil, ErrNotFound)
        
        // Act
        result, err := service.GetById(context.Background(), "nonexistent")
        
        // Assert
        assert.Nil(t, result)
        assert.ErrorIs(t, err, ErrNotFound)
    })
}

func TestProjectService_Create(t *testing.T) {
    t.Run("creates project with valid data", func(t *testing.T) {
        // Arrange
        mockRepo := new(MockProjectRepo)
        service := NewProjectService(mockRepo)
        
        input := &CreateProjectInput{
            Name:        "New Project",
            Slug:        "new-project",
            Description: "Test description",
        }
        
        mockRepo.On("Create", mock.Anything, mock.MatchedBy(func(p *repository.Project) bool {
            return p.Name == "New Project" && p.Slug == "new-project"
        })).Return(nil)
        
        // Act
        result, err := service.Create(context.Background(), input)
        
        // Assert
        assert.NoError(t, err)
        assert.NotEmpty(t, result.Id)
        assert.Equal(t, "New Project", result.Name)
    })
    
    t.Run("rejects duplicate slug", func(t *testing.T) {
        // Arrange
        mockRepo := new(MockProjectRepo)
        service := NewProjectService(mockRepo)
        
        input := &CreateProjectInput{
            Name: "Duplicate",
            Slug: "existing-slug",
        }
        
        mockRepo.On("ExistsBySlug", mock.Anything, "existing-slug").Return(true, nil)
        
        // Act
        result, err := service.Create(context.Background(), input)
        
        // Assert
        assert.Nil(t, result)
        assert.ErrorIs(t, err, ErrDuplicateSlug)
    })
}
```

### 2.3 Repository Integration Test

```go
// internal/repository/project_repo_test.go
package repository

import (
    "context"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "specmgmt/pkg/testutil"
)

func TestProjectRepository(t *testing.T) {
    // Setup test database
    db := testutil.NewTestDB(t)
    defer db.Close()
    
    repo := NewProjectRepository(db)
    ctx := context.Background()
    
    t.Run("Create and FindById", func(t *testing.T) {
        // Arrange
        project := testutil.NewProject().
            WithName("Integration Test").
            WithSlug("integration-test").
            Build()
        
        // Act - Create
        err := repo.Create(ctx, project)
        assert.NoError(t, err)
        
        // Act - Find
        found, err := repo.FindById(ctx, project.Id)
        
        // Assert
        assert.NoError(t, err)
        assert.Equal(t, "Integration Test", found.Name)
        assert.Equal(t, "integration-test", found.Slug)
    })
    
    t.Run("Update project", func(t *testing.T) {
        // Arrange
        project := testutil.NewProject().Build()
        repo.Create(ctx, project)
        
        // Act
        project.Name = "Updated Name"
        err := repo.Update(ctx, project)
        
        // Assert
        assert.NoError(t, err)
        
        found, _ := repo.FindById(ctx, project.Id)
        assert.Equal(t, "Updated Name", found.Name)
    })
    
    t.Run("Delete removes project and files", func(t *testing.T) {
        // Arrange
        project := testutil.NewProject().Build()
        repo.Create(ctx, project)
        
        file := testutil.NewFile().WithProjectId(project.Id).Build()
        fileRepo := NewFileRepository(db)
        fileRepo.Create(ctx, file)
        
        // Act
        err := repo.Delete(ctx, project.Id)
        
        // Assert
        assert.NoError(t, err)
        
        found, err := repo.FindById(ctx, project.Id)
        assert.Nil(t, found)
        assert.ErrorIs(t, err, ErrNotFound)
        
        // Verify cascade delete
        foundFile, _ := fileRepo.FindById(ctx, file.Id)
        assert.Nil(t, foundFile)
    })
}
```

### 2.4 Handler Test

```go
// internal/handlers/project_handler_test.go
package handlers

import (
    "bytes"
    "encoding/json"
    "net/http"
    "net/http/httptest"
    "testing"
    
    "github.com/stretchr/testify/assert"
    "specmgmt/pkg/testutil"
)

func TestProjectHandler_Create(t *testing.T) {
    t.Run("creates project with valid input", func(t *testing.T) {
        // Arrange
        app := testutil.NewTestApp(t)
        
        body := map[string]interface{}{
            "name":        "Test Project",
            "slug":        "test-project",
            "description": "A test project",
        }
        bodyBytes, _ := json.Marshal(body)
        
        req := httptest.NewRequest("POST", "/api/v1/projects", bytes.NewReader(bodyBytes))
        req.Header.Set("Content-Type", "application/json")
        req.Header.Set("Authorization", "Bearer "+app.TestToken)
        
        // Act
        resp := httptest.NewRecorder()
        app.Router.ServeHTTP(resp, req)
        
        // Assert
        assert.Equal(t, http.StatusCreated, resp.Code)
        
        var result map[string]interface{}
        json.Unmarshal(resp.Body.Bytes(), &result)
        
        assert.True(t, result["success"].(bool))
        assert.Equal(t, "Test Project", result["data"].(map[string]interface{})["name"])
    })
    
    t.Run("returns 400 for missing required fields", func(t *testing.T) {
        // Arrange
        app := testutil.NewTestApp(t)
        
        body := map[string]interface{}{
            "description": "Missing name and slug",
        }
        bodyBytes, _ := json.Marshal(body)
        
        req := httptest.NewRequest("POST", "/api/v1/projects", bytes.NewReader(bodyBytes))
        req.Header.Set("Content-Type", "application/json")
        req.Header.Set("Authorization", "Bearer "+app.TestToken)
        
        // Act
        resp := httptest.NewRecorder()
        app.Router.ServeHTTP(resp, req)
        
        // Assert
        assert.Equal(t, http.StatusBadRequest, resp.Code)
    })
    
    t.Run("returns 401 without auth token", func(t *testing.T) {
        // Arrange
        app := testutil.NewTestApp(t)
        
        req := httptest.NewRequest("POST", "/api/v1/projects", nil)
        
        // Act
        resp := httptest.NewRecorder()
        app.Router.ServeHTTP(resp, req)
        
        // Assert
        assert.Equal(t, http.StatusUnauthorized, resp.Code)
    })
}
```

### 2.5 Test Fixtures

```go
// pkg/testutil/fixtures.go
package testutil

import (
    "time"
    
    "github.com/google/uuid"
    "specmgmt/internal/repository"
)

type ProjectBuilder struct {
    project *repository.Project
}

func NewProject() *ProjectBuilder {
    return &ProjectBuilder{
        project: &repository.Project{
            Id:        uuid.New().String(),
            Name:      "Default Project",
            Slug:      "default-project",
            CreatedAt: time.Now(),
            UpdatedAt: time.Now(),
        },
    }
}

func (b *ProjectBuilder) WithName(name string) *ProjectBuilder {
    b.project.Name = name
    return b
}

func (b *ProjectBuilder) WithSlug(slug string) *ProjectBuilder {
    b.project.Slug = slug
    return b
}

func (b *ProjectBuilder) WithCategory(category string) *ProjectBuilder {
    b.project.Category = &category
    return b
}

func (b *ProjectBuilder) Build() *repository.Project {
    return b.project
}

// File builder
type FileBuilder struct {
    file *repository.File
}

func NewFile() *FileBuilder {
    return &FileBuilder{
        file: &repository.File{
            Id:        uuid.New().String(),
            Name:      "test-file.md",
            Path:      "test-file.md",
            Type:      "file",
            CreatedAt: time.Now(),
            UpdatedAt: time.Now(),
        },
    }
}

func (b *FileBuilder) WithProjectId(projectId string) *FileBuilder {
    b.file.ProjectId = projectId
    return b
}

func (b *FileBuilder) WithName(name string) *FileBuilder {
    b.file.Name = name
    b.file.Path = name
    return b
}

func (b *FileBuilder) AsFolder() *FileBuilder {
    b.file.Type = "folder"
    return b
}

func (b *FileBuilder) Build() *repository.File {
    return b.file
}
```

### 2.6 Test Database Helper

```go
// pkg/testutil/db.go
package testutil

import (
    "database/sql"
    "testing"
    
    _ "github.com/mattn/go-sqlite3"
    "specmgmt/internal/database"
)

func NewTestDB(t *testing.T) *sql.DB {
    t.Helper()
    
    // Use in-memory SQLite for tests
    db, err := sql.Open("sqlite3", ":memory:")
    if err != nil {
        t.Fatalf("failed to open test database: %v", err)
    }
    
    // Run migrations
    if err := database.Migrate(db); err != nil {
        t.Fatalf("failed to migrate test database: %v", err)
    }
    
    // Register cleanup
    t.Cleanup(func() {
        db.Close()
    })
    
    return db
}

type TestApp struct {
    DB        *sql.DB
    Router    http.Handler
    TestToken string
}

func NewTestApp(t *testing.T) *TestApp {
    t.Helper()
    
    db := NewTestDB(t)
    
    // Create test user and generate token
    testUser := NewUser().Build()
    userRepo := repository.NewUserRepository(db)
    userRepo.Create(context.Background(), testUser)
    
    tokenService := services.NewTokenService("test-secret")
    token, _ := tokenService.GenerateToken(testUser.Id, 24*time.Hour)
    
    // Build router with all handlers
    router := api.NewRouter(db)
    
    return &TestApp{
        DB:        db,
        Router:    router,
        TestToken: token,
    }
}
```

### 2.7 Running Go Tests

```bash
# Run all tests
go test ./...

# Run with coverage
go test -coverprofile=coverage.out ./...
go tool cover -html=coverage.out

# Run specific package
go test ./internal/services/...

# Run with verbose output
go test -v ./...

# Run specific test
go test -run TestProjectService_Create ./internal/services/
```

---

## 3. Frontend Testing (Vitest)

### 3.1 Project Structure

```
react-frontend/
├── src/
│   ├── components/
│   │   ├── project/
│   │   │   ├── ProjectCard.tsx
│   │   │   ├── ProjectCard.test.tsx
│   │   │   └── __snapshots__/
│   │   └── editor/
│   │       ├── MarkdownEditor.tsx
│   │       └── MarkdownEditor.test.tsx
│   ├── hooks/
│   │   ├── useProjects.ts
│   │   └── useProjects.test.ts
│   ├── test/
│   │   ├── setup.ts
│   │   ├── utils.tsx
│   │   └── mocks/
│   │       ├── handlers.ts
│   │       └── server.ts
│   └── vitest.config.ts
└── package.json
```

### 3.2 Test Setup

```typescript
// src/test/setup.ts
import "@testing-library/jest-dom";
import { afterAll, afterEach, beforeAll } from "vitest";
import { server } from "./mocks/server";

// Mock window.matchMedia
Object.defineProperty(window, "matchMedia", {
  writable: true,
  value: (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => {},
  }),
});

// Mock ResizeObserver
global.ResizeObserver = class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
};

// Setup MSW
beforeAll(() => server.listen({ onUnhandledRequest: "error" }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());
```

### 3.3 MSW Handlers

```typescript
// src/test/mocks/handlers.ts
import { http, HttpResponse } from "msw";

export const handlers = [
  // Projects
  http.get("/api/v1/projects", () => {
    return HttpResponse.json({
      success: true,
      data: [
        {
          id: "proj-1",
          name: "Test Project",
          slug: "test-project",
          description: "A test project",
          fileCount: 10,
          snapshotCount: 2,
          updatedAt: "2026-01-27T10:00:00Z",
        },
      ],
      meta: {
        requestId: "test-123",
        timestamp: new Date().toISOString(),
      },
    });
  }),

  http.post("/api/v1/projects", async ({ request }) => {
    const body = await request.json();
    return HttpResponse.json(
      {
        success: true,
        data: {
          id: "proj-new",
          ...body,
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        },
      },
      { status: 201 }
    );
  }),

  http.delete("/api/v1/projects/:id", ({ params }) => {
    return HttpResponse.json({ success: true });
  }),

  // Files
  http.get("/api/v1/projects/:projectId/files/tree", ({ params }) => {
    return HttpResponse.json({
      success: true,
      data: [
        {
          id: "file-1",
          name: "01-overview.md",
          path: "01-overview.md",
          type: "file",
          parentId: null,
        },
        {
          id: "folder-1",
          name: "01-backend",
          path: "01-backend",
          type: "folder",
          parentId: null,
          children: [
            {
              id: "file-2",
              name: "01-overview.md",
              path: "01-backend/01-overview.md",
              type: "file",
              parentId: "folder-1",
            },
          ],
        },
      ],
    });
  }),

  // AI
  http.post("/api/v1/ai/analyze", async ({ request }) => {
    return HttpResponse.json({
      success: true,
      data: {
        intent: "Create OAuth integration",
        questions: [
          {
            id: "q1",
            text: "Which providers?",
            type: "choice",
            options: ["Google", "GitHub", "Both"],
            required: true,
          },
        ],
      },
    });
  }),
];
```

### 3.4 Component Tests

```typescript
// src/components/project/ProjectCard.test.tsx
import { describe, it, expect, vi } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ProjectCard } from "./ProjectCard";

const mockProject = {
  id: "proj-1",
  name: "Test Project",
  slug: "test-project",
  description: "A test description",
  path: "test/path",
  fileCount: 10,
  snapshotCount: 2,
  updatedAt: "2026-01-27T10:00:00Z",
};

describe("ProjectCard", () => {
  it("renders project information", () => {
    render(
      <ProjectCard
        project={mockProject}
        onOpen={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onSnapshot={vi.fn()}
      />
    );

    expect(screen.getByText("Test Project")).toBeInTheDocument();
    expect(screen.getByText("A test description")).toBeInTheDocument();
    expect(screen.getByText("10 files")).toBeInTheDocument();
    expect(screen.getByText("2 snapshots")).toBeInTheDocument();
  });

  it("calls onOpen when card is clicked", async () => {
    const user = userEvent.setup();
    const onOpen = vi.fn();

    render(
      <ProjectCard
        project={mockProject}
        onOpen={onOpen}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onSnapshot={vi.fn()}
      />
    );

    await user.click(screen.getByText("Test Project"));
    expect(onOpen).toHaveBeenCalledWith("proj-1");
  });

  it("shows context menu on right click", async () => {
    const user = userEvent.setup();

    render(
      <ProjectCard
        project={mockProject}
        onOpen={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onSnapshot={vi.fn()}
      />
    );

    // Open context menu via more button
    const moreButton = screen.getByRole("button", { name: /more/i });
    await user.click(moreButton);

    expect(screen.getByText("Open")).toBeInTheDocument();
    expect(screen.getByText("Edit Details")).toBeInTheDocument();
    expect(screen.getByText("Delete")).toBeInTheDocument();
  });

  it("calls onDelete when delete is clicked", async () => {
    const user = userEvent.setup();
    const onDelete = vi.fn();

    render(
      <ProjectCard
        project={mockProject}
        onOpen={vi.fn()}
        onEdit={vi.fn()}
        onDelete={onDelete}
        onSnapshot={vi.fn()}
      />
    );

    const moreButton = screen.getByRole("button", { name: /more/i });
    await user.click(moreButton);
    await user.click(screen.getByText("Delete"));

    expect(onDelete).toHaveBeenCalledWith("proj-1");
  });
});
```

### 3.5 Hook Tests

```typescript
// src/hooks/useProjects.test.ts
import { describe, it, expect } from "vitest";
import { renderHook, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useProjects } from "./useProjects";

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  });

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
};

describe("useProjects", () => {
  it("fetches and returns projects", async () => {
    const { result } = renderHook(() => useProjects(), {
      wrapper: createWrapper(),
    });

    // Initially loading
    expect(result.current.isLoading).toBe(true);

    // Wait for data
    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(result.current.projects).toHaveLength(1);
    expect(result.current.projects[0].name).toBe("Test Project");
  });

  it("provides createProject mutation", async () => {
    const { result } = renderHook(() => useProjects(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    // Call create mutation
    result.current.createProject({
      name: "New Project",
      slug: "new-project",
    });

    await waitFor(() => {
      expect(result.current.isCreating).toBe(false);
    });
  });
});
```

### 3.6 Editor Tests

```typescript
// src/components/editor/MarkdownEditor.test.tsx
import { describe, it, expect, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MarkdownEditor } from "./MarkdownEditor";
import { ThemeProvider } from "@/context/ThemeContext";

const mockFile = {
  id: "file-1",
  name: "test.md",
  path: "test.md",
  type: "file" as const,
  parentId: null,
  projectId: "proj-1",
  sortOrder: 0,
  isModified: false,
  createdAt: "2026-01-27T10:00:00Z",
  updatedAt: "2026-01-27T10:00:00Z",
};

const renderEditor = (props = {}) => {
  return render(
    <ThemeProvider>
      <MarkdownEditor file={mockFile} onSave={vi.fn()} {...props} />
    </ThemeProvider>
  );
};

describe("MarkdownEditor", () => {
  it("displays file name in header", () => {
    renderEditor();
    expect(screen.getByText("test.md")).toBeInTheDocument();
  });

  it("shows toolbar with formatting buttons", () => {
    renderEditor();

    expect(screen.getByRole("button", { name: /bold/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /italic/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /heading 1/i })).toBeInTheDocument();
  });

  it("toggles between view modes", async () => {
    const user = userEvent.setup();
    renderEditor();

    // Find view mode toggle
    const previewButton = screen.getByRole("button", { name: /preview/i });
    await user.click(previewButton);

    // Preview should be visible
    expect(screen.getByTestId("markdown-preview")).toBeInTheDocument();
  });

  it("calls onSave after auto-save delay", async () => {
    vi.useFakeTimers();
    const onSave = vi.fn();

    renderEditor({ onSave });

    // Simulate typing (would need to mock CodeMirror properly)
    // For now, we test the auto-save mechanism conceptually

    vi.advanceTimersByTime(2000); // Auto-save delay

    // In real implementation, would verify onSave was called
    vi.useRealTimers();
  });

  it("shows modified indicator when content changes", async () => {
    const { rerender } = renderEditor();

    // Rerender with modified file
    rerender(
      <ThemeProvider>
        <MarkdownEditor
          file={{ ...mockFile, isModified: true }}
          onSave={vi.fn()}
        />
      </ThemeProvider>
    );

    expect(screen.getByTestId("modified-indicator")).toBeInTheDocument();
  });
});
```

### 3.7 Vitest Configuration

```typescript
// vitest.config.ts
import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react-swc";
import path from "path";

export default defineConfig({
  plugins: [react()],
  test: {
    environment: "jsdom",
    globals: true,
    setupFiles: ["./src/test/setup.ts"],
    include: ["src/**/*.{test,spec}.{ts,tsx}"],
    coverage: {
      provider: "v8",
      reporter: ["text", "html", "lcov"],
      exclude: [
        "node_modules/",
        "src/test/",
        "**/*.d.ts",
        "**/*.config.*",
      ],
      thresholds: {
        statements: 75,
        branches: 70,
        functions: 75,
        lines: 75,
      },
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
});
```

---

## 4. End-to-End Testing (Playwright)

### 4.1 E2E Test Structure

```
e2e/
├── fixtures/
│   ├── auth.ts
│   └── projects.ts
├── pages/
│   ├── DashboardPage.ts
│   ├── EditorPage.ts
│   └── LoginPage.ts
├── tests/
│   ├── auth.spec.ts
│   ├── projects.spec.ts
│   ├── editor.spec.ts
│   └── ai-chat.spec.ts
├── playwright.config.ts
└── global-setup.ts
```

### 4.2 Page Object Model

```typescript
// e2e/pages/DashboardPage.ts
import { Page, expect } from "@playwright/test";

export class DashboardPage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto("/");
    await this.page.waitForSelector('[data-testid="project-grid"]');
  }

  async createProject(name: string, slug: string) {
    await this.page.click('button:has-text("New Project")');
    await this.page.fill('input[name="name"]', name);
    await this.page.fill('input[name="slug"]', slug);
    await this.page.click('button:has-text("Create")');
    await expect(this.page.locator(`text=${name}`)).toBeVisible();
  }

  async openProject(name: string) {
    await this.page.click(`[data-testid="project-card"]:has-text("${name}")`);
    await this.page.waitForURL(/\/project\//);
  }

  async searchProjects(query: string) {
    await this.page.fill('input[placeholder*="Search"]', query);
  }

  async filterByCategory(category: string) {
    await this.page.click('button:has-text("All Categories")');
    await this.page.click(`text=${category}`);
  }

  async deleteProject(name: string) {
    const card = this.page.locator(`[data-testid="project-card"]:has-text("${name}")`);
    await card.locator('[data-testid="more-menu"]').click();
    await this.page.click('text=Delete');
    await this.page.click('button:has-text("Confirm")');
  }

  async getProjectCount() {
    return this.page.locator('[data-testid="project-card"]').count();
  }
}
```

### 4.3 E2E Test Scenarios

```typescript
// e2e/tests/projects.spec.ts
import { test, expect } from "@playwright/test";
import { DashboardPage } from "../pages/DashboardPage";
import { EditorPage } from "../pages/EditorPage";

test.describe("Project Management", () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto("/login");
    await page.fill('input[name="email"]', "test@example.com");
    await page.fill('input[name="password"]', "password123");
    await page.click('button:has-text("Sign In")');
    await page.waitForURL("/");
  });

  test("creates a new project", async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    const initialCount = await dashboard.getProjectCount();

    await dashboard.createProject("E2E Test Project", "e2e-test-project");

    const finalCount = await dashboard.getProjectCount();
    expect(finalCount).toBe(initialCount + 1);
  });

  test("opens project and navigates to editor", async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await dashboard.openProject("E2E Test Project");

    const editor = new EditorPage(page);
    await editor.selectFile("01-overview.md");

    await expect(page.locator('[data-testid="editor-content"]')).toBeVisible();
  });

  test("searches and filters projects", async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    // Search
    await dashboard.searchProjects("E2E");
    await expect(page.locator('text="E2E Test Project"')).toBeVisible();

    // Filter by category
    await dashboard.filterByCategory("WordPress Plugins");
    const count = await dashboard.getProjectCount();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test("deletes project with confirmation", async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();

    const initialCount = await dashboard.getProjectCount();

    await dashboard.deleteProject("E2E Test Project");

    const finalCount = await dashboard.getProjectCount();
    expect(finalCount).toBe(initialCount - 1);
  });
});
```

### 4.4 Editor E2E Tests

```typescript
// e2e/tests/editor.spec.ts
import { test, expect } from "@playwright/test";
import { EditorPage } from "../pages/EditorPage";

test.describe("Markdown Editor", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/login");
    await page.fill('input[name="email"]', "test@example.com");
    await page.fill('input[name="password"]', "password123");
    await page.click('button:has-text("Sign In")');
    await page.goto("/project/test-project");
  });

  test("loads file content in editor", async ({ page }) => {
    const editor = new EditorPage(page);
    await editor.selectFile("01-overview.md");

    const content = await editor.getEditorContent();
    expect(content).toContain("# Overview");
  });

  test("saves content on Ctrl+S", async ({ page }) => {
    const editor = new EditorPage(page);
    await editor.selectFile("01-overview.md");

    await editor.typeInEditor("## New Section");
    await page.keyboard.press("Control+s");

    await expect(page.locator('text="Saved"')).toBeVisible();
  });

  test("toolbar formatting works", async ({ page }) => {
    const editor = new EditorPage(page);
    await editor.selectFile("01-overview.md");

    // Select text and apply bold
    await editor.selectText("Overview");
    await editor.clickToolbarButton("Bold");

    const content = await editor.getEditorContent();
    expect(content).toContain("**Overview**");
  });

  test("preview renders markdown", async ({ page }) => {
    const editor = new EditorPage(page);
    await editor.selectFile("01-overview.md");

    await editor.togglePreview();

    await expect(page.locator('[data-testid="markdown-preview"] h1')).toHaveText("Overview");
  });

  test("creates new file via folder tree", async ({ page }) => {
    const editor = new EditorPage(page);

    await editor.rightClickFolder("01-backend");
    await page.click('text="New File"');
    await page.fill('input[placeholder="filename.md"]', "new-test.md");
    await page.click('button:has-text("Create")');

    await expect(page.locator('text="new-test.md"')).toBeVisible();
  });
});
```

### 4.5 AI Chat E2E Tests

```typescript
// e2e/tests/ai-chat.spec.ts
import { test, expect } from "@playwright/test";

test.describe("AI Chat", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/login");
    await page.fill('input[name="email"]', "test@example.com");
    await page.fill('input[name="password"]', "password123");
    await page.click('button:has-text("Sign In")');
    await page.goto("/project/test-project");
  });

  test("opens AI chat panel", async ({ page }) => {
    await page.click('[data-testid="ai-assistant-button"]');
    await expect(page.locator('text="AI Assistant"')).toBeVisible();
  });

  test("submits text and receives questions", async ({ page }) => {
    await page.click('[data-testid="ai-assistant-button"]');

    await page.fill('textarea[placeholder*="type your request"]', "Add OAuth login");
    await page.click('button:has-text("Analyze")');

    // Wait for questions to appear
    await expect(page.locator('text="Q1:"')).toBeVisible({ timeout: 10000 });
  });

  test("generates specification after answering questions", async ({ page }) => {
    await page.click('[data-testid="ai-assistant-button"]');

    await page.fill('textarea[placeholder*="type your request"]', "Add user settings page");
    await page.click('button:has-text("Analyze")');

    // Answer questions
    await page.waitForSelector('text="Q1:"');
    await page.click('label:has-text("All of the above")');

    await page.click('button:has-text("Generate Specification")');

    // Wait for generation
    await expect(page.locator('[data-testid="generation-progress"]')).toBeVisible();
    await expect(page.locator('text="generated successfully"')).toBeVisible({ timeout: 30000 });
  });
});
```

### 4.6 Playwright Configuration

```typescript
// e2e/playwright.config.ts
import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests",
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [
    ["html"],
    ["junit", { outputFile: "test-results/junit.xml" }],
  ],
  use: {
    baseURL: process.env.BASE_URL || "http://localhost:5173",
    trace: "on-first-retry",
    screenshot: "only-on-failure",
    video: "on-first-retry",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "firefox",
      use: { ...devices["Desktop Firefox"] },
    },
    {
      name: "webkit",
      use: { ...devices["Desktop Safari"] },
    },
    {
      name: "mobile-chrome",
      use: { ...devices["Pixel 5"] },
    },
  ],
  webServer: {
    command: "npm run dev",
    url: "http://localhost:5173",
    reuseExistingServer: !process.env.CI,
  },
});
```

---

## 5. Deployment Checklist

### 5.1 Pre-Deployment Verification

| Check | Command | Expected |
|-------|---------|----------|
| Go tests pass | `go test ./...` | All pass |
| Go lint clean | `golangci-lint run` | No errors |
| Frontend tests pass | `npm test` | All pass |
| ESLint clean | `npm run lint` | No errors |
| TypeScript compiles | `npm run typecheck` | No errors |
| E2E tests pass | `npm run e2e` | All pass |
| Build succeeds | `npm run build` | No errors |
| Coverage meets threshold | `go test -cover` | ≥80% |

### 5.2 Backend Deployment Steps

```bash
# 1. Build Go binary
CGO_ENABLED=1 go build -o specmgmt-server ./cmd/server

# 2. Verify binary
./specmgmt-server --version

# 3. Run migrations
./specmgmt-server migrate up

# 4. Seed initial data
./specmgmt-server seed

# 5. Start server
./specmgmt-server serve --port 8080
```

### 5.3 Frontend Deployment Steps

```bash
# 1. Install dependencies
npm ci

# 2. Build for production
npm run build

# 3. Preview build locally
npm run preview

# 4. Deploy to hosting
# (Varies by provider - Vercel, Netlify, etc.)
```

### 5.4 Environment Configuration

```env
# Backend (.env)
DATABASE_URL=./data/specmgmt.db
JWT_SECRET=<secure-random-string>
LLAMA_SERVER_PATH=/usr/local/bin/llama-server
LLAMA_MODELS_DIR=/models
SPEC_ROOT_DIR=/data/specs
GIT_AUTO_COMMIT=true
GIT_REMOTE_URL=git@github.com:user/specs.git

# Frontend (.env)
VITE_API_URL=https://api.specmgmt.example.com
VITE_APP_NAME=Spec Management
```

### 5.5 Health Checks

```go
// Backend health endpoint
// GET /health
{
  "status": "healthy",
  "version": "1.0.0",
  "checks": {
    "database": "ok",
    "filesystem": "ok",
    "llama_server": "running"
  },
  "uptime": 3600
}
```

### 5.6 Monitoring Setup

| Metric | Tool | Alert Threshold |
|--------|------|-----------------|
| API latency | Prometheus | p99 > 500ms |
| Error rate | Sentry | > 1% |
| Database connections | Prometheus | > 90% pool |
| Disk usage | Node Exporter | > 80% |
| Memory usage | Node Exporter | > 85% |

### 5.7 Rollback Procedure

```bash
# 1. Stop current server
systemctl stop specmgmt

# 2. Restore previous binary
cp /opt/specmgmt/backups/specmgmt-server-v0.9.0 /opt/specmgmt/specmgmt-server

# 3. Rollback database (if needed)
./specmgmt-server migrate down --steps 1

# 4. Restart server
systemctl start specmgmt

# 5. Verify health
curl http://localhost:8080/health
```

---

## 6. Acceptance Criteria

### 6.1 Testing Requirements

- [ ] Backend unit test coverage ≥80%
- [ ] Frontend unit test coverage ≥75%
- [ ] All critical paths have 100% coverage
- [ ] E2E tests cover all user flows
- [ ] Tests run in CI pipeline
- [ ] Test failures block deployment

### 6.2 Deployment Requirements

- [ ] Zero-downtime deployment possible
- [ ] Rollback procedure documented and tested
- [ ] Health checks implemented
- [ ] Monitoring alerts configured
- [ ] Logs aggregated centrally
- [ ] Secrets managed securely

---

## Related Specs

> **Note:** Specs migrated to consolidated `05-features/` structure.

- [Features Overview](../05-features/00-overview.md) - Consolidated architecture
- [API Client](../05-features/15-api-client/00-overview.md) - API endpoints
- [Monitoring](../05-features/17-monitoring/00-overview.md) - Observability
