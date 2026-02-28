package apperror

import (
	"fmt"
	"strings"
)

// ToClipboard formats the error for AI-friendly copy-paste.
func (e *AppError) ToClipboard() string {
	var b strings.Builder

	b.WriteString("## Error Report\n\n")
	writeCodeAndMessage(&b, e)
	writeDetailsSection(&b, e)
	writeValuesSection(&b, e)
	writeLocationSection(&b, e)
	writeDiagnosticSection(&b, e)
	writeStackSection(&b, e)

	return b.String()
}

// writeCodeAndMessage writes the code and message header.
func writeCodeAndMessage(b *strings.Builder, e *AppError) {
	b.WriteString(fmt.Sprintf("**Code:** %s\n", e.Code.String()))
	b.WriteString(fmt.Sprintf("**Message:** %s\n", e.Message))
}

// writeDetailsSection writes the details line if present.
func writeDetailsSection(b *strings.Builder, e *AppError) {
	if e.Details == "" {
		return
	}

	b.WriteString(fmt.Sprintf("**Details:** %s\n", e.Details))
}

// writeValuesSection writes injected variable values if present.
func writeValuesSection(b *strings.Builder, e *AppError) {
	isValuesEmpty := !e.HasValues()

	if isValuesEmpty {
		return
	}

	b.WriteString("\n**Values:**\n```\n")
	for k, v := range e.Values {
		b.WriteString(fmt.Sprintf("  %s: %s\n", k, v))
	}

	b.WriteString("```\n")
}

// writeLocationSection writes the caller location from the stack.
func writeLocationSection(b *strings.Builder, e *AppError) {
	if e.Stack.IsEmpty() {
		return
	}

	top := e.Stack.Frames[0]
	b.WriteString(fmt.Sprintf("**Location:** %s:%d (%s)\n", top.File, top.Line, top.Function))
}

// writeDiagnosticSection writes diagnostic fields if present.
func writeDiagnosticSection(b *strings.Builder, e *AppError) {
	isDiagnosticsEmpty := !e.Diagnostic.HasFields()

	if isDiagnosticsEmpty {
		return
	}

	b.WriteString("\n**Diagnostic:**\n```\n")
	b.WriteString(formatDiagnostic(e.Diagnostic))
	b.WriteString("```\n")
}

// writeStackSection writes the full stack trace if present.
func writeStackSection(b *strings.Builder, e *AppError) {
	if e.Stack.IsEmpty() {
		return
	}

	b.WriteString("\n**Stack Trace:**\n```\n")
	b.WriteString(e.Stack.String())
	b.WriteString("```\n")
}

// formatDiagnostic formats all non-zero diagnostic fields.
func formatDiagnostic(d ErrorDiagnostic) string {
	var b strings.Builder

	writeStringField(&b, "path", d.Path)
	writeStringField(&b, "file", d.File)
	writeStringField(&b, "destPath", d.DestPath)
	writeStringField(&b, "backupDir", d.BackupDir)
	writeStringField(&b, "url", d.Url)
	writeStringField(&b, "slug", d.Slug)
	writeStringField(&b, "filePath", d.FilePath)
	writeStringField(&b, "pluginSlug", d.PluginSlug)
	writeStringField(&b, "plugin", d.Plugin)
	writeInt64Field(&b, "siteId", d.SiteId)
	writeInt64Field(&b, "pluginId", d.PluginId)
	writeInt64Field(&b, "snapshotId", d.SnapshotId)
	writeInt64Field(&b, "mappingId", d.MappingId)
	writeInt64Field(&b, "versionId", d.VersionId)
	writeStringField(&b, "sessionId", d.SessionId)
	writeStringField(&b, "runId", d.RunId)
	writeIntField(&b, "statusCode", d.StatusCode)
	writeStringField(&b, "method", d.Method)
	writeStringField(&b, "endpoint", d.Endpoint)
	writeStringField(&b, "username", d.Username)

	return b.String()
}

// writeStringField writes a diagnostic field if non-empty.
func writeStringField(b *strings.Builder, name, value string) {
	if value == "" {
		return
	}

	b.WriteString(fmt.Sprintf("  %s: %s\n", name, value))
}

// writeInt64Field writes a diagnostic field if non-zero.
func writeInt64Field(b *strings.Builder, name string, value int64) {
	if value == 0 {
		return
	}

	b.WriteString(fmt.Sprintf("  %s: %d\n", name, value))
}

// writeIntField writes a diagnostic field if non-zero.
func writeIntField(b *strings.Builder, name string, value int) {
	if value == 0 {
		return
	}

	b.WriteString(fmt.Sprintf("  %s: %d\n", name, value))
}
