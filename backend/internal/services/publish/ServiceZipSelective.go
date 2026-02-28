package publish

import (
	"archive/zip"
	"fmt"
	"path/filepath"
	"strings"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// createSelectiveZip creates a zip file with only selected files
func (s *Service) createSelectiveZip(pluginPath, pluginName string, files []string) (string, error) {
	zc, err := s.resolveZipContext(pluginPath, pluginName, "-patch")
	if err != nil {
		return "", err
	}

	zs, err := openZipSession(zc)
	if err != nil {
		return "", err
	}

	writeErr := s.writeSelectiveEntries(zs.Writer, zs.Ctx, files)
	if writeErr != nil {
		return zs.CleanupOnError(writeErr)
	}

	appErr := zs.Finalize()
	if appErr != nil {
		return "", appErr
	}
	return zc.AbsZipPath, nil
}

// writeSelectiveEntries adds each selected file to the zip.
func (s *Service) writeSelectiveEntries(zw *zip.Writer, zc *zipContext, files []string) error {
	for _, relPath := range files {
		err := addSelectiveEntry(zw, zc, relPath)
		if err != nil {
			return err
		}
	}
	return nil
}

// addSelectiveEntry adds a single file to the selective zip, skipping missing/directory entries.
func addSelectiveEntry(zw *zip.Writer, zc *zipContext, relPath string) error {
	fullPath, err := pathutil.Join(zc.AbsPluginPath, relPath)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to resolve file path").WithFilePath(relPath)
	}

	fi, statErr := pathutil.StatFile(fullPath)
	if statErr != nil {
		if apperror.Is(statErr, apperror.ErrFSNotFound) {
			return nil
		}

		return apperror.Wrap(statErr, apperror.ErrFSRead, "failed to stat file for selective zip").
			WithFilePath(relPath)
	}

	if fi.Info.IsDir() {
		return nil
	}

	return addFileToZip(zw, fullPath, filepath.ToSlash(filepath.Join(zc.Slug, relPath)))
}

// shouldExclude checks if a file should be excluded from the zip
func (s *Service) shouldExclude(relPath string) bool {
	if matchesDefaultExclusion(relPath) {
		return true
	}
	return hasHiddenPathSegment(relPath)
}

// matchesDefaultExclusion checks against the default exclusion list.
func matchesDefaultExclusion(relPath string) bool {
	excludePatterns := []string{
		".git", ".svn", ".DS_Store", "node_modules",
		".idea", ".vscode", "Thumbs.db", ".env", ".env.local",
	}

	for _, pattern := range excludePatterns {
		if strings.HasPrefix(relPath, pattern+string(filepath.Separator)) ||
			relPath == pattern ||
			strings.Contains(relPath, string(filepath.Separator)+pattern+string(filepath.Separator)) {
			return true
		}
	}
	return false
}

// hasHiddenPathSegment returns true if any path segment starts with a dot.
func hasHiddenPathSegment(relPath string) bool {
	parts := strings.Split(relPath, string(filepath.Separator))
	for _, part := range parts {
		if strings.HasPrefix(part, ".") && part != "." && part != ".." {
			return true
		}
	}
	return false
}

// getZipStructure reads the internal structure of a ZIP file and returns a list of paths
func (s *Service) getZipStructure(zipPath string) []string {
	absZipPath, err := pathutil.ToAbsolute(zipPath)
	if err != nil {
		s.log.Warn("Failed to resolve ZIP path", "zipPath", zipPath, "error", err.Error())
		absZipPath = zipPath
	}

	reader, err := zip.OpenReader(absZipPath)
	if err != nil {
		s.log.Warn("Failed to read ZIP structure", "zipPath", absZipPath, "error", err.Error())
		return []string{"(failed to read ZIP structure: " + err.Error() + ")"}
	}
	defer reader.Close()

	return formatZipEntries(reader)
}

// formatZipEntries formats each zip entry as "name (size bytes)".
func formatZipEntries(reader *zip.ReadCloser) []string {
	entries := make([]string, 0, len(reader.File))
	for _, file := range reader.File {
		suffix := ""
		if file.FileInfo().IsDir() {
			suffix = "/"
		}
		entries = append(entries, fmt.Sprintf("%s%s (%d bytes)", file.Name, suffix, file.UncompressedSize64))
	}
	return entries
}
