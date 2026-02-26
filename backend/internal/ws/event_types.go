// Package ws — typed broadcast event data structs.
// Every Hub.Broadcast / ws.Broadcast[T] call MUST use one of these structs
// instead of map[string]any literals, per the Generic Enforce Pattern (GE-1).
package ws

import "encoding/json"

import "time"

// --- Auto-publish events ---

// AutoPublishTriggeredData is broadcast when file-watcher auto-publish begins.
type AutoPublishTriggeredData struct {
	PluginId   int64
	PluginName string
	Changes    int
	Sites      int
}

// AutoPublishFailedData is broadcast when auto-publish fails for a site.
type AutoPublishFailedData struct {
	PluginId int64
	SiteId   int64
	SiteName string
	Error    string
}

// AutoPublishCompleteData is broadcast when auto-publish succeeds for a site.
type AutoPublishCompleteData struct {
	PluginId     int64
	SiteId       int64
	SiteName     string
	FilesUpdated int
}

// FileChangeSummary holds aggregated counts for a batch of file changes.
type FileChangeSummary struct {
	Created  int
	Modified int
	Deleted  int
}

// FileChangeItem represents a single detected file change (mirrors watcher.FileChange for ws layer).
type FileChangeItem struct {
	Path       string
	ChangeType string
	Hash       string    `json:",omitempty"`
	Size       int64     `json:",omitempty"`
	ModTime    time.Time `json:",omitempty"`
}

// FileChangeBatchData is broadcast when the watcher detects file changes.
type FileChangeBatchData struct {
	PluginId    int64
	TriggerType string
	Changes     []FileChangeItem
	Summary     FileChangeSummary
}

// --- Git events ---

// GitPullStartedData is broadcast when git pull begins.
type GitPullStartedData struct {
	PluginId   int64
	PluginName string
}

// GitPullFailedData is broadcast when git pull fails.
type GitPullFailedData struct {
	PluginId int64
	Error    string
}

// GitPullCompleteData is broadcast when git pull completes successfully.
type GitPullCompleteData struct {
	PluginId     int64
	IsSuccess    bool
	FilesChanged int
	CommitHash   string
}

// GitPullAllCompleteData is broadcast when batch git pull completes.
type GitPullAllCompleteData struct {
	Succeeded int
	Failed    int
	Duration  int64
}

// GitCommitCompleteData is broadcast when git commit completes.
type GitCommitCompleteData struct {
	PluginId   int64
	IsSuccess  bool
	CommitHash string
}

// GitPushCompleteData is broadcast when git push completes.
type GitPushCompleteData struct {
	PluginId  int64
	IsSuccess bool
	Pushed    int
}

// --- Build events ---

// BuildStartedData is broadcast when a build command begins.
type BuildStartedData struct {
	PluginId   int64
	PluginName string
	Command    string
}

// BuildFailedData is broadcast when a build command fails.
type BuildFailedData struct {
	PluginId int64
	Error    string
	ExitCode int
}

// BuildCompleteData is broadcast when a build command succeeds.
type BuildCompleteData struct {
	PluginId  int64
	IsSuccess bool
	Duration  int64
}

// --- Sync events ---

// SyncStepProgressData is broadcast during sync operations with step granularity.
type SyncStepProgressData struct {
	PluginId int64
	SiteId   int64
	Step     string
	Progress int
	Total    int
	Message  string
}

// --- Publish events ---

// PublishStageProgressData is broadcast during publish with stage + step detail.
type PublishStageProgressData struct {
	PluginId int64
	SiteId   int64
	Stage    string
	Step     string
	Status   string
	Progress int
	Total    int
	Message  string
}

// PublishStageStatusData is broadcast for explicit stage status updates (may include details).
type PublishStageStatusData struct {
	PluginId int64
	SiteId   int64
	Stage    string
	Step     string
	Status   string
	Progress int
	Total    int
	Message  string
	Details  json.RawMessage `json:",omitempty"`
}

// PublishStageCompleteData is broadcast when a publish stage completes.
type PublishStageCompleteData struct {
	Type      string
	SessionId string
	Stage     string
	Status    string
	Duration  int64
	PluginId  int64
	SiteId    int64
	Details   json.RawMessage `json:",omitempty"`
}

// --- Scheduler events ---

// ScheduledJobStartedData is broadcast when a scheduled publish job starts.
type ScheduledJobStartedData struct {
	Type       string
	JobId      string
	PluginId   int64
	PluginName string
}

// ScheduledJobCompleteData is broadcast when a scheduled publish job completes.
type ScheduledJobCompleteData struct {
	Type       string
	JobId      string
	PluginId   int64
	PluginName string
	DurationMs int64
	NextRunAt  *time.Time `json:",omitempty"`
}

// ScheduledJobSummary holds the shape of a single job in a jobs-update broadcast.
type ScheduledJobSummary struct {
	Id         string
	PluginId   int64
	PluginName string
	IsEnabled  bool
	Schedule   string
	LastStatus string
	NextRunAt  string `json:",omitempty"`
	LastRunAt  string `json:",omitempty"`
}

// ScheduledJobsUpdateData is broadcast when the scheduled jobs list changes.
type ScheduledJobsUpdateData struct {
	Type string
	Jobs []ScheduledJobSummary
}

// QueueStatusData is broadcast when the publish queue status changes.
type QueueStatusData struct {
	Type      string
	Active    int
	Queued    int
	Completed int
	Failed    int
}

// --- Version events ---

// VersionCreatedData is broadcast when a new plugin version is recorded.
type VersionCreatedData struct {
	VersionId    int64
	Version      string
	PluginId     int64
	SiteId       int64
	FilesUpdated int
	PublishType  string
}

// RollbackStartedData is broadcast when a version rollback begins.
type RollbackStartedData struct {
	VersionId int64
	Version   string
	PluginId  int64
	SiteId    int64
}

// RollbackCompleteData is broadcast when a version rollback finishes.
type RollbackCompleteData struct {
	IsSuccess      bool
	VersionId      int64
	Version        string
	RolledBackAt   string
	Implementation string
	Message        string
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
