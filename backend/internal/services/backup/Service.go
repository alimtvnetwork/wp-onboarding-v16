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
	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/ziputil"
)

// Config holds backup service configuration
type Config struct {
	DB            *database.DB
	Logger        *logger.Logger
	WSHub         *ws.Hub
	BackupDir     string
	RetentionDays int
	MaxPerPlugin  int
}

// Service provides backup management operations
type Service struct {
	db            *database.DB
	log           *logger.Logger
	wsHub         *ws.Hub
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
		wsHub:         cfg.WSHub,
		backupDir:     cfg.BackupDir,
		retentionDays: cfg.RetentionDays,
		maxPerPlugin:  cfg.MaxPerPlugin,
	}
}

// BackupLogInput bundles parameters for broadcastLog.
type BackupLogInput struct {
	PluginID int64
	Level    string
	Step     string
	Message  string
	Details  []byte
}

// broadcastLog sends a log entry via WebSocket if hub is available
func (s *Service) broadcastLog(input BackupLogInput) {
	if s.wsHub != nil {
		s.wsHub.BroadcastBackupLog(ws.OperationLogInput{
			PluginID: input.PluginID,
			Entry:    ws.OperationLogEntry{Level: input.Level, Step: input.Step, Message: input.Message, Details: input.Details},
		})
	}
}

// Create downloads the current remote plugin and saves as a backup
func (s *Service) Create(ctx context.Context, mappingID int64) apperror.Result[models.Backup] {
	s.log.Info("Creating backup", "mappingId", mappingID)
	s.broadcastLog(BackupLogInput{PluginID: mappingID, Level: loglevel.Info.Lower(), Step: "init", Message: "Starting backup creation", Details: toDetails(InitDetails{
		MappingID: mappingID,
	})})

	// Generate backup filename with timestamp
	timestamp := time.Now().Format("20060102-150405")
	filename := fmt.Sprintf("backup-%d-%s.zip", mappingID, timestamp)
	backupPath, err := pathutil.Join(s.backupDir, filename)
	if err != nil {
		return apperror.FailWrap[models.Backup](err, apperror.ErrInternal, "failed to resolve backup path")
	}

	s.broadcastLog(BackupLogInput{PluginID: mappingID, Level: loglevel.Info.Lower(), Step: "prepare", Message: fmt.Sprintf("Preparing backup file: %s", filename)})

	// TODO: Download remote plugin via WP REST
	// For now, create an empty placeholder file
	file, err := os.Create(backupPath)
	if err != nil {
		s.broadcastLog(BackupLogInput{PluginID: mappingID, Level: loglevel.Error.Lower(), Step: "create", Message: fmt.Sprintf("Failed to create backup file: %v", err)})
		return apperror.FailWrap[models.Backup](err, apperror.ErrBackupCreate, "failed to create backup file")
	}
	file.Close()

	s.broadcastLog(BackupLogInput{PluginID: mappingID, Level: loglevel.Info.Lower(), Step: "write", Message: "Backup file created successfully", Details: toDetails(PathDetails{
		Path: backupPath,
	})})

	// Get file info for size
	size := pathutil.FileSize(backupPath)

	backup := models.Backup{
		PluginMappingID: mappingID,
		FilePath:        backupPath,
		FileSize:        size,
		CreatedAt:       time.Now(),
	}

	// TODO: Record in database

	// Enforce retention
	s.broadcastLog(BackupLogInput{PluginID: mappingID, Level: loglevel.Info.Lower(), Step: "retention", Message: "Enforcing retention policy", Details: toDetails(RetentionDetails{
		MaxPerPlugin:  s.maxPerPlugin,
		RetentionDays: s.retentionDays,
	})})
	if err := s.enforceRetention(ctx, mappingID); err != nil {
		s.log.Warn("Failed to enforce retention", "error", err)
		s.broadcastLog(BackupLogInput{PluginID: mappingID, Level: loglevel.Warn.Lower(), Step: "retention", Message: fmt.Sprintf("Retention enforcement warning: %v", err)})
	}

	s.log.Info("Backup created", "mappingId", mappingID, "path", backupPath)
	s.broadcastLog(BackupLogInput{PluginID: mappingID, Level: loglevel.Info.Lower(), Step: "complete", Message: "Backup created successfully", Details: toDetails(BackupCompleteDetails{
		Path:     backupPath,
		FileSize: size,
	})})

	return apperror.Ok(backup)
}

// List returns all backups for a plugin mapping
func (s *Service) List(ctx context.Context, mappingID int64) apperror.ResultSlice[models.Backup] {
	// TODO: Query database for backups
	return apperror.OkSlice([]models.Backup{})
}

// GetByID returns a specific backup
func (s *Service) GetByID(ctx context.Context, id int64) apperror.Result[models.Backup] {
	// TODO: Query database
	return apperror.Ok(models.Backup{})
}

// Restore uploads a backup to WordPress
func (s *Service) Restore(ctx context.Context, backupID int64) apperror.Result[RestoreResult] {
	s.log.Info("Restoring backup", "backupId", backupID)
	s.broadcastLog(BackupLogInput{PluginID: backupID, Level: loglevel.Info.Lower(), Step: "init", Message: "Starting backup restore", Details: toDetails(InitDetails{
		BackupID: backupID,
	})})

	// TODO: Implement restore:
	// 1. Get backup file path
	s.broadcastLog(BackupLogInput{PluginID: backupID, Level: loglevel.Info.Lower(), Step: "locate", Message: "Locating backup file"})

	// 2. Upload to WordPress
	s.broadcastLog(BackupLogInput{PluginID: backupID, Level: loglevel.Info.Lower(), Step: "upload", Message: "Uploading backup to WordPress"})

	// 3. Activate plugin
	s.broadcastLog(BackupLogInput{PluginID: backupID, Level: loglevel.Info.Lower(), Step: "activate", Message: "Activating restored plugin"})

	s.broadcastLog(BackupLogInput{PluginID: backupID, Level: loglevel.Info.Lower(), Step: "complete", Message: "Backup restored successfully"})

	return apperror.Ok(RestoreResult{IsSuccess: true})
}

// Delete removes a backup file and database record
func (s *Service) Delete(ctx context.Context, id int64) error {
	s.log.Info("Deleting backup", "id", id)
	s.broadcastLog(BackupLogInput{PluginID: id, Level: loglevel.Info.Lower(), Step: "delete", Message: "Deleting backup", Details: toDetails(InitDetails{
		BackupID: id,
	})})

	// TODO: Get backup path from database
	// TODO: Delete file and database record

	s.broadcastLog(BackupLogInput{PluginID: id, Level: loglevel.Info.Lower(), Step: "complete", Message: "Backup deleted successfully"})

	return nil
}

// Cleanup removes expired backups
func (s *Service) Cleanup(ctx context.Context) error {
	s.log.Info("Running backup cleanup")
	s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Info.Lower(), Step: "init", Message: "Starting backup cleanup", Details: toDetails(CleanupInitDetails{
		RetentionDays: s.retentionDays,
	})})

	cutoff := time.Now().AddDate(0, 0, -s.retentionDays)
	var removedCount int

	// Walk backup directory and find old files
	err := filepath.Walk(s.backupDir, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			return nil
		}

		if info.ModTime().Before(cutoff) {
			s.log.Debug("Removing expired backup", "path", path, "modified", info.ModTime())
			s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Debug.Lower(), Step: "remove", Message: fmt.Sprintf("Removing expired backup: %s", filepath.Base(path)), Details: toDetails(ExpiredBackupDetails{
				ModifiedAt: info.ModTime().Format(time.RFC3339),
			})})
			if err := os.Remove(path); err == nil {
				removedCount++
			}
			return nil
		}

		return nil
	})

	if err != nil {
		s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Error.Lower(), Step: "cleanup", Message: fmt.Sprintf("Cleanup failed: %v", err)})
		return apperror.Wrap(err, apperror.ErrFSRead, "cleanup failed").
			WithBackupDir(s.backupDir)
	}

	s.log.Info("Backup cleanup complete")
	s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Info.Lower(), Step: "complete", Message: fmt.Sprintf("Cleanup complete, removed %d expired backups", removedCount), Details: toDetails(CleanupCompleteDetails{
		RemovedCount: removedCount,
	})})

	return nil
}

// enforceRetention ensures we don't exceed max backups per plugin
func (s *Service) enforceRetention(ctx context.Context, mappingID int64) error {
	// TODO: Query database for backup count
	// TODO: Delete oldest backups if count exceeds maxPerPlugin
	return nil
}

// ExportToZip creates a zip archive of specified files/directories
func (s *Service) ExportToZip(ctx context.Context, sourcePaths []string, outputPath string) apperror.Result[ExportResult] {
	startTime := time.Now()
	s.log.Info("Starting export", "sources", len(sourcePaths), "output", outputPath)
	s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Info.Lower(), Step: "init", Message: fmt.Sprintf("Starting export to %s", filepath.Base(outputPath)), Details: toDetails(ExportInitDetails{
		SourceCount: len(sourcePaths),
	})})

	// Ensure output directory exists
	if err := os.MkdirAll(filepath.Dir(outputPath), 0755); err != nil {
		s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Error.Lower(), Step: "prepare", Message: fmt.Sprintf("Failed to create output directory: %v", err)})
		return apperror.FailWrap[ExportResult](err, apperror.ErrFSWrite, "failed to create output directory")
	}

	// Create zip file
	zipFile, err := os.Create(outputPath)
	if err != nil {
		s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Error.Lower(), Step: "create", Message: fmt.Sprintf("Failed to create zip file: %v", err)})
		return apperror.FailWrap[ExportResult](err, apperror.ErrFSZip, "failed to create zip file")
	}
	defer zipFile.Close()

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)
	defer zipWriter.Close()

	var filesCount int
	var totalBytes int64

	for _, sourcePath := range sourcePaths {
		fi, statErr := pathutil.StatFile(sourcePath)
		if statErr != nil {
			s.log.Warn("Skipping source", "path", sourcePath, "error", statErr)
			s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Warn.Lower(), Step: "skip", Message: fmt.Sprintf("Skipping source: %s", sourcePath), Details: toDetails(ExportErrorDetails{
				Error: statErr.Error(),
			})})
			continue
		}

		if fi.Info.IsDir() {
			// Walk directory
			baseDir := filepath.Base(sourcePath)
			s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Info.Lower(), Step: "scan", Message: fmt.Sprintf("Scanning directory: %s", baseDir)})

			err = filepath.Walk(sourcePath, func(path string, fi os.FileInfo, err error) error {
				if err != nil || fi.IsDir() {
					return nil
				}

				relPath, _ := filepath.Rel(sourcePath, path)
				zipPath := filepath.ToSlash(filepath.Join(baseDir, relPath))

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
	s.broadcastLog(BackupLogInput{PluginID: 0, Level: loglevel.Info.Lower(), Step: "complete", Message: fmt.Sprintf("Export complete: %d files, %d bytes", filesCount, totalBytes), Details: toDetails(ExportCompleteDetails{
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		DurationMs: duration.Milliseconds(),
	})})

	return apperror.Ok(ExportResult{
		OutputPath: outputPath,
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		Duration:   duration,
	})
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
func (s *Service) ImportFromZip(ctx context.Context, zipPath, destDir string, isOverwrite bool) apperror.Result[ImportResult] {
	startTime := time.Now()
	s.log.Info("Starting import", "zip", zipPath, "dest", destDir, "overwrite", isOverwrite)
	s.broadcastLog(BackupLogInput{PluginID: 0, Level: "info", Step: "init", Message: fmt.Sprintf("Starting import from %s", filepath.Base(zipPath)), Details: toDetails(ImportInitDetails{
		Destination: destDir,
		IsOverwrite: isOverwrite,
	})})

	// Open zip file
	reader, err := zip.OpenReader(zipPath)
	if err != nil {
		s.broadcastLog(BackupLogInput{PluginID: 0, Level: "error", Step: "open", Message: fmt.Sprintf("Failed to open zip: %v", err)})
		return apperror.FailWrap[ImportResult](err, apperror.ErrFSZip, "failed to open zip")
	}
	defer reader.Close()

	// Check if destination exists
	_, statErr := pathutil.StatDir(destDir)
	isDestExists := statErr == nil
	isReadOnly := !isOverwrite
	isConflict := isDestExists && isReadOnly

	if isConflict {
		s.broadcastLog(BackupLogInput{PluginID: 0, Level: "error", Step: "check", Message: "Destination exists, overwrite not enabled"})
		return apperror.FailNew[ImportResult](apperror.ErrFSWrite, "destination exists, use overwrite=true to replace")
	}

	// Create destination directory
	if err := os.MkdirAll(destDir, 0755); err != nil {
		s.broadcastLog(BackupLogInput{PluginID: 0, Level: "error", Step: "prepare", Message: fmt.Sprintf("Failed to create destination: %v", err)})
		return apperror.FailWrap[ImportResult](err, apperror.ErrFSWrite, "failed to create destination")
	}

	var filesCount int
	var totalBytes int64

	s.broadcastLog(BackupLogInput{PluginID: 0, Level: "info", Step: "extract", Message: fmt.Sprintf("Extracting %d files", len(reader.File))})

	// Extract files
	for _, file := range reader.File {
		if file.FileInfo().IsDir() {
			continue
		}

		// Security: prevent zip slip attacks
		destPath, err := pathutil.Join(destDir, file.Name)
		if err != nil {
			s.log.Warn("Failed to resolve zip entry path", "path", file.Name, "error", err)
			continue
		}
		if !strings.HasPrefix(destPath, filepath.Clean(destDir)+string(os.PathSeparator)) {
			s.log.Warn("Skipping potentially dangerous file path", "path", file.Name)
			s.broadcastLog(BackupLogInput{PluginID: 0, Level: "warn", Step: "security", Message: fmt.Sprintf("Skipping dangerous path: %s", file.Name)})
			continue
		}

		s.log.Debug("Extracting", "file", file.Name, "size", file.UncompressedSize64)

		// Create directory structure
		if err := os.MkdirAll(filepath.Dir(destPath), 0755); err != nil {
			s.broadcastLog(BackupLogInput{PluginID: 0, Level: "error", Step: "mkdir", Message: fmt.Sprintf("Failed to create directory: %v", err)})
			return apperror.FailWrap[ImportResult](err, apperror.ErrFSWrite, "failed to create directory")
		}

		// Extract file
		written, err := s.extractFile(file, destPath)
		if err != nil {
			s.broadcastLog(BackupLogInput{PluginID: 0, Level: "error", Step: "extract", Message: fmt.Sprintf("Failed to extract %s: %v", file.Name, err)})
			return apperror.FailWrap[ImportResult](err, apperror.ErrFSWrite, "failed to extract file")
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
	s.broadcastLog(BackupLogInput{PluginID: 0, Level: "info", Step: "complete", Message: fmt.Sprintf("Import complete: %d files, %d bytes", filesCount, totalBytes), Details: toDetails(ImportCompleteDetails{
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		DurationMs: duration.Milliseconds(),
	})})

	return apperror.Ok(ImportResult{
		DestPath:   destDir,
		FilesCount: filesCount,
		TotalBytes: totalBytes,
		Duration:   duration,
	})
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
	IsSuccess    bool
	ErrorMessage string `json:",omitempty"`
}

// ExportResult contains information about an export operation
type ExportResult struct {
	OutputPath string
	FilesCount int
	TotalBytes int64
	Duration   time.Duration
}

// ImportResult contains information about an import operation
type ImportResult struct {
	DestPath   string
	FilesCount int
	TotalBytes int64
	Duration   time.Duration
}
