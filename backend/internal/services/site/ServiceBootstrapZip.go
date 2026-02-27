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
func (s *Service) createUploaderZip(uploaderPath string) (string, error) {
	absUploaderPath, err := resolveUploaderDir(uploaderPath)
	if err != nil {
		return "", err
	}

	tempFile, err := os.CreateTemp("", "riseup-asia-uploader-*.zip")
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSWrite, "failed to create temp file for uploader ZIP")
	}
	tempPath := tempFile.Name()

	if err := writeUploaderZipContent(tempFile, absUploaderPath); err != nil {
		os.Remove(tempPath)
		return "", apperror.Wrap(err, apperror.ErrFSZip, "failed to create uploader ZIP").WithPath(pathutil.ForDisplay(absUploaderPath))
	}

	return tempPath, nil
}

// resolveUploaderDir validates and returns the absolute path to the uploader directory.
func resolveUploaderDir(uploaderPath string) (string, error) {
	absPath, err := pathutil.ToAbsolute(uploaderPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve uploader path").WithPath(uploaderPath)
	}

	info, err := os.Stat(absPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSNotFound, "uploader path not found").WithPath(pathutil.ForDisplay(absPath))
	}
	if !info.IsDir() {
		return "", apperror.New(apperror.ErrFSInvalid, "uploader path is not a directory").WithPath(pathutil.ForDisplay(absPath))
	}

	return absPath, nil
}

// writeUploaderZipContent walks the uploader directory and writes files into the ZIP.
func writeUploaderZipContent(tempFile *os.File, absUploaderPath string) error {
	zipWriter := zip.NewWriter(tempFile)
	ziputil.RegisterBestCompression(zipWriter)

	baseName := filepath.Base(absUploaderPath)
	err := filepath.Walk(absUploaderPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		return addFileToUploaderZip(uploaderZipEntryInput{Writer: zipWriter, BaseDir: absUploaderPath, BaseName: baseName, Path: path, Info: info})
	})

	zipWriter.Close()
	tempFile.Close()

	return err
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
	if relPath == "." {
		return nil
	}
	if shouldSkipFile(relPath) {
		if input.Info.IsDir() {
			return filepath.SkipDir
		}
		return nil
	}
	if input.Info.IsDir() {
		return nil
	}

	zipPath := input.BaseName + "/" + filepath.ToSlash(relPath)
	writer, err := input.Writer.Create(zipPath)
	if err != nil {
		return err
	}

	file, err := os.Open(input.Path)
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
	parts := strings.Split(relPath, "/")
	for _, part := range parts {
		if strings.HasPrefix(part, ".") && part != ".uploadignore" {
			return true
		}
	}
	skipPatterns := []string{"node_modules", "vendor", "tests", "phpunit.xml", "phpunit.xml.dist", "composer.lock"}
	for _, pattern := range skipPatterns {
		if relPath == pattern || strings.HasPrefix(relPath, pattern+"/") {
			return true
		}
	}
	return false
}
