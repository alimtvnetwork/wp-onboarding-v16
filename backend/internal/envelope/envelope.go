// Package envelope provides a universal response envelope for all API responses.
// Both the Go backend and PHP WordPress plugin must emit responses conforming
// to this structure for consistency.
package envelope

import (
	"encoding/json"
	"math"
	"net/http"
	"time"
)

// Response is the universal API response envelope.
// Every endpoint — success or error, single or list — returns this structure.
type Response struct {
	Status     Status       `json:"status"`
	Attributes Attributes   `json:"attributes"`
	Results    interface{}  `json:"results"`
	Navigation *Navigation  `json:"navigation,omitempty"`
	Error      *ErrorDetail `json:"error"`
	Additional interface{}  `json:"additional,omitempty"`
}

// Status describes the outcome of the request.
type Status struct {
	Success   bool   `json:"success"`
	Code      int    `json:"code"`
	Message   string `json:"message"`
	Timestamp string `json:"timestamp"`
}

// Attributes describes the shape and size of the result set.
type Attributes struct {
	IsSingle     bool `json:"isSingle"`
	IsMultiple   bool `json:"isMultiple"`
	TotalRecords int  `json:"totalRecords,omitempty"`
	PerPage      int  `json:"perPage,omitempty"`
	TotalPages   int  `json:"totalPages,omitempty"`
	CurrentPage  int  `json:"currentPage,omitempty"`
}

// Navigation provides pagination links for list responses.
type Navigation struct {
	NextPage *int  `json:"nextPage"`
	PrevPage *int  `json:"prevPage"`
	Pages    []int `json:"pages"`
}

// ErrorDetail carries error information. Stack traces are config-controlled.
type ErrorDetail struct {
	Code             string       `json:"code"`
	Message          string       `json:"message"`
	StackTrace       string       `json:"stackTrace,omitempty"`
	StackTraceFrames []StackFrame `json:"stackTraceFrames,omitempty"`
}

// StackFrame represents a single frame in a stack trace.
type StackFrame struct {
	File     string `json:"file"`
	Line     int    `json:"line"`
	Function string `json:"function"`
	Class    string `json:"class,omitempty"`
}

// DebugConfig controls error verbosity in responses.
type DebugConfig struct {
	IncludeStackTrace    bool `json:"includeStackTrace"`
	IncludeInternalErrors bool `json:"includeInternalErrors"`
	MaxStackFrames       int  `json:"maxStackFrames"`
}

// DefaultDebugConfig returns a production-safe default (no stack traces).
func DefaultDebugConfig() DebugConfig {
	return DebugConfig{
		IncludeStackTrace:    false,
		IncludeInternalErrors: false,
		MaxStackFrames:       20,
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

// Success creates a single-item success response (slim envelope, no navigation).
func Success(data interface{}) Response {
	results := []interface{}{data}
	return Response{
		Status: Status{
			Success:   true,
			Code:      http.StatusOK,
			Message:   "OK",
			Timestamp: now(),
		},
		Attributes: Attributes{
			IsSingle:   true,
			IsMultiple: false,
		},
		Results: results,
		Error:   nil,
	}
}

// Created creates a single-item 201 response.
func Created(data interface{}) Response {
	results := []interface{}{data}
	return Response{
		Status: Status{
			Success:   true,
			Code:      http.StatusCreated,
			Message:   "Created",
			Timestamp: now(),
		},
		Attributes: Attributes{
			IsSingle:   true,
			IsMultiple: false,
		},
		Results: results,
		Error:   nil,
	}
}

// Deleted creates a standard deletion success response.
func Deleted() Response {
	return Response{
		Status: Status{
			Success:   true,
			Code:      http.StatusOK,
			Message:   "Deleted",
			Timestamp: now(),
		},
		Attributes: Attributes{
			IsSingle:   true,
			IsMultiple: false,
		},
		Results: []interface{}{map[string]interface{}{"deleted": true}},
		Error:   nil,
	}
}

// List creates a paginated list response with navigation.
func List(data interface{}, pg Pagination) Response {
	nav := pg.Navigation()
	return Response{
		Status: Status{
			Success:   true,
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
		Error:      nil,
	}
}

// ListUnpaginated creates a list response without pagination metadata.
// Use when the endpoint returns all items without paging.
func ListUnpaginated(data interface{}, count int) Response {
	return Response{
		Status: Status{
			Success:   true,
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
		Error:   nil,
	}
}

// Error creates an error response. Stack traces are included only if debug config allows.
func Error(statusCode int, code, message string) Response {
	return Response{
		Status: Status{
			Success:   false,
			Code:      statusCode,
			Message:   message,
			Timestamp: now(),
		},
		Attributes: Attributes{
			IsSingle:   false,
			IsMultiple: false,
		},
		Results: []interface{}{},
		Error: &ErrorDetail{
			Code:    code,
			Message: message,
		},
	}
}

// ErrorWithTrace creates an error response with stack trace (respects debug config).
func ErrorWithTrace(statusCode int, code, message, stackTrace string, frames []StackFrame) Response {
	resp := Error(statusCode, code, message)
	if globalDebug.IncludeStackTrace {
		resp.Error.StackTrace = stackTrace
		if globalDebug.MaxStackFrames > 0 && len(frames) > globalDebug.MaxStackFrames {
			frames = frames[:globalDebug.MaxStackFrames]
		}
		resp.Error.StackTraceFrames = frames
	}
	return resp
}

// WithAdditional attaches additional payload to a response.
func (r Response) WithAdditional(data interface{}) Response {
	r.Additional = data
	return r
}

// --- Pagination ---

// Pagination holds the parameters for paginated queries.
type Pagination struct {
	Page         int `json:"page"`
	PerPage      int `json:"perPage"`
	TotalRecords int `json:"totalRecords"`
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

// Navigation computes the navigation block (next, prev, 5-page window).
func (p Pagination) Navigation() Navigation {
	total := p.TotalPages()
	nav := Navigation{}

	// Next page
	if p.Page < total {
		next := p.Page + 1
		nav.NextPage = &next
	}

	// Previous page
	if p.Page > 1 {
		prev := p.Page - 1
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

	pages := make([]int, 0, windowSize)
	for i := start; i <= end; i++ {
		pages = append(pages, i)
	}
	nav.Pages = pages

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
