// Package apperror — typed diagnostic context and formatting for structured errors.
package apperror

import (
	"fmt"
	"strings"
)

// ErrorDiagnostic holds typed diagnostic context for application errors.
type ErrorDiagnostic struct {
	Path       string `json:",omitempty"`
	File       string `json:",omitempty"`
	DestPath   string `json:",omitempty"`
	BackupDir  string `json:",omitempty"`
	Url        string `json:",omitempty"`
	Slug       string `json:",omitempty"`
	FilePath   string `json:",omitempty"`
	PluginSlug string `json:",omitempty"`
	Plugin     string `json:",omitempty"`
	SiteId     int64  `json:",omitempty"`
	PluginId   int64  `json:",omitempty"`
	SnapshotId int64  `json:",omitempty"`
	MappingId  int64  `json:",omitempty"`
	VersionId  int64  `json:",omitempty"`
	SessionId  string `json:",omitempty"`
	RunId      string `json:",omitempty"`
	StatusCode int    `json:",omitempty"`
	Method     string `json:",omitempty"`
	Endpoint   string `json:",omitempty"`
	Username   string `json:",omitempty"`
}

// HasFields returns true if any diagnostic field is populated.
func (d ErrorDiagnostic) HasFields() bool {
	return d != ErrorDiagnostic{}
}

// --- Typed diagnostic setters (fluent) ---

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

// WithUrl sets the url diagnostic field.
func (e *AppError) WithUrl(u string) *AppError {
	e.Diagnostic.Url = u
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

// WithSiteId sets the siteId diagnostic field.
func (e *AppError) WithSiteId(id int64) *AppError {
	e.Diagnostic.SiteId = id
	return e
}

// WithPluginId sets the pluginId diagnostic field.
func (e *AppError) WithPluginId(id int64) *AppError {
	e.Diagnostic.PluginId = id
	return e
}

// WithSnapshotId sets the snapshotId diagnostic field.
func (e *AppError) WithSnapshotId(id int64) *AppError {
	e.Diagnostic.SnapshotId = id
	return e
}

// WithMappingId sets the mappingId diagnostic field.
func (e *AppError) WithMappingId(id int64) *AppError {
	e.Diagnostic.MappingId = id
	return e
}

// WithVersionId sets the versionId diagnostic field.
func (e *AppError) WithVersionId(id int64) *AppError {
	e.Diagnostic.VersionId = id
	return e
}

// WithSessionId sets the sessionId diagnostic field.
func (e *AppError) WithSessionId(id string) *AppError {
	e.Diagnostic.SessionId = id
	return e
}

// WithRunId sets the runId diagnostic field.
func (e *AppError) WithRunId(id string) *AppError {
	e.Diagnostic.RunId = id
	return e
}

// WithStatusCode sets the statusCode diagnostic field.
func (e *AppError) WithStatusCode(code int) *AppError {
	e.Diagnostic.StatusCode = code
	return e
}

// WithMethod sets the method diagnostic field.
func (e *AppError) WithMethod(m string) *AppError {
	e.Diagnostic.Method = m
	return e
}

// WithEndpoint sets the endpoint diagnostic field.
func (e *AppError) WithEndpoint(ep string) *AppError {
	e.Diagnostic.Endpoint = ep
	return e
}

// WithUsername sets the username diagnostic field.
func (e *AppError) WithUsername(u string) *AppError {
	e.Diagnostic.Username = u
	return e
}

// WithDiagnostic sets the full diagnostic struct.
func (e *AppError) WithDiagnostic(d ErrorDiagnostic) *AppError {
	e.Diagnostic = d
	return e
}

// --- Diagnostic formatting ---

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
	isValueEmpty := value == ""

	if isValueEmpty {
		return
	}

	b.WriteString(fmt.Sprintf("  %s: %s\n", name, value))
}

// writeInt64Field writes a diagnostic field if non-zero.
func writeInt64Field(b *strings.Builder, name string, value int64) {
	isValueZero := value == 0

	if isValueZero {
		return
	}

	b.WriteString(fmt.Sprintf("  %s: %d\n", name, value))
}

// writeIntField writes a diagnostic field if non-zero.
func writeIntField(b *strings.Builder, name string, value int) {
	isValueZero := value == 0

	if isValueZero {
		return
	}

	b.WriteString(fmt.Sprintf("  %s: %d\n", name, value))
}
