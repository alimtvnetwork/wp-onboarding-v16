// Package apperror provides structured application errors with mandatory stack traces.
package apperror

import (
	"fmt"
	"runtime"
	"strings"
)

// DefaultMaxFrames is the maximum number of stack frames captured by default.
const DefaultMaxFrames = 18

// StackFrame represents a single frame in a captured stack trace.
type StackFrame struct {
	Function string
	File     string
	Line     int
}

// String formats the frame as "function\n      file:line".
func (f StackFrame) String() string {
	return fmt.Sprintf("%s\n      %s:%d", f.Function, f.File, f.Line)
}

// StackTrace holds captured frames and an optional previous trace from merging.
type StackTrace struct {
	Frames        []StackFrame
	PreviousTrace string `json:",omitempty"`
}

// CaptureStack captures a stack trace, skipping the given number of frames.
// Uses DefaultMaxFrames (18) as the maximum depth.
func CaptureStack(skip int) StackTrace {
	return CaptureStackN(skip+1, DefaultMaxFrames)
}

// CaptureStackN captures a stack trace with a custom max frame depth.
func CaptureStackN(skip int, maxFrames int) StackTrace {
	pcs := make([]uintptr, maxFrames)
	n := runtime.Callers(skip+1, pcs)
	rawFrames := runtime.CallersFrames(pcs[:n])

	frames := collectFrames(rawFrames, maxFrames)

	return StackTrace{Frames: frames}
}

// collectFrames iterates runtime frames and filters out runtime internals.
func collectFrames(rawFrames *runtime.Frames, maxFrames int) []StackFrame {
	var frames []StackFrame

	for {
		frame, more := rawFrames.Next()
		if isRuntimeFrame(frame.Function) {
			if !more {
				break
			}

			continue
		}

		frames = append(frames, StackFrame{
			Function: frame.Function,
			File:     frame.File,
			Line:     frame.Line,
		})

		isAtLimit := len(frames) >= maxFrames
		isExhausted := !more

		if isExhausted || isAtLimit {
			break
		}
	}

	return frames
}

// isRuntimeFrame returns true for Go runtime internals (excluding runtime.main).
func isRuntimeFrame(fn string) bool {
	isRuntime := strings.HasPrefix(fn, "runtime.")
	isMain := fn == "runtime.main"
	isAuxiliary := !isMain
	isRuntimeInternal :=
		isRuntime &&
		isAuxiliary

	return isRuntimeInternal
}

// String formats the full stack trace with numbered frames.
// Includes PreviousTrace if present.
func (s StackTrace) String() string {
	var b strings.Builder

	writeCurrentFrames(&b, s.Frames)
	writePreviousTrace(&b, s.PreviousTrace)

	return b.String()
}

// writeCurrentFrames writes numbered frames to the builder.
func writeCurrentFrames(b *strings.Builder, frames []StackFrame) {
	for i, frame := range frames {
		fmt.Fprintf(b, "  #%d %s\n", i, frame.String())
	}
}

// writePreviousTrace writes the previous trace section if present.
func writePreviousTrace(b *strings.Builder, previous string) {
	isPreviousEmpty := previous == ""

	if isPreviousEmpty {
		return
	}

	b.WriteString("\n  --- Previous Trace ---\n")
	b.WriteString(previous)
}

// CallerLine returns the top frame as "file:line" for compact display.
func (s StackTrace) CallerLine() string {
	if s.IsEmpty() {
		return "<unknown>"
	}

	return fmt.Sprintf("%s:%d", s.Frames[0].File, s.Frames[0].Line)
}

// FinalLine returns the bottom frame as "file:line" — the deepest origin point.
func (s StackTrace) FinalLine() string {
	if s.IsEmpty() {
		return "<unknown>"
	}

	last := s.Frames[len(s.Frames)-1]

	return fmt.Sprintf("%s:%d", last.File, last.Line)
}

// IsEmpty returns true if no frames were captured.
func (s StackTrace) IsEmpty() bool {
	return len(s.Frames) == 0
}

// Depth returns the number of captured frames.
func (s StackTrace) Depth() int {
	return len(s.Frames)
}

// HasPrevious returns true if a previous trace exists from merging.
func (s StackTrace) HasPrevious() bool {
	return s.PreviousTrace != ""
}
