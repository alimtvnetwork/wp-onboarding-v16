// Package handlers provides application settings HTTP request handlers
package handlers

import (
	"net/http"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	"wp-plugin-publish/internal/wordpress"
)

// GetSettings returns application settings
func GetSettings(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, buildSettingsResponse())
}

// buildSettingsResponse constructs the full settings response.
func buildSettingsResponse() SettingsResponse {
	return SettingsResponse{
		Watcher:    buildWatcherSettings(),
		Backup:     buildBackupSettings(),
		Logging:    buildLoggingSettings(),
		Appearance: AppearanceSettings{Theme: "system", IsCompactMode: false},
		Server:     ServerSettings{Port: 8080, WSReconnectDelayMs: 3000},
	}
}

// buildWatcherSettings returns the watcher configuration.
func buildWatcherSettings() WatcherSettings {
	return WatcherSettings{
		IsPollingEnabled:       false,
		IsScanAfterGitPull:     true,
		DebounceMs:             500,
		DefaultExcludePatterns: []string{".git", "node_modules", ".DS_Store"},
	}
}

// buildBackupSettings returns the backup configuration.
func buildBackupSettings() BackupSettings {
	return BackupSettings{
		IsAutoBackupBeforePublish: true,
		RetentionDays:             30,
		MaxBackupsPerPlugin:       10,
		Location:                  "backups",
	}
}

// buildLoggingSettings returns the logging configuration.
func buildLoggingSettings() LoggingSettings {
	return LoggingSettings{
		Level:         loglevel.Info.String(),
		RetentionDays: 7,
		IsDebugMode:   false,
	}
}

// UpdateSettings updates application settings
func UpdateSettings(w http.ResponseWriter, r *http.Request) {
	respondError(w, wordpress.HttpStatusNotImplemented, "E9004", "Not implemented")
}
