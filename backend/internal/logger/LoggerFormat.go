// Package logger — log line formatting and output helpers.
package logger

import (
	"fmt"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

// callerContext holds extracted caller info for log formatting.
type callerContext struct {
	funcName string
	file     string
	line     int
}

// buildLogLine assembles the complete log output string.
func (l *Logger) buildLogLine(level Level, msg string, caller callerContext, keyvals []any) string {
	var builder strings.Builder

	isColorEnabled := !l.config.NoColor

	if isColorEnabled {
		builder.WriteString(levelColors[level])
	}

	l.writeHeader(&builder, level, msg, caller)
	writeKeyvals(&builder, level, keyvals)
	appendStackIfError(&builder, level)

	if isColorEnabled {
		builder.WriteString(colorReset)
	}

	builder.WriteString("\n")
	return builder.String()
}

// writeHeader writes the timestamp, package, message, level, and location.
func (l *Logger) writeHeader(b *strings.Builder, level Level, msg string, caller callerContext) {
	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	levelStr := levelNames[level]
	hasPrefix := l.prefix != ""

	if hasPrefix {
		b.WriteString(fmt.Sprintf("%s %s] [%s] %s", l.prefix, timestamp, caller.funcName, msg))
	} else {
		b.WriteString(fmt.Sprintf("[%s] [%s] %s", timestamp, caller.funcName, msg))
	}

	b.WriteString(fmt.Sprintf(" [%s] [%s:%d]", levelStr, caller.file, caller.line))
}

// writeKeyvals writes key-value pairs in multi-line or compact format.
func writeKeyvals(b *strings.Builder, level Level, keyvals []any) {
	isHighSeverity := level >= LevelWarn
	hasMultipleKVs := len(keyvals) >= 2

	if isHighSeverity && hasMultipleKVs {
		writeMultiLineKeyvals(b, keyvals)
	} else {
		writeCompactKeyvals(b, keyvals)
	}
}

// writeMultiLineKeyvals renders key-value pairs on separate indented lines.
func writeMultiLineKeyvals(b *strings.Builder, keyvals []any) {
	maxKeyLen := findMaxKeyLen(keyvals)

	for i := 0; i < len(keyvals); i += 2 {
		hasValue := i+1 < len(keyvals)

		if hasValue {
			keyStr := fmt.Sprintf("%v", keyvals[i])
			padding := strings.Repeat(" ", maxKeyLen-len(keyStr))
			b.WriteString(fmt.Sprintf("\n  %s%s = %v", keyStr, padding, keyvals[i+1]))
		}
	}
}

// findMaxKeyLen finds the longest key string length for alignment.
func findMaxKeyLen(keyvals []any) int {
	maxKeyLen := 0

	for i := 0; i < len(keyvals); i += 2 {
		hasValue := i+1 < len(keyvals)

		if hasValue {
			keyStr := fmt.Sprintf("%v", keyvals[i])
			isLonger := len(keyStr) > maxKeyLen

			if isLonger {
				maxKeyLen = len(keyStr)
			}
		}
	}

	return maxKeyLen
}

// writeCompactKeyvals renders key-value pairs on a single line.
func writeCompactKeyvals(b *strings.Builder, keyvals []any) {
	for i := 0; i < len(keyvals); i += 2 {
		hasValue := i+1 < len(keyvals)

		if hasValue {
			b.WriteString(fmt.Sprintf(" %v=%v", keyvals[i], keyvals[i+1]))
		}
	}
}

// appendStackIfError appends a full stack trace for ERROR and FATAL levels.
func appendStackIfError(b *strings.Builder, level Level) {
	isBelowError := level < LevelError

	if isBelowError {
		return
	}

	stackTrace := CaptureStackTrace(3)
	b.WriteString("\n--- Stack Trace ---\n")
	b.WriteString(stackTrace)
	b.WriteString("--- End Stack Trace ---")
}

// extractPackageName extracts the Go package name from a program counter.
func extractPackageName(pc uintptr) string {
	fn := runtime.FuncForPC(pc)
	isMissing := fn == nil

	if isMissing {
		return "unknown"
	}

	return extractLastPackagePart(fn.Name())
}

// extractLastPackagePart parses "module/path/pkg.Func" to return "pkg".
func extractLastPackagePart(fullName string) string {
	parts := strings.Split(fullName, "/")
	isEmpty := len(parts) == 0

	if isEmpty {
		return "unknown"
	}

	lastPart := parts[len(parts)-1]
	funcParts := strings.Split(lastPart, ".")
	isFuncPartsEmpty := len(funcParts) == 0

	if isFuncPartsEmpty {
		return "unknown"
	}

	return funcParts[0]
}

// shortenFilePath makes a file path relative from "internal/" or "pkg/".
func shortenFilePath(file string) string {
	idx := strings.Index(file, "internal/")
	isInternalFound := idx != -1

	if isInternalFound {
		return file[idx:]
	}

	pkgIdx := strings.Index(file, "pkg/")
	isPkgFound := pkgIdx != -1

	if isPkgFound {
		return file[pkgIdx:]
	}

	return filepath.Base(file)
}
