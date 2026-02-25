package wordpress

import (
	"fmt"
	"runtime"
	"strings"
)

// DefaultStackTraceDepth is used when no config value is provided
const DefaultStackTraceDepth = 20

// captureStackTrace captures the call stack for debugging.
// maxDepth controls max frames captured (0 = use DefaultStackTraceDepth).
func captureStackTrace(skip int) string {
	return captureStackTraceN(skip+1, DefaultStackTraceDepth)
}

// captureStackTraceN captures the call stack with a configurable depth.
func captureStackTraceN(skip int, maxDepth int) string {
	if maxDepth <= 0 {
		maxDepth = DefaultStackTraceDepth
	}
	var builder strings.Builder
	pcs := make([]uintptr, maxDepth+10) // extra buffer for runtime frames that get filtered
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])

	frameNum := 0
	for {
		frame, more := frames.Next()
		// Skip runtime internals
		if strings.Contains(frame.Function, "runtime.") {
			if !more {
				break
			}
			continue
		}
		builder.WriteString(fmt.Sprintf("  #%d %s\n      %s:%d\n", frameNum, frame.Function, frame.File, frame.Line))
		frameNum++
		if !more || frameNum >= maxDepth {
			break
		}
	}
	return builder.String()
}
