package apperror

import (
	"fmt"
	"runtime"
	"strings"
)

// AppError represents a structured application error with stack trace.
type AppError struct {
	Code    ErrorCode
	Message string
	Details string
	Cause   error
	Stack   string
}

// Error implements the error interface.
func (e *AppError) Error() string {
	if e.Details != "" {
		return fmt.Sprintf("[%s] %s: %s", e.Code.String(), e.Message, e.Details)
	}

	return fmt.Sprintf("[%s] %s", e.Code.String(), e.Message)
}

// Unwrap returns the underlying error for errors.Is/As.
func (e *AppError) Unwrap() error {
	return e.Cause
}

// FullString returns the complete error representation including stack trace.
func (e *AppError) FullString() string {
	var b strings.Builder

	b.WriteString(e.Error())

	hasStack := e.Stack != ""

	if hasStack {
		b.WriteString("\nStack:\n")
		b.WriteString(e.Stack)
	}

	hasCause := e.Cause != nil

	if hasCause {
		b.WriteString(fmt.Sprintf("\nCaused by: %s", e.Cause.Error()))
	}

	return b.String()
}

// New creates a new AppError with stack trace.
func New(code ErrorCode, message string) *AppError {
	return &AppError{
		Code:  code,
		Message: message,
		Stack:   captureStack(2),
	}
}

// Wrap wraps an existing error with context and stack trace.
func Wrap(cause error, code ErrorCode, message string) *AppError {
	return &AppError{
		Code:    code,
		Message: message,
		Details: cause.Error(),
		Cause:   cause,
		Stack:   captureStack(2),
	}
}

// captureStack captures a simplified stack trace string.
func captureStack(skip int) string {
	pcs := make([]uintptr, 10)
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])

	var b strings.Builder

	for i := 0; ; i++ {
		frame, more := frames.Next()

		isRuntimeInternal := strings.HasPrefix(frame.Function, "runtime.") && frame.Function != "runtime.main"

		if isRuntimeInternal {
			if !more {
				break
			}

			continue
		}

		fmt.Fprintf(&b, "  #%d %s\n      %s:%d\n", i, frame.Function, frame.File, frame.Line)

		if !more {
			break
		}
	}

	return b.String()
}
