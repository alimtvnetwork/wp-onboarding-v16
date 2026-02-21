package config

import (
	"strconv"
	"strings"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
)

// ensureMappingsExist ensures all plugin→site mappings exist (idempotent, runs every startup)
func ensureMappingsExist(db *database.DB, cfg *Config, log *logger.Logger) error {
	log.Debug("Verifying mappings exist for all seeded plugins")

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

	mappingsCreated := 0
	for _, plugin := range cfg.Seed.Plugins {
		pluginId, err := db.GetPluginIdByPath(plugin.Path)
		if err != nil || pluginId == 0 {
			log.Warn("Plugin not found for mapping", "name", plugin.Name, "path", plugin.Path, "error", err)
			continue
		}

		remoteSlug := strings.ToLower(strings.ReplaceAll(plugin.Name, " ", "-"))
		for _, siteId := range siteIds {
			created, err := db.CreateSeedMapping(pluginId, siteId, remoteSlug, log)
			if err != nil {
				log.Warn("Mapping creation failed", "pluginId", pluginId, "siteId", siteId, "error", err)
			} else if created {
				mappingsCreated++
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

// normalizeUrl strips common WordPress paths and enforces HTTPS
func normalizeUrl(rawUrl string) string {
	u := strings.TrimSpace(rawUrl)
	u = strings.TrimRight(u, "/")
	for _, suffix := range []string{"/wp-admin", "/wp-login.php", "/wp-json"} {
		u = strings.TrimSuffix(u, suffix)
	}
	if strings.HasPrefix(u, "http://") {
		u = "https://" + strings.TrimPrefix(u, "http://")
	}
	if !strings.HasPrefix(u, "https://") {
		u = "https://" + u
	}
	return u
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
