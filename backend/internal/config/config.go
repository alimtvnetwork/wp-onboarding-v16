// Package config handles application configuration loading and management
package config

import (
	"encoding/json"
	"os"

	"wp-plugin-publish/internal/database"
)

// Config represents the application configuration
type Config struct {
	Version      string          `json:"version"`
	DatabasePath string          `json:"databasePath"`
	TempDir      string          `json:"tempDir"`
	Server       ServerConfig    `json:"server"`
	Watcher      WatcherConfig   `json:"watcher"`
	Backup       BackupConfig    `json:"backup"`
	Logging      LoggingConfig   `json:"logging"`
	Security     SecurityConfig  `json:"security"`
	WordPress    WordPressConfig `json:"wordpress"`
}

// ServerConfig holds HTTP server settings
type ServerConfig struct {
	Port               int `json:"port"`
	WSReconnectDelayMs int `json:"wsReconnectDelayMs"`
}

// WatcherConfig holds file watcher settings
type WatcherConfig struct {
	PollIntervalMs         int      `json:"pollIntervalMs"`
	DebounceMs             int      `json:"debounceMs"`
	DefaultExcludePatterns []string `json:"defaultExcludePatterns"`
}

// BackupConfig holds backup settings
type BackupConfig struct {
	Location             string `json:"location"`
	AutoBackupOnPublish  bool   `json:"autoBackupOnPublish"`
	RetentionDays        int    `json:"retentionDays"`
	MaxBackupsPerPlugin  int    `json:"maxBackupsPerPlugin"`
}

// LoggingConfig holds logging settings
type LoggingConfig struct {
	Level         string `json:"level"`
	RetentionDays int    `json:"retentionDays"`
	DebugMode     bool   `json:"debugMode"`
}

// SecurityConfig holds security settings
type SecurityConfig struct {
	EncryptionKey string `json:"encryptionKey"`
}

// WordPressConfig holds WordPress API settings
type WordPressConfig struct {
	TimeoutSeconds int `json:"timeoutSeconds"`
	MaxRetries     int `json:"maxRetries"`
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
			Level:         "info",
			RetentionDays: 7,
			DebugMode:     false,
		},
		Security: SecurityConfig{
			EncryptionKey: "", // Must be set via environment or config
		},
		WordPress: WordPressConfig{
			TimeoutSeconds: 30,
			MaxRetries:     3,
		},
	}
}

// Load reads configuration from a JSON file
func Load(path string) (*Config, error) {
	cfg := DefaultConfig()

	file, err := os.Open(path)
	if err != nil {
		if os.IsNotExist(err) {
			// Return defaults if config doesn't exist
			return cfg, nil
		}
		return nil, err
	}
	defer file.Close()

	decoder := json.NewDecoder(file)
	if err := decoder.Decode(cfg); err != nil {
		return nil, err
	}

	// Override with environment variables if set
	if key := os.Getenv("WPP_ENCRYPTION_KEY"); key != "" {
		cfg.Security.EncryptionKey = key
	}
	if port := os.Getenv("WPP_PORT"); port != "" {
		// Parse and set port from env
	}

	return cfg, nil
}

// SeedIfNeeded seeds the database from config if version is newer
func SeedIfNeeded(db *database.DB, cfg *Config) error {
	// Get current seed version from database
	currentVersion, err := db.GetSeedVersion()
	if err != nil {
		return err
	}

	// Compare versions and seed if config is newer
	if compareVersions(cfg.Version, currentVersion) > 0 {
		if err := seedFromConfig(db, cfg); err != nil {
			return err
		}
		if err := db.SetSeedVersion(cfg.Version); err != nil {
			return err
		}
	}

	return nil
}

// seedFromConfig populates database with default values from config
func seedFromConfig(db *database.DB, cfg *Config) error {
	// Seed default settings
	settings := map[string]interface{}{
		"watcher.pollIntervalMs":      cfg.Watcher.PollIntervalMs,
		"watcher.debounceMs":          cfg.Watcher.DebounceMs,
		"backup.retentionDays":        cfg.Backup.RetentionDays,
		"backup.maxBackupsPerPlugin":  cfg.Backup.MaxBackupsPerPlugin,
		"backup.autoBackupOnPublish":  cfg.Backup.AutoBackupOnPublish,
		"logging.level":               cfg.Logging.Level,
		"logging.retentionDays":       cfg.Logging.RetentionDays,
	}

	for key, value := range settings {
		if err := db.SetSettingIfNotExists(key, value); err != nil {
			return err
		}
	}

	return nil
}

// compareVersions compares two semantic versions
// Returns: -1 if a < b, 0 if a == b, 1 if a > b
func compareVersions(a, b string) int {
	if a == b {
		return 0
	}
	if b == "" {
		return 1
	}
	// TODO: Implement proper semantic version comparison
	return 0
}
