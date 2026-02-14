// Package apperror provides structured application errors
package apperror

import (
	"fmt"
	"runtime"
	"strings"
)

// ErrorDiagnostic holds typed diagnostic context for application errors.
// Replaces the former map[string]any ErrorContext alias (GE pattern).
type ErrorDiagnostic struct {
	Path       string `json:"path,omitempty"`
	File       string `json:"file,omitempty"`
	DestPath   string `json:"destPath,omitempty"`
	BackupDir  string `json:"backupDir,omitempty"`
	URL        string `json:"url,omitempty"`
	Slug       string `json:"slug,omitempty"`
	FilePath   string `json:"filePath,omitempty"`
	PluginSlug string `json:"pluginSlug,omitempty"`
	Plugin     string `json:"plugin,omitempty"`
	SiteID     int64  `json:"siteId,omitempty"`
	PluginID   int64  `json:"pluginId,omitempty"`
	SnapshotID int64  `json:"snapshotId,omitempty"`
	MappingID  int64  `json:"mappingId,omitempty"`
	VersionID  int64  `json:"versionId,omitempty"`
	SessionID  string `json:"sessionId,omitempty"`
	RunID      string `json:"runId,omitempty"`
}

// HasFields returns true if any diagnostic field is populated.
func (d ErrorDiagnostic) HasFields() bool {
	return d != ErrorDiagnostic{}
}

// AppError represents a structured application error
type AppError struct {
	Code       string          `json:"code"`
	Message    string          `json:"message"`
	Details    string          `json:"details,omitempty"`
	Diagnostic ErrorDiagnostic `json:"diagnostic,omitempty"`
	File       string          `json:"file,omitempty"`
	Line       int             `json:"line,omitempty"`
	Function   string          `json:"function,omitempty"`
	StackTrace string          `json:"stackTrace,omitempty"`
	Cause      error           `json:"-"`
}

// Error implements the error interface
func (e *AppError) Error() string {
	if e.Details != "" {
		return fmt.Sprintf("[%s] %s: %s", e.Code, e.Message, e.Details)
	}
	return fmt.Sprintf("[%s] %s", e.Code, e.Message)
}

// Unwrap returns the underlying error
func (e *AppError) Unwrap() error {
	return e.Cause
}

// New creates a new AppError with caller context and full stack trace
func New(code, message string) *AppError {
	err := &AppError{
		Code:    code,
		Message: message,
	}
	err.captureContext(2)
	err.StackTrace = captureStackTrace(2)
	return err
}

// Wrap wraps an existing error with additional context and full stack trace
func Wrap(cause error, code, message string) *AppError {
	err := &AppError{
		Code:    code,
		Message: message,
		Cause:   cause,
	}
	if cause != nil {
		err.Details = cause.Error()
	}
	err.captureContext(2)
	err.StackTrace = captureStackTrace(2)
	return err
}

// WithDetails adds details to the error
func (e *AppError) WithDetails(details string) *AppError {
	e.Details = details
	return e
}

// --- Typed diagnostic setters ---

// WithPath sets the path diagnostic field.
func (e *AppError) WithPath(p string) *AppError {
	e.Diagnostic.Path = p
	return e
}

// WithFile sets the file diagnostic field.
func (e *AppError) WithFile(f string) *AppError {
	e.Diagnostic.File = f
	return e
}

// WithFilePath sets the filePath diagnostic field.
func (e *AppError) WithFilePath(p string) *AppError {
	e.Diagnostic.FilePath = p
	return e
}

// WithDestPath sets the destPath diagnostic field.
func (e *AppError) WithDestPath(p string) *AppError {
	e.Diagnostic.DestPath = p
	return e
}

// WithBackupDir sets the backupDir diagnostic field.
func (e *AppError) WithBackupDir(d string) *AppError {
	e.Diagnostic.BackupDir = d
	return e
}

// WithURL sets the url diagnostic field.
func (e *AppError) WithURL(u string) *AppError {
	e.Diagnostic.URL = u
	return e
}

// WithSlug sets the slug diagnostic field.
func (e *AppError) WithSlug(s string) *AppError {
	e.Diagnostic.Slug = s
	return e
}

// WithPlugin sets the plugin diagnostic field.
func (e *AppError) WithPlugin(p string) *AppError {
	e.Diagnostic.Plugin = p
	return e
}

// WithPluginSlug sets the pluginSlug diagnostic field.
func (e *AppError) WithPluginSlug(s string) *AppError {
	e.Diagnostic.PluginSlug = s
	return e
}

// WithSiteID sets the siteId diagnostic field.
func (e *AppError) WithSiteID(id int64) *AppError {
	e.Diagnostic.SiteID = id
	return e
}

// WithPluginID sets the pluginId diagnostic field.
func (e *AppError) WithPluginID(id int64) *AppError {
	e.Diagnostic.PluginID = id
	return e
}

// WithSnapshotID sets the snapshotId diagnostic field.
func (e *AppError) WithSnapshotID(id int64) *AppError {
	e.Diagnostic.SnapshotID = id
	return e
}

// WithMappingID sets the mappingId diagnostic field.
func (e *AppError) WithMappingID(id int64) *AppError {
	e.Diagnostic.MappingID = id
	return e
}

// WithVersionID sets the versionId diagnostic field.
func (e *AppError) WithVersionID(id int64) *AppError {
	e.Diagnostic.VersionID = id
	return e
}

// WithSessionID sets the sessionId diagnostic field.
func (e *AppError) WithSessionID(id string) *AppError {
	e.Diagnostic.SessionID = id
	return e
}

// WithRunID sets the runId diagnostic field.
func (e *AppError) WithRunID(id string) *AppError {
	e.Diagnostic.RunID = id
	return e
}

// WithDiagnostic sets the full diagnostic struct.
func (e *AppError) WithDiagnostic(d ErrorDiagnostic) *AppError {
	e.Diagnostic = d
	return e
}

// WithStack captures a full stack trace
func (e *AppError) WithStack() *AppError {
	e.StackTrace = captureStackTrace(2)
	return e
}

// captureContext captures file, line, and function information
func (e *AppError) captureContext(skip int) {
	pc, file, line, ok := runtime.Caller(skip)
	if ok {
		parts := strings.Split(file, "/")
		e.File = parts[len(parts)-1]
		e.Line = line

		fn := runtime.FuncForPC(pc)
		if fn != nil {
			name := fn.Name()
			parts := strings.Split(name, ".")
			e.Function = parts[len(parts)-1]
		}
	}
}

// captureStackTrace captures a full stack trace
func captureStackTrace(skip int) string {
	var builder strings.Builder
	pcs := make([]uintptr, 64)
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])

	frameNum := 0
	for {
		frame, more := frames.Next()
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

// Is checks if the error matches a specific code
func Is(err error, code string) bool {
	if appErr, ok := err.(*AppError); ok {
		return appErr.Code == code
	}
	return false
}

// ToClipboard formats the error for AI-friendly copy-paste
func (e *AppError) ToClipboard() string {
	var builder strings.Builder

	builder.WriteString("## Error Report\n\n")
	builder.WriteString(fmt.Sprintf("**Code:** %s\n", e.Code))
	builder.WriteString(fmt.Sprintf("**Message:** %s\n", e.Message))

	if e.Details != "" {
		builder.WriteString(fmt.Sprintf("**Details:** %s\n", e.Details))
	}

	if e.File != "" {
		builder.WriteString(fmt.Sprintf("**Location:** %s:%d (%s)\n", e.File, e.Line, e.Function))
	}

	if e.Diagnostic.HasFields() {
		builder.WriteString("\n**Diagnostic:**\n```\n")
		d := e.Diagnostic
		if d.Path != "" {
			builder.WriteString(fmt.Sprintf("  path: %s\n", d.Path))
		}
		if d.File != "" {
			builder.WriteString(fmt.Sprintf("  file: %s\n", d.File))
		}
		if d.DestPath != "" {
			builder.WriteString(fmt.Sprintf("  destPath: %s\n", d.DestPath))
		}
		if d.BackupDir != "" {
			builder.WriteString(fmt.Sprintf("  backupDir: %s\n", d.BackupDir))
		}
		if d.URL != "" {
			builder.WriteString(fmt.Sprintf("  url: %s\n", d.URL))
		}
		if d.Slug != "" {
			builder.WriteString(fmt.Sprintf("  slug: %s\n", d.Slug))
		}
		if d.FilePath != "" {
			builder.WriteString(fmt.Sprintf("  filePath: %s\n", d.FilePath))
		}
		if d.PluginSlug != "" {
			builder.WriteString(fmt.Sprintf("  pluginSlug: %s\n", d.PluginSlug))
		}
		if d.Plugin != "" {
			builder.WriteString(fmt.Sprintf("  plugin: %s\n", d.Plugin))
		}
		if d.SiteID != 0 {
			builder.WriteString(fmt.Sprintf("  siteId: %d\n", d.SiteID))
		}
		if d.PluginID != 0 {
			builder.WriteString(fmt.Sprintf("  pluginId: %d\n", d.PluginID))
		}
		if d.SnapshotID != 0 {
			builder.WriteString(fmt.Sprintf("  snapshotId: %d\n", d.SnapshotID))
		}
		if d.MappingID != 0 {
			builder.WriteString(fmt.Sprintf("  mappingId: %d\n", d.MappingID))
		}
		if d.VersionID != 0 {
			builder.WriteString(fmt.Sprintf("  versionId: %d\n", d.VersionID))
		}
		if d.SessionID != "" {
			builder.WriteString(fmt.Sprintf("  sessionId: %s\n", d.SessionID))
		}
		if d.RunID != "" {
			builder.WriteString(fmt.Sprintf("  runId: %s\n", d.RunID))
		}
		builder.WriteString("```\n")
	}

	if e.StackTrace != "" {
		builder.WriteString("\n**Stack Trace:**\n```\n")
		builder.WriteString(e.StackTrace)
		builder.WriteString("```\n")
	}

	return builder.String()
}
