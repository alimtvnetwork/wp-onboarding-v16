// Package publish — standalone diff computation endpoint.
package publish

import (
	"context"

	"wp-plugin-publish/pkg/apperror"
)

// DiffResult represents the result of computing a diff between local and remote files.
type DiffResult struct {
	PluginId   int64
	PluginName string
	SiteId     int64
	SiteName   string
	SiteUrl    string
	RemoteSlug string
	TotalFiles int
	TotalSize  int64
	Added      int
	Modified   int
	Deleted    int
	Unchanged  int
	Files      []FilePreview
}

// ComputeDiff compares local plugin files against the remote manifest and returns the diff.
func (s *Service) ComputeDiff(ctx context.Context, pluginId, siteId int64) apperror.Result[DiffResult] {
	result := &DiffResult{PluginId: pluginId, SiteId: siteId, Files: []FilePreview{}}

	pluginInfo, err := s.loadPluginForDiff(ctx, pluginId, result)
	if err != nil {
		return apperror.Fail[DiffResult](err)
	}

	previewLoad, err := s.loadSiteForDiff(ctx, pluginId, siteId, result)
	if err != nil {
		return apperror.Fail[DiffResult](err)
	}

	scanResult, scanErr := s.scanLocalFiles(pluginInfo.Path, pluginInfo.ExcludePatterns)
	if scanErr != nil {
		return apperror.FailWrap[DiffResult](scanErr, apperror.ErrFSRead, "failed to scan plugin files")
	}

	// Invalidate cache to force fresh comparison
	s.manifestCache.Invalidate(pluginId, siteId)

	wpClient := s.wpClientFactory(previewLoad.Site.Url, previewLoad.Site.Username, previewLoad.Password)
	remoteFileMap, fetchFailed := s.fetchRemoteFileMap(ctx, wpClient, previewLoad.Mapping.RemoteSlug, pluginId, siteId)

	diffSummary := s.compareFiles(scanResult.Files, remoteFileMap, fetchFailed)
	result.Files = diffSummary.Files
	result.TotalFiles = len(diffSummary.Files)
	result.TotalSize = scanResult.TotalSize
	result.Added = diffSummary.Added
	result.Modified = diffSummary.Modified
	result.Deleted = diffSummary.Deleted
	result.Unchanged = diffSummary.Unchanged

	return apperror.Ok(*result)
}

// loadPluginForDiff loads plugin info for diff computation.
func (s *Service) loadPluginForDiff(ctx context.Context, pluginId int64, result *DiffResult) (*pluginPreviewInfo, *apperror.AppError) {
	pluginResult := s.pluginService.GetById(ctx, pluginId)
	if pluginResult.HasError() {
		return nil, apperror.Wrap(pluginResult.AppError(), apperror.ErrNotFound, "plugin not found")
	}
	p := pluginResult.Value()
	result.PluginName = p.Name

	return &pluginPreviewInfo{Path: p.Path, ExcludePatterns: p.ExcludePatterns}, nil
}

// loadSiteForDiff loads site, credentials, and mapping for diff.
func (s *Service) loadSiteForDiff(ctx context.Context, pluginId, siteId int64, result *DiffResult) (*previewLoadResult, *apperror.AppError) {
	credsResult := s.getSiteCredentials(ctx, siteId)
	if credsResult.HasError() {
		return nil, apperror.Wrap(credsResult.AppError(), apperror.ErrNotFound, "site not found")
	}
	creds := credsResult.Value()
	result.SiteName = creds.Site.Name
	result.SiteUrl = creds.Site.Url

	mapping, mappingErr := s.loadMappingForPreview(ctx, pluginId, siteId)
	if mappingErr != nil {
		return nil, mappingErr
	}
	result.RemoteSlug = mapping.RemoteSlug

	return buildPreviewLoadResult(creds, mapping), nil
}
