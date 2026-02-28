package envelope

import (
	"fmt"
	"runtime"
	"strings"
)

// CaptureMethodFrames captures the Go call stack as MethodFrame structs.
// skip controls how many frames to skip (2 = skip this function + caller).
// Only includes application frames (wp-plugin-publish/).
func CaptureMethodFrames(skip int) []MethodFrame {
	maxFrames := resolveMaxFrames()
	pcs := captureCallers(skip+1, 64)
	isCallersEmpty := pcs == nil

	if isCallersEmpty {
		return nil
	}

	return collectMethodFrames(pcs, maxFrames)
}

// collectMethodFrames iterates over program counters and collects MethodFrame entries.
func collectMethodFrames(pcs []uintptr, maxFrames int) []MethodFrame {
	frames := runtime.CallersFrames(pcs)
	var result []MethodFrame

	for {
		frame, more := frames.Next()
		if isAppFrame(frame.Function) {
			result = append(result, buildMethodFrame(frame))
			isFrameLimitReached := len(result) >= maxFrames

			if isFrameLimitReached {
				break
			}
		}

		isLastFrame := !more

		if isLastFrame {
			break
		}
	}
	return result
}

// buildMethodFrame constructs a MethodFrame from a runtime.Frame.
func buildMethodFrame(frame runtime.Frame) MethodFrame {
	return MethodFrame{
		Method:     shortenFuncName(frame.Function),
		File:       shortenAppPath(frame.File),
		LineNumber: frame.Line,
	}
}

// CaptureBackendTrace captures Go stack trace as string lines for Errors.Backend.
// skip controls how many frames to skip (2 = skip this function + caller).
func CaptureBackendTrace(skip int) []string {
	maxFrames := resolveMaxFrames()
	pcs := captureCallers(skip+1, 64)
	isCallersEmpty := pcs == nil

	if isCallersEmpty {
		return nil
	}

	return collectTraceLines(pcs, maxFrames)
}

// collectTraceLines iterates over program counters and collects formatted trace strings.
func collectTraceLines(pcs []uintptr, maxFrames int) []string {
	frames := runtime.CallersFrames(pcs)
	var result []string

	for {
		frame, more := frames.Next()
		if isAppFrame(frame.Function) {
			line := fmt.Sprintf("%s:%d %s", shortenAppPath(frame.File), frame.Line, shortenFuncName(frame.Function))
			result = append(result, line)
			isFrameLimitReached := len(result) >= maxFrames

			if isFrameLimitReached {
				break
			}
		}

		isLastFrame := !more

		if isLastFrame {
			break
		}
	}
	return result
}

// ErrorWithStack creates an error response with Go stack traces and methods stack auto-captured.
func ErrorWithStack(statusCode int, code, message string) Response {
	resp := Error(statusCode, code, message)
	backendTrace := CaptureBackendTrace(3)
	methodFrames := CaptureMethodFrames(3)
	resp = resp.WithBackendTrace(backendTrace)
	resp = resp.WithMethodsStack(methodFrames)
	return resp
}

// --- Internal helpers ---

// resolveMaxFrames returns the configured max stack frames.
func resolveMaxFrames() int {
	maxFrames := globalDebug.MaxStackFrames
	isMaxFramesInvalid := maxFrames <= 0

	if isMaxFramesInvalid {
		maxFrames = 20
	}

	return maxFrames
}

// captureCallers captures program counters from the call stack.
func captureCallers(skip, maxPCs int) []uintptr {
	pcs := make([]uintptr, maxPCs)
	n := runtime.Callers(skip+1, pcs)
	isCallersEmpty := n == 0

	if isCallersEmpty {
		return nil
	}

	return pcs[:n]
}

const appModulePrefix = "wp-plugin-publish/"

// isAppFrame checks if a function belongs to our application module.
func isAppFrame(funcName string) bool {
	return strings.Contains(funcName, appModulePrefix)
}

// shortenAppPath trims the module prefix from a file path.
func shortenAppPath(file string) string {
	idx := strings.Index(file, appModulePrefix)
	hasAppPrefix := idx >= 0

	if hasAppPrefix {
		return file[idx+len(appModulePrefix):]
	}

	return file
}

// shortenFuncName trims the module path prefix from a function name.
func shortenFuncName(fn string) string {
	fnIdx := strings.LastIndex(fn, "/")
	hasSlash := fnIdx >= 0

	if hasSlash {
		return fn[fnIdx+1:]
	}

	return fn
}
