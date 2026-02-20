// Package apperror provides structured application errors with mandatory stack traces.
package apperror

import (
	"fmt"
	"runtime"
	"strings"
)

// StackFrame represents a single frame in a captured stack trace.
type StackFrame struct {
	Function string `json:"function"`
	File     string `json:"file"`
	Line     int    `json:"line"`
}

// String formats the frame as "function\n      file:line".
func (f StackFrame) String() string {
	return fmt.Sprintf("%s\n      %s:%d", f.Function, f.File, f.Line)
}

// StackTrace is an ordered list of stack frames captured at error creation.
type StackTrace []StackFrame

// CaptureStack captures a stack trace, skipping the given number of frames.
func CaptureStack(skip int) StackTrace {
	pcs := make([]uintptr, 64)
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])

	var stack StackTrace
	for {
		frame, more := frames.Next()
		if isRuntimeFrame(frame.Function) {
			if !more {
				break
			}
			continue
		}
		stack = append(stack, StackFrame{
			Function: frame.Function,
			File:     frame.File,
			Line:     frame.Line,
		})
		if !more {
			break
		}
	}

	return stack
}

// isRuntimeFrame returns true for Go runtime internals (excluding runtime.main).
func isRuntimeFrame(fn string) bool {
	isRuntime := strings.Contains(fn, "runtime.")
	isMain := strings.Contains(fn, "runtime.main")

	return isRuntime && !isMain
}

// String formats the full stack trace with numbered frames.
func (s StackTrace) String() string {
	var b strings.Builder
	for i, frame := range s {
		fmt.Fprintf(&b, "  #%d %s\n", i, frame.String())
	}

	return b.String()
}

// CallerLine returns the top frame as "file:line" for compact display.
func (s StackTrace) CallerLine() string {
	if len(s) == 0 {
		return "<unknown>"
	}

	return fmt.Sprintf("%s:%d", s[0].File, s[0].Line)
}

// IsEmpty returns true if no frames were captured.
func (s StackTrace) IsEmpty() bool {
	return len(s) == 0
}

// Depth returns the number of captured frames.
func (s StackTrace) Depth() int {
	return len(s)
}
