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

	pcs := make([]uintptr, maxDepth+10)
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])

	return formatStackFrames(frames, maxDepth)
}

// formatStackFrames formats runtime frames into a readable stack trace string.
func formatStackFrames(frames *runtime.Frames, maxDepth int) string {
	var builder strings.Builder
	frameNum := 0

	for {
		frame, more := frames.Next()

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
