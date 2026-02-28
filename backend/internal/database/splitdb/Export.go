// Package splitdb provides import/export functionality for split databases
package splitdb

import (
	"archive/zip"
	"fmt"
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

// ImportResult contains information about an import operation
type ImportResult struct {
	ProjectSlug string
	FilesCount  int
	TotalBytes  int64
	Duration    time.Duration
}

// ExportProjectToZip creates a zip file of all project databases
func (m *DBManager) ExportProjectToZip(projectSlug, outputPath string) (*ExportResult, error) {
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

	// Ensure output directory exists
	err = os.MkdirAll(filepath.Dir(outputPath), 0755)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to create output directory").
			WithPath(outputPath)
	}

	// Create zip file
	zipFile, err := os.Create(outputPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip file").
			WithPath(outputPath)
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)
	defer zipWriter.Close()

	var filesCount int
	var totalBytes int64

	// Walk project directory and add all .db files
	err = filepath.Walk(projectDir, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			m.log.Warn("Skip file due to error", "path", path, "error", err)
			return nil // Continue walking
		}

		isSkippable := info.IsDir() || !strings.HasSuffix(path, ".db")
		if isSkippable {
			return nil
		}

		// Get relative path within project
		relPath, _ := filepath.Rel(projectDir, path)

		m.log.Debug("Adding to zip", "file", relPath, "size", info.Size())

		// Create zip entry with compression
		header := &zip.FileHeader{
			Name:     relPath,
			Method:   zip.Deflate,
			Modified: info.ModTime(),
		}
		writer, err := zipWriter.CreateHeader(header)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip entry").
				WithFile(relPath)
		}

		// Copy file content
		file, err := os.Open(path)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSRead, "failed to open file").
				WithPath(path)
		}
		defer file.Close()

		written, err := io.Copy(writer, file)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrFSZip, "failed to write to zip").
				WithFile(relPath)
		}

		filesCount++
		totalBytes += written

		return nil
	})

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSZip, "export failed").
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

// ImportProjectFromZip imports databases from a zip file
func (m *DBManager) ImportProjectFromZip(zipPath, projectSlug string, isOverwrite bool) (*ImportResult, error) {
	startTime := time.Now()
	m.log.Info("Starting import", "zip", zipPath, "project", projectSlug, "overwrite", isOverwrite)

	// Open zip file
	reader, err := zip.OpenReader(zipPath)
	if err != nil {
		m.log.Error("Failed to open zip", "error", err, "zip", zipPath)
		return nil, apperror.Wrap(err, apperror.ErrFSZip, "failed to open zip").
			WithPath(zipPath)
	}
	defer reader.Close()

	projectDir, err := pathutil.Join(m.dataDir, projectSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to resolve project directory")
	}

	// Check if project exists
	_, statErr := pathutil.StatDir(projectDir)
	isProjectExists := statErr == nil
	isReadOnly := !isOverwrite
	isConflict := isProjectExists && isReadOnly

	if isConflict {
		return nil, apperror.New(apperror.ErrFSWrite, "project exists, use overwrite=true to replace").
			WithDetails(projectSlug)
	}

	// Close any open databases for this project
	m.mu.Lock()
	m.closeProjectDBs(projectSlug)
	m.mu.Unlock()

	// Remove existing directory if overwriting
	if isOverwrite {
		err := os.RemoveAll(projectDir)
		if err != nil {
			m.log.Warn("Failed to remove existing project directory", "error", err)
		}
	}

	// Create project directory
	err = os.MkdirAll(projectDir, 0755)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to create project directory").
			WithPath(projectDir)
	}

	var filesCount int
	var totalBytes int64

	// Extract files
	for _, file := range reader.File {
		if file.FileInfo().IsDir() {
			continue
		}

		destPath, err := pathutil.Join(projectDir, file.Name)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to resolve import file path").WithFilePath(file.Name)
		}

		m.log.Debug("Extracting", "file", file.Name, "size", file.UncompressedSize64)

		// Create directory structure
		err = os.MkdirAll(filepath.Dir(destPath), 0755)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to create directory").
				WithPath(destPath)
		}

		// Extract file
		written, err := m.extractZipFile(file, destPath)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to extract file").
				WithFile(file.Name).
				WithDestPath(destPath)
		}

		filesCount++
		totalBytes += written
	}

	// Register databases in root.db
	err = m.registerImportedDatabases(projectSlug)
	if err != nil {
		m.log.Warn("Failed to register databases", "error", err)
	}

	duration := time.Since(startTime)
	m.log.Info("Import complete",
		"project", projectSlug,
		"filesCount", filesCount,
		"totalBytes", totalBytes,
		"durationMs", duration.Milliseconds(),
	)

	return &ImportResult{
		ProjectSlug: projectSlug,
		FilesCount:  filesCount,
		TotalBytes:  totalBytes,
		Duration:    duration,
	}, nil
}

// extractZipFile extracts a single file from a zip archive
func (m *DBManager) extractZipFile(file *zip.File, destPath string) (int64, error) {
	src, err := file.Open()
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrFSZip, "failed to open zip entry").
			WithFile(file.Name)
	}
	defer src.Close()

	dst, err := os.Create(destPath)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrFSWrite, "failed to create destination file").
			WithPath(destPath)
	}
	defer dst.Close()

	return io.Copy(dst, src)
}

// registerImportedDatabases scans and registers databases after import
func (m *DBManager) registerImportedDatabases(projectSlug string) error {
	// Ensure project exists in root.db
	project, err := m.getOrCreateProject(projectSlug)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to ensure project for import").
			WithSlug(projectSlug)
	}

	projectDir, err := pathutil.Join(m.dataDir, projectSlug)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to resolve project directory for import").
			WithSlug(projectSlug)
	}

	return filepath.Walk(projectDir, func(path string, info os.FileInfo, err error) error {
		isSkippable := err != nil || info.IsDir() || !strings.HasSuffix(path, ".db")
		if isSkippable {
			return nil
		}

		relPath, _ := filepath.Rel(m.dataDir, path)
		parts := strings.Split(relPath, string(os.PathSeparator))

		var dbType, entityID string
		if len(parts) >= 2 {
			dbType = strings.TrimSuffix(parts[1], ".db")
		}
		if len(parts) >= 3 {
			dbType = parts[1]
			entityID = strings.TrimSuffix(parts[2], ".db")
		}

		// Check if already registered
		var exists int
		m.rootDB.QueryRow(`SELECT 1 FROM Databases WHERE Path = ?`, relPath).Scan(&exists)
		if exists == 1 {
			return nil
		}

		// Register database
		_, err = m.rootDB.Exec(`
			INSERT INTO Databases (Id, ProjectId, Type, EntityId, Path, SizeBytes, Status, CreatedAt, UpdatedAt)
			VALUES (?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
		`, generateID(), project.ID, dbType, entityID, relPath, info.Size())
		if err != nil {
			return apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to register imported database").
				WithDetails(fmt.Sprintf("path=%s, type=%s", relPath, dbType))
		}

		return nil
	})
}

// ExportByType exports only specific database types
func (m *DBManager) ExportByType(projectSlug string, dbTypes []string, outputPath string) (*ExportResult, error) {
	startTime := time.Now()
	m.log.Info("Selective export", "project", projectSlug, "types", dbTypes)

	// Filter databases by type
	dbs, err := m.ListDatabases(projectSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list databases for export").
			WithSlug(projectSlug)
	}

	typeSet := make(map[string]bool)
	for _, t := range dbTypes {
		typeSet[t] = true
	}

	// Ensure output directory exists
	err = os.MkdirAll(filepath.Dir(outputPath), 0755)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSWrite, "failed to create output directory").
			WithPath(outputPath)
	}

	// Create zip with only matching types
	zipFile, err := os.Create(outputPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSZip, "failed to create zip file").
			WithPath(outputPath)
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)
	defer zipWriter.Close()

	var filesCount int
	var totalBytes int64

	for _, db := range dbs {
		if !typeSet[db.Type] {
			continue
		}

		m.log.Debug("Including", "type", db.Type, "path", db.Path)

		fullPath, err := pathutil.Join(m.dataDir, db.Path)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to resolve db path").WithPath(db.Path)
		}
		relPath := strings.TrimPrefix(db.Path, projectSlug+"/")

		header := &zip.FileHeader{
			Name:   relPath,
			Method: zip.Deflate,
		}
		writer, err := zipWriter.CreateHeader(header)
		if err != nil {
			continue
		}

		file, err := os.Open(fullPath)
		if err != nil {
			continue
		}

		written, err := io.Copy(writer, file)
		file.Close()

		if err == nil {
			filesCount++
			totalBytes += written
		}
	}

	duration := time.Since(startTime)
	return &ExportResult{
		OutputPath: outputPath,
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		Duration:   duration,
	}, nil
}
