// Package site — ZIP creation for uploader bootstrap
package site

import (
	"archive/zip"
	"io"
	"os"
	"path/filepath"
	"strings"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/ziputil"
)

// createUploaderZip creates a ZIP file of the uploader plugin
func (s *Service) createUploaderZip(uploaderPath string) (string, *apperror.AppError) {
	absUploaderPath, resolveErr := resolveUploaderDir(uploaderPath)
	if resolveErr != nil {
		return "", resolveErr
	}

	tempFile, err := os.CreateTemp("", "riseup-asia-uploader-*.zip")
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp file for uploader ZIP")
	}
	tempPath := tempFile.Name()

	writeErr := writeUploaderZipContent(tempFile, absUploaderPath)
	if writeErr != nil {
		pathutil.RemoveFileUnchecked(tempPath)

		return "", writeErr
	}

	return tempPath, nil
}

// resolveUploaderDir validates and returns the absolute path to the uploader directory.
func resolveUploaderDir(uploaderPath string) (string, *apperror.AppError) {
	absPath, err := pathutil.ToAbsolute(uploaderPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve uploader path").WithPath(uploaderPath)
	}

	info, err := os.Stat(absPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSNotFound, "uploader path not found").WithPath(pathutil.ForDisplay(absPath))
	}

	isDirectory := info.IsDir()
	isFile := !isDirectory

	if isFile {
		return "", apperror.New(apperror.ErrFSInvalid, "uploader path is not a directory").WithPath(pathutil.ForDisplay(absPath))
	}

	return absPath, nil
}

// writeUploaderZipContent walks the uploader directory and writes files into the ZIP.
func writeUploaderZipContent(tempFile *os.File, absUploaderPath string) *apperror.AppError {
	zipWriter := zip.NewWriter(tempFile)
	ziputil.RegisterBestCompression(zipWriter)

	baseName := filepath.Base(absUploaderPath)
	err := filepath.Walk(absUploaderPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}

		return addFileToUploaderZip(uploaderZipEntryInput{
			Writer: zipWriter, BaseDir: absUploaderPath, BaseName: baseName, Path: path, Info: info,
		})
	})

	zipWriter.Close()
	tempFile.Close()

	if err != nil {
		return apperror.Wrap(err, apperror.ErrFSZip, "failed to walk uploader directory")
	}

	return nil
}

// uploaderZipEntryInput bundles parameters for addFileToUploaderZip.
type uploaderZipEntryInput struct {
	Writer   *zip.Writer
	BaseDir  string
	BaseName string
	Path     string
	Info     os.FileInfo
}

// addFileToUploaderZip adds a single file entry to the uploader ZIP archive.
func addFileToUploaderZip(input uploaderZipEntryInput) error {
	relPath, _ := filepath.Rel(input.BaseDir, input.Path)
	isRootEntry := relPath == "."

	if isRootEntry {
		return nil
	}

	if shouldSkipFile(relPath) {
		return resolveSkipResult(input.Info)
	}

	if input.Info.IsDir() {
		return nil
	}

	zipPath := input.BaseName + "/" + filepath.ToSlash(relPath)

	return copyFileToZipEntry(input.Writer, zipPath, input.Path)
}

// resolveSkipResult returns SkipDir for directories, nil for files.
func resolveSkipResult(info os.FileInfo) error {
	if info.IsDir() {
		return filepath.SkipDir
	}

	return nil
}

// copyFileToZipEntry copies a single file into a ZIP archive entry.
func copyFileToZipEntry(zipWriter *zip.Writer, zipPath, filePath string) error {
	writer, err := zipWriter.Create(zipPath)
	if err != nil {
		return err
	}

	file, err := os.Open(filePath)
	if err != nil {
		return err
	}
	defer file.Close()

	_, err = io.Copy(writer, file)

	return err
}

// shouldSkipFile checks if a file should be skipped when creating the uploader ZIP
func shouldSkipFile(relPath string) bool {
	relPath = filepath.ToSlash(relPath)

	if hasHiddenSegment(relPath) {
		return true
	}

	return matchesSkipPattern(relPath)
}

// hasHiddenSegment checks if any path segment starts with a dot (except .uploadignore).
func hasHiddenSegment(relPath string) bool {
	parts := strings.Split(relPath, "/")
	for _, part := range parts {
		if strings.HasPrefix(part, ".") && part != ".uploadignore" {
			return true
		}
	}

	return false
}

// matchesSkipPattern checks if a path matches any skip pattern.
func matchesSkipPattern(relPath string) bool {
	skipPatterns := []string{"node_modules", "vendor", "tests", "phpunit.xml", "phpunit.xml.dist", "composer.lock"}
	for _, pattern := range skipPatterns {
		if relPath == pattern || strings.HasPrefix(relPath, pattern+"/") {
			return true
		}
	}

	return false
}
