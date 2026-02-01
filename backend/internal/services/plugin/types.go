// Package plugin provides local plugin directory management
package plugin

import "time"

// CreateInput holds data for creating a plugin
type CreateInput struct {
	Name            string   `json:"name" validate:"required,max=255"`
	Path            string   `json:"path" validate:"required,max=4096"`
	WatchEnabled    bool     `json:"watchEnabled"`
	ExcludePatterns []string `json:"excludePatterns"`
	GitEnabled      bool     `json:"gitEnabled"`
	GitRemoteURL    string   `json:"gitRemoteUrl"`
	BuildCommand    string   `json:"buildCommand"`
}

// UpdateInput holds data for updating a plugin
type UpdateInput struct {
	Name            *string   `json:"name,omitempty" validate:"omitempty,max=255"`
	Path            *string   `json:"path,omitempty" validate:"omitempty,max=4096"`
	WatchEnabled    *bool     `json:"watchEnabled,omitempty"`
	ExcludePatterns *[]string `json:"excludePatterns,omitempty"`
	GitEnabled      *bool     `json:"gitEnabled,omitempty"`
	GitRemoteURL    *string   `json:"gitRemoteUrl,omitempty"`
	BuildCommand    *string   `json:"buildCommand,omitempty"`
}

// CreateMappingInput holds data for creating a plugin-site mapping
type CreateMappingInput struct {
	PluginID   int64  `json:"pluginId" validate:"required"`
	SiteID     int64  `json:"siteId" validate:"required"`
	RemoteSlug string `json:"remoteSlug" validate:"required,max=255"`
}

// ScanResult represents the result of a directory scan
type ScanResult struct {
	Path       string     `json:"path"`
	IsValid    bool       `json:"isValid"`
	PluginName string     `json:"pluginName,omitempty"`
	Version    string     `json:"version,omitempty"`
	MainFile   string     `json:"mainFile,omitempty"`
	FileCount  int        `json:"fileCount"`
	TotalSize  int64      `json:"totalSize"`
	Files      []FileInfo `json:"files,omitempty"`
	Error      string     `json:"error,omitempty"`
}

// FileInfo holds metadata about a single file
type FileInfo struct {
	Path        string    `json:"path"`
	Size        int64     `json:"size"`
	Hash        string    `json:"hash"`
	ModifiedAt  time.Time `json:"modifiedAt"`
	IsDirectory bool      `json:"isDirectory"`
}
