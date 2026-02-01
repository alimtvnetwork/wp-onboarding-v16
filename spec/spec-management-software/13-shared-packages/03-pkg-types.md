# pkg/types Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Priority:** P0 (Foundational)  

---

## Overview

The `pkg/types` package defines shared type definitions, DTOs, and domain primitives used across all SpecBuilder Pro microservices. It enforces type safety through typed IDs, provides standard response wrappers, and defines common enums.

**Cross-References:**
- [Coding Guidelines](../04-coding-guidelines/00-overview.md)
- [pkg/errors Specification](./02-pkg-errors.md)

---

## File Structure

```
pkg/types/
├── ids.go         # Typed ID definitions
├── pagination.go  # Pagination types
├── response.go    # API response wrappers
├── metadata.go    # Common metadata types
├── enums.go       # Shared enum definitions
├── time.go        # Time utilities
└── types_test.go  # Comprehensive tests
```

---

## ids.go

```go
package types

import (
    "database/sql/driver"
    "encoding/json"
    "fmt"
    
    "github.com/google/uuid"
)

// ID is the base interface for all typed IDs
type ID interface {
    String() string
    IsZero() bool
    Validate() error
}

// ============ Project ID ============

// ProjectID is a typed identifier for projects
type ProjectID struct {
    value uuid.UUID
}

// NewProjectID creates a new random ProjectID
func NewProjectID() ProjectID {
    return ProjectID{value: uuid.New()}
}

// ParseProjectID parses a string into a ProjectID
func ParseProjectID(s string) (ProjectID, error) {
    id, err := uuid.Parse(s)
    if err != nil {
        return ProjectID{}, fmt.Errorf("invalid project ID: %w", err)
    }
    return ProjectID{value: id}, nil
}

// MustParseProjectID parses or panics
func MustParseProjectID(s string) ProjectID {
    id, err := ParseProjectID(s)
    if err != nil {
        panic(err)
    }
    return id
}

func (id ProjectID) String() string   { return id.value.String() }
func (id ProjectID) IsZero() bool     { return id.value == uuid.Nil }
func (id ProjectID) Validate() error {
    if id.IsZero() {
        return fmt.Errorf("project ID cannot be zero")
    }
    return nil
}

// Value implements driver.Valuer for database storage
func (id ProjectID) Value() (driver.Value, error) {
    return id.value.String(), nil
}

// Scan implements sql.Scanner for database retrieval
func (id *ProjectID) Scan(src any) error {
    switch v := src.(type) {
    case string:
        parsed, err := uuid.Parse(v)
        if err != nil {
            return err
        }
        id.value = parsed
    case []byte:
        parsed, err := uuid.Parse(string(v))
        if err != nil {
            return err
        }
        id.value = parsed
    default:
        return fmt.Errorf("cannot scan %T into ProjectID", src)
    }
    return nil
}

// MarshalJSON implements json.Marshaler
func (id ProjectID) MarshalJSON() ([]byte, error) {
    return json.Marshal(id.value.String())
}

// UnmarshalJSON implements json.Unmarshaler
func (id *ProjectID) UnmarshalJSON(data []byte) error {
    var s string
    if err := json.Unmarshal(data, &s); err != nil {
        return err
    }
    parsed, err := uuid.Parse(s)
    if err != nil {
        return err
    }
    id.value = parsed
    return nil
}

// ============ Spec ID ============

// SpecID is a typed identifier for specifications
type SpecID struct {
    value uuid.UUID
}

func NewSpecID() SpecID                          { return SpecID{value: uuid.New()} }
func ParseSpecID(s string) (SpecID, error)       { /* similar to ProjectID */ }
func MustParseSpecID(s string) SpecID            { /* similar to ProjectID */ }
func (id SpecID) String() string                 { return id.value.String() }
func (id SpecID) IsZero() bool                   { return id.value == uuid.Nil }
func (id SpecID) Validate() error                { /* similar to ProjectID */ }
func (id SpecID) Value() (driver.Value, error)   { return id.value.String(), nil }
func (id *SpecID) Scan(src any) error            { /* similar to ProjectID */ }
func (id SpecID) MarshalJSON() ([]byte, error)   { /* similar to ProjectID */ }
func (id *SpecID) UnmarshalJSON(data []byte) error { /* similar to ProjectID */ }

// ============ Conversation ID ============

// ConversationID is a typed identifier for conversations
type ConversationID struct {
    value uuid.UUID
}

func NewConversationID() ConversationID                          { return ConversationID{value: uuid.New()} }
func ParseConversationID(s string) (ConversationID, error)       { /* similar pattern */ }
func MustParseConversationID(s string) ConversationID            { /* similar pattern */ }
func (id ConversationID) String() string                         { return id.value.String() }
func (id ConversationID) IsZero() bool                           { return id.value == uuid.Nil }
func (id ConversationID) Validate() error                        { /* similar pattern */ }
func (id ConversationID) Value() (driver.Value, error)           { return id.value.String(), nil }
func (id *ConversationID) Scan(src any) error                    { /* similar pattern */ }
func (id ConversationID) MarshalJSON() ([]byte, error)           { /* similar pattern */ }
func (id *ConversationID) UnmarshalJSON(data []byte) error       { /* similar pattern */ }

// ============ Block ID ============

// BlockID is a typed identifier for Nexus-Flow blocks
type BlockID struct {
    value uuid.UUID
}

func NewBlockID() BlockID                          { return BlockID{value: uuid.New()} }
func ParseBlockID(s string) (BlockID, error)       { /* similar pattern */ }
func (id BlockID) String() string                  { return id.value.String() }
func (id BlockID) IsZero() bool                    { return id.value == uuid.Nil }

// ============ Execution ID ============

// ExecutionID is a typed identifier for Nexus-Flow executions
type ExecutionID struct {
    value uuid.UUID
}

func NewExecutionID() ExecutionID                  { return ExecutionID{value: uuid.New()} }
func ParseExecutionID(s string) (ExecutionID, error) { /* similar pattern */ }
func (id ExecutionID) String() string              { return id.value.String() }
func (id ExecutionID) IsZero() bool                { return id.value == uuid.Nil }

// ============ User ID ============

// UserID is a typed identifier for users
type UserID struct {
    value uuid.UUID
}

func NewUserID() UserID                          { return UserID{value: uuid.New()} }
func ParseUserID(s string) (UserID, error)       { /* similar pattern */ }
func (id UserID) String() string                 { return id.value.String() }
func (id UserID) IsZero() bool                   { return id.value == uuid.Nil }
```

---

## pagination.go

```go
package types

import (
    "net/http"
    "strconv"
)

// PageRequest represents pagination parameters
type PageRequest struct {
    Page     int    `json:"page"`
    PageSize int    `json:"pageSize"`
    SortBy   string `json:"sortBy,omitempty"`
    SortDir  SortDirection `json:"sortDir,omitempty"`
}

// SortDirection represents sort order
type SortDirection string

const (
    SortAsc  SortDirection = "asc"
    SortDesc SortDirection = "desc"
)

// DefaultPageRequest returns default pagination
func DefaultPageRequest() PageRequest {
    return PageRequest{
        Page:     1,
        PageSize: 20,
        SortDir:  SortDesc,
    }
}

// ParsePageRequest extracts pagination from HTTP request
func ParsePageRequest(r *http.Request) PageRequest {
    req := DefaultPageRequest()
    
    if page := r.URL.Query().Get("page"); page != "" {
        if p, err := strconv.Atoi(page); err == nil && p > 0 {
            req.Page = p
        }
    }
    
    if pageSize := r.URL.Query().Get("pageSize"); pageSize != "" {
        if ps, err := strconv.Atoi(pageSize); err == nil && ps > 0 && ps <= 100 {
            req.PageSize = ps
        }
    }
    
    if sortBy := r.URL.Query().Get("sortBy"); sortBy != "" {
        req.SortBy = sortBy
    }
    
    if sortDir := r.URL.Query().Get("sortDir"); sortDir != "" {
        if sortDir == "asc" {
            req.SortDir = SortAsc
        } else {
            req.SortDir = SortDesc
        }
    }
    
    return req
}

// Offset calculates the SQL offset
func (p PageRequest) Offset() int {
    return (p.Page - 1) * p.PageSize
}

// Limit returns the page size (alias for clarity)
func (p PageRequest) Limit() int {
    return p.PageSize
}

// Validate ensures pagination is within bounds
func (p PageRequest) Validate() error {
    if p.Page < 1 {
        return fmt.Errorf("page must be >= 1")
    }
    if p.PageSize < 1 || p.PageSize > 100 {
        return fmt.Errorf("pageSize must be between 1 and 100")
    }
    return nil
}

// PageResponse contains pagination metadata
type PageResponse[T any] struct {
    Items      []T  `json:"items"`
    Page       int  `json:"page"`
    PageSize   int  `json:"pageSize"`
    TotalItems int  `json:"totalItems"`
    TotalPages int  `json:"totalPages"`
    HasNext    bool `json:"hasNext"`
    HasPrev    bool `json:"hasPrev"`
}

// NewPageResponse creates a paginated response
func NewPageResponse[T any](items []T, req PageRequest, totalItems int) PageResponse[T] {
    totalPages := (totalItems + req.PageSize - 1) / req.PageSize
    if totalPages < 1 {
        totalPages = 1
    }
    
    return PageResponse[T]{
        Items:      items,
        Page:       req.Page,
        PageSize:   req.PageSize,
        TotalItems: totalItems,
        TotalPages: totalPages,
        HasNext:    req.Page < totalPages,
        HasPrev:    req.Page > 1,
    }
}

// CursorRequest represents cursor-based pagination
type CursorRequest struct {
    Cursor   string `json:"cursor,omitempty"`
    Limit    int    `json:"limit"`
    Forward  bool   `json:"forward"`
}

// CursorResponse contains cursor pagination data
type CursorResponse[T any] struct {
    Items      []T    `json:"items"`
    NextCursor string `json:"nextCursor,omitempty"`
    PrevCursor string `json:"prevCursor,omitempty"`
    HasMore    bool   `json:"hasMore"`
}
```

---

## response.go

```go
package types

import (
    "encoding/json"
    "net/http"
    "time"
)

// Response is the standard API response wrapper
type Response[T any] struct {
    Success   bool       `json:"success"`
    Data      T          `json:"data,omitempty"`
    Meta      *Meta      `json:"meta,omitempty"`
    Timestamp time.Time  `json:"timestamp"`
}

// Meta contains response metadata
type Meta struct {
    RequestID  string `json:"requestId,omitempty"`
    Duration   string `json:"duration,omitempty"`
    Version    string `json:"version,omitempty"`
}

// NewResponse creates a successful response
func NewResponse[T any](data T) Response[T] {
    return Response[T]{
        Success:   true,
        Data:      data,
        Timestamp: time.Now().UTC(),
    }
}

// NewResponseWithMeta creates a response with metadata
func NewResponseWithMeta[T any](data T, meta Meta) Response[T] {
    return Response[T]{
        Success:   true,
        Data:      data,
        Meta:      &meta,
        Timestamp: time.Now().UTC(),
    }
}

// Write sends the response as JSON
func (r Response[T]) Write(w http.ResponseWriter) error {
    w.Header().Set("Content-Type", "application/json")
    return json.NewEncoder(w).Encode(r)
}

// WriteWithStatus sends the response with a custom status
func (r Response[T]) WriteWithStatus(w http.ResponseWriter, status int) error {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(status)
    return json.NewEncoder(w).Encode(r)
}

// EmptyResponse is used when no data is returned
type EmptyResponse struct {
    Success   bool      `json:"success"`
    Message   string    `json:"message,omitempty"`
    Timestamp time.Time `json:"timestamp"`
}

// NewEmptyResponse creates an empty success response
func NewEmptyResponse() EmptyResponse {
    return EmptyResponse{
        Success:   true,
        Timestamp: time.Now().UTC(),
    }
}

// NewEmptyResponseWithMessage creates a response with a message
func NewEmptyResponseWithMessage(message string) EmptyResponse {
    return EmptyResponse{
        Success:   true,
        Message:   message,
        Timestamp: time.Now().UTC(),
    }
}

// CreatedResponse is returned after resource creation
type CreatedResponse[T ID] struct {
    Success   bool      `json:"success"`
    ID        T         `json:"id"`
    Message   string    `json:"message,omitempty"`
    Timestamp time.Time `json:"timestamp"`
}

// NewCreatedResponse creates a creation response
func NewCreatedResponse[T ID](id T, message string) CreatedResponse[T] {
    return CreatedResponse[T]{
        Success:   true,
        ID:        id,
        Message:   message,
        Timestamp: time.Now().UTC(),
    }
}
```

---

## metadata.go

```go
package types

import (
    "database/sql/driver"
    "encoding/json"
    "time"
)

// Timestamps contains standard timestamp fields
type Timestamps struct {
    CreatedAt time.Time  `json:"createdAt"`
    UpdatedAt time.Time  `json:"updatedAt"`
    DeletedAt *time.Time `json:"deletedAt,omitempty"`
}

// NewTimestamps creates timestamps with current time
func NewTimestamps() Timestamps {
    now := time.Now().UTC()
    return Timestamps{
        CreatedAt: now,
        UpdatedAt: now,
    }
}

// Touch updates the UpdatedAt timestamp
func (t *Timestamps) Touch() {
    t.UpdatedAt = time.Now().UTC()
}

// SoftDelete marks the record as deleted
func (t *Timestamps) SoftDelete() {
    now := time.Now().UTC()
    t.DeletedAt = &now
}

// IsDeleted checks if record is soft-deleted
func (t Timestamps) IsDeleted() bool {
    return t.DeletedAt != nil
}

// Versioned contains version control fields
type Versioned struct {
    Version   int       `json:"version"`
    UpdatedBy *UserID   `json:"updatedBy,omitempty"`
    UpdatedAt time.Time `json:"updatedAt"`
}

// NewVersioned creates initial version info
func NewVersioned() Versioned {
    return Versioned{
        Version:   1,
        UpdatedAt: time.Now().UTC(),
    }
}

// Increment bumps the version number
func (v *Versioned) Increment(by *UserID) {
    v.Version++
    v.UpdatedBy = by
    v.UpdatedAt = time.Now().UTC()
}

// Tags represents a list of string tags
type Tags []string

// Value implements driver.Valuer
func (t Tags) Value() (driver.Value, error) {
    if t == nil {
        return "[]", nil
    }
    return json.Marshal(t)
}

// Scan implements sql.Scanner
func (t *Tags) Scan(src any) error {
    switch v := src.(type) {
    case string:
        return json.Unmarshal([]byte(v), t)
    case []byte:
        return json.Unmarshal(v, t)
    case nil:
        *t = nil
        return nil
    default:
        return fmt.Errorf("cannot scan %T into Tags", src)
    }
}

// Contains checks if tag exists
func (t Tags) Contains(tag string) bool {
    for _, existing := range t {
        if existing == tag {
            return true
        }
    }
    return false
}

// Add appends a tag if not present
func (t *Tags) Add(tag string) {
    if !t.Contains(tag) {
        *t = append(*t, tag)
    }
}

// Remove removes a tag
func (t *Tags) Remove(tag string) {
    result := make(Tags, 0, len(*t))
    for _, existing := range *t {
        if existing != tag {
            result = append(result, existing)
        }
    }
    *t = result
}

// Metadata is a flexible key-value store
type Metadata map[string]any

// Value implements driver.Valuer
func (m Metadata) Value() (driver.Value, error) {
    if m == nil {
        return "{}", nil
    }
    return json.Marshal(m)
}

// Scan implements sql.Scanner
func (m *Metadata) Scan(src any) error {
    switch v := src.(type) {
    case string:
        return json.Unmarshal([]byte(v), m)
    case []byte:
        return json.Unmarshal(v, m)
    case nil:
        *m = nil
        return nil
    default:
        return fmt.Errorf("cannot scan %T into Metadata", src)
    }
}

// Get retrieves a value with type assertion
func (m Metadata) Get(key string) (any, bool) {
    v, ok := m[key]
    return v, ok
}

// GetString retrieves a string value
func (m Metadata) GetString(key string) string {
    if v, ok := m[key]; ok {
        if s, ok := v.(string); ok {
            return s
        }
    }
    return ""
}

// GetInt retrieves an integer value
func (m Metadata) GetInt(key string) int {
    if v, ok := m[key]; ok {
        switch n := v.(type) {
        case int:
            return n
        case float64:
            return int(n)
        }
    }
    return 0
}
```

---

## enums.go

```go
package types

import (
    "database/sql/driver"
    "fmt"
)

// ============ Status Enum ============

// Status represents entity lifecycle status
type Status string

const (
    StatusDraft     Status = "DRAFT"
    StatusActive    Status = "ACTIVE"
    StatusArchived  Status = "ARCHIVED"
    StatusDeleted   Status = "DELETED"
)

// AllStatuses returns all valid statuses
func AllStatuses() []Status {
    return []Status{StatusDraft, StatusActive, StatusArchived, StatusDeleted}
}

// Validate checks if the status is valid
func (s Status) Validate() error {
    switch s {
    case StatusDraft, StatusActive, StatusArchived, StatusDeleted:
        return nil
    default:
        return fmt.Errorf("invalid status: %s", s)
    }
}

// IsTerminal returns true for final states
func (s Status) IsTerminal() bool {
    return s == StatusDeleted
}

// Value implements driver.Valuer
func (s Status) Value() (driver.Value, error) {
    return string(s), nil
}

// Scan implements sql.Scanner
func (s *Status) Scan(src any) error {
    switch v := src.(type) {
    case string:
        *s = Status(v)
    case []byte:
        *s = Status(v)
    default:
        return fmt.Errorf("cannot scan %T into Status", src)
    }
    return s.Validate()
}

// ============ Priority Enum ============

// Priority represents task/item priority
type Priority string

const (
    PriorityLow      Priority = "LOW"
    PriorityMedium   Priority = "MEDIUM"
    PriorityHigh     Priority = "HIGH"
    PriorityCritical Priority = "CRITICAL"
)

// AllPriorities returns all valid priorities
func AllPriorities() []Priority {
    return []Priority{PriorityLow, PriorityMedium, PriorityHigh, PriorityCritical}
}

// Validate checks if the priority is valid
func (p Priority) Validate() error {
    switch p {
    case PriorityLow, PriorityMedium, PriorityHigh, PriorityCritical:
        return nil
    default:
        return fmt.Errorf("invalid priority: %s", p)
    }
}

// Weight returns numeric weight for sorting
func (p Priority) Weight() int {
    switch p {
    case PriorityCritical:
        return 4
    case PriorityHigh:
        return 3
    case PriorityMedium:
        return 2
    case PriorityLow:
        return 1
    default:
        return 0
    }
}

// Value implements driver.Valuer
func (p Priority) Value() (driver.Value, error) {
    return string(p), nil
}

// Scan implements sql.Scanner
func (p *Priority) Scan(src any) error {
    switch v := src.(type) {
    case string:
        *p = Priority(v)
    case []byte:
        *p = Priority(v)
    default:
        return fmt.Errorf("cannot scan %T into Priority", src)
    }
    return p.Validate()
}

// ============ Severity Enum ============

// Severity represents error/issue severity
type Severity string

const (
    SeverityInfo     Severity = "INFO"
    SeverityWarning  Severity = "WARNING"
    SeverityError    Severity = "ERROR"
    SeverityCritical Severity = "CRITICAL"
)

// AllSeverities returns all valid severities
func AllSeverities() []Severity {
    return []Severity{SeverityInfo, SeverityWarning, SeverityError, SeverityCritical}
}

// Validate checks if the severity is valid
func (s Severity) Validate() error {
    switch s {
    case SeverityInfo, SeverityWarning, SeverityError, SeverityCritical:
        return nil
    default:
        return fmt.Errorf("invalid severity: %s", s)
    }
}

// Value implements driver.Valuer
func (s Severity) Value() (driver.Value, error) {
    return string(s), nil
}

// Scan implements sql.Scanner
func (s *Severity) Scan(src any) error {
    switch v := src.(type) {
    case string:
        *s = Severity(v)
    case []byte:
        *s = Severity(v)
    default:
        return fmt.Errorf("cannot scan %T into Severity", src)
    }
    return s.Validate()
}

// ============ Block Type Enum (Nexus-Flow) ============

// BlockType represents Nexus-Flow block types
type BlockType string

const (
    BlockTypePrompt    BlockType = "PROMPT"
    BlockTypeSearch    BlockType = "SEARCH"
    BlockTypeCodeGen   BlockType = "CODE_GEN"
    BlockTypeValidate  BlockType = "VALIDATE"
    BlockTypeTransform BlockType = "TRANSFORM"
    BlockTypeCondition BlockType = "CONDITION"
    BlockTypeParallel  BlockType = "PARALLEL"
    BlockTypeLoop      BlockType = "LOOP"
    BlockTypeHTTP      BlockType = "HTTP"
    BlockTypeScript    BlockType = "SCRIPT"
)

// AllBlockTypes returns all valid block types
func AllBlockTypes() []BlockType {
    return []BlockType{
        BlockTypePrompt, BlockTypeSearch, BlockTypeCodeGen,
        BlockTypeValidate, BlockTypeTransform, BlockTypeCondition,
        BlockTypeParallel, BlockTypeLoop, BlockTypeHTTP, BlockTypeScript,
    }
}

// Validate checks if the block type is valid
func (b BlockType) Validate() error {
    for _, valid := range AllBlockTypes() {
        if b == valid {
            return nil
        }
    }
    return fmt.Errorf("invalid block type: %s", b)
}

// ============ Execution Status Enum ============

// ExecutionStatus represents Nexus-Flow execution status
type ExecutionStatus string

const (
    ExecutionPending   ExecutionStatus = "PENDING"
    ExecutionRunning   ExecutionStatus = "RUNNING"
    ExecutionPaused    ExecutionStatus = "PAUSED"
    ExecutionCompleted ExecutionStatus = "COMPLETED"
    ExecutionFailed    ExecutionStatus = "FAILED"
    ExecutionCancelled ExecutionStatus = "CANCELLED"
)

// AllExecutionStatuses returns all valid execution statuses
func AllExecutionStatuses() []ExecutionStatus {
    return []ExecutionStatus{
        ExecutionPending, ExecutionRunning, ExecutionPaused,
        ExecutionCompleted, ExecutionFailed, ExecutionCancelled,
    }
}

// IsTerminal returns true for final states
func (e ExecutionStatus) IsTerminal() bool {
    switch e {
    case ExecutionCompleted, ExecutionFailed, ExecutionCancelled:
        return true
    default:
        return false
    }
}

// IsSuccess returns true for successful completion
func (e ExecutionStatus) IsSuccess() bool {
    return e == ExecutionCompleted
}

// Validate checks if the execution status is valid
func (e ExecutionStatus) Validate() error {
    for _, valid := range AllExecutionStatuses() {
        if e == valid {
            return nil
        }
    }
    return fmt.Errorf("invalid execution status: %s", e)
}
```

---

## time.go

```go
package types

import (
    "database/sql/driver"
    "encoding/json"
    "time"
)

// Timestamp wraps time.Time with UTC enforcement
type Timestamp time.Time

// Now returns current UTC time as Timestamp
func Now() Timestamp {
    return Timestamp(time.Now().UTC())
}

// Time returns the underlying time.Time
func (t Timestamp) Time() time.Time {
    return time.Time(t)
}

// String returns RFC3339 formatted string
func (t Timestamp) String() string {
    return time.Time(t).Format(time.RFC3339)
}

// IsZero checks if timestamp is zero value
func (t Timestamp) IsZero() bool {
    return time.Time(t).IsZero()
}

// Before checks if t is before other
func (t Timestamp) Before(other Timestamp) bool {
    return time.Time(t).Before(time.Time(other))
}

// After checks if t is after other
func (t Timestamp) After(other Timestamp) bool {
    return time.Time(t).After(time.Time(other))
}

// Add returns t + duration
func (t Timestamp) Add(d time.Duration) Timestamp {
    return Timestamp(time.Time(t).Add(d))
}

// Value implements driver.Valuer
func (t Timestamp) Value() (driver.Value, error) {
    return time.Time(t).UTC().Format(time.RFC3339), nil
}

// Scan implements sql.Scanner
func (t *Timestamp) Scan(src any) error {
    switch v := src.(type) {
    case time.Time:
        *t = Timestamp(v.UTC())
    case string:
        parsed, err := time.Parse(time.RFC3339, v)
        if err != nil {
            return err
        }
        *t = Timestamp(parsed.UTC())
    case []byte:
        parsed, err := time.Parse(time.RFC3339, string(v))
        if err != nil {
            return err
        }
        *t = Timestamp(parsed.UTC())
    default:
        return fmt.Errorf("cannot scan %T into Timestamp", src)
    }
    return nil
}

// MarshalJSON implements json.Marshaler
func (t Timestamp) MarshalJSON() ([]byte, error) {
    return json.Marshal(time.Time(t).UTC().Format(time.RFC3339))
}

// UnmarshalJSON implements json.Unmarshaler
func (t *Timestamp) UnmarshalJSON(data []byte) error {
    var s string
    if err := json.Unmarshal(data, &s); err != nil {
        return err
    }
    parsed, err := time.Parse(time.RFC3339, s)
    if err != nil {
        return err
    }
    *t = Timestamp(parsed.UTC())
    return nil
}

// Duration wraps time.Duration with JSON support
type Duration time.Duration

// Value implements driver.Valuer (stores as nanoseconds)
func (d Duration) Value() (driver.Value, error) {
    return int64(d), nil
}

// Scan implements sql.Scanner
func (d *Duration) Scan(src any) error {
    switch v := src.(type) {
    case int64:
        *d = Duration(v)
    case float64:
        *d = Duration(int64(v))
    default:
        return fmt.Errorf("cannot scan %T into Duration", src)
    }
    return nil
}

// MarshalJSON implements json.Marshaler
func (d Duration) MarshalJSON() ([]byte, error) {
    return json.Marshal(time.Duration(d).String())
}

// UnmarshalJSON implements json.Unmarshaler
func (d *Duration) UnmarshalJSON(data []byte) error {
    var s string
    if err := json.Unmarshal(data, &s); err != nil {
        return err
    }
    parsed, err := time.ParseDuration(s)
    if err != nil {
        return err
    }
    *d = Duration(parsed)
    return nil
}
```

---

## Testing Requirements

```go
// types_test.go
package types_test

import (
    "encoding/json"
    "testing"
    
    "github.com/specbuilder/pkg/types"
)

func TestProjectID_ParseAndString(t *testing.T) {
    original := types.NewProjectID()
    parsed, err := types.ParseProjectID(original.String())
    
    if err != nil {
        t.Fatalf("failed to parse: %v", err)
    }
    
    if parsed.String() != original.String() {
        t.Errorf("mismatch: %s != %s", parsed, original)
    }
}

func TestProjectID_JSON(t *testing.T) {
    id := types.NewProjectID()
    
    data, err := json.Marshal(id)
    if err != nil {
        t.Fatalf("marshal failed: %v", err)
    }
    
    var parsed types.ProjectID
    if err := json.Unmarshal(data, &parsed); err != nil {
        t.Fatalf("unmarshal failed: %v", err)
    }
    
    if parsed.String() != id.String() {
        t.Errorf("mismatch after JSON roundtrip")
    }
}

func TestStatus_Validate(t *testing.T) {
    valid := []types.Status{
        types.StatusDraft,
        types.StatusActive,
        types.StatusArchived,
    }
    
    for _, s := range valid {
        if err := s.Validate(); err != nil {
            t.Errorf("%s should be valid", s)
        }
    }
    
    invalid := types.Status("INVALID")
    if err := invalid.Validate(); err == nil {
        t.Error("INVALID should fail validation")
    }
}

func TestPageResponse_Pagination(t *testing.T) {
    items := []string{"a", "b", "c"}
    req := types.PageRequest{Page: 2, PageSize: 10}
    
    resp := types.NewPageResponse(items, req, 25)
    
    if resp.TotalPages != 3 {
        t.Errorf("expected 3 pages, got %d", resp.TotalPages)
    }
    if !resp.HasNext {
        t.Error("should have next page")
    }
    if !resp.HasPrev {
        t.Error("should have previous page")
    }
}

func BenchmarkNewProjectID(b *testing.B) {
    for i := 0; i < b.N; i++ {
        _ = types.NewProjectID()
    }
}
```
