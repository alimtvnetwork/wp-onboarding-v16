// Package config handles application configuration loading and management
package config

import (
	"encoding/base64"
	"encoding/json"
	"os"
	"strconv"
	"strings"

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
	Seed         SeedConfig      `json:"seed"`
}

// ServerConfig holds HTTP server settings
type ServerConfig struct {
	Port               int    `json:"port"`
	WSReconnectDelayMs int    `json:"wsReconnectDelayMs"`
	StaticDir          string `json:"staticDir"`
}

// WatcherConfig holds file watcher settings
type WatcherConfig struct {
	PollIntervalMs         int      `json:"pollIntervalMs"`
	DebounceMs             int      `json:"debounceMs"`
	DefaultExcludePatterns []string `json:"defaultExcludePatterns"`
}

// BackupConfig holds backup settings
type BackupConfig struct {
	Location            string `json:"location"`
	AutoBackupOnPublish bool   `json:"autoBackupOnPublish"`
	RetentionDays       int    `json:"retentionDays"`
	MaxBackupsPerPlugin int    `json:"maxBackupsPerPlugin"`
}

// LoggingConfig holds logging settings
type LoggingConfig struct {
	Level         string `json:"level"`
	RetentionDays int    `json:"retentionDays"`
	DebugMode     bool   `json:"debugMode"`
	// TimeFormat uses Go time layout (e.g. "2006-01-02 03:04:05 PM" for 12-hour clock).
	// This is the SINGLE SOURCE OF TRUTH for all backend log timestamps.
	TimeFormat string `json:"timeFormat"`
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

// SeedConfig holds seedable test data for quick setup
type SeedConfig struct {
	Enabled bool         `json:"enabled"`
	Sites   []SeedSite   `json:"sites"`
	Plugins []SeedPlugin `json:"plugins"`
}

// SeedSite represents a site to seed
type SeedSite struct {
	Name                string `json:"name"`
	URL                 string `json:"url"`
	Username            string `json:"username"`
	ApplicationPassword string `json:"applicationPassword"` // Base64 encoded
	Category            string `json:"category"`
}

// SeedPlugin represents a plugin to seed
type SeedPlugin struct {
	Name        string `json:"name"`
	Path        string `json:"path"`
	Category    string `json:"category"`
	GitEnabled  bool   `json:"gitEnabled"`
	AutoPublish bool   `json:"autoPublish"`
	SiteNames   []string `json:"siteNames"` // Names of sites to link
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
			Level:         "info",
			RetentionDays: 7,
			DebugMode:     false,
			// Default: [YYYY-MM-DD hh:mm:ss AM/PM] (12-hour clock)
			TimeFormat: "2006-01-02 03:04:05 PM",
		},
		Security: SecurityConfig{
			EncryptionKey: "", // Must be set via environment or config
		},
		WordPress: WordPressConfig{
			TimeoutSeconds: 30,
			MaxRetries:     3,
		},
		Seed: SeedConfig{
			Enabled: false,
			Sites:   []SeedSite{},
			Plugins: []SeedPlugin{},
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
		"watcher.pollIntervalMs":     cfg.Watcher.PollIntervalMs,
		"watcher.debounceMs":         cfg.Watcher.DebounceMs,
		"backup.retentionDays":       cfg.Backup.RetentionDays,
		"backup.maxBackupsPerPlugin": cfg.Backup.MaxBackupsPerPlugin,
		"backup.autoBackupOnPublish": cfg.Backup.AutoBackupOnPublish,
		"logging.level":              cfg.Logging.Level,
		"logging.retentionDays":      cfg.Logging.RetentionDays,
	}

	for key, value := range settings {
		if err := db.SetSettingIfNotExists(key, value); err != nil {
			return err
		}
	}

	// Seed sites and plugins if enabled
	if cfg.Seed.Enabled {
		if err := seedSitesAndPlugins(db, cfg); err != nil {
			return err
		}
	}

	return nil
}

// seedSitesAndPlugins seeds test sites and plugins from config
func seedSitesAndPlugins(db *database.DB, cfg *Config) error {
	// Build site name -> ID map for plugin mapping
	siteNameToID := make(map[string]int64)

	// Seed sites
	for _, site := range cfg.Seed.Sites {
		// Decode base64 password
		passwordBytes, err := base64.StdEncoding.DecodeString(site.ApplicationPassword)
		if err != nil {
			// Try raw password if base64 decode fails
			passwordBytes = []byte(site.ApplicationPassword)
		}

		// Check if site already exists by URL
		existingID, err := db.GetSiteIDByURL(site.URL)
		if err == nil && existingID > 0 {
			siteNameToID[site.Name] = existingID
			continue
		}

		// Insert site with encrypted password placeholder (encryption handled by service layer)
		id, err := db.CreateSeedSite(site.Name, site.URL, site.Username, passwordBytes, site.Category)
		if err != nil {
			continue // Skip if insert fails (e.g., duplicate)
		}
		siteNameToID[site.Name] = id
	}

	// Seed plugins
	for _, plugin := range cfg.Seed.Plugins {
		// Check if plugin already exists by path
		existingID, err := db.GetPluginIDByPath(plugin.Path)
		if err == nil && existingID > 0 {
			continue
		}

		// Insert plugin
		pluginID, err := db.CreateSeedPlugin(plugin.Name, plugin.Path, plugin.Category, plugin.GitEnabled, plugin.AutoPublish)
		if err != nil {
			continue
		}

		// Create mappings for each linked site
		for _, siteName := range plugin.SiteNames {
			if siteID, ok := siteNameToID[siteName]; ok {
				remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))
				_ = db.CreateSeedMapping(pluginID, siteID, remoteSlug)
			}
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

	partsA := strings.Split(a, ".")
	partsB := strings.Split(b, ".")

	for i := 0; i < 3; i++ {
		var numA, numB int
		if i < len(partsA) {
			numA, _ = strconv.Atoi(partsA[i])
		}
		if i < len(partsB) {
			numB, _ = strconv.Atoi(partsB[i])
		}
		if numA > numB {
			return 1
		}
		if numA < numB {
			return -1
		}
	}

	return 0
}
