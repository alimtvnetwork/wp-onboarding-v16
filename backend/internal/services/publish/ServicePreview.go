package publish

import (
	"context"
	"io"
	"os"

	"wp-plugin-publish/internal/models"
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

	wpClient := s.wpClientFactory(previewLoad.Site.Url, previewLoad.Site.Username, previewLoad.Password)
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
	pluginResult := s.pluginService.GetById(ctx, pluginID)
	if pluginResult.HasError() {
		return nil, apperror.Wrap(pluginResult.AppError(), apperror.ErrNotFound, "plugin not found")
	}
	p := pluginResult.Value()
	result.PluginName = p.Name
	result.LocalVersion = s.getLocalPluginVersion(p.Path)

	return &pluginPreviewInfo{Path: p.Path, ExcludePatterns: p.ExcludePatterns}, nil
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

type sitePreviewInfo struct {
	Url      string
	Username string
}

type mappingPreviewInfo struct {
	RemoteSlug string
}

// loadSiteForPreview loads site, credentials, and mapping for preview.
func (s *Service) loadSiteForPreview(ctx context.Context, pluginID, siteID int64, result *PublishPreviewResult) (*previewLoadResult, *apperror.AppError) {
	credsResult := s.getSiteCredentials(ctx, siteID)
	if credsResult.HasError() {
		return nil, apperror.Wrap(credsResult.AppError(), apperror.ErrNotFound, "site not found")
	}
	creds := credsResult.Value()
	result.SiteName = creds.Site.Name
	result.SiteUrl = creds.Site.Url

	mapping, mappingErr := s.loadMappingForPreview(ctx, pluginID, siteID)
	if mappingErr != nil {
		return nil, mappingErr
	}
	result.RemoteSlug = mapping.RemoteSlug

	return buildPreviewLoadResult(creds, mapping), nil
}

// loadMappingForPreview loads the plugin-site mapping.
func (s *Service) loadMappingForPreview(ctx context.Context, pluginID, siteID int64) (models.PluginMapping, *apperror.AppError) {
	mappingResult := s.getMapping(ctx, pluginID, siteID)
	if mappingResult.HasError() {
		return models.PluginMapping{}, apperror.Wrap(mappingResult.AppError(), apperror.ErrNotFound, "plugin-site mapping not found")
	}
	return mappingResult.Value(), nil
}

// buildPreviewLoadResult constructs the previewLoadResult from credentials and mapping.
func buildPreviewLoadResult(creds siteCredentials, mapping models.PluginMapping) *previewLoadResult {
	return &previewLoadResult{
		Site:     &sitePreviewInfo{Url: creds.Site.Url, Username: creds.Site.Username},
		Password: creds.Password,
		Mapping:  &mappingPreviewInfo{RemoteSlug: mapping.RemoteSlug},
	}
}

// fileDiffDeps bundles resolved dependencies for GetFileDiff.
type fileDiffDeps struct {
	PluginPath string
	SiteUrl    string
	SiteUser   string
	Password   string
	RemoteSlug string
}

// GetFileDiff retrieves both local and remote content for a file to show differences.
func (s *Service) GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) apperror.Result[FileDiffResult] {
	deps := s.resolveFileDiffDeps(ctx, pluginID, siteID)
	if deps.HasError() {
		return apperror.Fail[FileDiffResult](deps.AppError())
	}

	d := deps.Value()
	result := &FileDiffResult{Path: filePath}
	result.LocalContent = s.readLocalFileContent(d.PluginPath, filePath)

	wpClient := s.wpClientFactory(d.SiteUrl, d.SiteUser, d.Password)
	result.RemoteContent = s.readRemoteFileContent(ctx, wpClient, d.RemoteSlug, filePath)

	return apperror.Ok(*result)
}

// resolveFileDiffDeps loads plugin, site credentials, and mapping for file diff.
func (s *Service) resolveFileDiffDeps(ctx context.Context, pluginID, siteID int64) apperror.Result[fileDiffDeps] {
	pluginResult := s.pluginService.GetById(ctx, pluginID)
	if pluginResult.HasError() {
		return apperror.FailWrap[fileDiffDeps](pluginResult.AppError(), apperror.ErrDatabaseQuery, "plugin not found")
	}

	credsResult := s.getSiteCredentials(ctx, siteID)
	if credsResult.HasError() {
		return apperror.FailWrap[fileDiffDeps](credsResult.AppError(), apperror.ErrDatabaseQuery, "site not found")
	}

	mappingResult := s.getMapping(ctx, pluginID, siteID)
	if mappingResult.HasError() {
		return apperror.FailWrap[fileDiffDeps](mappingResult.AppError(), apperror.ErrDatabaseQuery, "mapping not found")
	}

	creds := credsResult.Value()
	return apperror.Ok(fileDiffDeps{
		PluginPath: pluginResult.Value().Path,
		SiteUrl:    creds.Site.Url,
		SiteUser:   creds.Site.Username,
		Password:   creds.Password,
		RemoteSlug: mappingResult.Value().RemoteSlug,
	})
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
	result := wpClient.GetPluginFileContent(ctx, slug, filePath)
	if result.HasError() {
		s.log.Debug("Could not fetch remote file content", "path", filePath, "error", result.AppError())
		return ""
	}
	return result.Value()
}

// fetchRemoteFileMap fetches the remote file map for diff comparison.
func (s *Service) fetchRemoteFileMap(ctx context.Context, wpClient *wordpress.Client, remoteSlug string) (map[string]string, bool) {
	remoteFileMap := make(map[string]string)

	filesResult := wpClient.GetPluginFilesViaRiseup(ctx, remoteSlug)
	if filesResult.HasError() {
		filesResult = wpClient.GetPluginFiles(ctx, remoteSlug)
		if filesResult.HasError() {
			s.log.Debug("Could not fetch remote files, falling back to local-only preview", "error", filesResult.AppError())
			return remoteFileMap, true
		}
	}

	for _, rf := range filesResult.Value() {
		remoteFileMap[rf.Path] = rf.Hash
	}

	return remoteFileMap, false
}
