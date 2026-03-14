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

// ExportByType exports only specific database types
func (m *DBManager) ExportByType(projectSlug string, dbTypes []string, outputPath string) (*ExportResult, *apperror.AppError) {
	startTime := time.Now()
	m.log.Info("Selective export", "project", projectSlug, "types", dbTypes)

	dbs, appErr := m.ListDatabases(projectSlug)
	if appErr != nil {
		return nil, appErr
	}

	typeSet := make(map[string]bool)
	for _, t := range dbTypes {
		typeSet[t] = true
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

	filesCount, totalBytes := m.writeFilteredEntries(zipWriter, dbs, typeSet, projectSlug)

	duration := time.Since(startTime)

	return &ExportResult{
		OutputPath: outputPath,
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		Duration:   duration,
	}, nil
}

// writeFilteredEntries writes matching database files into the zip.
func (m *DBManager) writeFilteredEntries(zipWriter *zip.Writer, dbs []DatabaseInfo, typeSet map[string]bool, projectSlug string) (int, int64) {
	var filesCount int
	var totalBytes int64

	for _, db := range dbs {
		if !typeSet[db.Type] {
			continue
		}

		m.log.Debug("Including", "type", db.Type, "path", db.Path)

		fullPath, pathErr := pathutil.Join(m.dataDir, db.Path)
		if pathErr != nil {
			continue
		}
		relPath := strings.TrimPrefix(db.Path, projectSlug+"/")

		written := writeZipEntry(zipWriter, fullPath, relPath)
		if written > 0 {
			filesCount++
			totalBytes += written
		}
	}

	return filesCount, totalBytes
}

// writeZipEntry writes a single file into the zip and returns bytes written.
func writeZipEntry(zipWriter *zip.Writer, fullPath, relPath string) int64 {
	header := &zip.FileHeader{
		Name:   relPath,
		Method: zip.Deflate,
	}
	writer, headerErr := zipWriter.CreateHeader(header)
	if headerErr != nil {
		return 0
	}

	file, openErr := os.Open(fullPath)
	if openErr != nil {
		return 0
	}
	defer file.Close()

	written, copyErr := io.Copy(writer, file)
	if copyErr != nil {
		return 0
	}

	return written
}
