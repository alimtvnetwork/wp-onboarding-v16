package config

import (
	"encoding/base64"
	"strings"

	"wp-plugin-publish/internal/crypto"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
)

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
	credentials := resolveCredentials(site)

	existingId, lookupErr := db.GetSiteIdByUrl(normalizedUrl)
	isExisting := lookupErr == nil && existingId > 0

	if isExisting {
		log.Info("Site exists in DB", "id", existingId, "name", site.Name)
		seedCredentialsForSite(db, log, existingId, credentials, encryptionKey)

		return existingId
	}

	id := createSeedSiteRow(db, log, site, normalizedUrl, credentials, encryptionKey)
	isCreated := id > 0

	if isCreated {
		seedCredentialsForSite(db, log, id, credentials, encryptionKey)
	}

	return id
}

// resolveCredentials merges legacy single-credential with Credentials slice.
func resolveCredentials(site SeedSite) []SeedCredential {
	hasCredentials := len(site.Credentials) > 0

	if hasCredentials {
		return site.Credentials
	}

	hasLegacyCredential := site.Username != "" && site.ApplicationPassword != ""

	if hasLegacyCredential {
		return []SeedCredential{{
			AppName:             "default",
			Username:            site.Username,
			ApplicationPassword: site.ApplicationPassword,
			IsDefault:           true,
		}}
	}

	return nil
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

// createSeedSiteRow encrypts the first credential password and inserts a new site row.
func createSeedSiteRow(
	db *database.DB,
	log *logger.Logger,
	site SeedSite,
	normalizedUrl string,
	credentials []SeedCredential,
	encryptionKey []byte,
) int64 {
	firstCred := pickFirstCredential(credentials, site)
	password := decodePassword(firstCred.ApplicationPassword, site.Name, log)

	encryptedPassword, encryptErr := crypto.Encrypt(password, encryptionKey)
	if encryptErr != nil {
		log.Error("Failed to encrypt password for site", "site", site.Name, "error", encryptErr)

		return 0
	}

	input := database.SeedSiteInput{
		Name:              site.Name,
		Url:               normalizedUrl,
		Username:          firstCred.Username,
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

// pickFirstCredential returns the first credential or builds one from legacy fields.
func pickFirstCredential(credentials []SeedCredential, site SeedSite) SeedCredential {
	hasCreds := len(credentials) > 0

	if hasCreds {
		return credentials[0]
	}

	return SeedCredential{
		AppName:             "default",
		Username:            site.Username,
		ApplicationPassword: site.ApplicationPassword,
		IsDefault:           true,
	}
}

// seedCredentialsForSite inserts all credentials for a site into SiteCredentials.
func seedCredentialsForSite(db *database.DB, log *logger.Logger, siteId int64, credentials []SeedCredential, encryptionKey []byte) {
	for _, cred := range credentials {
		seedSingleCredential(db, log, siteId, cred, encryptionKey)
	}
}

// seedSingleCredential inserts one credential if it doesn't already exist.
func seedSingleCredential(db *database.DB, log *logger.Logger, siteId int64, cred SeedCredential, encryptionKey []byte) {
	exists, existsErr := db.CredentialExistsBySiteAndAppName(siteId, cred.AppName)
	if existsErr == nil && exists {
		log.Debug("Credential already exists", "siteId", siteId, "appName", cred.AppName)

		return
	}

	password := decodePassword(cred.ApplicationPassword, cred.AppName, log)

	encrypted, encErr := crypto.Encrypt(password, encryptionKey)
	if encErr != nil {
		log.Error("Failed to encrypt credential password", "appName", cred.AppName, "error", encErr)

		return
	}

	input := database.SeedCredentialInput{
		SiteId:            siteId,
		AppName:           cred.AppName,
		Username:          cred.Username,
		PasswordEncrypted: encrypted,
		IsDefault:         cred.IsDefault,
	}

	id, createErr := db.CreateSiteCredential(input)
	if createErr != nil {
		log.Error("Failed to create seed credential", "appName", cred.AppName, "error", createErr)

		return
	}

	log.Info("Credential CREATED", "credId", id, "siteId", siteId, "appName", cred.AppName)
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
