// Package models defines data structures for the application
package models

import (
	"encoding/json"
	"time"
)

// Site represents a WordPress site connection
type Site struct {
	Id                int64
	Name              string
	Url               string
	Username          string
	PasswordEncrypted []byte     `json:"-"`
	Category          string
	ConnectionStatus  string
	LastTestedAt      *time.Time `json:",omitempty"`
	LastSyncAt        *time.Time `json:",omitempty"`
	CreatedAt         time.Time
	UpdatedAt         time.Time
}

// Plugin represents a local plugin directory
type Plugin struct {
	Id              int64
	Name            string
	Path            string
	Category        string
	WatchEnabled    bool
	AutoPublish     bool
	ExcludePatterns []string
	FileCount       int
	ModifiedCount   int             `json:",omitempty"`
	LastScannedAt   *time.Time      `json:",omitempty"`
	Mappings        []PluginMapping `json:",omitempty"`
	GitEnabled      bool
	CreatedAt       time.Time
	UpdatedAt       time.Time
}

// PluginMapping represents the relationship between a plugin and a site
type PluginMapping struct {
	Id           int64
	PluginId     int64
	SiteId       int64
	SiteName     string     `json:",omitempty"`
	SiteUrl      string     `json:",omitempty"`
	RemoteSlug   string
	SyncStatus   string
	LastSyncAt   *time.Time `json:",omitempty"`
	LastBackupAt *time.Time `json:",omitempty"`
	CreatedAt    time.Time
	UpdatedAt    time.Time
}

// FileChange represents a detected file modification
type FileChange struct {
	Id               int64
	PluginId         int64
	FilePath         string
	ChangeType       string
	LocalHash        string     `json:",omitempty"`
	RemoteHash       string     `json:",omitempty"`
	LocalModifiedAt  *time.Time `json:",omitempty"`
	RemoteModifiedAt *time.Time `json:",omitempty"`
	LocalSize        int64      `json:",omitempty"`
	RemoteSize       int64      `json:",omitempty"`
	Direction        string     `json:",omitempty"`
	DetectedAt       time.Time
	SyncedAt         *time.Time `json:",omitempty"`
	Stats            *FileStats `json:",omitempty"`
}

// FileStats holds diff statistics for a file
type FileStats struct {
	Additions int
	Deletions int
}

// SyncRecord represents a sync operation history entry
type SyncRecord struct {
	Id              int64
	PluginMappingId int64
	SyncType        string
	Status          string
	FilesChecked    int
	FilesChanged    int
	FilesUploaded   int
	ErrorMessage    string     `json:",omitempty"`
	StartedAt       time.Time
	CompletedAt     *time.Time `json:",omitempty"`
}

// Backup represents a plugin backup record
type Backup struct {
	Id              int64
	PluginMappingId int64
	FilePath        string
	FileSize        int64
	PluginVersion   string     `json:",omitempty"`
	CreatedAt       time.Time
	ExpiresAt       *time.Time `json:",omitempty"`
}

// PluginVersion represents a publish operation history entry for rollback support
type PluginVersion struct {
	Id            int64
	PluginId      int64
	SiteId        int64
	SiteName      string `json:",omitempty"`
	Version       string
	BackupPath    string `json:",omitempty"`
	FilesUpdated  int
	GitCommitHash string `json:",omitempty"`
	PublishType   string
	Status        string
	Notes         string `json:",omitempty"`
	CreatedAt     time.Time
}

// ErrorLog represents an application error entry
type ErrorLog struct {
	Id         int64
	Code       string
	Level      string
	Message    string
	Details    string          `json:",omitempty"`
	Context    json.RawMessage `json:",omitempty"`
	File       string          `json:",omitempty"`
	Line       int             `json:",omitempty"`
	Function   string          `json:",omitempty"`
	StackTrace string          `json:",omitempty"`
	CreatedAt  time.Time
}

// Settings represents application settings
type Settings struct {
	Watcher    WatcherSettings
	Backup     BackupSettings
	Logging    LoggingSettings
	Appearance AppearanceSettings
	Server     ServerSettings
}

// WatcherSettings holds file watcher configuration
type WatcherSettings struct {
	PollIntervalMs         int
	DebounceMs             int
	DefaultExcludePatterns []string
}

// BackupSettings holds backup configuration
type BackupSettings struct {
	AutoBackupBeforePublish bool
	RetentionDays           int
	MaxBackupsPerPlugin     int
	Location                string
}

// LoggingSettings holds logging configuration
type LoggingSettings struct {
	Level         string
	RetentionDays int
	DebugMode     bool
}

// AppearanceSettings holds UI configuration
type AppearanceSettings struct {
	Theme       string
	CompactMode bool
}

// ServerSettings holds server configuration
type ServerSettings struct {
	Port               int
	WSReconnectDelayMs int
}
