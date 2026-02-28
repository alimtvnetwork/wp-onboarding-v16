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
	File            *os.File
	Writer          *zip.Writer
	Ctx             *zipContext
	ExcludePatterns []string
}

// resolveZipContext resolves temp dir, plugin path, and zip file path.
func (s *Service) resolveZipContext(pluginPath, pluginName, suffix string) (*zipContext, error) {
	paths, err := resolveZipPaths(s.tempDir, pluginPath)
	if err != nil {
		return nil, err
	}

	slug := strings.ToLower(strings.ReplaceAll(pluginName, " ", "-"))
	absZipPath, err := pathutil.Join(paths.TempDir, fmt.Sprintf("%s%s.zip", slug, suffix))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to resolve zip path")
	}

	zc := &zipContext{
		AbsPluginPath: paths.PluginPath,
		AbsZipPath:    absZipPath,
		Slug:          slug,
	}
	return zc, nil
}

// resolvedPaths holds resolved absolute paths for zip operations.
type resolvedPaths struct {
	TempDir    string
	PluginPath string
}

// resolveZipPaths resolves and ensures both the temp dir and plugin path exist.
func resolveZipPaths(tempDir, pluginPath string) (*resolvedPaths, error) {
	absTempDir, err := pathutil.ToAbsolute(tempDir)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to resolve temp directory path")
	}
	mkdirErr := os.MkdirAll(absTempDir, 0755)
	if mkdirErr != nil {
		return nil, apperror.Wrap(mkdirErr, apperror.ErrFSWrite, "failed to create temp directory")
	}

	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	return &resolvedPaths{TempDir: absTempDir, PluginPath: absPluginPath}, nil
}

// openZipSession creates a zip file, writer, and registers best compression.
func openZipSession(zc *zipContext) (*zipSession, error) {
	f, err := os.Create(zc.AbsZipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to create zip file")
	}

	w := zip.NewWriter(f)
	ziputil.RegisterBestCompression(w)

	zs := &zipSession{
		File:   f,
		Writer: w,
		Ctx:    zc,
	}
	return zs, nil
}

// Finalize closes the writer and file, then validates the zip.
func (zs *zipSession) Finalize() *apperror.AppError {
	return finalizeZip(zs.Writer, zs.File, zs.Ctx.AbsZipPath)
}

// CleanupOnError closes and removes the zip file, returning a wrapped error.
func (zs *zipSession) CleanupOnError(err error) (string, error) {
	zs.Writer.Close()
	zs.File.Close()
	pathutil.RemoveFileUnchecked(zs.Ctx.AbsZipPath)

	return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip archive")
}

// finalizeZip closes the zip writer and file, validates the result.
func finalizeZip(zw *zip.Writer, zf *os.File, absZipPath string) *apperror.AppError {
	zwErr := zw.Close()
	if zwErr != nil {
		zf.Close()
		pathutil.RemoveFileUnchecked(absZipPath)

		return apperror.Wrap(zwErr, apperror.ErrFSZip, "failed to finalize zip archive")
	}

	zfErr := zf.Close()
	if zfErr != nil {
		pathutil.RemoveFileUnchecked(absZipPath)

		return apperror.Wrap(zfErr, apperror.ErrFSWrite, "failed to close zip file")
	}

	return validateZipFile(absZipPath)
}

// validateZipFile checks the zip file exists and is non-empty.
func validateZipFile(absZipPath string) *apperror.AppError {
	fi, appErr := pathutil.StatFile(absZipPath)
	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrFSRead, "zip file not found after creation")
	}

	isEmpty := fi.Info.Size() == 0

	if isEmpty {
		pathutil.RemoveFileUnchecked(absZipPath)

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

	zs.ExcludePatterns = excludePatterns
	walkErr := s.walkAndAddEntries(zs)
	if walkErr != nil {
		return zs.CleanupOnError(walkErr)
	}

	appErr := zs.Finalize()
	if appErr != nil {
		return "", appErr
	}
	return zc.AbsZipPath, nil
}

// walkAndAddEntries walks the plugin directory and adds matching files to the zip.
func (s *Service) walkAndAddEntries(zs *zipSession) error {
	return filepath.Walk(zs.Ctx.AbsPluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSRead, "failed to walk plugin directory").WithFilePath(path)
		}
		return s.addFullZipEntry(zs, path, info)
	})
}

// addFullZipEntry adds a single file to the full zip, checking exclusions.
func (s *Service) addFullZipEntry(zs *zipSession, path string, info os.FileInfo) error {
	if info.IsDir() {
		return nil
	}
	relPath, err := filepath.Rel(zs.Ctx.AbsPluginPath, path)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to compute relative path").WithFilePath(path)
	}
	if isExcludedByPatterns(relPath, path, zs.ExcludePatterns) || s.shouldExclude(relPath) {
		return nil
	}
	return addFileToZip(zs.Writer, path, filepath.ToSlash(filepath.Join(zs.Ctx.Slug, relPath)))
}

// isExcludedByPatterns checks if a file matches any exclude pattern.
func isExcludedByPatterns(relPath, fullPath string, patterns []string) bool {
	for _, pattern := range patterns {
		matched, _ := filepath.Match(pattern, filepath.Base(fullPath))
		if matched {
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

	_, copyErr := io.Copy(writer, file)
	if copyErr != nil {
		return apperror.Wrap(copyErr, apperror.ErrFSZip, "failed to copy file into zip").WithFilePath(srcPath)
	}
	return nil
}
