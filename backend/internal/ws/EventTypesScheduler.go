// Package ws — scheduler, queue, bulk, version, and E2E event data structs.
package ws

import (
	"time"

	"wp-plugin-publish/internal/enums/publishtype"
)

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

// --- Bulk publish events ---

// BulkPublishStartedData is broadcast when a bulk publish operation begins.
type BulkPublishStartedData struct {
	Type            string
	TotalOperations int
	PluginCount     int
	SiteCount       int
}

// BulkPublishProgressData is broadcast for each item in a bulk publish.
type BulkPublishProgressData struct {
	Type     string
	PluginId int64
	SiteId   int64
	Current  int
	Total    int
	Progress int
	Message  string
}

// BulkPublishCompleteData is broadcast when a bulk publish finishes.
type BulkPublishCompleteData struct {
	Type       string
	Succeeded  int
	Failed     int
	Total      int
	DurationMs int64
}

// --- Version events ---

// VersionCreatedData is broadcast when a new plugin version is recorded.
type VersionCreatedData struct {
	VersionId    int64
	Version      string
	PluginId     int64
	SiteId       int64
	FilesUpdated int
	PublishType  publishtype.Variant
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
	RunId      string
	TotalTests int
}

// E2ETestStartedData is broadcast when an individual E2E test case begins.
type E2ETestStartedData struct {
	RunId    string
	CaseId   string
	CaseName string
}

// E2ETestCompletedData is broadcast when an individual E2E test case finishes.
type E2ETestCompletedData struct {
	RunId      string
	CaseId     string
	Status     string
	DurationMs int64
}

// E2ERunCompletedData is broadcast when an E2E test run finishes.
type E2ERunCompletedData struct {
	RunId  string
	Status string
	Passed int    `json:",omitempty"`
	Failed int    `json:",omitempty"`
}
