// Package envelope provides a universal response envelope for all API responses.
// Both the Go backend and PHP WordPress plugin must emit responses conforming
// to this structure. See spec/response-envelope/README.md for the full spec.
package envelope

import (
	"encoding/json"
	"fmt"
	"net/http"
	"time"
)

// Response is the universal API response envelope.
// Every endpoint — success or error, single or list — returns this structure.
// Optional sections use pointers with omitempty so they are absent from JSON when nil.
type Response struct {
	Status       Status
	Attributes   Attributes
	Results      any
	Navigation   *Navigation   `json:",omitempty"`
	Errors       *Errors       `json:",omitempty"`
	MethodsStack *MethodsStack `json:",omitempty"`
}

// Status describes the outcome of the request.
type Status struct {
	IsSuccess bool
	IsFailed  bool
	Code      int
	Message   string
	Timestamp string
}

// Attributes describes the shape and size of the result set.
type Attributes struct {
	RequestedAt        string `json:",omitempty"`
	RequestDelegatedAt string `json:",omitempty"`
	SessionId          string `json:",omitempty"`
	HasAnyErrors       bool
	IsSingle           bool
	IsMultiple         bool
	TotalRecords       int    `json:",omitempty"`
	PerPage            int    `json:",omitempty"`
	TotalPages         int    `json:",omitempty"`
	CurrentPage        int    `json:",omitempty"`
}

// Navigation provides pagination URL links for list responses.
type Navigation struct {
	NextPage    *string
	PrevPage    *string
	CloserLinks []string
}

// Errors carries error information. Top-level, conditionally included.
type Errors struct {
	BackendMessage             string
	DelegatedServiceErrorStack []string `json:",omitempty"`
	Backend                    []string `json:",omitempty"`
	Frontend                   []string `json:",omitempty"`
}

// MethodsStack carries debug call-chain traces. Top-level, conditionally included.
type MethodsStack struct {
	Backend  []MethodFrame
	Frontend []MethodFrame
}

// MethodFrame represents a single frame in the methods stack.
type MethodFrame struct {
	Method     string
	File       string
	LineNumber int
}

// StackFrame represents a single frame in a stack trace (used in Errors.Backend parsing).
type StackFrame struct {
	File     string
	Line     int
	Function string
	Class    string `json:",omitempty"`
}

// DebugConfig controls error and diagnostic verbosity in responses.
type DebugConfig struct {
	IncludeErrors       bool
	IncludeStackTrace   bool
	IncludeMethodsStack bool
	MaxStackFrames      int
}

// DefaultDebugConfig returns a production-safe default.
func DefaultDebugConfig() DebugConfig {
	return DebugConfig{
		IncludeErrors:       true,
		IncludeStackTrace:   true,
		IncludeMethodsStack: true,
		MaxStackFrames:      20,
	}
}

// --- Global debug config (set once at startup) ---

var globalDebug = DefaultDebugConfig()

// SetDebugConfig sets the global debug configuration. Call once at startup.
func SetDebugConfig(cfg DebugConfig) {
	globalDebug = cfg
}

// GetDebugConfig returns the current global debug configuration.
func GetDebugConfig() DebugConfig {
	return globalDebug
}

// --- Builder Functions ---

// Success creates a single-item success response (slim envelope).
// Generic: callers get compile-time type checking on the data parameter.
func Success[T any](data T) Response {
	return Response{
		Status: Status{
			IsSuccess: true,
			IsFailed:  false,
			Code:      http.StatusOK,
			Message:   "OK",
			Timestamp: now(),
		},
		Attributes: Attributes{
			IsSingle:   true,
			IsMultiple: false,
		},
		Results: []T{data},
	}
}

// Created creates a single-item 201 response.
// Generic: callers get compile-time type checking on the data parameter.
func Created[T any](data T) Response {
	return Response{
		Status: Status{
			IsSuccess: true,
			IsFailed:  false,
			Code:      http.StatusCreated,
			Message:   "Created",
			Timestamp: now(),
		},
		Attributes: Attributes{
			IsSingle:   true,
			IsMultiple: false,
		},
		Results: []T{data},
	}
}

// Deleted creates a standard deletion success response.
func Deleted() Response {
	return Response{
		Status: Status{
			IsSuccess: true,
			IsFailed:  false,
			Code:      http.StatusOK,
			Message:   "Deleted",
			Timestamp: now(),
		},
		Attributes: Attributes{
			IsSingle:   false,
			IsMultiple: false,
		},
		Results: []struct{}{},
	}
}

// List creates a paginated list response with navigation URL links.
// Generic: callers get compile-time type checking on the data parameter.
// requestPath is the base URL path (e.g., "/api/v1/plugins") used to generate navigation URLs.
func List[T any](data T, pg Pagination, requestPath string) Response {
	nav := pg.NavigationURLs(requestPath)
	resp := newListResponse(data, pg)
	resp.Navigation = &nav
	return resp
}

// newListResponse builds a list response with pagination attributes.
func newListResponse[T any](data T, pg Pagination) Response {
	return Response{
		Status:     successStatus(http.StatusOK, "OK"),
		Attributes: listAttributes(pg),
		Results:    data,
	}
}

// listAttributes builds attributes for a paginated list.
func listAttributes(pg Pagination) Attributes {
	return Attributes{
		IsSingle:     false,
		IsMultiple:   true,
		TotalRecords: pg.TotalRecords,
		PerPage:      pg.PerPage,
		TotalPages:   pg.TotalPages(),
		CurrentPage:  pg.Page,
	}
}

// ListUnpaginated creates a list response without pagination metadata.
// Generic: callers get compile-time type checking on the data parameter.
func ListUnpaginated[T any](data T, count int) Response {
	return Response{
		Status: successStatus(http.StatusOK, "OK"),
		Attributes: Attributes{
			IsSingle:     false,
			IsMultiple:   true,
			TotalRecords: count,
		},
		Results: data,
	}
}

// Error creates an error response. Populates the top-level Errors block
// if error reporting is enabled in debug config.
func Error(statusCode int, code, message string) Response {
	resp := Response{
		Status:     failureStatus(statusCode, message),
		Attributes: Attributes{HasAnyErrors: true},
		Results:    []struct{}{},
	}
	if globalDebug.IncludeErrors {
		resp.Errors = &Errors{
			BackendMessage: fmt.Sprintf("[%s] %s", code, message),
		}
	}
	return resp
}

// successStatus builds a success Status.
func successStatus(code int, message string) Status {
	return Status{
		IsSuccess: true,
		IsFailed:  false,
		Code:      code,
		Message:   message,
		Timestamp: now(),
	}
}

// failureStatus builds a failure Status.
func failureStatus(code int, message string) Status {
	return Status{
		IsSuccess: false,
		IsFailed:  true,
		Code:      code,
		Message:   message,
		Timestamp: now(),
	}
}

// --- Fluent Modifiers ---

// WithEndpoints sets the requested and delegated endpoint in attributes.
func (r Response) WithEndpoints(requested, delegated string) Response {
	r.Attributes.RequestedAt = requested
	r.Attributes.RequestDelegatedAt = delegated
	return r
}

// WithSessionId attaches a session ID to the response attributes for frontend diagnostics.
func (r Response) WithSessionId(sessionId string) Response {
	r.Attributes.SessionId = sessionId
	return r
}

// WithBackendTrace appends backend stack trace lines to the Errors block.
// Only populated if IncludeStackTrace is enabled.
func (r Response) WithBackendTrace(lines []string) Response {
	isTraceDisabled := !globalDebug.IncludeStackTrace
	isEmpty := len(lines) == 0
	if isTraceDisabled || isEmpty {
		return r
	}
	hasFrameLimit     := globalDebug.MaxStackFrames > 0
	isOverFrameLimit  := len(lines) > globalDebug.MaxStackFrames

	if hasFrameLimit && isOverFrameLimit {
		lines = lines[:globalDebug.MaxStackFrames]
	}
	r.ensureErrors()
	r.Errors.Backend = lines
	return r
}

// WithDelegatedErrorStack attaches delegated service error stack lines.
// Only populated if IncludeStackTrace is enabled.
func (r Response) WithDelegatedErrorStack(lines []string) Response {
	isTraceDisabled := !globalDebug.IncludeStackTrace
	isEmpty := len(lines) == 0
	if isTraceDisabled || isEmpty {
		return r
	}
	hasFrameLimit     := globalDebug.MaxStackFrames > 0
	isOverFrameLimit  := len(lines) > globalDebug.MaxStackFrames

	if hasFrameLimit && isOverFrameLimit {
		lines = lines[:globalDebug.MaxStackFrames]
	}
	r.ensureErrors()
	r.Errors.DelegatedServiceErrorStack = lines
	return r
}

// WithMethodsStack attaches the backend methods stack for diagnostics.
// Only populated if IncludeMethodsStack is enabled.
func (r Response) WithMethodsStack(frames []MethodFrame) Response {
	isStackDisabled := !globalDebug.IncludeMethodsStack
	isEmpty := len(frames) == 0
	if isStackDisabled || isEmpty {
		return r
	}
	r.MethodsStack = &MethodsStack{
		Backend:  frames,
		Frontend: []MethodFrame{},
	}
	return r
}

// ensureErrors initializes the Errors block if nil.
func (r *Response) ensureErrors() {
	if r.Errors == nil {
		r.Errors = &Errors{}
		r.Attributes.HasAnyErrors = true
	}
}

// --- HTTP Writer ---

// Write serializes and writes the response to the HTTP response writer.
func Write(w http.ResponseWriter, resp Response) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(resp.Status.Code)
	json.NewEncoder(w).Encode(resp)
}

// --- Helpers ---

func now() string {
	return time.Now().UTC().Format(time.RFC3339)
}
