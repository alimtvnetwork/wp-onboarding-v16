// Package models - Site Health Check data structures
package models

import "time"

// SiteHealthCheck represents a single health check result
type SiteHealthCheck struct {
	Id           int64
	SiteId       int64
	SiteName     string
	SiteUrl      string
	Status       string    // healthy, degraded, down, unknown
	ResponseMs   int64
	StatusCode   int
	ErrorMessage string    `json:",omitempty"`
	UploaderOk   bool
	WPVersion    string    `json:",omitempty"`
	PhpVersion   string    `json:",omitempty"`
	CreatedAt    time.Time
}

// SiteHealthSummary provides aggregated health data for a site
type SiteHealthSummary struct {
	SiteId          int64
	SiteName        string
	SiteUrl         string
	CurrentStatus   string
	LastCheckedAt   *string `json:",omitempty"`
	AvgResponseMs   float64
	UptimePercent   float64
	TotalChecks     int
	HealthyChecks   int
	DownChecks      int
	LastErrorAt     *string `json:",omitempty"`
	LastError       string  `json:",omitempty"`
	ConsecutiveDown int
}

// SiteHealthStats provides overall health statistics
type SiteHealthStats struct {
	TotalSites    int
	HealthySites  int
	DegradedSites int
	DownSites     int
	UnknownSites  int
	AvgResponseMs float64
	AvgUptime     float64
}

// SiteHealthFilters for querying health checks
type SiteHealthFilters struct {
	SiteId    int64  `json:",omitempty"`
	Status    string `json:",omitempty"`
	StartDate string `json:",omitempty"`
	EndDate   string `json:",omitempty"`
}
