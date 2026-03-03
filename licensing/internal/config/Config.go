// Package config provides environment-based configuration for the licensing server.
package config

import (
	"os"
	"strconv"
)

// Config holds all licensing server configuration values.
type Config struct {
	Port       int    // HTTP server port (default: 8090)
	DBPath     string // SQLite database file path (default: ./data/licensing.db)
	HMACSecret string // Shared secret for HMAC request signing (required)
	AdminToken string // Bearer token for admin endpoints (required)
	RateLimit  int    // Default requests/min per IP (default: 60)
	GraceDays  int    // Grace period after license expiration (default: 7)
	LogLevel   string // Log verbosity: debug, info, warn, error (default: info)
}

// Load reads configuration from environment variables with sensible defaults.
func Load() Config {
	return Config{
		Port:       envInt("LICENSING_PORT", 8090),
		DBPath:     envStr("LICENSING_DB_PATH", "./data/licensing.db"),
		HMACSecret: envStr("LICENSING_HMAC_SECRET", ""),
		AdminToken: envStr("LICENSING_ADMIN_TOKEN", ""),
		RateLimit:  envInt("LICENSING_RATE_LIMIT", 60),
		GraceDays:  envInt("LICENSING_GRACE_DAYS", 7),
		LogLevel:   envStr("LICENSING_LOG_LEVEL", "info"),
	}
}

// Validate checks that required configuration values are set.
func (c Config) Validate() []string {
	var missing []string

	isHMACMissing := c.HMACSecret == ""
	isAdminTokenMissing := c.AdminToken == ""

	if isHMACMissing {
		missing = append(missing, "LICENSING_HMAC_SECRET")
	}
	if isAdminTokenMissing {
		missing = append(missing, "LICENSING_ADMIN_TOKEN")
	}

	return missing
}

// envStr reads a string environment variable with a fallback default.
func envStr(key, fallback string) string {
	val := os.Getenv(key)
	isValEmpty := val == ""

	if isValEmpty {
		return fallback
	}

	return val
}

// envInt reads an integer environment variable with a fallback default.
func envInt(key string, fallback int) int {
	val := os.Getenv(key)
	isValEmpty := val == ""

	if isValEmpty {
		return fallback
	}

	parsed, parseErr := strconv.Atoi(val)
	if parseErr != nil {
		return fallback
	}

	return parsed
}
