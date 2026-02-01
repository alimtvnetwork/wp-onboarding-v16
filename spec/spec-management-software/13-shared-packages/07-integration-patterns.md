# Integration Patterns

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  

---

## Overview

This document describes how the shared packages integrate within microservices and across the SpecBuilder Pro system.

---

## Package Import Order

Always import packages in this order to respect dependencies:

```go
import (
    // Standard library
    "context"
    "fmt"
    "time"
    
    // External packages
    "github.com/spf13/viper"
    
    // Shared packages (dependency order)
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/types"
    "github.com/specbuilder/pkg/logging"
    "github.com/specbuilder/pkg/config"
    "github.com/specbuilder/pkg/database"
    
    // Internal packages
    "github.com/specbuilder/internal/specmgr/repository"
)
```

---

## Service Bootstrap Pattern

Every microservice follows this bootstrap pattern:

```go
package main

import (
    "context"
    "os"
    "os/signal"
    "syscall"
    
    "github.com/specbuilder/pkg/config"
    "github.com/specbuilder/pkg/database"
    "github.com/specbuilder/pkg/errors"
    "github.com/specbuilder/pkg/logging"
)

func main() {
    // 1. Load configuration
    cfg := config.MustLoad(config.LoadOptions{
        ConfigName: "specmgr",
        EnvPrefix:  "SPECMGR",
    })
    
    // 2. Initialize logger
    logger := logging.New(
        logging.WithLevel(cfg.Logging.Level),
        logging.WithFormat(cfg.Logging.Format),
        logging.WithService("specmgr", "1.0.0"),
    )
    logging.SetDefault(logger)
    
    logger.Info("starting service",
        "environment", cfg.Environment,
        "port", cfg.Server.Port,
    )
    
    // 3. Initialize database
    db, err := database.Open(cfg.Database.SettingsPath,
        database.WithLogger(logger),
        database.WithJournalMode(cfg.Database.JournalMode),
    )
    if err != nil {
        logger.Error("failed to open database", logging.Err(err))
        os.Exit(1)
    }
    defer db.Close()
    
    // 4. Run migrations
    migrator := database.NewMigrator(db, &database.EmbedSource{
        FS:  migrationsFS,
        Dir: "migrations",
    })
    if err := migrator.Migrate(context.Background()); err != nil {
        logger.Error("migration failed", logging.Err(err))
        os.Exit(1)
    }
    
    // 5. Initialize services
    // ...
    
    // 6. Start HTTP server
    srv := &http.Server{
        Addr:         cfg.Server.Address(),
        Handler:      handler,
        ReadTimeout:  cfg.Server.ReadTimeout,
        WriteTimeout: cfg.Server.WriteTimeout,
    }
    
    // 7. Graceful shutdown
    go func() {
        if err := srv.ListenAndServe(); err != http.ErrServerClosed {
            logger.Error("server error", logging.Err(err))
        }
    }()
    
    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
    <-quit
    
    ctx, cancel := context.WithTimeout(context.Background(), cfg.Server.ShutdownTimeout)
    defer cancel()
    
    logger.Info("shutting down")
    srv.Shutdown(ctx)
}
```

---

## Error Handling Flow

```
┌───────────────────────────────────────────────────────────────────┐
│                         Request Flow                               │
├───────────────────────────────────────────────────────────────────┤
│                                                                    │
│   HTTP Request                                                     │
│        │                                                           │
│        ▼                                                           │
│   ┌─────────────┐                                                  │
│   │  Middleware │ ──► Validation Error ──► errors.NewValidation()  │
│   └─────────────┘                                                  │
│        │                                                           │
│        ▼                                                           │
│   ┌─────────────┐                                                  │
│   │   Handler   │ ──► Business Error ──► errors.NewBusiness()      │
│   └─────────────┘                                                  │
│        │                                                           │
│        ▼                                                           │
│   ┌─────────────┐                                                  │
│   │   Service   │ ──► Domain Error ──► errors.New()                │
│   └─────────────┘                                                  │
│        │                                                           │
│        ▼                                                           │
│   ┌─────────────┐                                                  │
│   │ Repository  │ ──► DB Error ──► errors.NewDatabase()            │
│   └─────────────┘                                                  │
│        │                                                           │
│        ▼                                                           │
│   ┌─────────────┐                                                  │
│   │  Database   │ ──► sql.Error ──► wrapped by repository          │
│   └─────────────┘                                                  │
│                                                                    │
└───────────────────────────────────────────────────────────────────┘
```

### Error Propagation Example

```go
// Repository layer
func (r *SpecRepository) GetByID(ctx context.Context, id types.SpecID) (*Spec, error) {
    row := r.db.QueryRowContext(ctx, "SELECT ... WHERE ID = ?", id)
    
    var spec Spec
    if err := row.Scan(&spec.ID, &spec.Name, ...); err != nil {
        if err == sql.ErrNoRows {
            return nil, errors.NewDatabaseNotFound("Spec", id.String())
        }
        return nil, errors.NewDatabase(
            errors.ErrDatabaseQuery,
            "failed to get spec",
            map[string]any{"id": id},
        ).WithCause(err)
    }
    
    return &spec, nil
}

// Service layer
func (s *SpecService) GetSpec(ctx context.Context, id types.SpecID) (*Spec, error) {
    spec, err := s.repo.GetByID(ctx, id)
    if err != nil {
        // Log but don't wrap - error already has context
        s.logger.ErrorContext(ctx, "failed to get spec",
            logging.Err(err),
            "spec_id", id,
        )
        return nil, err
    }
    
    return spec, nil
}

// Handler layer
func (h *SpecHandler) GetSpec(w http.ResponseWriter, r *http.Request) error {
    id, err := types.ParseSpecID(chi.URLParam(r, "id"))
    if err != nil {
        return errors.NewValidationFormat("id", "valid UUID")
    }
    
    spec, err := h.service.GetSpec(r.Context(), id)
    if err != nil {
        return err // Will be handled by errors.Wrap middleware
    }
    
    return types.NewResponse(spec).Write(w)
}

// Router setup
r.Get("/specs/{id}", errors.Wrap(handler.GetSpec))
```

---

## Logging Context Propagation

```go
func HandleCreateSpec(w http.ResponseWriter, r *http.Request) error {
    ctx := r.Context()
    
    // Context already has request_id from middleware
    logger := logging.FromContext(ctx, h.logger)
    
    // Parse request
    var req CreateSpecRequest
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        logger.Warn("invalid request body", logging.Err(err))
        return errors.NewValidation(errors.ErrValidationFormat, "invalid JSON", nil)
    }
    
    // Add business context
    ctx = logging.WithFields(ctx,
        "project_id", req.ProjectID,
        "spec_name", req.Name,
    )
    
    // Create spec
    spec, err := h.service.CreateSpec(ctx, req)
    if err != nil {
        logger.ErrorContext(ctx, "failed to create spec", logging.Err(err))
        return err
    }
    
    logger.InfoContext(ctx, "spec created", "spec_id", spec.ID)
    
    return types.NewCreatedResponse(spec.ID, "Spec created").WriteWithStatus(w, 201)
}
```

---

## Configuration Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                     Configuration Sources                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   Priority (highest to lowest):                                  │
│                                                                  │
│   1. Environment Variables (SPEC_*)                              │
│      └── Overrides everything                                    │
│                                                                  │
│   2. Config File (config.yaml)                                   │
│      └── Service-specific settings                               │
│                                                                  │
│   3. Default Values (pkg/config/defaults.go)                     │
│      └── Sensible defaults for all settings                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Environment Variable Mapping

```bash
# Server
SPEC_SERVER_PORT=8080
SPEC_SERVER_READ_TIMEOUT=30s

# Database  
SPEC_DATABASE_SETTINGS_PATH=/data/settings.db
SPEC_DATABASE_JOURNAL_MODE=WAL

# Logging
SPEC_LOGGING_LEVEL=debug
SPEC_LOGGING_FORMAT=json

# AI (secrets)
SPEC_AI_API_KEY=sk-xxx
```

---

## Database Access Patterns

### Multi-Database Pattern

```go
type DatabaseManager struct {
    settings *database.DB  // Global settings
    projects *database.DB  // Project index
    logger   logging.Logger
}

func NewDatabaseManager(cfg config.DatabaseConfig, logger logging.Logger) (*DatabaseManager, error) {
    settings, err := database.Open(cfg.SettingsPath,
        database.WithLogger(logger.With("db", "settings")),
    )
    if err != nil {
        return nil, err
    }
    
    projects, err := database.Open(cfg.ProjectsPath,
        database.WithLogger(logger.With("db", "projects")),
    )
    if err != nil {
        settings.Close()
        return nil, err
    }
    
    return &DatabaseManager{
        settings: settings,
        projects: projects,
        logger:   logger,
    }, nil
}

// GetProjectDB opens the project-specific database
func (m *DatabaseManager) GetProjectDB(projectID types.ProjectID) (*database.DB, error) {
    path := filepath.Join(m.projectDataDir, projectID.String(), "project.db")
    return database.Open(path,
        database.WithLogger(m.logger.With("db", "project", "project_id", projectID)),
    )
}

// GetConversationDB opens a conversation-specific database
func (m *DatabaseManager) GetConversationDB(convID types.ConversationID) (*database.DB, error) {
    path := filepath.Join(m.convDataDir, convID.String()+".db")
    return database.Open(path,
        database.WithLogger(m.logger.With("db", "conversation", "conv_id", convID)),
    )
}

func (m *DatabaseManager) Close() error {
    var errs []error
    if err := m.settings.Close(); err != nil {
        errs = append(errs, err)
    }
    if err := m.projects.Close(); err != nil {
        errs = append(errs, err)
    }
    // Return first error or nil
    if len(errs) > 0 {
        return errs[0]
    }
    return nil
}
```

---

## Health Check Integration

```go
type HealthChecker struct {
    db     *database.DB
    logger logging.Logger
}

func (h *HealthChecker) Check(ctx context.Context) map[string]any {
    health := h.db.HealthCheck(ctx)
    
    return map[string]any{
        "status":  health.Status,
        "latency": health.Latency.String(),
        "database": map[string]any{
            "open_connections": health.OpenConns,
            "in_use":           health.InUse,
            "idle":             health.Idle,
        },
    }
}

// HTTP endpoint
func (h *HealthHandler) HealthCheck(w http.ResponseWriter, r *http.Request) {
    health := h.checker.Check(r.Context())
    
    status := http.StatusOK
    if health["status"] == database.HealthStatusDown {
        status = http.StatusServiceUnavailable
    }
    
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(status)
    json.NewEncoder(w).Encode(health)
}
```

---

## Testing Patterns

### Unit Test with Mocks

```go
func TestSpecService_CreateSpec(t *testing.T) {
    // Create mock repository
    repo := &MockSpecRepository{
        CreateFunc: func(ctx context.Context, spec *Spec) error {
            return nil
        },
    }
    
    // Create service with noop logger
    svc := NewSpecService(repo, logging.NewNoop())
    
    // Test
    spec, err := svc.CreateSpec(context.Background(), CreateSpecRequest{
        Name: "Test Spec",
    })
    
    assert.NoError(t, err)
    assert.NotEmpty(t, spec.ID)
}
```

### Integration Test with Real Database

```go
func TestSpecRepository_Integration(t *testing.T) {
    if testing.Short() {
        t.Skip("skipping integration test")
    }
    
    // Create temp database
    db, err := database.Open(":memory:")
    require.NoError(t, err)
    defer db.Close()
    
    // Run migrations
    migrator := database.NewMigrator(db, testMigrations)
    require.NoError(t, migrator.Migrate(context.Background()))
    
    // Create repository
    repo := NewSpecRepository(db, logging.NewNoop())
    
    // Test CRUD operations
    ctx := context.Background()
    
    // Create
    spec := &Spec{ID: types.NewSpecID(), Name: "Test"}
    err = repo.Create(ctx, spec)
    require.NoError(t, err)
    
    // Read
    found, err := repo.GetByID(ctx, spec.ID)
    require.NoError(t, err)
    assert.Equal(t, spec.Name, found.Name)
    
    // Update
    spec.Name = "Updated"
    err = repo.Update(ctx, spec)
    require.NoError(t, err)
    
    // Delete
    err = repo.Delete(ctx, spec.ID)
    require.NoError(t, err)
    
    // Verify deleted
    _, err = repo.GetByID(ctx, spec.ID)
    assert.True(t, errors.Is(err, sql.ErrNoRows))
}
```

---

## Related Specifications

- [pkg/errors](./02-pkg-errors.md)
- [pkg/types](./03-pkg-types.md)
- [pkg/logging](./04-pkg-logging.md)
- [pkg/config](./05-pkg-config.md)
- [pkg/database](./06-pkg-database.md)
