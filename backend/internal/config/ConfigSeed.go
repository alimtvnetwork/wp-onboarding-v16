package config

import (
	"encoding/base64"
	"strings"

	"wp-plugin-publish/internal/crypto"
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

// seedAllSites processes all configured seed sites and returns their IDs.
func seedAllSites(db *database.DB, cfg *Config, log *logger.Logger, encryptionKey []byte) []int64 {
	var allSiteIds []int64

	for i, site := range cfg.Seed.Sites {
		log.Info("Processing site", "index", i+1, "name", site.Name)

		id := seedSingleSite(db, log, site, encryptionKey)
		isCreated := id > 0

		if isCreated {
			allSiteIds = append(allSiteIds, id)
		}
	}

	return allSiteIds
}

// seedSingleSite creates or finds a single site; returns its ID or 0 on failure.
func seedSingleSite(db *database.DB, log *logger.Logger, site SeedSite, encryptionKey []byte) int64 {
	normalizedUrl := normalizeUrl(site.URL)
	password := decodePassword(site.ApplicationPassword, site.Name, log)

	existingId, lookupErr := db.GetSiteIdByUrl(normalizedUrl)
	isExisting := lookupErr == nil && existingId > 0

	if isExisting {
		log.Info("Site exists in DB", "id", existingId, "name", site.Name)
		return existingId
	}

	return createSeedSite(db, log, site, normalizedUrl, password, encryptionKey)
}

// decodePassword decodes a base64 application password, falling back to raw bytes.
func decodePassword(encoded string, siteName string, log *logger.Logger) []byte {
	decoded, decodeErr := base64.StdEncoding.DecodeString(encoded)
	if decodeErr != nil {
		log.Warn("Base64 decode failed for site password, using raw", "site", siteName)
		return []byte(encoded)
	}

	return decoded
}

// createSeedSite encrypts the password and inserts a new site row.
func createSeedSite(
	db *database.DB,
	log *logger.Logger,
	site SeedSite,
	normalizedUrl string,
	password []byte,
	encryptionKey []byte,
) int64 {
	encryptedPassword, encryptErr := crypto.Encrypt(password, encryptionKey)
	if encryptErr != nil {
		log.Error("Failed to encrypt password for site", "site", site.Name, "error", encryptErr)
		return 0
	}

	input := database.SeedSiteInput{
		Name:              site.Name,
		Url:               normalizedUrl,
		Username:          site.Username,
		PasswordEncrypted: encryptedPassword,
		Category:          site.Category,
	}

	id, createErr := db.CreateSeedSite(input)
	if createErr != nil {
		log.Error("Failed to create seed site", "name", site.Name, "error", createErr)
		return 0
	}

	log.Info("Site CREATED", "name", site.Name, "id", id)

	return id
}

// seedAllPlugins processes all configured seed plugins and returns total mappings created.
func seedAllPlugins(db *database.DB, cfg *Config, log *logger.Logger, siteIds []int64) int {
	totalMappings := 0

	for i, plugin := range cfg.Seed.Plugins {
		log.Info("Processing plugin", "index", i+1, "name", plugin.Name, "path", plugin.Path)
		totalMappings += seedSinglePlugin(db, log, plugin, siteIds)
	}

	return totalMappings
}

// seedSinglePlugin creates or finds a plugin and maps it to all sites.
func seedSinglePlugin(db *database.DB, log *logger.Logger, plugin SeedPlugin, siteIds []int64) int {
	pluginId := resolveOrCreatePlugin(db, log, plugin)
	isUnresolved := pluginId == 0

	if isUnresolved {
		return 0
	}

	remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))

	return createPluginMappings(db, log, pluginId, remoteSlug, siteIds)
}

// resolveOrCreatePlugin finds an existing plugin by path or creates a new one.
func resolveOrCreatePlugin(db *database.DB, log *logger.Logger, plugin SeedPlugin) int64 {
	existingId, lookupErr := db.GetPluginIdByPath(plugin.Path)
	isExisting := lookupErr == nil && existingId > 0

	if isExisting {
		log.Info("Plugin exists in DB", "id", existingId, "name", plugin.Name)
		return existingId
	}

	input := database.SeedPluginInput{
		Name:        plugin.Name,
		Path:        plugin.Path,
		Category:    plugin.Category,
		GitEnabled:  plugin.GitEnabled,
		AutoPublish: plugin.AutoPublish,
	}

	id, createErr := db.CreateSeedPlugin(input)
	if createErr != nil {
		log.Error("Failed to create seed plugin", "name", plugin.Name, "error", createErr)
		return 0
	}

	log.Info("Plugin CREATED", "name", plugin.Name, "id", id)

	return id
}

// createPluginMappings maps a plugin to all sites, returning the count of new mappings.
func createPluginMappings(db *database.DB, log *logger.Logger, pluginId int64, remoteSlug string, siteIds []int64) int {
	created := 0

	for _, siteId := range siteIds {
		input := database.SeedMappingInput{
			PluginId:   pluginId,
			SiteId:     siteId,
			RemoteSlug: remoteSlug,
			Logger:     log,
		}

		wasCreated, mapErr := db.CreateSeedMapping(input)
		if mapErr != nil {
			log.Warn("Failed to create mapping", "pluginId", pluginId, "siteId", siteId, "error", mapErr)
		} else if wasCreated {
			created++
		}
	}

	return created
}
