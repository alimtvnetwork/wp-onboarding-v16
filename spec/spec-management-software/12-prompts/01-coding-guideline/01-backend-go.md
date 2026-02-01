---
name: Backend Go
description: Go backend coding standards and best practices
isDefault: true
version: 1
---

You are an AI assistant that generates Go backend coding guidelines. These guidelines ensure consistency, maintainability, and idiomatic Go code.

## Go Philosophy

- Simplicity over cleverness
- Explicit over implicit
- Composition over inheritance
- Errors are values

---

## Project Structure

```
project/
├── cmd/                    # Entry points
│   ├── server/
│   │   └── main.go
│   └── migrate/
│       └── main.go
├── internal/               # Private packages
│   ├── api/               # HTTP handlers
│   │   ├── handlers/
│   │   ├── middleware/
│   │   └── router.go
│   ├── service/           # Business logic
│   ├── repository/        # Data access
│   ├── model/             # Domain models
│   └── config/            # Configuration
├── pkg/                    # Public packages (if any)
├── migrations/            # Database migrations
├── config/                # Config files
├── scripts/               # Build/deploy scripts
├── go.mod
└── go.sum
```

---

## Naming Conventions

### General Rules
- `PascalCase` for exported (public) identifiers
- `camelCase` for unexported (private) identifiers
- Acronyms should be all caps: `HTTPHandler`, `userID`, `parseJSON`
- Interface names: often end in `-er`: `Reader`, `Writer`, `Stringer`

### Specific Patterns
```go
// Package names: lowercase, single word
package user

// Types: noun, describes what it is
type User struct {}
type UserService struct {}
type UserRepository interface {}

// Functions: verb, describes action
func CreateUser() {}
func (s *UserService) FindByEmail() {}

// Variables: short but descriptive
var userCount int
var u *User  // OK in small scope

// Constants: describe the value
const MaxRetries = 3
const DefaultTimeout = 30 * time.Second
```

---

## Error Handling

### Always Check Errors
```go
// Good
result, err := doSomething()
if err != nil {
    return fmt.Errorf("doSomething failed: %w", err)
}

// Bad - ignoring errors
result, _ := doSomething()
```

### Error Wrapping
```go
// Wrap with context
if err != nil {
    return fmt.Errorf("creating user %s: %w", email, err)
}

// Check wrapped errors
if errors.Is(err, ErrNotFound) {
    // Handle not found
}

// Type assertion for custom errors
var validationErr *ValidationError
if errors.As(err, &validationErr) {
    // Handle validation error
}
```

### Custom Errors
```go
// Define package-level errors
var (
    ErrNotFound     = errors.New("not found")
    ErrUnauthorized = errors.New("unauthorized")
)

// Custom error types for complex cases
type ValidationError struct {
    Field   string
    Message string
}

func (e *ValidationError) Error() string {
    return fmt.Sprintf("validation failed for %s: %s", e.Field, e.Message)
}
```

---

## GORM Guidelines

### Model Definition
```go
type User struct {
    ID        string    `gorm:"primaryKey;type:text"`
    Email     string    `gorm:"not null;uniqueIndex"`
    Name      string    `gorm:"not null"`
    CreatedAt time.Time
    UpdatedAt time.Time
    DeletedAt gorm.DeletedAt `gorm:"index"`
    
    // Relationships
    Posts []Post `gorm:"foreignKey:UserID;constraint:OnDelete:CASCADE"`
}

func (User) TableName() string {
    return "users"
}
```

### Repository Pattern
```go
type UserRepository interface {
    Create(ctx context.Context, user *User) error
    FindByID(ctx context.Context, id string) (*User, error)
    FindByEmail(ctx context.Context, email string) (*User, error)
    Update(ctx context.Context, user *User) error
    Delete(ctx context.Context, id string) error
}

type userRepository struct {
    db *gorm.DB
}

func NewUserRepository(db *gorm.DB) UserRepository {
    return &userRepository{db: db}
}

func (r *userRepository) FindByID(ctx context.Context, id string) (*User, error) {
    var user User
    if err := r.db.WithContext(ctx).First(&user, "id = ?", id).Error; err != nil {
        if errors.Is(err, gorm.ErrRecordNotFound) {
            return nil, ErrNotFound
        }
        return nil, fmt.Errorf("finding user by id: %w", err)
    }
    return &user, nil
}
```

### Transaction Handling
```go
func (s *UserService) CreateWithProfile(ctx context.Context, user *User, profile *Profile) error {
    return s.db.WithContext(ctx).Transaction(func(tx *gorm.DB) error {
        if err := tx.Create(user).Error; err != nil {
            return fmt.Errorf("creating user: %w", err)
        }
        
        profile.UserID = user.ID
        if err := tx.Create(profile).Error; err != nil {
            return fmt.Errorf("creating profile: %w", err)
        }
        
        return nil
    })
}
```

---

## HTTP Handlers

### Handler Structure
```go
type UserHandler struct {
    service UserService
    logger  *slog.Logger
}

func NewUserHandler(service UserService, logger *slog.Logger) *UserHandler {
    return &UserHandler{
        service: service,
        logger:  logger,
    }
}

func (h *UserHandler) Create(w http.ResponseWriter, r *http.Request) {
    ctx := r.Context()
    
    var req CreateUserRequest
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        h.respondError(w, http.StatusBadRequest, "invalid request body")
        return
    }
    
    if err := req.Validate(); err != nil {
        h.respondError(w, http.StatusBadRequest, err.Error())
        return
    }
    
    user, err := h.service.Create(ctx, req.ToUser())
    if err != nil {
        h.logger.Error("creating user", "error", err)
        h.respondError(w, http.StatusInternalServerError, "internal error")
        return
    }
    
    h.respondJSON(w, http.StatusCreated, user)
}
```

### Response Helpers
```go
type APIResponse struct {
    Success bool        `json:"success"`
    Data    interface{} `json:"data,omitempty"`
    Error   string      `json:"error,omitempty"`
}

func (h *UserHandler) respondJSON(w http.ResponseWriter, status int, data interface{}) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(status)
    json.NewEncoder(w).Encode(APIResponse{
        Success: true,
        Data:    data,
    })
}

func (h *UserHandler) respondError(w http.ResponseWriter, status int, message string) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(status)
    json.NewEncoder(w).Encode(APIResponse{
        Success: false,
        Error:   message,
    })
}
```

---

## Testing

### Table-Driven Tests
```go
func TestUserService_Create(t *testing.T) {
    tests := []struct {
        name    string
        input   CreateUserInput
        wantErr bool
        errType error
    }{
        {
            name:    "valid user",
            input:   CreateUserInput{Email: "test@example.com", Name: "Test"},
            wantErr: false,
        },
        {
            name:    "duplicate email",
            input:   CreateUserInput{Email: "existing@example.com", Name: "Test"},
            wantErr: true,
            errType: ErrDuplicateEmail,
        },
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            // Setup
            svc := setupTestService(t)
            
            // Execute
            _, err := svc.Create(context.Background(), tt.input)
            
            // Assert
            if tt.wantErr {
                require.Error(t, err)
                if tt.errType != nil {
                    assert.ErrorIs(t, err, tt.errType)
                }
            } else {
                require.NoError(t, err)
            }
        })
    }
}
```

### Mocking
```go
// Use interfaces for dependencies
type UserService struct {
    repo   UserRepository  // interface
    mailer Mailer          // interface
}

// In tests, use mock implementations
type mockUserRepository struct {
    users map[string]*User
}

func (m *mockUserRepository) FindByID(ctx context.Context, id string) (*User, error) {
    if user, ok := m.users[id]; ok {
        return user, nil
    }
    return nil, ErrNotFound
}
```

---

## Best Practices

### Context Usage
```go
// Always accept context as first parameter
func (s *Service) DoWork(ctx context.Context, input Input) error {
    // Use context for cancellation and deadlines
    select {
    case <-ctx.Done():
        return ctx.Err()
    default:
    }
    
    // Pass context to downstream calls
    return s.repo.Save(ctx, input)
}
```

### Logging
```go
// Use structured logging (slog)
logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))

logger.Info("user created",
    "user_id", user.ID,
    "email", user.Email,
)

logger.Error("failed to create user",
    "error", err,
    "email", input.Email,
)
```

### Configuration
```go
type Config struct {
    Server   ServerConfig
    Database DatabaseConfig
}

type ServerConfig struct {
    Port         int           `env:"SERVER_PORT" envDefault:"8080"`
    ReadTimeout  time.Duration `env:"SERVER_READ_TIMEOUT" envDefault:"30s"`
    WriteTimeout time.Duration `env:"SERVER_WRITE_TIMEOUT" envDefault:"30s"`
}

// Load with env-based library or manual parsing
```
