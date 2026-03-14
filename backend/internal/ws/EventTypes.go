// Package ws — typed broadcast event data structs.
// Every Hub.Broadcast / ws.Broadcast[T] call MUST use one of these structs
// instead of map[string]any literals, per the Generic Enforce Pattern (GE-1).
package ws

import (
	"encoding/json"
	"time"
)

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
