// Package session provides session-based logging for operations
package session

import (
	"runtime"
	"strings"
)

// CaptureGoStack captures the current Go runtime stack trace, filtered to
// application frames only (excluding standard library and runtime internals).
// The skip parameter controls how many frames to skip from the top of the stack
// (use 1 to skip CaptureGoStack itself, 2 to also skip the caller, etc.).
func CaptureGoStack(skip int) []StackFrame {
	const maxFrames = 32
	pcs := make([]uintptr, maxFrames)
	// skip+1 to also skip runtime.Callers itself
	n := runtime.Callers(skip+1, pcs)
	if n == 0 {
		return nil
	}
	pcs = pcs[:n]

	frames := runtime.CallersFrames(pcs)
	var result []StackFrame

	for {
		frame, more := frames.Next()

		// Filter out runtime internals and standard library
		if isApplicationFrame(frame.Function) {
			result = append(result, StackFrame{
				Function: frame.Function,
				File:     frame.File,
				Line:     frame.Line,
			})
		}

		if !more {
			break
		}
	}

	return result
}

// isApplicationFrame returns true if the function belongs to application code
// (not Go runtime, standard library, or third-party middleware).
func isApplicationFrame(funcName string) bool {
	if funcName == "" {
		return false
	}

	// Exclude Go runtime and standard library
	excludePrefixes := []string{
		"runtime.",
		"net/http.",
		"net.",
		"syscall.",
		"internal/",
		"reflect.",
		"sync.",
		"testing.",
	}

	for _, prefix := range excludePrefixes {
		if strings.HasPrefix(funcName, prefix) {
			return false
		}
	}

	// Include anything from our module
	if strings.Contains(funcName, "wp-plugin-publish/") {
		return true
	}

	// Exclude everything else (third-party packages not in our module)
	// But include if it doesn't look like a standard library path
	if !strings.Contains(funcName, ".") {
		return false
	}

	return strings.Contains(funcName, "wp-plugin-publish/")
}
