package sync

import (
	"context"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) CheckSync(ctx context.Context, pluginID, siteID int64) (*SyncResult, error) {
	s.log.Info("Checking sync status", "pluginId", pluginID, "siteId", siteID)

	// Broadcast sync started event
	s.wsHub.Broadcast(ws.EventSyncStarted, map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
	})

	result := &SyncResult{
		PluginID:  pluginID,
		SiteID:    siteID,
		CheckedAt: time.Now(),
		Changes:   []FileChange{},
	}

	// Get plugin details
	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		result.Status = "error"
		result.Error = err.Error()
		return result, err
	}
	result.PluginName = plugin.Name

	// Get site details
	var site models.Site
	err = s.db.QueryRowContext(ctx, `
		SELECT Id, Name, Url, Username, PasswordEncrypted
		FROM Sites WHERE Id = ?
	`, siteID).Scan(&site.ID, &site.Name, &site.URL, &site.Username, &site.PasswordEncrypted)
	if err != nil {
		result.Status = "error"
		result.Error = "site not found"
		return result, apperror.New(apperror.ErrNotFound, "site not found")
	}
	result.SiteName = site.Name

	// Get mapping to find remote slug
	var remoteSlug string
	err = s.db.QueryRowContext(ctx, `
		SELECT RemoteSlug FROM PluginMappings
		WHERE PluginId = ? AND SiteId = ?
	`, pluginID, siteID).Scan(&remoteSlug)
	if err != nil {
		result.Status = "error"
		result.Error = "plugin not mapped to site"
		return result, apperror.New(apperror.ErrNotFound, "mapping not found")
	}

	// Scan local plugin directory
	localScan, err := s.pluginService.ScanDirectory(ctx, plugin.Path)
	if err != nil {
		result.Status = "error"
		result.Error = err.Error()
		return result, err
	}
	result.TotalFiles = localScan.FileCount

	// Create WordPress client and get remote files
	wpClient := s.wpClientFactory(site.URL, site.Username, string(site.PasswordEncrypted))
	remoteFiles, err := wpClient.GetPluginFiles(ctx, remoteSlug)
	if err != nil {
		// If remote plugin doesn't exist, all files are "added"
		s.log.Warn("Could not fetch remote files", "error", err)
		for _, f := range localScan.Files {
			if !f.IsDirectory {
				result.Changes = append(result.Changes, FileChange{
					Path:       f.Path,
					ChangeType: "added",
					LocalHash:  f.Hash,
					LocalSize:  f.Size,
					LocalMTime: f.ModifiedAt,
				})
				result.AddedFiles++
			}
		}
		result.ChangedFiles = result.AddedFiles
		result.Status = "pending"
	} else {
		// Compare local and remote files
		result.Changes = s.compareFiles(localScan.Files, remoteFiles)
		for _, c := range result.Changes {
			switch c.ChangeType {
			case "added":
				result.AddedFiles++
			case "modified":
				result.ModifiedFiles++
			case "deleted":
				result.DeletedFiles++
			}
		}
		result.ChangedFiles = len(result.Changes)

		if result.ChangedFiles == 0 {
			result.Status = "synced"
		} else {
			result.Status = "pending"
		}
	}

	// Update mapping sync status
	s.db.ExecContext(ctx, `
		UPDATE PluginMappings 
		SET SyncStatus = ?, UpdatedAt = datetime('now')
		WHERE PluginId = ? AND SiteId = ?
	`, result.Status, pluginID, siteID)

	// Broadcast sync complete event
	s.wsHub.Broadcast(ws.EventSyncComplete, map[string]interface{}{
		"pluginId":     pluginID,
		"siteId":       siteID,
		"status":       result.Status,
		"changedFiles": result.ChangedFiles,
	})

	return result, nil
}

func (s *serviceImpl) CheckAllSites(ctx context.Context, pluginID int64) (*BatchSyncResult, error) {
	s.log.Info("Checking sync for all sites", "pluginId", pluginID)

	// Get all mappings for this plugin
	mappings, err := s.pluginService.GetMappings(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	batch := &BatchSyncResult{
		PluginID: pluginID,
		Results:  make([]SyncResult, 0, len(mappings)),
		Summary:  SyncSummary{TotalSites: len(mappings)},
	}

	for _, m := range mappings {
		result, err := s.CheckSync(ctx, pluginID, m.SiteID)
		if err != nil {
			batch.Summary.ErrorSites++
		} else {
			switch result.Status {
			case "synced":
				batch.Summary.SyncedSites++
			case "pending":
				batch.Summary.PendingSites++
			default:
				batch.Summary.ErrorSites++
			}
			batch.Summary.TotalChanges += result.ChangedFiles
		}
		batch.Results = append(batch.Results, *result)
	}

	return batch, nil
}

func (s *serviceImpl) CheckAllPlugins(ctx context.Context) ([]SyncResult, error) {
	s.log.Info("Checking sync for all plugins")

	// Get all mappings
	rows, err := s.db.QueryContext(ctx, `
		SELECT PluginId, SiteId FROM PluginMappings
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var results []SyncResult
	for rows.Next() {
		var pluginID, siteID int64
		rows.Scan(&pluginID, &siteID)

		result, _ := s.CheckSync(ctx, pluginID, siteID)
		if result != nil {
			results = append(results, *result)
		}
	}

	return results, nil
}
