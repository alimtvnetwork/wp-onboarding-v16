package config

import (
	"strconv"
	"strings"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
)

// seedSetting is a typed key-value pair for seeding settings.
type seedSetting struct {
	Key   string
	Value any // restricted to int/string/bool from config fields
}

// seedDefaultSettings writes all config-driven settings to the database.
func seedDefaultSettings(db *database.DB, cfg *Config, log *logger.Logger) {
	settings := buildSettingsList(cfg)

	for _, s := range settings {
		err := db.SetSettingIfNotExists(s.Key, s.Value)
		if err != nil {
			log.Warn("Failed to set setting", "key", s.Key, "error", err)
		}
	}
}

// buildSettingsList returns the full list of seedable settings from config.
func buildSettingsList(cfg *Config) []seedSetting {
	return []seedSetting{
		{"watcher.pollIntervalMs", cfg.Watcher.PollIntervalMs},
		{"watcher.debounceMs", cfg.Watcher.DebounceMs},
		{"backup.retentionDays", cfg.Backup.RetentionDays},
		{"backup.maxBackupsPerPlugin", cfg.Backup.MaxBackupsPerPlugin},
		{"backup.autoBackupOnPublish", cfg.Backup.AutoBackupOnPublish},
		{"logging.level", cfg.Logging.Level},
		{"logging.retentionDays", cfg.Logging.RetentionDays},
		{"logging.stackTraceDepth", cfg.Logging.StackTraceDepth},
		{"logging.phpStackTraceDepth", cfg.Logging.PhpStackTraceDepth},
		{"responseDebug.includeStackTrace", cfg.ResponseDebug.IncludeStackTrace},
		{"responseDebug.includeInternalErrors", cfg.ResponseDebug.IncludeInternalErrors},
		{"responseDebug.includeMethodsStack", cfg.ResponseDebug.IncludeMethodsStack},
		{"responseDebug.maxStackFrames", cfg.ResponseDebug.MaxStackFrames},
		{"snapshot.mode", cfg.Snapshot.Mode},
		{"snapshot.backupType", cfg.Snapshot.BackupType},
		{"snapshot.workerCount", cfg.Snapshot.WorkerCount},
		{"snapshot.storagePath", cfg.Snapshot.StoragePath},
		{"snapshot.includePlugins", cfg.Snapshot.IncludePlugins},
		{"snapshot.pluginSelection", cfg.Snapshot.PluginSelection},
		{"snapshot.retentionDays", cfg.Snapshot.RetentionDays},
		{"snapshot.retentionCount", cfg.Snapshot.RetentionCount},
		{"snapshot.compression", cfg.Snapshot.Compression},
		{"snapshot.batchSize", cfg.Snapshot.BatchSize},
	}
}

// ensureMappingsExist ensures all plugin→site mappings exist (idempotent, runs every startup)
func ensureMappingsExist(db *database.DB, cfg *Config, log *logger.Logger) *apperror.AppError {
	log.Debug("Verifying mappings exist for all seeded plugins")

	siteIds := collectSeedSiteIds(db, cfg, log)
	isEmpty := len(siteIds) == 0

	if isEmpty {
		log.Debug("No sites found for mapping verification")
		return nil
	}

	log.Debug("Found sites for mapping", "count", len(siteIds))
	mappingsCreated := createMappingsForAllPlugins(db, cfg, log, siteIds)
	logMappingResult(log, mappingsCreated)

	return nil
}

// collectSeedSiteIds resolves database IDs for all configured seed sites.
func collectSeedSiteIds(db *database.DB, cfg *Config, log *logger.Logger) []int64 {
	var siteIds []int64

	for _, site := range cfg.Seed.Sites {
		normalizedUrl := normalizeUrl(site.URL)
		id, err := db.GetSiteIdByUrl(normalizedUrl)
		isFound := err == nil && id > 0

		if isFound {
			siteIds = append(siteIds, id)
		} else {
			log.Warn("Site not found in database", "name", site.Name, "url", normalizedUrl, "error", err)
		}
	}

	return siteIds
}

// createMappingsForAllPlugins maps every seed plugin to the given sites.
func createMappingsForAllPlugins(db *database.DB, cfg *Config, log *logger.Logger, siteIds []int64) int {
	mappingsCreated := 0

	for _, plugin := range cfg.Seed.Plugins {
		pluginId, err := db.GetPluginIdByPath(plugin.Path)
		isPluginMissing := err != nil || pluginId == 0

		if isPluginMissing {
			log.Warn("Plugin not found for mapping", "name", plugin.Name, "path", plugin.Path, "error", err)
			continue
		}

		remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))
		mappingsCreated += createPluginMappings(db, log, pluginId, remoteSlug, siteIds)
	}

	return mappingsCreated
}

// logMappingResult logs the mapping verification outcome.
func logMappingResult(log *logger.Logger, mappingsCreated int) {
	hasMappingsCreated := mappingsCreated > 0

	if hasMappingsCreated {
		log.Info("Mapping verification complete", "mappingsCreated", mappingsCreated)
	} else {
		log.Debug("All mappings already exist")
	}
}

// normalizeUrl strips common WordPress paths and enforces HTTPS
func normalizeUrl(rawUrl string) string {
	u := strings.TrimSpace(rawUrl)
	u = strings.TrimRight(u, "/")

	for _, suffix := range []string{"/wp-admin", "/wp-login.php", "/wp-json"} {
		u = strings.TrimSuffix(u, suffix)
	}

	isHttp := strings.HasPrefix(u, "http://")

	if isHttp {
		u = "https://" + strings.TrimPrefix(u, "http://")
	}

	isHttpsMissing := !strings.HasPrefix(u, "https://")

	if isHttpsMissing {
		u = "https://" + u
	}

	return u
}

// compareVersions compares two semantic versions
// Returns: -1 if a < b, 0 if a == b, 1 if a > b
func compareVersions(a, b string) int {
	isEqual := a == b

	if isEqual {
		return 0
	}

	isBEmpty := b == ""

	if isBEmpty {
		return 1
	}

	partsA := strings.Split(a, ".")
	partsB := strings.Split(b, ".")

	for i := 0; i < 3; i++ {
		result := compareVersionPart(partsA, partsB, i)
		isDecisive := result != 0

		if isDecisive {
			return result
		}
	}

	return 0
}

// compareVersionPart compares a single version segment at the given index.
func compareVersionPart(partsA []string, partsB []string, index int) int {
	numA := parseVersionPart(partsA, index)
	numB := parseVersionPart(partsB, index)

	isAGreater := numA > numB

	if isAGreater {
		return 1
	}

	isASmaller := numA < numB

	if isASmaller {
		return -1
	}

	return 0
}

// parseVersionPart extracts an integer from a version parts slice at the given index.
func parseVersionPart(parts []string, index int) int {
	isWithinBounds := index < len(parts)

	if isWithinBounds {
		parsed, parseErr := strconv.Atoi(parts[index])
		if parseErr == nil {
			return parsed
		}
	}

	return 0
}
