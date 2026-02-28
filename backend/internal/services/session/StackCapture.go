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
	isEmpty := n == 0

	if isEmpty {
		return nil
	}

	return collectAppFrames(pcs[:n])
}

// collectAppFrames iterates over program counters and collects application frames.
func collectAppFrames(pcs []uintptr) []StackFrame {
	frames := runtime.CallersFrames(pcs)
	var result []StackFrame

	for {
		frame, more := frames.Next()

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

// excludedPrefixes lists function name prefixes that identify non-application frames.
var excludedPrefixes = []string{
	"runtime.",
	"net/http.",
	"net.",
	"syscall.",
	"internal/",
	"reflect.",
	"sync.",
	"testing.",
}

// isApplicationFrame returns true if the function belongs to application code
// (not Go runtime, standard library, or third-party middleware).
func isApplicationFrame(funcName string) bool {
	if funcName == "" {
		return false
	}
	if hasExcludedPrefix(funcName) {
		return false
	}
	return isOwnModule(funcName)
}

// hasExcludedPrefix checks if the function name starts with any excluded prefix.
func hasExcludedPrefix(funcName string) bool {
	for _, prefix := range excludedPrefixes {
		if strings.HasPrefix(funcName, prefix) {
			return true
		}
	}
	return false
}

// isOwnModule checks if the function belongs to our module.
func isOwnModule(funcName string) bool {
	isPlainName := !strings.Contains(funcName, ".")

	if isPlainName {
		return false
	}

	return strings.Contains(funcName, "wp-plugin-publish/")
}
