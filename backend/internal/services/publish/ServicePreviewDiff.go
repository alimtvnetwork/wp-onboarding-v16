package publish

import (
	"os"
	"path/filepath"
	"strings"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/pathutil"
)

// scanContext holds the shared state for a file scanning walk.
type scanContext struct {
	AbsPath         string
	ExcludePatterns []string
	LocalFiles      map[string]FilePreview
	TotalSize       *int64
}

// localScanResult holds the output of scanning local plugin files.
type localScanResult struct {
	Files     map[string]FilePreview
	TotalSize int64
}

// scanLocalFiles walks the plugin directory and returns file previews
func (s *Service) scanLocalFiles(pluginPath string, excludePatterns []string) (*localScanResult, error) {
	absPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return nil, err
	}

	var totalSize int64
	sc := &scanContext{
		AbsPath:         absPath,
		ExcludePatterns: excludePatterns,
		LocalFiles:      make(map[string]FilePreview),
		TotalSize:       &totalSize,
	}

	err = filepath.Walk(absPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}
		return s.processScanEntry(sc, path, info)
	})

	if err != nil {
		return nil, err
	}
	return &localScanResult{Files: sc.LocalFiles, TotalSize: totalSize}, nil
}

// processScanEntry handles a single entry during local file scanning.
func (s *Service) processScanEntry(sc *scanContext, path string, info os.FileInfo) error {
	if info.IsDir() {
		return checkDirExclusion(path, sc.ExcludePatterns)
	}

	relPath, err := filepath.Rel(sc.AbsPath, path)
	if err != nil {
		return nil
	}

	if isExcludedFile(path, relPath, sc.ExcludePatterns) {
		return nil
	}

	hash := s.calculateFileHash(path).ValueOr("")
	relPathSlash := filepath.ToSlash(relPath)

	preview := FilePreview{
		Path:      relPathSlash,
		Size:      info.Size(),
		LocalHash: hash,
	}
	sc.LocalFiles[relPathSlash] = preview
	*sc.TotalSize += info.Size()
	return nil
}

// checkDirExclusion returns SkipDir if the directory matches an exclude pattern.
func checkDirExclusion(path string, patterns []string) error {
	for _, pattern := range patterns {
		if strings.Contains(path, pattern) {
			return filepath.SkipDir
		}
	}
	return nil
}

// isExcludedFile checks if a file should be excluded from scanning.
func isExcludedFile(path, relPath string, patterns []string) bool {
	for _, pattern := range patterns {
		matched, _ := filepath.Match(pattern, filepath.Base(path))
		if matched {
			return true
		}
	}
	return strings.HasPrefix(filepath.Base(path), ".")
}

// fetchRemoteVersion tries to get the remote plugin version
func (s *Service) fetchRemoteVersion(wpClient *wordpress.Client, remoteSlug string) string {
	pluginsResult := wpClient.ListPluginsViaUploader()
	if pluginsResult.IsSafe() {
		for _, rp := range pluginsResult.Value() {
			if rp.Slug == remoteSlug {
				return rp.Version
			}
		}
	}

	pluginResult := wpClient.GetPlugin(remoteSlug)
	if pluginResult.IsSafe() {
		return pluginResult.Value().Version
	}
	return ""
}

// FileDiffSummary holds the comparison result between local and remote files.
type FileDiffSummary struct {
	Files    []FilePreview
	Added    int
	Modified int
	Deleted  int
}

// compareFiles compares local and remote files and returns the diff
func (s *Service) compareFiles(localFiles map[string]FilePreview, remoteFileMap map[string]string, fetchFailed bool) FileDiffSummary {
	if fetchFailed {
		return markAllAsAdded(localFiles)
	}
	return diffLocalRemote(localFiles, remoteFileMap)
}

// markAllAsAdded returns all local files as "added".
func markAllAsAdded(localFiles map[string]FilePreview) FileDiffSummary {
	var files []FilePreview
	for _, lf := range localFiles {
		lf.ChangeType = "added"
		files = append(files, lf)
	}
	return FileDiffSummary{
		Files: files,
		Added: len(files),
	}
}

// diffLocalRemote compares local files against remote hashes.
func diffLocalRemote(localFiles map[string]FilePreview, remoteFileMap map[string]string) FileDiffSummary {
	var files []FilePreview
	var added, modified, deleted int

	for path, lf := range localFiles {
		remoteHash, isFound := remoteFileMap[path]
		if isFound {
			if lf.LocalHash != remoteHash {
				lf.ChangeType = "modified"
				modified++
				files = append(files, lf)
			}
			delete(remoteFileMap, path)
		} else {
			lf.ChangeType = "added"
			added++
			files = append(files, lf)
		}
	}

	for path := range remoteFileMap {
		deletedFile := FilePreview{
			Path:       path,
			ChangeType: "deleted",
		}
		files = append(files, deletedFile)
		deleted++
	}

	return FileDiffSummary{
		Files:    files,
		Added:    added,
		Modified: modified,
		Deleted:  deleted,
	}
}
