// Package ws — typed broadcast event data structs.
// Every Hub.Broadcast / ws.Broadcast[T] call MUST use one of these structs
// instead of map[string]any literals, per the Generic Enforce Pattern (GE-1).
package ws

import "encoding/json"

import "time"

// --- Auto-publish events ---

// AutoPublishTriggeredData is broadcast when file-watcher auto-publish begins.
type AutoPublishTriggeredData struct {
	PluginID   int64  `json:"pluginId"`
	PluginName string `json:"pluginName"`
	Changes    int    `json:"changes"`
	Sites      int    `json:"sites"`
}

// AutoPublishFailedData is broadcast when auto-publish fails for a site.
type AutoPublishFailedData struct {
	PluginID int64  `json:"pluginId"`
	SiteID   int64  `json:"siteId"`
	SiteName string `json:"siteName"`
	Error    string `json:"error"`
}

// AutoPublishCompleteData is broadcast when auto-publish succeeds for a site.
type AutoPublishCompleteData struct {
	PluginID     int64  `json:"pluginId"`
	SiteID       int64  `json:"siteId"`
	SiteName     string `json:"siteName"`
	FilesUpdated int    `json:"filesUpdated"`
}

// FileChangeSummary holds aggregated counts for a batch of file changes.
type FileChangeSummary struct {
	Created  int `json:"created"`
	Modified int `json:"modified"`
	Deleted  int `json:"deleted"`
}

// FileChangeItem represents a single detected file change (mirrors watcher.FileChange for ws layer).
type FileChangeItem struct {
	Path       string    `json:"path"`
	ChangeType string    `json:"type"`
	Hash       string    `json:"hash,omitempty"`
	Size       int64     `json:"size,omitempty"`
	ModTime    time.Time `json:"modTime,omitempty"`
}

// FileChangeBatchData is broadcast when the watcher detects file changes.
type FileChangeBatchData struct {
	PluginID    int64              `json:"pluginId"`
	TriggerType string            `json:"triggerType"`
	Changes     []FileChangeItem  `json:"changes"`
	Summary     FileChangeSummary `json:"summary"`
}

// --- Git events ---

// GitPullStartedData is broadcast when git pull begins.
type GitPullStartedData struct {
	PluginID   int64  `json:"pluginId"`
	PluginName string `json:"pluginName"`
}

// GitPullFailedData is broadcast when git pull fails.
type GitPullFailedData struct {
	PluginID int64  `json:"pluginId"`
	Error    string `json:"error"`
}

// GitPullCompleteData is broadcast when git pull completes successfully.
type GitPullCompleteData struct {
	PluginID     int64  `json:"pluginId"`
	IsSuccess    bool   `json:"success"`
	FilesChanged int    `json:"filesChanged"`
	CommitHash   string `json:"commitHash"`
}

// GitPullAllCompleteData is broadcast when batch git pull completes.
type GitPullAllCompleteData struct {
	Succeeded int   `json:"succeeded"`
	Failed    int   `json:"failed"`
	Duration  int64 `json:"duration"`
}

// GitCommitCompleteData is broadcast when git commit completes.
type GitCommitCompleteData struct {
	PluginID   int64  `json:"pluginId"`
	IsSuccess  bool   `json:"success"`
	CommitHash string `json:"commitHash"`
}

// GitPushCompleteData is broadcast when git push completes.
type GitPushCompleteData struct {
	PluginID  int64 `json:"pluginId"`
	IsSuccess bool  `json:"success"`
	Pushed    int   `json:"pushed"`
}

// --- Build events ---

// BuildStartedData is broadcast when a build command begins.
type BuildStartedData struct {
	PluginID   int64  `json:"pluginId"`
	PluginName string `json:"pluginName"`
	Command    string `json:"command"`
}

// BuildFailedData is broadcast when a build command fails.
type BuildFailedData struct {
	PluginID int64  `json:"pluginId"`
	Error    string `json:"error"`
	ExitCode int    `json:"exitCode"`
}

// BuildCompleteData is broadcast when a build command succeeds.
type BuildCompleteData struct {
	PluginID  int64 `json:"pluginId"`
	IsSuccess bool  `json:"success"`
	Duration  int64 `json:"duration"`
}

// --- Sync events ---

// SyncStepProgressData is broadcast during sync operations with step granularity.
type SyncStepProgressData struct {
	PluginID int64  `json:"pluginId"`
	SiteID   int64  `json:"siteId"`
	Step     string `json:"step"`
	Progress int    `json:"progress"`
	Total    int    `json:"total"`
	Message  string `json:"message"`
}

// --- Publish events ---

// PublishStageProgressData is broadcast during publish with stage + step detail.
type PublishStageProgressData struct {
	PluginID int64  `json:"pluginId"`
	SiteID   int64  `json:"siteId"`
	Stage    string `json:"stage"`
	Step     string `json:"step"`
	Status   string `json:"status"`
	Progress int    `json:"progress"`
	Total    int    `json:"total"`
	Message  string `json:"message"`
}

// PublishStageStatusData is broadcast for explicit stage status updates (may include details).
type PublishStageStatusData struct {
	PluginID int64           `json:"pluginId"`
	SiteID   int64           `json:"siteId"`
	Stage    string          `json:"stage"`
	Step     string          `json:"step"`
	Status   string          `json:"status"`
	Progress int             `json:"progress"`
	Total    int             `json:"total"`
	Message  string          `json:"message"`
	Details  json.RawMessage `json:"details,omitempty"`
}

// PublishStageCompleteData is broadcast when a publish stage completes.
type PublishStageCompleteData struct {
	Type      string          `json:"type"`
	SessionID string          `json:"sessionId"`
	Stage     string          `json:"stage"`
	Status    string          `json:"status"`
	Duration  int64           `json:"duration"`
	PluginID  int64           `json:"pluginId"`
	SiteID    int64           `json:"siteId"`
	Details   json.RawMessage `json:"details,omitempty"`
}

// --- Scheduler events ---

// ScheduledJobStartedData is broadcast when a scheduled publish job starts.
type ScheduledJobStartedData struct {
	Type       string `json:"type"`
	JobID      string `json:"jobId"`
	PluginID   int64  `json:"pluginId"`
	PluginName string `json:"pluginName"`
}

// ScheduledJobCompleteData is broadcast when a scheduled publish job completes.
type ScheduledJobCompleteData struct {
	Type       string     `json:"type"`
	JobID      string     `json:"jobId"`
	PluginID   int64      `json:"pluginId"`
	PluginName string     `json:"pluginName"`
	DurationMs int64      `json:"durationMs"`
	NextRunAt  *time.Time `json:"nextRunAt,omitempty"`
}

// ScheduledJobSummary holds the shape of a single job in a jobs-update broadcast.
type ScheduledJobSummary struct {
	ID         string `json:"id"`
	PluginID   int64  `json:"pluginId"`
	PluginName string `json:"pluginName"`
	IsEnabled  bool   `json:"enabled"`
	Schedule   string `json:"schedule"`
	LastStatus string `json:"lastStatus"`
	NextRunAt  string `json:"nextRunAt,omitempty"`
	LastRunAt  string `json:"lastRunAt,omitempty"`
}

// ScheduledJobsUpdateData is broadcast when the scheduled jobs list changes.
type ScheduledJobsUpdateData struct {
	Type string                `json:"type"`
	Jobs []ScheduledJobSummary `json:"jobs"`
}

// QueueStatusData is broadcast when the publish queue status changes.
type QueueStatusData struct {
	Type      string `json:"type"`
	Active    int    `json:"active"`
	Queued    int    `json:"queued"`
	Completed int    `json:"completed"`
	Failed    int    `json:"failed"`
}

// --- Version events ---

// VersionCreatedData is broadcast when a new plugin version is recorded.
type VersionCreatedData struct {
	VersionID    int64  `json:"versionId"`
	Version      string `json:"version"`
	PluginID     int64  `json:"pluginId"`
	SiteID       int64  `json:"siteId"`
	FilesUpdated int    `json:"filesUpdated"`
	PublishType  string `json:"publishType"`
}

// RollbackStartedData is broadcast when a version rollback begins.
type RollbackStartedData struct {
	VersionID int64  `json:"versionId"`
	Version   string `json:"version"`
	PluginID  int64  `json:"pluginId"`
	SiteID    int64  `json:"siteId"`
}

// RollbackCompleteData is broadcast when a version rollback finishes.
type RollbackCompleteData struct {
	IsSuccess      bool   `json:"success"`
	VersionID      int64  `json:"versionId"`
	Version        string `json:"version"`
	RolledBackAt   string `json:"rolledBackAt"`
	Implementation string `json:"implementation"`
	Message        string `json:"message"`
}

// --- E2E test events ---

// E2ERunStartedData is broadcast when an E2E test run begins.
type E2ERunStartedData struct {
	RunID      string `json:"runId"`
	TotalTests int    `json:"totalTests"`
}

// E2ETestStartedData is broadcast when an individual E2E test case begins.
type E2ETestStartedData struct {
	RunID    string `json:"runId"`
	CaseID   string `json:"caseId"`
	CaseName string `json:"caseName"`
}

// E2ETestCompletedData is broadcast when an individual E2E test case finishes.
type E2ETestCompletedData struct {
	RunID      string `json:"runId"`
	CaseID     string `json:"caseId"`
	Status     string `json:"status"`
	DurationMs int64  `json:"durationMs"`
}

// E2ERunCompletedData is broadcast when an E2E test run finishes.
type E2ERunCompletedData struct {
	RunID  string `json:"runId"`
	Status string `json:"status"`
	Passed int    `json:"passed,omitempty"`
	Failed int    `json:"failed,omitempty"`
}
