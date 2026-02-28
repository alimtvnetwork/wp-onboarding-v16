// Package watcher — auto-publish and broadcast helpers.
package watcher

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// triggerAutoPublish checks if plugin has autoPublish enabled and publishes to all mapped sites
func (s *Service) triggerAutoPublish(ctx context.Context, pluginId int64, changes []FileChange) {
	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return
	}
	p := pResult.Value()

	isAutoPublishDisabled := !p.AutoPublish

	if isAutoPublishDisabled {
		return
	}

	hasMappings := len(p.Mappings) > 0
	isMappingsMissing := !hasMappings

	if isMappingsMissing {
		s.log.Debug("Auto-publish skipped: no site mappings", "plugin", p.Name, "pluginId", pluginId)
		return
	}

	s.log.Info("Auto-publish triggered",
		"plugin", p.Name,
		"pluginId", pluginId,
		"changes", len(changes),
		"sites", len(p.Mappings),
	)

	ws.Broadcast(s.wsHub, ws.EventAutoPublishTriggered, ws.AutoPublishTriggeredData{
		PluginId:   pluginId,
		PluginName: p.Name,
		Changes:    len(changes),
		Sites:      len(p.Mappings),
	})

	if s.publishService == nil {
		s.log.Warn("Auto-publish: publish service not configured", "plugin", p.Name, "pluginId", pluginId)
		return
	}

	s.publishToMappedSites(ctx, p, pluginId, changes)
}

// publishToMappedSites publishes to all mapped sites.
func (s *Service) publishToMappedSites(ctx context.Context, p models.Plugin, pluginId int64, changes []FileChange) {
	successCount := 0
	for _, mapping := range p.Mappings {
		filesUpdated, err := s.publishService.PublishPlugin(ctx, pluginId, mapping.SiteId, "full", true)
		if err != nil {
			s.log.Error("Auto-publish failed",
				"plugin", p.Name,
				"site", mapping.SiteName,
				"pluginId", pluginId,
				"siteId", mapping.SiteId,
				"error", err,
			)
			ws.Broadcast(s.wsHub, ws.EventAutoPublishFailed, ws.AutoPublishFailedData{
				PluginId: pluginId,
				SiteId:   mapping.SiteId,
				SiteName: mapping.SiteName,
				Error:    err.Error(),
			})
			continue
		}
		successCount++
		ws.Broadcast(s.wsHub, ws.EventAutoPublishComplete, ws.AutoPublishCompleteData{
			PluginId:     pluginId,
			SiteId:       mapping.SiteId,
			SiteName:     mapping.SiteName,
			FilesUpdated: filesUpdated,
		})
	}

	s.log.Info("Auto-publish complete", "plugin", p.Name, "pluginId", pluginId, "successfulSites", successCount)
}

// broadcastChanges sends file changes via WebSocket
func (s *Service) broadcastChanges(pluginId int64, changes []FileChange, triggerType string) {
	var created, modified, deleted int
	for _, c := range changes {
		switch c.ChangeType {
		case "created":
			created++
		case "modified":
			modified++
		case "deleted":
			deleted++
		}
	}

	wsChanges := make([]ws.FileChangeItem, len(changes))
	for i, c := range changes {
		wsChanges[i] = ws.FileChangeItem{
			Path:       c.Path,
			ChangeType: c.ChangeType,
			Hash:       c.Hash,
			Size:       c.Size,
			ModTime:    c.ModTime,
		}
	}
	ws.Broadcast(s.wsHub, ws.EventFileChange, ws.FileChangeBatchData{
		PluginId:    pluginId,
		TriggerType: triggerType,
		Changes:     wsChanges,
		Summary: ws.FileChangeSummary{
			Created:  created,
			Modified: modified,
			Deleted:  deleted,
		},
	})
}

// RecordFileChange records a file change in the database
func (s *Service) RecordFileChange(ctx context.Context, change *models.FileChange) error {
	_, err := s.db.ExecContext(ctx, `
		INSERT INTO FileChanges (PluginId, FilePath, ChangeType, LocalHash, DetectedAt)
		VALUES (?, ?, ?, ?, datetime('now'))
	`, change.PluginId, change.FilePath, change.ChangeType, change.LocalHash)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to record file change")
	}

	return nil
}
