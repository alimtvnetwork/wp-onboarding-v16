// Package models - Publish History data structures
package models

import "time"

// PublishHistory represents a record of a publish operation
type PublishHistory struct {
	Id               int64
	PluginId         int64
	PluginName       string
	SiteId           int64
	SiteName         string
	SiteUrl          string
	SessionId        string     `json:",omitempty"`
	Status           string     // success, failed, partial
	Mode             string     // full, selected
	FilesUpdated     int
	ActivationStatus string     // active, inactive, error
	RollbackStatus   string     `json:",omitempty"`
	RollbackMessage  string     `json:",omitempty"`
	ErrorMessage     string     `json:",omitempty"`
	DurationMs       int64
	CreatedAt        time.Time
}

// PublishHistoryFilters for querying publish history
type PublishHistoryFilters struct {
	PluginId int64  `json:",omitempty"`
	SiteId   int64  `json:",omitempty"`
	Status   string `json:",omitempty"`
	Search   string `json:",omitempty"`
}

// PublishHistoryStats aggregates publish statistics
type PublishHistoryStats struct {
	TotalPublishes    int
	SuccessCount      int
	FailureCount      int
	PartialCount      int
	AvgDurationMs     float64
	TotalFilesUpdated int
	LastPublishAt     *string `json:",omitempty"`
}
