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

	projectDir := filepath.Join(m.dataDir, projectSlug)
	if _, err := os.Stat(projectDir); os.IsNotExist(err) {
		return nil, fmt.Errorf("project not found: %s", projectSlug)
	}

	// Ensure output directory exists
	if err := os.MkdirAll(filepath.Dir(outputPath), 0755); err != nil {
		return nil, fmt.Errorf("failed to create output directory: %w", err)
	}

	// Create zip file
	zipFile, err := os.Create(outputPath)
	if err != nil {
		return nil, fmt.Errorf("failed to create zip file: %w", err)
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	defer zipWriter.Close()

	var filesCount int
	var totalBytes int64

	// Walk project directory and add all .db files
	err = filepath.Walk(projectDir, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			m.log.Warn("Skip file due to error", "path", path, "error", err)
			return nil // Continue walking
		}

		if info.IsDir() || !strings.HasSuffix(path, ".db") {
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
			return fmt.Errorf("failed to create zip entry: %w", err)
		}

		// Copy file content
		file, err := os.Open(path)
		if err != nil {
			return fmt.Errorf("failed to open file: %w", err)
		}
		defer file.Close()

		written, err := io.Copy(writer, file)
		if err != nil {
			return fmt.Errorf("failed to write to zip: %w", err)
		}

		filesCount++
		totalBytes += written

		return nil
	})

	if err != nil {
		return nil, fmt.Errorf("export failed: %w", err)
	}

	duration := time.Since(startTime)
	m.log.Info("Export complete",
		"project", projectSlug,
		"output", outputPath,
		"files_count", filesCount,
		"total_bytes", totalBytes,
		"duration_ms", duration.Milliseconds(),
	)

	return &ExportResult{
		OutputPath: outputPath,
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		Duration:   duration,
	}, nil
}

// ImportProjectFromZip imports databases from a zip file
func (m *DBManager) ImportProjectFromZip(zipPath, projectSlug string, overwrite bool) (*ImportResult, error) {
	startTime := time.Now()
	m.log.Info("Starting import", "zip", zipPath, "project", projectSlug, "overwrite", overwrite)

	// Open zip file
	reader, err := zip.OpenReader(zipPath)
	if err != nil {
		m.log.Error("Failed to open zip", "error", err, "zip", zipPath)
		return nil, fmt.Errorf("failed to open zip: %w", err)
	}
	defer reader.Close()

	projectDir := filepath.Join(m.dataDir, projectSlug)

	// Check if project exists
	if _, err := os.Stat(projectDir); err == nil && !overwrite {
		return nil, fmt.Errorf("project exists, use overwrite=true to replace")
	}

	// Close any open databases for this project
	m.mu.Lock()
	m.closeProjectDBs(projectSlug)
	m.mu.Unlock()

	// Remove existing directory if overwriting
	if overwrite {
		if err := os.RemoveAll(projectDir); err != nil {
			m.log.Warn("Failed to remove existing project directory", "error", err)
		}
	}

	// Create project directory
	if err := os.MkdirAll(projectDir, 0755); err != nil {
		return nil, fmt.Errorf("failed to create project directory: %w", err)
	}

	var filesCount int
	var totalBytes int64

	// Extract files
	for _, file := range reader.File {
		if file.FileInfo().IsDir() {
			continue
		}

		destPath := filepath.Join(projectDir, file.Name)

		m.log.Debug("Extracting", "file", file.Name, "size", file.UncompressedSize64)

		// Create directory structure
		if err := os.MkdirAll(filepath.Dir(destPath), 0755); err != nil {
			return nil, fmt.Errorf("failed to create directory: %w", err)
		}

		// Extract file
		written, err := m.extractZipFile(file, destPath)
		if err != nil {
			return nil, fmt.Errorf("failed to extract %s: %w", file.Name, err)
		}

		filesCount++
		totalBytes += written
	}

	// Register databases in root.db
	if err := m.registerImportedDatabases(projectSlug); err != nil {
		m.log.Warn("Failed to register databases", "error", err)
	}

	duration := time.Since(startTime)
	m.log.Info("Import complete",
		"project", projectSlug,
		"files_count", filesCount,
		"total_bytes", totalBytes,
		"duration_ms", duration.Milliseconds(),
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
		return 0, err
	}
	defer src.Close()

	dst, err := os.Create(destPath)
	if err != nil {
		return 0, err
	}
	defer dst.Close()

	return io.Copy(dst, src)
}

// registerImportedDatabases scans and registers databases after import
func (m *DBManager) registerImportedDatabases(projectSlug string) error {
	// Ensure project exists in root.db
	project, err := m.getOrCreateProject(projectSlug)
	if err != nil {
		return err
	}

	projectDir := filepath.Join(m.dataDir, projectSlug)

	return filepath.Walk(projectDir, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() || !strings.HasSuffix(path, ".db") {
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
		m.rootDB.QueryRow(`SELECT 1 FROM databases WHERE path = ?`, relPath).Scan(&exists)
		if exists == 1 {
			return nil
		}

		// Register database
		_, err = m.rootDB.Exec(`
			INSERT INTO databases (id, project_id, type, entity_id, path, size_bytes, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
		`, generateID(), project.ID, dbType, entityID, relPath, info.Size())

		return err
	})
}

// ExportByType exports only specific database types
func (m *DBManager) ExportByType(projectSlug string, dbTypes []string, outputPath string) (*ExportResult, error) {
	startTime := time.Now()
	m.log.Info("Selective export", "project", projectSlug, "types", dbTypes)

	// Filter databases by type
	dbs, err := m.ListDatabases(projectSlug)
	if err != nil {
		return nil, err
	}

	typeSet := make(map[string]bool)
	for _, t := range dbTypes {
		typeSet[t] = true
	}

	// Ensure output directory exists
	if err := os.MkdirAll(filepath.Dir(outputPath), 0755); err != nil {
		return nil, fmt.Errorf("failed to create output directory: %w", err)
	}

	// Create zip with only matching types
	zipFile, err := os.Create(outputPath)
	if err != nil {
		return nil, fmt.Errorf("failed to create zip file: %w", err)
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	defer zipWriter.Close()

	var filesCount int
	var totalBytes int64

	for _, db := range dbs {
		if !typeSet[db.Type] {
			continue
		}

		m.log.Debug("Including", "type", db.Type, "path", db.Path)

		fullPath := filepath.Join(m.dataDir, db.Path)
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
