package publish

import (
	"context"
	"io"
	"os"
	"path/filepath"
	"strings"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// PreviewPublish returns a preview of what files will change during publish
func (s *Service) PreviewPublish(ctx context.Context, pluginID, siteID int64) apperror.Result[PublishPreviewResult] {
	result := &PublishPreviewResult{
		PluginID: pluginID,
		SiteID:   siteID,
		Files:    []FilePreview{},
	}

	pluginResult := s.pluginService.GetByID(ctx, pluginID)
	if pluginResult.HasError() {
		return apperror.Fail[PublishPreviewResult](apperror.Wrap(pluginResult.AppError(), apperror.ErrNotFound, "plugin not found"))
	}
	pluginInfo := pluginResult.Value()
	result.PluginName = pluginInfo.Name
	result.LocalVersion = s.getLocalPluginVersion(pluginInfo.Path)

	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		return apperror.FailWrap[PublishPreviewResult](err, apperror.ErrNotFound, "site not found")
	}
	result.SiteName = siteInfo.Name
	result.SiteURL = siteInfo.URL

	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return apperror.FailWrap[PublishPreviewResult](err, apperror.ErrNotFound, "plugin-site mapping not found")
	}
	result.RemoteSlug = mapping.RemoteSlug

	// Scan local files
	localFiles, totalSize, err := s.scanLocalFiles(pluginInfo.Path, pluginInfo.ExcludePatterns)
	if err != nil {
		return apperror.FailWrap[PublishPreviewResult](err, apperror.ErrFSRead, "failed to scan plugin files")
	}

	// Fetch remote files for diff
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	result.RemoteVersion = s.fetchRemoteVersion(wpClient, mapping.RemoteSlug)
	remoteFileMap, fetchFailed := s.fetchRemoteFileMap(ctx, wpClient, mapping.RemoteSlug)

	// Compare
	files, added, modified, deleted := s.compareFiles(localFiles, remoteFileMap, fetchFailed)

	result.Files = files
	result.TotalFiles = len(files)
	result.TotalSize = totalSize
	result.Added = added
	result.Modified = modified
	result.Deleted = deleted

	return apperror.Ok(*result)
}

// GetFileDiff retrieves both local and remote content for a file to show differences
func (s *Service) GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) apperror.Result[FileDiffResult] {
	pluginResult := s.pluginService.GetByID(ctx, pluginID)
	if pluginResult.HasError() {
		return apperror.Fail[FileDiffResult](apperror.Wrap(pluginResult.AppError(), apperror.ErrDatabaseQuery, "plugin not found"))
	}
	pluginInfo := pluginResult.Value()

	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		return apperror.FailWrap[FileDiffResult](err, apperror.ErrDatabaseQuery, "site not found")
	}

	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return apperror.FailWrap[FileDiffResult](err, apperror.ErrDatabaseQuery, "mapping not found")
	}

	result := &FileDiffResult{Path: filePath}

	// Read local file
	localPath, err := pathutil.Join(pluginInfo.Path, filePath)
	if err != nil {
		return apperror.Fail[FileDiffResult](apperror.Wrap(err, apperror.ErrInternal, "failed to resolve local file path").WithFilePath(filePath))
	}
	localFile, err := os.Open(localPath)
	if err != nil {
		if !os.IsNotExist(err) {
			return apperror.FailWrap[FileDiffResult](err, apperror.ErrFSRead, "failed to read local file")
		}
		result.LocalContent = ""
	} else {
		defer localFile.Close()
		content, err := io.ReadAll(localFile)
		if err != nil {
			return apperror.FailWrap[FileDiffResult](err, apperror.ErrFSRead, "failed to read local file content")
		}
		result.LocalContent = string(content)
	}

	// Fetch remote file
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	remoteContent, err := wpClient.GetPluginFileContent(ctx, mapping.RemoteSlug, filePath)
	if err != nil {
		s.log.Debug("Could not fetch remote file content", "path", filePath, "error", err)
		result.RemoteContent = ""
	} else {
		result.RemoteContent = remoteContent
	}

	return apperror.Ok(*result)
}

// scanLocalFiles walks the plugin directory and returns file previews
func (s *Service) scanLocalFiles(pluginPath string, excludePatterns []string) (map[string]FilePreview, int64, error) {
	absPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return nil, 0, err
	}

	localFiles := make(map[string]FilePreview)
	var totalSize int64

	err = filepath.Walk(absPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}
		if info.IsDir() {
			for _, pattern := range excludePatterns {
				if strings.Contains(path, pattern) {
					return filepath.SkipDir
				}
			}
			return nil
		}

		relPath, err := filepath.Rel(absPath, path)
		if err != nil {
			return nil
		}

		for _, pattern := range excludePatterns {
			if matched, _ := filepath.Match(pattern, filepath.Base(path)); matched {
				return nil
			}
		}

		if strings.HasPrefix(filepath.Base(path), ".") {
			return nil
		}

		hash, _ := s.calculateFileHash(path)
		relPathSlash := filepath.ToSlash(relPath)

		localFiles[relPathSlash] = FilePreview{
			Path:      relPathSlash,
			Size:      info.Size(),
			LocalHash: hash,
		}
		totalSize += info.Size()
		return nil
	})

	return localFiles, totalSize, err
}

// fetchRemoteVersion tries to get the remote plugin version
func (s *Service) fetchRemoteVersion(wpClient *wordpress.Client, remoteSlug string) string {
	remotePlugins, err := wpClient.ListPluginsViaUploader()
	if err == nil {
		for _, rp := range remotePlugins {
			if rp.Slug == remoteSlug {
				return rp.Version
			}
		}
	}

	remotePluginInfo, err := wpClient.GetPlugin(remoteSlug)
	if err == nil && remotePluginInfo != nil {
		return remotePluginInfo.Version
	}

	return ""
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

// compareFiles compares local and remote files and returns the diff
func (s *Service) compareFiles(localFiles map[string]FilePreview, remoteFileMap map[string]string, fetchFailed bool) ([]FilePreview, int, int, int) {
	var files []FilePreview
	var added, modified, deleted int

	if fetchFailed {
		for _, localFile := range localFiles {
			localFile.ChangeType = "added"
			files = append(files, localFile)
			added++
		}
		return files, added, modified, deleted
	}

	for path, localFile := range localFiles {
		if remoteHash, exists := remoteFileMap[path]; exists {
			if localFile.LocalHash != remoteHash {
				localFile.ChangeType = "modified"
				modified++
			} else {
				continue
			}
			delete(remoteFileMap, path)
		} else {
			localFile.ChangeType = "added"
			added++
		}
		files = append(files, localFile)
	}

	for path := range remoteFileMap {
		files = append(files, FilePreview{
			Path:       path,
			ChangeType: "deleted",
			Size:       0,
		})
		deleted++
	}

	return files, added, modified, deleted
}
