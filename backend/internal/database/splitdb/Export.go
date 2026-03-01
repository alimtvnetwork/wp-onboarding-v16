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

	// Ensure output directory exists
	mkdirErr := os.MkdirAll(filepath.Dir(outputPath), 0755)
	if mkdirErr != nil {
		return nil, apperror.Wrap(mkdirErr, apperror.ErrFSWrite, "failed to create output directory").
			WithPath(outputPath)
	}

	// Create zip file
	zipFile, createErr := os.Create(outputPath)
	if createErr != nil {
		return nil, apperror.Wrap(createErr, apperror.ErrFSZip, "failed to create zip file").
			WithPath(outputPath)
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)
	defer zipWriter.Close()

	var filesCount int
	var totalBytes int64

	// Walk project directory and add all .db files
	walkErr := filepath.Walk(projectDir, func(path string, info os.FileInfo, err error) error {
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
		writer, headerErr := zipWriter.CreateHeader(header)
		if headerErr != nil {
			return apperror.Wrap(headerErr, apperror.ErrFSZip, "failed to create zip entry").
				WithFile(relPath)
		}

		// Copy file content
		file, openErr := os.Open(path)
		if openErr != nil {
			return apperror.Wrap(openErr, apperror.ErrFSRead, "failed to open file").
				WithPath(path)
		}
		defer file.Close()

		written, copyErr := io.Copy(writer, file)
		if copyErr != nil {
			return apperror.Wrap(copyErr, apperror.ErrFSZip, "failed to write to zip").
				WithFile(relPath)
		}

		filesCount++
		totalBytes += written

		return nil
	})

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

// ImportProjectFromZip imports databases from a zip file
func (m *DBManager) ImportProjectFromZip(zipPath, projectSlug string, isOverwrite bool) (*ImportResult, *apperror.AppError) {
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

	projectDir, pathErr := pathutil.Join(m.dataDir, projectSlug)
	if pathErr != nil {
		return nil, apperror.Wrap(pathErr, apperror.ErrInternal, "failed to resolve project directory")
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
		appErr := pathutil.RemoveDir(projectDir, "projectDir")
		if appErr != nil {
			m.log.Warn("Failed to remove existing project directory", "error", appErr)
		}
	}

	// Create project directory
	mkdirErr := os.MkdirAll(projectDir, 0755)
	if mkdirErr != nil {
		return nil, apperror.Wrap(mkdirErr, apperror.ErrFSWrite, "failed to create project directory").
			WithPath(projectDir)
	}

	var filesCount int
	var totalBytes int64

	// Extract files
	for _, file := range reader.File {
		if file.FileInfo().IsDir() {
			continue
		}

		destPath, joinErr := pathutil.Join(projectDir, file.Name)
		if joinErr != nil {
			return nil, apperror.Wrap(joinErr, apperror.ErrInternal, "failed to resolve import file path").WithFilePath(file.Name)
		}

		m.log.Debug("Extracting", "file", file.Name, "size", file.UncompressedSize64)

		// Create directory structure
		mkdirErr := os.MkdirAll(filepath.Dir(destPath), 0755)
		if mkdirErr != nil {
			return nil, apperror.Wrap(mkdirErr, apperror.ErrFSWrite, "failed to create directory").
				WithPath(destPath)
		}

		// Extract file
		written, extractErr := m.extractZipFile(file, destPath)
		if extractErr != nil {
			return nil, apperror.Wrap(extractErr, apperror.ErrFSWrite, "failed to extract file").
				WithFile(file.Name).
				WithDestPath(destPath)
		}

		filesCount++
		totalBytes += written
	}

	// Register databases in root.db
	regErr := m.registerImportedDatabases(projectSlug)
	if regErr != nil {
		m.log.Warn("Failed to register databases", "error", regErr)
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
func (m *DBManager) extractZipFile(file *zip.File, destPath string) (int64, *apperror.AppError) {
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

	written, err := io.Copy(dst, src)
	if err != nil {
		return 0, apperror.Wrap(err, apperror.ErrFSZip, "failed to copy zip entry content").
			WithFile(file.Name)
	}

	return written, nil
}

// registerImportedDatabases scans and registers databases after import
func (m *DBManager) registerImportedDatabases(projectSlug string) *apperror.AppError {
	// Ensure project exists in root.db
	project, appErr := m.getOrCreateProject(projectSlug)
	if appErr != nil {
		return apperror.Wrap(appErr, apperror.ErrDatabaseInsert, "failed to ensure project for import").
			WithSlug(projectSlug)
	}

	projectDir, pathErr := pathutil.Join(m.dataDir, projectSlug)
	if pathErr != nil {
		return apperror.Wrap(pathErr, apperror.ErrInternal, "failed to resolve project directory for import").
			WithSlug(projectSlug)
	}

	walkErr := filepath.Walk(projectDir, func(path string, info os.FileInfo, err error) error {
		isSkippable := err != nil || info.IsDir() || !strings.HasSuffix(path, ".db")
		if isSkippable {
			return nil
		}

		relPath, _ := filepath.Rel(m.dataDir, path)
		parts := strings.Split(relPath, string(os.PathSeparator))

		var dbType, entityId string
		if len(parts) >= 2 {
			dbType = strings.TrimSuffix(parts[1], ".db")
		}
		if len(parts) >= 3 {
			dbType = parts[1]
			entityId = strings.TrimSuffix(parts[2], ".db")
		}

		// Check if already registered
		var exists int
		m.rootDB.QueryRow(`SELECT 1 FROM Databases WHERE Path = ?`, relPath).Scan(&exists)
		isAlreadyRegistered := exists == 1

		if isAlreadyRegistered {
			return nil
		}

		// Register database
		_, execErr := m.rootDB.Exec(`
			INSERT INTO Databases (Id, ProjectId, Type, EntityId, Path, SizeBytes, Status, CreatedAt, UpdatedAt)
			VALUES (?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
		`, generateId(), project.Id, dbType, entityId, relPath, info.Size())
		if execErr != nil {
			return apperror.Wrap(execErr, apperror.ErrDatabaseInsert, "failed to register imported database").
				WithDetails(fmt.Sprintf("path=%s, type=%s", relPath, dbType))
		}

		return nil
	})

	if walkErr != nil {
		return apperror.Wrap(walkErr, apperror.ErrDatabaseInsert, "failed to walk project directory for registration")
	}

	return nil
}

// ExportByType exports only specific database types
func (m *DBManager) ExportByType(projectSlug string, dbTypes []string, outputPath string) (*ExportResult, *apperror.AppError) {
	startTime := time.Now()
	m.log.Info("Selective export", "project", projectSlug, "types", dbTypes)

	// Filter databases by type
	dbs, appErr := m.ListDatabases(projectSlug)
	if appErr != nil {
		return nil, appErr
	}

	typeSet := make(map[string]bool)
	for _, t := range dbTypes {
		typeSet[t] = true
	}

	// Ensure output directory exists
	mkdirErr := os.MkdirAll(filepath.Dir(outputPath), 0755)
	if mkdirErr != nil {
		return nil, apperror.Wrap(mkdirErr, apperror.ErrFSWrite, "failed to create output directory").
			WithPath(outputPath)
	}

	// Create zip with only matching types
	zipFile, createErr := os.Create(outputPath)
	if createErr != nil {
		return nil, apperror.Wrap(createErr, apperror.ErrFSZip, "failed to create zip file").
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

		fullPath, pathErr := pathutil.Join(m.dataDir, db.Path)
		if pathErr != nil {
			return nil, apperror.Wrap(pathErr, apperror.ErrInternal, "failed to resolve db path").WithPath(db.Path)
		}
		relPath := strings.TrimPrefix(db.Path, projectSlug+"/")

		header := &zip.FileHeader{
			Name:   relPath,
			Method: zip.Deflate,
		}
		writer, headerErr := zipWriter.CreateHeader(header)
		if headerErr != nil {
			continue
		}

		file, openErr := os.Open(fullPath)
		if openErr != nil {
			continue
		}

		written, copyErr := io.Copy(writer, file)
		file.Close()

		if copyErr == nil {
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
