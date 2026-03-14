package config

import (
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
)

// SeedIfNeeded seeds the database from config if version is newer
func SeedIfNeeded(db *database.DB, cfg *Config, log *logger.Logger) *apperror.AppError {
	log.Info("Checking seed requirements", "configVersion", cfg.Version, "seedEnabled", cfg.Seed.Enabled)

	seedErr := seedIfVersionNewer(db, cfg, log)
	if seedErr != nil {
		return seedErr
	}

	if cfg.Seed.Enabled {
		return ensureMappingsIfEnabled(db, cfg, log)
	}

	return nil
}

// seedIfVersionNewer runs seeding when config version exceeds the stored seed version.
func seedIfVersionNewer(db *database.DB, cfg *Config, log *logger.Logger) *apperror.AppError {
	currentVersion, appErr := db.GetSeedVersion()
	if appErr != nil {
		log.Error("Failed to get seed version from database", "error", appErr)
		return appErr
	}

	isNewer := compareVersions(cfg.Version, currentVersion) > 0

	if isNewer {
		return applySeed(db, cfg, log, currentVersion)
	}

	log.Debug("No seeding required", "configVersion", cfg.Version, "dbVersion", currentVersion)

	return nil
}

// applySeed runs seedFromConfig and persists the new seed version.
func applySeed(db *database.DB, cfg *Config, log *logger.Logger, fromVersion string) *apperror.AppError {
	log.Info("Seeding database", "from", fromVersion, "to", cfg.Version)

	seedErr := seedFromConfig(db, cfg, log)
	if seedErr != nil {
		log.Error("Seeding failed", "error", seedErr)
		return apperror.Wrap(seedErr, apperror.ErrConfigSeed, "seed from config")
	}

	setVersionErr := db.SetSeedVersion(cfg.Version)
	if setVersionErr != nil {
		log.Error("Failed to update seed version", "error", setVersionErr)
		return setVersionErr
	}

	log.Info("Seed version updated", "version", cfg.Version)

	return nil
}

// ensureMappingsIfEnabled verifies all plugin→site mappings exist.
func ensureMappingsIfEnabled(db *database.DB, cfg *Config, log *logger.Logger) *apperror.AppError {
	log.Info("Ensuring all plugin→site mappings exist")

	mapErr := ensureMappingsExist(db, cfg, log)
	if mapErr != nil {
		log.Error("Mapping verification failed", "error", mapErr)
		return apperror.Wrap(mapErr, apperror.ErrConfigSeed, "ensure mappings exist")
	}

	return nil
}

// seedFromConfig populates database with default values from config
func seedFromConfig(db *database.DB, cfg *Config, log *logger.Logger) *apperror.AppError {
	log.Debug("Seeding default settings")
	seedDefaultSettings(db, cfg, log)

	if cfg.Seed.Enabled {
		log.Info("Seeding sites and plugins", "siteCount", len(cfg.Seed.Sites), "pluginCount", len(cfg.Seed.Plugins))

		return seedSitesAndPlugins(db, cfg, log)
	}

	return nil
}

// seedSitesAndPlugins seeds test sites and plugins from config
func seedSitesAndPlugins(db *database.DB, cfg *Config, log *logger.Logger) *apperror.AppError {
	log.Info("=== SEEDING START ===", "sites", len(cfg.Seed.Sites), "plugins", len(cfg.Seed.Plugins))
	encryptionKey := []byte(cfg.Security.EncryptionKey)

	allSiteIds := seedAllSites(db, cfg, log, encryptionKey)
	log.Info("Site processing complete", "siteIds", allSiteIds)

	totalMappings := seedAllPlugins(db, cfg, log, allSiteIds)
	log.Info("=== SEEDING COMPLETE ===", "sitesTotal", len(allSiteIds), "pluginsTotal", len(cfg.Seed.Plugins), "mappingsCreated", totalMappings)

	return nil
}
