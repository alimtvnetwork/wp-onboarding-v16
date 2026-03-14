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
)

// ImportResult contains information about an import operation
type ImportResult struct {
	ProjectSlug string
	FilesCount  int
	TotalBytes  int64
	Duration    time.Duration
}

// ImportProjectFromZip imports databases from a zip file
func (m *DBManager) ImportProjectFromZip(zipPath, projectSlug string, isOverwrite bool) (*ImportResult, *apperror.AppError) {
	startTime := time.Now()
	m.log.Info("Starting import", "zip", zipPath, "project", projectSlug, "overwrite", isOverwrite)

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

	prepErr := m.prepareImportDir(projectSlug, projectDir, isOverwrite)
	if prepErr != nil {
		return nil, prepErr
	}

	filesCount, totalBytes, extractErr := m.extractAllFiles(reader, projectDir)
	if extractErr != nil {
		return nil, extractErr
	}

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

// prepareImportDir validates and prepares the target directory.
func (m *DBManager) prepareImportDir(projectSlug, projectDir string, isOverwrite bool) *apperror.AppError {
	_, statErr := pathutil.StatDir(projectDir)
	isProjectExists := statErr == nil
	isReadOnly := !isOverwrite
	isConflict := isProjectExists && isReadOnly

	if isConflict {
		return apperror.New(apperror.ErrFSWrite, "project exists, use overwrite=true to replace").
			WithDetails(projectSlug)
	}

	m.mu.Lock()
	m.closeProjectDBs(projectSlug)
	m.mu.Unlock()

	if isOverwrite {
		appErr := pathutil.RemoveDir(projectDir, "projectDir")
		if appErr != nil {
			m.log.Warn("Failed to remove existing project directory", "error", appErr)
		}
	}

	mkdirErr := os.MkdirAll(projectDir, 0755)
	if mkdirErr != nil {
		return apperror.Wrap(mkdirErr, apperror.ErrFSWrite, "failed to create project directory").
			WithPath(projectDir)
	}

	return nil
}

// extractAllFiles extracts all files from the zip reader into projectDir.
func (m *DBManager) extractAllFiles(reader *zip.ReadCloser, projectDir string) (int, int64, *apperror.AppError) {
	var filesCount int
	var totalBytes int64

	for _, file := range reader.File {
		if file.FileInfo().IsDir() {
			continue
		}

		destPath, joinErr := pathutil.Join(projectDir, file.Name)
		if joinErr != nil {
			return 0, 0, apperror.Wrap(joinErr, apperror.ErrInternal, "failed to resolve import file path").WithFilePath(file.Name)
		}

		m.log.Debug("Extracting", "file", file.Name, "size", file.UncompressedSize64)

		mkdirErr := os.MkdirAll(filepath.Dir(destPath), 0755)
		if mkdirErr != nil {
			return 0, 0, apperror.Wrap(mkdirErr, apperror.ErrFSWrite, "failed to create directory").
				WithPath(destPath)
		}

		written, extractErr := m.extractZipFile(file, destPath)
		if extractErr != nil {
			return 0, 0, apperror.Wrap(extractErr, apperror.ErrFSWrite, "failed to extract file").
				WithFile(file.Name).
				WithDestPath(destPath)
		}

		filesCount++
		totalBytes += written
	}

	return filesCount, totalBytes, nil
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

		return m.registerSingleDatabase(path, project.Id, projectSlug)
	})

	if walkErr != nil {
		return apperror.Wrap(walkErr, apperror.ErrDatabaseInsert, "failed to walk project directory for registration")
	}

	return nil
}

// registerSingleDatabase registers one database file in root.db.
func (m *DBManager) registerSingleDatabase(path string, projectId int64, projectSlug string) error {
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

	var exists int
	m.rootDB.QueryRow(`SELECT 1 FROM Databases WHERE Path = ?`, relPath).Scan(&exists)
	isAlreadyRegistered := exists == 1

	if isAlreadyRegistered {
		return nil
	}

	info, statErr := os.Stat(path)
	if statErr != nil {
		return nil
	}

	_, execErr := m.rootDB.Exec(`
		INSERT INTO Databases (Id, ProjectId, Type, EntityId, Path, SizeBytes, Status, CreatedAt, UpdatedAt)
		VALUES (?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
	`, generateId(), projectId, dbType, entityId, relPath, info.Size())
	if execErr != nil {
		return apperror.Wrap(execErr, apperror.ErrDatabaseInsert, "failed to register imported database").
			WithDetails(fmt.Sprintf("path=%s, type=%s", relPath, dbType))
	}

	return nil
}
