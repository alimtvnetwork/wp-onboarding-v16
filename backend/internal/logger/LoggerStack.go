// Package logger — stack trace capture and process output logging
package logger

import (
	"fmt"
	"runtime"
	"strings"
)

// CaptureStackTrace captures a full stack trace starting from skip frames up
func CaptureStackTrace(skip int) string {
	pcs := make([]uintptr, 64)
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])

	return formatLogFrames(frames)
}

// formatLogFrames formats runtime frames into a readable stack trace string.
func formatLogFrames(frames *runtime.Frames) string {
	var builder strings.Builder
	frameNum := 0

	for {
		frame, more := frames.Next()
		isRuntime := strings.Contains(frame.Function, "runtime.")
		isMainFunc := strings.Contains(frame.Function, "runtime.main")
		isRuntimeInternal :=
			isRuntime &&
			!isMainFunc
		if isRuntimeInternal {
			if !more {
				break
			}
			continue
		}
		fmt.Fprintf(&builder, "  #%d %s\n      %s:%d\n", frameNum, frame.Function, frame.File, frame.Line)
		frameNum++
		if !more {
			break
		}
	}

	return builder.String()
}

// LogProcessOutput logs the output of an external process with proper formatting
func (l *Logger) LogProcessOutput(processName string, stdout, stderr string) {
	if stdout != "" {
		l.Info(fmt.Sprintf("[%s] stdout", processName), "output", stdout)
	}
	if stderr != "" {
		l.Warn(fmt.Sprintf("[%s] stderr", processName), "output", stderr)
	}
}

// ProcessErrorInput holds parameters for logging process execution errors.
type ProcessErrorInput struct {
	ProcessName string
	Command     string
	Err         error
	Stdout      string
	Stderr      string
}

// LogProcessError logs a process execution error with full context.
func (l *Logger) LogProcessError(input ProcessErrorInput) {
	l.Error(fmt.Sprintf("[%s] execution failed", input.ProcessName),
		"command", input.Command,
		"error", input.Err,
		"stdout", input.Stdout,
		"stderr", input.Stderr,
	)
}
