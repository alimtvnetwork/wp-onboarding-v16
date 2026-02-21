package config

import (
	"wp-plugin-publish/internal/enums/backup_type"
	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/internal/enums/plugin_selection"
	"wp-plugin-publish/internal/enums/snapshot_mode"
)

// E2EConfig holds end-to-end test settings
type E2EConfig struct {
	Enabled          bool
	TestPluginPath   string
	TestSiteURL      string
	TestSiteUsername  string
	TestSitePassword string
}

// ResponseDebugConfig controls error verbosity in API responses.
type ResponseDebugConfig struct {
	IncludeStackTrace     bool
	IncludeInternalErrors bool
	IncludeMethodsStack   bool
	MaxStackFrames        int
}

// SnapshotConfig holds snapshot backup system settings
type SnapshotConfig struct {
	Mode            snapshotmode.Variant
	BackupType      backuptype.Variant
	WorkerCount     int
	StoragePath     string
	IncludePlugins  bool
	PluginSelection pluginselection.Variant
	RetentionDays   int
	RetentionCount  int
	Compression     bool
	BatchSize       int
}

// ServerConfig holds HTTP server settings
type ServerConfig struct {
	Port               int
	WSReconnectDelayMs int
	StaticDir          string
}

// WatcherConfig holds file watcher settings
type WatcherConfig struct {
	PollIntervalMs         int
	DebounceMs             int
	DefaultExcludePatterns []string
}

// BackupConfig holds backup settings
type BackupConfig struct {
	Location            string
	AutoBackupOnPublish bool
	RetentionDays       int
	MaxBackupsPerPlugin int
}

// LoggingConfig holds logging settings
type LoggingConfig struct {
	Level                  loglevel.Variant
	RetentionDays          int
	DebugMode              bool
	TimeFormat             string
	ClearLogsOnStartup     bool
	ClearSessionsOnStartup bool
	SessionLoggingEnabled  bool
	StackTraceDepth        int
	PhpStackTraceDepth     int
}

// SecurityConfig holds security settings
type SecurityConfig struct {
	EncryptionKey string
}

// WordPressConfig holds WordPress API settings
type WordPressConfig struct {
	TimeoutSeconds int
	MaxRetries     int
}

// RemotePluginsConfig holds caching settings for remote plugin lists
type RemotePluginsConfig struct {
	CacheEnabled    bool
	CacheTTLMinutes int
}

// SeedConfig holds seedable test data for quick setup
type SeedConfig struct {
	Enabled bool
	Sites   []SeedSite
	Plugins []SeedPlugin
}

// SeedSite represents a site to seed
type SeedSite struct {
	Name                string
	URL                 string
	Username            string
	ApplicationPassword string
	Category            string
}

// SeedPlugin represents a plugin to seed
type SeedPlugin struct {
	Name        string
	Path        string
	Category    string
	GitEnabled  bool
	AutoPublish bool
	SiteNames   []string
}
