// Package plugin provides local plugin directory management
package plugin

import "time"

// CreateInput holds data for creating a plugin
type CreateInput struct {
	Name            string
	Path            string
	Category        string
	WatchEnabled    bool
	AutoPublish     bool
	ExcludePatterns []string
	GitEnabled      bool
	GitRemoteURL    string
	BuildCommand    string
	ForceCreate     bool // Skip path validation errors
}

// UpdateInput holds data for updating a plugin
type UpdateInput struct {
	Name            *string   `json:",omitempty"`
	Path            *string   `json:",omitempty"`
	Category        *string   `json:",omitempty"`
	WatchEnabled    *bool     `json:",omitempty"`
	AutoPublish     *bool     `json:",omitempty"`
	ExcludePatterns *[]string `json:",omitempty"`
	GitEnabled      *bool     `json:",omitempty"`
	GitRemoteURL    *string   `json:",omitempty"`
	BuildCommand    *string   `json:",omitempty"`
}

// CreateMappingInput holds data for creating a plugin-site mapping
type CreateMappingInput struct {
	PluginID   int64
	SiteID     int64
	RemoteSlug string
}

// ScanResult represents the result of a directory scan
type ScanResult struct {
	Path        string
	IsValid     bool
	PluginName  string     `json:",omitempty"`
	Version     string     `json:",omitempty"`
	MainFile    string     `json:",omitempty"`
	Description string     `json:",omitempty"`
	Author      string     `json:",omitempty"`
	AuthorURI   string     `json:",omitempty"`
	PluginURI   string     `json:",omitempty"`
	TextDomain  string     `json:",omitempty"`
	RequiresPHP string     `json:",omitempty"`
	RequiresWP  string     `json:",omitempty"`
	FileCount   int
	TotalSize   int64
	Files       []FileInfo `json:",omitempty"`
	Error       string     `json:",omitempty"`
}

// IsInvalid returns true if the scan result does not represent a valid plugin.
func (s *ScanResult) IsInvalid() bool { return !s.IsValid }

// FileInfo holds metadata about a single file
type FileInfo struct {
	Path        string
	Size        int64
	Hash        string
	ModifiedAt  time.Time
	IsDirectory bool
}

// PluginDetected represents a detected WordPress plugin written to .plugin-detected.json
type PluginDetected struct {
	PluginName  string `json:"pluginName"`            // external key (.plugin-detected.json)
	Version     string `json:"version"`               // external key
	Slug        string `json:"slug"`                  // external key
	MainFile    string `json:"mainFile"`              // external key
	Description string `json:"description,omitempty"` // external key
	Author      string `json:"author,omitempty"`      // external key
	AuthorURI   string `json:"authorUri,omitempty"`   // external key
	PluginURI   string `json:"pluginUri,omitempty"`   // external key
	TextDomain  string `json:"textDomain,omitempty"`  // external key
	RequiresPHP string `json:"requiresPHP,omitempty"` // external key
	RequiresWP  string `json:"requiresWP,omitempty"`  // external key
	DetectedAt  string `json:"detectedAt"`            // external key
}
