// Package models defines data structures for the application
package models

import "time"

// Site represents a WordPress site connection
type Site struct {
	ID                int64      `json:"id"`
	Name              string     `json:"name"`
	URL               string     `json:"url"`
	Username          string     `json:"username"`
	PasswordEncrypted []byte     `json:"-"`
	Category          string     `json:"category"`
	ConnectionStatus  string     `json:"connectionStatus"`
	LastTestedAt      *time.Time `json:"lastTestedAt,omitempty"`
	LastSyncAt        *time.Time `json:"lastSyncAt,omitempty"`
	CreatedAt         time.Time  `json:"createdAt"`
	UpdatedAt         time.Time  `json:"updatedAt"`
}

// Plugin represents a local plugin directory
type Plugin struct {
	ID              int64           `json:"id"`
	Name            string          `json:"name"`
	Path            string          `json:"path"`
	Category        string          `json:"category"`
	WatchEnabled    bool            `json:"watchEnabled"`
	AutoPublish     bool            `json:"autoPublish"`
	ExcludePatterns []string        `json:"excludePatterns"`
	FileCount       int             `json:"fileCount"`
	ModifiedCount   int             `json:"modifiedCount,omitempty"`
	LastScannedAt   *time.Time      `json:"lastScannedAt,omitempty"`
	Mappings        []PluginMapping `json:"mappings,omitempty"`
	GitEnabled      bool            `json:"gitEnabled"`
	CreatedAt       time.Time       `json:"createdAt"`
	UpdatedAt       time.Time       `json:"updatedAt"`
}

// PluginMapping represents the relationship between a plugin and a site
type PluginMapping struct {
	ID           int64     `json:"id"`
	PluginID     int64     `json:"pluginId"`
	SiteID       int64     `json:"siteId"`
	SiteName     string    `json:"siteName,omitempty"`
	SiteURL      string    `json:"siteUrl,omitempty"`
	RemoteSlug   string    `json:"remoteSlug"`
	SyncStatus   string    `json:"syncStatus"`
	LastSyncAt   *time.Time `json:"lastSyncAt,omitempty"`
	LastBackupAt *time.Time `json:"lastBackupAt,omitempty"`
	CreatedAt    time.Time `json:"createdAt"`
	UpdatedAt    time.Time `json:"updatedAt"`
}

// FileChange represents a detected file modification
type FileChange struct {
	ID               int64      `json:"id"`
	PluginID         int64      `json:"pluginId"`
	FilePath         string     `json:"path"`
	ChangeType       string     `json:"status"`    // added, modified, deleted, renamed
	LocalHash        string     `json:"localHash,omitempty"`
	RemoteHash       string     `json:"remoteHash,omitempty"`
	LocalModifiedAt  *time.Time `json:"localModifiedAt,omitempty"`
	RemoteModifiedAt *time.Time `json:"remoteModifiedAt,omitempty"`
	LocalSize        int64      `json:"localSize,omitempty"`
	RemoteSize       int64      `json:"remoteSize,omitempty"`
	Direction        string     `json:"direction,omitempty"` // local_newer, remote_newer, local_only, remote_only
	DetectedAt       time.Time  `json:"detectedAt"`
	SyncedAt         *time.Time `json:"syncedAt,omitempty"`
	Stats            *FileStats `json:"stats,omitempty"`
}

// FileStats holds diff statistics for a file
type FileStats struct {
	Additions int `json:"additions"`
	Deletions int `json:"deletions"`
}

// SyncRecord represents a sync operation history entry
type SyncRecord struct {
	ID              int64     `json:"id"`
	PluginMappingID int64     `json:"pluginMappingId"`
	SyncType        string    `json:"syncType"` // check, publish
	Status          string    `json:"status"`   // pending, running, completed, failed
	FilesChecked    int       `json:"filesChecked"`
	FilesChanged    int       `json:"filesChanged"`
	FilesUploaded   int       `json:"filesUploaded"`
	ErrorMessage    string    `json:"errorMessage,omitempty"`
	StartedAt       time.Time `json:"startedAt"`
	CompletedAt     *time.Time `json:"completedAt,omitempty"`
}

// Backup represents a plugin backup record
type Backup struct {
	ID              int64     `json:"id"`
	PluginMappingID int64     `json:"pluginMappingId"`
	FilePath        string    `json:"filePath"`
	FileSize        int64     `json:"fileSize"`
	PluginVersion   string    `json:"pluginVersion,omitempty"`
	CreatedAt       time.Time `json:"createdAt"`
	ExpiresAt       *time.Time `json:"expiresAt,omitempty"`
}

// PluginVersion represents a publish operation history entry for rollback support
type PluginVersion struct {
	ID            int64     `json:"id"`
	PluginID      int64     `json:"pluginId"`
	SiteID        int64     `json:"siteId"`
	SiteName      string    `json:"siteName,omitempty"`
	Version       string    `json:"version"`
	BackupPath    string    `json:"backupPath,omitempty"`
	FilesUpdated  int       `json:"filesUpdated"`
	GitCommitHash string    `json:"gitCommitHash,omitempty"`
	PublishType   string    `json:"publishType"`
	Status        string    `json:"status"`
	Notes         string    `json:"notes,omitempty"`
	CreatedAt     time.Time `json:"createdAt"`
}

// ErrorLogContext holds structured context for error log entries.
type ErrorLogContext = map[string]any

// ErrorLog represents an application error entry
type ErrorLog struct {
	ID         int64           `json:"id"`
	Code       string          `json:"code"`
	Level      string          `json:"level"`
	Message    string          `json:"message"`
	Details    string          `json:"details,omitempty"`
	Context    ErrorLogContext `json:"context,omitempty"`
	File       string          `json:"file,omitempty"`
	Line       int             `json:"line,omitempty"`
	Function   string          `json:"function,omitempty"`
	StackTrace string          `json:"stackTrace,omitempty"`
	CreatedAt  time.Time       `json:"createdAt"`
}

// Settings represents application settings
type Settings struct {
	Watcher    WatcherSettings    `json:"watcher"`
	Backup     BackupSettings     `json:"backup"`
	Logging    LoggingSettings    `json:"logging"`
	Appearance AppearanceSettings `json:"appearance"`
	Server     ServerSettings     `json:"server"`
}

// WatcherSettings holds file watcher configuration
type WatcherSettings struct {
	PollIntervalMs         int      `json:"pollIntervalMs"`
	DebounceMs             int      `json:"debounceMs"`
	DefaultExcludePatterns []string `json:"defaultExcludePatterns"`
}

// BackupSettings holds backup configuration
type BackupSettings struct {
	AutoBackupBeforePublish bool   `json:"autoBackupBeforePublish"`
	RetentionDays           int    `json:"retentionDays"`
	MaxBackupsPerPlugin     int    `json:"maxBackupsPerPlugin"`
	Location                string `json:"location"`
}

// LoggingSettings holds logging configuration
type LoggingSettings struct {
	Level         string `json:"level"`
	RetentionDays int    `json:"retentionDays"`
	DebugMode     bool   `json:"debugMode"`
}

// AppearanceSettings holds UI configuration
type AppearanceSettings struct {
	Theme       string `json:"theme"`
	CompactMode bool   `json:"compactMode"`
}

// ServerSettings holds server configuration
type ServerSettings struct {
	Port               int `json:"port"`
	WSReconnectDelayMs int `json:"wsReconnectDelayMs"`
}
