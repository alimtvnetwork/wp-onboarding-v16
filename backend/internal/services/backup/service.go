// Package backup provides plugin backup and restore functionality
package backup

import (
	"archive/zip"
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
)

// Config holds backup service configuration
type Config struct {
	DB            *database.DB
	Logger        *logger.Logger
	BackupDir     string
	RetentionDays int
	MaxPerPlugin  int
}

// Service provides backup management operations
type Service struct {
	db            *database.DB
	log           *logger.Logger
	backupDir     string
	retentionDays int
	maxPerPlugin  int
}

// New creates a new backup service
func New(cfg Config) *Service {
	// Ensure backup directory exists
	if err := os.MkdirAll(cfg.BackupDir, 0755); err != nil {
		cfg.Logger.Error("Failed to create backup directory", "error", err)
	}

	return &Service{
		db:            cfg.DB,
		log:           cfg.Logger,
		backupDir:     cfg.BackupDir,
		retentionDays: cfg.RetentionDays,
		maxPerPlugin:  cfg.MaxPerPlugin,
	}
}

// Create downloads the current remote plugin and saves as a backup
func (s *Service) Create(ctx context.Context, mappingID int64) (*models.Backup, error) {
	s.log.Info("Creating backup", "mapping_id", mappingID)

	// Generate backup filename with timestamp
	timestamp := time.Now().Format("20060102-150405")
	filename := fmt.Sprintf("backup-%d-%s.zip", mappingID, timestamp)
	backupPath := filepath.Join(s.backupDir, filename)

	// TODO: Download remote plugin via WP REST
	// For now, create an empty placeholder file
	file, err := os.Create(backupPath)
	if err != nil {
		return nil, fmt.Errorf("failed to create backup file: %w", err)
	}
	file.Close()

	// Get file info for size
	info, _ := os.Stat(backupPath)
	var size int64
	if info != nil {
		size = info.Size()
	}

	backup := &models.Backup{
		PluginMappingID: mappingID,
		FilePath:        backupPath,
		FileSize:        size,
		CreatedAt:       time.Now(),
	}

	// TODO: Record in database

	// Enforce retention
	if err := s.enforceRetention(ctx, mappingID); err != nil {
		s.log.Warn("Failed to enforce retention", "error", err)
	}

	s.log.Info("Backup created", "mapping_id", mappingID, "path", backupPath)
	return backup, nil
}

// List returns all backups for a plugin mapping
func (s *Service) List(ctx context.Context, mappingID int64) ([]models.Backup, error) {
	// TODO: Query database for backups
	return []models.Backup{}, nil
}

// GetByID returns a specific backup
func (s *Service) GetByID(ctx context.Context, id int64) (*models.Backup, error) {
	// TODO: Query database
	return nil, nil
}

// Restore uploads a backup to WordPress
func (s *Service) Restore(ctx context.Context, backupID int64) (*RestoreResult, error) {
	s.log.Info("Restoring backup", "backup_id", backupID)

	// TODO: Implement restore:
	// 1. Get backup file path
	// 2. Upload to WordPress
	// 3. Activate plugin

	return &RestoreResult{Success: true}, nil
}

// Delete removes a backup file and database record
func (s *Service) Delete(ctx context.Context, id int64) error {
	s.log.Info("Deleting backup", "id", id)

	// TODO: Get backup path from database
	// TODO: Delete file and database record

	return nil
}

// Cleanup removes expired backups
func (s *Service) Cleanup(ctx context.Context) error {
	s.log.Info("Running backup cleanup")

	cutoff := time.Now().AddDate(0, 0, -s.retentionDays)

	// Walk backup directory and find old files
	err := filepath.Walk(s.backupDir, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			return nil
		}

		if info.ModTime().Before(cutoff) {
			s.log.Debug("Removing expired backup", "path", path, "modified", info.ModTime())
			return os.Remove(path)
		}

		return nil
	})

	if err != nil {
		return fmt.Errorf("cleanup failed: %w", err)
	}

	s.log.Info("Backup cleanup complete")
	return nil
}

// enforceRetention ensures we don't exceed max backups per plugin
func (s *Service) enforceRetention(ctx context.Context, mappingID int64) error {
	// TODO: Query database for backup count
	// TODO: Delete oldest backups if count exceeds maxPerPlugin
	return nil
}

// ExportToZip creates a zip archive of specified files/directories
func (s *Service) ExportToZip(ctx context.Context, sourcePaths []string, outputPath string) (*ExportResult, error) {
	startTime := time.Now()
	s.log.Info("Starting export", "sources", len(sourcePaths), "output", outputPath)

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

	for _, sourcePath := range sourcePaths {
		info, err := os.Stat(sourcePath)
		if err != nil {
			s.log.Warn("Skipping source", "path", sourcePath, "error", err)
			continue
		}

		if info.IsDir() {
			// Walk directory
			baseDir := filepath.Base(sourcePath)
			err = filepath.Walk(sourcePath, func(path string, fi os.FileInfo, err error) error {
				if err != nil || fi.IsDir() {
					return nil
				}

				relPath, _ := filepath.Rel(sourcePath, path)
				zipPath := filepath.Join(baseDir, relPath)

				written, err := s.addFileToZip(zipWriter, path, zipPath)
				if err != nil {
					s.log.Warn("Failed to add file", "path", path, "error", err)
					return nil
				}

				filesCount++
				totalBytes += written
				return nil
			})
			if err != nil {
				s.log.Warn("Failed to walk directory", "path", sourcePath, "error", err)
			}
		} else {
			// Single file
			written, err := s.addFileToZip(zipWriter, sourcePath, filepath.Base(sourcePath))
			if err != nil {
				s.log.Warn("Failed to add file", "path", sourcePath, "error", err)
				continue
			}
			filesCount++
			totalBytes += written
		}
	}

	duration := time.Since(startTime)
	s.log.Info("Export complete",
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

// addFileToZip adds a single file to a zip archive
func (s *Service) addFileToZip(zw *zip.Writer, sourcePath, zipPath string) (int64, error) {
	file, err := os.Open(sourcePath)
	if err != nil {
		return 0, err
	}
	defer file.Close()

	info, err := file.Stat()
	if err != nil {
		return 0, err
	}

	header := &zip.FileHeader{
		Name:     zipPath,
		Method:   zip.Deflate,
		Modified: info.ModTime(),
	}

	writer, err := zw.CreateHeader(header)
	if err != nil {
		return 0, err
	}

	return io.Copy(writer, file)
}

// ImportFromZip extracts a zip archive to the specified directory
func (s *Service) ImportFromZip(ctx context.Context, zipPath, destDir string, overwrite bool) (*ImportResult, error) {
	startTime := time.Now()
	s.log.Info("Starting import", "zip", zipPath, "dest", destDir, "overwrite", overwrite)

	// Open zip file
	reader, err := zip.OpenReader(zipPath)
	if err != nil {
		return nil, fmt.Errorf("failed to open zip: %w", err)
	}
	defer reader.Close()

	// Check if destination exists
	if _, err := os.Stat(destDir); err == nil && !overwrite {
		return nil, fmt.Errorf("destination exists, use overwrite=true to replace")
	}

	// Create destination directory
	if err := os.MkdirAll(destDir, 0755); err != nil {
		return nil, fmt.Errorf("failed to create destination: %w", err)
	}

	var filesCount int
	var totalBytes int64

	// Extract files
	for _, file := range reader.File {
		if file.FileInfo().IsDir() {
			continue
		}

		// Security: prevent zip slip attacks
		destPath := filepath.Join(destDir, file.Name)
		if !strings.HasPrefix(destPath, filepath.Clean(destDir)+string(os.PathSeparator)) {
			s.log.Warn("Skipping potentially dangerous file path", "path", file.Name)
			continue
		}

		s.log.Debug("Extracting", "file", file.Name, "size", file.UncompressedSize64)

		// Create directory structure
		if err := os.MkdirAll(filepath.Dir(destPath), 0755); err != nil {
			return nil, fmt.Errorf("failed to create directory: %w", err)
		}

		// Extract file
		written, err := s.extractFile(file, destPath)
		if err != nil {
			return nil, fmt.Errorf("failed to extract %s: %w", file.Name, err)
		}

		filesCount++
		totalBytes += written
	}

	duration := time.Since(startTime)
	s.log.Info("Import complete",
		"dest", destDir,
		"files_count", filesCount,
		"total_bytes", totalBytes,
		"duration_ms", duration.Milliseconds(),
	)

	return &ImportResult{
		DestPath:   destDir,
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		Duration:   duration,
	}, nil
}

// extractFile extracts a single file from a zip archive
func (s *Service) extractFile(file *zip.File, destPath string) (int64, error) {
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

// RestoreResult represents the result of a restore operation
type RestoreResult struct {
	Success      bool   `json:"success"`
	ErrorMessage string `json:"errorMessage,omitempty"`
}

// ExportResult contains information about an export operation
type ExportResult struct {
	OutputPath string        `json:"outputPath"`
	FilesCount int           `json:"filesCount"`
	TotalBytes int64         `json:"totalBytes"`
	Duration   time.Duration `json:"duration"`
}

// ImportResult contains information about an import operation
type ImportResult struct {
	DestPath   string        `json:"destPath"`
	FilesCount int           `json:"filesCount"`
	TotalBytes int64         `json:"totalBytes"`
	Duration   time.Duration `json:"duration"`
}
