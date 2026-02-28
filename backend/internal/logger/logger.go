// Package logger provides structured logging with file:line:function context
package logger

import (
	"fmt"
	"io"
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

// Level represents log severity
type Level int

const (
	LevelDebug Level = iota
	LevelInfo
	LevelWarn
	LevelError
	LevelFatal
)

var levelNames = map[Level]string{
	LevelDebug: "DEBUG",
	LevelInfo:  "INFO",
	LevelWarn:  "WARN",
	LevelError: "ERROR",
	LevelFatal: "FATAL",
}

var levelColors = map[Level]string{
	LevelDebug: "\033[36m", // Cyan
	LevelInfo:  "\033[32m", // Green
	LevelWarn:  "\033[33m", // Yellow
	LevelError: "\033[31m", // Red
	LevelFatal: "\033[35m", // Magenta
}

const colorReset = "\033[0m"

// Config holds logger configuration
type Config struct {
	Level      Level
	Output     io.Writer
	TimeFormat string
	NoColor    bool
	AppName    string // Optional: prefix logs with app name
	AppVersion string // Optional: include version in prefix
}

// Logger is the main logging struct
type Logger struct {
	config Config
	prefix string // Computed prefix from AppName/AppVersion
}

// New creates a new logger instance
func New(cfg Config) *Logger {
	isOutputMissing := cfg.Output == nil

	if isOutputMissing {
		cfg.Output = os.Stdout
	}

	isTimeFormatMissing := cfg.TimeFormat == ""

	if isTimeFormatMissing {
		cfg.TimeFormat = time.RFC3339
	}

	// Build prefix with just version number (cleaner format)
	prefix := ""
	hasVersion := cfg.AppVersion != ""

	if hasVersion {
		prefix = "[v" + cfg.AppVersion
	}

	return &Logger{config: cfg, prefix: prefix}
}

// callerContext holds extracted caller info for log formatting.
type callerContext struct {
	funcName string
	file     string
	line     int
}

// log is the internal logging method.
func (l *Logger) log(level Level, msg string, keyvals ...any) {
	isBelowLevel := level < l.config.Level

	if isBelowLevel {
		return
	}

	caller := l.captureCaller()
	line := l.buildLogLine(level, msg, caller, keyvals)
	fmt.Fprint(l.config.Output, line)
}

// captureCaller extracts function name, file, and line from the call stack.
func (l *Logger) captureCaller() callerContext {
	pc, file, line, isValid := runtime.Caller(2)
	isInvalid := !isValid

	if isInvalid {
		return callerContext{funcName: "unknown", file: "unknown", line: 0}
	}

	funcName := extractPackageName(pc)
	file = shortenFilePath(file)
	return callerContext{funcName: funcName, file: file, line: line}
}

// extractPackageName extracts the Go package name from a program counter.
func extractPackageName(pc uintptr) string {
	fn := runtime.FuncForPC(pc)
	isMissing := fn == nil

	if isMissing {
		return "unknown"
	}

	fullName := fn.Name()
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

// Debug logs a debug message
func (l *Logger) Debug(msg string, keyvals ...any) {
	l.log(LevelDebug, msg, keyvals...)
}

// Info logs an info message
func (l *Logger) Info(msg string, keyvals ...any) {
	l.log(LevelInfo, msg, keyvals...)
}

// Warn logs a warning message
func (l *Logger) Warn(msg string, keyvals ...any) {
	l.log(LevelWarn, msg, keyvals...)
}

// Error logs an error message with full stack trace
func (l *Logger) Error(msg string, keyvals ...any) {
	l.log(LevelError, msg, keyvals...)
}

// Fatal logs a fatal message with full stack trace and exits
func (l *Logger) Fatal(msg string, keyvals ...any) {
	l.log(LevelFatal, msg, keyvals...)
	os.Exit(1)
}

// WithContext returns a child logger with additional context
func (l *Logger) WithContext(keyvals ...any) *Logger {
	// TODO: Implement context inheritance
	return l
}
