// Package config handles application configuration loading and management
package config

import (
	"encoding/json"
	"os"
	"strconv"

	"wp-plugin-publish/internal/enums/backuptype"
	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	pluginselection "wp-plugin-publish/internal/enums/pluginselectiontype"
	snapshotmode "wp-plugin-publish/internal/enums/snapshotmodetype"
)

// Config represents the application configuration
type Config struct {
	Version       string
	DatabasePath  string
	TempDir       string
	Server        ServerConfig
	Watcher       WatcherConfig
	Backup        BackupConfig
	Logging       LoggingConfig
	Security      SecurityConfig
	WordPress     WordPressConfig
	RemotePlugins RemotePluginsConfig
	Snapshot      SnapshotConfig
	Seed          SeedConfig
	E2E           E2EConfig
	ResponseDebug ResponseDebugConfig
}

// DefaultConfig returns the default configuration
func DefaultConfig() *Config {
	return &Config{
		Version:      "1.0.0",
		DatabasePath: "data/app.db",
		TempDir:      ".temp",
		Server: ServerConfig{
			Port:               8080,
			WSReconnectDelayMs: 3000,
			StaticDir:          "frontend/dist",
		},
		Watcher: WatcherConfig{
			PollIntervalMs:         5000,
			DebounceMs:             500,
			DefaultExcludePatterns: []string{".git", "node_modules", ".DS_Store", "*.log"},
		},
		Backup: BackupConfig{
			Location:            "backups",
			AutoBackupOnPublish: true,
			RetentionDays:       30,
			MaxBackupsPerPlugin: 10,
		},
		Logging: LoggingConfig{
			Level:                  loglevel.Info,
			RetentionDays:          7,
			DebugMode:              false,
			TimeFormat:             "2006-01-02 03:04:05 PM",
			ClearLogsOnStartup:     false,
			ClearSessionsOnStartup: false,
			SessionLoggingEnabled:  true,
			StackTraceDepth:        20,
			PhpStackTraceDepth:     0,
		},
		Security: SecurityConfig{
			EncryptionKey: "",
		},
		WordPress: WordPressConfig{
			TimeoutSeconds: 30,
			MaxRetries:     3,
		},
		RemotePlugins: RemotePluginsConfig{
			CacheEnabled:    true,
			CacheTTLMinutes: 60,
		},
		Snapshot: SnapshotConfig{
			Mode:            snapshotmode.PerTable,
			BackupType:      backuptype.Incremental,
			WorkerCount:     10,
			StoragePath:     "snapshots/",
			IncludePlugins:  true,
			PluginSelection: pluginselection.All,
			RetentionDays:   30,
			RetentionCount:  10,
			Compression:     true,
			BatchSize:       1000,
		},
		Seed: SeedConfig{
			Enabled: false,
			Sites:   []SeedSite{},
			Plugins: []SeedPlugin{},
		},
		E2E: E2EConfig{
			Enabled: false,
		},
		ResponseDebug: ResponseDebugConfig{
			IncludeStackTrace:     true,
			IncludeInternalErrors: true,
			IncludeMethodsStack:   true,
			MaxStackFrames:        20,
		},
	}
}

// Load reads configuration from a JSON file
func Load(path string) (*Config, error) {
	cfg := DefaultConfig()

	file, err := os.Open(path)
	if err != nil {
		if os.IsNotExist(err) {
			return cfg, nil
		}
		return nil, err
	}
	defer file.Close()

	decoder := json.NewDecoder(file)
	if err := decoder.Decode(cfg); err != nil {
		return nil, err
	}

	key := os.Getenv("WPP_ENCRYPTION_KEY")
	if key != "" {
		cfg.Security.EncryptionKey = key
	}

	port := os.Getenv("WPP_PORT")
	if port != "" {
		p, err := strconv.Atoi(port)
		if err == nil {
			cfg.Server.Port = p
		}
	}

	return cfg, nil
}
