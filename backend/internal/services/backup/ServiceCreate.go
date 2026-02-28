package backup

import (
	"context"
	"fmt"
	"os"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// Create downloads the current remote plugin and saves as a backup
func (s *Service) Create(ctx context.Context, mappingId int64) apperror.Result[models.Backup] {
	s.log.Info("Creating backup", "mappingId", mappingId)

	initLog := BackupLogInput{
		PluginId: mappingId,
		Step:     "init",
		Message:  "Starting backup creation",
		Details:  toDetails(InitDetails{MappingId: mappingId}),
	}
	s.logInfoWithDetails(initLog)

	pathResult := s.createBackupFile(mappingId)
	if pathResult.HasError() {
		return apperror.Fail[models.Backup](pathResult.AppError())
	}

	backupPath := pathResult.Value()
	backup := s.buildBackupModel(mappingId, backupPath)
	s.runRetentionPolicy(ctx, mappingId)
	s.logBackupComplete(mappingId, backupPath, backup.FileSize)

	return apperror.Ok(backup)
}

// createBackupFile generates the backup zip and returns the path.
func (s *Service) createBackupFile(mappingId int64) apperror.Result[string] {
	timestamp := time.Now().Format("20060102-150405")
	filename := fmt.Sprintf("backup-%d-%s.zip", mappingId, timestamp)

	backupPath, err := pathutil.Join(s.backupDir, filename)
	if err != nil {
		return apperror.FailWrap[string](err, apperror.ErrBackupCreate, "resolve backup path")
	}

	s.logInfo(mappingId, "prepare", fmt.Sprintf("Preparing backup file: %s", filename))

	file, createErr := os.Create(backupPath)
	if createErr != nil {
		s.logError(mappingId, "create", fmt.Sprintf("Failed to create backup file: %v", createErr))

		return apperror.FailWrap[string](createErr, apperror.ErrBackupCreate, "failed to create backup file")
	}
	file.Close()

	writeLog := BackupLogInput{
		PluginId: mappingId,
		Step:     "write",
		Message:  "Backup file created successfully",
		Details:  toDetails(PathDetails{Path: backupPath}),
	}
	s.logInfoWithDetails(writeLog)

	return apperror.Ok(backupPath)
}

// buildBackupModel creates the backup model struct.
func (s *Service) buildBackupModel(mappingId int64, backupPath string) models.Backup {
	return models.Backup{
		PluginMappingId: mappingId,
		FilePath:        backupPath,
		FileSize:        pathutil.FileSize(backupPath),
		CreatedAt:       time.Now(),
	}
}

// runRetentionPolicy enforces the retention policy and logs warnings on failure.
func (s *Service) runRetentionPolicy(ctx context.Context, mappingId int64) {
	retentionDetails := toDetails(RetentionDetails{
		MaxPerPlugin:  s.maxPerPlugin,
		RetentionDays: s.retentionDays,
	})
	retentionLog := BackupLogInput{
		PluginId: mappingId,
		Step:     "retention",
		Message:  "Enforcing retention policy",
		Details:  retentionDetails,
	}
	s.logInfoWithDetails(retentionLog)

	appErr := s.enforceRetention(ctx, mappingId)
	if appErr != nil {
		s.log.Warn("Failed to enforce retention", "error", appErr)
		s.logWarn(mappingId, "retention", fmt.Sprintf("Retention enforcement warning: %v", appErr))
	}
}

// logBackupComplete broadcasts the completion log.
func (s *Service) logBackupComplete(mappingId int64, backupPath string, size int64) {
	s.log.Info("Backup created", "mappingId", mappingId, "path", backupPath)
	completeDetails := toDetails(BackupCompleteDetails{Path: backupPath, FileSize: size})

	completeLog := BackupLogInput{
		PluginId: mappingId,
		Step:     "complete",
		Message:  "Backup created successfully",
		Details:  completeDetails,
	}
	s.logInfoWithDetails(completeLog)
}
