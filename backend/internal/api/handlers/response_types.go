// Package handlers - Typed response structs replacing map[string]any literals
package handlers

// --- Common action responses ---

// ActionResponse represents a simple boolean action result (deleted, cleared, enabled, etc.)
type ActionResponse struct {
	Deleted  bool   `json:"deleted,omitempty"`
	Cleared  bool   `json:"cleared,omitempty"`
	Enabled  bool   `json:"enabled,omitempty"`
	Disabled bool   `json:"disabled,omitempty"`
	Aborted  bool   `json:"aborted,omitempty"`
	Restored bool   `json:"restored,omitempty"`
	ID       any    `json:"id,omitempty"`
	Plugin   string `json:"plugin,omitempty"`
	SiteID   int64  `json:"siteId,omitempty"`
	Message  string `json:"message,omitempty"`
	Count    int    `json:"count,omitempty"`
}

// --- Paginated list responses ---

// PaginatedSessions is the response shape for session list endpoints.
type PaginatedSessions struct {
	Sessions any `json:"sessions"`
	Total    int `json:"total"`
	Limit    int `json:"limit,omitempty"`
	Offset   int `json:"offset,omitempty"`
}

// PaginatedErrors is the response shape for error list endpoints.
type PaginatedErrors struct {
	Errors any `json:"errors"`
	Total  int `json:"total"`
	Limit  int `json:"limit,omitempty"`
	Offset int `json:"offset,omitempty"`
}

// PaginatedEntries is the response shape for publish history list endpoints.
type PaginatedEntries struct {
	Entries any `json:"entries"`
	Total   int `json:"total"`
	Limit   int `json:"limit,omitempty"`
	Offset  int `json:"offset,omitempty"`
}

// --- Domain-specific responses ---

// HealthResponse is the response shape for the health endpoint.
type HealthResponse struct {
	Status    string `json:"status"`
	Timestamp string `json:"timestamp"`
}

// APIIndexResponse is the response shape for the API index endpoint.
type APIIndexResponse struct {
	Name    string `json:"name"`
	Version string `json:"version"`
	Health  string `json:"health"`
	WS      string `json:"ws"`
}

// FileContentResponse is the response for file content retrieval.
type FileContentResponse struct {
	Path    string `json:"path"`
	Content string `json:"content"`
	Plugin  string `json:"plugin,omitempty"`
}

// PluginExistsResponse is the response for remote plugin existence checks.
type PluginExistsResponse struct {
	Exists     bool   `json:"exists"`
	Status     string `json:"status"`
	PluginFile string `json:"plugin_file"`
	Plugin     string `json:"plugin"`
}

// SessionLogsResponse is the response for session log retrieval.
type SessionLogsResponse struct {
	SessionID string `json:"sessionId"`
	Logs      string `json:"logs"`
}

// ScanResultResponse is the response for directory scan with detection.
type ScanResultResponse struct {
	Scan             any    `json:"scan"`
	DetectionCreated bool   `json:"detectionCreated,omitempty"`
	DetectionError   string `json:"detectionError,omitempty"`
}

// MultiScanResponse is the response for multi-directory scanning.
type MultiScanResponse struct {
	Scanned  int `json:"scanned"`
	Detected int `json:"detected"`
	Results  any `json:"results"`
}

// BulkBootstrapResponse wraps results for bulk uploader deployment.
type BulkBootstrapResponse struct {
	Results any `json:"results"`
}

// ErrorReportResponse wraps a bulk-exported error report.
type ErrorReportResponse struct {
	Report string `json:"report"`
	Count  int    `json:"count"`
}

// LogFileResponse is the response for log file content retrieval.
type LogFileResponse struct {
	Content    string `json:"content"`
	Path       string `json:"path,omitempty"`
	Filename   string `json:"filename,omitempty"`
	Exists     bool   `json:"exists,omitempty"`
	LogType    string `json:"logType,omitempty"`
	Size       int64  `json:"size,omitempty"`
	ModifiedAt string `json:"modifiedAt,omitempty"`
}

// LogLinesResponse is the response for streamed log lines.
type LogLinesResponse struct {
	Lines      []string `json:"lines"`
	TotalLines int      `json:"totalLines"`
	Path       string   `json:"path"`
	Exists     bool     `json:"exists"`
	LogType    string   `json:"logType"`
	Size       int64    `json:"size"`
	ModifiedAt string   `json:"modifiedAt"`
}

// SettingsResponse is the complete settings payload.
type SettingsResponse struct {
	Watcher    WatcherSettings    `json:"watcher"`
	Backup     BackupSettings     `json:"backup"`
	Logging    LoggingSettings    `json:"logging"`
	Appearance AppearanceSettings `json:"appearance"`
	Server     ServerSettings     `json:"server"`
}

// WatcherSettings holds watcher configuration.
type WatcherSettings struct {
	PollingEnabled         bool     `json:"pollingEnabled"`
	ScanAfterGitPull       bool     `json:"scanAfterGitPull"`
	DebounceMs             int      `json:"debounceMs"`
	DefaultExcludePatterns []string `json:"defaultExcludePatterns"`
}

// BackupSettings holds backup configuration.
type BackupSettings struct {
	AutoBackupBeforePublish bool   `json:"autoBackupBeforePublish"`
	RetentionDays           int    `json:"retentionDays"`
	MaxBackupsPerPlugin     int    `json:"maxBackupsPerPlugin"`
	Location                string `json:"location"`
}

// LoggingSettings holds logging configuration.
type LoggingSettings struct {
	Level         string `json:"level"`
	RetentionDays int    `json:"retentionDays"`
	DebugMode     bool   `json:"debugMode"`
}

// AppearanceSettings holds UI appearance configuration.
type AppearanceSettings struct {
	Theme       string `json:"theme"`
	CompactMode bool   `json:"compactMode"`
}

// ServerSettings holds server configuration.
type ServerSettings struct {
	Port               int `json:"port"`
	WSReconnectDelayMs int `json:"wsReconnectDelayMs"`
}

// SnapshotDeleteResponse is the response for snapshot deletion.
type SnapshotDeleteResponse struct {
	Deleted    bool  `json:"deleted"`
	SnapshotID int64 `json:"snapshotId"`
}
