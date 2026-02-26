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
	if cfg.Output == nil {
		cfg.Output = os.Stdout
	}
	if cfg.TimeFormat == "" {
		cfg.TimeFormat = time.RFC3339
	}

	// Build prefix with just version number (cleaner format)
	prefix := ""
	if cfg.AppVersion != "" {
		prefix = "[v" + cfg.AppVersion
	}

	return &Logger{config: cfg, prefix: prefix}
}

// log is the internal logging method with new format:
// [vX.X.X YYYY-MM-DD HH:MM:SS] [package] Message [LEVEL] [file:line]
func (l *Logger) log(level Level, msg string, keyvals ...any) {
	if level < l.config.Level {
		return
	}

	// Get caller info
	pc, file, line, ok := runtime.Caller(2)
	funcName := "unknown"
	if ok {
		// Extract package name from function
		fn := runtime.FuncForPC(pc)
		if fn != nil {
			fullName := fn.Name()
			// Extract package name (e.g., "wp-plugin-publish/internal/services/publish" -> "publish")
			parts := strings.Split(fullName, "/")
			if len(parts) > 0 {
				lastPart := parts[len(parts)-1]
				// Split by "." to get package.function
				funcParts := strings.Split(lastPart, ".")
				if len(funcParts) > 0 {
					funcName = funcParts[0]
				}
			}
		}
		// Keep full relative file path for better debugging
		// Try to make it relative from "internal/" or "pkg/"
		idx := strings.Index(file, "internal/")
		if idx != -1 {
			file = file[idx:]
		} else if pkgIdx := strings.Index(file, "pkg/"); pkgIdx != -1 {
			file = file[pkgIdx:]
		} else {
			file = filepath.Base(file)
		}
	} else {
		file = "unknown"
		line = 0
	}

	// Build log message with new format
	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	levelStr := levelNames[level]

	var builder strings.Builder

	// Add color if enabled
	if !l.config.NoColor {
		builder.WriteString(levelColors[level])
	}

	// Header line: [vX.X.X YYYY-MM-DD HH:MM:SS] [package] Message [LEVEL] [file:line]
	if l.prefix != "" {
		builder.WriteString(fmt.Sprintf("%s %s] [%s] %s", l.prefix, timestamp, funcName, msg))
	} else {
		builder.WriteString(fmt.Sprintf("[%s] [%s] %s", timestamp, funcName, msg))
	}

	// Add level and location at the end in brackets
	builder.WriteString(fmt.Sprintf(" [%s] [%s:%d]", levelStr, file, line))

	// For ERROR/WARN: render key-value pairs on separate indented lines for readability
	// For INFO/DEBUG: keep compact single-line format
	if (level >= LevelWarn) && len(keyvals) >= 2 {
		// Multi-line structured output
		// Find max key length for alignment
		maxKeyLen := 0
		for i := 0; i < len(keyvals); i += 2 {
			if i+1 < len(keyvals) {
				keyStr := fmt.Sprintf("%v", keyvals[i])
				if len(keyStr) > maxKeyLen {
					maxKeyLen = len(keyStr)
				}
			}
		}
		for i := 0; i < len(keyvals); i += 2 {
			if i+1 < len(keyvals) {
				keyStr := fmt.Sprintf("%v", keyvals[i])
				padding := strings.Repeat(" ", maxKeyLen-len(keyStr))
				builder.WriteString(fmt.Sprintf("\n  %s%s = %v", keyStr, padding, keyvals[i+1]))
			}
		}
	} else {
		// Compact single-line for INFO/DEBUG
		for i := 0; i < len(keyvals); i += 2 {
			if i+1 < len(keyvals) {
				builder.WriteString(fmt.Sprintf(" %v=%v", keyvals[i], keyvals[i+1]))
			}
		}
	}

	// For ERROR and FATAL levels, append full stack trace
	if level >= LevelError {
		stackTrace := CaptureStackTrace(3)
		builder.WriteString("\n--- Stack Trace ---\n")
		builder.WriteString(stackTrace)
		builder.WriteString("--- End Stack Trace ---")
	}

	if !l.config.NoColor {
		builder.WriteString(colorReset)
	}
	builder.WriteString("\n")

	fmt.Fprint(l.config.Output, builder.String())
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

// CaptureStackTrace captures a full stack trace starting from skip frames up
func CaptureStackTrace(skip int) string {
	var builder strings.Builder
	pcs := make([]uintptr, 64) // Increased buffer for deeper stacks
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])

	frameNum := 0
	for {
		frame, more := frames.Next()
		// Skip runtime internals
		if strings.Contains(frame.Function, "runtime.") && !strings.Contains(frame.Function, "runtime.main") {
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
