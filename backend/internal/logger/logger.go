// Package logger provides structured logging with file:line:function context
package logger

import (
	"fmt"
	"io"
	"os"
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

// log is the internal logging method
func (l *Logger) log(level Level, msg string, keyvals ...interface{}) {
	if level < l.config.Level {
		return
	}

	// Get caller info
	_, file, line, ok := runtime.Caller(2)
	if ok {
		// Extract just the filename
		parts := strings.Split(file, "/")
		file = parts[len(parts)-1]
	} else {
		file = "unknown"
		line = 0
	}

	// Build log message
	timestamp := time.Now().Format(l.config.TimeFormat)
	levelStr := levelNames[level]

	var builder strings.Builder
	
	// Add color if enabled
	if !l.config.NoColor {
		builder.WriteString(levelColors[level])
	}
	
	// Format: [vX.X.X - TIME] message key=value... (LEVEL file:line)
	if l.prefix != "" {
		builder.WriteString(fmt.Sprintf("%s - %s] %s", l.prefix, timestamp, msg))
	} else {
		builder.WriteString(fmt.Sprintf("[%s] %s", timestamp, msg))
	}

	// Add key-value pairs
	for i := 0; i < len(keyvals); i += 2 {
		if i+1 < len(keyvals) {
			builder.WriteString(fmt.Sprintf(" %v=%v", keyvals[i], keyvals[i+1]))
		}
	}

	// Add level and location at the end
	builder.WriteString(fmt.Sprintf(" (%s %s:%d)", levelStr, file, line))

	if !l.config.NoColor {
		builder.WriteString(colorReset)
	}
	builder.WriteString("\n")

	fmt.Fprint(l.config.Output, builder.String())
}

// Debug logs a debug message
func (l *Logger) Debug(msg string, keyvals ...interface{}) {
	l.log(LevelDebug, msg, keyvals...)
}

// Info logs an info message
func (l *Logger) Info(msg string, keyvals ...interface{}) {
	l.log(LevelInfo, msg, keyvals...)
}

// Warn logs a warning message
func (l *Logger) Warn(msg string, keyvals ...interface{}) {
	l.log(LevelWarn, msg, keyvals...)
}

// Error logs an error message
func (l *Logger) Error(msg string, keyvals ...interface{}) {
	l.log(LevelError, msg, keyvals...)
}

// Fatal logs a fatal message and exits
func (l *Logger) Fatal(msg string, keyvals ...interface{}) {
	l.log(LevelFatal, msg, keyvals...)
	os.Exit(1)
}

// WithContext returns a child logger with additional context
func (l *Logger) WithContext(keyvals ...interface{}) *Logger {
	// TODO: Implement context inheritance
	return l
}
