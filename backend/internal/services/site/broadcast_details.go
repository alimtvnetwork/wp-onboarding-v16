// Package site — typed detail structs for WebSocket broadcast payloads.
// These replace inline map[string]any literals at call sites, ensuring
// type safety per the Generic Enforce Pattern (GE-1).
package site

import "encoding/json"

// toDetailsMap converts a typed struct to map[string]any for WS broadcast boundaries.
// This preserves the map[string]any interface contract while ensuring call sites use typed structs.
func toDetailsMap(v any) map[string]any {
	data, err := json.Marshal(v)
	if err != nil {
		return nil
	}
	var m map[string]any
	if json.Unmarshal(data, &m) != nil {
		return nil
	}
	return m
}

// --- Connection test detail structs ---

// ErrorDetail carries a single error message for broadcast context.
type ErrorDetail struct {
	Error string `json:"error"`
}

// ConnectionFailureDetails carries context for a failed connection attempt.
type ConnectionFailureDetails struct {
	URL      string `json:"url"`
	Username string `json:"username"`
}

// ConnectionSuccessDetails carries context for a successful connection.
type ConnectionSuccessDetails struct {
	WPVersion string `json:"wpVersion"`
}

// URLNormalizeDetails carries URL normalization context.
type URLNormalizeDetails struct {
	OriginalURL   string `json:"originalUrl"`
	NormalizedURL string `json:"normalizedUrl"`
}

// --- Bootstrap/uploader detail structs ---

// SiteContextDetails carries site identification context for broadcast logs.
type SiteContextDetails struct {
	SiteID   int64  `json:"siteId"`
	SiteName string `json:"siteName,omitempty"`
	SiteURL  string `json:"siteUrl,omitempty"`
}

// SiteIDDetail carries a minimal site ID reference.
type SiteIDDetail struct {
	SiteID int64 `json:"siteId"`
}

// BootstrapLogDetails carries bootstrap progress context with step info.
type BootstrapLogDetails struct {
	SiteID   int64  `json:"siteId"`
	SiteName string `json:"siteName,omitempty"`
	Step     string `json:"step,omitempty"`
	Status   string `json:"status,omitempty"`
	Details  any    `json:"details,omitempty"`
}

// ZipCreationDetails carries ZIP archive creation context.
type ZipCreationDetails struct {
	SiteID int64  `json:"siteId"`
	Path   string `json:"path"`
}

// UploaderDeployDetails carries uploader deployment result context.
type UploaderDeployDetails struct {
	SiteID    int64  `json:"siteId"`
	SiteName  string `json:"siteName"`
	Activated bool   `json:"activated"`
}

// --- Remote action detail structs ---

// PHPErrorDetail carries context for a single remote PHP error entry.
type PHPErrorDetail struct {
	PHPFile    string `json:"phpFile"`
	PHPLine    int    `json:"phpLine"`
	PHPLevel   string `json:"phpLevel"`
	PHPCreated string `json:"phpCreated"`
}

// PHPErrorCountDetail carries the count of remote PHP errors.
type PHPErrorCountDetail struct {
	PHPErrorCount int `json:"phpErrorCount"`
}

// StackTraceLogDetails carries PHP stacktrace metadata.
type StackTraceLogDetails struct {
	Lines     int  `json:"lines"`
	TotalSize int  `json:"totalSize"`
	Truncated bool `json:"truncated"`
}

// StackTraceContentDetails carries full PHP stacktrace content for session persistence.
type StackTraceContentDetails struct {
	Content   string `json:"content"`
	Lines     int    `json:"lines"`
	Truncated bool   `json:"truncated"`
}
