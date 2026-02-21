package config

import (
	"encoding/base64"
	"strings"

	"wp-plugin-publish/internal/crypto"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
)

// SeedIfNeeded seeds the database from config if version is newer
func SeedIfNeeded(db *database.DB, cfg *Config, log *logger.Logger) error {
	log.Info("Checking seed requirements", "configVersion", cfg.Version, "seedEnabled", cfg.Seed.Enabled)

	currentVersion, err := db.GetSeedVersion()
	if err != nil {
		log.Error("Failed to get seed version from database", "error", err)
		return err
	}
	log.Debug("Current seed version", "version", currentVersion)

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

	settings := map[string]any{
		"watcher.pollIntervalMs":     cfg.Watcher.PollIntervalMs,
		"watcher.debounceMs":         cfg.Watcher.DebounceMs,
		"backup.retentionDays":       cfg.Backup.RetentionDays,
		"backup.maxBackupsPerPlugin": cfg.Backup.MaxBackupsPerPlugin,
		"backup.autoBackupOnPublish": cfg.Backup.AutoBackupOnPublish,
		"logging.level":              cfg.Logging.Level,
		"logging.retentionDays":      cfg.Logging.RetentionDays,
		"logging.stackTraceDepth":    cfg.Logging.StackTraceDepth,
		"logging.phpStackTraceDepth": cfg.Logging.PhpStackTraceDepth,
		"responseDebug.includeStackTrace":     cfg.ResponseDebug.IncludeStackTrace,
		"responseDebug.includeInternalErrors": cfg.ResponseDebug.IncludeInternalErrors,
		"responseDebug.includeMethodsStack":   cfg.ResponseDebug.IncludeMethodsStack,
		"responseDebug.maxStackFrames":        cfg.ResponseDebug.MaxStackFrames,
		"snapshot.mode":            cfg.Snapshot.Mode,
		"snapshot.backupType":      cfg.Snapshot.BackupType,
		"snapshot.workerCount":     cfg.Snapshot.WorkerCount,
		"snapshot.storagePath":     cfg.Snapshot.StoragePath,
		"snapshot.includePlugins":  cfg.Snapshot.IncludePlugins,
		"snapshot.pluginSelection": cfg.Snapshot.PluginSelection,
		"snapshot.retentionDays":   cfg.Snapshot.RetentionDays,
		"snapshot.retentionCount":  cfg.Snapshot.RetentionCount,
		"snapshot.compression":     cfg.Snapshot.Compression,
		"snapshot.batchSize":       cfg.Snapshot.BatchSize,
	}

	for key, value := range settings {
		if err := db.SetSettingIfNotExists(key, value); err != nil {
			log.Warn("Failed to set setting", "key", key, "error", err)
		}
	}

	if cfg.Seed.Enabled {
		log.Info("Seeding sites and plugins", "siteCount", len(cfg.Seed.Sites), "pluginCount", len(cfg.Seed.Plugins))
		if err := seedSitesAndPlugins(db, cfg, log); err != nil {
			return err
		}
	}

	return nil
}

// seedSitesAndPlugins seeds test sites and plugins from config
func seedSitesAndPlugins(db *database.DB, cfg *Config, log *logger.Logger) error {
	log.Info("=== SEEDING START ===", "sites", len(cfg.Seed.Sites), "plugins", len(cfg.Seed.Plugins))

	var allSiteIds []int64
	encryptionKey := []byte(cfg.Security.EncryptionKey)

	for i, site := range cfg.Seed.Sites {
		normalizedUrl := normalizeUrl(site.URL)
		log.Info("Processing site", "index", i+1, "name", site.Name, "rawUrl", site.URL, "normalizedUrl", normalizedUrl)

		passwordPlaintext, err := base64.StdEncoding.DecodeString(site.ApplicationPassword)
		if err != nil {
			log.Warn("Base64 decode failed for site password, using raw", "site", site.Name)
			passwordPlaintext = []byte(site.ApplicationPassword)
		}

		existingId, err := db.GetSiteIdByUrl(normalizedUrl)
		if err == nil && existingId > 0 {
			log.Info("Site exists in DB", "id", existingId, "name", site.Name)
			allSiteIds = append(allSiteIds, existingId)
			continue
		}

		encryptedPassword, err := crypto.Encrypt(passwordPlaintext, encryptionKey)
		if err != nil {
			log.Error("Failed to encrypt password for site", "site", site.Name, "error", err)
			continue
		}

		id, err := db.CreateSeedSite(site.Name, normalizedUrl, site.Username, encryptedPassword, site.Category)
		if err != nil {
			log.Error("Failed to create seed site", "name", site.Name, "error", err)
			continue
		}
		log.Info("Site CREATED", "name", site.Name, "id", id)
		allSiteIds = append(allSiteIds, id)
	}

	log.Info("Site processing complete", "siteIds", allSiteIds)

	totalMappingsCreated := 0
	for i, plugin := range cfg.Seed.Plugins {
		log.Info("Processing plugin", "index", i+1, "name", plugin.Name, "path", plugin.Path)

		var pluginId int64

		existingId, err := db.GetPluginIdByPath(plugin.Path)
		if err == nil && existingId > 0 {
			log.Info("Plugin exists in DB", "id", existingId, "name", plugin.Name)
			pluginId = existingId
		} else {
			pluginId, err = db.CreateSeedPlugin(plugin.Name, plugin.Path, plugin.Category, plugin.GitEnabled, plugin.AutoPublish)
			if err != nil {
				log.Error("Failed to create seed plugin", "name", plugin.Name, "path", plugin.Path, "error", err)
				continue
			}
			log.Info("Plugin CREATED", "name", plugin.Name, "id", pluginId)
		}

		remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))
		for _, siteId := range allSiteIds {
			created, err := db.CreateSeedMapping(pluginId, siteId, remoteSlug, log)
			if err != nil {
				log.Warn("Failed to create mapping", "pluginId", pluginId, "siteId", siteId, "error", err)
			} else if created {
				totalMappingsCreated++
			}
		}
	}

	log.Info("=== SEEDING COMPLETE ===", "sitesTotal", len(allSiteIds), "pluginsTotal", len(cfg.Seed.Plugins), "mappingsCreated", totalMappingsCreated)
	return nil
}
