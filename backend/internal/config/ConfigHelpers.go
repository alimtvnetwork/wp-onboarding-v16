package config

import (
	"strconv"
	"strings"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
)

// ensureMappingsExist ensures all plugin→site mappings exist (idempotent, runs every startup)
func ensureMappingsExist(db *database.DB, cfg *Config, log *logger.Logger) *apperror.AppError {
	log.Debug("Verifying mappings exist for all seeded plugins")

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

	isEmpty := len(siteIds) == 0

	if isEmpty {
		log.Debug("No sites found for mapping verification")
		return nil
	}

	log.Debug("Found sites for mapping", "count", len(siteIds))

	mappingsCreated := 0

	for _, plugin := range cfg.Seed.Plugins {
		pluginId, err := db.GetPluginIdByPath(plugin.Path)
		isPluginMissing := err != nil || pluginId == 0

		if isPluginMissing {
			log.Warn("Plugin not found for mapping", "name", plugin.Name, "path", plugin.Path, "error", err)
			continue
		}

		remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))

		for _, siteId := range siteIds {
			created, err := db.CreateSeedMapping(database.SeedMappingInput{PluginId: pluginId, SiteId: siteId, RemoteSlug: remoteSlug, Logger: log})
			if err != nil {
				log.Warn("Mapping creation failed", "pluginId", pluginId, "siteId", siteId, "error", err)
			} else if created {
				mappingsCreated++
			}
		}
	}

	hasMappingsCreated := mappingsCreated > 0

	if hasMappingsCreated {
		log.Info("Mapping verification complete", "mappingsCreated", mappingsCreated)
	} else {
		log.Debug("All mappings already exist")
	}

	return nil
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
		var numA, numB int

		isWithinA := i < len(partsA)

		if isWithinA {
			parsed, parseErr := strconv.Atoi(partsA[i])
			if parseErr == nil {
				numA = parsed
			}
		}

		isWithinB := i < len(partsB)

		if isWithinB {
			parsed, parseErr := strconv.Atoi(partsB[i])
			if parseErr == nil {
				numB = parsed
			}
		}

		isAGreater := numA > numB

		if isAGreater {
			return 1
		}

		isASmaller := numA < numB

		if isASmaller {
			return -1
		}
	}

	return 0
}
