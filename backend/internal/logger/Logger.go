// Package logger provides structured logging with file:line:function context
package logger

import (
	"fmt"
	"io"
	"os"
	"runtime"
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
