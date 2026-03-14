package plugin

import (
	"context"
	"strings"

	"wp-plugin-publish/pkg/apperror"
)

// UpdatePluginMappingsInput bundles parameters for UpdateMappingsForPlugin.
type UpdatePluginMappingsInput struct {
	PluginId   int64
	SiteIds    []int64
	RemoteSlug string
}

// UpdateMappingsForPlugin replaces all site mappings for a plugin.
func (s *Service) UpdateMappingsForPlugin(ctx context.Context, input UpdatePluginMappingsInput) *apperror.AppError {
	s.log.Info("Updating plugin mappings", "pluginId", input.PluginId, "sites", len(input.SiteIds))

	_, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE PluginId = ?", input.PluginId)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to clear existing mappings")
	}

	s.insertMappingsForPlugin(ctx, input)
	return nil
}

// insertMappingsForPlugin inserts a mapping for each siteId.
func (s *Service) insertMappingsForPlugin(ctx context.Context, input UpdatePluginMappingsInput) {
	for _, siteId := range input.SiteIds {
		_, err := s.db.ExecContext(ctx, `
			INSERT INTO PluginMappings (PluginId, SiteId, RemoteSlug, SyncStatus, CreatedAt, UpdatedAt)
			VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now'))
		`, input.PluginId, siteId, input.RemoteSlug)
		if err != nil {
			s.log.Warn("Failed to create mapping", "pluginId", input.PluginId, "siteId", siteId, "error", err)
		}
	}
}

// UpdateMappingsForSite replaces all plugin mappings for a site.
func (s *Service) UpdateMappingsForSite(ctx context.Context, siteId int64, pluginIds []int64) *apperror.AppError {
	s.log.Info("Updating site mappings", "siteId", siteId, "plugins", len(pluginIds))

	slugByPluginId := s.buildSlugMap(ctx, siteId)

	_, err := s.db.ExecContext(ctx, "DELETE FROM PluginMappings WHERE SiteId = ?", siteId)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to clear existing site mappings")
	}

	s.insertMappingsForSite(ctx, insertSiteMappingsInput{SiteId: siteId, PluginIds: pluginIds, SlugMap: slugByPluginId})

	s.log.Info("Site mappings updated", "siteId", siteId, "pluginsLinked", len(pluginIds))
	return nil
}

// buildSlugMap returns a map of pluginId → remoteSlug from existing mappings.
func (s *Service) buildSlugMap(ctx context.Context, siteId int64) map[int64]string {
	existingResult := s.GetMappingsBySite(ctx, siteId)
	slugByPluginId := make(map[int64]string)

	hasExistingMappings := !existingResult.HasError()

	if hasExistingMappings {
		for _, m := range existingResult.Items() {
			slugByPluginId[m.PluginId] = m.RemoteSlug
		}
	}
	return slugByPluginId
}

// insertSiteMappingsInput bundles parameters for insertMappingsForSite.
type insertSiteMappingsInput struct {
	SiteId    int64
	PluginIds []int64
	SlugMap   map[int64]string
}

// insertMappingsForSite inserts mappings for each pluginId using resolved slugs.
func (s *Service) insertMappingsForSite(ctx context.Context, input insertSiteMappingsInput) {
	for _, pluginId := range input.PluginIds {
		remoteSlug := s.resolveRemoteSlug(ctx, pluginId, input.SlugMap)

		_, err := s.db.ExecContext(ctx, `
			INSERT INTO PluginMappings (PluginId, SiteId, RemoteSlug, SyncStatus, CreatedAt, UpdatedAt)
			VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now'))
		`, pluginId, input.SiteId, remoteSlug)
		if err != nil {
			s.log.Warn("Failed to create site mapping", "siteId", input.SiteId, "pluginId", pluginId, "error", err)
		}
	}
}

// resolveRemoteSlug returns the existing slug or generates one from plugin name.
func (s *Service) resolveRemoteSlug(ctx context.Context, pluginId int64, slugByPluginId map[int64]string) string {
	slug, isFound := slugByPluginId[pluginId]
	hasSlug := slug != ""
	isResolved := isFound && hasSlug

	if isResolved {

		return slug
	}

	var pluginName string
	err := s.db.QueryRowContext(ctx, "SELECT Name FROM Plugins WHERE Id = ?", pluginId).Scan(&pluginName)
	if err == nil {
		return strings.ToLower(strings.ReplaceAll(pluginName, " ", "-"))
	}
	return "plugin"
}
