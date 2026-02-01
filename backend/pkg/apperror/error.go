// Package apperror provides structured application errors
package apperror

import (
	"fmt"
	"runtime"
	"strings"
)

// AppError represents a structured application error
type AppError struct {
	Code       string                 `json:"code"`
	Message    string                 `json:"message"`
	Details    string                 `json:"details,omitempty"`
	Context    map[string]interface{} `json:"context,omitempty"`
	File       string                 `json:"file,omitempty"`
	Line       int                    `json:"line,omitempty"`
	Function   string                 `json:"function,omitempty"`
	StackTrace string                 `json:"stackTrace,omitempty"`
	Cause      error                  `json:"-"`
}

// Error implements the error interface
func (e *AppError) Error() string {
	if e.Details != "" {
		return fmt.Sprintf("[%s] %s: %s", e.Code, e.Message, e.Details)
	}
	return fmt.Sprintf("[%s] %s", e.Code, e.Message)
}

// Unwrap returns the underlying error
func (e *AppError) Unwrap() error {
	return e.Cause
}

// New creates a new AppError with caller context
func New(code, message string) *AppError {
	err := &AppError{
		Code:    code,
		Message: message,
	}
	err.captureContext(2)
	return err
}

// Wrap wraps an existing error with additional context
func Wrap(cause error, code, message string) *AppError {
	err := &AppError{
		Code:    code,
		Message: message,
		Cause:   cause,
	}
	if cause != nil {
		err.Details = cause.Error()
	}
	err.captureContext(2)
	return err
}

// WithDetails adds details to the error
func (e *AppError) WithDetails(details string) *AppError {
	e.Details = details
	return e
}

// WithContext adds context key-value pairs
func (e *AppError) WithContext(key string, value interface{}) *AppError {
	if e.Context == nil {
		e.Context = make(map[string]interface{})
	}
	e.Context[key] = value
	return e
}

// WithStack captures a full stack trace
func (e *AppError) WithStack() *AppError {
	e.StackTrace = captureStackTrace(2)
	return e
}

// captureContext captures file, line, and function information
func (e *AppError) captureContext(skip int) {
	pc, file, line, ok := runtime.Caller(skip)
	if ok {
		// Extract just the filename
		parts := strings.Split(file, "/")
		e.File = parts[len(parts)-1]
		e.Line = line

		// Get function name
		fn := runtime.FuncForPC(pc)
		if fn != nil {
			name := fn.Name()
			parts := strings.Split(name, ".")
			e.Function = parts[len(parts)-1]
		}
	}
}

// captureStackTrace captures a full stack trace
func captureStackTrace(skip int) string {
	var builder strings.Builder
	pcs := make([]uintptr, 32)
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])

	for {
		frame, more := frames.Next()
		fmt.Fprintf(&builder, "%s\n\t%s:%d\n", frame.Function, frame.File, frame.Line)
		if !more {
			break
		}
	}

	return builder.String()
}

// Is checks if the error matches a specific code
func Is(err error, code string) bool {
	if appErr, ok := err.(*AppError); ok {
		return appErr.Code == code
	}
	return false
}

// ToClipboard formats the error for AI-friendly copy-paste
func (e *AppError) ToClipboard() string {
	var builder strings.Builder

	builder.WriteString("## Error Report\n\n")
	builder.WriteString(fmt.Sprintf("**Code:** %s\n", e.Code))
	builder.WriteString(fmt.Sprintf("**Message:** %s\n", e.Message))

	if e.Details != "" {
		builder.WriteString(fmt.Sprintf("**Details:** %s\n", e.Details))
	}

	if e.File != "" {
		builder.WriteString(fmt.Sprintf("**Location:** %s:%d (%s)\n", e.File, e.Line, e.Function))
	}

	if len(e.Context) > 0 {
		builder.WriteString("\n**Context:**\n```json\n")
		for k, v := range e.Context {
			builder.WriteString(fmt.Sprintf("  %s: %v\n", k, v))
		}
		builder.WriteString("```\n")
	}

	if e.StackTrace != "" {
		builder.WriteString("\n**Stack Trace:**\n```\n")
		builder.WriteString(e.StackTrace)
		builder.WriteString("```\n")
	}

	return builder.String()
}
