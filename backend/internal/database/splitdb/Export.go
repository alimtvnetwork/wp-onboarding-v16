// Package splitdb provides import/export functionality for split databases
package splitdb

import (
	"archive/zip"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/ziputil"
)

// ExportResult contains information about an export operation
type ExportResult struct {
	OutputPath string
	FilesCount int
	TotalBytes int64
	Duration   time.Duration
}

// ExportProjectToZip creates a zip file of all project databases
func (m *DBManager) ExportProjectToZip(projectSlug, outputPath string) (*ExportResult, *apperror.AppError) {
	startTime := time.Now()
	m.log.Info("Starting export", "project", projectSlug, "output", outputPath)

	projectDir, err := pathutil.Join(m.dataDir, projectSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to resolve project directory")
	}
	_, statErr := pathutil.StatDir(projectDir)
	isProjectMissing := statErr != nil

	if isProjectMissing {
		return nil, apperror.New(apperror.ErrNotFound, "project not found").
			WithDetails(projectSlug)
	}

	mkdirErr := os.MkdirAll(filepath.Dir(outputPath), 0755)
	if mkdirErr != nil {
		return nil, apperror.Wrap(mkdirErr, apperror.ErrFSWrite, "failed to create output directory").
			WithPath(outputPath)
	}

	zipFile, createErr := os.Create(outputPath)
	if createErr != nil {
		return nil, apperror.Wrap(createErr, apperror.ErrFSZip, "failed to create zip file").
			WithPath(outputPath)
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)
	defer zipWriter.Close()

	filesCount, totalBytes, walkErr := walkAndZipProject(zipWriter, projectDir, m)
	if walkErr != nil {
		return nil, apperror.Wrap(walkErr, apperror.ErrFSZip, "export failed").
			WithDetails(projectSlug)
	}

	duration := time.Since(startTime)
	m.log.Info("Export complete",
		"project", projectSlug,
		"output", outputPath,
		"filesCount", filesCount,
		"totalBytes", totalBytes,
		"durationMs", duration.Milliseconds(),
	)

	return &ExportResult{
		OutputPath: outputPath,
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		Duration:   duration,
	}, nil
}

// walkAndZipProject walks the project directory and adds all .db files to the zip.
func walkAndZipProject(zipWriter *zip.Writer, projectDir string, m *DBManager) (int, int64, error) {
	var filesCount int
	var totalBytes int64

	walkErr := filepath.Walk(projectDir, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			m.log.Warn("Skip file due to error", "path", path, "error", err)
			return nil
		}

		isSkippable := info.IsDir() || !strings.HasSuffix(path, ".db")
		if isSkippable {
			return nil
		}

		relPath, _ := filepath.Rel(projectDir, path)
		m.log.Debug("Adding to zip", "file", relPath, "size", info.Size())

		written, addErr := addFileToZip(zipWriter, path, relPath, info)
		if addErr != nil {
			return addErr
		}

		filesCount++
		totalBytes += written

		return nil
	})

	return filesCount, totalBytes, walkErr
}

// addFileToZip adds a single file to the zip writer.
func addFileToZip(zipWriter *zip.Writer, path, relPath string, info os.FileInfo) (int64, error) {
	header := &zip.FileHeader{
		Name:     relPath,
		Method:   zip.Deflate,
		Modified: info.ModTime(),
	}
	writer, headerErr := zipWriter.CreateHeader(header)
	if headerErr != nil {
		return 0, apperror.Wrap(headerErr, apperror.ErrFSZip, "failed to create zip entry").
			WithFile(relPath)
	}

	file, openErr := os.Open(path)
	if openErr != nil {
		return 0, apperror.Wrap(openErr, apperror.ErrFSRead, "failed to open file").
			WithPath(path)
	}
	defer file.Close()

	written, copyErr := io.Copy(writer, file)
	if copyErr != nil {
		return 0, apperror.Wrap(copyErr, apperror.ErrFSZip, "failed to write to zip").
			WithFile(relPath)
	}

	return written, nil
}
