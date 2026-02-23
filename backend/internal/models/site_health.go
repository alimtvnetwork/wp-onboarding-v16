// Package models - Site Health Check data structures
package models

import "time"

// SiteHealthCheck represents a single health check result
type SiteHealthCheck struct {
	Id           int64     `json:"id"`
	SiteId       int64     `json:"siteId"`
	SiteName     string    `json:"siteName"`
	SiteUrl      string    `json:"siteUrl"`
	Status       string    `json:"status"`       // healthy, degraded, down, unknown
	ResponseMs   int64     `json:"responseMs"`
	StatusCode   int       `json:"statusCode"`
	ErrorMessage string    `json:"errorMessage,omitempty"`
	UploaderOk   bool      `json:"uploaderOk"`
	WPVersion    string    `json:"wpVersion,omitempty"`
	PhpVersion   string    `json:"phpVersion,omitempty"`
	CreatedAt    time.Time `json:"createdAt"`
}

// SiteHealthSummary provides aggregated health data for a site
type SiteHealthSummary struct {
	SiteId          int64   `json:"siteId"`
	SiteName        string  `json:"siteName"`
	SiteUrl         string  `json:"siteUrl"`
	CurrentStatus   string  `json:"currentStatus"`
	LastCheckedAt   *string `json:"lastCheckedAt,omitempty"`
	AvgResponseMs   float64 `json:"avgResponseMs"`
	UptimePercent   float64 `json:"uptimePercent"`
	TotalChecks     int     `json:"totalChecks"`
	HealthyChecks   int     `json:"healthyChecks"`
	DownChecks      int     `json:"downChecks"`
	LastErrorAt     *string `json:"lastErrorAt,omitempty"`
	LastError       string  `json:"lastError,omitempty"`
	ConsecutiveDown int     `json:"consecutiveDown"`
}

// SiteHealthStats provides overall health statistics
type SiteHealthStats struct {
	TotalSites    int     `json:"totalSites"`
	HealthySites  int     `json:"healthySites"`
	DegradedSites int     `json:"degradedSites"`
	DownSites     int     `json:"downSites"`
	UnknownSites  int     `json:"unknownSites"`
	AvgResponseMs float64 `json:"avgResponseMs"`
	AvgUptime     float64 `json:"avgUptime"`
}

// SiteHealthFilters for querying health checks
type SiteHealthFilters struct {
	SiteId    int64  `json:"siteId,omitempty"`
	Status    string `json:"status,omitempty"`
	StartDate string `json:"startDate,omitempty"`
	EndDate   string `json:"endDate,omitempty"`
}
