package publish

import (
	"archive/zip"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/ziputil"
)

// zipContext holds resolved paths for zip creation.
type zipContext struct {
	AbsPluginPath string
	AbsZipPath    string
	Slug          string
}

// zipSession holds an open zip file and writer for building archives.
type zipSession struct {
	File   *os.File
	Writer *zip.Writer
	Ctx    *zipContext
}

// resolveZipContext resolves temp dir, plugin path, and zip file path.
func (s *Service) resolveZipContext(pluginPath, pluginName, suffix string) (*zipContext, error) {
	absTempDir, absPluginPath, err := resolveZipPaths(s.tempDir, pluginPath)
	if err != nil {
		return nil, err
	}

	slug := strings.ToLower(strings.ReplaceAll(pluginName, " ", "-"))
	absZipPath, err := pathutil.Join(absTempDir, fmt.Sprintf("%s%s.zip", slug, suffix))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to resolve zip path")
	}

	return &zipContext{AbsPluginPath: absPluginPath, AbsZipPath: absZipPath, Slug: slug}, nil
}

// resolveZipPaths resolves and ensures both the temp dir and plugin path exist.
func resolveZipPaths(tempDir, pluginPath string) (string, string, error) {
	absTempDir, err := pathutil.ToAbsolute(tempDir)
	if err != nil {
		return "", "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to resolve temp directory path")
	}
	if err := os.MkdirAll(absTempDir, 0755); err != nil {
		return "", "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp directory")
	}

	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return "", "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	return absTempDir, absPluginPath, nil
}

// openZipSession creates a zip file, writer, and registers best compression.
func openZipSession(zc *zipContext) (*zipSession, error) {
	f, err := os.Create(zc.AbsZipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to create zip file")
	}

	w := zip.NewWriter(f)
	ziputil.RegisterBestCompression(w)

	return &zipSession{File: f, Writer: w, Ctx: zc}, nil
}

// Finalize closes the writer and file, then validates the zip.
func (zs *zipSession) Finalize() *apperror.AppError {
	return finalizeZip(zs.Writer, zs.File, zs.Ctx.AbsZipPath)
}

// CleanupOnError closes and removes the zip file, returning a wrapped error.
func (zs *zipSession) CleanupOnError(err error) (string, error) {
	zs.Writer.Close()
	zs.File.Close()
	os.Remove(zs.Ctx.AbsZipPath)
	return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip archive")
}

// finalizeZip closes the zip writer and file, validates the result.
func finalizeZip(zw *zip.Writer, zf *os.File, absZipPath string) *apperror.AppError {
	if err := zw.Close(); err != nil {
		zf.Close()
		os.Remove(absZipPath)
		return apperror.Wrap(err, apperror.ErrFSZip, "failed to finalize zip archive")
	}
	if err := zf.Close(); err != nil {
		os.Remove(absZipPath)
		return apperror.Wrap(err, apperror.ErrFSWrite, "failed to close zip file")
	}
	return validateZipFile(absZipPath)
}

// validateZipFile checks the zip file exists and is non-empty.
func validateZipFile(absZipPath string) *apperror.AppError {
	fi, appErr := pathutil.StatFile(absZipPath)
	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrFSRead, "zip file not found after creation")
	}

	if fi.Info.Size() == 0 {
		os.Remove(absZipPath)

		return apperror.New(apperror.ErrFSZip, "zip file is empty after creation")
	}

	return nil
}

// createFullZip creates a zip file of the entire plugin directory
func (s *Service) createFullZip(pluginPath, pluginName string, excludePatterns []string) (string, error) {
	zc, err := s.resolveZipContext(pluginPath, pluginName, "")
	if err != nil {
		return "", err
	}

	zs, err := openZipSession(zc)
	if err != nil {
		return "", err
	}

	if walkErr := s.walkAndAddEntries(zs, excludePatterns); walkErr != nil {
		return zs.CleanupOnError(walkErr)
	}

	if appErr := zs.Finalize(); appErr != nil {
		return "", appErr
	}
	return zc.AbsZipPath, nil
}

// walkAndAddEntries walks the plugin directory and adds matching files to the zip.
func (s *Service) walkAndAddEntries(zs *zipSession, excludePatterns []string) error {
	return filepath.Walk(zs.Ctx.AbsPluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSRead, "failed to walk plugin directory").WithFilePath(path)
		}
		return s.addFullZipEntry(zs.Writer, zs.Ctx, path, info, excludePatterns)
	})
}

// addFullZipEntry adds a single file to the full zip, checking exclusions.
func (s *Service) addFullZipEntry(zw *zip.Writer, zc *zipContext, path string, info os.FileInfo, excludePatterns []string) error {
	if info.IsDir() {
		return nil
	}
	relPath, err := filepath.Rel(zc.AbsPluginPath, path)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to compute relative path").WithFilePath(path)
	}
	if isExcludedByPatterns(relPath, path, excludePatterns) || s.shouldExclude(relPath) {
		return nil
	}
	return addFileToZip(zw, path, filepath.ToSlash(filepath.Join(zc.Slug, relPath)))
}

// isExcludedByPatterns checks if a file matches any exclude pattern.
func isExcludedByPatterns(relPath, fullPath string, patterns []string) bool {
	for _, pattern := range patterns {
		if matched, _ := filepath.Match(pattern, filepath.Base(fullPath)); matched {
			return true
		}
		if strings.Contains(relPath, pattern) {
			return true
		}
	}
	return false
}

// addFileToZip writes a single file into the zip archive at the given entry path.
func addFileToZip(zw *zip.Writer, srcPath, zipEntryPath string) error {
	writer, err := zw.Create(zipEntryPath)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip entry").WithFilePath(zipEntryPath)
	}
	file, err := os.Open(srcPath)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrFSRead, "failed to open file for zipping").WithFilePath(srcPath)
	}
	defer file.Close()

	if _, err := io.Copy(writer, file); err != nil {
		return apperror.Wrap(err, apperror.ErrFSZip, "failed to copy file into zip").WithFilePath(srcPath)
	}
	return nil
}

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

	if err := s.writeSelectiveEntries(zs.Writer, zs.Ctx, files); err != nil {
		return zs.CleanupOnError(err)
	}

	if appErr := zs.Finalize(); appErr != nil {
		return "", appErr
	}
	return zc.AbsZipPath, nil
}

// writeSelectiveEntries adds each selected file to the zip.
func (s *Service) writeSelectiveEntries(zw *zip.Writer, zc *zipContext, files []string) error {
	for _, relPath := range files {
		if err := addSelectiveEntry(zw, zc, relPath); err != nil {
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
