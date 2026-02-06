// Package models - Publish History data structures
package models

import "time"

// PublishHistory represents a record of a publish operation
type PublishHistory struct {
	ID               int64     `json:"id"`
	PluginID         int64     `json:"pluginId"`
	PluginName       string    `json:"pluginName"`
	SiteID           int64     `json:"siteId"`
	SiteName         string    `json:"siteName"`
	SiteURL          string    `json:"siteUrl"`
	SessionID        string    `json:"sessionId,omitempty"`
	Status           string    `json:"status"`           // success, failed, partial
	Mode             string    `json:"mode"`             // full, selected
	FilesUpdated     int       `json:"filesUpdated"`
	ActivationStatus string    `json:"activationStatus"` // active, inactive, error
	RollbackStatus   string    `json:"rollbackStatus,omitempty"`
	RollbackMessage  string    `json:"rollbackMessage,omitempty"`
	ErrorMessage     string    `json:"errorMessage,omitempty"`
	DurationMs       int64     `json:"durationMs"`
	CreatedAt        time.Time `json:"createdAt"`
}

// PublishHistoryFilters for querying publish history
type PublishHistoryFilters struct {
	PluginID int64  `json:"pluginId,omitempty"`
	SiteID   int64  `json:"siteId,omitempty"`
	Status   string `json:"status,omitempty"`
	Search   string `json:"search,omitempty"`
}

// PublishHistoryStats aggregates publish statistics
type PublishHistoryStats struct {
	TotalPublishes   int     `json:"totalPublishes"`
	SuccessCount     int     `json:"successCount"`
	FailureCount     int     `json:"failureCount"`
	PartialCount     int     `json:"partialCount"`
	AvgDurationMs    float64 `json:"avgDurationMs"`
	TotalFilesUpdated int    `json:"totalFilesUpdated"`
	LastPublishAt    *string `json:"lastPublishAt,omitempty"`
}
