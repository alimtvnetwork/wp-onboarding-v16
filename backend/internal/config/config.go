// Package config handles application configuration loading and management
package config

import (
	"encoding/base64"
	"encoding/json"
	"os"
	"strconv"
	"strings"

	"wp-plugin-publish/internal/crypto"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
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
	Name        string   `json:"name"`
	Path        string   `json:"path"`
	Category    string   `json:"category"`
	GitEnabled  bool     `json:"gitEnabled"`
	AutoPublish bool     `json:"autoPublish"`
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
func SeedIfNeeded(db *database.DB, cfg *Config, log *logger.Logger) error {
	log.Info("Checking seed requirements", "configVersion", cfg.Version, "seedEnabled", cfg.Seed.Enabled)

	// Get current seed version from database
	currentVersion, err := db.GetSeedVersion()
	if err != nil {
		log.Error("Failed to get seed version from database", "error", err)
		return err
	}
	log.Debug("Current seed version", "version", currentVersion)

	// Compare versions and seed if config is newer
	if compareVersions(cfg.Version, currentVersion) > 0 {
		log.Info("Seeding database", "from", currentVersion, "to", cfg.Version)
		if err := seedFromConfig(db, cfg, log); err != nil {
			log.Error("Seeding failed", "error", err)
			return err
		}
		if err := db.SetSeedVersion(cfg.Version); err != nil {
			log.Error("Failed to update seed version", "error", err)
			return err
		}
		log.Info("Seed version updated", "version", cfg.Version)
	} else {
		log.Debug("No seeding required", "configVersion", cfg.Version, "dbVersion", currentVersion)
	}

	// ALWAYS ensure mappings exist on every startup (idempotent)
	// This catches cases where plugins/sites exist but mappings are missing
	if cfg.Seed.Enabled {
		log.Info("Ensuring all plugin→site mappings exist")
		if err := ensureMappingsExist(db, cfg, log); err != nil {
			log.Error("Mapping verification failed", "error", err)
			return err
		}
	}

	return nil
}

// seedFromConfig populates database with default values from config
func seedFromConfig(db *database.DB, cfg *Config, log *logger.Logger) error {
	log.Debug("Seeding default settings")

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
			log.Warn("Failed to set setting", "key", key, "error", err)
		}
	}

	// Seed sites and plugins if enabled
	if cfg.Seed.Enabled {
		log.Info("Seeding sites and plugins", "siteCount", len(cfg.Seed.Sites), "pluginCount", len(cfg.Seed.Plugins))
		if err := seedSitesAndPlugins(db, cfg, log); err != nil {
			return err
		}
	}

	return nil
}

// normalizeUrl strips common WordPress paths and enforces HTTPS
func normalizeUrl(rawUrl string) string {
	u := strings.TrimSpace(rawUrl)
	// Remove trailing slashes
	u = strings.TrimRight(u, "/")
	// Strip common WP paths
	for _, suffix := range []string{"/wp-admin", "/wp-login.php", "/wp-json"} {
		u = strings.TrimSuffix(u, suffix)
	}
	// Enforce HTTPS
	if strings.HasPrefix(u, "http://") {
		u = "https://" + strings.TrimPrefix(u, "http://")
	}
	if !strings.HasPrefix(u, "https://") {
		u = "https://" + u
	}
	return u
}

// seedSitesAndPlugins seeds test sites and plugins from config
// This implementation maps ALL plugins to ALL sites (requested behaviour)
func seedSitesAndPlugins(db *database.DB, cfg *Config, log *logger.Logger) error {
	// Collect all seeded site IDs (for all→all mapping)
	var allSiteIds []int64

	// Get encryption key from config
	encryptionKey := []byte(cfg.Security.EncryptionKey)

	// Seed sites
	log.Debug("Processing seed sites", "count", len(cfg.Seed.Sites))
	for _, site := range cfg.Seed.Sites {
		// Normalize URL before checking/inserting
		normalizedUrl := normalizeUrl(site.URL)
		log.Debug("Processing seed site", "name", site.Name, "url", normalizedUrl)

		// Decode base64 password to get plaintext
		passwordPlaintext, err := base64.StdEncoding.DecodeString(site.ApplicationPassword)
		if err != nil {
			log.Warn("Base64 decode failed for site password, using raw", "site", site.Name)
			passwordPlaintext = []byte(site.ApplicationPassword)
		}

		// Check if site already exists by URL
		existingId, err := db.GetSiteIdByUrl(normalizedUrl)
		if err == nil && existingId > 0 {
			log.Debug("Site already exists", "name", site.Name, "id", existingId)
			allSiteIds = append(allSiteIds, existingId)
			continue
		}

		// IMPORTANT: Encrypt password using AES-256-GCM before storing
		encryptedPassword, err := crypto.Encrypt(passwordPlaintext, encryptionKey)
		if err != nil {
			log.Error("Failed to encrypt password for site", "site", site.Name, "error", err)
			continue
		}

		// Insert site with properly encrypted password and normalized URL
		id, err := db.CreateSeedSite(site.Name, normalizedUrl, site.Username, encryptedPassword, site.Category)
		if err != nil {
			log.Error("Failed to create seed site", "name", site.Name, "error", err)
			continue
		}
		log.Info("Created seed site", "name", site.Name, "id", id)
		allSiteIds = append(allSiteIds, id)
	}

	// Seed plugins and map each to ALL sites
	log.Debug("Processing seed plugins", "count", len(cfg.Seed.Plugins))
	for _, plugin := range cfg.Seed.Plugins {
		log.Debug("Processing seed plugin", "name", plugin.Name, "path", plugin.Path)

		var pluginId int64

		// Check if plugin already exists by path
		existingId, err := db.GetPluginIdByPath(plugin.Path)
		if err == nil && existingId > 0 {
			log.Debug("Plugin already exists", "name", plugin.Name, "id", existingId)
			pluginId = existingId
		} else {
			// Insert new plugin
			pluginId, err = db.CreateSeedPlugin(plugin.Name, plugin.Path, plugin.Category, plugin.GitEnabled, plugin.AutoPublish)
			if err != nil {
				log.Error("Failed to create seed plugin", "name", plugin.Name, "path", plugin.Path, "error", err)
				continue
			}
			log.Info("Created seed plugin", "name", plugin.Name, "id", pluginId)
		}

		// Create mappings to ALL seeded sites (all→all)
		remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))
		mappingsCreated := 0
		for _, siteId := range allSiteIds {
			err := db.CreateSeedMapping(pluginId, siteId, remoteSlug)
			if err != nil {
				// Check if it's a duplicate error (expected for existing mappings)
				if strings.Contains(err.Error(), "UNIQUE") {
					log.Debug("Mapping already exists", "pluginId", pluginId, "siteId", siteId)
				} else {
					log.Warn("Failed to create mapping", "pluginId", pluginId, "siteId", siteId, "error", err)
				}
			} else {
				mappingsCreated++
				log.Debug("Created mapping", "pluginId", pluginId, "siteId", siteId, "remoteSlug", remoteSlug)
			}
		}
		if mappingsCreated > 0 {
			log.Info("Plugin mappings created", "plugin", plugin.Name, "mappings", mappingsCreated)
		}
	}

	log.Info("Seeding complete", "sitesTotal", len(allSiteIds), "pluginsTotal", len(cfg.Seed.Plugins))
	return nil
}

// ensureMappingsExist ensures all plugin→site mappings exist (idempotent, runs every startup)
// This handles cases where plugins/sites exist but mappings were not created
func ensureMappingsExist(db *database.DB, cfg *Config, log *logger.Logger) error {
	log.Debug("Verifying mappings exist for all seeded plugins")

	// Get all site IDs
	var siteIds []int64
	for _, site := range cfg.Seed.Sites {
		normalizedUrl := normalizeUrl(site.URL)
		if id, err := db.GetSiteIdByUrl(normalizedUrl); err == nil && id > 0 {
			siteIds = append(siteIds, id)
		} else {
			log.Warn("Site not found in database", "name", site.Name, "url", normalizedUrl, "error", err)
		}
	}

	if len(siteIds) == 0 {
		log.Debug("No sites found for mapping verification")
		return nil
	}

	log.Debug("Found sites for mapping", "count", len(siteIds))

	// Ensure each plugin is mapped to all sites
	mappingsCreated := 0
	for _, plugin := range cfg.Seed.Plugins {
		pluginId, err := db.GetPluginIdByPath(plugin.Path)
		if err != nil || pluginId == 0 {
			log.Warn("Plugin not found for mapping", "name", plugin.Name, "path", plugin.Path, "error", err)
			continue
		}

		remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))
		for _, siteId := range siteIds {
			// CreateSeedMapping uses INSERT OR IGNORE, so this is idempotent
			err := db.CreateSeedMapping(pluginId, siteId, remoteSlug)
			if err != nil {
				if strings.Contains(err.Error(), "UNIQUE") {
					// Mapping already exists - this is expected
					log.Debug("Mapping already exists", "pluginId", pluginId, "siteId", siteId)
				} else {
					log.Warn("Mapping creation failed", "pluginId", pluginId, "siteId", siteId, "error", err)
				}
			} else {
				mappingsCreated++
				log.Debug("Created missing mapping", "pluginId", pluginId, "siteId", siteId)
			}
		}
	}

	if mappingsCreated > 0 {
		log.Info("Mapping verification complete", "mappingsCreated", mappingsCreated)
	} else {
		log.Debug("All mappings already exist")
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
