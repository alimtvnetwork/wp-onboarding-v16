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

// createFullZip creates a zip file of the entire plugin directory
func (s *Service) createFullZip(pluginPath, pluginName string, excludePatterns []string) (string, error) {
	absTempDir, err := pathutil.ToAbsolute(s.tempDir)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to resolve temp directory path")
	}
	if err := os.MkdirAll(absTempDir, 0755); err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp directory")
	}

	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	slug := strings.ToLower(strings.ReplaceAll(pluginName, " ", "-"))

	absZipPath, err := pathutil.Join(absTempDir, fmt.Sprintf("%s.zip", slug))
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to resolve zip path")
	}
	zipFile, err := os.Create(absZipPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create zip file")
	}

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)

	err = filepath.Walk(absPluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSRead, "failed to walk plugin directory").
				WithFilePath(path)
		}
		if info.IsDir() {
			return nil
		}

		relPath, err := filepath.Rel(absPluginPath, path)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrInternal, "failed to compute relative path").
				WithFilePath(path)
		}

		// Check exclude patterns
		for _, pattern := range excludePatterns {
			if matched, _ := filepath.Match(pattern, filepath.Base(path)); matched {
				return nil
			}
			if strings.Contains(relPath, pattern) {
				return nil
			}
		}

		if s.shouldExclude(relPath) {
			return nil
		}

		zipEntryPath := filepath.ToSlash(filepath.Join(slug, relPath))
		writer, err := zipWriter.Create(zipEntryPath)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip entry").
				WithFilePath(zipEntryPath)
		}

		file, err := os.Open(path)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSRead, "failed to open file for zipping").
				WithFilePath(path)
		}
		defer file.Close()

		_, err = io.Copy(writer, file)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSZip, "failed to copy file into zip").
				WithFilePath(relPath)
		}
		return nil
	})

	if err != nil {
		zipWriter.Close()
		zipFile.Close()
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip archive")
	}

	if err := zipWriter.Close(); err != nil {
		zipFile.Close()
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to finalize zip archive")
	}
	if err := zipFile.Close(); err != nil {
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to close zip file")
	}

	if info, statErr := os.Stat(absZipPath); statErr != nil {
		return "", apperror.Wrap(statErr, apperror.ErrFSRead, "zip file not found after creation")
	} else if info.Size() == 0 {
		os.Remove(absZipPath)
		return "", apperror.New(apperror.ErrFSZip, "zip file is empty after creation")
	}

	return absZipPath, nil
}

// createSelectiveZip creates a zip file with only selected files
func (s *Service) createSelectiveZip(pluginPath, pluginName string, files []string) (string, error) {
	absTempDir, err := pathutil.ToAbsolute(s.tempDir)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to resolve temp directory path")
	}
	if err := os.MkdirAll(absTempDir, 0755); err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp directory")
	}

	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	slug := strings.ToLower(strings.ReplaceAll(pluginName, " ", "-"))

	absZipPath, err := pathutil.Join(absTempDir, fmt.Sprintf("%s-patch.zip", slug))
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to resolve patch zip path")
	}
	zipFile, err := os.Create(absZipPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create zip file")
	}

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)

	for _, relPath := range files {
		fullPath, err := pathutil.Join(absPluginPath, relPath)
		if err != nil {
			return "", apperror.Wrap(err, apperror.ErrInternal, "failed to resolve file path").WithFilePath(relPath)
		}

		info, err := os.Stat(fullPath)
		if err != nil {
			if os.IsNotExist(err) {
				continue
			}
			return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to stat file for selective zip").
				WithFilePath(relPath)
		}
		if info.IsDir() {
			continue
		}

		zipFilePath := filepath.ToSlash(filepath.Join(slug, relPath))
		writer, err := zipWriter.Create(zipFilePath)
		if err != nil {
			zipWriter.Close()
			zipFile.Close()
			os.Remove(absZipPath)
			return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip entry").
				WithFilePath(zipFilePath)
		}

		file, err := os.Open(fullPath)
		if err != nil {
			zipWriter.Close()
			zipFile.Close()
			os.Remove(absZipPath)
			return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to open file for selective zip").
				WithFilePath(relPath)
		}

		_, copyErr := io.Copy(writer, file)
		file.Close()
		if copyErr != nil {
			zipWriter.Close()
			zipFile.Close()
			os.Remove(absZipPath)
			return "", apperror.Wrap(copyErr, apperror.ErrFSZip, "failed to copy file into selective zip").
				WithFilePath(relPath)
		}
	}

	if err := zipWriter.Close(); err != nil {
		zipFile.Close()
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to finalize zip archive")
	}
	if err := zipFile.Close(); err != nil {
		os.Remove(absZipPath)
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to close zip file")
	}

	if info, statErr := os.Stat(absZipPath); statErr != nil {
		return "", apperror.Wrap(statErr, apperror.ErrFSRead, "zip file not found after creation")
	} else if info.Size() == 0 {
		os.Remove(absZipPath)
		return "", apperror.New(apperror.ErrFSZip, "zip file is empty after creation")
	}

	return absZipPath, nil
}

// shouldExclude checks if a file should be excluded from the zip
func (s *Service) shouldExclude(relPath string) bool {
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
	var entries []string

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

	for _, file := range reader.File {
		suffix := ""
		if file.FileInfo().IsDir() {
			suffix = "/"
		}
		entries = append(entries, fmt.Sprintf("%s%s (%d bytes)", file.Name, suffix, file.UncompressedSize64))
	}

	return entries
}
