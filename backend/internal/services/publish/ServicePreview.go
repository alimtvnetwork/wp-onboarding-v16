package publish

import (
	"context"
	"io"
	"os"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// PreviewPublish returns a preview of what files will change during publish
func (s *Service) PreviewPublish(ctx context.Context, pluginID, siteID int64) apperror.Result[PublishPreviewResult] {
	result := &PublishPreviewResult{PluginId: pluginID, SiteId: siteID, Files: []FilePreview{}}

	pluginInfo, err := s.loadPluginForPreview(ctx, pluginID, result)
	if err != nil {
		return apperror.Fail[PublishPreviewResult](err)
	}

	previewLoad, err := s.loadSiteForPreview(ctx, pluginID, siteID, result)
	if err != nil {
		return apperror.Fail[PublishPreviewResult](err)
	}

	scanResult, scanErr := s.scanLocalFiles(pluginInfo.Path, pluginInfo.ExcludePatterns)
	if scanErr != nil {
		return apperror.FailWrap[PublishPreviewResult](scanErr, apperror.ErrFSRead, "failed to scan plugin files")
	}

	wpClient := s.wpClientFactory(previewLoad.Site.URL, previewLoad.Site.Username, previewLoad.Password)
	result.RemoteVersion = s.fetchRemoteVersion(wpClient, previewLoad.Mapping.RemoteSlug)
	remoteFileMap, fetchFailed := s.fetchRemoteFileMap(ctx, wpClient, previewLoad.Mapping.RemoteSlug)

	diffSummary := s.compareFiles(scanResult.Files, remoteFileMap, fetchFailed)
	result.Files = diffSummary.Files
	result.TotalFiles = len(diffSummary.Files)
	result.TotalSize = scanResult.TotalSize
	result.Added = diffSummary.Added
	result.Modified = diffSummary.Modified
	result.Deleted = diffSummary.Deleted

	return apperror.Ok(*result)
}

// loadPluginForPreview loads plugin info and populates preview result fields.
func (s *Service) loadPluginForPreview(ctx context.Context, pluginID int64, result *PublishPreviewResult) (*pluginPreviewInfo, *apperror.AppError) {
	pluginResult := s.pluginService.GetByID(ctx, pluginID)
	if pluginResult.HasError() {
		return nil, apperror.Wrap(pluginResult.AppError(), apperror.ErrNotFound, "plugin not found")
	}
	p := pluginResult.Value()
	result.PluginName = p.Name
	result.LocalVersion = s.getLocalPluginVersion(p.Path)

	info := &pluginPreviewInfo{
		Path:            p.Path,
		ExcludePatterns: p.ExcludePatterns,
	}

	return info, nil
}

// pluginPreviewInfo holds fields needed for preview after loading.
type pluginPreviewInfo struct {
	Path            string
	ExcludePatterns []string
}

// previewLoadResult holds the loaded site, password, and mapping for preview.
type previewLoadResult struct {
	Site     *sitePreviewInfo
	Password string
	Mapping  *mappingPreviewInfo
}

// loadSiteForPreview loads site, credentials, and mapping for preview.
func (s *Service) loadSiteForPreview(ctx context.Context, pluginID, siteID int64, result *PublishPreviewResult) (*previewLoadResult, *apperror.AppError) {
	creds, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrNotFound, "site not found")
	}
	result.SiteName = creds.Site.Name
	result.SiteUrl = creds.Site.URL

	mapping, mapErr := s.getMapping(ctx, pluginID, siteID)
	if mapErr != nil {
		return nil, apperror.Wrap(mapErr, apperror.ErrNotFound, "plugin-site mapping not found")
	}
	result.RemoteSlug = mapping.RemoteSlug

	loadResult := &previewLoadResult{
		Site: &sitePreviewInfo{
			URL:      creds.Site.URL,
			Username: creds.Site.Username,
		},
		Password: creds.Password,
		Mapping: &mappingPreviewInfo{
			RemoteSlug: mapping.RemoteSlug,
		},
	}
	return loadResult, nil
}

type sitePreviewInfo struct {
	URL      string
	Username string
}

type mappingPreviewInfo struct {
	RemoteSlug string
}

// GetFileDiff retrieves both local and remote content for a file to show differences
func (s *Service) GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) apperror.Result[FileDiffResult] {
	pluginResult := s.pluginService.GetByID(ctx, pluginID)
	if pluginResult.HasError() {
		return apperror.Fail[FileDiffResult](apperror.Wrap(pluginResult.AppError(), apperror.ErrDatabaseQuery, "plugin not found"))
	}

	creds, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		return apperror.FailWrap[FileDiffResult](err, apperror.ErrDatabaseQuery, "site not found")
	}

	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return apperror.FailWrap[FileDiffResult](err, apperror.ErrDatabaseQuery, "mapping not found")
	}

	result := &FileDiffResult{Path: filePath}
	result.LocalContent = s.readLocalFileContent(pluginResult.Value().Path, filePath)

	wpClient := s.wpClientFactory(creds.Site.URL, creds.Site.Username, creds.Password)
	result.RemoteContent = s.readRemoteFileContent(ctx, wpClient, mapping.RemoteSlug, filePath)

	return apperror.Ok(*result)
}

// readLocalFileContent reads a local file and returns its content or empty string.
func (s *Service) readLocalFileContent(pluginPath, filePath string) string {
	localPath, err := pathutil.Join(pluginPath, filePath)
	if err != nil {
		return ""
	}
	file, err := os.Open(localPath)
	if err != nil {
		return ""
	}
	defer file.Close()

	content, err := io.ReadAll(file)
	if err != nil {
		return ""
	}
	return string(content)
}

// readRemoteFileContent fetches remote file content or returns empty string.
func (s *Service) readRemoteFileContent(ctx context.Context, wpClient *wordpress.Client, slug, filePath string) string {
	content, err := wpClient.GetPluginFileContent(ctx, slug, filePath)
	if err != nil {
		s.log.Debug("Could not fetch remote file content", "path", filePath, "error", err)
		return ""
	}
	return content
}

// fetchRemoteFileMap fetches the remote file map for diff comparison
func (s *Service) fetchRemoteFileMap(ctx context.Context, wpClient *wordpress.Client, remoteSlug string) (map[string]string, bool) {
	remoteFileMap := make(map[string]string)

	remoteFiles, err := wpClient.GetPluginFilesViaRiseup(ctx, remoteSlug)
	if err != nil {
		remoteFiles, err = wpClient.GetPluginFiles(ctx, remoteSlug)
		if err != nil {
			s.log.Debug("Could not fetch remote files, falling back to local-only preview", "error", err)
			return remoteFileMap, true
		}
	}

	for _, rf := range remoteFiles {
		remoteFileMap[rf.Path] = rf.Hash
	}
	return remoteFileMap, false
}
