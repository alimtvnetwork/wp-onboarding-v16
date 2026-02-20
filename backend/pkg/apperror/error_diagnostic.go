package apperror

// ErrorDiagnostic holds typed diagnostic context for application errors.
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
	StatusCode int    `json:"statusCode,omitempty"`
	Method     string `json:"method,omitempty"`
	Endpoint   string `json:"endpoint,omitempty"`
	Username   string `json:"username,omitempty"`
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
