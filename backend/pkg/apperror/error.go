// Package apperror provides structured application errors with mandatory stack traces.
package apperror

import (
	"fmt"
	"strings"
)

// AppError represents a structured application error with mandatory stack trace.
type AppError struct {
	Code       ErrorCode         `json:"code"`
	Message    string            `json:"message"`
	Details    string            `json:"details,omitempty"`
	Values     map[string]string `json:"values,omitempty"`
	Diagnostic ErrorDiagnostic   `json:"diagnostic,omitempty"`
	Stack      StackTrace        `json:"stack"`
	Cause      error             `json:"-"`
}

// Error implements the error interface.
func (e *AppError) Error() string {
	if e.Details != "" {
		return fmt.Sprintf("[%s] %s: %s", e.Code.String(), e.Message, e.Details)
	}

	return fmt.Sprintf("[%s] %s", e.Code.String(), e.Message)
}

// String returns the full error representation including stack trace.
func (e *AppError) String() string {
	return e.FullString()
}

// FullString returns code + message + details + values + diagnostics + stack + cause chain.
func (e *AppError) FullString() string {
	var b strings.Builder

	b.WriteString(fmt.Sprintf("[%s] %s", e.Code.String(), e.Message))
	appendDetails(&b, e)
	appendValues(&b, e)
	appendDiagnostics(&b, e)
	appendStack(&b, e)
	appendCauseChain(&b, e)

	return b.String()
}

// appendDetails writes the details line if present.
func appendDetails(b *strings.Builder, e *AppError) {
	if e.Details == "" {
		return
	}

	b.WriteString(fmt.Sprintf("\nDetails: %s", e.Details))
}

// appendValues writes the values section if present.
func appendValues(b *strings.Builder, e *AppError) {
	if !e.HasValues() {
		return
	}

	b.WriteString("\nValues:")
	for k, v := range e.Values {
		b.WriteString(fmt.Sprintf("\n  %s: %s", k, v))
	}
}

// appendDiagnostics writes diagnostic fields if present.
func appendDiagnostics(b *strings.Builder, e *AppError) {
	if !e.Diagnostic.HasFields() {
		return
	}

	b.WriteString("\nDiagnostic: ")
	b.WriteString(formatDiagnostic(e.Diagnostic))
}

// appendStack writes the stack trace if present.
func appendStack(b *strings.Builder, e *AppError) {
	if e.Stack.IsEmpty() {
		return
	}

	b.WriteString("\nStack:\n")
	b.WriteString(e.Stack.String())
}

// appendCauseChain writes the cause chain if present.
func appendCauseChain(b *strings.Builder, e *AppError) {
	if e.Cause == nil {
		return
	}

	b.WriteString(fmt.Sprintf("\nCaused by: %s", e.Cause.Error()))
}

// Unwrap returns the underlying error for errors.Is/As.
func (e *AppError) Unwrap() error {
	return e.Cause
}

// Is checks if the error matches a specific code.
func (e *AppError) Is(target error) bool {
	other, ok := target.(*AppError)
	if !ok {
		return false
	}

	return e.Code == other.Code
}

// HasCause returns true if a wrapped cause exists.
func (e *AppError) HasCause() bool {
	return e.Cause != nil
}

// HasValues returns true if the Values map is populated.
func (e *AppError) HasValues() bool {
	return len(e.Values) > 0
}

// HasDiagnostic returns true if any diagnostic field is set.
func (e *AppError) HasDiagnostic() bool {
	return e.Diagnostic.HasFields()
}

// --- Constructors ---

// New creates a new AppError with mandatory stack trace.
func New(code ErrorCode, message string) *AppError {
	return &AppError{
		Code:    code,
		Message: message,
		Stack:   CaptureStack(2),
	}
}

// NewWithSkip creates a new AppError with explicit additional skip for stack capture.
func NewWithSkip(code ErrorCode, message string, skip int) *AppError {
	return &AppError{
		Code:    code,
		Message: message,
		Stack:   CaptureStack(2 + skip),
	}
}

// Wrap wraps an existing error with context and mandatory stack trace.
// If cause is an *AppError, its stack is preserved in PreviousTrace.
func Wrap(cause error, code ErrorCode, message string) *AppError {
	return WrapWithSkip(cause, code, message, 0)
}

// WrapWithSkip wraps with explicit additional skip for stack capture.
func WrapWithSkip(cause error, code ErrorCode, message string, skip int) *AppError {
	stack := CaptureStack(3 + skip)
	stack = mergeIfAppError(stack, cause)

	err := &AppError{
		Code:    code,
		Message: message,
		Cause:   cause,
		Stack:   stack,
	}

	setCauseDetails(err, cause)

	return err
}

// mergeIfAppError preserves the original stack trace when re-wrapping an AppError.
func mergeIfAppError(stack StackTrace, cause error) StackTrace {
	if cause == nil {
		return stack
	}

	appErr, ok := cause.(*AppError)
	isAppErrWithStack := ok && !appErr.Stack.IsEmpty()
	if isAppErrWithStack {
		stack.PreviousTrace = appErr.Stack.String()
	}

	return stack
}

// setCauseDetails sets Details from cause if present.
func setCauseDetails(err *AppError, cause error) {
	if cause == nil {
		return
	}

	err.Details = cause.Error()
}

// WrapWithDetails wraps an error with explicit details override.
func WrapWithDetails(cause error, code ErrorCode, message, details string) *AppError {
	err := WrapWithSkip(cause, code, message, 1)
	err.Details = details

	return err
}

// WithDetails adds details to the error.
func (e *AppError) WithDetails(details string) *AppError {
	e.Details = details

	return e
}

// --- Flow control ---

// Panic logs the full error and panics with the formatted message.
// Use ONLY for unrecoverable initialization failures.
func (e *AppError) Panic(message string) {
	panic(fmt.Sprintf("%s\n%s", message, e.FullString()))
}

// Throw panics with the AppError itself (recoverable via recover).
func (e *AppError) Throw() {
	panic(e)
}
