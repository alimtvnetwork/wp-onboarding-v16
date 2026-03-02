// Package sync — sync checking operations.
package sync

import (
	"context"
	"time"

	changetype "wp-plugin-publish/internal/enums/changetype"
	syncstep "wp-plugin-publish/internal/enums/syncsteptype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

// CheckSync compares local vs remote files for a specific plugin-site mapping.
func (s *serviceImpl) CheckSync(ctx context.Context, pluginId, siteId int64) apperror.Result[SyncResult] {
	result := SyncResult{PluginId: pluginId, SiteId: siteId, CheckedAt: time.Now()}

	startProgress := SyncProgressInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Step:     syncstep.Checking.Value(),
		Progress: 0,
		Message:  "Starting sync check...",
	}
	s.broadcastProgress(startProgress)

	localFiles, errMsg := s.scanLocalForCheck(ctx, pluginId, siteId)
	if errMsg != "" {
		result.ErrorMessage = errMsg

		return apperror.Ok(result)
	}
	result.LocalFiles = len(localFiles)

	remoteFiles, errMsg := s.fetchRemoteManifest(ctx, pluginId, siteId)
	if errMsg != "" {
		result.ErrorMessage = errMsg
		errorProgress := SyncProgressInput{
			PluginId: pluginId,
			SiteId:   siteId,
			Step:     syncstep.Error.Value(),
			Progress: 100,
			Message:  errMsg,
		}
		s.broadcastProgress(errorProgress)

		return apperror.Ok(result)
	}
	result.RemoteFiles = len(remoteFiles)

	s.finalizeCheckResult(&result, localFiles, remoteFiles, pluginId, siteId)

	return apperror.Ok(result)
}

// scanLocalForCheck scans local files for a sync check, returning files or an error message.
func (s *serviceImpl) scanLocalForCheck(ctx context.Context, pluginId, siteId int64) (map[string]FileEntry, string) {
	plugResult := s.pluginService.GetById(ctx, pluginId)
	if plugResult.HasError() {
		return nil, plugResult.AppError().Error()
	}
	plug := plugResult.Value()

	scanProgress := SyncProgressInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Step:     syncstep.Scanning.Value(),
		Progress: 20,
		Message:  "Scanning local files...",
	}
	s.broadcastProgress(scanProgress)

	localFiles, err := s.scanLocalFiles(plug.Path, plug.ExcludePatterns)
	if err != nil {
		return nil, err.Error()
	}

	return localFiles, ""
}

// finalizeCheckResult computes changes, updates mapping status, and broadcasts completion.
func (s *serviceImpl) finalizeCheckResult(result *SyncResult, localFiles, remoteFiles map[string]FileEntry, pluginId, siteId int64) {
	compareProgress := SyncProgressInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Step:     syncstep.Comparing.Value(),
		Progress: 70,
		Message:  "Comparing files...",
	}
	s.broadcastProgress(compareProgress)

	changes := s.compareFiles(localFiles, remoteFiles)

	result.Changes = changes
	result.Added = countByType(changes, changetype.Added.Value())
	result.Modified = countByType(changes, changetype.Modified.Value())
	result.Deleted = countByType(changes, changetype.Deleted.Value())
	result.IsInSync = len(changes) == 0

	s.updateMappingSyncStatus(context.Background(), pluginId, siteId, result.IsInSync)

	completeProgress := SyncProgressInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Step:     syncstep.Complete.Value(),
		Progress: 100,
		Message:  "Sync check complete",
	}
	s.broadcastProgress(completeProgress)

	s.log.Info("Sync check completed", "pluginId", pluginId, "siteId", siteId, "isInSync", result.IsInSync, "changes", len(changes))
}

// CheckAllSites checks sync status for all sites mapped to a plugin.
func (s *serviceImpl) CheckAllSites(ctx context.Context, pluginId int64) apperror.Result[BatchSyncResult] {
	result := BatchSyncResult{PluginId: pluginId, Results: []SyncResult{}}

	plugResult := s.pluginService.GetById(ctx, pluginId)
	if plugResult.HasError() {
		return apperror.Fail[BatchSyncResult](plugResult.AppError())
	}
	plug := plugResult.Value()
	result.PluginName = plug.Name

	mappingsResult := s.getMappings(ctx, pluginId)
	if mappingsResult.HasError() {
		return apperror.Fail[BatchSyncResult](mappingsResult.AppError())
	}

	s.aggregateSiteResults(&result, ctx, pluginId, mappingsResult.Items())

	return apperror.Ok(result)
}

// aggregateSiteResults checks each mapping and tallies results.
func (s *serviceImpl) aggregateSiteResults(result *BatchSyncResult, ctx context.Context, pluginId int64, mappings []models.PluginMapping) {
	result.TotalSites = len(mappings)

	for _, mapping := range mappings {
		syncResult := s.CheckSync(ctx, pluginId, mapping.SiteId)
		isSyncUnavailable := !syncResult.IsSafe()

		if isSyncUnavailable {
			continue
		}

		sr := syncResult.Value()
		sr.SiteName = mapping.SiteName
		result.Results = append(result.Results, sr)

		switch {
		case sr.ErrorMessage != "":
			result.Errors++
		case sr.IsInSync:
			result.IsInSync++
		default:
			result.OutOfSync++
		}
	}
}

// CheckAllPlugins checks sync status for all registered plugins.
func (s *serviceImpl) CheckAllPlugins(ctx context.Context) apperror.ResultSlice[SyncResult] {
	pluginListResult := s.pluginService.List(ctx)
	if pluginListResult.HasError() {
		return apperror.FailSlice[SyncResult](pluginListResult.AppError())
	}

	var results []SyncResult
	for _, plug := range pluginListResult.Items() {
		batchResult := s.CheckAllSites(ctx, plug.Id)
		if batchResult.IsSafe() {
			results = append(results, batchResult.Value().Results...)
		}
	}

	if results == nil {
		results = []SyncResult{}
	}

	return apperror.OkSlice(results)
}
