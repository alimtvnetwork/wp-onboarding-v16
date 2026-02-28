// Package sync — sync checking operations.
package sync

import (
	"context"

	changetype "wp-plugin-publish/internal/enums/changetype"
	syncstep "wp-plugin-publish/internal/enums/syncsteptype"
	"wp-plugin-publish/pkg/apperror"
)

// CheckSync compares local vs remote files for a specific plugin-site mapping
func (s *serviceImpl) CheckSync(ctx context.Context, pluginID, siteID int64) apperror.Result[SyncResult] {
	result := SyncResult{
		PluginID:  pluginID,
		SiteID:    siteID,
		CheckedAt: time.Now(),
	}

	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Checking.Value(), Progress: 0, Message: "Starting sync check..."})

	plugResult := s.pluginService.GetByID(ctx, pluginID)
	if plugResult.HasError() {
		result.ErrorMessage = plugResult.AppError().Error()

		return apperror.Ok(result)
	}
	plug := plugResult.Value()

	// Scan local files
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Scanning.Value(), Progress: 20, Message: "Scanning local files..."})
	localFiles, err := s.scanLocalFiles(plug.Path, plug.ExcludePatterns)
	if err != nil {
		result.ErrorMessage = err.Error()

		return apperror.Ok(result)
	}
	result.LocalFiles = len(localFiles)

	// Fetch remote manifest
	remoteFiles, errMsg := s.fetchRemoteManifest(ctx, pluginID, siteID)
	if errMsg != "" {
		result.ErrorMessage = errMsg
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Error.Value(), Progress: 100, Message: errMsg})

		return apperror.Ok(result)
	}
	result.RemoteFiles = len(remoteFiles)

	// Compare files
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 70, Message: "Comparing files..."})
	changes := s.compareFiles(localFiles, remoteFiles)

	result.Changes = changes
	result.Added = countByType(changes, changetype.Added.Value())
	result.Modified = countByType(changes, changetype.Modified.Value())
	result.Deleted = countByType(changes, changetype.Deleted.Value())
	result.IsInSync = len(changes) == 0

	s.updateMappingSyncStatus(ctx, pluginID, siteID, result.IsInSync)
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100, Message: "Sync check complete"})

	s.log.Info("Sync check completed",
		"pluginId", pluginID,
		"siteId", siteID,
		"isInSync", result.IsInSync,
		"changes", len(changes))

	return apperror.Ok(result)
}

// CheckAllSites checks sync status for all sites mapped to a plugin
func (s *serviceImpl) CheckAllSites(ctx context.Context, pluginID int64) apperror.Result[BatchSyncResult] {
	result := BatchSyncResult{
		PluginID: pluginID,
		Results:  []SyncResult{},
	}

	plugResult := s.pluginService.GetByID(ctx, pluginID)
	if plugResult.HasError() {
		return apperror.Fail[BatchSyncResult](plugResult.AppError())
	}
	plug := plugResult.Value()
	result.PluginName = plug.Name

	mappingsResult := s.getMappings(ctx, pluginID)
	if mappingsResult.HasError() {
		return apperror.Fail[BatchSyncResult](mappingsResult.AppError())
	}
	mappings := mappingsResult.Items()
	result.TotalSites = len(mappings)

	for _, mapping := range mappings {
		syncResult := s.CheckSync(ctx, pluginID, mapping.SiteID)
		if syncResult.IsSafe() {
			sr := syncResult.Value()
			sr.SiteName = mapping.SiteName
			result.Results = append(result.Results, sr)

			if sr.ErrorMessage != "" {
				result.Errors++
			} else if sr.IsInSync {
				result.IsInSync++
			} else {
				result.OutOfSync++
			}
		}
	}

	return apperror.Ok(result)
}

// CheckAllPlugins checks sync status for all registered plugins
func (s *serviceImpl) CheckAllPlugins(ctx context.Context) apperror.ResultSlice[SyncResult] {
	var results []SyncResult

	pluginListResult := s.pluginService.List(ctx)
	if pluginListResult.HasError() {
		return apperror.FailSlice[SyncResult](pluginListResult.AppError())
	}

	pluginList := pluginListResult.Items()

	for _, plug := range pluginList {
		batchResult := s.CheckAllSites(ctx, plug.ID)
		if batchResult.IsSafe() {
			results = append(results, batchResult.Value().Results...)
		}
	}

	isResultsEmpty := results == nil

	if isResultsEmpty {
		results = []SyncResult{}
	}
	return apperror.OkSlice(results)
}
