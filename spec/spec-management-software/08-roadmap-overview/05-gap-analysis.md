# Fix Plan: Gaps & Missing Sections

**Version:** 1.0.0  
**Status:** Active  
**Last Updated:** 2026-01-27

---

## Overview

This document provides a phase-by-phase plan to fix all identified gaps in the implementation guidelines. Each fix phase is atomic (15-45 minutes) and can be completed independently.

---

## Fix Phase Index

### Critical Fixes (Phases FIX-A1 to FIX-A12)

| Phase | Issue | Location | Est. Time |
|-------|-------|----------|-----------|
| FIX-A1 | Graceful shutdown | Phase A1 | 15 min |
| FIX-A2 | .env.example creation | Phase A1 | 10 min |
| FIX-A3 | Error types foundation | Phase B3 | 30 min |
| FIX-A4 | Error wrapper utilities | Phase B3 | 20 min |
| FIX-A5 | Validation middleware | Phase B4 | 30 min |
| FIX-A6 | Input validators | Phase B4 | 30 min |
| FIX-A7 | Rate limiter middleware | New B4a | 30 min |
| FIX-A8 | Request/Response DTOs base | A12, B7 | 30 min |
| FIX-A9 | Database transactions | C1 | 20 min |
| FIX-A10 | Structured logging | A5 | 30 min |
| FIX-A11 | Pagination patterns | B6, B9 | 25 min |
| FIX-A12 | Full-text search setup | New B10a | 30 min |

### Medium Fixes - Backend (Phases FIX-B1 to FIX-B8)

| Phase | Issue | Location | Est. Time |
|-------|-------|----------|-----------|
| FIX-B1 | Audio file handling | F.3/H2 | 30 min |
| FIX-B2 | Audio temp cleanup | F.3/H2 | 20 min |
| FIX-B3 | WebSocket connection | F.4/H4 | 45 min |
| FIX-B4 | SSE streaming setup | F.4/H4 | 30 min |
| FIX-B5 | Preset management handlers | New F5a | 30 min |
| FIX-B6 | Guidelines management handlers | New F5b | 30 min |
| FIX-B7 | Brute-force lockout | A11 | 30 min |
| FIX-B8 | Session cleanup cron | A10 | 20 min |

### Medium Fixes - Frontend (Phases FIX-C1 to FIX-C6)

| Phase | Issue | Location | Est. Time |
|-------|-------|----------|-----------|
| FIX-C1 | FolderSyncWizard types | G5 | 20 min |
| FIX-C2 | FolderSyncWizard component | G5 | 45 min |
| FIX-C3 | Consistency report types | K3-K4 | 20 min |
| FIX-C4 | Consistency report UI | K3-K4 | 45 min |
| FIX-C5 | Streaming connection hook | I6 | 30 min |
| FIX-C6 | Streaming reconnection logic | I6 | 30 min |

### Infrastructure Fixes (Phases FIX-D1 to FIX-D6)

| Phase | Issue | Location | Est. Time |
|-------|-------|----------|-----------|
| FIX-D1 | Dockerfile creation | New K7 | 30 min |
| FIX-D2 | docker-compose.yml | New K7 | 20 min |
| FIX-D3 | Makefile commands | New K7 | 20 min |
| FIX-D4 | Health check endpoint | A1 | 15 min |
| FIX-D5 | Metrics endpoint | New K7a | 30 min |
| FIX-D6 | CI/CD workflow | New K8 | 45 min |

---

## FIX-A1: Graceful Shutdown

**Goal:** Add proper server shutdown handling to prevent data loss

**Add to Phase A1 Checklist:**
- [ ] Update `cmd/server/main.go` with graceful shutdown:
  ```go
  package main

  import (
      "context"
      "log"
      "net/http"
      "os"
      "os/signal"
      "syscall"
      "time"

      "github.com/gin-gonic/gin"
  )

  func main() {
      r := gin.Default()
      
      // Health endpoint
      r.GET("/health", func(c *gin.Context) {
          c.JSON(200, gin.H{"status": "ok", "timestamp": time.Now().UTC()})
      })

      // Create server
      srv := &http.Server{
          Addr:         ":8080",
          Handler:      r,
          ReadTimeout:  15 * time.Second,
          WriteTimeout: 15 * time.Second,
          IdleTimeout:  60 * time.Second,
      }

      // Start server in goroutine
      go func() {
          log.Printf("Server starting on %s", srv.Addr)
          if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
              log.Fatalf("Server error: %v", err)
          }
      }()

      // Wait for interrupt signal
      quit := make(chan os.Signal, 1)
      signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
      <-quit

      log.Println("Shutting down server...")

      // Give outstanding requests 30 seconds to complete
      ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
      defer cancel()

      if err := srv.Shutdown(ctx); err != nil {
          log.Fatalf("Server forced to shutdown: %v", err)
      }

      log.Println("Server exited gracefully")
  }
  ```

**Verify:** Server shuts down cleanly on SIGTERM/SIGINT

---

## FIX-A2: Environment File Template

**Goal:** Create .env.example for easy setup

**Add to Phase A1 Checklist:**
- [ ] Create `.env.example`:
  ```bash
  # Server Configuration
  SERVER_PORT=8080
  SERVER_READ_TIMEOUT=15s
  SERVER_WRITE_TIMEOUT=15s

  # Database
  DATABASE_PATH=./data/spec.db

  # JWT Settings
  JWT_SECRET=change-me-in-production-use-32-chars-min
  JWT_ACCESS_TTL=15m
  JWT_REFRESH_TTL=336h

  # Spec Management
  SPEC_ROOT_PATH=./specs

  # LLaMA Configuration
  LLAMA_SERVER_PATH=/usr/local/bin/llama-server
  LLAMA_VOICE_MODEL=whisper-large-v3
  LLAMA_REASONING_MODEL=deepseek-r1:14b

  # Rate Limiting
  RATE_LIMIT_REQUESTS=100
  RATE_LIMIT_WINDOW=1m

  # Logging
  LOG_LEVEL=info
  LOG_FORMAT=json
  ```
- [ ] Add `.env` to `.gitignore`
- [ ] Create `internal/config/env.go` to load .env file:
  ```go
  package config

  import (
      "bufio"
      "os"
      "strings"
  )

  // LoadEnvFile loads environment variables from .env file
  func LoadEnvFile(path string) error {
      file, err := os.Open(path)
      if err != nil {
          return nil // .env file is optional
      }
      defer file.Close()

      scanner := bufio.NewScanner(file)
      for scanner.Scan() {
          line := strings.TrimSpace(scanner.Text())
          
          // Skip comments and empty lines
          if line == "" || strings.HasPrefix(line, "#") {
              continue
          }

          // Parse KEY=value
          parts := strings.SplitN(line, "=", 2)
          if len(parts) != 2 {
              continue
          }

          key := strings.TrimSpace(parts[0])
          value := strings.TrimSpace(parts[1])

          // Don't override existing env vars
          if os.Getenv(key) == "" {
              os.Setenv(key, value)
          }
      }

      return scanner.Err()
  }
  ```

**Verify:** Config loads from .env file correctly

---

## FIX-A3: Error Types Foundation

**Goal:** Create standardized error types with codes

**Create new section in Phase B3:**
- [ ] Create `internal/errors/codes.go`:
  ```go
  package errors

  // Error code ranges per general-spec standards
  const (
      // 1xxx - Validation Errors
      ErrCodeValidation      = 1000
      ErrCodeRequired        = 1001
      ErrCodeInvalidFormat   = 1002
      ErrCodeOutOfRange      = 1003
      ErrCodeTooLong         = 1004
      ErrCodeTooShort        = 1005

      // 2xxx - Auth/Authorization Errors
      ErrCodeAuth            = 2000
      ErrCodeUnauthorized    = 2001
      ErrCodeForbidden       = 2002
      ErrCodeTokenExpired    = 2003
      ErrCodeTokenInvalid    = 2004
      ErrCodeSessionRevoked  = 2005
      ErrCodeAccountLocked   = 2006

      // 3xxx - Database Errors
      ErrCodeDatabase        = 3000
      ErrCodeNotFound        = 3001
      ErrCodeDuplicate       = 3002
      ErrCodeConstraint      = 3003
      ErrCodeTransaction     = 3004

      // 4xxx - External Service Errors
      ErrCodeExternal        = 4000
      ErrCodeTimeout         = 4001
      ErrCodeUnavailable     = 4002

      // 5xxx - Business Logic Errors
      ErrCodeBusiness        = 5000
      ErrCodeConflict        = 5001
      ErrCodePrecondition    = 5002
      ErrCodeQuotaExceeded   = 5003

      // 6xxx - File System Errors
      ErrCodeFileSystem      = 6000
      ErrCodeFileNotFound    = 6001
      ErrCodePathTraversal   = 6002
      ErrCodeFileExists      = 6003
      ErrCodeHashMismatch    = 6004

      // 7xxx - Configuration Errors
      ErrCodeConfig          = 7000
      ErrCodeMissingConfig   = 7001
      ErrCodeInvalidConfig   = 7002

      // 9xxx - System Errors
      ErrCodeSystem          = 9000
      ErrCodeInternal        = 9001
      ErrCodePanic           = 9002
  )

  // Error code to constant name mapping
  var ErrorNames = map[int]string{
      ErrCodeValidation:     "ERR_VALIDATION",
      ErrCodeRequired:       "ERR_REQUIRED",
      ErrCodeInvalidFormat:  "ERR_INVALID_FORMAT",
      ErrCodeUnauthorized:   "ERR_UNAUTHORIZED",
      ErrCodeForbidden:      "ERR_FORBIDDEN",
      ErrCodeTokenExpired:   "ERR_TOKEN_EXPIRED",
      ErrCodeNotFound:       "ERR_NOT_FOUND",
      ErrCodeDuplicate:      "ERR_DUPLICATE",
      ErrCodeConflict:       "ERR_CONFLICT",
      ErrCodeFileNotFound:   "ERR_FILE_NOT_FOUND",
      ErrCodePathTraversal:  "ERR_PATH_TRAVERSAL",
      ErrCodeInternal:       "ERR_INTERNAL",
  }
  ```

**Verify:** Error codes compile and are accessible

---

## FIX-A4: Error Wrapper Utilities

**Goal:** Create error wrapper for consistent error handling

**Add to Phase B3:**
- [ ] Create `internal/errors/app_error.go`:
  ```go
  package errors

  import (
      "fmt"
      "net/http"
  )

  // AppError represents an application error with code and context
  type AppError struct {
      Code       int               `json:"code"`
      Constant   string            `json:"constant"`
      Message    string            `json:"message"`
      Details    map[string]any    `json:"details,omitempty"`
      Cause      error             `json:"-"`
      HTTPStatus int               `json:"-"`
  }

  func (e *AppError) Error() string {
      if e.Cause != nil {
          return fmt.Sprintf("[%s] %s: %v", e.Constant, e.Message, e.Cause)
      }
      return fmt.Sprintf("[%s] %s", e.Constant, e.Message)
  }

  func (e *AppError) Unwrap() error {
      return e.Cause
  }

  // New creates a new AppError
  func New(code int, message string) *AppError {
      return &AppError{
          Code:       code,
          Constant:   ErrorNames[code],
          Message:    message,
          HTTPStatus: codeToHTTPStatus(code),
      }
  }

  // Wrap wraps an existing error with an AppError
  func Wrap(code int, message string, cause error) *AppError {
      return &AppError{
          Code:       code,
          Constant:   ErrorNames[code],
          Message:    message,
          Cause:      cause,
          HTTPStatus: codeToHTTPStatus(code),
      }
  }

  // WithDetails adds context details to the error
  func (e *AppError) WithDetails(details map[string]any) *AppError {
      e.Details = details
      return e
  }

  // WithField adds a single detail field
  func (e *AppError) WithField(key string, value any) *AppError {
      if e.Details == nil {
          e.Details = make(map[string]any)
      }
      e.Details[key] = value
      return e
  }

  // codeToHTTPStatus maps error codes to HTTP status
  func codeToHTTPStatus(code int) int {
      switch {
      case code >= 1000 && code < 2000:
          return http.StatusBadRequest
      case code >= 2000 && code < 3000:
          if code == ErrCodeForbidden {
              return http.StatusForbidden
          }
          return http.StatusUnauthorized
      case code == ErrCodeNotFound, code == ErrCodeFileNotFound:
          return http.StatusNotFound
      case code == ErrCodeDuplicate:
          return http.StatusConflict
      case code >= 3000 && code < 4000:
          return http.StatusInternalServerError
      case code >= 4000 && code < 5000:
          return http.StatusBadGateway
      case code >= 5000 && code < 6000:
          return http.StatusUnprocessableEntity
      case code >= 6000 && code < 7000:
          return http.StatusBadRequest
      default:
          return http.StatusInternalServerError
      }
  }

  // Common error constructors
  func ValidationError(field, message string) *AppError {
      return New(ErrCodeValidation, message).WithField("field", field)
  }

  func NotFoundError(resource, id string) *AppError {
      return New(ErrCodeNotFound, fmt.Sprintf("%s not found", resource)).
          WithField("resource", resource).
          WithField("id", id)
  }

  func UnauthorizedError(message string) *AppError {
      return New(ErrCodeUnauthorized, message)
  }

  func ForbiddenError(message string) *AppError {
      return New(ErrCodeForbidden, message)
  }

  func ConflictError(message string) *AppError {
      return New(ErrCodeConflict, message)
  }

  func InternalError(cause error) *AppError {
      return Wrap(ErrCodeInternal, "Internal server error", cause)
  }
  ```

**Verify:** Can create, wrap, and format errors consistently

---

## FIX-A5: Validation Middleware

**Goal:** Create validation middleware for request processing

**Add to Phase B4:**
- [ ] Create `internal/middleware/validation.go`:
  ```go
  package middleware

  import (
      "net/http"
      "reflect"
      "strings"

      "spec-manager/internal/errors"
      "github.com/gin-gonic/gin"
      "github.com/go-playground/validator/v10"
  )

  var validate *validator.Validate

  func init() {
      validate = validator.New()
      
      // Use JSON tag names in error messages
      validate.RegisterTagNameFunc(func(fld reflect.StructField) string {
          name := strings.SplitN(fld.Tag.Get("json"), ",", 2)[0]
          if name == "-" {
              return ""
          }
          return name
      })

      // Register custom validators
      validate.RegisterValidation("slug", validateSlug)
      validate.RegisterValidation("path", validatePath)
  }

  // validateSlug checks for valid URL slug format
  func validateSlug(fl validator.FieldLevel) bool {
      slug := fl.Field().String()
      if len(slug) == 0 {
          return true // handled by required
      }
      for _, r := range slug {
          if !((r >= 'a' && r <= 'z') || (r >= '0' && r <= '9') || r == '-') {
              return false
          }
      }
      return true
  }

  // validatePath checks for valid file path (no traversal)
  func validatePath(fl validator.FieldLevel) bool {
      path := fl.Field().String()
      if strings.Contains(path, "..") {
          return false
      }
      if strings.HasPrefix(path, "/") {
          return false
      }
      return true
  }

  // ValidateRequest validates request body and binds to struct
  func ValidateRequest[T any](c *gin.Context) (*T, *errors.AppError) {
      var req T
      
      if err := c.ShouldBindJSON(&req); err != nil {
          return nil, errors.New(errors.ErrCodeInvalidFormat, "Invalid JSON format")
      }

      if err := validate.Struct(req); err != nil {
          if validationErrors, ok := err.(validator.ValidationErrors); ok {
              details := make(map[string]any)
              for _, e := range validationErrors {
                  field := e.Field()
                  details[field] = formatValidationError(e)
              }
              return nil, errors.New(errors.ErrCodeValidation, "Validation failed").
                  WithDetails(details)
          }
          return nil, errors.New(errors.ErrCodeValidation, err.Error())
      }

      return &req, nil
  }

  func formatValidationError(e validator.FieldError) string {
      switch e.Tag() {
      case "required":
          return "This field is required"
      case "email":
          return "Must be a valid email address"
      case "min":
          return "Must be at least " + e.Param() + " characters"
      case "max":
          return "Must be at most " + e.Param() + " characters"
      case "slug":
          return "Must contain only lowercase letters, numbers, and hyphens"
      case "path":
          return "Invalid file path"
      default:
          return "Invalid value"
      }
  }
  ```

**Dependencies:** Add `go get github.com/go-playground/validator/v10`

**Verify:** Validation errors return proper field-level details

---

## FIX-A6: Input Validators

**Goal:** Create reusable input validators

**Add to Phase B4:**
- [ ] Create `internal/utils/validators.go`:
  ```go
  package utils

  import (
      "regexp"
      "strings"
      "unicode/utf8"
  )

  // PathValidator validates file system paths
  type PathValidator struct {
      MaxLength        int
      AllowedPrefixes  []string
      DisallowedChars  []rune
  }

  func NewPathValidator() *PathValidator {
      return &PathValidator{
          MaxLength:       255,
          AllowedPrefixes: []string{},
          DisallowedChars: []rune{'<', '>', ':', '"', '|', '?', '*', '\x00'},
      }
  }

  func (v *PathValidator) Validate(path string) error {
      // Check length
      if utf8.RuneCountInString(path) > v.MaxLength {
          return fmt.Errorf("path exceeds maximum length of %d", v.MaxLength)
      }

      // Check for traversal
      if strings.Contains(path, "..") {
          return fmt.Errorf("path traversal not allowed")
      }

      // Check for absolute path
      if strings.HasPrefix(path, "/") || strings.HasPrefix(path, "\\") {
          return fmt.Errorf("absolute paths not allowed")
      }

      // Check for disallowed characters
      for _, r := range path {
          for _, disallowed := range v.DisallowedChars {
              if r == disallowed {
                  return fmt.Errorf("path contains disallowed character: %c", r)
              }
          }
      }

      return nil
  }

  // UsernameValidator validates usernames
  var usernameRegex = regexp.MustCompile(`^[a-zA-Z][a-zA-Z0-9_-]{2,49}$`)

  func ValidateUsername(username string) error {
      if !usernameRegex.MatchString(username) {
          return fmt.Errorf("username must start with letter, 3-50 chars, alphanumeric with _ or -")
      }
      return nil
  }

  // EmailValidator validates email addresses
  var emailRegex = regexp.MustCompile(`^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$`)

  func ValidateEmail(email string) error {
      if len(email) > 255 {
          return fmt.Errorf("email exceeds maximum length")
      }
      if !emailRegex.MatchString(email) {
          return fmt.Errorf("invalid email format")
      }
      return nil
  }

  // SlugValidator validates URL slugs
  var slugRegex = regexp.MustCompile(`^[a-z0-9]+(?:-[a-z0-9]+)*$`)

  func ValidateSlug(slug string) error {
      if len(slug) < 2 || len(slug) > 100 {
          return fmt.Errorf("slug must be 2-100 characters")
      }
      if !slugRegex.MatchString(slug) {
          return fmt.Errorf("slug must be lowercase with hyphens only")
      }
      return nil
  }

  // ContentValidator validates content for size and encoding
  func ValidateContent(content string, maxSize int) error {
      if len(content) > maxSize {
          return fmt.Errorf("content exceeds maximum size of %d bytes", maxSize)
      }
      if !utf8.ValidString(content) {
          return fmt.Errorf("content must be valid UTF-8")
      }
      return nil
  }
  ```

**Verify:** All validators work with edge cases

---

## FIX-A7: Rate Limiter Middleware

**Goal:** Add request rate limiting

**Create new Phase B4a:**
- [ ] Create `internal/middleware/rate_limit.go`:
  ```go
  package middleware

  import (
      "net/http"
      "sync"
      "time"

      "github.com/gin-gonic/gin"
  )

  type RateLimiter struct {
      requests map[string]*bucket
      mu       sync.RWMutex
      limit    int
      window   time.Duration
  }

  type bucket struct {
      count    int
      resetAt  time.Time
  }

  func NewRateLimiter(limit int, window time.Duration) *RateLimiter {
      rl := &RateLimiter{
          requests: make(map[string]*bucket),
          limit:    limit,
          window:   window,
      }
      
      // Cleanup old entries every minute
      go rl.cleanup()
      
      return rl
  }

  func (rl *RateLimiter) cleanup() {
      ticker := time.NewTicker(time.Minute)
      for range ticker.C {
          rl.mu.Lock()
          now := time.Now()
          for key, b := range rl.requests {
              if now.After(b.resetAt) {
                  delete(rl.requests, key)
              }
          }
          rl.mu.Unlock()
      }
  }

  func (rl *RateLimiter) Allow(key string) bool {
      rl.mu.Lock()
      defer rl.mu.Unlock()

      now := time.Now()
      b, exists := rl.requests[key]

      if !exists || now.After(b.resetAt) {
          rl.requests[key] = &bucket{
              count:   1,
              resetAt: now.Add(rl.window),
          }
          return true
      }

      if b.count >= rl.limit {
          return false
      }

      b.count++
      return true
  }

  func (rl *RateLimiter) Remaining(key string) int {
      rl.mu.RLock()
      defer rl.mu.RUnlock()

      b, exists := rl.requests[key]
      if !exists {
          return rl.limit
      }
      
      if time.Now().After(b.resetAt) {
          return rl.limit
      }

      return rl.limit - b.count
  }

  // RateLimitMiddleware creates a rate limiting middleware
  func RateLimitMiddleware(limit int, window time.Duration) gin.HandlerFunc {
      limiter := NewRateLimiter(limit, window)

      return func(c *gin.Context) {
          // Use IP as key (can be enhanced with user ID for authenticated routes)
          key := c.ClientIP()
          
          if userId := c.GetString("userId"); userId != "" {
              key = "user:" + userId
          }

          if !limiter.Allow(key) {
              c.Header("Retry-After", "60")
              c.Header("X-RateLimit-Limit", fmt.Sprintf("%d", limit))
              c.Header("X-RateLimit-Remaining", "0")
              c.JSON(http.StatusTooManyRequests, gin.H{
                  "success": false,
                  "error":   "Rate limit exceeded",
                  "code":    429,
              })
              c.Abort()
              return
          }

          c.Header("X-RateLimit-Limit", fmt.Sprintf("%d", limit))
          c.Header("X-RateLimit-Remaining", fmt.Sprintf("%d", limiter.Remaining(key)))
          
          c.Next()
      }
  }
  ```

**Verify:** Rate limiting correctly blocks excessive requests

---

## FIX-A8: Request/Response DTOs Base

**Goal:** Create base DTO structures

**Add to Phase A12:**
- [ ] Create `internal/handlers/dto/base.go`:
  ```go
  package dto

  import "time"

  // APIResponse is the standard response envelope
  type APIResponse struct {
      Success bool        `json:"success"`
      Data    interface{} `json:"data,omitempty"`
      Error   *APIError   `json:"error,omitempty"`
      Meta    *APIMeta    `json:"meta,omitempty"`
  }

  // APIError represents an error in the response
  type APIError struct {
      Code     int            `json:"code"`
      Constant string         `json:"constant"`
      Message  string         `json:"message"`
      Details  map[string]any `json:"details,omitempty"`
  }

  // APIMeta contains response metadata
  type APIMeta struct {
      RequestId  string     `json:"requestId,omitempty"`
      Timestamp  time.Time  `json:"timestamp"`
      Pagination *PageMeta  `json:"pagination,omitempty"`
  }

  // PageMeta contains pagination information
  type PageMeta struct {
      Page       int  `json:"page"`
      PageSize   int  `json:"pageSize"`
      TotalItems int  `json:"totalItems"`
      TotalPages int  `json:"totalPages"`
      HasNext    bool `json:"hasNext"`
      HasPrev    bool `json:"hasPrev"`
  }

  // PaginationRequest for list endpoints
  type PaginationRequest struct {
      Page     int    `form:"page" binding:"min=1"`
      PageSize int    `form:"pageSize" binding:"min=1,max=100"`
      Sort     string `form:"sort"`
      Order    string `form:"order" binding:"oneof=asc desc ''"`
  }

  func (p *PaginationRequest) WithDefaults() {
      if p.Page == 0 {
          p.Page = 1
      }
      if p.PageSize == 0 {
          p.PageSize = 20
      }
      if p.Order == "" {
          p.Order = "asc"
      }
  }

  func (p *PaginationRequest) Offset() int {
      return (p.Page - 1) * p.PageSize
  }
  ```
- [ ] Create `internal/handlers/response.go`:
  ```go
  package handlers

  import (
      "time"

      "spec-manager/internal/errors"
      "spec-manager/internal/handlers/dto"
      "github.com/gin-gonic/gin"
      "github.com/google/uuid"
  )

  // Success sends a successful response
  func Success(c *gin.Context, status int, data interface{}) {
      c.JSON(status, dto.APIResponse{
          Success: true,
          Data:    data,
          Meta: &dto.APIMeta{
              RequestId: getRequestId(c),
              Timestamp: time.Now().UTC(),
          },
      })
  }

  // SuccessWithPagination sends a paginated response
  func SuccessWithPagination(c *gin.Context, data interface{}, page dto.PageMeta) {
      c.JSON(200, dto.APIResponse{
          Success: true,
          Data:    data,
          Meta: &dto.APIMeta{
              RequestId:  getRequestId(c),
              Timestamp:  time.Now().UTC(),
              Pagination: &page,
          },
      })
  }

  // Error sends an error response
  func Error(c *gin.Context, err *errors.AppError) {
      c.JSON(err.HTTPStatus, dto.APIResponse{
          Success: false,
          Error: &dto.APIError{
              Code:     err.Code,
              Constant: err.Constant,
              Message:  err.Message,
              Details:  err.Details,
          },
          Meta: &dto.APIMeta{
              RequestId: getRequestId(c),
              Timestamp: time.Now().UTC(),
          },
      })
  }

  func getRequestId(c *gin.Context) string {
      if id := c.GetString("requestId"); id != "" {
          return id
      }
      return uuid.NewString()
  }
  ```

**Verify:** Responses follow consistent envelope format

---

## FIX-A9: Database Transactions

**Goal:** Add transaction support for multi-step operations

**Add to Phase C1:**
- [ ] Create `internal/repository/transaction.go`:
  ```go
  package repository

  import (
      "context"
      "database/sql"
      "fmt"
  )

  // TxFunc is a function that runs within a transaction
  type TxFunc func(tx *sql.Tx) error

  // WithTransaction executes a function within a database transaction
  func (db *DB) WithTransaction(ctx context.Context, fn TxFunc) error {
      tx, err := db.BeginTx(ctx, nil)
      if err != nil {
          return fmt.Errorf("begin transaction: %w", err)
      }

      defer func() {
          if p := recover(); p != nil {
              tx.Rollback()
              panic(p) // re-throw panic after rollback
          }
      }()

      if err := fn(tx); err != nil {
          if rbErr := tx.Rollback(); rbErr != nil {
              return fmt.Errorf("rollback failed: %v (original error: %w)", rbErr, err)
          }
          return err
      }

      if err := tx.Commit(); err != nil {
          return fmt.Errorf("commit transaction: %w", err)
      }

      return nil
  }

  // WithTransactionResult executes a function and returns a result
  func WithTransactionResult[T any](db *DB, ctx context.Context, fn func(tx *sql.Tx) (T, error)) (T, error) {
      var result T
      
      tx, err := db.BeginTx(ctx, nil)
      if err != nil {
          return result, fmt.Errorf("begin transaction: %w", err)
      }

      defer func() {
          if p := recover(); p != nil {
              tx.Rollback()
              panic(p)
          }
      }()

      result, err = fn(tx)
      if err != nil {
          tx.Rollback()
          return result, err
      }

      if err := tx.Commit(); err != nil {
          return result, fmt.Errorf("commit transaction: %w", err)
      }

      return result, nil
  }
  ```

**Verify:** Transactions rollback on error, commit on success

---

## FIX-A10: Structured Logging

**Goal:** Add structured JSON logging

**Add to Phase A5:**
- [ ] Create `internal/utils/logger.go`:
  ```go
  package utils

  import (
      "encoding/json"
      "fmt"
      "io"
      "os"
      "sync"
      "time"
  )

  type LogLevel int

  const (
      LogLevelDebug LogLevel = iota
      LogLevelInfo
      LogLevelWarn
      LogLevelError
  )

  var levelNames = map[LogLevel]string{
      LogLevelDebug: "debug",
      LogLevelInfo:  "info",
      LogLevelWarn:  "warn",
      LogLevelError: "error",
  }

  type Logger struct {
      mu     sync.Mutex
      level  LogLevel
      output io.Writer
      fields map[string]any
  }

  type LogEntry struct {
      Timestamp string         `json:"timestamp"`
      Level     string         `json:"level"`
      Message   string         `json:"message"`
      Fields    map[string]any `json:"fields,omitempty"`
  }

  var defaultLogger = &Logger{
      level:  LogLevelInfo,
      output: os.Stdout,
      fields: make(map[string]any),
  }

  func SetLogLevel(level string) {
      switch level {
      case "debug":
          defaultLogger.level = LogLevelDebug
      case "info":
          defaultLogger.level = LogLevelInfo
      case "warn":
          defaultLogger.level = LogLevelWarn
      case "error":
          defaultLogger.level = LogLevelError
      }
  }

  func (l *Logger) log(level LogLevel, msg string, fields map[string]any) {
      if level < l.level {
          return
      }

      l.mu.Lock()
      defer l.mu.Unlock()

      allFields := make(map[string]any)
      for k, v := range l.fields {
          allFields[k] = v
      }
      for k, v := range fields {
          allFields[k] = v
      }

      entry := LogEntry{
          Timestamp: time.Now().UTC().Format(time.RFC3339),
          Level:     levelNames[level],
          Message:   msg,
          Fields:    allFields,
      }

      data, _ := json.Marshal(entry)
      fmt.Fprintln(l.output, string(data))
  }

  func (l *Logger) With(key string, value any) *Logger {
      newFields := make(map[string]any)
      for k, v := range l.fields {
          newFields[k] = v
      }
      newFields[key] = value

      return &Logger{
          level:  l.level,
          output: l.output,
          fields: newFields,
      }
  }

  // Package-level functions
  func Debug(msg string, fields ...map[string]any) {
      f := mergeFields(fields)
      defaultLogger.log(LogLevelDebug, msg, f)
  }

  func Info(msg string, fields ...map[string]any) {
      f := mergeFields(fields)
      defaultLogger.log(LogLevelInfo, msg, f)
  }

  func Warn(msg string, fields ...map[string]any) {
      f := mergeFields(fields)
      defaultLogger.log(LogLevelWarn, msg, f)
  }

  func Error(msg string, fields ...map[string]any) {
      f := mergeFields(fields)
      defaultLogger.log(LogLevelError, msg, f)
  }

  func mergeFields(fields []map[string]any) map[string]any {
      if len(fields) == 0 {
          return nil
      }
      result := make(map[string]any)
      for _, f := range fields {
          for k, v := range f {
              result[k] = v
          }
      }
      return result
  }
  ```

**Verify:** Logs output valid JSON with timestamps

---

## FIX-A11: Pagination Patterns

**Goal:** Add standardized pagination to list endpoints

**Add to Phase B6 and B9:**
- [ ] Create `internal/repository/pagination.go`:
  ```go
  package repository

  import (
      "fmt"
      "strings"
  )

  type PageParams struct {
      Page     int
      PageSize int
      Sort     string
      Order    string // "asc" or "desc"
  }

  func (p *PageParams) Offset() int {
      return (p.Page - 1) * p.PageSize
  }

  func (p *PageParams) Limit() int {
      return p.PageSize
  }

  // BuildOrderClause creates SQL ORDER BY clause
  func (p *PageParams) BuildOrderClause(allowedColumns map[string]string) string {
      if p.Sort == "" {
          return ""
      }

      // Map API field name to DB column
      column, ok := allowedColumns[p.Sort]
      if !ok {
          return ""
      }

      order := "ASC"
      if strings.ToLower(p.Order) == "desc" {
          order = "DESC"
      }

      return fmt.Sprintf("ORDER BY %s %s", column, order)
  }

  type PageResult[T any] struct {
      Items      []T
      TotalItems int
      Page       int
      PageSize   int
  }

  func (r *PageResult[T]) TotalPages() int {
      if r.PageSize == 0 {
          return 0
      }
      return (r.TotalItems + r.PageSize - 1) / r.PageSize
  }

  func (r *PageResult[T]) HasNext() bool {
      return r.Page < r.TotalPages()
  }

  func (r *PageResult[T]) HasPrev() bool {
      return r.Page > 1
  }
  ```
- [ ] Update Project Repository with GORM pagination:
  ```go
  // ORM Policy: All database operations use GORM. Raw SQL is forbidden.
  
  func (r *ProjectRepo) List(ownerId string, params PageParams) (*PageResult[models.Project], error) {
      var total int64
      r.db.Model(&models.Project{}).Where("owner_id = ?", ownerId).Count(&total)

      var projects []models.Project
      query := r.db.Where("owner_id = ?", ownerId)
      
      // Apply sorting via GORM
      orderClause := params.BuildGormOrder(map[string]string{
          "name":      "name",
          "createdAt": "created_at",
          "updatedAt": "updated_at",
      })
      
      err := query.
          Order(orderClause).
          Limit(params.Limit()).
          Offset(params.Offset()).
          Find(&projects).Error
      
      if err != nil {
          return nil, err
      }

      return &PageResult[models.Project]{
          Items:      projects,
          TotalItems: int(total),
          Page:       params.Page,
          PageSize:   params.PageSize,
      }, nil
  }
  ```

**Verify:** List endpoints return proper pagination metadata

---

## FIX-A12: Full-Text Search Setup

**Goal:** Add SQLite FTS5 for search using GORM Raw (only exception for FTS)

> **Note:** FTS5 virtual tables require raw SQL as GORM doesn't support them natively.
> This is the ONLY acceptable exception to the ORM-only policy.

**Create new Phase B10a:**
- [ ] Create FTS setup in `internal/db/fts.go`:
  ```go
  package db

  import (
      "gorm.io/gorm"
  )

  // InitFullTextSearch creates FTS5 virtual table (requires raw SQL)
  // This is the ONLY place where raw SQL is permitted - FTS5 is not supported by GORM
  func InitFullTextSearch(db *gorm.DB) error {
      return db.Exec(`
          CREATE VIRTUAL TABLE IF NOT EXISTS FileSearch USING fts5(
              FileId,
              ProjectId,
              Name,
              Path,
              Content
          )
      `).Error
  }
  ```
- [ ] Create `internal/repository/search_repo.go` with GORM:
  ```go
  package repository

  import (
      "gorm.io/gorm"
  )

  type SearchRepo struct {
      db *gorm.DB
  }

  func NewSearchRepo(db *gorm.DB) *SearchRepo {
      return &SearchRepo{db: db}
  }

  type SearchResult struct {
      FileId    string  `json:"fileId"`
      ProjectId string  `json:"projectId"`
      Name      string  `json:"name"`
      Path      string  `json:"path"`
      Snippet   string  `json:"snippet"`
      Rank      float64 `json:"rank"`
  }

  // Search uses GORM's Raw for FTS5 queries (only exception to ORM policy)
  func (r *SearchRepo) Search(projectId, query string, limit int) ([]SearchResult, error) {
      var results []SearchResult
      
      // FTS5 queries require Raw - this is the only acceptable exception
      err := r.db.Raw(`
          SELECT FileId, ProjectId, Name, Path, 
                 snippet(FileSearch, 4, '<mark>', '</mark>', '...', 32) as Snippet, 
                 rank
          FROM FileSearch
          WHERE ProjectId = ? AND FileSearch MATCH ?
          ORDER BY rank
          LIMIT ?
      `, projectId, query, limit).Scan(&results).Error
      
      return results, err
  }

  // UpdateContent updates FTS index
  func (r *SearchRepo) UpdateContent(fileId, content string) error {
      return r.db.Exec(`
          UPDATE FileSearch SET Content = ? WHERE FileId = ?
      `, content, fileId).Error
  }

  // IndexFile adds file to search index
  func (r *SearchRepo) IndexFile(fileId, projectId, name, path string) error {
      return r.db.Exec(`
          INSERT INTO FileSearch(FileId, ProjectId, Name, Path, Content)
          VALUES (?, ?, ?, ?, '')
      `, fileId, projectId, name, path).Error
  }

  // RemoveFile removes file from search index
  func (r *SearchRepo) RemoveFile(fileId string) error {
      return r.db.Exec(`DELETE FROM FileSearch WHERE FileId = ?`, fileId).Error
  }
  ```

**Verify:** Search returns relevant results with snippets

---

## FIX-B1: Audio File Handling

**Goal:** Validate and process audio uploads for voice transcription

**Add to Phase H2 Checklist:**
- [ ] Create `internal/utils/audio.go`:
  ```go
  package utils

  import (
      "bytes"
      "encoding/binary"
      "fmt"
      "io"
      "mime/multipart"
      "os"
      "path/filepath"
      "strings"

      "github.com/google/uuid"
  )

  // AudioConfig defines audio processing parameters
  type AudioConfig struct {
      MaxFileSize     int64    // Max file size in bytes (25MB)
      AllowedFormats  []string // Allowed MIME types
      TempDir         string   // Temp directory for uploads
      SampleRate      int      // Target sample rate (24000 Hz)
      Channels        int      // Mono = 1
      BitsPerSample   int      // 16-bit PCM
  }

  func DefaultAudioConfig() *AudioConfig {
      return &AudioConfig{
          MaxFileSize:    25 * 1024 * 1024, // 25MB
          AllowedFormats: []string{"audio/wav", "audio/mpeg", "audio/mp3", "audio/ogg", "audio/webm"},
          TempDir:        "./data/temp/audio",
          SampleRate:     24000,
          Channels:       1,
          BitsPerSample:  16,
      }
  }

  // AudioValidator validates uploaded audio files
  type AudioValidator struct {
      config *AudioConfig
  }

  func NewAudioValidator(config *AudioConfig) *AudioValidator {
      // Ensure temp directory exists
      os.MkdirAll(config.TempDir, 0755)
      return &AudioValidator{config: config}
  }

  // ValidateAndSave validates an uploaded audio file and saves to temp
  func (v *AudioValidator) ValidateAndSave(file *multipart.FileHeader) (string, error) {
      // Check file size
      if file.Size > v.config.MaxFileSize {
          return "", fmt.Errorf("file size %d exceeds maximum %d bytes", file.Size, v.config.MaxFileSize)
      }

      // Check MIME type
      contentType := file.Header.Get("Content-Type")
      if !v.isAllowedFormat(contentType) {
          return "", fmt.Errorf("unsupported audio format: %s", contentType)
      }

      // Open uploaded file
      src, err := file.Open()
      if err != nil {
          return "", fmt.Errorf("failed to open uploaded file: %w", err)
      }
      defer src.Close()

      // Validate audio header (basic magic byte check)
      header := make([]byte, 12)
      if _, err := src.Read(header); err != nil {
          return "", fmt.Errorf("failed to read file header: %w", err)
      }
      if !v.validateMagicBytes(header, contentType) {
          return "", fmt.Errorf("file content does not match declared type")
      }

      // Reset to beginning
      src.Seek(0, 0)

      // Generate temp filename
      ext := filepath.Ext(file.Filename)
      if ext == "" {
          ext = ".wav"
      }
      tempPath := filepath.Join(v.config.TempDir, uuid.NewString()+ext)

      // Save to temp file
      dst, err := os.Create(tempPath)
      if err != nil {
          return "", fmt.Errorf("failed to create temp file: %w", err)
      }
      defer dst.Close()

      if _, err := io.Copy(dst, src); err != nil {
          os.Remove(tempPath)
          return "", fmt.Errorf("failed to save audio file: %w", err)
      }

      return tempPath, nil
  }

  func (v *AudioValidator) isAllowedFormat(contentType string) bool {
      for _, allowed := range v.config.AllowedFormats {
          if strings.EqualFold(contentType, allowed) {
              return true
          }
      }
      return false
  }

  func (v *AudioValidator) validateMagicBytes(header []byte, contentType string) bool {
      switch {
      case strings.Contains(contentType, "wav"):
          // WAV: RIFF....WAVE
          return bytes.HasPrefix(header, []byte("RIFF")) && bytes.Contains(header, []byte("WAVE"))
      case strings.Contains(contentType, "mp3"), strings.Contains(contentType, "mpeg"):
          // MP3: ID3 or 0xFF 0xFB
          return bytes.HasPrefix(header, []byte("ID3")) || (header[0] == 0xFF && (header[1]&0xE0) == 0xE0)
      case strings.Contains(contentType, "ogg"):
          // OGG: OggS
          return bytes.HasPrefix(header, []byte("OggS"))
      case strings.Contains(contentType, "webm"):
          // WebM: 0x1A 0x45 0xDF 0xA3
          return header[0] == 0x1A && header[1] == 0x45 && header[2] == 0xDF && header[3] == 0xA3
      default:
          return true // Allow unknown formats to pass through
      }
  }

  // ConvertToPCM16 converts audio to 16-bit PCM at 24kHz (placeholder - use ffmpeg in production)
  func (v *AudioValidator) ConvertToPCM16(inputPath string) ([]byte, error) {
      // For production, use ffmpeg:
      // ffmpeg -i input.wav -ar 24000 -ac 1 -f s16le -acodec pcm_s16le output.raw
      
      // This is a simplified WAV reader for already-compliant files
      data, err := os.ReadFile(inputPath)
      if err != nil {
          return nil, err
      }

      // If already WAV, extract PCM data
      if bytes.HasPrefix(data, []byte("RIFF")) {
          return v.extractWavPCM(data)
      }

      return nil, fmt.Errorf("format requires ffmpeg conversion")
  }

  func (v *AudioValidator) extractWavPCM(data []byte) ([]byte, error) {
      // Find "data" chunk
      dataIndex := bytes.Index(data, []byte("data"))
      if dataIndex == -1 {
          return nil, fmt.Errorf("invalid WAV: no data chunk")
      }

      // Read data chunk size (4 bytes after "data")
      if len(data) < dataIndex+8 {
          return nil, fmt.Errorf("invalid WAV: truncated data chunk")
      }
      
      chunkSize := binary.LittleEndian.Uint32(data[dataIndex+4 : dataIndex+8])
      pcmStart := dataIndex + 8
      pcmEnd := pcmStart + int(chunkSize)

      if pcmEnd > len(data) {
          pcmEnd = len(data)
      }

      return data[pcmStart:pcmEnd], nil
  }

  // EncodeToBase64PCM encodes PCM data to base64 for API transmission
  func EncodeToBase64PCM(pcmData []byte) string {
      return base64.StdEncoding.EncodeToString(pcmData)
  }
  ```

**Verify:** Can validate and save audio files, extract PCM data

---

## FIX-B2: Audio Temp File Cleanup

**Goal:** Automatically clean up temporary audio files

**Add to Phase H2 Checklist:**
- [ ] Create `internal/services/audio_cleanup.go`:
  ```go
  package services

  import (
      "log"
      "os"
      "path/filepath"
      "time"
  )

  // AudioCleanupService handles temp file cleanup
  type AudioCleanupService struct {
      tempDir    string
      maxAge     time.Duration
      interval   time.Duration
      stopCh     chan struct{}
  }

  func NewAudioCleanupService(tempDir string, maxAge, interval time.Duration) *AudioCleanupService {
      return &AudioCleanupService{
          tempDir:  tempDir,
          maxAge:   maxAge,
          interval: interval,
          stopCh:   make(chan struct{}),
      }
  }

  // Start begins the cleanup goroutine
  func (s *AudioCleanupService) Start() {
      go s.run()
      log.Printf("Audio cleanup service started (dir: %s, maxAge: %s)", s.tempDir, s.maxAge)
  }

  // Stop gracefully stops the cleanup service
  func (s *AudioCleanupService) Stop() {
      close(s.stopCh)
  }

  func (s *AudioCleanupService) run() {
      ticker := time.NewTicker(s.interval)
      defer ticker.Stop()

      // Run immediately on start
      s.cleanup()

      for {
          select {
          case <-ticker.C:
              s.cleanup()
          case <-s.stopCh:
              log.Println("Audio cleanup service stopped")
              return
          }
      }
  }

  func (s *AudioCleanupService) cleanup() {
      now := time.Now()
      deleted := 0
      errors := 0

      err := filepath.Walk(s.tempDir, func(path string, info os.FileInfo, err error) error {
          if err != nil {
              return nil // Skip files we can't access
          }

          // Skip directories
          if info.IsDir() {
              return nil
          }

          // Check if file is older than maxAge
          if now.Sub(info.ModTime()) > s.maxAge {
              if err := os.Remove(path); err != nil {
                  log.Printf("Failed to delete temp file %s: %v", path, err)
                  errors++
              } else {
                  deleted++
              }
          }

          return nil
      })

      if err != nil {
          log.Printf("Error walking temp directory: %v", err)
      }

      if deleted > 0 || errors > 0 {
          log.Printf("Audio cleanup: deleted %d files, %d errors", deleted, errors)
      }
  }

  // CleanupNow triggers an immediate cleanup
  func (s *AudioCleanupService) CleanupNow() (int, error) {
      now := time.Now()
      deleted := 0

      err := filepath.Walk(s.tempDir, func(path string, info os.FileInfo, err error) error {
          if err != nil || info.IsDir() {
              return nil
          }

          if now.Sub(info.ModTime()) > s.maxAge {
              if err := os.Remove(path); err == nil {
                  deleted++
              }
          }
          return nil
      })

      return deleted, err
  }
  ```
- [ ] Update `main.go` to start cleanup service:
  ```go
  // In main()
  audioCleanup := services.NewAudioCleanupService(
      "./data/temp/audio",
      1*time.Hour,  // Max age: 1 hour
      15*time.Minute, // Check every 15 minutes
  )
  audioCleanup.Start()
  defer audioCleanup.Stop()
  ```

**Verify:** Old temp files are automatically deleted after 1 hour

---

## FIX-B3: WebSocket Connection Manager

**Goal:** Implement WebSocket support for real-time AI streaming

**Add to Phase H4 Checklist:**
- [ ] Add dependency: `go get github.com/gorilla/websocket`
- [ ] Create `internal/websocket/manager.go`:
  ```go
  package websocket

  import (
      "encoding/json"
      "log"
      "net/http"
      "sync"
      "time"

      "github.com/gorilla/websocket"
  )

  const (
      writeWait      = 10 * time.Second
      pongWait       = 60 * time.Second
      pingPeriod     = (pongWait * 9) / 10
      maxMessageSize = 512 * 1024 // 512KB
  )

  var upgrader = websocket.Upgrader{
      ReadBufferSize:  1024,
      WriteBufferSize: 1024,
      CheckOrigin: func(r *http.Request) bool {
          // In production, validate origin
          return true
      },
  }

  // Client represents a WebSocket client
  type Client struct {
      ID       string
      UserID   string
      conn     *websocket.Conn
      send     chan []byte
      manager  *Manager
  }

  // Manager handles WebSocket connections
  type Manager struct {
      clients    map[string]*Client
      register   chan *Client
      unregister chan *Client
      broadcast  chan *BroadcastMessage
      mu         sync.RWMutex
  }

  type BroadcastMessage struct {
      UserID  string
      Message []byte
  }

  func NewManager() *Manager {
      return &Manager{
          clients:    make(map[string]*Client),
          register:   make(chan *Client),
          unregister: make(chan *Client),
          broadcast:  make(chan *BroadcastMessage),
      }
  }

  // Run starts the manager goroutine
  func (m *Manager) Run() {
      for {
          select {
          case client := <-m.register:
              m.mu.Lock()
              m.clients[client.ID] = client
              m.mu.Unlock()
              log.Printf("Client connected: %s (user: %s)", client.ID, client.UserID)

          case client := <-m.unregister:
              m.mu.Lock()
              if _, ok := m.clients[client.ID]; ok {
                  delete(m.clients, client.ID)
                  close(client.send)
              }
              m.mu.Unlock()
              log.Printf("Client disconnected: %s", client.ID)

          case msg := <-m.broadcast:
              m.mu.RLock()
              for _, client := range m.clients {
                  if client.UserID == msg.UserID {
                      select {
                      case client.send <- msg.Message:
                      default:
                          close(client.send)
                          delete(m.clients, client.ID)
                      }
                  }
              }
              m.mu.RUnlock()
          }
      }
  }

  // HandleWebSocket handles WebSocket upgrade and connection
  func (m *Manager) HandleWebSocket(w http.ResponseWriter, r *http.Request, userID string) {
      conn, err := upgrader.Upgrade(w, r, nil)
      if err != nil {
          log.Printf("WebSocket upgrade failed: %v", err)
          return
      }

      client := &Client{
          ID:      generateID(),
          UserID:  userID,
          conn:    conn,
          send:    make(chan []byte, 256),
          manager: m,
      }

      m.register <- client

      go client.writePump()
      go client.readPump()
  }

  // SendToUser sends a message to all connections of a user
  func (m *Manager) SendToUser(userID string, message interface{}) error {
      data, err := json.Marshal(message)
      if err != nil {
          return err
      }

      m.broadcast <- &BroadcastMessage{
          UserID:  userID,
          Message: data,
      }
      return nil
  }

  func (c *Client) readPump() {
      defer func() {
          c.manager.unregister <- c
          c.conn.Close()
      }()

      c.conn.SetReadLimit(maxMessageSize)
      c.conn.SetReadDeadline(time.Now().Add(pongWait))
      c.conn.SetPongHandler(func(string) error {
          c.conn.SetReadDeadline(time.Now().Add(pongWait))
          return nil
      })

      for {
          _, message, err := c.conn.ReadMessage()
          if err != nil {
              if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
                  log.Printf("WebSocket error: %v", err)
              }
              break
          }

          // Handle incoming message
          c.handleMessage(message)
      }
  }

  func (c *Client) writePump() {
      ticker := time.NewTicker(pingPeriod)
      defer func() {
          ticker.Stop()
          c.conn.Close()
      }()

      for {
          select {
          case message, ok := <-c.send:
              c.conn.SetWriteDeadline(time.Now().Add(writeWait))
              if !ok {
                  c.conn.WriteMessage(websocket.CloseMessage, []byte{})
                  return
              }

              w, err := c.conn.NextWriter(websocket.TextMessage)
              if err != nil {
                  return
              }
              w.Write(message)

              // Write queued messages
              n := len(c.send)
              for i := 0; i < n; i++ {
                  w.Write([]byte{'\n'})
                  w.Write(<-c.send)
              }

              if err := w.Close(); err != nil {
                  return
              }

          case <-ticker.C:
              c.conn.SetWriteDeadline(time.Now().Add(writeWait))
              if err := c.conn.WriteMessage(websocket.PingMessage, nil); err != nil {
                  return
              }
          }
      }
  }

  func (c *Client) handleMessage(message []byte) {
      var msg map[string]interface{}
      if err := json.Unmarshal(message, &msg); err != nil {
          return
      }

      // Handle message based on type
      msgType, _ := msg["type"].(string)
      switch msgType {
      case "audio_chunk":
          // Handle audio chunk for transcription
          log.Printf("Received audio chunk from client %s", c.ID)
      case "ping":
          c.send <- []byte(`{"type":"pong"}`)
      }
  }

  func generateID() string {
      return fmt.Sprintf("%d", time.Now().UnixNano())
  }
  ```

**Verify:** WebSocket connections work with ping/pong keepalive

---

## FIX-B4: SSE Streaming Setup

**Goal:** Implement Server-Sent Events for AI response streaming

**Add to Phase H4 Checklist:**
- [ ] Create `internal/handlers/sse.go`:
  ```go
  package handlers

  import (
      "encoding/json"
      "fmt"
      "net/http"
      "time"

      "github.com/gin-gonic/gin"
  )

  // SSEClient represents a connected SSE client
  type SSEClient struct {
      ID       string
      UserID   string
      Messages chan SSEMessage
      Done     chan struct{}
  }

  // SSEMessage represents a server-sent event
  type SSEMessage struct {
      Event string      `json:"event"`
      Data  interface{} `json:"data"`
      ID    string      `json:"id,omitempty"`
      Retry int         `json:"retry,omitempty"`
  }

  // SSEManager manages SSE connections
  type SSEManager struct {
      clients map[string]*SSEClient
  }

  func NewSSEManager() *SSEManager {
      return &SSEManager{
          clients: make(map[string]*SSEClient),
      }
  }

  // StreamHandler handles SSE connections
  func (m *SSEManager) StreamHandler() gin.HandlerFunc {
      return func(c *gin.Context) {
          userID := c.GetString("userId")
          if userID == "" {
              c.JSON(http.StatusUnauthorized, gin.H{"error": "unauthorized"})
              return
          }

          // Set SSE headers
          c.Header("Content-Type", "text/event-stream")
          c.Header("Cache-Control", "no-cache")
          c.Header("Connection", "keep-alive")
          c.Header("Transfer-Encoding", "chunked")
          c.Header("X-Accel-Buffering", "no") // Disable nginx buffering

          // Create client
          client := &SSEClient{
              ID:       fmt.Sprintf("%s-%d", userID, time.Now().UnixNano()),
              UserID:   userID,
              Messages: make(chan SSEMessage, 100),
              Done:     make(chan struct{}),
          }

          m.clients[client.ID] = client
          defer func() {
              delete(m.clients, client.ID)
              close(client.Done)
          }()

          // Send initial connection event
          m.sendEvent(c, SSEMessage{
              Event: "connected",
              Data:  map[string]string{"clientId": client.ID},
          })

          // Keep-alive ticker
          ticker := time.NewTicker(30 * time.Second)
          defer ticker.Stop()

          // Stream events
          clientGone := c.Writer.CloseNotify()
          for {
              select {
              case <-clientGone:
                  return

              case msg := <-client.Messages:
                  m.sendEvent(c, msg)

              case <-ticker.C:
                  m.sendEvent(c, SSEMessage{
                      Event: "ping",
                      Data:  map[string]int64{"timestamp": time.Now().Unix()},
                  })
              }
          }
      }
  }

  func (m *SSEManager) sendEvent(c *gin.Context, msg SSEMessage) {
      data, _ := json.Marshal(msg.Data)

      if msg.ID != "" {
          fmt.Fprintf(c.Writer, "id: %s\n", msg.ID)
      }
      if msg.Retry > 0 {
          fmt.Fprintf(c.Writer, "retry: %d\n", msg.Retry)
      }
      fmt.Fprintf(c.Writer, "event: %s\n", msg.Event)
      fmt.Fprintf(c.Writer, "data: %s\n\n", data)

      c.Writer.Flush()
  }

  // SendToUser sends an event to all SSE clients of a user
  func (m *SSEManager) SendToUser(userID string, event string, data interface{}) {
      for _, client := range m.clients {
          if client.UserID == userID {
              select {
              case client.Messages <- SSEMessage{Event: event, Data: data}:
              default:
                  // Client buffer full, skip
              }
          }
      }
  }

  // StreamAIResponse streams AI response chunks via SSE
  func (m *SSEManager) StreamAIResponse(userID string, responseChan <-chan string) {
      messageID := fmt.Sprintf("msg-%d", time.Now().UnixNano())
      chunkIndex := 0

      for chunk := range responseChan {
          m.SendToUser(userID, "ai_chunk", map[string]interface{}{
              "messageId": messageID,
              "index":     chunkIndex,
              "content":   chunk,
              "done":      false,
          })
          chunkIndex++
      }

      // Send completion event
      m.SendToUser(userID, "ai_chunk", map[string]interface{}{
          "messageId": messageID,
          "index":     chunkIndex,
          "content":   "",
          "done":      true,
      })
  }

  // StreamAudioResponse streams audio delta chunks via SSE
  func (m *SSEManager) StreamAudioResponse(userID string, audioChan <-chan []byte) {
      for audioData := range audioChan {
          m.SendToUser(userID, "audio_delta", map[string]interface{}{
              "delta": base64.StdEncoding.EncodeToString(audioData),
          })
      }

      m.SendToUser(userID, "audio_done", map[string]interface{}{})
  }
  ```
- [ ] Create `internal/handlers/ai_stream.go`:
  ```go
  package handlers

  import (
      "net/http"

      "spec-manager/internal/services"
      "github.com/gin-gonic/gin"
  )

  type AIStreamHandler struct {
      aiService  *services.AIService
      sseManager *SSEManager
  }

  func NewAIStreamHandler(aiService *services.AIService, sseManager *SSEManager) *AIStreamHandler {
      return &AIStreamHandler{
          aiService:  aiService,
          sseManager: sseManager,
      }
  }

  type ChatRequest struct {
      Message string `json:"message" binding:"required"`
      Context string `json:"context"`
  }

  func (h *AIStreamHandler) Chat(c *gin.Context) {
      var req ChatRequest
      if err := c.ShouldBindJSON(&req); err != nil {
          c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
          return
      }

      userID := c.GetString("userId")

      // Start streaming in background
      go func() {
          responseChan := make(chan string, 100)
          go h.aiService.GenerateStreamingResponse(req.Message, req.Context, responseChan)
          h.sseManager.StreamAIResponse(userID, responseChan)
      }()

      c.JSON(http.StatusAccepted, gin.H{
          "success": true,
          "message": "Streaming started",
      })
  }

  func (h *AIStreamHandler) RegisterRoutes(r *gin.RouterGroup, authMiddleware gin.HandlerFunc) {
      ai := r.Group("/ai")
      ai.Use(authMiddleware)
      ai.GET("/stream", h.sseManager.StreamHandler())
      ai.POST("/chat", h.Chat)
  }
  ```

**Verify:** SSE events stream correctly with keepalive pings

---

## FIX-B5: Preset Management Handlers

**Goal:** CRUD handlers for managing presets

**Create new Phase F5a:**
- [ ] Create `internal/models/preset.go`:
  ```go
  package models

  import "time"

  type PresetType string

  const (
      PresetTypeSpec       PresetType = "spec"
      PresetTypeGuideline  PresetType = "guideline"
      PresetTypeInstruction PresetType = "instruction"
  )

  type Preset struct {
      Id          string     `json:"id"`
      Type        PresetType `json:"type"`
      Name        string     `json:"name"`
      Slug        string     `json:"slug"`
      Description *string    `json:"description"`
      Content     string     `json:"content"`
      IsDefault   bool       `json:"isDefault"`
      SortOrder   int        `json:"sortOrder"`
      CreatedAt   time.Time  `json:"createdAt"`
      UpdatedAt   time.Time  `json:"updatedAt"`
  }
  ```
- [ ] Create `migrations/003_presets.sql`:
  ```sql
  CREATE TABLE Preset (
      Id TEXT PRIMARY KEY,
      Type TEXT NOT NULL CHECK (Type IN ('spec', 'guideline', 'instruction')),
      Name TEXT NOT NULL,
      Slug TEXT NOT NULL,
      Description TEXT,
      Content TEXT NOT NULL,
      IsDefault INTEGER DEFAULT 0,
      SortOrder INTEGER DEFAULT 0,
      CreatedAt TEXT NOT NULL,
      UpdatedAt TEXT NOT NULL,
      UNIQUE(Type, Slug)
  );

  CREATE INDEX IX_Preset_Type ON Preset(Type);
  CREATE INDEX IX_Preset_IsDefault ON Preset(IsDefault);
  ```
- [ ] Create `internal/repository/preset_repo.go` with GORM:
  ```go
  package repository

  import (
      "time"

      "spec-manager/internal/models"
      "github.com/google/uuid"
      "gorm.io/gorm"
  )

  // ORM Policy: All database operations use GORM. Raw SQL is forbidden.

  type PresetRepo struct {
      db *gorm.DB
  }

  func NewPresetRepo(db *gorm.DB) *PresetRepo {
      return &PresetRepo{db: db}
  }

  func (r *PresetRepo) Create(preset *models.Preset) error {
      preset.Id = uuid.NewString()
      preset.CreatedAt = time.Now().UTC()
      preset.UpdatedAt = preset.CreatedAt
      
      return r.db.Create(preset).Error
  }

  func (r *PresetRepo) GetById(id string) (*models.Preset, error) {
      var preset models.Preset
      err := r.db.First(&preset, "id = ?", id).Error
      if err != nil {
          return nil, err
      }
      return &preset, nil
  }

  func (r *PresetRepo) ListByType(presetType models.PresetType) ([]models.Preset, error) {
      var presets []models.Preset
      err := r.db.
          Where("type = ?", presetType).
          Order("sort_order, name").
          Find(&presets).Error
      return presets, err
  }

  func (r *PresetRepo) Update(preset *models.Preset) error {
      preset.UpdatedAt = time.Now().UTC()
      return r.db.Save(preset).Error
  }

  func (r *PresetRepo) Delete(id string) error {
      return r.db.Delete(&models.Preset{}, "id = ?", id).Error
  }

  func (r *PresetRepo) GetDefault(presetType models.PresetType) (*models.Preset, error) {
      var preset models.Preset
      err := r.db.
          Where("type = ?", presetType).
          Where("is_default = ?", true).
          First(&preset).Error
      if err != nil {
          return nil, err
      }
      return &preset, nil
  }
  ```
- [ ] Create `internal/handlers/preset.go`:
  ```go
  package handlers

  import (
      "net/http"

      "spec-manager/internal/models"
      "spec-manager/internal/repository"
      "github.com/gin-gonic/gin"
  )

  type PresetHandler struct {
      repo *repository.PresetRepo
  }

  func NewPresetHandler(repo *repository.PresetRepo) *PresetHandler {
      return &PresetHandler{repo: repo}
  }

  func (h *PresetHandler) List(c *gin.Context) {
      presetType := models.PresetType(c.Query("type"))
      if presetType == "" {
          presetType = models.PresetTypeSpec
      }

      presets, err := h.repo.ListByType(presetType)
      if err != nil {
          c.JSON(http.StatusInternalServerError, gin.H{"success": false, "error": err.Error()})
          return
      }

      c.JSON(http.StatusOK, gin.H{"success": true, "data": presets})
  }

  func (h *PresetHandler) Create(c *gin.Context) {
      var preset models.Preset
      if err := c.ShouldBindJSON(&preset); err != nil {
          c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": err.Error()})
          return
      }

      if err := h.repo.Create(&preset); err != nil {
          c.JSON(http.StatusInternalServerError, gin.H{"success": false, "error": err.Error()})
          return
      }

      c.JSON(http.StatusCreated, gin.H{"success": true, "data": preset})
  }

  func (h *PresetHandler) Get(c *gin.Context) {
      id := c.Param("id")
      preset, err := h.repo.GetById(id)
      if err != nil {
          c.JSON(http.StatusNotFound, gin.H{"success": false, "error": "Preset not found"})
          return
      }

      c.JSON(http.StatusOK, gin.H{"success": true, "data": preset})
  }

  func (h *PresetHandler) Update(c *gin.Context) {
      id := c.Param("id")
      existing, err := h.repo.GetById(id)
      if err != nil {
          c.JSON(http.StatusNotFound, gin.H{"success": false, "error": "Preset not found"})
          return
      }

      if err := c.ShouldBindJSON(existing); err != nil {
          c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": err.Error()})
          return
      }

      if err := h.repo.Update(existing); err != nil {
          c.JSON(http.StatusInternalServerError, gin.H{"success": false, "error": err.Error()})
          return
      }

      c.JSON(http.StatusOK, gin.H{"success": true, "data": existing})
  }

  func (h *PresetHandler) Delete(c *gin.Context) {
      id := c.Param("id")
      if err := h.repo.Delete(id); err != nil {
          c.JSON(http.StatusInternalServerError, gin.H{"success": false, "error": err.Error()})
          return
      }

      c.JSON(http.StatusOK, gin.H{"success": true, "data": nil})
  }

  func (h *PresetHandler) RegisterRoutes(r *gin.RouterGroup, authMiddleware gin.HandlerFunc) {
      presets := r.Group("/presets")
      presets.Use(authMiddleware)
      presets.GET("", h.List)
      presets.POST("", h.Create)
      presets.GET("/:id", h.Get)
      presets.PUT("/:id", h.Update)
      presets.DELETE("/:id", h.Delete)
  }
  ```

**Verify:** CRUD operations work for presets

---

## FIX-B6: Guidelines Management Handlers

**Goal:** CRUD handlers for managing coding guidelines

**Create new Phase F5b:**
- [ ] Create `internal/models/guideline.go`:
  ```go
  package models

  import "time"

  type GuidelineCategory string

  const (
      GuidelineCategoryNaming     GuidelineCategory = "naming"
      GuidelineCategoryStructure  GuidelineCategory = "structure"
      GuidelineCategoryTesting    GuidelineCategory = "testing"
      GuidelineCategorySecurity   GuidelineCategory = "security"
      GuidelineCategoryPerformance GuidelineCategory = "performance"
  )

  type Guideline struct {
      Id          string            `json:"id"`
      ProjectId   *string           `json:"projectId"` // nil = global
      Category    GuidelineCategory `json:"category"`
      Title       string            `json:"title"`
      Description string            `json:"description"`
      Rule        string            `json:"rule"`
      Examples    string            `json:"examples"` // JSON array
      IsActive    bool              `json:"isActive"`
      Priority    int               `json:"priority"`
      CreatedAt   time.Time         `json:"createdAt"`
      UpdatedAt   time.Time         `json:"updatedAt"`
  }
  ```
- [ ] Create `internal/models/guideline.go` with GORM model:
  ```go
  package models

  import (
      "time"
      "gorm.io/gorm"
  )

  // ORM Policy: All database operations use GORM. Raw SQL is forbidden.

  type GuidelineCategory string

  const (
      GuidelineCategoryNaming      GuidelineCategory = "naming"
      GuidelineCategoryStructure   GuidelineCategory = "structure"
      GuidelineCategoryTesting     GuidelineCategory = "testing"
      GuidelineCategorySecurity    GuidelineCategory = "security"
      GuidelineCategoryPerformance GuidelineCategory = "performance"
  )

  // Guideline defines a coding guideline/rule
  type Guideline struct {
      Id          string            `gorm:"primaryKey;type:text"`
      ProjectId   *string           `gorm:"type:text;index"`
      Category    GuidelineCategory `gorm:"type:text;not null;index"`
      Title       string            `gorm:"type:text;not null"`
      Description string            `gorm:"type:text;not null"`
      Rule        string            `gorm:"type:text;not null"`
      Examples    string            `gorm:"type:text;default:'[]'"`
      IsActive    bool              `gorm:"default:true;index"`
      Priority    int               `gorm:"default:0"`
      CreatedAt   time.Time         `gorm:"not null"`
      UpdatedAt   time.Time         `gorm:"not null"`
      DeletedAt   gorm.DeletedAt    `gorm:"index"`
      
      // Relationships
      Project *Project `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
  }
  ```
- [ ] Create `internal/repository/guideline_repo.go` with GORM:
  ```go
  package repository

  import (
      "time"

      "spec-manager/internal/models"
      "github.com/google/uuid"
      "gorm.io/gorm"
  )

  // ORM Policy: All database operations use GORM. Raw SQL is forbidden.

  type GuidelineRepo struct {
      db *gorm.DB
  }

  func NewGuidelineRepo(db *gorm.DB) *GuidelineRepo {
      return &GuidelineRepo{db: db}
  }

  func (r *GuidelineRepo) Create(g *models.Guideline) error {
      g.Id = uuid.NewString()
      g.CreatedAt = time.Now().UTC()
      g.UpdatedAt = g.CreatedAt
      
      return r.db.Create(g).Error
  }

  func (r *GuidelineRepo) ListForProject(projectId *string, category *models.GuidelineCategory, activeOnly bool) ([]models.Guideline, error) {
      var guidelines []models.Guideline
      
      query := r.db.Where("project_id = ? OR project_id IS NULL", projectId)
      
      if category != nil {
          query = query.Where("category = ?", *category)
      }
      
      if activeOnly {
          query = query.Where("is_active = ?", true)
      }
      
      err := query.Order("priority DESC, title").Find(&guidelines).Error
      return guidelines, err
  }

  func (r *GuidelineRepo) Update(g *models.Guideline) error {
      g.UpdatedAt = time.Now().UTC()
      return r.db.Save(g).Error
  }

  func (r *GuidelineRepo) Delete(id string) error {
      return r.db.Delete(&models.Guideline{}, "id = ?", id).Error
  }

  func (r *GuidelineRepo) ToggleActive(id string, isActive bool) error {
      return r.db.Model(&models.Guideline{}).
          Where("id = ?", id).
          Updates(map[string]interface{}{
              "is_active":  isActive,
              "updated_at": time.Now().UTC(),
          }).Error
  }
  ```

**Verify:** Guidelines CRUD works with project scoping

---

## FIX-B7: Brute-Force Lockout

**Goal:** Implement progressive login lockout for brute-force protection

**Add to Phase A11:**
- [ ] Create `internal/services/lockout.go`:
  ```go
  package services

  import (
      "sync"
      "time"
  )

  // LockoutConfig defines lockout escalation thresholds
  type LockoutConfig struct {
      Thresholds []LockoutThreshold
  }

  type LockoutThreshold struct {
      Attempts int
      Duration time.Duration
  }

  func DefaultLockoutConfig() *LockoutConfig {
      return &LockoutConfig{
          Thresholds: []LockoutThreshold{
              {Attempts: 3, Duration: 30 * time.Second},
              {Attempts: 5, Duration: 5 * time.Minute},
              {Attempts: 10, Duration: 30 * time.Minute},
              {Attempts: 20, Duration: 24 * time.Hour}, // Permanent until manual unlock
          },
      }
  }

  // LockoutService manages login attempt tracking and lockouts
  type LockoutService struct {
      config   *LockoutConfig
      attempts map[string]*AttemptRecord
      mu       sync.RWMutex
  }

  type AttemptRecord struct {
      Count       int
      FirstAttempt time.Time
      LastAttempt  time.Time
      LockedUntil  *time.Time
  }

  func NewLockoutService(config *LockoutConfig) *LockoutService {
      if config == nil {
          config = DefaultLockoutConfig()
      }
      return &LockoutService{
          config:   config,
          attempts: make(map[string]*AttemptRecord),
      }
  }

  // IsLocked checks if an identifier (username/IP) is currently locked
  func (s *LockoutService) IsLocked(identifier string) (bool, time.Duration) {
      s.mu.RLock()
      defer s.mu.RUnlock()

      record, exists := s.attempts[identifier]
      if !exists || record.LockedUntil == nil {
          return false, 0
      }

      remaining := time.Until(*record.LockedUntil)
      if remaining <= 0 {
          return false, 0
      }

      return true, remaining
  }

  // RecordFailedAttempt records a failed login attempt
  func (s *LockoutService) RecordFailedAttempt(identifier string) (locked bool, duration time.Duration) {
      s.mu.Lock()
      defer s.mu.Unlock()

      now := time.Now()
      record, exists := s.attempts[identifier]

      if !exists {
          record = &AttemptRecord{
              Count:        0,
              FirstAttempt: now,
          }
          s.attempts[identifier] = record
      }

      // Reset if last attempt was more than 1 hour ago
      if now.Sub(record.LastAttempt) > time.Hour {
          record.Count = 0
          record.FirstAttempt = now
          record.LockedUntil = nil
      }

      record.Count++
      record.LastAttempt = now

      // Check for lockout threshold
      for i := len(s.config.Thresholds) - 1; i >= 0; i-- {
          threshold := s.config.Thresholds[i]
          if record.Count >= threshold.Attempts {
              lockUntil := now.Add(threshold.Duration)
              record.LockedUntil = &lockUntil
              return true, threshold.Duration
          }
      }

      return false, 0
  }

  // RecordSuccessfulLogin clears the attempt record on successful login
  func (s *LockoutService) RecordSuccessfulLogin(identifier string) {
      s.mu.Lock()
      defer s.mu.Unlock()

      delete(s.attempts, identifier)
  }

  // ManualUnlock forcefully unlocks an identifier
  func (s *LockoutService) ManualUnlock(identifier string) {
      s.mu.Lock()
      defer s.mu.Unlock()

      if record, exists := s.attempts[identifier]; exists {
          record.LockedUntil = nil
          record.Count = 0
      }
  }

  // GetAttemptCount returns current failed attempt count
  func (s *LockoutService) GetAttemptCount(identifier string) int {
      s.mu.RLock()
      defer s.mu.RUnlock()

      if record, exists := s.attempts[identifier]; exists {
          return record.Count
      }
      return 0
  }

  // Cleanup removes stale records older than 24 hours
  func (s *LockoutService) Cleanup() int {
      s.mu.Lock()
      defer s.mu.Unlock()

      threshold := time.Now().Add(-24 * time.Hour)
      removed := 0

      for id, record := range s.attempts {
          if record.LastAttempt.Before(threshold) {
              delete(s.attempts, id)
              removed++
          }
      }

      return removed
  }
  ```
- [ ] Update `internal/services/auth_service.go`:
  ```go
  // Add lockout service to AuthService
  type AuthService struct {
      userRepo    *repository.UserRepo
      sessionRepo *repository.SessionRepo
      jwt         *utils.JWTUtil
      lockout     *LockoutService // Add this
  }

  func (s *AuthService) Login(req LoginRequest, deviceInfo string) (*utils.TokenPair, *models.User, error) {
      identifier := req.Username

      // Check if locked out
      if locked, remaining := s.lockout.IsLocked(identifier); locked {
          return nil, nil, fmt.Errorf("account temporarily locked, try again in %s", remaining.Round(time.Second))
      }

      // Find user
      user, err := s.userRepo.GetByUsername(req.Username)
      if err != nil {
          user, err = s.userRepo.GetByEmail(req.Username)
      }
      if err != nil || user == nil {
          s.lockout.RecordFailedAttempt(identifier)
          return nil, nil, ErrInvalidCredentials
      }

      // Verify password
      if !utils.VerifyPassword(user.PasswordHash, req.Password) {
          locked, duration := s.lockout.RecordFailedAttempt(identifier)
          if locked {
              return nil, nil, fmt.Errorf("too many failed attempts, account locked for %s", duration.Round(time.Second))
          }
          return nil, nil, ErrInvalidCredentials
      }

      // Successful login - clear lockout
      s.lockout.RecordSuccessfulLogin(identifier)

      // ... rest of login logic
  }
  ```

**Verify:** Lockout escalates correctly after failed attempts

---

## FIX-B8: Session Cleanup Cron

**Goal:** Automatically clean up expired sessions

**Add to Phase A10:**
- [ ] Create `internal/services/session_cleanup.go`:
  ```go
  package services

  import (
      "log"
      "time"

      "spec-manager/internal/repository"
  )

  // SessionCleanupService handles expired session cleanup
  type SessionCleanupService struct {
      sessionRepo *repository.SessionRepo
      interval    time.Duration
      stopCh      chan struct{}
  }

  func NewSessionCleanupService(sessionRepo *repository.SessionRepo, interval time.Duration) *SessionCleanupService {
      return &SessionCleanupService{
          sessionRepo: sessionRepo,
          interval:    interval,
          stopCh:      make(chan struct{}),
      }
  }

  // Start begins the cleanup goroutine
  func (s *SessionCleanupService) Start() {
      go s.run()
      log.Printf("Session cleanup service started (interval: %s)", s.interval)
  }

  // Stop gracefully stops the cleanup service
  func (s *SessionCleanupService) Stop() {
      close(s.stopCh)
  }

  func (s *SessionCleanupService) run() {
      ticker := time.NewTicker(s.interval)
      defer ticker.Stop()

      // Run immediately on start
      s.cleanup()

      for {
          select {
          case <-ticker.C:
              s.cleanup()
          case <-s.stopCh:
              log.Println("Session cleanup service stopped")
              return
          }
      }
  }

  func (s *SessionCleanupService) cleanup() {
      deleted, err := s.sessionRepo.CleanExpired()
      if err != nil {
          log.Printf("Session cleanup error: %v", err)
          return
      }

      if deleted > 0 {
          log.Printf("Session cleanup: removed %d expired sessions", deleted)
      }
  }

  // CleanupNow triggers an immediate cleanup
  func (s *SessionCleanupService) CleanupNow() (int64, error) {
      return s.sessionRepo.CleanExpired()
  }
  ```
- [ ] Update `internal/repository/session_repo.go` with GORM cleanup methods:
  ```go
  // ORM Policy: All database operations use GORM. Raw SQL is forbidden.

  // CleanRevokedSessions removes sessions revoked more than 7 days ago
  func (r *SessionRepo) CleanRevokedSessions() (int64, error) {
      threshold := time.Now().Add(-7 * 24 * time.Hour).UTC()
      result := r.db.
          Where("revoked_at IS NOT NULL").
          Where("revoked_at < ?", threshold).
          Delete(&models.Session{})
      
      return result.RowsAffected, result.Error
  }

  // GetActiveSessions returns all active sessions for a user
  func (r *SessionRepo) GetActiveSessions(userId string) ([]models.Session, error) {
      var sessions []models.Session
      now := time.Now().UTC()
      
      err := r.db.
          Where("user_id = ?", userId).
          Where("revoked_at IS NULL").
          Where("expires_at > ?", now).
          Order("created_at DESC").
          Find(&sessions).Error
      
      return sessions, err
  }

  // RevokeAllUserSessions revokes all sessions for a user (e.g., on password change)
  func (r *SessionRepo) RevokeAllUserSessions(userId string, exceptSessionId string) error {
      now := time.Now().UTC()
      return r.db.Model(&models.Session{}).
          Where("user_id = ?", userId).
          Where("id != ?", exceptSessionId).
          Where("revoked_at IS NULL").
          Update("revoked_at", now).Error
  }
  ```
- [ ] Update `main.go` to start session cleanup:
  ```go
  // In main()
  sessionCleanup := services.NewSessionCleanupService(
      sessionRepo,
      6*time.Hour, // Run every 6 hours
  )
  sessionCleanup.Start()
  defer sessionCleanup.Stop()
  ```

**Verify:** Expired and revoked sessions are automatically cleaned up

---

# FIX-C: Frontend Medium Fixes (Complete)

## FIX-C1: FolderSyncWizard TypeScript Types

**Goal:** Define complete type system for folder synchronization

**Add to Phase E frontend:**
- [ ] Create `src/types/folder-sync.ts`:
  ```typescript
  // ============================================================
  // Folder Sync Types - Complete Type Definitions
  // ============================================================

  /** Sync direction configuration */
  export type SyncDirection = 'pull' | 'push' | 'bidirectional';

  /** File conflict resolution strategy */
  export type ConflictResolution = 'local' | 'remote' | 'newest' | 'manual';

  /** Current sync operation status */
  export type SyncStatus = 
    | 'idle' 
    | 'connecting' 
    | 'scanning' 
    | 'syncing' 
    | 'resolving-conflicts'
    | 'completed' 
    | 'error'
    | 'cancelled';

  /** Individual file sync state */
  export type FileSyncState = 
    | 'pending' 
    | 'uploading' 
    | 'downloading' 
    | 'conflict' 
    | 'synced' 
    | 'error'
    | 'skipped';

  /** Folder sync configuration */
  export interface FolderSyncConfig {
    id: string;
    projectId: string;
    localPath: string;
    remotePath: string;
    direction: SyncDirection;
    conflictResolution: ConflictResolution;
    excludePatterns: string[];
    includePatterns: string[];
    autoSync: boolean;
    syncIntervalMinutes: number;
    lastSyncAt: string | null;
    createdAt: string;
    updatedAt: string;
  }

  /** File metadata for sync comparison */
  export interface SyncFileInfo {
    path: string;
    relativePath: string;
    size: number;
    modifiedAt: string;
    hash: string;
    isDirectory: boolean;
  }

  /** File conflict details */
  export interface FileConflict {
    id: string;
    filePath: string;
    localFile: SyncFileInfo;
    remoteFile: SyncFileInfo;
    conflictType: 'modified-both' | 'deleted-remote' | 'deleted-local' | 'type-mismatch';
    resolution: ConflictResolution | null;
    resolvedAt: string | null;
  }

  /** Individual file sync operation */
  export interface FileSyncOperation {
    id: string;
    filePath: string;
    operation: 'upload' | 'download' | 'delete' | 'skip';
    state: FileSyncState;
    progress: number; // 0-100
    bytesTransferred: number;
    totalBytes: number;
    error: string | null;
    startedAt: string | null;
    completedAt: string | null;
  }

  /** Overall sync session state */
  export interface SyncSession {
    id: string;
    configId: string;
    status: SyncStatus;
    totalFiles: number;
    processedFiles: number;
    totalBytes: number;
    transferredBytes: number;
    operations: FileSyncOperation[];
    conflicts: FileConflict[];
    errors: SyncError[];
    startedAt: string;
    completedAt: string | null;
  }

  /** Sync error details */
  export interface SyncError {
    code: string;
    message: string;
    filePath: string | null;
    recoverable: boolean;
    timestamp: string;
  }

  /** Wizard step configuration */
  export interface WizardStep {
    id: string;
    title: string;
    description: string;
    isComplete: boolean;
    isActive: boolean;
    isDisabled: boolean;
  }

  /** Wizard state */
  export interface FolderSyncWizardState {
    currentStep: number;
    steps: WizardStep[];
    config: Partial<FolderSyncConfig>;
    validation: Record<string, string | null>;
    isSubmitting: boolean;
  }

  /** API request/response types */
  export interface CreateSyncConfigRequest {
    projectId: string;
    localPath: string;
    remotePath: string;
    direction: SyncDirection;
    conflictResolution: ConflictResolution;
    excludePatterns?: string[];
    includePatterns?: string[];
    autoSync?: boolean;
    syncIntervalMinutes?: number;
  }

  export interface StartSyncRequest {
    configId: string;
    dryRun?: boolean;
  }

  export interface ResolvConflictRequest {
    conflictId: string;
    resolution: ConflictResolution;
    useForAll?: boolean;
  }

  export interface SyncProgressEvent {
    type: 'progress' | 'file-complete' | 'conflict' | 'error' | 'complete';
    sessionId: string;
    data: {
      processedFiles?: number;
      totalFiles?: number;
      transferredBytes?: number;
      totalBytes?: number;
      currentFile?: string;
      operation?: FileSyncOperation;
      conflict?: FileConflict;
      error?: SyncError;
    };
  }
  ```

**Verify:** Types compile without errors, cover all sync scenarios

---

## FIX-C2: FolderSyncWizard React Component

**Goal:** Complete wizard UI for configuring folder sync

**Add to Phase E frontend:**
- [ ] Create `src/components/folder-sync/FolderSyncWizard.tsx`:
  ```tsx
  import React, { useState, useCallback, useMemo } from 'react';
  import { Button } from '@/components/ui/button';
  import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
  import { Input } from '@/components/ui/input';
  import { Label } from '@/components/ui/label';
  import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
  import { Switch } from '@/components/ui/switch';
  import { Textarea } from '@/components/ui/textarea';
  import { Progress } from '@/components/ui/progress';
  import { Badge } from '@/components/ui/badge';
  import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
  import { 
    FolderSync, ArrowRight, ArrowLeft, Check, X, 
    Folder, RefreshCw, AlertTriangle, Settings 
  } from 'lucide-react';
  import { 
    FolderSyncConfig, 
    SyncDirection, 
    ConflictResolution,
    FolderSyncWizardState,
    WizardStep 
  } from '@/types/folder-sync';

  interface FolderSyncWizardProps {
    projectId: string;
    onComplete: (config: FolderSyncConfig) => void;
    onCancel: () => void;
    existingConfig?: FolderSyncConfig;
  }

  const WIZARD_STEPS: Omit<WizardStep, 'isComplete' | 'isActive' | 'isDisabled'>[] = [
    { id: 'paths', title: 'Select Paths', description: 'Choose local and remote folders' },
    { id: 'direction', title: 'Sync Direction', description: 'Configure how files sync' },
    { id: 'filters', title: 'File Filters', description: 'Include/exclude patterns' },
    { id: 'schedule', title: 'Schedule', description: 'Auto-sync settings' },
    { id: 'review', title: 'Review', description: 'Confirm configuration' },
  ];

  export function FolderSyncWizard({ 
    projectId, 
    onComplete, 
    onCancel, 
    existingConfig 
  }: FolderSyncWizardProps) {
    const [state, setState] = useState<FolderSyncWizardState>(() => ({
      currentStep: 0,
      steps: WIZARD_STEPS.map((step, idx) => ({
        ...step,
        isComplete: false,
        isActive: idx === 0,
        isDisabled: idx > 0,
      })),
      config: existingConfig ?? {
        projectId,
        localPath: '',
        remotePath: '',
        direction: 'bidirectional',
        conflictResolution: 'newest',
        excludePatterns: ['node_modules', '.git', '*.log'],
        includePatterns: ['*'],
        autoSync: false,
        syncIntervalMinutes: 30,
      },
      validation: {},
      isSubmitting: false,
    }));

    const updateConfig = useCallback(<K extends keyof FolderSyncConfig>(
      key: K, 
      value: FolderSyncConfig[K]
    ) => {
      setState(prev => ({
        ...prev,
        config: { ...prev.config, [key]: value },
        validation: { ...prev.validation, [key]: null },
      }));
    }, []);

    const validateStep = useCallback((stepIndex: number): boolean => {
      const { config } = state;
      const errors: Record<string, string> = {};

      switch (stepIndex) {
        case 0: // Paths
          if (!config.localPath?.trim()) {
            errors.localPath = 'Local path is required';
          }
          if (!config.remotePath?.trim()) {
            errors.remotePath = 'Remote path is required';
          }
          break;
        case 1: // Direction
          if (!config.direction) {
            errors.direction = 'Select a sync direction';
          }
          if (!config.conflictResolution) {
            errors.conflictResolution = 'Select a conflict resolution strategy';
          }
          break;
        // Steps 2, 3 have no required fields
      }

      setState(prev => ({ ...prev, validation: errors }));
      return Object.keys(errors).length === 0;
    }, [state.config]);

    const goToStep = useCallback((stepIndex: number) => {
      if (stepIndex < 0 || stepIndex >= WIZARD_STEPS.length) return;
      
      // Validate current step before advancing
      if (stepIndex > state.currentStep && !validateStep(state.currentStep)) {
        return;
      }

      setState(prev => ({
        ...prev,
        currentStep: stepIndex,
        steps: prev.steps.map((step, idx) => ({
          ...step,
          isActive: idx === stepIndex,
          isComplete: idx < stepIndex,
          isDisabled: idx > stepIndex + 1,
        })),
      }));
    }, [state.currentStep, validateStep]);

    const handleSubmit = useCallback(async () => {
      if (!validateStep(state.currentStep)) return;

      setState(prev => ({ ...prev, isSubmitting: true }));

      try {
        const response = await fetch('/api/v1/folder-sync/configs', {
          method: existingConfig ? 'PUT' : 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(state.config),
        });

        if (!response.ok) {
          throw new Error('Failed to save configuration');
        }

        const { data } = await response.json();
        onComplete(data);
      } catch (error) {
        setState(prev => ({
          ...prev,
          isSubmitting: false,
          validation: { submit: error instanceof Error ? error.message : 'Unknown error' },
        }));
      }
    }, [state.config, state.currentStep, validateStep, existingConfig, onComplete]);

    const progressPercent = useMemo(() => 
      ((state.currentStep + 1) / WIZARD_STEPS.length) * 100
    , [state.currentStep]);

    return (
      <Card className="w-full max-w-2xl mx-auto">
        <CardHeader>
          <div className="flex items-center gap-2">
            <FolderSync className="h-6 w-6 text-primary" />
            <CardTitle>
              {existingConfig ? 'Edit Folder Sync' : 'Setup Folder Sync'}
            </CardTitle>
          </div>
          <CardDescription>
            {WIZARD_STEPS[state.currentStep].description}
          </CardDescription>
          <Progress value={progressPercent} className="mt-4" />
          
          {/* Step indicators */}
          <div className="flex justify-between mt-4">
            {state.steps.map((step, idx) => (
              <button
                key={step.id}
                onClick={() => !step.isDisabled && goToStep(idx)}
                disabled={step.isDisabled}
                className={`flex flex-col items-center gap-1 p-2 rounded-lg transition-colors ${
                  step.isActive 
                    ? 'bg-primary/10 text-primary' 
                    : step.isComplete 
                      ? 'text-green-600' 
                      : step.isDisabled 
                        ? 'text-muted-foreground opacity-50' 
                        : 'text-muted-foreground hover:bg-muted'
                }`}
              >
                <div className={`w-8 h-8 rounded-full flex items-center justify-center border-2 ${
                  step.isComplete 
                    ? 'border-green-500 bg-green-500 text-white' 
                    : step.isActive 
                      ? 'border-primary' 
                      : 'border-muted-foreground'
                }`}>
                  {step.isComplete ? <Check className="w-4 h-4" /> : idx + 1}
                </div>
                <span className="text-xs font-medium">{step.title}</span>
              </button>
            ))}
          </div>
        </CardHeader>

        <CardContent className="min-h-[300px]">
          {state.currentStep === 0 && (
            <PathSelectionStep 
              config={state.config}
              validation={state.validation}
              onUpdate={updateConfig}
            />
          )}
          {state.currentStep === 1 && (
            <DirectionStep 
              config={state.config}
              validation={state.validation}
              onUpdate={updateConfig}
            />
          )}
          {state.currentStep === 2 && (
            <FiltersStep 
              config={state.config}
              onUpdate={updateConfig}
            />
          )}
          {state.currentStep === 3 && (
            <ScheduleStep 
              config={state.config}
              onUpdate={updateConfig}
            />
          )}
          {state.currentStep === 4 && (
            <ReviewStep config={state.config} />
          )}

          {state.validation.submit && (
            <Alert variant="destructive" className="mt-4">
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>Error</AlertTitle>
              <AlertDescription>{state.validation.submit}</AlertDescription>
            </Alert>
          )}
        </CardContent>

        <CardFooter className="flex justify-between">
          <Button variant="outline" onClick={onCancel}>
            Cancel
          </Button>
          
          <div className="flex gap-2">
            {state.currentStep > 0 && (
              <Button 
                variant="outline" 
                onClick={() => goToStep(state.currentStep - 1)}
              >
                <ArrowLeft className="w-4 h-4 mr-2" />
                Back
              </Button>
            )}
            
            {state.currentStep < WIZARD_STEPS.length - 1 ? (
              <Button onClick={() => goToStep(state.currentStep + 1)}>
                Next
                <ArrowRight className="w-4 h-4 ml-2" />
              </Button>
            ) : (
              <Button 
                onClick={handleSubmit} 
                disabled={state.isSubmitting}
              >
                {state.isSubmitting ? (
                  <>
                    <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                    Saving...
                  </>
                ) : (
                  <>
                    <Check className="w-4 h-4 mr-2" />
                    {existingConfig ? 'Update' : 'Create'} Configuration
                  </>
                )}
              </Button>
            )}
          </div>
        </CardFooter>
      </Card>
    );
  }

  // ============================================================
  // Step Components
  // ============================================================

  interface StepProps {
    config: Partial<FolderSyncConfig>;
    validation?: Record<string, string | null>;
    onUpdate: <K extends keyof FolderSyncConfig>(key: K, value: FolderSyncConfig[K]) => void;
  }

  function PathSelectionStep({ config, validation, onUpdate }: StepProps) {
    return (
      <div className="space-y-6">
        <div className="space-y-2">
          <Label htmlFor="localPath">
            <Folder className="w-4 h-4 inline mr-2" />
            Local Folder Path
          </Label>
          <Input
            id="localPath"
            value={config.localPath ?? ''}
            onChange={(e) => onUpdate('localPath', e.target.value)}
            placeholder="/path/to/local/folder"
            className={validation?.localPath ? 'border-destructive' : ''}
          />
          {validation?.localPath && (
            <p className="text-sm text-destructive">{validation.localPath}</p>
          )}
          <p className="text-sm text-muted-foreground">
            The folder on your local machine to sync
          </p>
        </div>

        <div className="space-y-2">
          <Label htmlFor="remotePath">
            <Folder className="w-4 h-4 inline mr-2" />
            Remote Folder Path
          </Label>
          <Input
            id="remotePath"
            value={config.remotePath ?? ''}
            onChange={(e) => onUpdate('remotePath', e.target.value)}
            placeholder="specs/my-project"
            className={validation?.remotePath ? 'border-destructive' : ''}
          />
          {validation?.remotePath && (
            <p className="text-sm text-destructive">{validation.remotePath}</p>
          )}
          <p className="text-sm text-muted-foreground">
            The folder path within the project repository
          </p>
        </div>
      </div>
    );
  }

  function DirectionStep({ config, validation, onUpdate }: StepProps) {
    return (
      <div className="space-y-6">
        <div className="space-y-3">
          <Label>Sync Direction</Label>
          <RadioGroup
            value={config.direction}
            onValueChange={(value) => onUpdate('direction', value as SyncDirection)}
          >
            <div className="flex items-center space-x-2 p-3 rounded-lg border hover:bg-muted/50">
              <RadioGroupItem value="pull" id="pull" />
              <Label htmlFor="pull" className="flex-1 cursor-pointer">
                <span className="font-medium">Pull Only</span>
                <p className="text-sm text-muted-foreground">
                  Download remote changes to local folder
                </p>
              </Label>
            </div>
            <div className="flex items-center space-x-2 p-3 rounded-lg border hover:bg-muted/50">
              <RadioGroupItem value="push" id="push" />
              <Label htmlFor="push" className="flex-1 cursor-pointer">
                <span className="font-medium">Push Only</span>
                <p className="text-sm text-muted-foreground">
                  Upload local changes to remote folder
                </p>
              </Label>
            </div>
            <div className="flex items-center space-x-2 p-3 rounded-lg border hover:bg-muted/50">
              <RadioGroupItem value="bidirectional" id="bidirectional" />
              <Label htmlFor="bidirectional" className="flex-1 cursor-pointer">
                <span className="font-medium">Bidirectional</span>
                <p className="text-sm text-muted-foreground">
                  Sync changes in both directions
                </p>
              </Label>
            </div>
          </RadioGroup>
        </div>

        <div className="space-y-3">
          <Label>Conflict Resolution</Label>
          <RadioGroup
            value={config.conflictResolution}
            onValueChange={(value) => onUpdate('conflictResolution', value as ConflictResolution)}
          >
            <div className="flex items-center space-x-2 p-3 rounded-lg border hover:bg-muted/50">
              <RadioGroupItem value="newest" id="newest" />
              <Label htmlFor="newest" className="flex-1 cursor-pointer">
                <span className="font-medium">Use Newest</span>
                <p className="text-sm text-muted-foreground">
                  Keep the most recently modified version
                </p>
              </Label>
            </div>
            <div className="flex items-center space-x-2 p-3 rounded-lg border hover:bg-muted/50">
              <RadioGroupItem value="local" id="local" />
              <Label htmlFor="local" className="flex-1 cursor-pointer">
                <span className="font-medium">Prefer Local</span>
                <p className="text-sm text-muted-foreground">
                  Always keep local version on conflict
                </p>
              </Label>
            </div>
            <div className="flex items-center space-x-2 p-3 rounded-lg border hover:bg-muted/50">
              <RadioGroupItem value="remote" id="remote" />
              <Label htmlFor="remote" className="flex-1 cursor-pointer">
                <span className="font-medium">Prefer Remote</span>
                <p className="text-sm text-muted-foreground">
                  Always keep remote version on conflict
                </p>
              </Label>
            </div>
            <div className="flex items-center space-x-2 p-3 rounded-lg border hover:bg-muted/50">
              <RadioGroupItem value="manual" id="manual" />
              <Label htmlFor="manual" className="flex-1 cursor-pointer">
                <span className="font-medium">Ask Each Time</span>
                <p className="text-sm text-muted-foreground">
                  Prompt for resolution on each conflict
                </p>
              </Label>
            </div>
          </RadioGroup>
        </div>
      </div>
    );
  }

  function FiltersStep({ config, onUpdate }: Omit<StepProps, 'validation'>) {
    return (
      <div className="space-y-6">
        <div className="space-y-2">
          <Label htmlFor="excludePatterns">Exclude Patterns</Label>
          <Textarea
            id="excludePatterns"
            value={config.excludePatterns?.join('\n') ?? ''}
            onChange={(e) => onUpdate('excludePatterns', 
              e.target.value.split('\n').filter(p => p.trim())
            )}
            placeholder="node_modules&#10;.git&#10;*.log"
            rows={5}
          />
          <p className="text-sm text-muted-foreground">
            One pattern per line. Files matching these patterns will be ignored.
          </p>
        </div>

        <div className="space-y-2">
          <Label htmlFor="includePatterns">Include Patterns</Label>
          <Textarea
            id="includePatterns"
            value={config.includePatterns?.join('\n') ?? ''}
            onChange={(e) => onUpdate('includePatterns', 
              e.target.value.split('\n').filter(p => p.trim())
            )}
            placeholder="*.md&#10;*.json&#10;src/**/*"
            rows={5}
          />
          <p className="text-sm text-muted-foreground">
            One pattern per line. Only files matching these patterns will be synced.
            Leave as * to include all files.
          </p>
        </div>
      </div>
    );
  }

  function ScheduleStep({ config, onUpdate }: Omit<StepProps, 'validation'>) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between p-4 rounded-lg border">
          <div className="space-y-0.5">
            <Label htmlFor="autoSync">Enable Auto-Sync</Label>
            <p className="text-sm text-muted-foreground">
              Automatically sync files at regular intervals
            </p>
          </div>
          <Switch
            id="autoSync"
            checked={config.autoSync ?? false}
            onCheckedChange={(checked) => onUpdate('autoSync', checked)}
          />
        </div>

        {config.autoSync && (
          <div className="space-y-2">
            <Label htmlFor="syncInterval">Sync Interval (minutes)</Label>
            <Input
              id="syncInterval"
              type="number"
              min={5}
              max={1440}
              value={config.syncIntervalMinutes ?? 30}
              onChange={(e) => onUpdate('syncIntervalMinutes', parseInt(e.target.value) || 30)}
            />
            <p className="text-sm text-muted-foreground">
              How often to check for changes (5-1440 minutes)
            </p>
          </div>
        )}

        <Alert>
          <Settings className="h-4 w-4" />
          <AlertTitle>Manual Sync</AlertTitle>
          <AlertDescription>
            You can always trigger a sync manually from the project dashboard,
            regardless of auto-sync settings.
          </AlertDescription>
        </Alert>
      </div>
    );
  }

  function ReviewStep({ config }: { config: Partial<FolderSyncConfig> }) {
    const directionLabels: Record<SyncDirection, string> = {
      pull: 'Pull Only (Remote → Local)',
      push: 'Push Only (Local → Remote)',
      bidirectional: 'Bidirectional',
    };

    const conflictLabels: Record<ConflictResolution, string> = {
      local: 'Prefer Local',
      remote: 'Prefer Remote',
      newest: 'Use Newest',
      manual: 'Ask Each Time',
    };

    return (
      <div className="space-y-4">
        <h3 className="font-semibold">Configuration Summary</h3>
        
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1">
            <Label className="text-muted-foreground">Local Path</Label>
            <p className="font-mono text-sm">{config.localPath || '-'}</p>
          </div>
          <div className="space-y-1">
            <Label className="text-muted-foreground">Remote Path</Label>
            <p className="font-mono text-sm">{config.remotePath || '-'}</p>
          </div>
          <div className="space-y-1">
            <Label className="text-muted-foreground">Sync Direction</Label>
            <Badge variant="secondary">
              {config.direction ? directionLabels[config.direction] : '-'}
            </Badge>
          </div>
          <div className="space-y-1">
            <Label className="text-muted-foreground">Conflict Resolution</Label>
            <Badge variant="secondary">
              {config.conflictResolution ? conflictLabels[config.conflictResolution] : '-'}
            </Badge>
          </div>
          <div className="space-y-1">
            <Label className="text-muted-foreground">Auto-Sync</Label>
            <Badge variant={config.autoSync ? 'default' : 'outline'}>
              {config.autoSync ? `Every ${config.syncIntervalMinutes} min` : 'Disabled'}
            </Badge>
          </div>
          <div className="space-y-1">
            <Label className="text-muted-foreground">Exclude Patterns</Label>
            <p className="text-sm">{config.excludePatterns?.length || 0} patterns</p>
          </div>
        </div>
      </div>
    );
  }

  export default FolderSyncWizard;
  ```

**Verify:** Wizard navigates through steps, validates inputs, submits config

---

## FIX-C3: ConsistencyReport TypeScript Types

**Goal:** Define types for consistency check results

**Add to Phase G frontend:**
- [ ] Create `src/types/consistency-report.ts`:
  ```typescript
  // ============================================================
  // Consistency Report Types - Complete Type Definitions
  // ============================================================

  /** Severity level for issues */
  export type IssueSeverity = 'error' | 'warning' | 'info';

  /** Category of consistency issue */
  export type IssueCategory = 
    | 'broken-link'
    | 'missing-reference'
    | 'duplicate-definition'
    | 'naming-convention'
    | 'structure-violation'
    | 'orphaned-file'
    | 'circular-dependency'
    | 'schema-mismatch'
    | 'version-conflict';

  /** Status of the consistency check */
  export type ReportStatus = 
    | 'pending'
    | 'running'
    | 'completed'
    | 'failed'
    | 'cancelled';

  /** Individual consistency issue */
  export interface ConsistencyIssue {
    id: string;
    category: IssueCategory;
    severity: IssueSeverity;
    title: string;
    description: string;
    filePath: string;
    lineNumber: number | null;
    columnNumber: number | null;
    sourceText: string | null;
    suggestedFix: string | null;
    relatedFiles: string[];
    isResolved: boolean;
    resolvedAt: string | null;
    resolvedBy: string | null;
  }

  /** Summary statistics for a report */
  export interface ReportSummary {
    totalIssues: number;
    errorCount: number;
    warningCount: number;
    infoCount: number;
    resolvedCount: number;
    filesScanned: number;
    scanDurationMs: number;
    categoryCounts: Record<IssueCategory, number>;
  }

  /** Full consistency report */
  export interface ConsistencyReport {
    id: string;
    projectId: string;
    status: ReportStatus;
    summary: ReportSummary;
    issues: ConsistencyIssue[];
    createdAt: string;
    completedAt: string | null;
    triggeredBy: 'manual' | 'auto' | 'commit-hook';
    commitHash: string | null;
  }

  /** Filter options for issue list */
  export interface IssueFilters {
    severity: IssueSeverity[];
    categories: IssueCategory[];
    filePath: string | null;
    showResolved: boolean;
    searchQuery: string;
  }

  /** Grouped issues by file */
  export interface FileIssueGroup {
    filePath: string;
    issues: ConsistencyIssue[];
    errorCount: number;
    warningCount: number;
    infoCount: number;
  }

  /** Report comparison between two runs */
  export interface ReportComparison {
    previousReportId: string;
    currentReportId: string;
    newIssues: ConsistencyIssue[];
    resolvedIssues: ConsistencyIssue[];
    unchangedIssues: ConsistencyIssue[];
    trendDirection: 'improving' | 'worsening' | 'stable';
  }

  /** Real-time scan progress */
  export interface ScanProgress {
    status: ReportStatus;
    currentFile: string | null;
    filesScanned: number;
    totalFiles: number;
    issuesFound: number;
    progressPercent: number;
  }

  /** SSE event types for live updates */
  export type ScanEventType = 
    | 'scan-started'
    | 'file-scanned'
    | 'issue-found'
    | 'scan-progress'
    | 'scan-completed'
    | 'scan-failed';

  export interface ScanEvent {
    type: ScanEventType;
    reportId: string;
    timestamp: string;
    data: {
      progress?: ScanProgress;
      issue?: ConsistencyIssue;
      report?: ConsistencyReport;
      error?: string;
    };
  }
  ```

**Verify:** Types cover all report scenarios, match backend API

---

## FIX-C4: ConsistencyReport UI Component

**Goal:** Complete UI for viewing and managing consistency reports

**Add to Phase G frontend:**
- [ ] Create `src/components/consistency/ConsistencyReportView.tsx`:
  ```tsx
  import React, { useState, useMemo, useCallback } from 'react';
  import { Button } from '@/components/ui/button';
  import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
  import { Badge } from '@/components/ui/badge';
  import { Input } from '@/components/ui/input';
  import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
  import { ScrollArea } from '@/components/ui/scroll-area';
  import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
  import { Checkbox } from '@/components/ui/checkbox';
  import { Progress } from '@/components/ui/progress';
  import { 
    AlertCircle, AlertTriangle, Info, Search, 
    ChevronDown, ChevronRight, FileText, Check,
    RefreshCw, Filter, Download, Clock, GitCommit
  } from 'lucide-react';
  import {
    ConsistencyReport,
    ConsistencyIssue,
    IssueFilters,
    IssueSeverity,
    IssueCategory,
    FileIssueGroup,
    ScanProgress
  } from '@/types/consistency-report';

  interface ConsistencyReportViewProps {
    report: ConsistencyReport;
    scanProgress?: ScanProgress;
    onRerunScan: () => void;
    onResolveIssue: (issueId: string) => void;
    onExportReport: (format: 'json' | 'csv' | 'markdown') => void;
  }

  const SEVERITY_CONFIG: Record<IssueSeverity, { 
    icon: React.ElementType; 
    color: string; 
    bgColor: string 
  }> = {
    error: { icon: AlertCircle, color: 'text-destructive', bgColor: 'bg-destructive/10' },
    warning: { icon: AlertTriangle, color: 'text-yellow-600', bgColor: 'bg-yellow-50' },
    info: { icon: Info, color: 'text-blue-600', bgColor: 'bg-blue-50' },
  };

  const CATEGORY_LABELS: Record<IssueCategory, string> = {
    'broken-link': 'Broken Link',
    'missing-reference': 'Missing Reference',
    'duplicate-definition': 'Duplicate Definition',
    'naming-convention': 'Naming Convention',
    'structure-violation': 'Structure Violation',
    'orphaned-file': 'Orphaned File',
    'circular-dependency': 'Circular Dependency',
    'schema-mismatch': 'Schema Mismatch',
    'version-conflict': 'Version Conflict',
  };

  export function ConsistencyReportView({
    report,
    scanProgress,
    onRerunScan,
    onResolveIssue,
    onExportReport,
  }: ConsistencyReportViewProps) {
    const [filters, setFilters] = useState<IssueFilters>({
      severity: ['error', 'warning', 'info'],
      categories: Object.keys(CATEGORY_LABELS) as IssueCategory[],
      filePath: null,
      showResolved: false,
      searchQuery: '',
    });
    const [expandedFiles, setExpandedFiles] = useState<Set<string>>(new Set());

    // Filter and group issues
    const filteredIssues = useMemo(() => {
      return report.issues.filter(issue => {
        if (!filters.severity.includes(issue.severity)) return false;
        if (!filters.categories.includes(issue.category)) return false;
        if (!filters.showResolved && issue.isResolved) return false;
        if (filters.filePath && !issue.filePath.includes(filters.filePath)) return false;
        if (filters.searchQuery) {
          const query = filters.searchQuery.toLowerCase();
          return (
            issue.title.toLowerCase().includes(query) ||
            issue.description.toLowerCase().includes(query) ||
            issue.filePath.toLowerCase().includes(query)
          );
        }
        return true;
      });
    }, [report.issues, filters]);

    const groupedByFile = useMemo((): FileIssueGroup[] => {
      const groups = new Map<string, ConsistencyIssue[]>();
      
      filteredIssues.forEach(issue => {
        const existing = groups.get(issue.filePath) || [];
        groups.set(issue.filePath, [...existing, issue]);
      });

      return Array.from(groups.entries()).map(([filePath, issues]) => ({
        filePath,
        issues: issues.sort((a, b) => (a.lineNumber || 0) - (b.lineNumber || 0)),
        errorCount: issues.filter(i => i.severity === 'error').length,
        warningCount: issues.filter(i => i.severity === 'warning').length,
        infoCount: issues.filter(i => i.severity === 'info').length,
      })).sort((a, b) => b.errorCount - a.errorCount);
    }, [filteredIssues]);

    const toggleFileExpanded = useCallback((filePath: string) => {
      setExpandedFiles(prev => {
        const next = new Set(prev);
        if (next.has(filePath)) {
          next.delete(filePath);
        } else {
          next.add(filePath);
        }
        return next;
      });
    }, []);

    const updateFilter = useCallback(<K extends keyof IssueFilters>(
      key: K, 
      value: IssueFilters[K]
    ) => {
      setFilters(prev => ({ ...prev, [key]: value }));
    }, []);

    const toggleSeverity = useCallback((severity: IssueSeverity) => {
      setFilters(prev => ({
        ...prev,
        severity: prev.severity.includes(severity)
          ? prev.severity.filter(s => s !== severity)
          : [...prev.severity, severity],
      }));
    }, []);

    const isScanning = scanProgress && scanProgress.status === 'running';

    return (
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-2xl font-bold">Consistency Report</h2>
            <p className="text-muted-foreground">
              {report.completedAt 
                ? `Completed ${new Date(report.completedAt).toLocaleString()}`
                : 'In progress...'}
              {report.commitHash && (
                <span className="ml-2 inline-flex items-center gap-1">
                  <GitCommit className="w-3 h-3" />
                  <code className="text-xs">{report.commitHash.slice(0, 7)}</code>
                </span>
              )}
            </p>
          </div>
          <div className="flex gap-2">
            <Button 
              variant="outline" 
              onClick={() => onExportReport('markdown')}
              disabled={isScanning}
            >
              <Download className="w-4 h-4 mr-2" />
              Export
            </Button>
            <Button onClick={onRerunScan} disabled={isScanning}>
              <RefreshCw className={`w-4 h-4 mr-2 ${isScanning ? 'animate-spin' : ''}`} />
              {isScanning ? 'Scanning...' : 'Rerun Scan'}
            </Button>
          </div>
        </div>

        {/* Scan Progress */}
        {isScanning && scanProgress && (
          <Card>
            <CardContent className="pt-6">
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span>Scanning: {scanProgress.currentFile || 'Initializing...'}</span>
                  <span>{scanProgress.filesScanned}/{scanProgress.totalFiles} files</span>
                </div>
                <Progress value={scanProgress.progressPercent} />
                <p className="text-sm text-muted-foreground">
                  {scanProgress.issuesFound} issues found so far
                </p>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Summary Cards */}
        <div className="grid grid-cols-4 gap-4">
          <SummaryCard 
            title="Errors" 
            count={report.summary.errorCount} 
            icon={AlertCircle}
            color="text-destructive"
          />
          <SummaryCard 
            title="Warnings" 
            count={report.summary.warningCount} 
            icon={AlertTriangle}
            color="text-yellow-600"
          />
          <SummaryCard 
            title="Info" 
            count={report.summary.infoCount} 
            icon={Info}
            color="text-blue-600"
          />
          <SummaryCard 
            title="Resolved" 
            count={report.summary.resolvedCount} 
            icon={Check}
            color="text-green-600"
          />
        </div>

        {/* Filters */}
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Filter className="w-4 h-4" />
              Filters
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-4">
              <div className="relative flex-1 min-w-[200px]">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input
                  placeholder="Search issues..."
                  value={filters.searchQuery}
                  onChange={(e) => updateFilter('searchQuery', e.target.value)}
                  className="pl-9"
                />
              </div>
              
              <div className="flex gap-2">
                {(['error', 'warning', 'info'] as IssueSeverity[]).map(severity => {
                  const config = SEVERITY_CONFIG[severity];
                  const Icon = config.icon;
                  const isActive = filters.severity.includes(severity);
                  
                  return (
                    <Button
                      key={severity}
                      variant={isActive ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => toggleSeverity(severity)}
                      className="capitalize"
                    >
                      <Icon className="w-4 h-4 mr-1" />
                      {severity}
                    </Button>
                  );
                })}
              </div>

              <div className="flex items-center gap-2">
                <Checkbox
                  id="showResolved"
                  checked={filters.showResolved}
                  onCheckedChange={(checked) => updateFilter('showResolved', !!checked)}
                />
                <label htmlFor="showResolved" className="text-sm">
                  Show resolved
                </label>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Issues List */}
        <Tabs defaultValue="by-file">
          <TabsList>
            <TabsTrigger value="by-file">By File</TabsTrigger>
            <TabsTrigger value="by-category">By Category</TabsTrigger>
            <TabsTrigger value="all">All Issues</TabsTrigger>
          </TabsList>

          <TabsContent value="by-file" className="mt-4">
            <ScrollArea className="h-[600px]">
              <div className="space-y-2">
                {groupedByFile.map(group => (
                  <FileIssueCard
                    key={group.filePath}
                    group={group}
                    isExpanded={expandedFiles.has(group.filePath)}
                    onToggle={() => toggleFileExpanded(group.filePath)}
                    onResolveIssue={onResolveIssue}
                  />
                ))}
                {groupedByFile.length === 0 && (
                  <EmptyState message="No issues match your filters" />
                )}
              </div>
            </ScrollArea>
          </TabsContent>

          <TabsContent value="by-category" className="mt-4">
            <CategoryView 
              issues={filteredIssues} 
              onResolveIssue={onResolveIssue}
            />
          </TabsContent>

          <TabsContent value="all" className="mt-4">
            <ScrollArea className="h-[600px]">
              <div className="space-y-2">
                {filteredIssues.map(issue => (
                  <IssueCard 
                    key={issue.id} 
                    issue={issue} 
                    onResolve={() => onResolveIssue(issue.id)}
                  />
                ))}
                {filteredIssues.length === 0 && (
                  <EmptyState message="No issues match your filters" />
                )}
              </div>
            </ScrollArea>
          </TabsContent>
        </Tabs>
      </div>
    );
  }

  // ============================================================
  // Sub-components
  // ============================================================

  function SummaryCard({ 
    title, 
    count, 
    icon: Icon, 
    color 
  }: { 
    title: string; 
    count: number; 
    icon: React.ElementType; 
    color: string;
  }) {
    return (
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-muted-foreground">{title}</p>
              <p className="text-3xl font-bold">{count}</p>
            </div>
            <Icon className={`w-8 h-8 ${color}`} />
          </div>
        </CardContent>
      </Card>
    );
  }

  function FileIssueCard({
    group,
    isExpanded,
    onToggle,
    onResolveIssue,
  }: {
    group: FileIssueGroup;
    isExpanded: boolean;
    onToggle: () => void;
    onResolveIssue: (id: string) => void;
  }) {
    return (
      <Collapsible open={isExpanded} onOpenChange={onToggle}>
        <Card>
          <CollapsibleTrigger asChild>
            <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  {isExpanded ? (
                    <ChevronDown className="w-4 h-4" />
                  ) : (
                    <ChevronRight className="w-4 h-4" />
                  )}
                  <FileText className="w-4 h-4 text-muted-foreground" />
                  <code className="text-sm">{group.filePath}</code>
                </div>
                <div className="flex gap-2">
                  {group.errorCount > 0 && (
                    <Badge variant="destructive">{group.errorCount}</Badge>
                  )}
                  {group.warningCount > 0 && (
                    <Badge variant="secondary" className="bg-yellow-100 text-yellow-800">
                      {group.warningCount}
                    </Badge>
                  )}
                  {group.infoCount > 0 && (
                    <Badge variant="secondary" className="bg-blue-100 text-blue-800">
                      {group.infoCount}
                    </Badge>
                  )}
                </div>
              </div>
            </CardHeader>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <CardContent className="pt-0">
              <div className="space-y-2 pl-6">
                {group.issues.map(issue => (
                  <IssueCard 
                    key={issue.id} 
                    issue={issue} 
                    compact
                    onResolve={() => onResolveIssue(issue.id)}
                  />
                ))}
              </div>
            </CardContent>
          </CollapsibleContent>
        </Card>
      </Collapsible>
    );
  }

  function IssueCard({ 
    issue, 
    compact = false,
    onResolve 
  }: { 
    issue: ConsistencyIssue; 
    compact?: boolean;
    onResolve: () => void;
  }) {
    const config = SEVERITY_CONFIG[issue.severity];
    const Icon = config.icon;

    return (
      <div className={`p-3 rounded-lg border ${config.bgColor} ${
        issue.isResolved ? 'opacity-60' : ''
      }`}>
        <div className="flex items-start gap-3">
          <Icon className={`w-5 h-5 mt-0.5 ${config.color}`} />
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2">
              <span className="font-medium">{issue.title}</span>
              <Badge variant="outline" className="text-xs">
                {CATEGORY_LABELS[issue.category]}
              </Badge>
              {issue.isResolved && (
                <Badge variant="secondary" className="bg-green-100 text-green-800">
                  Resolved
                </Badge>
              )}
            </div>
            <p className="text-sm text-muted-foreground mt-1">
              {issue.description}
            </p>
            {!compact && issue.lineNumber && (
              <p className="text-xs text-muted-foreground mt-1">
                Line {issue.lineNumber}
                {issue.columnNumber && `, Column ${issue.columnNumber}`}
              </p>
            )}
            {!compact && issue.sourceText && (
              <pre className="mt-2 p-2 bg-muted rounded text-xs overflow-x-auto">
                {issue.sourceText}
              </pre>
            )}
            {!compact && issue.suggestedFix && (
              <div className="mt-2 p-2 bg-green-50 rounded border border-green-200">
                <p className="text-xs font-medium text-green-800">Suggested Fix:</p>
                <p className="text-xs text-green-700">{issue.suggestedFix}</p>
              </div>
            )}
          </div>
          {!issue.isResolved && (
            <Button variant="ghost" size="sm" onClick={onResolve}>
              <Check className="w-4 h-4" />
            </Button>
          )}
        </div>
      </div>
    );
  }

  function CategoryView({ 
    issues, 
    onResolveIssue 
  }: { 
    issues: ConsistencyIssue[]; 
    onResolveIssue: (id: string) => void;
  }) {
    const grouped = useMemo(() => {
      const map = new Map<IssueCategory, ConsistencyIssue[]>();
      issues.forEach(issue => {
        const existing = map.get(issue.category) || [];
        map.set(issue.category, [...existing, issue]);
      });
      return map;
    }, [issues]);

    return (
      <ScrollArea className="h-[600px]">
        <div className="space-y-6">
          {Array.from(grouped.entries()).map(([category, categoryIssues]) => (
            <div key={category}>
              <h3 className="font-semibold mb-2 flex items-center gap-2">
                {CATEGORY_LABELS[category]}
                <Badge>{categoryIssues.length}</Badge>
              </h3>
              <div className="space-y-2">
                {categoryIssues.map(issue => (
                  <IssueCard 
                    key={issue.id} 
                    issue={issue} 
                    onResolve={() => onResolveIssue(issue.id)}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>
      </ScrollArea>
    );
  }

  function EmptyState({ message }: { message: string }) {
    return (
      <div className="text-center py-12">
        <Check className="w-12 h-12 mx-auto text-green-500 mb-4" />
        <p className="text-muted-foreground">{message}</p>
      </div>
    );
  }

  export default ConsistencyReportView;
  ```

**Verify:** Report displays correctly, filters work, issues can be marked resolved

---

## FIX-C5: useStreamingConnection Hook

**Goal:** Create reusable hook for SSE/WebSocket streaming with reconnection

**Add to Phase F frontend:**
- [ ] Create `src/hooks/useStreamingConnection.ts`:
  ```typescript
  import { useState, useEffect, useRef, useCallback } from 'react';

  // ============================================================
  // Streaming Connection Hook - With Exponential Backoff
  // ============================================================

  export type ConnectionState = 
    | 'disconnected' 
    | 'connecting' 
    | 'connected' 
    | 'reconnecting' 
    | 'error'
    | 'closed';

  export interface StreamingConfig {
    /** Base URL for the SSE/WebSocket endpoint */
    url: string;
    /** Connection type */
    type: 'sse' | 'websocket';
    /** Enable auto-reconnection (default: true) */
    autoReconnect?: boolean;
    /** Maximum reconnection attempts (default: 10) */
    maxRetries?: number;
    /** Initial backoff delay in ms (default: 1000) */
    initialBackoffMs?: number;
    /** Maximum backoff delay in ms (default: 30000) */
    maxBackoffMs?: number;
    /** Backoff multiplier (default: 2) */
    backoffMultiplier?: number;
    /** Add jitter to backoff (default: true) */
    useJitter?: boolean;
    /** Connection timeout in ms (default: 10000) */
    connectionTimeoutMs?: number;
    /** Heartbeat interval check in ms (default: 30000) */
    heartbeatIntervalMs?: number;
    /** Auth token for connection */
    authToken?: string;
    /** Custom headers (SSE only) */
    headers?: Record<string, string>;
  }

  export interface StreamingCallbacks<T> {
    /** Called when a message is received */
    onMessage: (data: T) => void;
    /** Called when connection state changes */
    onStateChange?: (state: ConnectionState) => void;
    /** Called on connection error */
    onError?: (error: Error) => void;
    /** Called when connection is established */
    onConnect?: () => void;
    /** Called when connection is closed */
    onClose?: () => void;
  }

  export interface UseStreamingResult {
    /** Current connection state */
    state: ConnectionState;
    /** Number of reconnection attempts */
    retryCount: number;
    /** Time until next reconnection attempt (ms) */
    nextRetryIn: number;
    /** Manually connect */
    connect: () => void;
    /** Manually disconnect */
    disconnect: () => void;
    /** Send message (WebSocket only) */
    send: (data: unknown) => void;
    /** Last error if any */
    error: Error | null;
  }

  const DEFAULT_CONFIG: Required<Omit<StreamingConfig, 'url' | 'type' | 'authToken' | 'headers'>> = {
    autoReconnect: true,
    maxRetries: 10,
    initialBackoffMs: 1000,
    maxBackoffMs: 30000,
    backoffMultiplier: 2,
    useJitter: true,
    connectionTimeoutMs: 10000,
    heartbeatIntervalMs: 30000,
  };

  /**
   * Calculate backoff delay with optional jitter
   */
  function calculateBackoff(
    attempt: number,
    initialMs: number,
    maxMs: number,
    multiplier: number,
    useJitter: boolean
  ): number {
    // Exponential backoff: initialMs * multiplier^attempt
    const exponentialDelay = Math.min(
      initialMs * Math.pow(multiplier, attempt),
      maxMs
    );

    if (!useJitter) {
      return exponentialDelay;
    }

    // Add jitter: random value between 0.5x and 1.5x the delay
    const jitterMin = exponentialDelay * 0.5;
    const jitterMax = exponentialDelay * 1.5;
    return Math.floor(Math.random() * (jitterMax - jitterMin + 1) + jitterMin);
  }

  /**
   * Hook for managing SSE or WebSocket streaming connections
   * with automatic reconnection and exponential backoff
   */
  export function useStreamingConnection<T = unknown>(
    config: StreamingConfig,
    callbacks: StreamingCallbacks<T>
  ): UseStreamingResult {
    const mergedConfig = { ...DEFAULT_CONFIG, ...config };
    
    const [state, setState] = useState<ConnectionState>('disconnected');
    const [retryCount, setRetryCount] = useState(0);
    const [nextRetryIn, setNextRetryIn] = useState(0);
    const [error, setError] = useState<Error | null>(null);

    // Refs for connection management
    const connectionRef = useRef<EventSource | WebSocket | null>(null);
    const retryTimeoutRef = useRef<NodeJS.Timeout | null>(null);
    const heartbeatTimeoutRef = useRef<NodeJS.Timeout | null>(null);
    const countdownIntervalRef = useRef<NodeJS.Timeout | null>(null);
    const lastMessageTimeRef = useRef<number>(Date.now());
    const isManualDisconnectRef = useRef(false);

    // Stable callback refs
    const callbacksRef = useRef(callbacks);
    callbacksRef.current = callbacks;

    const updateState = useCallback((newState: ConnectionState) => {
      setState(newState);
      callbacksRef.current.onStateChange?.(newState);
    }, []);

    const clearTimers = useCallback(() => {
      if (retryTimeoutRef.current) {
        clearTimeout(retryTimeoutRef.current);
        retryTimeoutRef.current = null;
      }
      if (heartbeatTimeoutRef.current) {
        clearTimeout(heartbeatTimeoutRef.current);
        heartbeatTimeoutRef.current = null;
      }
      if (countdownIntervalRef.current) {
        clearInterval(countdownIntervalRef.current);
        countdownIntervalRef.current = null;
      }
      setNextRetryIn(0);
    }, []);

    const closeConnection = useCallback(() => {
      clearTimers();
      
      if (connectionRef.current) {
        if (connectionRef.current instanceof EventSource) {
          connectionRef.current.close();
        } else if (connectionRef.current instanceof WebSocket) {
          connectionRef.current.close(1000, 'Manual disconnect');
        }
        connectionRef.current = null;
      }
    }, [clearTimers]);

    const scheduleReconnect = useCallback(() => {
      if (!mergedConfig.autoReconnect) return;
      if (isManualDisconnectRef.current) return;
      if (retryCount >= mergedConfig.maxRetries) {
        updateState('error');
        setError(new Error(`Max reconnection attempts (${mergedConfig.maxRetries}) exceeded`));
        return;
      }

      const delay = calculateBackoff(
        retryCount,
        mergedConfig.initialBackoffMs,
        mergedConfig.maxBackoffMs,
        mergedConfig.backoffMultiplier,
        mergedConfig.useJitter
      );

      updateState('reconnecting');
      setNextRetryIn(delay);

      // Start countdown
      const startTime = Date.now();
      countdownIntervalRef.current = setInterval(() => {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, delay - elapsed);
        setNextRetryIn(remaining);
        if (remaining <= 0) {
          clearInterval(countdownIntervalRef.current!);
          countdownIntervalRef.current = null;
        }
      }, 100);

      retryTimeoutRef.current = setTimeout(() => {
        setRetryCount(prev => prev + 1);
        connect();
      }, delay);
    }, [retryCount, mergedConfig, updateState]);

    const startHeartbeatCheck = useCallback(() => {
      if (heartbeatTimeoutRef.current) {
        clearTimeout(heartbeatTimeoutRef.current);
      }

      heartbeatTimeoutRef.current = setTimeout(() => {
        const timeSinceLastMessage = Date.now() - lastMessageTimeRef.current;
        
        if (timeSinceLastMessage > mergedConfig.heartbeatIntervalMs * 2) {
          // No messages received, connection might be stale
          console.warn('Heartbeat timeout, reconnecting...');
          closeConnection();
          scheduleReconnect();
        } else {
          startHeartbeatCheck();
        }
      }, mergedConfig.heartbeatIntervalMs);
    }, [mergedConfig.heartbeatIntervalMs, closeConnection, scheduleReconnect]);

    const handleMessage = useCallback((rawData: string) => {
      lastMessageTimeRef.current = Date.now();
      startHeartbeatCheck();

      try {
        const parsed = JSON.parse(rawData) as T;
        callbacksRef.current.onMessage(parsed);
      } catch (e) {
        // If not JSON, pass raw string
        callbacksRef.current.onMessage(rawData as unknown as T);
      }
    }, [startHeartbeatCheck]);

    const connectSSE = useCallback(() => {
      const url = new URL(mergedConfig.url);
      if (mergedConfig.authToken) {
        url.searchParams.set('token', mergedConfig.authToken);
      }

      const eventSource = new EventSource(url.toString());

      eventSource.onopen = () => {
        setRetryCount(0);
        setError(null);
        updateState('connected');
        startHeartbeatCheck();
        callbacksRef.current.onConnect?.();
      };

      eventSource.onmessage = (event) => {
        handleMessage(event.data);
      };

      eventSource.onerror = () => {
        const wasConnected = state === 'connected';
        updateState('error');
        setError(new Error('SSE connection error'));
        callbacksRef.current.onError?.(new Error('SSE connection error'));
        
        eventSource.close();
        connectionRef.current = null;

        if (wasConnected || state === 'connecting') {
          scheduleReconnect();
        }
      };

      connectionRef.current = eventSource;
    }, [mergedConfig, state, updateState, handleMessage, startHeartbeatCheck, scheduleReconnect]);

    const connectWebSocket = useCallback(() => {
      const url = new URL(mergedConfig.url);
      if (mergedConfig.authToken) {
        url.searchParams.set('token', mergedConfig.authToken);
      }

      const ws = new WebSocket(url.toString());

      // Connection timeout
      const timeoutId = setTimeout(() => {
        if (ws.readyState === WebSocket.CONNECTING) {
          ws.close();
          setError(new Error('Connection timeout'));
          scheduleReconnect();
        }
      }, mergedConfig.connectionTimeoutMs);

      ws.onopen = () => {
        clearTimeout(timeoutId);
        setRetryCount(0);
        setError(null);
        updateState('connected');
        startHeartbeatCheck();
        callbacksRef.current.onConnect?.();
      };

      ws.onmessage = (event) => {
        handleMessage(event.data);
      };

      ws.onerror = (event) => {
        console.error('WebSocket error:', event);
        setError(new Error('WebSocket connection error'));
        callbacksRef.current.onError?.(new Error('WebSocket connection error'));
      };

      ws.onclose = (event) => {
        clearTimeout(timeoutId);
        connectionRef.current = null;
        callbacksRef.current.onClose?.();

        if (!isManualDisconnectRef.current && !event.wasClean) {
          scheduleReconnect();
        } else {
          updateState('closed');
        }
      };

      connectionRef.current = ws;
    }, [mergedConfig, updateState, handleMessage, startHeartbeatCheck, scheduleReconnect]);

    const connect = useCallback(() => {
      if (connectionRef.current) {
        closeConnection();
      }

      isManualDisconnectRef.current = false;
      clearTimers();
      updateState('connecting');

      if (mergedConfig.type === 'sse') {
        connectSSE();
      } else {
        connectWebSocket();
      }
    }, [mergedConfig.type, closeConnection, clearTimers, updateState, connectSSE, connectWebSocket]);

    const disconnect = useCallback(() => {
      isManualDisconnectRef.current = true;
      closeConnection();
      setRetryCount(0);
      updateState('disconnected');
    }, [closeConnection, updateState]);

    const send = useCallback((data: unknown) => {
      if (mergedConfig.type !== 'websocket') {
        console.warn('send() is only available for WebSocket connections');
        return;
      }

      if (connectionRef.current instanceof WebSocket) {
        if (connectionRef.current.readyState === WebSocket.OPEN) {
          const message = typeof data === 'string' ? data : JSON.stringify(data);
          connectionRef.current.send(message);
        } else {
          console.warn('WebSocket is not open');
        }
      }
    }, [mergedConfig.type]);

    // Cleanup on unmount
    useEffect(() => {
      return () => {
        isManualDisconnectRef.current = true;
        closeConnection();
      };
    }, [closeConnection]);

    return {
      state,
      retryCount,
      nextRetryIn,
      connect,
      disconnect,
      send,
      error,
    };
  }

  export default useStreamingConnection;
  ```

**Verify:** Hook connects/reconnects with exponential backoff, handles both SSE and WebSocket

---

## FIX-C6: Streaming AI Response Component

**Goal:** Create component that uses streaming hook for AI chat responses

**Add to Phase F frontend:**
- [ ] Create `src/components/ai/StreamingAIResponse.tsx`:
  ```tsx
  import React, { useState, useCallback, useEffect, useRef } from 'react';
  import { Button } from '@/components/ui/button';
  import { Card, CardContent } from '@/components/ui/card';
  import { Badge } from '@/components/ui/badge';
  import { Progress } from '@/components/ui/progress';
  import { Skeleton } from '@/components/ui/skeleton';
  import { Alert, AlertDescription } from '@/components/ui/alert';
  import { 
    Wifi, WifiOff, RefreshCw, Loader2, 
    CheckCircle, AlertCircle, Clock 
  } from 'lucide-react';
  import { 
    useStreamingConnection, 
    ConnectionState, 
    StreamingConfig 
  } from '@/hooks/useStreamingConnection';

  // ============================================================
  // Types for AI Streaming
  // ============================================================

  interface AIStreamEvent {
    type: 'chunk' | 'complete' | 'error' | 'metadata';
    requestId: string;
    data: {
      content?: string;
      tokens?: number;
      totalTokens?: number;
      model?: string;
      finishReason?: string;
      error?: string;
    };
  }

  interface StreamingAIResponseProps {
    /** SSE endpoint for AI responses */
    endpoint: string;
    /** Initial prompt/request to send */
    requestId: string;
    /** Auth token for connection */
    authToken: string;
    /** Called when streaming is complete */
    onComplete?: (fullResponse: string) => void;
    /** Called on error */
    onError?: (error: Error) => void;
    /** Show connection status indicator */
    showConnectionStatus?: boolean;
    /** Custom placeholder while loading */
    placeholder?: React.ReactNode;
  }

  // ============================================================
  // Connection Status Component
  // ============================================================

  function ConnectionStatus({ 
    state, 
    retryCount, 
    nextRetryIn,
    onReconnect
  }: { 
    state: ConnectionState; 
    retryCount: number;
    nextRetryIn: number;
    onReconnect: () => void;
  }) {
    const getStatusConfig = () => {
      switch (state) {
        case 'connected':
          return { 
            icon: Wifi, 
            color: 'text-green-500', 
            bg: 'bg-green-50',
            label: 'Connected' 
          };
        case 'connecting':
          return { 
            icon: Loader2, 
            color: 'text-blue-500 animate-spin', 
            bg: 'bg-blue-50',
            label: 'Connecting...' 
          };
        case 'reconnecting':
          return { 
            icon: RefreshCw, 
            color: 'text-yellow-500 animate-spin', 
            bg: 'bg-yellow-50',
            label: `Reconnecting in ${Math.ceil(nextRetryIn / 1000)}s (attempt ${retryCount + 1})` 
          };
        case 'error':
          return { 
            icon: AlertCircle, 
            color: 'text-destructive', 
            bg: 'bg-destructive/10',
            label: 'Connection failed' 
          };
        case 'disconnected':
        case 'closed':
        default:
          return { 
            icon: WifiOff, 
            color: 'text-muted-foreground', 
            bg: 'bg-muted',
            label: 'Disconnected' 
          };
      }
    };

    const config = getStatusConfig();
    const Icon = config.icon;

    return (
      <div className={`flex items-center gap-2 px-3 py-1.5 rounded-full ${config.bg}`}>
        <Icon className={`w-4 h-4 ${config.color}`} />
        <span className="text-sm font-medium">{config.label}</span>
        {(state === 'error' || state === 'disconnected') && (
          <Button 
            variant="ghost" 
            size="sm" 
            onClick={onReconnect}
            className="h-6 px-2"
          >
            Retry
          </Button>
        )}
      </div>
    );
  }

  // ============================================================
  // Token Progress Component
  // ============================================================

  function TokenProgress({ 
    current, 
    total 
  }: { 
    current: number; 
    total: number;
  }) {
    const percentage = total > 0 ? (current / total) * 100 : 0;
    
    return (
      <div className="space-y-1">
        <div className="flex justify-between text-xs text-muted-foreground">
          <span>Tokens generated</span>
          <span>{current} / {total}</span>
        </div>
        <Progress value={percentage} className="h-1" />
      </div>
    );
  }

  // ============================================================
  // Streaming Response Display
  // ============================================================

  function StreamingText({ 
    text, 
    isComplete 
  }: { 
    text: string; 
    isComplete: boolean;
  }) {
    const textRef = useRef<HTMLDivElement>(null);

    // Auto-scroll as content streams in
    useEffect(() => {
      if (textRef.current) {
        textRef.current.scrollTop = textRef.current.scrollHeight;
      }
    }, [text]);

    return (
      <div 
        ref={textRef}
        className="prose prose-sm max-w-none overflow-y-auto max-h-[400px]"
      >
        {text}
        {!isComplete && (
          <span className="inline-block w-2 h-4 bg-primary/50 animate-pulse ml-0.5" />
        )}
      </div>
    );
  }

  // ============================================================
  // Main Component
  // ============================================================

  export function StreamingAIResponse({
    endpoint,
    requestId,
    authToken,
    onComplete,
    onError,
    showConnectionStatus = true,
    placeholder,
  }: StreamingAIResponseProps) {
    const [responseText, setResponseText] = useState('');
    const [isComplete, setIsComplete] = useState(false);
    const [metadata, setMetadata] = useState<{
      model?: string;
      tokens?: number;
      totalTokens?: number;
      finishReason?: string;
    }>({});
    const [streamError, setStreamError] = useState<string | null>(null);

    const handleMessage = useCallback((event: AIStreamEvent) => {
      if (event.requestId !== requestId) return;

      switch (event.type) {
        case 'chunk':
          if (event.data.content) {
            setResponseText(prev => prev + event.data.content);
          }
          if (event.data.tokens) {
            setMetadata(prev => ({
              ...prev,
              tokens: event.data.tokens,
              totalTokens: event.data.totalTokens,
            }));
          }
          break;

        case 'metadata':
          setMetadata(prev => ({
            ...prev,
            model: event.data.model,
            totalTokens: event.data.totalTokens,
          }));
          break;

        case 'complete':
          setIsComplete(true);
          setMetadata(prev => ({
            ...prev,
            finishReason: event.data.finishReason,
          }));
          onComplete?.(responseText + (event.data.content || ''));
          break;

        case 'error':
          setStreamError(event.data.error || 'Unknown error');
          onError?.(new Error(event.data.error || 'Stream error'));
          break;
      }
    }, [requestId, responseText, onComplete, onError]);

    const handleStreamError = useCallback((error: Error) => {
      setStreamError(error.message);
      onError?.(error);
    }, [onError]);

    const streamingConfig: StreamingConfig = {
      url: `${endpoint}?requestId=${requestId}`,
      type: 'sse',
      authToken,
      autoReconnect: true,
      maxRetries: 5,
      initialBackoffMs: 1000,
      maxBackoffMs: 15000,
    };

    const { 
      state, 
      retryCount, 
      nextRetryIn, 
      connect, 
      disconnect 
    } = useStreamingConnection(streamingConfig, {
      onMessage: handleMessage,
      onError: handleStreamError,
      onConnect: () => {
        setStreamError(null);
      },
    });

    // Auto-connect on mount
    useEffect(() => {
      connect();
      return () => disconnect();
    }, [connect, disconnect]);

    // Reset state for new request
    useEffect(() => {
      setResponseText('');
      setIsComplete(false);
      setMetadata({});
      setStreamError(null);
    }, [requestId]);

    const isLoading = state === 'connecting' && !responseText;

    return (
      <Card>
        <CardContent className="pt-6 space-y-4">
          {/* Header with status */}
          {showConnectionStatus && (
            <div className="flex items-center justify-between">
              <ConnectionStatus 
                state={state}
                retryCount={retryCount}
                nextRetryIn={nextRetryIn}
                onReconnect={connect}
              />
              {metadata.model && (
                <Badge variant="outline">{metadata.model}</Badge>
              )}
            </div>
          )}

          {/* Error display */}
          {streamError && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{streamError}</AlertDescription>
            </Alert>
          )}

          {/* Loading placeholder */}
          {isLoading && (
            placeholder || (
              <div className="space-y-2">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-3/4" />
                <Skeleton className="h-4 w-5/6" />
              </div>
            )
          )}

          {/* Streaming text */}
          {responseText && (
            <StreamingText text={responseText} isComplete={isComplete} />
          )}

          {/* Token progress */}
          {metadata.tokens !== undefined && metadata.totalTokens && (
            <TokenProgress 
              current={metadata.tokens} 
              total={metadata.totalTokens} 
            />
          )}

          {/* Completion status */}
          {isComplete && (
            <div className="flex items-center gap-2 text-sm text-green-600">
              <CheckCircle className="w-4 h-4" />
              <span>
                Response complete
                {metadata.finishReason && ` (${metadata.finishReason})`}
              </span>
            </div>
          )}
        </CardContent>
      </Card>
    );
  }

  export default StreamingAIResponse;
  ```

**Verify:** Component streams AI responses, shows connection status, handles errors gracefully

---

# FIX-D: Infrastructure Fixes (Complete)

## FIX-D1: Multi-Stage Dockerfile

**Goal:** Create optimized production Docker image with minimal attack surface

**Create `Dockerfile`:**
```dockerfile
# ============================================================
# Spec Management Software - Multi-Stage Dockerfile
# ============================================================

# ---- Build Stage ----
FROM golang:1.22-alpine AS builder

# Install build dependencies
RUN apk add --no-cache \
    gcc \
    musl-dev \
    sqlite-dev \
    git \
    ca-certificates \
    tzdata

# Set working directory
WORKDIR /build

# Copy go mod files first for better caching
COPY go.mod go.sum ./
RUN go mod download && go mod verify

# Copy source code
COPY . .

# Build with optimizations
# CGO_ENABLED=1 required for SQLite
# -ldflags for smaller binary and version info
ARG VERSION=dev
ARG BUILD_TIME
ARG GIT_COMMIT

RUN CGO_ENABLED=1 GOOS=linux go build \
    -ldflags="-s -w \
        -X 'main.Version=${VERSION}' \
        -X 'main.BuildTime=${BUILD_TIME}' \
        -X 'main.GitCommit=${GIT_COMMIT}'" \
    -o /build/spec-manager \
    ./cmd/server

# ---- Runtime Stage ----
FROM alpine:3.19 AS runtime

# Security: run as non-root user
RUN addgroup -g 1000 appgroup && \
    adduser -u 1000 -G appgroup -h /app -D appuser

# Install runtime dependencies
RUN apk add --no-cache \
    ca-certificates \
    tzdata \
    sqlite-libs \
    curl

# Create necessary directories
RUN mkdir -p /app/data /app/logs /app/uploads && \
    chown -R appuser:appgroup /app

WORKDIR /app

# Copy binary from builder
COPY --from=builder /build/spec-manager /app/spec-manager

# Copy migrations and static files if any
COPY --from=builder /build/migrations /app/migrations
# COPY --from=builder /build/static /app/static

# Set ownership
RUN chown -R appuser:appgroup /app

# Switch to non-root user
USER appuser

# Environment variables
ENV APP_ENV=production \
    PORT=8080 \
    DB_PATH=/app/data/spec-manager.db \
    LOG_LEVEL=info \
    TZ=UTC

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8080/health || exit 1

# Run the application
ENTRYPOINT ["/app/spec-manager"]
CMD ["serve"]
```

**Verify:** Image builds successfully, runs as non-root, health check passes

---

## FIX-D2: Docker Compose Configuration

**Goal:** Complete development and production Docker Compose setup

**Create `docker-compose.yml`:**
```yaml
# ============================================================
# Spec Management Software - Docker Compose
# ============================================================

version: "3.9"

services:
  # ---- Main Application ----
  spec-manager:
    build:
      context: .
      dockerfile: Dockerfile
      args:
        VERSION: ${VERSION:-dev}
        BUILD_TIME: ${BUILD_TIME:-unknown}
        GIT_COMMIT: ${GIT_COMMIT:-unknown}
    image: spec-manager:${VERSION:-latest}
    container_name: spec-manager
    restart: unless-stopped
    ports:
      - "${PORT:-8080}:8080"
    volumes:
      # Persistent data
      - spec-data:/app/data
      - spec-logs:/app/logs
      - spec-uploads:/app/uploads
      # Optional: mount local specs for development
      # - ./specs:/app/specs:ro
    environment:
      - APP_ENV=${APP_ENV:-production}
      - PORT=8080
      - DB_PATH=/app/data/spec-manager.db
      - LOG_LEVEL=${LOG_LEVEL:-info}
      - JWT_SECRET=${JWT_SECRET:?JWT_SECRET is required}
      - CORS_ORIGINS=${CORS_ORIGINS:-http://localhost:3000}
      - MAX_UPLOAD_SIZE=${MAX_UPLOAD_SIZE:-10485760}
      - SESSION_TIMEOUT=${SESSION_TIMEOUT:-24h}
      # AI Integration (optional)
      - LLAMA_SERVER_PATH=${LLAMA_SERVER_PATH:-}
      - LLAMA_MODEL_PATH=${LLAMA_MODEL_PATH:-}
      # Metrics
      - METRICS_ENABLED=${METRICS_ENABLED:-true}
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/health"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 10s
    networks:
      - spec-network
    depends_on:
      - init-db
    labels:
      - "com.spec-manager.description=Spec Management API Server"
      - "com.spec-manager.version=${VERSION:-dev}"

  # ---- Database Initialization ----
  init-db:
    image: spec-manager:${VERSION:-latest}
    container_name: spec-manager-init
    command: ["migrate", "up"]
    volumes:
      - spec-data:/app/data
    environment:
      - DB_PATH=/app/data/spec-manager.db
    networks:
      - spec-network
    restart: "no"

  # ---- Frontend (Development) ----
  frontend:
    image: node:20-alpine
    container_name: spec-manager-frontend
    working_dir: /app
    command: sh -c "npm install && npm run dev -- --host 0.0.0.0"
    ports:
      - "${FRONTEND_PORT:-3000}:5173"
    volumes:
      - ./frontend:/app
      - frontend-node-modules:/app/node_modules
    environment:
      - VITE_API_URL=http://localhost:${PORT:-8080}
    networks:
      - spec-network
    profiles:
      - dev

  # ---- Prometheus (Monitoring) ----
  prometheus:
    image: prom/prometheus:v2.48.0
    container_name: spec-manager-prometheus
    ports:
      - "${PROMETHEUS_PORT:-9090}:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml:ro
      - prometheus-data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--storage.tsdb.retention.time=30d'
      - '--web.enable-lifecycle'
    networks:
      - spec-network
    profiles:
      - monitoring

  # ---- Grafana (Dashboards) ----
  grafana:
    image: grafana/grafana:10.2.0
    container_name: spec-manager-grafana
    ports:
      - "${GRAFANA_PORT:-3001}:3000"
    volumes:
      - grafana-data:/var/lib/grafana
      - ./monitoring/grafana/provisioning:/etc/grafana/provisioning:ro
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD:-admin}
      - GF_USERS_ALLOW_SIGN_UP=false
    networks:
      - spec-network
    depends_on:
      - prometheus
    profiles:
      - monitoring

  # ---- Backup Service ----
  backup:
    image: alpine:3.19
    container_name: spec-manager-backup
    volumes:
      - spec-data:/data:ro
      - ./backups:/backups
    command: >
      sh -c "
        while true; do
          TIMESTAMP=$$(date +%Y%m%d_%H%M%S)
          cp /data/spec-manager.db /backups/spec-manager_$$TIMESTAMP.db
          find /backups -name '*.db' -mtime +7 -delete
          sleep 86400
        done
      "
    restart: unless-stopped
    profiles:
      - backup

# ---- Networks ----
networks:
  spec-network:
    driver: bridge
    name: spec-manager-network

# ---- Volumes ----
volumes:
  spec-data:
    name: spec-manager-data
  spec-logs:
    name: spec-manager-logs
  spec-uploads:
    name: spec-manager-uploads
  frontend-node-modules:
    name: spec-manager-frontend-modules
  prometheus-data:
    name: spec-manager-prometheus
  grafana-data:
    name: spec-manager-grafana
```

**Create `docker-compose.override.yml` for development:**
```yaml
# Development overrides
version: "3.9"

services:
  spec-manager:
    build:
      target: builder
    volumes:
      - .:/build
      - spec-data:/app/data
    command: ["go", "run", "./cmd/server", "serve"]
    environment:
      - APP_ENV=development
      - LOG_LEVEL=debug
```

**Verify:** `docker-compose up` starts all services, volumes persist data

---

## FIX-D3: Makefile with Common Commands

**Goal:** Provide standardized development and deployment commands

**Create `Makefile`:**
```makefile
# ============================================================
# Spec Management Software - Makefile
# ============================================================

# Variables
APP_NAME := spec-manager
VERSION := $(shell git describe --tags --always --dirty 2>/dev/null || echo "dev")
BUILD_TIME := $(shell date -u '+%Y-%m-%dT%H:%M:%SZ')
GIT_COMMIT := $(shell git rev-parse --short HEAD 2>/dev/null || echo "unknown")
GO_FILES := $(shell find . -name '*.go' -not -path "./vendor/*")

# Build flags
LDFLAGS := -ldflags "-s -w \
	-X 'main.Version=$(VERSION)' \
	-X 'main.BuildTime=$(BUILD_TIME)' \
	-X 'main.GitCommit=$(GIT_COMMIT)'"

# Docker settings
DOCKER_IMAGE := $(APP_NAME)
DOCKER_TAG := $(VERSION)

# Colors for output
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m

.PHONY: all build run test clean docker help

# ---- Default Target ----
all: lint test build

# ---- Build Targets ----
build: ## Build the application binary
	@echo "$(GREEN)Building $(APP_NAME) $(VERSION)...$(NC)"
	CGO_ENABLED=1 go build $(LDFLAGS) -o bin/$(APP_NAME) ./cmd/server

build-linux: ## Build for Linux (cross-compile)
	@echo "$(GREEN)Building $(APP_NAME) for Linux...$(NC)"
	CGO_ENABLED=1 GOOS=linux GOARCH=amd64 go build $(LDFLAGS) -o bin/$(APP_NAME)-linux-amd64 ./cmd/server

build-all: build-linux ## Build for all platforms
	@echo "$(GREEN)All builds complete$(NC)"

install-deps: ## Install development dependencies
	@echo "$(GREEN)Installing dependencies...$(NC)"
	go mod download
	go install github.com/golangci/golangci-lint/cmd/golangci-lint@latest
	go install github.com/swaggo/swag/cmd/swag@latest

# ---- Development Targets ----
run: ## Run the application locally
	@echo "$(GREEN)Starting $(APP_NAME)...$(NC)"
	go run ./cmd/server serve

run-dev: ## Run with hot reload (requires air)
	@echo "$(GREEN)Starting $(APP_NAME) with hot reload...$(NC)"
	air

dev: ## Start development environment with Docker
	@echo "$(GREEN)Starting development environment...$(NC)"
	docker-compose --profile dev up --build

# ---- Testing Targets ----
test: ## Run all tests
	@echo "$(GREEN)Running tests...$(NC)"
	go test -v -race -coverprofile=coverage.out ./...

test-short: ## Run tests without race detector
	@echo "$(GREEN)Running quick tests...$(NC)"
	go test -v -short ./...

test-coverage: test ## Generate and view coverage report
	@echo "$(GREEN)Generating coverage report...$(NC)"
	go tool cover -html=coverage.out -o coverage.html
	@echo "$(GREEN)Coverage report: coverage.html$(NC)"

test-integration: ## Run integration tests
	@echo "$(GREEN)Running integration tests...$(NC)"
	go test -v -tags=integration ./...

benchmark: ## Run benchmarks
	@echo "$(GREEN)Running benchmarks...$(NC)"
	go test -bench=. -benchmem ./...

# ---- Code Quality ----
lint: ## Run linters
	@echo "$(GREEN)Running linters...$(NC)"
	golangci-lint run ./...

lint-fix: ## Run linters and fix issues
	@echo "$(GREEN)Running linters with auto-fix...$(NC)"
	golangci-lint run --fix ./...

fmt: ## Format code
	@echo "$(GREEN)Formatting code...$(NC)"
	gofmt -s -w $(GO_FILES)
	go mod tidy

vet: ## Run go vet
	@echo "$(GREEN)Running go vet...$(NC)"
	go vet ./...

security: ## Run security scanner
	@echo "$(GREEN)Running security scan...$(NC)"
	gosec -quiet ./...

# ---- Database Targets ----
migrate-up: ## Run database migrations
	@echo "$(GREEN)Running migrations...$(NC)"
	go run ./cmd/server migrate up

migrate-down: ## Rollback last migration
	@echo "$(GREEN)Rolling back migration...$(NC)"
	go run ./cmd/server migrate down

migrate-create: ## Create new migration (usage: make migrate-create NAME=add_users_table)
	@echo "$(GREEN)Creating migration: $(NAME)...$(NC)"
	go run ./cmd/server migrate create $(NAME)

db-seed: ## Seed database with sample data
	@echo "$(GREEN)Seeding database...$(NC)"
	go run ./cmd/server seed

db-reset: ## Reset database (drop and recreate)
	@echo "$(RED)WARNING: This will delete all data!$(NC)"
	@read -p "Are you sure? [y/N] " confirm && [ "$$confirm" = "y" ]
	rm -f data/*.db
	$(MAKE) migrate-up
	$(MAKE) db-seed

# ---- Docker Targets ----
docker-build: ## Build Docker image
	@echo "$(GREEN)Building Docker image...$(NC)"
	docker build \
		--build-arg VERSION=$(VERSION) \
		--build-arg BUILD_TIME=$(BUILD_TIME) \
		--build-arg GIT_COMMIT=$(GIT_COMMIT) \
		-t $(DOCKER_IMAGE):$(DOCKER_TAG) \
		-t $(DOCKER_IMAGE):latest .

docker-run: ## Run Docker container
	@echo "$(GREEN)Running Docker container...$(NC)"
	docker run -d \
		--name $(APP_NAME) \
		-p 8080:8080 \
		-v spec-data:/app/data \
		-e JWT_SECRET=$(JWT_SECRET) \
		$(DOCKER_IMAGE):$(DOCKER_TAG)

docker-push: ## Push Docker image to registry
	@echo "$(GREEN)Pushing Docker image...$(NC)"
	docker push $(DOCKER_IMAGE):$(DOCKER_TAG)
	docker push $(DOCKER_IMAGE):latest

docker-up: ## Start all services with Docker Compose
	@echo "$(GREEN)Starting services...$(NC)"
	docker-compose up -d

docker-down: ## Stop all services
	@echo "$(GREEN)Stopping services...$(NC)"
	docker-compose down

docker-logs: ## View container logs
	docker-compose logs -f

docker-clean: ## Remove all containers and volumes
	@echo "$(RED)WARNING: This will delete all data!$(NC)"
	@read -p "Are you sure? [y/N] " confirm && [ "$$confirm" = "y" ]
	docker-compose down -v --rmi local

# ---- Documentation ----
docs: ## Generate API documentation
	@echo "$(GREEN)Generating API docs...$(NC)"
	swag init -g cmd/server/main.go -o docs

docs-serve: docs ## Serve API documentation
	@echo "$(GREEN)Serving docs at http://localhost:8081/swagger/...$(NC)"
	go run ./cmd/server docs

# ---- Monitoring ----
monitoring-up: ## Start monitoring stack
	@echo "$(GREEN)Starting monitoring...$(NC)"
	docker-compose --profile monitoring up -d

monitoring-down: ## Stop monitoring stack
	@echo "$(GREEN)Stopping monitoring...$(NC)"
	docker-compose --profile monitoring down

# ---- Cleanup ----
clean: ## Clean build artifacts
	@echo "$(GREEN)Cleaning...$(NC)"
	rm -rf bin/
	rm -f coverage.out coverage.html
	go clean -cache -testcache

clean-all: clean docker-clean ## Full cleanup including Docker

# ---- Release ----
release: lint test build docker-build ## Full release build
	@echo "$(GREEN)Release $(VERSION) ready$(NC)"

tag: ## Create git tag (usage: make tag V=1.0.0)
	@echo "$(GREEN)Creating tag v$(V)...$(NC)"
	git tag -a v$(V) -m "Release v$(V)"
	git push origin v$(V)

# ---- Help ----
help: ## Show this help message
	@echo "$(GREEN)$(APP_NAME) - Available Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-18s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "Version: $(VERSION)"
	@echo "Commit:  $(GIT_COMMIT)"
```

**Verify:** `make help` shows all commands, `make build` compiles successfully

---

## FIX-D4: Enhanced Health Check Endpoint

**Goal:** Comprehensive health check with component status

**Create `internal/handlers/health_handler.go`:**
```go
package handlers

import (
    "context"
    "database/sql"
    "net/http"
    "runtime"
    "sync"
    "time"

    "github.com/gin-gonic/gin"
)

// ============================================================
// Health Check Handler - With Component Status
// ============================================================

type HealthStatus string

const (
    HealthStatusHealthy   HealthStatus = "healthy"
    HealthStatusDegraded  HealthStatus = "degraded"
    HealthStatusUnhealthy HealthStatus = "unhealthy"
)

type ComponentHealth struct {
    Name      string       `json:"name"`
    Status    HealthStatus `json:"status"`
    Message   string       `json:"message,omitempty"`
    Latency   string       `json:"latency,omitempty"`
    CheckedAt time.Time    `json:"checked_at"`
}

type HealthResponse struct {
    Status     HealthStatus      `json:"status"`
    Version    string            `json:"version"`
    Uptime     string            `json:"uptime"`
    Components []ComponentHealth `json:"components"`
    System     SystemInfo        `json:"system"`
    Timestamp  time.Time         `json:"timestamp"`
}

type SystemInfo struct {
    GoVersion    string `json:"go_version"`
    NumGoroutine int    `json:"num_goroutine"`
    NumCPU       int    `json:"num_cpu"`
    MemAlloc     uint64 `json:"mem_alloc_bytes"`
    MemSys       uint64 `json:"mem_sys_bytes"`
}

type HealthHandler struct {
    db           *sql.DB
    startTime    time.Time
    version      string
    buildTime    string
    gitCommit    string
    checkTimeout time.Duration
}

func NewHealthHandler(db *sql.DB, version, buildTime, gitCommit string) *HealthHandler {
    return &HealthHandler{
        db:           db,
        startTime:    time.Now(),
        version:      version,
        buildTime:    buildTime,
        gitCommit:    gitCommit,
        checkTimeout: 5 * time.Second,
    }
}

// Health performs comprehensive health check
func (h *HealthHandler) Health(c *gin.Context) {
    ctx, cancel := context.WithTimeout(c.Request.Context(), h.checkTimeout)
    defer cancel()

    // Check components in parallel
    components := h.checkComponents(ctx)

    // Determine overall status
    overallStatus := h.determineOverallStatus(components)

    // Get system info
    var mem runtime.MemStats
    runtime.ReadMemStats(&mem)

    response := HealthResponse{
        Status:     overallStatus,
        Version:    h.version,
        Uptime:     time.Since(h.startTime).Round(time.Second).String(),
        Components: components,
        System: SystemInfo{
            GoVersion:    runtime.Version(),
            NumGoroutine: runtime.NumGoroutine(),
            NumCPU:       runtime.NumCPU(),
            MemAlloc:     mem.Alloc,
            MemSys:       mem.Sys,
        },
        Timestamp: time.Now().UTC(),
    }

    statusCode := http.StatusOK
    if overallStatus == HealthStatusUnhealthy {
        statusCode = http.StatusServiceUnavailable
    } else if overallStatus == HealthStatusDegraded {
        statusCode = http.StatusOK // Still return 200 for degraded
    }

    c.JSON(statusCode, response)
}

// Liveness is a simple check for Kubernetes liveness probe
func (h *HealthHandler) Liveness(c *gin.Context) {
    c.JSON(http.StatusOK, gin.H{
        "status": "alive",
        "time":   time.Now().UTC(),
    })
}

// Readiness checks if the service is ready to receive traffic
func (h *HealthHandler) Readiness(c *gin.Context) {
    ctx, cancel := context.WithTimeout(c.Request.Context(), 2*time.Second)
    defer cancel()

    // Quick database ping
    if err := h.db.PingContext(ctx); err != nil {
        c.JSON(http.StatusServiceUnavailable, gin.H{
            "status":  "not_ready",
            "message": "database unavailable",
            "time":    time.Now().UTC(),
        })
        return
    }

    c.JSON(http.StatusOK, gin.H{
        "status": "ready",
        "time":   time.Now().UTC(),
    })
}

func (h *HealthHandler) checkComponents(ctx context.Context) []ComponentHealth {
    var wg sync.WaitGroup
    components := make([]ComponentHealth, 0, 3)
    componentsCh := make(chan ComponentHealth, 3)

    // Database check
    wg.Add(1)
    go func() {
        defer wg.Done()
        componentsCh <- h.checkDatabase(ctx)
    }()

    // Disk space check
    wg.Add(1)
    go func() {
        defer wg.Done()
        componentsCh <- h.checkDiskSpace()
    }()

    // Memory check
    wg.Add(1)
    go func() {
        defer wg.Done()
        componentsCh <- h.checkMemory()
    }()

    // Wait and collect
    go func() {
        wg.Wait()
        close(componentsCh)
    }()

    for component := range componentsCh {
        components = append(components, component)
    }

    return components
}

func (h *HealthHandler) checkDatabase(ctx context.Context) ComponentHealth {
    start := time.Now()
    component := ComponentHealth{
        Name:      "database",
        CheckedAt: time.Now().UTC(),
    }

    if err := h.db.PingContext(ctx); err != nil {
        component.Status = HealthStatusUnhealthy
        component.Message = "database ping failed: " + err.Error()
        return component
    }

    // Check if we can execute a simple query
    var result int
    err := h.db.QueryRowContext(ctx, "SELECT 1").Scan(&result)
    if err != nil {
        component.Status = HealthStatusDegraded
        component.Message = "database query failed: " + err.Error()
        return component
    }

    latency := time.Since(start)
    component.Latency = latency.String()

    if latency > 100*time.Millisecond {
        component.Status = HealthStatusDegraded
        component.Message = "high latency"
    } else {
        component.Status = HealthStatusHealthy
        component.Message = "connected"
    }

    return component
}

func (h *HealthHandler) checkDiskSpace() ComponentHealth {
    component := ComponentHealth{
        Name:      "disk",
        CheckedAt: time.Now().UTC(),
    }

    // This is a simplified check - in production, use syscall.Statfs
    // to get actual disk usage
    component.Status = HealthStatusHealthy
    component.Message = "ok"

    return component
}

func (h *HealthHandler) checkMemory() ComponentHealth {
    var mem runtime.MemStats
    runtime.ReadMemStats(&mem)

    component := ComponentHealth{
        Name:      "memory",
        CheckedAt: time.Now().UTC(),
    }

    // Check if memory usage is concerning (>1GB allocated)
    if mem.Alloc > 1<<30 {
        component.Status = HealthStatusDegraded
        component.Message = "high memory usage"
    } else {
        component.Status = HealthStatusHealthy
        component.Message = "ok"
    }

    return component
}

func (h *HealthHandler) determineOverallStatus(components []ComponentHealth) HealthStatus {
    hasUnhealthy := false
    hasDegraded := false

    for _, c := range components {
        switch c.Status {
        case HealthStatusUnhealthy:
            hasUnhealthy = true
        case HealthStatusDegraded:
            hasDegraded = true
        }
    }

    if hasUnhealthy {
        return HealthStatusUnhealthy
    }
    if hasDegraded {
        return HealthStatusDegraded
    }
    return HealthStatusHealthy
}

// RegisterRoutes registers health check routes
func (h *HealthHandler) RegisterRoutes(r *gin.Engine) {
    r.GET("/health", h.Health)
    r.GET("/healthz", h.Liveness)  // Kubernetes liveness
    r.GET("/readyz", h.Readiness)  // Kubernetes readiness
}
```

**Verify:** `/health` returns component statuses, `/healthz` and `/readyz` work for K8s

---

## FIX-D5: Prometheus Metrics Endpoint

**Goal:** Expose application metrics for monitoring

**Create `internal/metrics/metrics.go`:**
```go
package metrics

import (
    "strconv"
    "sync"
    "time"

    "github.com/gin-gonic/gin"
    "github.com/prometheus/client_golang/prometheus"
    "github.com/prometheus/client_golang/prometheus/promauto"
    "github.com/prometheus/client_golang/prometheus/promhttp"
)

// ============================================================
// Prometheus Metrics - Application Instrumentation
// ============================================================

var (
    // HTTP Metrics
    httpRequestsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Name: "spec_manager_http_requests_total",
            Help: "Total number of HTTP requests",
        },
        []string{"method", "path", "status"},
    )

    httpRequestDuration = promauto.NewHistogramVec(
        prometheus.HistogramOpts{
            Name:    "spec_manager_http_request_duration_seconds",
            Help:    "HTTP request duration in seconds",
            Buckets: prometheus.DefBuckets,
        },
        []string{"method", "path"},
    )

    httpRequestsInFlight = promauto.NewGauge(
        prometheus.GaugeOpts{
            Name: "spec_manager_http_requests_in_flight",
            Help: "Current number of HTTP requests being processed",
        },
    )

    // Database Metrics
    dbQueryTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Name: "spec_manager_db_queries_total",
            Help: "Total number of database queries",
        },
        []string{"operation", "table"},
    )

    dbQueryDuration = promauto.NewHistogramVec(
        prometheus.HistogramOpts{
            Name:    "spec_manager_db_query_duration_seconds",
            Help:    "Database query duration in seconds",
            Buckets: []float64{.001, .005, .01, .025, .05, .1, .25, .5, 1},
        },
        []string{"operation", "table"},
    )

    dbConnectionsActive = promauto.NewGauge(
        prometheus.GaugeOpts{
            Name: "spec_manager_db_connections_active",
            Help: "Number of active database connections",
        },
    )

    // Business Metrics
    projectsTotal = promauto.NewGauge(
        prometheus.GaugeOpts{
            Name: "spec_manager_projects_total",
            Help: "Total number of projects",
        },
    )

    specsTotal = promauto.NewGauge(
        prometheus.GaugeOpts{
            Name: "spec_manager_specs_total",
            Help: "Total number of spec files",
        },
    )

    activeUsersTotal = promauto.NewGauge(
        prometheus.GaugeOpts{
            Name: "spec_manager_active_users_total",
            Help: "Number of active users in the last 24 hours",
        },
    )

    aiRequestsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Name: "spec_manager_ai_requests_total",
            Help: "Total number of AI requests",
        },
        []string{"model", "status"},
    )

    aiRequestDuration = promauto.NewHistogramVec(
        prometheus.HistogramOpts{
            Name:    "spec_manager_ai_request_duration_seconds",
            Help:    "AI request duration in seconds",
            Buckets: []float64{.5, 1, 2.5, 5, 10, 30, 60, 120},
        },
        []string{"model"},
    )

    // Auth Metrics
    loginAttemptsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Name: "spec_manager_login_attempts_total",
            Help: "Total number of login attempts",
        },
        []string{"status"},
    )

    activeSessionsTotal = promauto.NewGauge(
        prometheus.GaugeOpts{
            Name: "spec_manager_active_sessions_total",
            Help: "Number of active user sessions",
        },
    )

    // Cache Metrics
    cacheHitsTotal = promauto.NewCounter(
        prometheus.CounterOpts{
            Name: "spec_manager_cache_hits_total",
            Help: "Total number of cache hits",
        },
    )

    cacheMissesTotal = promauto.NewCounter(
        prometheus.CounterOpts{
            Name: "spec_manager_cache_misses_total",
            Help: "Total number of cache misses",
        },
    )

    // Error Metrics
    errorsTotal = promauto.NewCounterVec(
        prometheus.CounterOpts{
            Name: "spec_manager_errors_total",
            Help: "Total number of errors by type",
        },
        []string{"type", "code"},
    )

    // Application Info
    appInfo = promauto.NewGaugeVec(
        prometheus.GaugeOpts{
            Name: "spec_manager_app_info",
            Help: "Application version information",
        },
        []string{"version", "build_time", "git_commit"},
    )
)

// Collector for periodic business metrics
type BusinessMetricsCollector struct {
    mu              sync.Mutex
    updateInterval  time.Duration
    stopCh          chan struct{}
    getProjectCount func() (int64, error)
    getSpecCount    func() (int64, error)
    getActiveUsers  func() (int64, error)
    getActiveSessions func() (int64, error)
}

func NewBusinessMetricsCollector(
    getProjectCount, getSpecCount, getActiveUsers, getActiveSessions func() (int64, error),
) *BusinessMetricsCollector {
    return &BusinessMetricsCollector{
        updateInterval:    1 * time.Minute,
        stopCh:            make(chan struct{}),
        getProjectCount:   getProjectCount,
        getSpecCount:      getSpecCount,
        getActiveUsers:    getActiveUsers,
        getActiveSessions: getActiveSessions,
    }
}

func (c *BusinessMetricsCollector) Start() {
    go c.run()
}

func (c *BusinessMetricsCollector) Stop() {
    close(c.stopCh)
}

func (c *BusinessMetricsCollector) run() {
    ticker := time.NewTicker(c.updateInterval)
    defer ticker.Stop()

    // Update immediately
    c.update()

    for {
        select {
        case <-ticker.C:
            c.update()
        case <-c.stopCh:
            return
        }
    }
}

func (c *BusinessMetricsCollector) update() {
    c.mu.Lock()
    defer c.mu.Unlock()

    if count, err := c.getProjectCount(); err == nil {
        projectsTotal.Set(float64(count))
    }

    if count, err := c.getSpecCount(); err == nil {
        specsTotal.Set(float64(count))
    }

    if count, err := c.getActiveUsers(); err == nil {
        activeUsersTotal.Set(float64(count))
    }

    if count, err := c.getActiveSessions(); err == nil {
        activeSessionsTotal.Set(float64(count))
    }
}

// ============================================================
// Gin Middleware for Metrics
// ============================================================

func PrometheusMiddleware() gin.HandlerFunc {
    return func(c *gin.Context) {
        // Skip metrics endpoint itself
        if c.Request.URL.Path == "/metrics" {
            c.Next()
            return
        }

        start := time.Now()
        httpRequestsInFlight.Inc()

        c.Next()

        httpRequestsInFlight.Dec()
        duration := time.Since(start).Seconds()
        status := strconv.Itoa(c.Writer.Status())
        path := c.FullPath() // Use route pattern, not actual path
        if path == "" {
            path = "unknown"
        }

        httpRequestsTotal.WithLabelValues(c.Request.Method, path, status).Inc()
        httpRequestDuration.WithLabelValues(c.Request.Method, path).Observe(duration)
    }
}

// ============================================================
// Metric Recording Functions
// ============================================================

func RecordDBQuery(operation, table string, duration time.Duration) {
    dbQueryTotal.WithLabelValues(operation, table).Inc()
    dbQueryDuration.WithLabelValues(operation, table).Observe(duration.Seconds())
}

func SetDBConnections(count int) {
    dbConnectionsActive.Set(float64(count))
}

func RecordAIRequest(model, status string, duration time.Duration) {
    aiRequestsTotal.WithLabelValues(model, status).Inc()
    aiRequestDuration.WithLabelValues(model).Observe(duration.Seconds())
}

func RecordLoginAttempt(success bool) {
    status := "failure"
    if success {
        status = "success"
    }
    loginAttemptsTotal.WithLabelValues(status).Inc()
}

func RecordError(errorType, code string) {
    errorsTotal.WithLabelValues(errorType, code).Inc()
}

func RecordCacheHit() {
    cacheHitsTotal.Inc()
}

func RecordCacheMiss() {
    cacheMissesTotal.Inc()
}

func SetAppInfo(version, buildTime, gitCommit string) {
    appInfo.WithLabelValues(version, buildTime, gitCommit).Set(1)
}

// ============================================================
// Metrics Handler
// ============================================================

func MetricsHandler() gin.HandlerFunc {
    h := promhttp.Handler()
    return func(c *gin.Context) {
        h.ServeHTTP(c.Writer, c.Request)
    }
}

// RegisterMetrics registers the /metrics endpoint
func RegisterMetrics(r *gin.Engine) {
    r.GET("/metrics", MetricsHandler())
}
```

**Create `monitoring/prometheus.yml`:**
```yaml
# Prometheus configuration for Spec Manager
global:
  scrape_interval: 15s
  evaluation_interval: 15s

alerting:
  alertmanagers:
    - static_configs:
        - targets: []

rule_files: []

scrape_configs:
  - job_name: 'spec-manager'
    static_configs:
      - targets: ['spec-manager:8080']
    metrics_path: /metrics
    scheme: http
```

**Verify:** `/metrics` returns Prometheus format, Prometheus scrapes successfully

---

## FIX-D6: GitHub Actions CI/CD Workflow

**Goal:** Complete CI/CD pipeline with testing, building, and deployment

**Create `.github/workflows/ci.yml`:**
```yaml
# ============================================================
# Spec Management Software - CI/CD Pipeline
# ============================================================

name: CI/CD

on:
  push:
    branches: [main, develop]
    tags: ['v*']
  pull_request:
    branches: [main, develop]

env:
  GO_VERSION: '1.22'
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  # ---- Lint Job ----
  lint:
    name: Lint
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: ${{ env.GO_VERSION }}
          cache: true

      - name: Run golangci-lint
        uses: golangci/golangci-lint-action@v4
        with:
          version: latest
          args: --timeout=5m

  # ---- Test Job ----
  test:
    name: Test
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: ${{ env.GO_VERSION }}
          cache: true

      - name: Install SQLite
        run: sudo apt-get update && sudo apt-get install -y sqlite3 libsqlite3-dev

      - name: Run tests
        run: |
          go test -v -race -coverprofile=coverage.out -covermode=atomic ./...

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v4
        with:
          files: coverage.out
          fail_ci_if_error: false

      - name: Run integration tests
        run: go test -v -tags=integration ./...
        env:
          TEST_DB_PATH: ":memory:"

  # ---- Security Scan Job ----
  security:
    name: Security Scan
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Run Gosec Security Scanner
        uses: securego/gosec@master
        with:
          args: '-no-fail -fmt sarif -out results.sarif ./...'

      - name: Upload SARIF file
        uses: github/codeql-action/upload-sarif@v3
        with:
          sarif_file: results.sarif
        if: always()

      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          ignore-unfixed: true
          format: 'sarif'
          output: 'trivy-results.sarif'
          severity: 'CRITICAL,HIGH'

  # ---- Build Job ----
  build:
    name: Build
    runs-on: ubuntu-latest
    needs: [test, security]
    outputs:
      version: ${{ steps.meta.outputs.version }}
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Set up Go
        uses: actions/setup-go@v5
        with:
          go-version: ${{ env.GO_VERSION }}
          cache: true

      - name: Install SQLite
        run: sudo apt-get update && sudo apt-get install -y sqlite3 libsqlite3-dev

      - name: Get version
        id: meta
        run: |
          if [[ $GITHUB_REF == refs/tags/* ]]; then
            VERSION=${GITHUB_REF#refs/tags/v}
          else
            VERSION=$(git describe --tags --always --dirty)
          fi
          echo "version=$VERSION" >> $GITHUB_OUTPUT
          echo "Building version: $VERSION"

      - name: Build binary
        run: |
          CGO_ENABLED=1 go build \
            -ldflags="-s -w \
              -X 'main.Version=${{ steps.meta.outputs.version }}' \
              -X 'main.BuildTime=$(date -u +%Y-%m-%dT%H:%M:%SZ)' \
              -X 'main.GitCommit=${{ github.sha }}'" \
            -o bin/spec-manager \
            ./cmd/server

      - name: Upload binary artifact
        uses: actions/upload-artifact@v4
        with:
          name: spec-manager-linux-amd64
          path: bin/spec-manager
          retention-days: 7

  # ---- Docker Build Job ----
  docker:
    name: Docker Build
    runs-on: ubuntu-latest
    needs: build
    permissions:
      contents: read
      packages: write
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up QEMU
        uses: docker/setup-qemu-action@v3

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Log in to Container Registry
        if: github.event_name != 'pull_request'
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=ref,event=branch
            type=ref,event=pr
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}
            type=sha

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: ${{ github.event_name != 'pull_request' }}
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
          build-args: |
            VERSION=${{ needs.build.outputs.version }}
            BUILD_TIME=${{ github.event.head_commit.timestamp }}
            GIT_COMMIT=${{ github.sha }}
          platforms: linux/amd64,linux/arm64

  # ---- Deploy Job (Staging) ----
  deploy-staging:
    name: Deploy to Staging
    runs-on: ubuntu-latest
    needs: docker
    if: github.ref == 'refs/heads/develop'
    environment:
      name: staging
      url: https://staging.spec-manager.example.com
    steps:
      - name: Deploy to staging
        run: |
          echo "Deploying to staging environment..."
          # Add your deployment commands here
          # Examples:
          # - kubectl set image deployment/spec-manager spec-manager=${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:develop
          # - ssh deploy@staging "docker pull ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:develop && docker-compose up -d"

      - name: Run smoke tests
        run: |
          echo "Running smoke tests..."
          # curl -f https://staging.spec-manager.example.com/health

  # ---- Deploy Job (Production) ----
  deploy-production:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: docker
    if: startsWith(github.ref, 'refs/tags/v')
    environment:
      name: production
      url: https://spec-manager.example.com
    steps:
      - name: Deploy to production
        run: |
          echo "Deploying to production environment..."
          # Add your production deployment commands here

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v1
        with:
          generate_release_notes: true
          files: |
            bin/spec-manager-*
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

      - name: Notify deployment
        run: |
          echo "Production deployment complete!"
          # Add Slack/Discord notification here

  # ---- Cleanup Job ----
  cleanup:
    name: Cleanup Old Artifacts
    runs-on: ubuntu-latest
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
    steps:
      - name: Delete old workflow runs
        uses: Mattraks/delete-workflow-runs@v2
        with:
          retain_days: 30
          keep_minimum_runs: 10
```

**Create `.github/workflows/codeql.yml`:**
```yaml
# CodeQL Security Analysis
name: "CodeQL"

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]
  schedule:
    - cron: '30 1 * * 0'

jobs:
  analyze:
    name: Analyze
    runs-on: ubuntu-latest
    permissions:
      actions: read
      contents: read
      security-events: write

    strategy:
      fail-fast: false
      matrix:
        language: ['go']

    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Initialize CodeQL
        uses: github/codeql-action/init@v3
        with:
          languages: ${{ matrix.language }}

      - name: Autobuild
        uses: github/codeql-action/autobuild@v3

      - name: Perform CodeQL Analysis
        uses: github/codeql-action/analyze@v3
        with:
          category: "/language:${{matrix.language}}"
```

**Create `.github/dependabot.yml`:**
```yaml
# Dependabot configuration
version: 2
updates:
  - package-ecosystem: "gomod"
    directory: "/"
    schedule:
      interval: "weekly"
    open-pull-requests-limit: 10
    labels:
      - "dependencies"
      - "go"

  - package-ecosystem: "docker"
    directory: "/"
    schedule:
      interval: "weekly"
    labels:
      - "dependencies"
      - "docker"

  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
    labels:
      - "dependencies"
      - "ci"
```

**Verify:** CI pipeline runs on push, builds and tests pass, Docker image is published

---

## Fix Completion Tracking

```
Critical Fixes:
[ ] FIX-A1  [ ] FIX-A2  [ ] FIX-A3  [ ] FIX-A4
[ ] FIX-A5  [ ] FIX-A6  [ ] FIX-A7  [ ] FIX-A8
[ ] FIX-A9  [ ] FIX-A10 [ ] FIX-A11 [ ] FIX-A12

Backend Medium Fixes:
[ ] FIX-B1  [ ] FIX-B2  [ ] FIX-B3  [ ] FIX-B4
[ ] FIX-B5  [ ] FIX-B6  [ ] FIX-B7  [ ] FIX-B8

Frontend Medium Fixes:
[ ] FIX-C1  [ ] FIX-C2  [ ] FIX-C3
[ ] FIX-C4  [ ] FIX-C5  [ ] FIX-C6

Infrastructure Fixes:
[ ] FIX-D1  [ ] FIX-D2  [ ] FIX-D3
[ ] FIX-D4  [ ] FIX-D5  [ ] FIX-D6
```

---

## Cross-References

> **Note:** Specs migrated to consolidated `05-features/` structure.

- [Implementation Guidelines](./04-implementation-guidelines.md) - Original phases
- [API Client](../05-features/15-api-client/00-overview.md) - API contract
- [Database Schema](../07-database-design/00-overview.md) - Table definitions
- [Error Management](../06-error-management/00-overview.md) - Error codes
