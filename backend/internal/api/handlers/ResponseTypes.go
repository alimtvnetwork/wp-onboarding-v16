// Package handlers - Typed response structs replacing map[string]any literals
package handlers

import (
	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
)

// --- Common action responses ---

// ActionResponse represents a simple boolean action result (deleted, cleared, enabled, etc.)
type ActionResponse struct {
	IsDeleted  bool   `json:",omitempty"`
	IsCleared  bool   `json:",omitempty"`
	IsEnabled  bool   `json:",omitempty"`
	IsDisabled bool   `json:",omitempty"`
	IsAborted  bool   `json:",omitempty"`
	IsRestored bool   `json:",omitempty"`
	Id         string `json:",omitempty"`
	Plugin     string `json:",omitempty"`
	SiteId     int64  `json:",omitempty"`
	Message    string `json:",omitempty"`
	Count      int    `json:",omitempty"`
}

// --- Paginated list responses ---

// PaginatedSessions is the response shape for session list endpoints.
type PaginatedSessions struct {
	Sessions []*middleware.RequestSession
	Total    int
	Limit    int `json:",omitempty"`
	Offset   int `json:",omitempty"`
}

// PaginatedErrors is the response shape for error list endpoints.
type PaginatedErrors struct {
	Errors []models.ErrorHistory
	Total  int
	Limit  int `json:",omitempty"`
	Offset int `json:",omitempty"`
}

// PaginatedEntries is the response shape for publish history list endpoints.
type PaginatedEntries struct {
	Entries []models.PublishHistory
	Total   int
	Limit   int `json:",omitempty"`
	Offset  int `json:",omitempty"`
}

// --- Domain-specific responses ---

// HealthResponse is the response shape for the health endpoint.
type HealthResponse struct {
	Status    string
	Timestamp string
}

// ApiIndexResponse is the response shape for the API index endpoint.
type ApiIndexResponse struct {
	Name    string
	Version string
	Health  string
	WS      string
}

// FileContentResponse is the response for file content retrieval.
type FileContentResponse struct {
	Path    string
	Content string
	Plugin  string `json:",omitempty"`
}

// PluginExistsResponse is the response for remote plugin existence checks.
type PluginExistsResponse struct {
	IsExists   bool
	Status     string
	PluginFile string
	Plugin     string
}

// SessionLogsResponse is the response for session log retrieval.
type SessionLogsResponse struct {
	SessionId string
	Logs      string
}

// ScanResultResponse is the response for directory scan with detection.
type ScanResultResponse struct {
	Scan             *plugin.ScanResult
	IsDetectionCreated bool   `json:",omitempty"`
	DetectionError   string `json:",omitempty"`
}

// DirectoryScanResult is the response for a single directory in multi-scan.
type DirectoryScanResult struct {
	Path             string
	IsPlugin         bool
	Metadata         *plugin.ScanResult `json:",omitempty"`
	Error            string             `json:",omitempty"`
	IsDetectionCreated bool             `json:",omitempty"`
}

// MultiScanResponse is the response for multi-directory scanning.
type MultiScanResponse struct {
	Scanned  int
	Detected int
	Results  []DirectoryScanResult
}

// BulkBootstrapSiteResult is the result for a single site in bulk bootstrap.
type BulkBootstrapSiteResult struct {
	SiteId      int64
	SiteName    string
	IsSuccess   bool
	Message     string
	IsActivated bool   `json:",omitempty"`
	Error       string `json:",omitempty"`
}

// BulkBootstrapResponse wraps results for bulk uploader deployment.
type BulkBootstrapResponse struct {
	Results []BulkBootstrapSiteResult
}

// ErrorReportResponse wraps a bulk-exported error report.
type ErrorReportResponse struct {
	Report string
	Count  int
}

// LogFileResponse is the response for log file content retrieval.
type LogFileResponse struct {
	Content    string
	Path       string `json:",omitempty"`
	Filename   string `json:",omitempty"`
	IsExists   bool   `json:",omitempty"`
	LogType    string `json:",omitempty"`
	Size       int64  `json:",omitempty"`
	ModifiedAt string `json:",omitempty"`
}

// LogLinesResponse is the response for streamed log lines.
type LogLinesResponse struct {
	Lines      []string
	TotalLines int
	Path       string
	IsExists   bool
	LogType    string
	Size       int64
	ModifiedAt string
}

// SettingsResponse is the complete settings payload.
type SettingsResponse struct {
	Watcher    WatcherSettings
	Backup     BackupSettings
	Logging    LoggingSettings
	Appearance AppearanceSettings
	Server     ServerSettings
}

// WatcherSettings holds watcher configuration.
type WatcherSettings struct {
	IsPollingEnabled       bool
	IsScanAfterGitPull     bool
	DebounceMs             int
	DefaultExcludePatterns []string
}

// BackupSettings holds backup configuration.
type BackupSettings struct {
	IsAutoBackupBeforePublish bool
	RetentionDays             int
	MaxBackupsPerPlugin       int
	Location                  string
}

// LoggingSettings holds logging configuration.
type LoggingSettings struct {
	Level         string
	RetentionDays int
	IsDebugMode   bool
}

// AppearanceSettings holds UI appearance configuration.
type AppearanceSettings struct {
	Theme       string
	IsCompactMode bool
}

// ServerSettings holds server configuration.
type ServerSettings struct {
	Port               int
	WSReconnectDelayMs int
}

// SnapshotDeleteResponse is the response for snapshot deletion.
type SnapshotDeleteResponse struct {
	IsDeleted  bool
	SnapshotId int64
}
