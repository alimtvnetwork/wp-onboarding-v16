// Package envelope provides a universal response envelope for all API responses.
// Both the Go backend and PHP WordPress plugin must emit responses conforming
// to this structure. See spec/response-envelope/README.md for the full spec.
package envelope

import (
	"encoding/json"
	"fmt"
	"math"
	"net/http"
	"runtime"
	"strings"
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
	return Response{
		Status: Status{
			IsSuccess: true,
			IsFailed:  false,
			Code:      http.StatusOK,
			Message:   "OK",
			Timestamp: now(),
		},
		Attributes: Attributes{
			IsSingle:     false,
			IsMultiple:   true,
			TotalRecords: pg.TotalRecords,
			PerPage:      pg.PerPage,
			TotalPages:   pg.TotalPages(),
			CurrentPage:  pg.Page,
		},
		Results:    data,
		Navigation: &nav,
	}
}

// ListUnpaginated creates a list response without pagination metadata.
// Generic: callers get compile-time type checking on the data parameter.
func ListUnpaginated[T any](data T, count int) Response {
	return Response{
		Status: Status{
			IsSuccess: true,
			IsFailed:  false,
			Code:      http.StatusOK,
			Message:   "OK",
			Timestamp: now(),
		},
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
		Status: Status{
			IsSuccess: false,
			IsFailed:  true,
			Code:      statusCode,
			Message:   message,
			Timestamp: now(),
		},
		Attributes: Attributes{
			HasAnyErrors: true,
		},
		Results: []struct{}{},
	}
	if globalDebug.IncludeErrors {
		resp.Errors = &Errors{
			BackendMessage: fmt.Sprintf("[%s] %s", code, message),
		}
	}
	return resp
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
	if !globalDebug.IncludeStackTrace || len(lines) == 0 {
		return r
	}
	if globalDebug.MaxStackFrames > 0 && len(lines) > globalDebug.MaxStackFrames {
		lines = lines[:globalDebug.MaxStackFrames]
	}
	r.ensureErrors()
	r.Errors.Backend = lines
	return r
}

// WithDelegatedErrorStack attaches delegated service error stack lines.
// Only populated if IncludeStackTrace is enabled.
func (r Response) WithDelegatedErrorStack(lines []string) Response {
	if !globalDebug.IncludeStackTrace || len(lines) == 0 {
		return r
	}
	if globalDebug.MaxStackFrames > 0 && len(lines) > globalDebug.MaxStackFrames {
		lines = lines[:globalDebug.MaxStackFrames]
	}
	r.ensureErrors()
	r.Errors.DelegatedServiceErrorStack = lines
	return r
}

// WithMethodsStack attaches the backend methods stack for diagnostics.
// Only populated if IncludeMethodsStack is enabled.
func (r Response) WithMethodsStack(frames []MethodFrame) Response {
	if !globalDebug.IncludeMethodsStack || len(frames) == 0 {
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

// --- Pagination ---

// Pagination holds the parameters for paginated queries.
type Pagination struct {
	Page         int
	PerPage      int
	TotalRecords int
}

// DefaultPagination returns the default pagination (page 1, 20 per page).
func DefaultPagination() Pagination {
	return Pagination{Page: 1, PerPage: 20}
}

// NewPagination creates a Pagination with computed totals.
func NewPagination(totalRecords, page, perPage int) Pagination {
	if page < 1 {
		page = 1
	}
	if perPage < 1 {
		perPage = 20
	}
	return Pagination{
		Page:         page,
		PerPage:      perPage,
		TotalRecords: totalRecords,
	}
}

// TotalPages computes the total number of pages.
func (p Pagination) TotalPages() int {
	if p.PerPage <= 0 {
		return 0
	}
	return int(math.Ceil(float64(p.TotalRecords) / float64(p.PerPage)))
}

// Offset returns the SQL OFFSET for the current page.
func (p Pagination) Offset() int {
	return (p.Page - 1) * p.PerPage
}

// NavigationURLs computes the navigation block with URL string links.
func (p Pagination) NavigationURLs(basePath string) Navigation {
	total := p.TotalPages()
	nav := Navigation{}

	// Next page
	if p.Page < total {
		next := fmt.Sprintf("%s?page=%d&perPage=%d", basePath, p.Page+1, p.PerPage)
		nav.NextPage = &next
	}

	// Previous page
	if p.Page > 1 {
		prev := fmt.Sprintf("%s?page=%d&perPage=%d", basePath, p.Page-1, p.PerPage)
		nav.PrevPage = &prev
	}

	// 5-page sliding window centered on current page
	windowSize := 5
	start := p.Page - windowSize/2
	if start < 1 {
		start = 1
	}
	end := start + windowSize - 1
	if end > total {
		end = total
		start = end - windowSize + 1
		if start < 1 {
			start = 1
		}
	}

	links := make([]string, 0, windowSize)
	for i := start; i <= end; i++ {
		links = append(links, fmt.Sprintf("%s?page=%d&perPage=%d", basePath, i, p.PerPage))
	}
	nav.CloserLinks = links

	return nav
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

// CaptureMethodFrames captures the Go call stack as MethodFrame structs.
// skip controls how many frames to skip (2 = skip this function + caller).
// Only includes application frames (wp-plugin-publish/).
func CaptureMethodFrames(skip int) []MethodFrame {
	maxFrames := globalDebug.MaxStackFrames
	if maxFrames <= 0 {
		maxFrames = 20
	}
	pcs := make([]uintptr, 64)
	n := runtime.Callers(skip+1, pcs)
	if n == 0 {
		return nil
	}
	pcs = pcs[:n]
	frames := runtime.CallersFrames(pcs)
	var result []MethodFrame
	for {
		frame, more := frames.Next()
		// Only include application-specific frames
		if strings.Contains(frame.Function, "wp-plugin-publish/") {
			// Extract short file name
			file := frame.File
			idx := strings.Index(file, "wp-plugin-publish/")
			if idx >= 0 {
				file = file[idx+len("wp-plugin-publish/"):]
			}
			// Extract short function name
			fn := frame.Function
			fnIdx := strings.LastIndex(fn, "/")
			if fnIdx >= 0 {
				fn = fn[fnIdx+1:]
			}
			result = append(result, MethodFrame{
				Method:     fn,
				File:       file,
				LineNumber: frame.Line,
			})
			if len(result) >= maxFrames {
				break
			}
		}
		if !more {
			break
		}
	}
	return result
}

// CaptureBackendTrace captures Go stack trace as string lines for Errors.Backend.
// skip controls how many frames to skip (2 = skip this function + caller).
func CaptureBackendTrace(skip int) []string {
	maxFrames := globalDebug.MaxStackFrames
	if maxFrames <= 0 {
		maxFrames = 20
	}
	pcs := make([]uintptr, 64)
	n := runtime.Callers(skip+1, pcs)
	if n == 0 {
		return nil
	}
	pcs = pcs[:n]
	frames := runtime.CallersFrames(pcs)
	var result []string
	for {
		frame, more := frames.Next()
		if strings.Contains(frame.Function, "wp-plugin-publish/") {
			file := frame.File
			idx := strings.Index(file, "wp-plugin-publish/")
			if idx >= 0 {
				file = file[idx+len("wp-plugin-publish/"):]
			}
			fn := frame.Function
			fnIdx := strings.LastIndex(fn, "/")
			if fnIdx >= 0 {
				fn = fn[fnIdx+1:]
			}
			result = append(result, fmt.Sprintf("%s:%d %s", file, frame.Line, fn))
			if len(result) >= maxFrames {
				break
			}
		}
		if !more {
			break
		}
	}
	return result
}

// ErrorWithStack creates an error response with Go stack traces and methods stack auto-captured.
func ErrorWithStack(statusCode int, code, message string) Response {
	resp := Error(statusCode, code, message)
	backendTrace := CaptureBackendTrace(3)
	methodFrames := CaptureMethodFrames(3)
	resp = resp.WithBackendTrace(backendTrace)
	resp = resp.WithMethodsStack(methodFrames)
	return resp
}
